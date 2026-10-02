<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Modules;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Une adresse du Core est servie par sa classe, ou par le module qui la **dérive**
     * (point de surcharge) : on étend la page, on ne la remplace pas.
     */
    private function assertServedBy(string $path, string $coreClass, string $action): void
    {
        $route = $this->router->match($path);

        self::assertNotNull($route, $path . ' est routée.');
        self::assertSame($action, $route['action'], $path);

        $class = (string) $route['class'];

        self::assertTrue(
            $class === $coreClass || is_subclass_of($class, $coreClass),
            $path . ' est servie par ' . $coreClass . ' ou par la classe du module qui en hérite (vu : ' . $class . ').'
        );
    }

    public function testMatchResolvesALegacyFileRoute(): void
    {
        // L'adresse historique suit la page moderne (`overview.php` → `/game/overview`).
        $this->assertServedBy('overview.php', 'App\Controllers\Game\OverviewController', 'indexAction');

        $route = $this->router->match('overview.php');

        self::assertSame(['INSIDE' => true, 'INSTALL' => false], $route['constants'] ?? null);
    }

    public function testMatchResolvesAnMvcPath(): void
    {
        // `game/overview` est une page du Core de l'application : le registre des modules
        // est interrogé avant la table des routes, et un module déposé qui la déclare au
        // manifeste la sert — en héritant de la page du Core (module `officier`).
        $this->assertServedBy('game/overview', 'App\Controllers\Game\OverviewController', 'indexAction');
    }

    public function testMatchResolvesANestedMvcPath(): void
    {
        // Une route profonde vise le contrôleur du Core (ou la classe du module qui en
        // hérite), et la méthode suit le dernier segment.
        $this->assertServedBy('game/profil/techdetails', 'App\Controllers\Game\ProfilController', 'techdetailsAction');
    }

    public function testMatchSupportsTheFrontModule(): void
    {
        $route = $this->router->match('front/login');

        self::assertNotNull($route);
        self::assertSame('App\Controllers\Front\LoginController', $route['class']);
    }

    public function testMatchAppliesControllerAliases(): void
    {
        $route = $this->router->match('game/stat');

        self::assertNotNull($route);
        self::assertSame('App\Controllers\Game\StatsController', $route['class']);
    }

    /**
     * Le nom de classe ne se déduit pas toujours du segment d'URL
     * (`declarelist` donnerait `DeclarelistController`). L'autoload PSR-4 cherche
     * le fichier au nom exact : sans alias, ces adresses ne répondent que sur un
     * système de fichiers insensible à la casse. Un module qui apporte la même
     * page l'annonce à son manifeste (`Router::CONTROLLER_ALIASES` reste au Core
     * pour les siennes).
     */
    public function testMatchAppliesEveryControllerAlias(): void
    {
        $aliases = array(
            'back/declarelist' => 'App\Controllers\Back\DeclareListController',
            'back/messagelist' => 'App\Controllers\Back\MessageListController',
            'game/mipattack' => 'App\Controllers\Game\MipAttackController',
            'game/missile-attack' => 'App\Controllers\Game\MissileLaunchController',
        );

        foreach ($aliases as $path => $class) {
            $route = $this->router->match($path);

            self::assertNotNull($route, $path . ' doit être routé');
            self::assertSame($class, $route['class'], $path);
        }
    }

    /**
     * Le nom de classe déduit d'un segment d'URL doit correspondre **à la casse
     * près** à un fichier du dossier : sur un système sensible à la casse, une
     * seule majuscule d'écart fait un 404 (`messagelist` → `Messagelist`).
     * Comparer la liste **réelle** des fichiers attrape l'erreur même sous
     * Windows, dont le volume est insensible à la casse.
     */
    public function testEveryAdminPageNameMatchesARealFile(): void
    {
        $paths = array(
            'back/overview', 'back/userlist', 'back/multi', 'back/chat', 'back/credit',
            'back/messagelist', 'back/flying-fleets', 'back/settings', 'back/message-all',
            'back/queue-fix', 'back/reset', 'back/menu', 'back/actions', 'back/player',
            'back/notes', 'back/declarelist', 'game/mipattack', 'game/missile-attack',
        );
        $files = array();

        foreach ($paths as $path) {
            $route = $this->router->match($path);

            self::assertNotNull($route, $path . ' doit être routé');

            // App \ Controllers \ <module> \ <Classe>Controller
            $parts = explode('\\', $route['class']);
            $short = (string) ($parts[3] ?? '');
            $directory = rtrim(ROOT_PATH, '/\\') . '/app/Controllers/' . ($parts[2] ?? '') . '/';

            if (!isset($files[$directory])) {
                $files[$directory] = array_map('basename', (array) glob($directory . '*.php'));
            }

            self::assertContains(
                $short . '.php',
                $files[$directory],
                $path . ' : le nom de classe doit correspondre à un fichier réel.'
            );
        }
    }

    public function testLegacyAdminPagesRedirectToTheAdminPanel(): void
    {
        self::assertSame('/back/overview', $this->router->redirect301('admin/overview.php'));
        self::assertSame('/back/userlist', $this->router->redirect301('admin/userlist.php'));
        self::assertSame('/back/multi?tab=declared', $this->router->redirect301('admin/declare_list.php'));
        self::assertSame('/back/multi', $this->router->redirect301('admin/multi.php'));
    }

    /**
     * Les pages retirées du panneau (bannissements, erreurs, cryptage, exécution
     * de commande SQL, ajout de ressources, recherche d'un joueur) ne redirigent
     * plus : leur ancienne adresse ne mène nulle part, et c'est voulu.
     */
    public function testRemovedAdminPagesNoLongerRedirect(): void
    {
        foreach (array('banned', 'unbanned', 'autounban', 'errors', 'md5enc', 'QueryExecute', 'add_money', 'add_moon', 'paneladmina', 'planetlist', 'moonlist', 'activeplanet', 'md5changepass') as $page) {
            self::assertNull($this->router->redirect301('admin/' . $page . '.php'), $page);
        }
    }

    public function testMatchResolvesTheSiteRoot(): void
    {
        $route = $this->router->match('');

        self::assertNotNull($route);
        self::assertSame('App\Controllers\Game\HomeController', $route['class']);
        self::assertSame('indexAction', $route['action']);
    }

    public function testMatchResolvesApiStateRoute(): void
    {
        $route = $this->router->match('game/api/state');

        self::assertNotNull($route);
        self::assertSame('App\Controllers\Api\StateApiController', $route['class']);
        self::assertSame('showAction', $route['action']);
        self::assertTrue($route['api']);
        self::assertSame(['INSIDE' => true, 'API_REQUEST' => true], $route['constants']);
    }

    public function testMatchResolvesApiResourcesRoute(): void
    {
        $route = $this->router->match('game/api/resources/percent');

        self::assertNotNull($route);
        self::assertSame('App\Controllers\Api\ResourcesApiController', $route['class']);
        self::assertSame('percentAction', $route['action']);
        self::assertTrue($route['api']);
    }

    public function testMatchResolvesApiBuildingsRoutes(): void
    {
        $expected = [
            'game/api/buildings/add' => 'addAction',
            'game/api/buildings/destroy' => 'destroyAction',
            'game/api/buildings/cancel' => 'cancelAction',
            'game/api/buildings/remove' => 'removeAction',
        ];

        foreach ($expected as $path => $action) {
            $route = $this->router->match($path);

            self::assertNotNull($route, "Route non résolue : {$path}");
            self::assertSame('App\Controllers\Api\BuildingsApiController', $route['class']);
            self::assertSame($action, $route['action']);
            self::assertTrue($route['api']);
        }
    }

    public function testMatchResolvesQueueAndResearchRoutes(): void
    {
        $expected = [
            'game/api/queues/reorder' => ['App\Controllers\Api\QueuesApiController', 'reorderAction'],
            'game/api/research/start' => ['App\Controllers\Api\ResearchApiController', 'startAction'],
            'game/api/research/cancel' => ['App\Controllers\Api\ResearchApiController', 'cancelAction'],
        ];

        foreach ($expected as $path => [$class, $action]) {
            $route = $this->router->match($path);

            self::assertNotNull($route, "Route non résolue : {$path}");
            self::assertSame($class, $route['class']);
            self::assertSame($action, $route['action']);
            self::assertTrue($route['api']);
        }
    }

    /**
     * Une adresse qu'un module déclarait disparaît avec lui : la route n'existe plus, et
     * une route JSON inconnue reçoit le 404 JSON du Core de l'application — la page d'un
     * module absent ne laisse ni erreur fatale, ni HTML au milieu d'une réponse JSON.
     */
    public function testAModuleRouteDisappearsWithItsModule(): void
    {
        // Avec le module déposé, la route est servie par le module ; sans lui, elle
        // disparaît : une route JSON reçoit le 404 JSON du Core, une page n'existe plus —
        // ni erreur fatale, ni HTML au milieu d'une réponse JSON.
        $apiRoutes = array(
            'game/api/notes/save' => 'notes',
            'game/api/notes/delete' => 'notes',
            'game/api/chat/send' => 'chat',
        );

        foreach ($apiRoutes as $path => $module) {
            $route = $this->router->match($path);

            self::assertNotNull($route, $path . ' doit rester routé.');
            self::assertTrue($route['api'], $path);

            if (Modules::exists($module)) {
                self::assertStringContainsString('Modules\\', (string) $route['class'], $path . ' est servi par le module.');

                continue;
            }

            self::assertSame('App\Controllers\Api\ApiNotFoundController', $route['class'], $path);
            self::assertSame('notFoundAction', $route['action'], $path);
        }

        foreach (array('game/chat' => 'chat', 'game/marchand' => 'marchand') as $path => $module) {
            $route = $this->router->match($path);

            if (Modules::exists($module)) {
                self::assertStringContainsString('Modules\\', (string) ($route['class'] ?? ''), $path . ' est servi par le module.');

                continue;
            }

            self::assertNull($route, $path . ' n\'existe plus sans son module.');
        }
    }

    public function testMatchResolvesProfilMessageAndFleetRoutes(): void
    {
        $expected = [
            'game/api/profil/rename' => ['App\Controllers\Api\ProfilApiController', 'renameAction'],
            'game/api/messages/send' => ['App\Controllers\Api\MessagesApiController', 'sendAction'],
            'game/api/fleet/estimate' => ['App\Controllers\Api\FleetApiController', 'estimateAction'],
            'game/api/fleet/send' => ['App\Controllers\Api\FleetApiController', 'sendAction'],
            'game/api/options/save' => ['App\Controllers\Api\OptionsApiController', 'saveAction'],
            'game/api/options/vacation' => ['App\Controllers\Api\OptionsApiController', 'vacationAction'],
            'game/api/shipyard/add' => ['App\Controllers\Api\ShipyardApiController', 'addAction'],
        ];

        foreach ($expected as $path => [$class, $action]) {
            $route = $this->router->match($path);

            self::assertNotNull($route, "Route non résolue : {$path}");
            self::assertSame($class, $route['class']);
            self::assertSame($action, $route['action']);
            self::assertTrue($route['api']);
        }
    }

    public function testUnknownApiPathReturnsAJsonNotFoundRoute(): void
    {
        $route = $this->router->match('game/api/does-not-exist');

        self::assertNotNull($route);
        self::assertSame('App\Controllers\Api\ApiNotFoundController', $route['class']);
        self::assertSame('notFoundAction', $route['action']);
        self::assertTrue($route['api']);
    }

    public function testLegacyRoutesAreNotFlaggedAsApi(): void
    {
        $route = $this->router->match('overview.php');

        self::assertNotNull($route);
        self::assertArrayNotHasKey('api', $route);
    }

    public function testMatchReturnsNullForUnknownRoutes(): void
    {
        self::assertNull($this->router->match('game/does-not-exist'));
        self::assertNull($this->router->match('unknown/path'));
        self::assertNull($this->router->match('nope.php'));
    }

    public function testRedirect301MapsLegacyFilesToMvcUrls(): void
    {
        self::assertSame('/game/overview', $this->router->redirect301('overview.php'));
        self::assertSame('/game/fleet/floten1', $this->router->redirect301('floten1.php'));
        self::assertSame('/front/login', $this->router->redirect301('login.php'));
        self::assertSame('/game/profil/messages', $this->router->redirect301('messages.php'));
    }

    public function testRedirect301ReturnsNullWhenThereIsNoTarget(): void
    {
        self::assertNull($this->router->redirect301('index.php'));
        self::assertNull($this->router->redirect301('overview'));
        self::assertNull($this->router->redirect301('unknown.php'));
    }

    public function testEveryLegacyRedirectTargetStartsWithASlash(): void
    {
        foreach (Router::LEGACY_TO_NEW as $legacyFile => $target) {
            self::assertStringEndsWith('.php', $legacyFile);
            self::assertStringStartsWith('/', $target);
        }
    }
}
