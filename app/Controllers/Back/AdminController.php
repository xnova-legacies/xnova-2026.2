<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\AbstractController;
use App\Core\Acl;
use App\Core\GameConfig;
use App\Core\Language;
use App\Core\Modules;
use App\Core\Project;
use App\Core\Request;
use App\Core\Response;
use App\Core\TemplateEngine;
use App\Services\AclService;
use App\Services\ModuleService;

/**
 * Coeur d'application des pages du panneau d'administration.
 *
 * Toutes les pages admin partagent la même garde (`AdminAccess`) et le même
 * habillage (l'en-tête admin, sans barre de navigation de jeu). Les pages
 * préparatoires héritent de ce contrôleur : elles ne font que préparer des
 * données, le balisage vit dans `app/View/OpenGame/admin/*.tpl`.
 */
abstract class AdminController extends AbstractController
{
    /** Constantes attendues par le code legacy des pages d'administration. */
    public static function legacyConstants(): array
    {
        return ['INSIDE' => true, 'INSTALL' => false, 'IN_ADMIN' => true];
    }

    /**
     * Permission exigée par la page, prise dans le catalogue `App\Core\Acl`.
     *
     * Chaque page du panneau **la déclare** : il n'y a pas de permission par
     * défaut. Un oubli ferme la page (voir `guard()`) au lieu de l'ouvrir — et
     * `tests/Unit/Core/AclTest` vérifie que chaque contrôleur en déclare une qui
     * existe vraiment.
     */
    protected function requiredPermission(): string
    {
        return '';
    }

    /**
     * La garde d'accès, à appeler en tête de chaque action.
     *
     * Renvoie la réponse « accès refusé » quand le rôle du compte ne porte pas la
     * permission de la page, et `null` quand la page peut s'afficher. Le compte
     * d'installation (identifiant 1) est super administrateur : il passe toujours.
     */
    protected function guard(): ?Response
    {
        // Les trois fichiers de libellés du panneau sont chargés ici : la garde
        // passe avant tout, et les pages piochent aussi bien dans les libellés
        // d'administration que dans ceux du menu (planètes, messages, outils).
        $this->includeLang('admin');
        $this->includeLang('admin/adminpanel');
        $this->includeLang('leftmenu');

        $user = $this->user();
        $permission = $this->requiredPermission();

        // Une permission vide (page qui ne la déclare pas) ou inconnue du
        // catalogue ferme la page : jamais de porte ouverte par omission.
        if (
            $permission !== '' && Acl::exists($permission)
            && (new AclService())->can($user, $permission)
        ) {
            return null;
        }

        $lang = $this->lang();

        return $this->renderPage(
            $this->adminTemplate('access_denied', array(
                'access_denied_message' => $lang['sys_noalloaw'] ?? '',
            )),
            $lang['sys_noaccess'] ?? 'Acces refuse',
            false,
            '',
            true
        );
    }

    /**
     * Liens du menu latéral : section => [clé de langue du lien => permission
     * exigée] (une permission vide = lien toujours visible, comme le forum ou le
     * retour au jeu).
     *
     * Le menu ne montre que ce que le rôle du compte ouvre : un opérateur ne voit
     * pas les réglages du jeu dans sa colonne, et une section dont tous les liens
     * sont fermés disparaît avec son titre. Le gabarit porte les marqueurs de
     * classe (`{adm_conf_class}`, `{section_admin_class}`).
     */
    private const MENU = array(
        'admin' => array(
            'adm_over' => 'admin.overview',
            'adm_conf' => 'admin.settings',
            'adm_reset' => 'admin.reset',
            'adm_extcopy' => 'admin.credit',
            'adm_roles' => 'admin.roles',
            'adm_modules' => 'admin.modules',
        ),
        'player' => array(
            'adm_plrlst' => 'admin.userlist',
            'adm_player' => 'admin.player',
            'adm_actions' => 'admin.actions',
            'adm_multi' => 'admin.multi',
        ),
        'tool' => array(
            'adm_fleet' => 'admin.fleets',
            'adm_build' => 'admin.queue',
        ),
        'Messages' => array(
            'adm_msg' => 'admin.messages',
            'adm_notes' => 'admin.notes',
            'adm_msg_all' => 'admin.message-all',
            'adm_chat' => 'admin.chat',
        ),
        'infog' => array(
            'adm_help' => '',
            'adm_back' => '',
        ),
    );

