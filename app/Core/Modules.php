<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registre des modules : lecture des manifestes `modules/<nom>/package.json`.
 *
 * Un module est un **module** : un dossier sous `modules/`, avec son manifeste
 * `package.json` qui le décrit et, s'il apporte du code, ses sous-dossiers
 * `core/`, `controllers/`, `entities/`, `repositories/`, `services/`, `view/` et
 * `language/`. Le registre
 * ne code rien en dur : il découvre ce qui est déposé, valide le manifeste et
 * répond aux questions du reste du jeu (quelles pages existent, quelle permission
 * les ouvre, quel module possède telle adresse).
 *
 * `core/` porte ce qui **n'existe pas** dans le jeu : les fonctions propres au
 * module (une règle de calcul, un parseur). Une règle générale a déjà sa classe dans
 * `app/Core` : le module la réutilise au lieu de la recopier. Un module dépose donc
 * du Coeur d'application uniquement quand son besoin lui est propre — jamais une copie d'un
 * `app/Core/*`.
 *
 * Manifeste (`package.json`) :
 *
 *     {
 *       "name": "chat",                     // optionnel : le nom du dossier fait foi
 *       "label": "mod_chat",                // clé de langue ou texte affiché
 *       "description": "mod_chat_desc",
 *       "version": "1.0.0",
 *       "author": "…",
 *       "order": 30,                        // ordre d'affichage (défaut : alphabétique)
 *       "page": "/game/chat",
 *       "routes": {"game/chat": "ChatController@indexAction"},
 *       "api": {"game/api/chat/send": "ChatApiController@sendAction"},
 *       "legacy": {"chat.php": "/game/chat"},
 *       "permissions": ["module.chat"],      // défaut : module.<nom>
 *       "dependencies": {"core": ">=2026.6", "modules": ["marchand"]},
 *       "settings": {"purge_days": {"label": "…", "type": "int", "default": 30}}
 *     }
 *
 * Une adresse est servie par une classe du **module** (`Modules\<Nom>\Controllers\…`,
 * la classe étant nommée par le manifeste) : le jeu n'a donc pas à savoir quelles
 * pages un module apporte, il interroge le registre. Un module qui veut ajuster une
 * page du jeu en dérive le contrôleur (`extends`) : jamais de copie.
 *
 * Un module **déclare ses dépendances** : le Coeur d'application (`core`, comparé à la version du
 * manifeste du projet, `Project::version()`) et les autres modules dont il a besoin (`modules`). `dependencyProblems()`
 * dit ce qui manque, le panneau l'affiche, et `ModuleDependenciesTest` tient la règle :
 * une classe du Coeur d'application utilisée dans un module doit être **importée** (`use`), sinon
 * elle est cherchée sous `Modules\<Nom>\…` et la page tombe en erreur fatale.
 *
 * `App\Services\ModuleService` lit et écrit l'**état** (table `modules` : allumé ou
 * non, réglages) ; `App\Core\Acl` ajoute les permissions déclarées au catalogue du
 * panneau (groupe « Modules »). Tout est pur ici : le registre ne touche pas la base.
 *
 * Deux décisions, dans cet ordre :
 *  - l'**interrupteur** : éteint, le module disparaît pour tout le monde — pages,
 *    menu, routes JSON — sauf pour le compte d'installation et les rôles qui
 *    peuvent administrer les modules, qui doivent pouvoir le rallumer ;
 *  - la **permission** (`module.<nom>`) : un rôle qui la porte ouvre le module, un
 *    rôle qui ne la porte pas le ferme à ses comptes. Un compte **sans rôle** garde
 *    l'accès (comportement historique) : sans cela, tous les joueurs perdraient le
 *    tchat le jour où l'ACL est branchée.
 */
final class Modules
{
    /** Nom du fichier qui décrit un module, à sa racine. */
    public const MANIFEST = 'package.json';

    /**
     * Dossier des modules **archivés**, sous `modules/`.
     *
     * Il porte les modules désinstallés depuis le panneau : un module archivé n'est plus
     * **découvert** (la découverte lit `modules/<nom>/package.json`, pas ses sous-dossiers),
     * donc il disparaît exactement comme s'il n'avait jamais été déposé — ses pages, ses
     * routes, ses surcharges et ses migrations avec lui. Ses fichiers restent sur le
     * disque, et `ModuleService::restore()` les remet en place.
     */
    public const ARCHIVE_DIRECTORY = '.archive';

    /**
     * Préfixe de namespace du code d'un module : `Modules\<Nom>\…`.
     *
     * Le deuxième segment est le **dossier** du module, insensible à la casse
     * (`Modules\Marchand\Services\…` → `modules/marchand/services/`).
     */
    public const NAMESPACE_PREFIX = 'Modules\\';

    /** Sous-dossiers qu'un module peut apporter (documentation et contrôle). */
    public const DIRECTORIES = array(
        'core',
        'controllers',
        'entities',
        'repositories',
        'services',
        'view',
        'language',
    );

