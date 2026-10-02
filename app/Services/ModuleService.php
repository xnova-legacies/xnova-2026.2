<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Acl;
use App\Core\GameConfig;
use App\Core\Language;
use App\Core\Modules;
use App\Core\Project;
use App\Core\Request;
use App\Core\Response;
use App\Core\TemplateEngine;
use App\Entities\Module;
use App\Repositories\ModuleRepository;

/**
 * État des modules du jeu : allumés ou éteints, et pour qui.
 *
 * `App\Core\Modules` dit ce qui existe (libellé, page, routes, permission) et
 * `App\Core\Acl::manages()` dit qui gère qui ; ce service fait le lien avec la base
 * (`table modules`) et **garde les routes** : avant qu'une page ou une route JSON
 * d'un module éteint ne s'exécute, `index.php` lui demande la réponse de refus.
 *
 * Deux règles, dans cet ordre :
 *  - module **éteint** : la page, le menu et les routes JSON disparaissent pour tout
 *    la planète, **sauf** le compte d'installation et les rôles qui peuvent
 *    administrer les modules — c'est eux qui le rallument, il leur faut le voir ;
 *  - module **allumé** : un rôle qui porte `module.<nom>` l'ouvre, un rôle qui ne le
 *    porte pas le ferme, et un compte **sans rôle** garde l'accès (comportement
 *    d'avant l'ACL : sinon tous les joueurs perdraient le tchat d'un coup).
 */
final class ModuleService
{
    /** Interrupteurs historiques de la table `config`, repris par le semis. */
    private const LEGACY_SWITCHES = array(
        'marchand' => 'enable_marchand',
        'annonces' => 'enable_announces',
    );

    /** Filtres d'état de la liste des modules (page du panneau). */
    public const FILTERS = array('installed', 'archived', 'all');

    /** Filtre par défaut : les modules **déposés**, comme les listes du panneau. */
    public const FILTER_DEFAULT = 'installed';

    /**
     * Colonnes triables de la liste : clé de l'URL => clé de la ligne.
     *
     * Liste **blanche**, comme les `SORTS` d'un dépôt : une clé inconnue retombe sur le
     * nom, jamais sur une valeur venue de l'URL.
     */
    public const SORTS = array(
        'name' => 'name',
        'label' => 'label',
        'description' => 'description',
        'permission' => 'permission',
        'address' => 'page',
    );

    /**
     * État de tous les modules, lu une fois par requête.
     *
     * @var array<string, array{active: bool, settings: array<string, mixed>}>|null
     */
    private static ?array $states = null;

    public function __construct(
        private readonly ModuleRepository $modules = new ModuleRepository(),
        private readonly AclService $acl = new AclService(),
    ) {
    }

