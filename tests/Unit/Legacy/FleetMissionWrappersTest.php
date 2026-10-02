<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use App\Services\FleetMissionService;
use PHPUnit\Framework\TestCase;

/**
 * Les tableaux globales `MissionCase*()` (appelées par FlyingFleetHandler)
 * délèguent au service : leurs méthodes cibles sont vérifiées ici.
 *
 * C'est le garde-fou d'une erreur fatale constatée en jeu : deux tableaux
 * appelaient `recycling()` et `colonisation()`, méthodes renommées `recycle()`
 * et `colonise()` dans le service. Comme FlyingFleetHandler est exécuté par
 * `common.php`, l'erreur cassait **toutes** les pages tant qu'un vol de
 * colonisation ou de recyclage était en cours.
 */
final class FleetMissionWrappersTest extends TestCase
{
    public function testEveryLegacyWrapperCallsAnExistingServiceMethod(): void
    {
        $source = file_get_contents(ROOT_PATH . 'app/Core/Legacy/FleetFunctions.php');

        self::assertIsString($source);

        // Les tableaux vont de MissionCaseStay() jusqu'à SendSimpleMessage()
        // (juste après MissionCaseExpedition), toutes sur le même modèle.
        $start = strpos($source, 'function MissionCaseStay');
        $end = strpos($source, 'function SendSimpleMessage');

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $wrappers = substr($source, $start, $end - $start);

        preg_match_all('/\$service->([A-Za-z_][A-Za-z0-9_]*)\(/', $wrappers, $matches);
        $called = array_values(array_unique($matches[1]));

        self::assertNotEmpty($called, 'Aucune tableau MissionCase* trouvée : le test doit être mis à jour.');

        foreach ($called as $method) {
            self::assertTrue(
                method_exists(FleetMissionService::class, $method),
                'FleetMissionService::' . $method . '() est appelée par un tableau legacy mais n\'existe pas.'
            );
        }
    }

    public function testMissionWrappersCoverTheHandledMissions(): void
    {
        $service = new FleetMissionService();

        foreach (array('transport', 'stay', 'stayAlly', 'spy', 'recycle', 'colonise', 'expedition') as $method) {
            self::assertTrue(method_exists($service, $method));
        }
    }
}