    /**
     * Dossiers de **données**, d'**outils** ou d'**assets** d'un module (jamais
     * résolus comme des classes).
     *
     * `db/` porte les migrations du module (`modules/<nom>/db/migrations/`) : le
     * module dépose le schéma qu'il ajoute au jeu avec son code, sans toucher à
     * `db/migrations/` du Coeur d'application.
     *
     * `cli/` porte ses points d'entrée en ligne de commande
     * (`modules/<nom>/cli/<script>.php`) : une tâche qu'un module apporte se lance
     * depuis son module. Ce n'est pas une classe, et rien de tel n'est routé.
     *
     * `assets/` porte ses fichiers servis par le navigateur
     * (`modules/<nom>/assets/*.js|*.css`) : **seul** dossier d'un module que
     * `.htaccess` laisse lire, et **découvert** — déposer un script suffit à le
     * faire charger par les pages du module (`Modules::assetTags()`).
     *
     * `tests/` porte ses tests unitaires (`modules/<nom>/tests/*Test.php`), dans le
     * namespace `Modules\<Nom>\Tests\` : ils voyagent avec le module et la suite du
     * Coeur d'application les découvre (`phpunit.xml` descend dans `modules/`).
     */
    public const DATA_DIRECTORIES = array('db', 'cli', 'assets', 'tests');

    /**
     * Catalogue lu une fois par requête.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $catalog = null;

    /**
     * Module qui sert la requête en cours (`null` : une page du jeu).
     *
     * Posé par le routeur au moment où il résout une adresse déclarée par un
     * manifeste : c'est la seule chose que l'en-tête a besoin de savoir pour ajouter
     * les assets du module (`assetTags()`), sans que le jeu ait à dire lequel c'est.
     */
    private static ?string $serving = null;

    /** Note le module qui sert la requête (appelé par `Router`). */
    public static function serve(string $name): void
    {
        self::$serving = $name;
    }

    /** Module qui sert la requête en cours, ou `null`. */
    public static function serving(): ?string
    {
        return self::$serving;
    }