    /**
     * Catalogue et état réunis, dans l'ordre du catalogue.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $states = $this->states();
        $modules = array();

        foreach (Modules::all() as $name => $definition) {
            $modules[$name] = array(
                'name' => $name,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'page' => $definition['page'],
                'permission' => Modules::permission($name),
                'permissions' => Modules::permissions($name),
                'version' => (string) ($definition['version'] ?? ''),
                'author' => (string) ($definition['author'] ?? ''),
                'native' => (bool) ($definition['native'] ?? false),
                'active' => $states[$name]['active'],
                'settings' => $states[$name]['settings'],
                'settings_schema' => Modules::settingsSchema($name),
                'dependencies' => Modules::dependencies($name),
                'dependency_problems' => $this->dependencyProblems($name),
            );
        }

        return $modules;
    }

    /**
     * Liste des modules pour la page du panneau : les **déposés**, les **archivés**, ou
     * les deux, filtrés par une recherche libre.
     *
     * Tous ont la **même forme**, pour que le gabarit n'ait qu'une ligne à remplir —
     * l'état (`archived`, `active`) décide seulement de ce qui est montré.
     *
     * @param string $filter une valeur de `FILTERS`
     * @param string $search recherche libre (nom, libellé, description, permission, adresse)
     * @param array<string, mixed> $lang libellés du jeu **et** des modules, pour chercher ce
     *        que l'administrateur lit : un manifeste ne porte que des clés (`mod_chat`)
     * @return list<array<string, mixed>>
     */
    public function listing(string $filter = self::FILTER_DEFAULT, string $search = '', array $lang = array()): array
    {
        $modules = array();

        if ($filter !== 'archived') {
            foreach ($this->all() as $module) {
                $modules[] = $module + array('archived' => false);
            }
        }

        if ($filter !== 'installed') {
            foreach (Modules::archived() as $manifest) {
                $modules[] = self::describeArchived($manifest);
            }
        }

        // Le libellé et la description d'un manifeste sont des **clés** de langue : la
        // recherche porte sur ce qui s'affiche, sinon « tchat » ne trouverait pas le
        // tchat (dont le nom technique est `chat`).
        foreach ($modules as $index => $module) {
            $modules[$index]['label_text'] = (string) ($lang[(string) $module['label']] ?? $module['label']);
            $modules[$index]['description_text'] = (string) ($lang[(string) $module['description']] ?? $module['description']);
        }

        return self::search($modules, $search);
    }

    /**
     * Filtre d'état demandé, ramené à une valeur connue.
     */
    public static function listFilter(mixed $value): string
    {
        $filter = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        return in_array($filter, self::FILTERS, true) ? $filter : self::FILTER_DEFAULT;
    }

