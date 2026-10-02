<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FleetMissionService;
use PHPUnit\Framework\TestCase;

/**
 * L'attaque groupée est résolue en une seule bataille : le moteur ne connaît
 * qu'une flotte attaquante, donc les flottes du groupe sont fusionnées en une
 * seule ligne (vaisseaux et soutes additionnés, coordonnées du meneur gardées).
 */
final class FleetGroupTest extends TestCase
{
    public function testTheShipsAndTheCargoOfEveryMemberAreSummed(): void
    {
        $merged = FleetMissionService::mergeGroup(array(
            array(
                'fleet_id' => 22,
                'fleet_owner' => 1,
                'fleet_array' => '204,200;205,60;',
                'fleet_amount' => 260,
                'fleet_resource_metal' => 100,
                'fleet_resource_crystal' => 0,
                'fleet_resource_deuterium' => 50,
                'fleet_start_galaxy' => 1,
                'fleet_end_galaxy' => 1,
                'fleet_start_time' => 1000,
            ),
            array(
                'fleet_id' => 23,
                'fleet_owner' => 3,
                'fleet_array' => '204,120;206,10;',
                'fleet_amount' => 130,
                'fleet_resource_metal' => 5,
                'fleet_resource_crystal' => 7,
                'fleet_resource_deuterium' => 0,
                'fleet_start_galaxy' => 2,
                'fleet_end_galaxy' => 1,
                'fleet_start_time' => 1000,
            ),
        ));

        self::assertSame('204,320;205,60;206,10;', $merged['fleet_array']);
        self::assertSame(390, $merged['fleet_amount']);
        self::assertSame(105, $merged['fleet_resource_metal']);
        self::assertSame(7, $merged['fleet_resource_crystal']);
        self::assertSame(50, $merged['fleet_resource_deuterium']);
        self::assertSame(0, $merged['fleet_mess'], 'La flotte fusionnée part au combat.');
    }

    public function testTheLeaderKeepsItsIdentityCoordinatesAndDates(): void
    {
        $merged = FleetMissionService::mergeGroup(array(
            array(
                'fleet_id' => 22,
                'fleet_owner' => 1,
                'fleet_array' => '204,10;',
                'fleet_start_galaxy' => 1,
                'fleet_start_system' => 1,
                'fleet_start_planet' => 1,
                'fleet_end_galaxy' => 1,
                'fleet_end_system' => 28,
                'fleet_end_planet' => 4,
                'fleet_owner_note' => 'meneur',
            ),
            array(
                'fleet_id' => 23,
                'fleet_owner' => 3,
                'fleet_array' => '204,5;',
                'fleet_start_galaxy' => 2,
                'fleet_start_system' => 34,
                'fleet_start_planet' => 4,
                'fleet_end_galaxy' => 1,
                'fleet_end_system' => 28,
                'fleet_end_planet' => 4,
                'fleet_owner_note' => 'invité',
            ),
        ));

        self::assertSame(22, $merged['fleet_id']);
        self::assertSame(1, $merged['fleet_owner']);
        // Le trajet (départ et arrivée) reste celui du meneur.
        self::assertSame(1, $merged['fleet_start_system']);
        self::assertSame(1, $merged['fleet_start_galaxy']);
        self::assertSame(28, $merged['fleet_end_system']);
        self::assertSame('meneur', $merged['fleet_owner_note']);
    }

    public function testAnEmptyOrBrokenFleetArrayDoesNotBreakTheMerge(): void
    {
        $merged = FleetMissionService::mergeGroup(array(
            array('fleet_id' => 1, 'fleet_array' => '', 'fleet_resource_metal' => 0),
            array('fleet_id' => 2, 'fleet_array' => '204,0;abc,x;206,3;'),
        ));

        self::assertSame('206,3;', $merged['fleet_array']);
        self::assertSame(3, $merged['fleet_amount']);
    }
}