    /** Dossier des modules (à la racine du jeu). */
    public static function directory(): string
    {
        return rtrim(defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 2), '/\\') . '/modules/';
    }

    /** Dossier des migrations d'un module (`modules/<nom>/db/migrations/`). */
    public static function migrationsDirectory(string $name): string
    {
        return self::directory() . $name . '/db/migrations/';
    }

    /**
     * Dossier d'archive (`modules/.archive/`), ou celui d'un module archivé
     * (`modules/.archive/<nom>/`).
     */
    public static function archiveDirectory(string $name = ''): string
    {
        $directory = self::directory() . self::ARCHIVE_DIRECTORY . '/';

        return $name === '' ? $directory : $directory . $name . '/';
    }

    /**
     * Modules **archivés** (désinstallés du panneau), dans l'ordre alphabétique.
     *
     * Fonction pure : le registre lit des manifestes, jamais la base. Un module archivé
     * n'est plus dans le catalogue (`all()`), donc rien du jeu ne le voit ; il reste
     * réinstallable tant que son dossier est là.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function archived(): array
    {
        $archived = array();

        foreach ((array) glob(self::archiveDirectory() . '*/' . self::MANIFEST) as $file) {
            $module = self::read((string) $file);

            if ($module !== null) {
                $archived[(string) $module['name']] = $module;
            }
        }

        return $archived;
    }

    /**
     * Dossiers de migrations des modules déposés, dans l'ordre du catalogue.
     *
     * Le schéma du Coeur d'application est joué en premier (`db/migrations/`), puis celui de
     * chaque module : un module peut donc compter sur les tables du jeu.
     *
     * @return array<string, string> nom du module => dossier de ses migrations
     */
    public static function migrationDirectories(): array
    {
        $directories = array();

        foreach (self::names() as $name) {
            $directories[$name] = self::migrationsDirectory($name);
        }

        return $directories;
    }

    /**
     * Catalogue : nom technique => manifeste normalisé, dans l'ordre d'affichage.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $catalog = array();

        foreach ((array) glob(self::directory() . '*/' . self::MANIFEST) as $file) {
            $module = self::read((string) $file);

            if ($module !== null) {
                $catalog[(string) $module['name']] = $module;
            }
        }

        // Ordre d'affichage : `order` du manifeste, puis le nom technique.
        uasort($catalog, static function (array $left, array $right): int {
            return array($left['order'], $left['name']) <=> array($right['order'], $right['name']);
        });

        return self::$catalog = $catalog;
    }

    /** Vide le cache du registre (installation d'un module, ou test). */
    public static function clearCache(): void
    {
        self::$catalog = null;
    }

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    /** Clé de langue (ou texte) du libellé d'un module. */
    public static function label(string $name): string
    {
        return (string) (self::all()[$name]['label'] ?? $name);
    }

    /** Clé de langue (ou texte) de la description d'un module. */
    public static function description(string $name): string
    {
        return (string) (self::all()[$name]['description'] ?? '');
    }

    /** Adresse de la page principale d'un module (celle qu'on montre au panneau). */
    public static function page(string $name): string
    {
        return (string) (self::all()[$name]['page'] ?? '');
    }

    /** Version déclarée par le manifeste. */
    public static function version(string $name): string
    {
        return (string) (self::all()[$name]['version'] ?? '');
    }

    /**
     * Dépendances déclarées par un module : le Coeur d'application et les autres modules exigés.
     *
     * @return array{core: string, modules: array<int, string>}
     */
    public static function dependencies(string $name): array
    {
        return (array) (self::all()[$name]['dependencies'] ?? array('core' => '', 'modules' => array()));
    }

    /**
     * Premier segment à partir duquel une version est un **millésime** (`2026.37`).
     *
     * La gamme millésimée a été close par `1.0.0` : elle reste dans l'historique, mais
     * un millésime est toujours plus ancien qu'un numéro (voir `coreSatisfies()`).
     */
    public const SCHEME_YEAR = 2000;

    /**
     * Le Coeur d'application satisfait-il la contrainte déclarée ? (`>=2026.6`, `=2026.6`, `>…`)
     *
     * Comparaison sur les nombres de version (pas la comparaison de chaînes, qui
     * dirait que `2026.10` est plus petit que `2026.6`). Une contrainte vide ou une
     * version inconnue ne se plaignent pas : on ne refuse pas un module qu'on ne sait
     * pas juger.
     */
    public static function coreSatisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || trim($version) === '') {
            return true;
        }

        if (preg_match('/^(>=|<=|>|<|=)?\s*(\d+(?:\.\d+)*)$/', $constraint, $parts) !== 1) {
            return true;
        }

        $operator = $parts[1] === '' ? '=' : $parts[1];
        $wanted = self::versionParts($parts[2]);
        $current = self::versionParts($version);

        // Deux gammes se sont succédé : les millésimes (`2026.37`) puis les numéros
        // (`1.0.0`). Comparer segment par segment dirait que `2026` écrase `1`, donc que
        // la gamme close est la plus récente : un rang placé devant chaque version rend
        // l'ordre juste (`2026.37` → `[0, 2026, 37]`, `1.0.0` → `[1, 0, 0]`) sans toucher
        // au reste de la comparaison.
        $current = array_merge(array((int) (($current[0] ?? 0) < self::SCHEME_YEAR)), $current);
        $wanted = array_merge(array((int) (($wanted[0] ?? 0) < self::SCHEME_YEAR)), $wanted);

        $length = max(count($wanted), count($current));

        for ($index = 0; $index < $length; $index++) {
            $left = $current[$index] ?? 0;
            $right = $wanted[$index] ?? 0;

            if ($left === $right) {
                continue;
            }

            $greater = $left > $right;

            return match ($operator) {
                '>' => $greater,
                '>=' => $greater,
                '<' => !$greater,
                '<=' => !$greater,
                default => false,
            };
        }

        return in_array($operator, array('=', '>=', '<='), true);
    }

    /**
     * Ce qui manque au module pour tourner : Coeur d'application trop ancien, module absent, module
     * éteint.
     *
     * Fonction **pure** : la version du Coeur d'application et la liste des modules déposés sont
     * passées en paramètre (le registre ne lit pas le manifeste du projet lui-même : c'est
     * `ModuleService::coreVersion()` qui le fait).
     *
     * La clé dit de quel genre de manque il s'agit : `core` (Coeur d'application trop ancien),
     * `module:<nom>` (module **absent**, donc introuvable) et `off:<nom>` (module
     * présent mais **éteint** : il suffit de le rallumer).
     *
     * @param array<int, string> $available noms des modules déposés
     * @param array<int, string> $disabled noms des modules déposés mais éteints
     * @return array<string, string> clé du manque => détail lisible
     */
    public static function dependencyProblems(
        string $name,
        string $coreVersion,
        array $available,
        array $disabled = array()
    ): array {
        return self::problems(self::dependencies($name), $coreVersion, $available, $disabled);
    }

    /**
     * Verdict de dépendances, à partir d'une déclaration déjà lue.
     *
     * Fonction pure et seule porteuse de la règle : `dependencyProblems()` ne fait que
     * lui apporter le manifeste du module (c'est ce qui la rend testable sans fiction
     * dans un manifeste).
     *
     * @param array<string, mixed> $declared déclaration (`core`, `modules`)
     * @param array<int, string> $available noms des modules déposés
     * @param array<int, string> $disabled noms des modules éteints
     * @return array<string, string>
     */
    public static function problems(
        array $declared,
        string $coreVersion,
        array $available,
        array $disabled = array()
    ): array {
        $problems = array();
        $core = trim((string) ($declared['core'] ?? ''));

        if (!self::coreSatisfies($coreVersion, $core)) {
            $problems['core'] = $core;
        }

        foreach (self::missingModules((array) ($declared['modules'] ?? array()), $available) as $required) {
            $problems['module:' . $required] = $required;
        }

        foreach ((array) ($declared['modules'] ?? array()) as $required) {
            if (in_array($required, $disabled, true)) {
                $problems['off:' . $required] = (string) $required;
            }
        }

        return $problems;
    }

    /**
     * Modules exigés qui ne sont pas déposés (comparaison par nom technique).
     *
     * @param array<int, string> $required
     * @param array<int, string> $available
     * @return array<int, string>
     */
    public static function missingModules(array $required, array $available): array
    {
        $missing = array();

        foreach ($required as $name) {
            if (!in_array($name, $available, true)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /** Découpe une version en nombres (« 2026.6 » → [2026, 6]). */
    private static function versionParts(string $version): array
    {
        return array_map('intval', explode('.', trim($version)));
    }

    /** Auteur déclaré par le manifeste. */
    public static function author(string $name): string
    {
        return (string) (self::all()[$name]['author'] ?? '');
    }

    /** Module livré avec le jeu (l'opposé d'un module déposé après coup). */
    public static function isNative(string $name): bool
    {
        return (bool) (self::all()[$name]['native'] ?? false);
    }

    /**
     * Réglages **déclarés** par le module (nom du réglage => description).
     *
     * @return array<string, mixed>
     */
    public static function settingsSchema(string $name): array
    {
        return (array) (self::all()[$name]['settings'] ?? array());
    }

    /** Permission ACL qui ouvre un module (`module.chat`). */
    public static function permission(string $name): string
    {
        return Acl::MODULE_PREFIX . $name;
    }

    /**
     * Toutes les permissions déclarées par un module (au moins sa permission
     * principale, `module.<nom>`), dans l'ordre du manifeste.
     *
     * @return array<int, string>
     */
    public static function permissions(string $name): array
    {
        $declared = (array) (self::all()[$name]['permissions'] ?? array());
        $main = self::permission($name);

        if (!in_array($main, $declared, true)) {
            array_unshift($declared, $main);
        }

        return array_values(array_unique($declared));
    }

    /**
     * Nom du module derrière une permission (`module.chat` => `chat`), ou `null`
     * quand aucune permission déclarée ne correspond.
     */
    public static function ofPermission(string $permission): ?string
    {
        foreach (self::all() as $name => $module) {
            if (in_array($permission, self::permissions($name), true)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Module qui possède cette adresse (`game/chat`, `game/api/chat/send`), ou
     * `null` : c'est la correspondance qui permet de garder une route sans que le
     * contrôleur ait à s'en occuper.
     */
    public static function ofRoute(string $path): ?string
    {
        $path = self::normalizePath($path);

        if ($path === '') {
            return null;
        }

        foreach (self::all() as $name => $module) {
            if (isset($module['routes'][$path]) || isset($module['api'][$path])) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Contrôleur qui sert une adresse déclarée par un module, ou `null`.
     *
     * C'est **la** résolution des routes d'un module : le jeu ne connaît pas les
     * pages qu'un module apporte, il demande au registre. La classe vit toujours
     * dans le module (`Modules\<Nom>\Controllers\…`) ; si le fichier n'existe pas,
     * l'adresse est ignorée (le module est peut-être mal installé : mieux vaut un
     * 404 qu'une erreur fatale).
     *
     * @return array{class: string, action: string}|null
     */
    public static function controllerFor(string $path): ?array
    {
        $path = self::normalizePath($path);

        if ($path === '') {
            return null;
        }

        foreach (self::all() as $name => $module) {
            $route = $module['routes'][$path] ?? $module['api'][$path] ?? null;

            if ($route === null) {
                continue;
            }

            $class = 'Modules\\' . ucfirst($name) . '\\Controllers\\' . $route[0];

            return class_exists($class) ? array('class' => $class, 'action' => $route[1]) : null;
        }

        return null;
    }

    /** Redirection d'une adresse historique déclarée par un module (`chat.php`). */
    public static function legacy(string $path): ?string
    {
        $path = strtolower(trim($path));

        foreach (self::all() as $module) {
            if (isset($module['legacy'][$path])) {
                return (string) $module['legacy'][$path];
            }
        }

        return null;
    }

    /** Toutes les adresses gardées d'un module (pages puis routes JSON). */
    public static function routes(string $name): array
    {
        $module = self::all()[$name] ?? array();

        return array_merge(array_keys((array) ($module['routes'] ?? array())), array_keys((array) ($module['api'] ?? array())));
    }

    /** Dossier des gabarits d'un module (`modules/<nom>/view/`). */
    public static function viewDirectory(string $name): string
    {
        return self::directory() . $name . '/view/';
    }

    /**
     * Adresse web des assets d'un module (`/modules/<nom>/assets/`).
     */
    public static function assetUrl(string $name): string
    {
        return '/modules/' . rawurlencode($name) . '/assets/';
    }

    /**
     * Dossier des **assets web** d'un module (`modules/<nom>/assets/`).
     *
     * C'est le seul dossier d'un module que le serveur web laisse lire :
     * `.htaccess` en ouvre l'accès pour une liste **fermée** d'extensions
     * statiques — un `.php` déposé là reste refusé, sinon un fichier ajouté au
     * module s'exécuterait.
     */
    public static function assetDirectory(string $name): string
    {
        return self::directory() . $name . '/assets/';
    }

    /**
     * Assets d'un module, triés : nom de fichier => chemin absolu.
     *
     * Ils sont **découverts**, jamais déclarés : déposer un `.js` ou un `.css` dans
     * `modules/<nom>/assets/` suffit à le faire charger par les pages du module.
     *
     * @return array<string, string>
     */
    public static function assets(string $name): array
    {
        $directory = self::assetDirectory($name);
        $files = array_merge(glob($directory . '*.js') ?: array(), glob($directory . '*.css') ?: array());
        $assets = array();

        foreach ($files as $file) {
            if (is_file($file)) {
                $assets[basename($file)] = $file;
            }
        }

        ksort($assets);

        return $assets;
    }

    /**
     * Balises des assets du module qui sert la requête (vide pour une page du jeu).
     *
     * La date de modification sert de version, comme pour les scripts du Coeur d'application : le
     * navigateur recharge le fichier dès qu'il change, sans purge de cache. Un
     * script est posé en `defer` — il vit dans l'en-tête, donc il **n'est pas**
     * réexécuté par un remplacement partiel de page et doit écouter
     * `xnova:reloaded` s'il lit le DOM.
     */
    public static function assetTags(): string
    {
        $name = self::serving();

        if ($name === null) {
            return '';
        }

        $tags = '';

        foreach (self::assets($name) as $file => $path) {
            $url = htmlspecialchars(
                self::assetUrl($name) . rawurlencode($file) . '?v=' . (int) @filemtime($path),
                ENT_QUOTES
            );

            $tags .= str_ends_with($file, '.css')
                ? '<link rel="stylesheet" href="' . $url . '">' . "\n"
                : '<script src="' . $url . '" defer></script>' . "\n";
        }

        return $tags;
    }

    /**
     * Dossiers de langue d'un module, la langue du compte d'abord.
     *
     * @return array<int, string>
     */
    public static function languageDirectories(string $name, string $lang): array
    {
        $directories = array(self::directory() . $name . '/language/' . $lang . '/');

        if ($lang !== 'fr') {
            $directories[] = self::directory() . $name . '/language/fr/';
        }

        return $directories;
    }

    /** Adresse nettoyée : sans slash, en minuscules (format du routeur). */
    private static function normalizePath(string $path): string
    {
        return rtrim(ltrim(strtolower(trim($path)), '/'), '/');
    }

    /**
     * Nom technique valide : le dossier d'un module devient un segment de namespace.
     */
    private static function validName(string $name): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $name) === 1;
    }

    /**
     * Le module est-il utilisable par ce compte ? Fonction pure : le module doit
     * être allumé, et si le compte porte un rôle, ce rôle doit ouvrir le module.
     *
     * Un compte **sans rôle** garde l'accès : c'est le comportement d'avant l'ACL,
     * et sans cette règle tous les joueurs perdraient le tchat, les notes ou le
     * marchand le jour où l'ACL est branchée. Le rôle sert à **restreindre**.
     */
    public static function reachable(bool $active, bool $hasRole, bool $granted): bool
    {
        if (!$active) {
            return false;
        }

        return !$hasRole || $granted;
    }

    /**
     * Fichier qui porte une classe de module, ou `null` si elle n'en est pas une.
     *
     * Un module est autonome : son code vit dans son module et suit le découpage de
     * `app/` (`Core/` → `core/`, `Controllers/` → `controllers/`, `Services/` →
     * `services/`…). Seuls les dossiers déclarés dans `DIRECTORIES` sont servis : le
     * registre ne résout pas de chemin arbitraire, et un `..` ne peut pas sortir du
     * dossier (chaque segment est validé).
     */
    public static function classFile(string $class): ?string
    {
        $file = self::classPath($class);

        return $file !== null && is_file($file) ? $file : null;
    }

    /**
     * Chemin attendu d'une classe de module, sans regarder le disque (fonction pure) :
     * c'est elle qui porte la règle de nommage, `classFile()` ne fait que vérifier
     * l'existence du fichier.
     */
    public static function classPath(string $class): ?string
    {
        if (!str_starts_with($class, self::NAMESPACE_PREFIX)) {
            return null;
        }

        $parts = explode('\\', substr($class, strlen(self::NAMESPACE_PREFIX)));

        // Au moins `<Nom>\<Dossier>\<Classe>` : une classe posée à la racine du dossier
        // d'un module n'est pas résolue (pas de fichier « en vrac »).
        if (count($parts) < 3) {
            return null;
        }

        $name = strtolower((string) array_shift($parts));
        $directory = strtolower((string) array_shift($parts));

        if (!self::validName($name) || !in_array($directory, self::DIRECTORIES, true)) {
            return null;
        }

        foreach ($parts as $part) {
            if (preg_match('/^[A-Za-z0-9_]+$/', $part) !== 1) {
                return null;
            }
        }

        return self::directory() . $name . '/' . $directory . '/' . implode('/', $parts) . '.php';
    }

    /**
     * Surcharges déclarées par un module : classe du Coeur d'application => classe du module.
     *
     * @return array<string, string>
     */
    public static function overrides(string $name): array
    {
        return (array) (self::all()[$name]['overrides'] ?? array());
    }

    /**
     * Module qui surcharge une classe du Coeur d'application, ou `null`.
     *
     * **Rien à déclarer** dans le cas courant : un module qui dépose
     * `core/Combat/BattleEngine.php` (même nom court, même couche que
     * `app/Core/Combat/BattleEngine.php`) surcharge la classe du Coeur d'application. La clé
     * `overrides` du manifeste ne sert qu'aux noms qui ne suivent pas la convention.
     *
     * L'ordre du catalogue décide : le premier module qui correspond gagne (les tests
     * refusent deux surcharges de la même classe).
     *
     * @return array{module: string, class: string}|null
     */
    public static function overrideFor(string $class): ?array
    {
        foreach (self::all() as $name => $module) {
            $target = self::overrides($name)[$class] ?? self::conventionOverride($name, $class);

            if ($target !== null) {
                return array('module' => $name, 'class' => $target);
            }
        }

        return null;
    }

    /**
     * Surcharge déduite du **nom** de la classe, ou `null`.
     *
     * Formulaire : `App\Services\ResourceService` cherche le fichier de même nom
     * court dans la couche `services/` du module — chemin **miroir** du Coeur d'application
     * (`core/Combat/BattleEngine.php`) ou fichier **à plat** (`controllers/…`).
     * Le nom de classe suit l'emplacement réel du fichier, comme `classPath()`.
     *
     * Pour un **contrôleur**, le dossier tranche : `controllers/Back/X.php` dérive une
     * page du **panneau** d'administration (`App\Controllers\Back\X`), un fichier à
     * plat (`controllers/X.php`) une page du **jeu** (`Game`, `Api`, `Front`). Les deux
     * familles ne se mélangent pas : une page du panneau ne peut pas être prise par un
     * fichier de jeu, ni l'inverse.
     */
    private static function conventionOverride(string $name, string $class): ?string
    {
        $parts = explode('\\', $class);
        array_shift($parts);

        $layer = strtolower((string) array_shift($parts));
        $short = (string) array_pop($parts);

        if ($short === '' || !in_array($layer, self::DIRECTORIES, true)) {
            return null;
        }

        $mirrors = array(implode('/', $parts));

        // Le panneau a son propre dossier : pas de repli sur le fichier à plat, qui
        // désigne une page du jeu.
        if ($layer !== 'controllers' || ($parts[0] ?? '') !== 'Back') {
            $mirrors[] = '';
        }

        foreach ($mirrors as $mirror) {
            $relative = $layer . '/' . ($mirror === '' ? '' : $mirror . '/') . $short . '.php';

            if (!is_file(self::directory() . $name . '/' . $relative)) {
                continue;
            }

            $segments = explode('/', substr($relative, 0, -4));

            return self::NAMESPACE_PREFIX . implode('\\', array_map('ucfirst', array_merge(array($name), $segments)));
        }

        return null;
    }

    /**
     * Champs de débris ajoutés par un module : colonne de `galaxy` => clé de langue.
     *
     * @return array<string, string>
     */
    public static function debrisFields(string $name): array
    {
        return (array) (self::all()[$name]['debris'] ?? array());
    }

    /**
     * Classe des **tables de jeu** déposées par un module, ou `null`.
     *
     * Elle vit dans `core/` du module et expose un unique `all()` qui retourne les
     * entrées à fusionner (`resource`, `requirements`, `pricelist`, `combatcaps`,
     * `reslist`) : un module qui apporte une unité la décrit chez lui, jamais dans
     * `app/Core/GameTables`.
     */
    public static function tables(string $name): ?string
    {
        $declared = trim((string) (self::all()[$name]['tables'] ?? ''));

        if (preg_match('/^[A-Za-z0-9_]+$/', $declared) !== 1) {
            return null;
        }

        return 'Modules\\' . ucfirst($name) . '\\Core\\' . $declared;
    }

    /**
     * Tous les champs de débris déclarés : colonne => nom du module.
     *
     * @return array<string, string>
     */
    public static function debrisColumns(): array
    {
        $columns = array();

        foreach (self::all() as $name => $module) {
            foreach (array_keys(self::debrisFields($name)) as $column) {
                $columns[$column] = $name;
            }
        }

        return $columns;
    }

    /**
     * Missions de flotte déclarées par un module : id => gestionnaire et exigences.
     *
     * @return array<int, array{handler: string, free_target: bool, debris: bool, ships: array<int, int>}>
     */
    public static function missions(string $name): array
    {
        return (array) (self::all()[$name]['missions'] ?? array());
    }

    /**
     * Gestionnaire d'une mission déclarée par un module, ou `null`.
     *
     * Le Coeur d'application ne connaît que ses propres missions : un module apporte les siennes
     * (id, gestionnaire, cible libre ou non, champ de débris ou non, vaisseaux acceptés).
     *
     * @return array{class: string, method: string, module: string, free_target: bool, debris: bool, ships: array<int, int>}|null
     */
    public static function missionFor(int $mission): ?array
    {
        foreach (self::all() as $name => $module) {
            $declared = self::missions($name)[$mission] ?? null;

            if ($declared === null) {
                continue;
            }

            $parts = explode('@', (string) $declared['handler'], 2);

            return array(
                'class' => 'Modules\\' . ucfirst($name) . '\\Services\\' . $parts[0],
                'method' => $parts[1],
                'refusal_method' => (string) ($declared['refusal'] ?? ''),
                'module' => $name,
                'free_target' => (bool) $declared['free_target'],
                'debris' => (bool) $declared['debris'],
                'ships' => (array) $declared['ships'],
            );
        }

        return null;
    }

    /**
     * Lecture et validation d'un manifeste. Un module illisible est **ignoré** (et
     * non fatal) : un module cassé ne doit pas empêcher le jeu de démarrer.
     *
     * @return array<string, mixed>|null
     */
    private static function read(string $file): ?array
    {
        $raw = @file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $manifest = json_decode($raw, true);

        if (!is_array($manifest)) {
            return null;
        }

        // Le dossier fait foi : `name` n'est qu'un contrôle de cohérence. Son nom
        // devient un segment de namespace (`Modules\Chat\…`) : minuscules, sans
        // tiret, comme un identifiant PHP.
        $name = basename(dirname($file));

        if (!self::validName($name)) {
            return null;
        }

        $declared = trim((string) ($manifest['name'] ?? ''));

        if ($declared !== '' && $declared !== $name) {
            return null;
        }

        return array(
            'name' => $name,
            'label' => trim((string) ($manifest['label'] ?? $name)),
            'description' => trim((string) ($manifest['description'] ?? '')),
            'version' => trim((string) ($manifest['version'] ?? '')),
            'author' => trim((string) ($manifest['author'] ?? '')),
            'native' => (bool) ($manifest['native'] ?? false),
            'order' => (int) ($manifest['order'] ?? 0),
            'page' => trim((string) ($manifest['page'] ?? '')),
            'routes' => self::routeMap($manifest['routes'] ?? array(), false),
            'api' => self::routeMap($manifest['api'] ?? array(), true),
            'legacy' => self::legacyMap($manifest['legacy'] ?? array()),
            'permissions' => self::permissionList($manifest['permissions'] ?? array(), $name),
            'dependencies' => self::dependencyMap($manifest['dependencies'] ?? array()),
            'overrides' => self::overrideMap($manifest['overrides'] ?? array()),
            'tables' => trim((string) ($manifest['tables'] ?? '')),
            'debris' => self::debrisFieldMap($manifest['debris'] ?? array()),
            'missions' => self::missionMap($manifest['missions'] ?? array()),
            'settings' => is_array($manifest['settings'] ?? null) ? $manifest['settings'] : array(),
        );
    }

    /**
     * Surcharges déclarées : `App\…\Classe` => classe **du module**.
     *
     * Un module ne remplace jamais une classe du Coeur d'application : il en **dérive** (le test
     * des dépendances le vérifie). Le Coeur d'application garde donc toujours sa version, et ne
     * s'en sert plus quand un module **allumé** déclare la surcharge (voir
     * `ModuleService::implementation()`). Une entrée mal formée est ignorée.
     *
     * @param mixed $overrides
     * @return array<string, string>
     */
    private static function overrideMap(mixed $overrides): array
    {
        if (!is_array($overrides)) {
            return array();
        }

        $clean = array();

        foreach ($overrides as $target => $class) {
            $target = trim((string) $target);
            $class = trim((string) $class);

            if (!str_starts_with($target, 'App\\') || !str_starts_with($class, self::NAMESPACE_PREFIX)) {
                continue;
            }

            $clean[$target] = $class;
        }

        return $clean;
    }

    /**
     * Champs de débris ajoutés : colonne de `galaxy` => clé de langue du libellé.
     *
     * Le Coeur d'application écrit et affiche **tous** les champs déclarés par les modules
     * allumés (le moteur de combat doit alors fournir la valeur dans `debris`) : la
     * vue galaxie et le rapport n'ont pas à connaître le deutérium en particulier.
     *
     * @param mixed $debris
     * @return array<string, string>
     */
    private static function debrisFieldMap(mixed $debris): array
    {
        if (!is_array($debris)) {
            return array();
        }

        $clean = array();

        foreach ($debris as $column => $label) {
            $column = strtolower(trim((string) $column));
            $label = trim((string) $label);

            if (preg_match('/^[a-z][a-z0-9_]*$/', $column) !== 1 || $label === '') {
                continue;
            }

            $clean[$column] = $label;
        }

        return $clean;
    }

    /**
     * Missions de flotte déclarées : `id` => gestionnaire et exigences.
     *
     * `handler` s'écrit `Classe@methode` et vit dans `services/` du module (même
     * convention que les routes, qui visent `controllers/`) : le Coeur d'application appelle le
     * gestionnaire du module quand un vol de cette mission arrive.
     *
     * `debris` dit que la mission se joue **sur un champ de débris** : c'est ce que
     * la vue galaxie regarde pour proposer le raccourci, sans connaître le module.
     *
     * @param mixed $missions
     * @return array<int, array{handler: string, free_target: bool, debris: bool, ships: array<int, int>}>
     */
    private static function missionMap(mixed $missions): array
    {
        if (!is_array($missions)) {
            return array();
        }

        $clean = array();

        foreach ($missions as $id => $declared) {
            $id = (int) $id;
            $declared = is_array($declared) ? $declared : array();
            $handler = trim((string) ($declared['handler'] ?? ''));
            $refusal = trim((string) ($declared['refusal'] ?? ''));
            $ships = array();

            foreach ((array) ($declared['ships'] ?? array()) as $ship) {
                if ((int) $ship > 0) {
                    $ships[] = (int) $ship;
                }
            }

            if ($id <= 0 || preg_match('/^[A-Za-z0-9_]+@[A-Za-z0-9_]+$/', $handler) !== 1) {
                continue;
            }

            $clean[$id] = array(
                'handler' => $handler,
                'refusal' => preg_match('/^[A-Za-z0-9_]+$/', $refusal) === 1 ? $refusal : '',
                'free_target' => (bool) ($declared['free_target'] ?? false),
                'debris' => (bool) ($declared['debris'] ?? false),
                'ships' => $ships,
            );
        }

        return $clean;
    }

    /**
     * Adresses d'un manifeste : `chemin` => `Classe@action`.
     *
     * Une adresse de page reste dans la zone du jeu (`game/…`) ; une adresse JSON
     * commence par `game/api/`, et le routeur le vérifie dans les deux sens. Une
     * entrée mal formée est **ignorée** (le module reste utilisable, l'adresse
     * retombe sur le jeu ou sur un 404) :
     *
     * @param mixed $routes
     * @return array<string, array{0: string, 1: string}>
     */
    private static function routeMap(mixed $routes, bool $api): array
    {
        if (!is_array($routes)) {
            return array();
        }

        $clean = array();

        foreach ($routes as $path => $target) {
            $path = rtrim(ltrim(strtolower(trim((string) $path)), '/'), '/');
            $target = trim((string) $target);

            // Une page vit dans la zone du jeu, une route JSON sous `game/api/` :
            // l'inverse est refusé (le routeur décide d'après le préfixe).
            if ($api !== str_starts_with($path, 'game/api/')) {
                continue;
            }

            if (!$api && !str_starts_with($path, 'game/')) {
                continue;
            }

            $controller = explode('@', $target);

            if (count($controller) !== 2) {
                continue;
            }

            $class = trim($controller[0]);
            $action = trim($controller[1]);

            // Le nom de classe accepte un sous-dossier (`Back\ChatController`) ;
            // l'action est une méthode, sans argument.
            if (
                preg_match('/^[A-Za-z0-9_]+(?:\\\\[A-Za-z0-9_]+)*$/', $class) !== 1
                || preg_match('/^[a-zA-Z0-9_]+$/', $action) !== 1
            ) {
                continue;
            }

            $clean[$path] = array($class, $action);
        }

        return $clean;
    }

    /**
     * Redirections des adresses historiques d'un module (`chat.php` => `/game/chat`).
     *
     * @param mixed $legacy
     * @return array<string, string>
     */
    private static function legacyMap(mixed $legacy): array
    {
        if (!is_array($legacy)) {
            return array();
        }

        $clean = array();

        foreach ($legacy as $from => $to) {
            $from = strtolower(trim((string) $from));
            $to = trim((string) $to);

            if ($from === '' || $to === '') {
                continue;
            }

            $clean[$from] = $to;
        }

        return $clean;
    }

    /**
     * Dépendances d'un manifeste : le Coeur d'application (`core`) et les modules exigés.
     *
     * Une entrée mal formée est **ignorée** (un module reste utilisable même si son
     * auteur s'est trompé dans la déclaration).
     *
     * @param mixed $declared
     * @return array{core: string, modules: array<int, string>}
     */
    private static function dependencyMap(mixed $declared): array
    {
        $dependencies = array('core' => '', 'modules' => array());

        if (!is_array($declared)) {
            return $dependencies;
        }

        $core = trim((string) ($declared['core'] ?? ''));

        if ($core !== '' && preg_match('/^(>=|<=|>|<|=)?\s*\d+(?:\.\d+)*$/', $core) === 1) {
            $dependencies['core'] = $core;
        }

        foreach ((array) ($declared['modules'] ?? array()) as $module) {
            $module = strtolower(trim((string) $module));

            if (self::validName($module) && $module !== '') {
                $dependencies['modules'][] = $module;
            }
        }

        $dependencies['modules'] = array_values(array_unique($dependencies['modules']));

        return $dependencies;
    }

    /**
     * Permissions d'un manifeste : des identifiants `module.…`, la principale étant
     * ajoutée si le module l'a oubliée.
     *
     * @param mixed $permissions
     * @return array<int, string>
     */
    private static function permissionList(mixed $permissions, string $name): array
    {
        $clean = array();

        if (is_array($permissions)) {
            foreach ($permissions as $permission) {
                $permission = trim((string) $permission);

                if ($permission !== '' && str_starts_with($permission, Acl::MODULE_PREFIX)) {
                    $clean[] = $permission;
                }
            }
        }

        $main = Acl::MODULE_PREFIX . $name;

        if (!in_array($main, $clean, true)) {
            array_unshift($clean, $main);
        }

        return array_values(array_unique($clean));
    }
}
