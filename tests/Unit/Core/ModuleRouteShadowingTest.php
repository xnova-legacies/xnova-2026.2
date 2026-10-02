<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Modules;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Une route déclarée par un module ne recouvre jamais une page du Coeur d'application.
 *
 * Le routeur interroge le registre **avant** sa propre table : une adresse que le Coeur
 * d'application **sait servir** et qu'un module recopie dans son manifeste — par exemple
 * `game/overview`, pour dériver la page — gagne donc à tous les coups. Le garde des modules
 * suit le même manifeste et refuse l'adresse quand le module est éteint (« Le module … est
 * désactivé par l'administration : son adresse n'est plus accessible »), alors que le Coeur
 * d'application sert la page depuis toujours.
 *
 * Dériver une page du Coeur d'application ne se déclare pas : le module dépose le **même nom
 * court** dans la **même couche** (`controllers/OverviewController.php` pour
 * `App\Controllers\Game\OverviewController`), et `ModuleService::resolve()` retombe tout seul
 * sur la classe du jeu quand le module n'est pas utilisable. C'est ce que dit
 * `modules/README.md` : « rien à déclarer ».
 *
 * L'inverse est vrai aussi : quand la table du Coeur d'application cite une classe qui
 * **n'existe pas** (`game/api/notes/save` et son `NotesApiController`, qui vit dans le module),
 * le Coeur d'application ne sait rien servir — la déclaration du module est **nécessaire**, et le
 * refus du garde (403 avec un message clair) vaut mieux que le 404 du routeur. Le test ne
 * signale donc que les adresses réellement servies par le Coeur d'application.
 */
final class ModuleRouteShadowingTest extends TestCase
{
    /**
     * Adresses d'une table privée du routeur, lue par réflexion.
     *
     * `Router::ROUTES` et `Router::API_ROUTES` sont `private` : la réflexion est le seul
     * moyen de les interroger sans les ouvrir, comme le fait `AdminStateFilterTest` pour
     * la règle des filtres d'état.
     *
     * @return array<string, string> adresse → classe du Coeur d'application
     */
    private static function coreRoutes(string $constant): array
    {
        $routes = (new ReflectionClass(Router::class))->getConstant($constant);
        $map = array();

        foreach (is_array($routes) ? $routes : array() as $path => $route) {
            $map[(string) $path] = is_array($route) ? (string) ($route[0] ?? '') : '';
        }

        return $map;
    }

    public function testNoModuleClaimsAnAddressTheCoreAlreadyServes(): void
    {
        $modules = Modules::all();

        if ($modules === array()) {
            self::markTestSkipped('Aucun module déposé : rien à vérifier dans la version livrée.');
        }

        $coreRoutes = array();

        foreach (array('ROUTES', 'API_ROUTES') as $constant) {
            foreach (self::coreRoutes($constant) as $path => $class) {
                // Une adresse n'est un recouvrement que si le Coeur d'application la sert **vraiment** :
                // une entree dont la classe n'existe pas laisse la fonctionnalite au module.
                $coreRoutes[$path] = class_exists($class);
            }
        }

        $offenders = array();

        foreach ($modules as $name => $module) {
            $declared = array_merge(
                array_keys((array) ($module['routes'] ?? array())),
                array_keys((array) ($module['api'] ?? array()))
            );

            foreach ($declared as $path) {
                if (($coreRoutes[$path] ?? false) === true) {
                    $offenders[] = $name . ' → ' . $path;
                }
            }
        }

        self::assertSame(
            array(),
            $offenders,
            'Une page ou une route JSON du Coeur d\'application ne se déclare pas au manifeste d\'un '
            . 'module : le module la dérive (même nom court, même couche) et le Coeur d\'application '
            . 'la sert quand le module est éteint. Déclarée, le garde des modules refuserait la page '
            . 'pour tout le monde.'
        );
    }
}