    /**
     * Le menu latéral de l'administration.
     *
     * Une seule implémentation, utilisée par le Coeur d'application (`adminPage()`) et par la
     * page d'accueil du panneau : les liens du gabarit sont absolus, la page
     * courante n'a plus besoin d'être ouverte dans un cadre.
     */
    protected function adminMenu(): string
    {
        $this->includeLang('admin');
        $this->includeLang('admin/adminpanel');
        // Les libellés des entrées du menu viennent de `leftmenu.mo`, comme pour
        // le menu de jeu : la page historique héritait du même fichier.
        $this->includeLang('leftmenu');

        $config = GameConfig::load();
        $acl = new AclService();
        $user = $this->user();
        $visibility = array();

        // Une entrée fermée est masquée par une classe (jamais retirée du
        // gabarit), et une section sans aucune entrée ouverte disparaît aussi.
        foreach (self::MENU as $section => $links) {
            $visible = 0;

            foreach ($links as $label => $permission) {
                $open = $permission === '' || (Acl::exists($permission) && $acl->can($user, $permission));
                $visibility[$label . '_class'] = $open ? '' : ' d-none';
                $visible += $open ? 1 : 0;
            }

            $visibility['section_' . $section . '_class'] = $visible > 0 ? '' : ' d-none';
        }

        // Les pages qu'un **module** apporte au panneau s'ajoutent d'elles-mêmes : un
        // module dont l'adresse principale est une page du panneau (`/back/...`) prend
        // sa place dans le menu, avec son libellé et sa permission. Le Coeur d'application ne
        // connaît le nom d'aucun module — c'est le manifeste qui décide.
        $visibility['adm_modules_links'] = $this->moduleMenuLinks($user);

        return $this->adminTemplate('left_menu', $this->lang() + $visibility + array(
            'dpath' => $this->skinPath(),
            'XNovaRelease' => Project::version(),
            'servername' => (string) ($config['game_name'] ?? 'XNova'),
        ));
    }

    /** État « éléments existants » des filtres de liste (défaut). */
    public const STATE_LIVE = 'live';

    /** État « éléments supprimés logiquement » des filtres de liste. */
    public const STATE_DELETED = 'deleted';

    /**
     * État demandé par un filtre de liste : existants (défaut) ou supprimés
     * logiquement. Toute valeur inconnue — ou un tableau, qui arrive d'une URL
     * bricolée — revient à « existants ».
     */
    public static function stateFilter(mixed $value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return strtolower(trim((string) $value)) === self::STATE_DELETED;
    }

    /**
     * Clé d'état à reprendre dans les liens (pagination, filtres, retour).
     */
    public static function stateKey(bool $deleted): string
    {
        return $deleted ? self::STATE_DELETED : self::STATE_LIVE;
    }

    /**
     * Identifiants cochés dans un tableau à sélection multiple (`sele[<id>]`).
     *
     * Une seule implémentation : les pages de liste du panneau s'en servent, elles
     * ne relisent plus le tableau posté chacune de leur côté.
     *
     * @return list<int>
     */
    protected static function selection(Request $request): array
    {
        $selection = $request->post('sele');
        $ids = array();

        if (is_array($selection)) {
            foreach (array_keys($selection) as $key) {
                if (is_numeric($key)) {
                    $ids[] = (int) $key;
                }
            }
        }

        return $ids;
    }

    /**
     * Boutons du filtre d'état (« existants » / « supprimés ») : le même gabarit,
     * rempli deux fois. `$query` garde les autres filtres de la page (la catégorie
     * d'un message, par exemple) et reçoit `state`.
     *
     * Une seule implémentation : les pages à liste qui filtrent par état s'en
     * servent, elles ne réécrivent pas les deux liens.
     *
     * @param array<string, scalar> $query
     */
    protected function stateButtons(
        string $path,
        array $lang,
        bool $deleted,
        string $liveLabel,
        string $deletedLabel,
        array $query = array()
    ): string {
        $html = '';

        foreach (
            array(
                array(self::STATE_LIVE, $liveLabel, false),
                array(self::STATE_DELETED, $deletedLabel, true),
            ) as $state
        ) {
            $html .= $this->adminTemplate('filter_button', $lang + array(
                'filter_label' => (string) ($lang[$state[1]] ?? $state[0]),
                'filter_href' => $path . '?' . str_replace('&', '&amp;', http_build_query(
                    array_merge($query, array('state' => $state[0]))
                )),
                'filter_active' => $state[2] === $deleted ? ' active' : '',
            ));
        }

        return $html;
    }

