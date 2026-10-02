<?php

namespace App\Core;

use App\Controllers\Api\ApiNotFoundController;
use App\Controllers\Api\BuildingsApiController;
use App\Controllers\Api\FleetApiController;
use App\Controllers\Api\FleetsApiController;
use App\Controllers\Api\MessagesApiController;
use App\Controllers\Api\OptionsApiController;
use App\Controllers\Api\ProfilApiController;
use App\Controllers\Api\QueuesApiController;
use App\Controllers\Api\ResearchApiController;
use App\Controllers\Api\ResourcesApiController;
use App\Controllers\Api\ShipyardApiController;
use App\Controllers\Api\StateApiController;
use App\Controllers\Front\BannedController;
use App\Controllers\Front\ChangelogController;
use App\Controllers\Front\ContactController;
use App\Controllers\Front\CreditController;
use App\Controllers\Front\LoginController;
use App\Controllers\Front\LogoutController;
use App\Controllers\Front\LostPasswordController;
use App\Controllers\Game\AddDeclareController;
use App\Controllers\Game\BuddyController;
use App\Controllers\Game\BuildingsController;
use App\Controllers\Game\FleetController;
use App\Controllers\Game\FramesController;
use App\Controllers\Game\GalaxyController;
use App\Controllers\Game\HomeController;
use App\Controllers\Game\InfosController;
use App\Controllers\Game\JumpgateController;
use App\Controllers\Game\LeftMenuController;
use App\Controllers\Game\MissileLaunchController;
use App\Controllers\Game\MipAttackController;
use App\Controllers\Game\MultiController;
use App\Controllers\Game\OverviewController;
use App\Controllers\Game\PhalanxController;
use App\Controllers\Game\ProfilController;
use App\Controllers\Game\RegController;
use App\Controllers\Game\ResourcesController;
use App\Controllers\Game\RulesController;
use App\Controllers\Game\RwController;
use App\Controllers\Game\SearchController;
use App\Controllers\Game\StatsController;
use App\Controllers\Game\UserSettingsController;
use App\Controllers\Game\AcsController;
use App\Services\ModuleService;

final class Router
{
    private const ROUTES = [
        '' => [HomeController::class, 'indexAction'],
        'index.php' => [HomeController::class, 'indexAction'],
        'login.php' => [LoginController::class, 'indexAction'],
        'logout.php' => [LogoutController::class, 'indexAction'],
        'lostpassword.php' => [LostPasswordController::class, 'indexAction'],
        'changelog.php' => [ChangelogController::class, 'indexAction'],
        'credit.php' => [CreditController::class, 'indexAction'],
        'rules.php' => [RulesController::class, 'indexAction'],
        'contact.php' => [ContactController::class, 'indexAction'],
        'banned.php' => [BannedController::class, 'indexAction'],
        'techtree.php' => [ProfilController::class, 'techtreeAction'],
        'techdetails.php' => [ProfilController::class, 'techdetailsAction'],
        'frames.php' => [FramesController::class, 'indexAction'],
        'leftmenu.php' => [LeftMenuController::class, 'indexAction'],
        'multi.php' => [MultiController::class, 'indexAction'],
        'search.php' => [SearchController::class, 'indexAction'],
        'imperium.php' => [ProfilController::class, 'imperiumAction'],
        'reg.php' => [RegController::class, 'indexAction'],
        'neusuw.php' => [UserSettingsController::class, 'indexAction'],
        'overview.php' => [OverviewController::class, 'indexAction'],
        'options.php' => [ProfilController::class, 'optionsAction'],
        'messages.php' => [ProfilController::class, 'messagesAction'],
        'buddy.php' => [BuddyController::class, 'indexAction'],
        'resources.php' => [ResourcesController::class, 'indexAction'],
        'buildings.php' => [BuildingsController::class, 'indexAction'],
        'fleet.php' => [FleetController::class, 'indexAction'],
        'fleetback.php' => [FleetController::class, 'backAction'],
        'fleetshortcut.php' => [FleetController::class, 'shortcutAction'],
        'quickfleet.php' => [FleetController::class, 'quickfleetAction'],
        'floten1.php' => [FleetController::class, 'floten1Action'],
        'floten2.php' => [FleetController::class, 'floten2Action'],
        'floten3.php' => [FleetController::class, 'floten3Action'],
        'flotenajax.php' => [FleetController::class, 'flotenajaxAction'],
        'galaxy.php' => [GalaxyController::class, 'indexAction'],
        'phalanx.php' => [PhalanxController::class, 'indexAction'],
        'jumpgate.php' => [JumpgateController::class, 'indexAction'],
        'mipattack.php' => [MipAttackController::class, 'indexAction'],
        'missile-attack.php' => [MissileLaunchController::class, 'indexAction'],
        'rw.php' => [RwController::class, 'indexAction'],
        'acs.php' => [AcsController::class, 'indexAction'],
        'add_declare.php' => [AddDeclareController::class, 'indexAction'],
        'stat.php' => [StatsController::class, 'indexAction'],
        'stats.php' => [StatsController::class, 'indexAction'],
        'infos.php' => [InfosController::class, 'indexAction'],
    ];