    /**
     * Recherche libre sur une liste de modules. Fonction **pure** : elle ne lit que la
     * liste qu'on lui donne.
     *
     * Elle regarde le nom technique, le libellé (brut **et** traduit, `label_text`), la
     * description, la permission, l'adresse et l'auteur.
     *
     * @param list<array<string, mixed>> $modules
     * @return list<array<string, mixed>>
     */
    public static function search(array $modules, string $term): array
    {
        $needle = mb_strtolower(trim($term));

        if ($needle === '') {
            return $modules;
        }

        return array_values(array_filter(
            $modules,
            static function (array $module) use ($needle): bool {
                foreach (array('name', 'label', 'label_text', 'description', 'description_text', 'permission', 'page', 'author') as $key) {
                    if (str_contains(mb_strtolower((string) ($module[$key] ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /**
     * Tri d'une liste de modules par une colonne **déclarée** (`SORTS`).
     *
     * La comparaison est naturelle et insensible à la casse (« 10 » se range après
     * « 9 »), et une colonne inconnue retombe sur le nom : jamais une valeur de l'URL.
     *
     * @param list<array<string, mixed>> $modules
     * @return list<array<string, mixed>>
     */
    public static function sort(array $modules, string $field, string $order): array
    {
        $key = self::SORTS[$field] ?? 'name';
        $direction = strtolower($order) === 'desc' ? -1 : 1;

        usort($modules, static function (array $left, array $right) use ($key, $direction): int {
            return $direction * strnatcasecmp((string) ($left[$key] ?? ''), (string) ($right[$key] ?? ''));
        });

        return $modules;
    }

    /**
     * Un module **archivé**, décrit comme un module déposé.
     *
     * Il n'est plus dans le catalogue (`all()`) : la page a pourtant besoin de la même
     * forme pour le lister, l'annoncer et le réinstaller — tout vient de son manifeste,
     * qui a suivi le module dans l'archive.
     *
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private static function describeArchived(array $manifest): array
    {
        $name = (string) $manifest['name'];

        return array(
            'name' => $name,
            'label' => (string) $manifest['label'],
            'description' => (string) $manifest['description'],
            'page' => (string) $manifest['page'],
            'permission' => Modules::permission($name),
            'permissions' => Modules::permissions($name),
            'version' => (string) ($manifest['version'] ?? ''),
            'author' => (string) ($manifest['author'] ?? ''),
            'native' => (bool) ($manifest['native'] ?? false),
            'active' => false,
            'archived' => true,
            'dependencies' => (array) ($manifest['dependencies'] ?? array('core' => '', 'modules' => array())),
            // Un module archivé n'attend aucune dépendance : il n'est simplement plus là.
            'dependency_problems' => array(),
        );
    }

    /**
     * Ce qui manque à un module pour tourner (Coeur d'application trop ancien, module absent ou
     * éteint).
     *
     * La version du Coeur d'application vient de la constante `VERSION` (`common.php`), qui n'existe
     * pas en ligne de commande : sans elle, on ne se plaint pas (mieux vaut ne rien
     * dire qu'interdire un module sur une information manquante).
     *
     * @return array<string, string>
     */
    public function dependencyProblems(string $name): array
    {
        return Modules::dependencyProblems($name, $this->coreVersion(), Modules::names(), $this->disabledNames());
    }

    /**
     * Modules déposés mais éteints (la table ne porte que l'état) : c'est ce qui
     * distingue « à rallumer » de « à déposer ».
     *
     * @return array<int, string>
     */
    private function disabledNames(): array
    {
        $states = $this->states();
        $disabled = array();

        foreach (Modules::names() as $name) {
            if (($states[$name]['active'] ?? true) !== true) {
                $disabled[] = $name;
            }
        }

        return $disabled;
    }

    /**
     * Ce qui manque à un module, en clair : « Coeur d'application ≥ 2026.6, absent : Tchat,
     * éteint : Notes ». Les libellés des modules viennent de leur module.
     */
    public function dependencyText(string $name): string
    {
        $problems = $this->dependencyProblems($name);

        if ($problems === array()) {
            return '';
        }

        Language::include('modules');
        $lang = Language::all();
        $parts = array();

        foreach ($problems as $kind => $detail) {
            if ($kind === 'core') {
                $parts[] = trim((string) ($lang['mod_dep_core'] ?? '') . ' ' . str_replace(
                    array('>=', '<='),
                    array('&ge;', '&le;'),
                    $detail
                ));

                continue;
            }

            $key = str_starts_with($kind, 'off:') ? 'mod_dep_off' : 'mod_dep_missing';
            $required = (string) substr($kind, (int) strpos($kind, ':') + 1);
            Language::includeModule($required);

            $parts[] = trim((string) ($lang[$key] ?? '') . ' ' . (string) (Language::all()[Modules::label($required)] ?? $required));
        }

        return implode(', ', $parts);
    }

    /**
     * Modules qui dépendent de celui-ci et qui sont allumés : tant qu'ils tournent,
     * on ne peut pas l'éteindre (sinon leurs pages cesseraient de répondre).
     *
     * @return array<int, string>
     */
    public function dependants(string $name): array
    {
        $dependants = array();

        foreach (Modules::names() as $other) {
            if ($other === $name || !$this->isActive($other)) {
                continue;
            }

            if (in_array($name, (array) Modules::dependencies($other)['modules'], true)) {
                $dependants[] = $other;
            }
        }

        return $dependants;
    }

    /** Version du Coeur d'application, lue dans le manifeste du projet (`Project::version()`). */
    public function coreVersion(): string
    {
        return Project::version();
    }

    /** Réglages d'un module (tableau vide pour un module inconnu ou sans réglage). */
    public function settings(string $name): array
    {
        $states = $this->states();

        return $states[$name]['settings'] ?? array();
    }

    /**
     * Le module est-il allumé ? Un module sans ligne en base l'est : le catalogue
     * fait foi, la table ne porte que les réglages.
     */
    public function isActive(string $name): bool
    {
        $states = $this->states();

        return $states[$name]['active'] ?? true;
    }

    /**
     * Le compte peut-il administrer les modules ? Le compte d'installation, par
     * définition, et tout rôle qui porte la page « Modules ».
     *
     * @param array<string, mixed> $user
     */
    public function canAdminister(array $user): bool
    {
        return Acl::isSuperAdmin($user) || $this->acl->can($user, 'admin.modules');
    }

    /**
     * Le module est-il **utilisable sur cette installation** ? Déposé, allumé et ses
     * dépendances satisfaites — sans parler du compte.
     *
     * C'est la question que se pose le Coeur d'application quand il affiche une donnée du module
     * ailleurs que dans ses pages : la colonne d'alliance de la galaxie, la recherche
     * d'alliance, le classement des alliances. Un jeu sans alliances éteint le module,
     * et ces affichages disparaissent avec lui.
     */
    public function available(string $name): bool
    {
        return Modules::exists($name)
            && $this->isActive($name)
            && $this->dependencyProblems($name) === array();
    }

    /**
     * Efface, dans chaque module déposé et allumé, les données d'un compte.
     *
     * Le Coeur d'application nomme le **geste**, jamais la table d'un module : le nettoyage anti-triche
     * (`UserFunctions::DeleteSelectedUser()`) passe par ici, et un module qui détient des
     * données liées à un compte (notes, annonces…) dépose `services/AccountPurgeService.php`
     * en dérivant la réponse du Coeur d'application. Sans module, la boucle ne fait rien — la table
     * n'existe même pas.
     */
    public function purgeAccountData(int $userId): void
    {
        foreach (Modules::names() as $name) {
            if (!$this->available($name)) {
                continue;
            }

            // Même convention que la résolution d'une surcharge : la classe d'un module vit
            // sous `Modules\<Nom>\<Couche>\<Nom court>`, et `Modules::classPath()` refuse
            // tout chemin hors des dossiers déclarés.
            $class = Modules::NAMESPACE_PREFIX . ucfirst($name) . '\\Services\\AccountPurgeService';

            if (Modules::classPath($class) === null || !class_exists($class)) {
                continue;
            }

            /** @var \App\Services\AccountPurgeService $service */
            $service = new $class();
            $service->purge($userId);
        }
    }

    /**
     * Classe effective d'un **point de surcharge** du Coeur d'application.
     *
     * Un module ne remplace jamais une classe du jeu : il en **dérive**, et cette
     * classe prend la main tant que le module est utilisable. Le Coeur d'application appelle donc
     * ce résolveur au lieu de nommer sa classe en dur :
     *
     *     $engine = ModuleService::resolve(BattleEngine::class);
     *     $battle  = $engine::resolve($CurrentSet, $TargetSet, $CurrentTechno, $TargetTechno);
     *
     * Sans module déclaré — ou module éteint —, c'est la classe du Coeur d'application qui
     * répond : le jeu se comporte exactement comme s'il n'y avait pas de surcharge.
     *
     * @param class-string $class
     * @return class-string
     */
    public function implementation(string $class): string
    {
        $override = Modules::overrideFor($class);

        if ($override === null || !$this->available($override['module'])) {
            return $class;
        }

        return class_exists($override['class']) ? $override['class'] : $class;
    }

    /**
     * Classes effectives, retenues pour la requête : la question est posée à chaque
     * ligne d'une galaxie ou d'une liste, et la réponse ne change pas en cours de route.
     *
     * @var array<class-string, class-string>
     */
    private static array $resolved = array();

    /**
     * Classe effective d'un point de surcharge, en **une ligne** au point d'appel :
     *
     *     $page = ModuleService::instance(ResourceService::class)->buildPage(...);
     *
     * @param class-string $class
     * @return class-string
     */
    public static function resolve(string $class): string
    {
        return self::$resolved[$class] ??= (new self())->implementation($class);
    }

    /**
     * Instance effective d'un service surchargeable : celle du module allumé qui
     * dérive la classe, sinon celle du Coeur d'application.
     *
     * @param class-string $class
     */
    public static function instance(string $class): object
    {
        $implementation = self::resolve($class);

        return new $implementation();
    }

    /**
     * Gestionnaire d'une **mission de flotte** déclarée par un module, ou `null`
     * quand la mission n'est pas déclarée (ou appartient à un module éteint).
     *
     * @return array{class: string, method: string, module: string, free_target: bool, debris: bool, ships: array<int, int>}|null
     */
    public function missionHandler(int $mission): ?array
    {
        $declared = Modules::missionFor($mission);

        if ($declared === null || !$this->available($declared['module'])) {
            return null;
        }

        return class_exists($declared['class']) ? $declared : null;
    }

    /**
     * Missions déclarées par les modules **utilisables**, pour que les pages d'envoi
     * et l'API proposent exactement ce que le traitement accepte.
     *
     * @return array<int, array{class: string, method: string, module: string, free_target: bool, debris: bool, ships: array<int, int>}>
     */
    public function missionHandlers(): array
    {
        $handlers = array();

        foreach (Modules::names() as $name) {
            foreach (array_keys(Modules::missions($name)) as $id) {
                $declared = $this->missionHandler((int) $id);

                if ($declared !== null) {
                    $handlers[(int) $id] = $declared;
                }
            }
        }

        return $handlers;
    }

    /**
     * Champs de débris ajoutés par les modules utilisables : colonne de `galaxy` =>
     * clé de langue du libellé.
     *
     * Le Coeur d'application écrit et affiche ces champs **sans les connaître** : un module qui
     * ajoute une ressource au champ de débris la déclare au manifeste, et le moteur
     * de combat (surchargé par le module) la renseigne dans `debris`.
     *
     * @return array<string, string>
     */
    public function debrisFields(): array
    {
        $fields = array();

        foreach (Modules::debrisColumns() as $column => $name) {
            if (!$this->available($name)) {
                continue;
            }

            $fields[$column] = (string) (Modules::debrisFields($name)[$column] ?? $column);
        }

        return $fields;
    }

    /**
     * Lignes de rapport des champs de débris **ajoutés** par un module.
     *
     * Le rapport annonce le métal et le cristal ; une ressource de plus — le deutérium
     * du module `extracteurs` — est annoncée par la phrase que le module apporte
     * (`sys_gcdrunits_extra`, déjà mise à disposition par `Language::include()`). Sans
     * cette phrase, il n'y a rien à dire : le Coeur d'application n'ajoute rien et ne connaît aucune
     * ressource en particulier.
     *
     * @param array<string, mixed> $debris valeurs `debris` du combat
     * @param array<string, mixed> $lang   libellés en cours
     */
    public function debrisExtraReport(array $debris, array $lang): string
    {
        $format = (string) ($lang['sys_gcdrunits_extra'] ?? '');

        if ($format === '') {
            return '';
        }

        $text = '';

        foreach ($this->debrisFields() as $column => $label) {
            if ((float) ($debris[$column] ?? 0) <= 0.0) {
                continue;
            }

            $text .= '<br />' . sprintf(
                $format,
                number_format((float) $debris[$column], 0, '', '.'),
                (string) ($lang[$label] ?? $label)
            );
        }

        return $text;
    }

    /**
     * Le compte atteint-il ce module ? C'est la seule décision d'accès d'un module,
     * utilisée par la garde des routes **et** par le menu latéral.
     *
     * @param array<string, mixed> $user
     */
    public function accessible(string $name, array $user): bool
    {
        if (!Modules::exists($name)) {
            return true;
        }

        // Éteint, ou privé d'une dépendance : le module disparaît comme s'il n'était
        // pas là, et seul un administrateur des modules le voit encore — c'est lui qui
        // répare.
        if (!$this->available($name)) {
            return $this->canAdminister($user);
        }

        $roleId = (int) ($user['role_id'] ?? 0);

        if ($roleId <= 0) {
            // Sans rôle, l'accès reste ouvert : c'est le rôle qui restreint.
            return Modules::reachable(true, false, false);
        }

        return Modules::reachable(true, true, $this->acl->can($user, Modules::permission($name)));
    }

    /**
     * Réponse à renvoyer quand l'adresse demandée appartient à un module qui n'est
     * pas ouvert à ce compte, ou `null` quand la page peut continuer.
     *
     * Appelée par `index.php` après le journal des actions : une page comme une
     * route JSON passe par ici, donc la règle est écrite une seule fois.
     */
    public function guardRoute(Request $request, bool $isApi): ?Response
    {
        $name = Modules::ofRoute($request->path());

        if ($name === null) {
            return null;
        }

        $user = is_array($GLOBALS['user'] ?? null) ? $GLOBALS['user'] : array();

        if ($this->accessible($name, $user)) {
            return null;
        }

        Language::include('modules');
        // Le libellé du module vient de son module : chargé à la demande, il suit le
        // module même quand son nom technique ne dit rien au joueur.
        Language::includeModule($name);

        $lang = Language::all();
        $label = (string) ($lang[Modules::label($name)] ?? $name);
        $problems = $this->dependencyText($name);

        // Deux refus différents : le module est éteint, ou il tourne mais il attend une
        // dépendance (module éteint ou absent, Coeur d'application trop ancien). Le joueur doit
        // pouvoir dire les deux à l'administrateur.
        $message = $problems === ''
            ? str_replace('%s', $label, (string) ($lang['mod_disabled_message'] ?? ''))
            : str_replace(
                array('%module%', '%depends%'),
                array($label, $problems),
                (string) ($lang['mod_dep_blocked'] ?? '')
            );

        if ($isApi) {
            return Response::json(array(
                'ok' => false,
                'error' => array(
                    'code' => 'module_disabled',
                    'message' => $message,
                    'fields' => array(),
                ),
            ), 403);
        }

        return Response::html(
            TemplateEngine::renderDisplay(
                '<div class="alert alert-warning" role="alert">' . $message . '</div>',
                (string) ($lang['mod_disabled_title'] ?? ''),
                true
            ),
            403
        );
    }

    /**
     * Allume ou éteint un module. Renvoie '' si c'est fait, sinon la clé du refus.
     */
    public function setActive(string $name, bool $active): string
    {
        if (!Modules::exists($name)) {
            return 'mod_notfound';
        }

        // Éteindre un module dont un autre dépend laisserait ce dernier sans Coeur d'application :
        // on refuse, et l'administrateur éteint d'abord celui qui en dépend.
        if (!$active && $this->dependants($name) !== array()) {
            return 'mod_dep_needed';
        }

        $this->modules->saveActive($name, $active, time());
        self::$states = null;

        return '';
    }

    /**
     * **Désinstalle** un module : ses fichiers passent dans `modules/.archive/<nom>/`.
     *
     * C'est un archivage, pas une suppression : un module archivé n'est plus **découvert**,
     * donc il disparaît du jeu exactement comme s'il n'avait jamais été déposé — ses pages,
     * ses routes JSON, ses surcharges de classes et ses migrations avec lui — et les modules
     * qui en dépendent se retrouvent suspendus. Ses fichiers restent sur le disque, ses
     * réglages restent en base : `restore()` le réinstalle tel quel.
     *
     * Renvoie '' si c'est fait, sinon la clé du refus.
     */
    public function archive(string $name): string
    {
        if (!Modules::exists($name)) {
            return 'mod_notfound';
        }

        $from = Modules::directory() . $name;
        $to = Modules::archiveDirectory($name);

        // Une archive du même nom ne se réécrit pas : l'administrateur réinstalle
        // l'ancienne ou la déplace d'abord.
        if (file_exists($to)) {
            return 'mod_archive_exists';
        }

        if (!is_dir($from) || !$this->makeArchive()) {
            return 'mod_archive_failed';
        }

        if (!@rename($from, $to)) {
            return 'mod_archive_failed';
        }

        $this->forget();

        return '';
    }

    /**
     * **Réinstalle** un module archivé : son dossier revient sous `modules/`.
     *
     * Renvoie '' si c'est fait, sinon la clé du refus.
     */
    public function restore(string $name): string
    {
        if (!isset(Modules::archived()[$name])) {
            return 'mod_archive_missing';
        }

        if (file_exists(Modules::directory() . $name)) {
            return 'mod_archive_conflict';
        }

        if (!@rename(Modules::archiveDirectory($name), Modules::directory() . $name)) {
            return 'mod_archive_failed';
        }

        $this->forget();

        return '';
    }

    /** Crée le dossier d'archive au besoin (il n'existe pas tant que rien n'est archivé). */
    private function makeArchive(): bool
    {
        $directory = Modules::archiveDirectory();

        return is_dir($directory) || @mkdir($directory, 0775, true) || is_dir($directory);
    }

    /**
     * Le catalogue et les états sont lus **une fois par requête** : après un déplacement
     * de module, la question doit être reposée (sinon la page suivante de la même requête
     * croirait encore le module déposé).
     */
    private function forget(): void
    {
        Modules::clearCache();
        self::$states = null;
        self::$resolved = array();
    }

    /**
     * Enregistre les réglages d'un module (JSON libre : leur forme dépend du module).
     *
     * @param array<string, mixed> $settings
     */
    public function saveSettings(string $name, array $settings): string
    {
        if (!Modules::exists($name)) {
            return 'mod_notfound';
        }

        $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->modules->saveSettings($name, $encoded === false ? '' : $encoded, time());
        self::$states = null;

        return '';
    }

    /**
     * Crée la ligne des modules qui n'en ont pas, en respectant les interrupteurs
     * historiques (`enable_marchand`, `enable_announces` de la table `config`) :
     * une fonctionnalité éteinte avant le registre le reste. **Idempotent** : un
     * module déjà réglé n'est jamais réécrit. Renvoie le nombre de lignes créées.
     */
    public function seed(): int
    {
        $config = GameConfig::load();
        $states = array();

        foreach (Modules::names() as $name) {
            $states[$name] = $this->defaultActive($name, $config);
        }

        $created = $this->modules->insertMissing($states, time());
        self::$states = null;

        return $created;
    }

    /** Semis depuis un point d'entrée sans service (installateur, `db/modules.php`). */
    public static function seedDefaults(): int
    {
        return (new self())->seed();
    }

    /** Vide le cache d'état (après une écriture, ou pour un test). */
    public static function clearCache(): void
    {
        self::$states = null;
    }

    /**
     * État de départ d'un module : les interrupteurs de la table `config` qui
     * existaient avant le registre font foi, tout le reste naît allumé.
     *
     * @param array<string, mixed> $config
     */
    private function defaultActive(string $name, array $config): bool
    {
        $switch = self::LEGACY_SWITCHES[$name] ?? null;

        if ($switch === null) {
            return true;
        }

        return (int) ($config[$switch] ?? 1) === 1;
    }

    /**
     * Catalogue + état brut, lu une seule fois par requête.
     *
     * @return array<string, array{active: bool, settings: array<string, mixed>}>
     */
    private function states(): array
    {
        if (self::$states !== null) {
            return self::$states;
        }

        $states = array();

        foreach (Modules::names() as $name) {
            $states[$name] = array('active' => true, 'settings' => array());
        }

        foreach ($this->modules->findAll() as $name => $row) {
            // Une ligne dont le module a disparu du code est ignorée (et non
            // supprimée : elle reviendra avec lui).
            if (!Modules::exists($name)) {
                continue;
            }

            $module = Module::fromRow($row);
            $states[$name] = array('active' => $module->isActive(), 'settings' => $module->settings());
        }

        return self::$states = $states;
    }
}