    /**
     * Rendu d'une page admin : en-tête admin, sans barre de navigation de jeu,
     * avec le menu latéral de l'administration.
     *
     * `$withMenu = false` pour la page qui **est** le menu (le hub), afin de ne
     * pas l'afficher deux fois.
     */
    protected function adminPage(string $body, string $title, bool $withMenu = true): Response
    {
        if ($withMenu) {
            $body = $this->adminTemplate('layout', array(
                'admin_menu' => $this->adminMenu(),
                'admin_body' => $body,
            ));
        }

        return $this->renderPage($body, $title, false, '', true);
    }

    /**
     * Rendu d'une page de l'administration.
     *
     * Le squelette (`admin/panel.tpl`) n'est écrit qu'une fois : chaque page ne
     * fournit que son corps (un tableau ou un formulaire) et son pied.
     *
     * @param array<string, mixed> $data données du gabarit de corps
     */
    protected function adminPanel(
        string $name,
        array $data,
        string $title,
        string $icon = 'bi-sliders',
        string $footer = '',
        bool $padded = false,
    ): string {
        return $this->adminTemplate('panel', array(
            'panel_icon' => $icon,
            'panel_title' => $title,
            'panel_body' => $this->adminTemplate($name, $data),
            'panel_body_class' => $padded ? 'card-body' : 'card-body p-0',
            // Le pied n'est masqué que lorsqu'il n'a rien à dire : la classe est
            // la seule différence, le gabarit reste unique.
            'panel_footer_class' => $footer === ''
                ? 'card-footer small text-body-secondary d-none'
                : 'card-footer small text-body-secondary',
            'panel_footer' => $footer,
        ));
    }

    /** Rendu d'un gabarit admin (les gabarits de l'admin vivent dans `admin/`). */
    protected function adminTemplate(string $name, array $data = array()): string
    {
        return TemplateEngine::render('admin/' . $name, $data);
    }

    /**
     * Réponse d'une page du panneau dont le **module** n'est pas là.
     *
     * Une page qui s'appuie sur une classe d'un module (le dépôt des notes, celui du
     * tchat) ne doit jamais tomber sur une classe introuvable : sans le module, il n'y a
     * rien à administrer, et la page le dit. Le module peut être **absent** (archivé, ou
     * jamais déposé) ou **éteint** : le message est le même, et le libellé vient du module.
     */
    protected function moduleMissing(string $name): Response
    {
        // Le libellé du module vient de son dossier déposé, sinon de son archive
        // (`modules/.archive/<nom>/`) : les deux appels sont tolérants.
        Language::include('modules');
        Language::includeModule($name);
        Language::includeArchived($name);

        $lang = $this->lang();
        $label = (string) ($lang[Modules::label($name)] ?? $name);

        $body = $this->adminPanel(
            'module_missing',
            array(
                'module_missing_message' => str_replace(
                    '%s',
                    $label,
                    (string) ($lang['mod_missing_message'] ?? '')
                ),
            ),
            $label,
            'bi-box-seam'
        );

        return $this->adminPage($body, $label);
    }

    /**
     * Entrées de menu des **modules** qui apportent une page au panneau.
     *
     * Un module dont la page principale est une page du panneau (`/back/...`) se
     * présente ici : son libellé vient de son module (`mod_<nom>`) et l'accès suit la
     * même règle que ses autres pages (`ModuleService::accessible()`). Un module
     * éteint disparaît du menu, sauf pour qui administre les modules.
     *
     * @param array<string, mixed> $user
     */
    private function moduleMenuLinks(array $user): string
    {
        $modules = new ModuleService();
        Language::includeModules();
        $moduleLang = Language::all();
        $links = '';

        foreach ($modules->all() as $name => $module) {
            $page = (string) $module['page'];

            if (!str_starts_with($page, '/back/') || !$modules->accessible($name, $user)) {
                continue;
            }

            $links .= TemplateEngine::render('left_menu_link', array(
                'menu_link_url' => $page,
                'menu_link_label' => (string) ($moduleLang[$module['label']] ?? $name),
                'menu_link_accesskey' => '',
            ));
        }

        return $links;
    }

    /** Action par défaut : la page d'accueil du panneau n'existe pas encore. */
    public function indexAction(Request $request): Response
    {
        return $this->redirect('/back/overview');
    }
}