    /** Préfixe commun des points d'entrée JSON. */
    public const API_PREFIX = 'game/api/';

    /**
     * Constantes de bootstrap des routes API : INSIDE est requis par les
     * fichiers legacy (includes/vars.php) et API_REQUEST permet à common.php
     * de répondre en JSON au lieu de rediriger vers la page de connexion.
     */
    public const API_CONSTANTS = ['INSIDE' => true, 'API_REQUEST' => true];

    /**
     * Points d'entrée JSON. Toute autre URL /game/api/... reçoit un 404 JSON.
     */
    private const API_ROUTES = [
        'game/api/state' => [StateApiController::class, 'showAction'],
        'game/api/resources/percent' => [ResourcesApiController::class, 'percentAction'],
        'game/api/buildings/add' => [BuildingsApiController::class, 'addAction'],
        'game/api/buildings/destroy' => [BuildingsApiController::class, 'destroyAction'],
        'game/api/buildings/cancel' => [BuildingsApiController::class, 'cancelAction'],
        'game/api/buildings/remove' => [BuildingsApiController::class, 'removeAction'],
        'game/api/queues/reorder' => [QueuesApiController::class, 'reorderAction'],
        'game/api/research/start' => [ResearchApiController::class, 'startAction'],
        'game/api/research/cancel' => [ResearchApiController::class, 'cancelAction'],
        'game/api/notes/save' => [NotesApiController::class, 'saveAction'],
        'game/api/notes/delete' => [NotesApiController::class, 'deleteAction'],
        'game/api/profil/rename' => [ProfilApiController::class, 'renameAction'],
        'game/api/messages/send' => [MessagesApiController::class, 'sendAction'],
        'game/api/fleet/estimate' => [FleetApiController::class, 'estimateAction'],
        'game/api/fleet/send' => [FleetApiController::class, 'sendAction'],
        'game/api/fleets' => [FleetsApiController::class, 'indexAction'],
        'game/api/options/save' => [OptionsApiController::class, 'saveAction'],
        'game/api/options/vacation' => [OptionsApiController::class, 'vacationAction'],
        'game/api/shipyard/add' => [ShipyardApiController::class, 'addAction'],
    ];

