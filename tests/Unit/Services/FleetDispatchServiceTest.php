<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FleetDispatchService;
use PHPUnit\Framework\TestCase;

/**
 * Les contrôles d'envoi (vaisseaux, soute, stock) sont testés ici ; l'écriture de
 * la flotte passe par la base et n'est pas couverte unitairement.
 */
final class FleetDispatchServiceTest extends TestCase
{
    public function testMissionsCoverTheStandardSet(): void
    {
        // Les missions du Core de l'application, dans l'ordre, plus celles qu'un module
        // déclare à son manifeste : le module « extracteurs » ajoute la 12, et elle doit
        // venir d'un manifeste — le Core n'en garde aucun identifiant en dur.
        $core = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15);
        $missions = FleetDispatchService::missions();

        self::assertSame($core, array_values(array_intersect($missions, $core)));

        foreach (array_diff($missions, $core) as $declared) {
            self::assertArrayHasKey(
                $declared,
                FleetDispatchService::galaxyDebrisMissions(),
                'Une mission en plus du Core vient d\'un manifeste (mission de champ de débris).'
            );
        }

        self::assertSame(10, FleetDispatchService::MISSION_ORBIT);
    }

    public function testHoldTargetMustBeAFriendOrAnAlly(): void
    {
        $moi = array('ally_id' => 5);

        self::assertTrue(
            FleetDispatchService::holdTargetAllowed($moi, array('ally_id' => 5), false),
            'Même alliance : stationnement autorisé.'
        );
        self::assertTrue(
            FleetDispatchService::holdTargetAllowed($moi, array('ally_id' => 9), true),
            'Ami accepté : stationnement autorisé.'
        );
        self::assertFalse(
            FleetDispatchService::holdTargetAllowed($moi, array('ally_id' => 9), false),
            'Ni ami ni allié : refusé.'
        );
        self::assertFalse(
            FleetDispatchService::holdTargetAllowed(array('ally_id' => 0), array('ally_id' => 0), false),
            'Sans alliance, il faut être ami.'
        );
    }

    public function testOrbitTargetMustBeTheDeparturePlanet(): void
    {
        $origin = array('galaxy' => 1, 'system' => 1, 'planet' => 1, 'planet_type' => 1);

        self::assertTrue(FleetDispatchService::orbitTargetOk($origin, $origin));
        // Même coordonnée mais lune : ce n'est pas la planète de départ.
        self::assertFalse(FleetDispatchService::orbitTargetOk(
            $origin,
            array('galaxy' => 1, 'system' => 1, 'planet' => 1, 'planet_type' => 3)
        ));
        // Une autre coordonnée, même à soi : refusée.
        self::assertFalse(FleetDispatchService::orbitTargetOk(
            $origin,
            array('galaxy' => 1, 'system' => 1, 'planet' => 2, 'planet_type' => 1)
        ));
        self::assertFalse(FleetDispatchService::orbitTargetOk(
            $origin,
            array('galaxy' => 2, 'system' => 1, 'planet' => 1, 'planet_type' => 1)
        ));
    }

    public function testNormalizeSpeedClampsToAllowedTenths(): void
    {
        self::assertSame(10, FleetDispatchService::MAX_SPEED);
        self::assertSame(10, FleetDispatchService::normalizeSpeed(10));
        self::assertSame(10, FleetDispatchService::normalizeSpeed(50));
        self::assertSame(4, FleetDispatchService::normalizeSpeed('4'));
        self::assertSame(1, FleetDispatchService::normalizeSpeed(0));
        self::assertSame(1, FleetDispatchService::normalizeSpeed(-3));
    }

    public function testNormalizeShipsKeepsPositiveIntegerCounts(): void
    {
        $ships = FleetDispatchService::normalizeShips(array(
            '203' => '5',
            '204' => 3,
            '205' => 0,
            '206' => -2,
            'foo' => 4,
        ));

        self::assertSame(array(203 => 5, 204 => 3), $ships);
    }

    public function testStorageNeededSumsTheCargo(): void
    {
        self::assertSame(60.0, FleetDispatchService::storageNeeded(10.0, 20.0, 30.0));
    }

    public function testMaxExpeditionsFollowsTheTechnology(): void
    {
        // Sans la technologie (124), aucune expedition n'est possible ; sinon la
        // page /game/fleet autorise 1 + tech / 3 vols simultanes.
        self::assertSame(0, FleetDispatchService::maxExpeditions(0));
        self::assertSame(1, FleetDispatchService::maxExpeditions(1));
        self::assertSame(2, FleetDispatchService::maxExpeditions(3));
        self::assertSame(2, FleetDispatchService::maxExpeditions(5));
        self::assertSame(4, FleetDispatchService::maxExpeditions(9));
    }

    public function testExpeditionMissionIsDeclared(): void
    {
        self::assertSame(15, FleetDispatchService::MISSION_EXPEDITION);
        self::assertArrayHasKey(FleetDispatchService::MISSION_EXPEDITION, FleetDispatchService::MISSIONS);
    }

    /**
     * Une colonie abandonnée ou une lune détruite n'est plus une planète : aucune
     * mission ne peut la viser, sauf celles qui partent vers une position libre
     * (colonisation, recyclage, expédition).
     */
    public function testMissionsThatNeedAWorldAtArrival(): void
    {
        foreach (array(1, 2, 3, 4, 5, 6, 9, 10) as $mission) {
            self::assertTrue(
                FleetDispatchService::missionNeedsTarget($mission),
                'La mission ' . $mission . ' doit exiger une planète à l\'arrivée.'
            );
        }

        foreach (array(7, 8, 15) as $mission) {
            self::assertFalse(
                FleetDispatchService::missionNeedsTarget($mission),
                'La mission ' . $mission . ' vise une position libre.'
            );
        }
    }

    public function testCapacityComparison(): void
    {
        self::assertTrue(FleetDispatchService::capacityOk(100.0, 100.0));
        self::assertFalse(FleetDispatchService::capacityOk(99.0, 100.0));
    }

    public function testStockCheckIncludesTheFuel(): void
    {
        $planet = array('metal' => 1000, 'crystal' => 1000, 'deuterium' => 500);

        self::assertTrue(FleetDispatchService::stockOk($planet, 100.0, 100.0, 100.0, 400));
        // Le carburant est prélevé en plus de la cargaison transportée.
        self::assertFalse(FleetDispatchService::stockOk($planet, 100.0, 100.0, 100.0, 401));
        self::assertFalse(FleetDispatchService::stockOk($planet, 1001.0, 10.0, 0.0, 0));
        self::assertFalse(FleetDispatchService::stockOk($planet, 10.0, 1001.0, 0.0, 0));
    }
}