    public const LEGACY_TO_NEW = [
        'login.php' => '/front/login',
        'logout.php' => '/front/logout',
        'lostpassword.php' => '/front/lostpassword',
        'changelog.php' => '/front/changelog',
        'credit.php' => '/front/credit',
        'contact.php' => '/front/contact',
        'banned.php' => '/front/banned',
        'overview.php' => '/game/overview',
        'buildings.php' => '/game/buildings',
        'resources.php' => '/game/resources',
        'fleet.php' => '/game/fleet',
        'fleetback.php' => '/game/fleet/back',
        'fleetshortcut.php' => '/game/fleet/shortcut',
        'quickfleet.php' => '/game/fleet/quickfleet',
        'floten1.php' => '/game/fleet/floten1',
        'floten2.php' => '/game/fleet/floten2',
        'floten3.php' => '/game/fleet/floten3',
        'flotenajax.php' => '/game/fleet/flotenajax',
        'galaxy.php' => '/game/galaxy',
        'phalanx.php' => '/game/phalanx',
        'jumpgate.php' => '/game/jumpgate',
        'mipattack.php' => '/game/mipattack',
        'raketenangriff.php' => '/game/missile-attack',
        'missile-attack.php' => '/game/missile-attack',
        'rw.php' => '/game/rw',
        'verband.php' => '/game/acs',
        'acs.php' => '/game/acs',
        'add_declare.php' => '/game/add-declare',
        'buddy.php' => '/game/buddy',
        'search.php' => '/game/search',
        'multi.php' => '/game/multi',
        'leftmenu.php' => '/game/leftmenu',
        'frames.php' => '/game/frames',
        'reg.php' => '/game/reg',
        'rules.php' => '/game/rules',
        'neusuw.php' => '/game/neusuw',
        'stat.php' => '/game/stat',
        'infos.php' => '/game/infos',
        'imperium.php' => '/game/profil/imperium',
        'techtree.php' => '/game/profil',
        'techdetails.php' => '/game/profil/techdetails',
        'options.php' => '/game/profil/options',
        'messages.php' => '/game/profil/messages',

        // Panneau d'administration migré : l'adresse historique redirige.
        // Les pages retirées (bannissements, erreurs, cryptage, exécution de
        // commande SQL, ajout de ressources, recherche d'un joueur, listes de
        // planètes et de lunes) ne figurent plus ici : leur adresse ne mène nulle
        // part, c'est voulu — la fiche joueur dit désormais tout des planètes.
        'admin/overview.php' => '/back/overview',
        'admin/leftmenu.php' => '/back/menu',
        'admin/userlist.php' => '/back/userlist',
        'admin/multi.php' => '/back/multi',
        'admin/declare_list.php' => '/back/multi?tab=declared',
        'admin/chat.php' => '/back/chat',
        'admin/messagelist.php' => '/back/messagelist',
        'admin/ShowFlyingFleets.php' => '/back/flying-fleets',
        'admin/settings.php' => '/back/settings',
        'admin/credit.php' => '/back/credit',
        'admin/messall.php' => '/back/message-all',
        'admin/ElementQueueFixer.php' => '/back/queue-fix',
        'admin/XNovaResetUnivers.php' => '/back/reset',
    ];

    /**
     * Segment d'URL → nom de classe, quand le second ne se déduit pas du premier.
     *
     * `ucwords('mipattack')` donne `Mipattack` : l'autoload PSR-4 cherche alors
     * `MipattackController.php` et ne trouve pas `MipAttackController.php` sur un
     * système de fichiers sensible à la casse (Linux, production).
     */
    private const CONTROLLER_ALIASES = array(
        'game' => array(
            'stat' => 'Stats',
            'neusuw' => 'UserSettings',
            'mipattack' => 'MipAttack',
            // `ucwords('missile-attack')` ne donne pas le nom du fichier : le tir
            // de missiles vit dans `MissileLaunchController.php`.
            'missile-attack' => 'MissileLaunch',
        ),
        'back' => array(
            'declarelist' => 'DeclareList',
            // `ucwords('messagelist')` donne `Messagelist` : le fichier s'appelle
            // `MessageListController.php`. Sans cet alias, la page ne se charge
            // que sur un système de fichiers insensible à la casse.
            'messagelist' => 'MessageList',
        ),
    );

    public function match(string $relativePath): ?array
    {
        if (str_starts_with($relativePath, self::API_PREFIX)) {
            return $this->matchApi($relativePath);
        }

        // Un module sert ses propres pages : le jeu ne connaît pas ce qu'un module
        // apporte, il interroge le registre (`Modules::controllerFor()`).
        $moduleRoute = Modules::controllerFor($relativePath);

        if ($moduleRoute !== null) {
            $this->serveModule($relativePath);

            $constants = method_exists($moduleRoute['class'], 'legacyConstants')
                ? $moduleRoute['class']::legacyConstants()
                : [];

            return array(
                'class' => $moduleRoute['class'],
                'action' => $moduleRoute['action'],
                'constants' => $constants,
            );
        }

        $route = self::ROUTES[$relativePath] ?? null;
        if ($route !== null && class_exists($route[0])) {
            // Point de surcharge : un module peut dériver une page du jeu (même nom
            // court, même couche) ; sans module allumé, la classe du Coeur d'application
            // sert exactement comme avant.
            $class = ModuleService::resolve($route[0]);
            $constants = method_exists($class, 'legacyConstants') ? $class::legacyConstants() : [];

            return array(
                'class' => $class,
                'action' => $route[1],
                'constants' => $constants,
            );
        }

        $route = $this->matchDynamic($relativePath);
        if ($route !== null) {
            return $route;
        }

        return null;
    }

    public function redirect301(string $relativePath): ?string
    {
        // Les pages d'administration gardent leur adresse historique
        // (`admin/overview.php`) : elle redirige vers la page moderne (`/back/...`).
        if (!preg_match('#^(?:admin/)?([a-zA-Z_0-9]+)\.php$#', $relativePath, $m)) {
            return null;
        }

        // Une adresse historique d'un module redirige aussi : c'est le manifeste qui
        // la déclare, comme ses pages (`chat.php` → `/game/chat`).
        $target = Modules::legacy($relativePath) ?? self::LEGACY_TO_NEW[$relativePath] ?? null;

        return $target;
    }

    /**
     * Note le module qui sert la route, pour que l'en-tête ajoute **ses** assets.
     *
     * Seules les adresses **déclarées au manifeste** comptent : une page du jeu que
     * le module se contente de dériver (point de surcharge) garde les assets du jeu.
     */
    private function serveModule(string $relativePath): void
    {
        $module = Modules::ofRoute($relativePath);

        if ($module !== null) {
            Modules::serve($module);
        }
    }

    /**
     * Résolution des routes JSON : table explicite, sinon 404 JSON.
     *
     * @return array{class: string, action: string, constants: array<string, bool>, api: true}
     */
    private function matchApi(string $relativePath): array
    {
        // Route JSON d'un module (déclarée dans son manifeste), sinon table du jeu.
        $moduleRoute = Modules::controllerFor($relativePath);

        if ($moduleRoute !== null) {
            $this->serveModule($relativePath);

            return [
                'class' => $moduleRoute['class'],
                'action' => $moduleRoute['action'],
                'constants' => self::API_CONSTANTS,
                'api' => true,
            ];
        }

        $route = self::API_ROUTES[$relativePath] ?? null;

        if ($route === null || !class_exists($route[0])) {
            $route = [ApiNotFoundController::class, 'notFoundAction'];
        }

        return [
            'class' => $route[0],
            'action' => $route[1],
            'constants' => self::API_CONSTANTS,
            'api' => true,
        ];
    }


    private function matchDynamic(string $relativePath): ?array
    {
        $segments = explode('/', rtrim($relativePath, '/'));

        // préfixes de module valides : /game/, /front/, /back/
        $validModules = array('game', 'front', 'back');

        if (count($segments) < 2 || !in_array($segments[0], $validModules, true)) {
            return null;
        }

        $module = ucfirst($segments[0]); // game → Game, front → Front, back → Back

        $controllerName = str_replace(' ', '', ucwords(str_replace('-', ' ', str_replace('_', ' ', $segments[1]))));
        $actionName = isset($segments[2]) && $segments[2] !== '' ? $segments[2] : 'index';

        // Certains segments ne se déduisent pas du nom de classe (`declarelist` →
        // `DeclareListController`) : l'autoload PSR-4 cherche le fichier au nom
        // exact, ce qui échoue sur un système de fichiers sensible à la casse.
        $aliases = self::CONTROLLER_ALIASES[$segments[0]] ?? array();

        if (isset($aliases[$segments[1]])) {
            $controllerName = $aliases[$segments[1]];
        }

        $class = 'App\\Controllers\\' . $module . '\\' . $controllerName . 'Controller';
        $action = $actionName . 'Action';

        if (!class_exists($class) || !method_exists($class, $action)) {
            return null;
        }

        // Point de surcharge : la page peut être dérivée par un module (voir match()).
        $class = ModuleService::resolve($class);

        if (!method_exists($class, $action)) {
            return null;
        }

        $constants = $class::legacyConstants();

        return array(
            'class' => $class,
            'action' => $action,
            'constants' => $constants,
        );
    }
}
