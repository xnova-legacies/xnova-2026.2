<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\Fleet;
use PHPUnit\Framework\TestCase;

final class FleetTest extends TestCase
{
    public function testParsesTheFleetArrayIntoShipCounts(): void
    {
        $fleet = Fleet::fromRow(['fleet_array' => '202,5;203,3;']);

        self::assertSame([202 => 5, 203 => 3], $fleet->array());
    }

    public function testParsesAnEmptyFleetArray(): void
    {
        self::assertSame([], Fleet::fromRow(['fleet_array' => ''])->array());
        self::assertSame([], Fleet::fromRow([])->array());
    }

    public function testNewBuildsAFleetRecord(): void
    {
        $fleet = Fleet::new(
            7,
            3,
            '202,5;203,3;',
            ['galaxy' => 1, 'system' => 2, 'planet' => 3, 'type' => 1, 'time' => 100],
            ['galaxy' => 1, 'system' => 2, 'planet' => 4, 'type' => 1, 'time' => 200]
        );

        self::assertSame(7, $fleet->ownerId());
        self::assertSame(3, $fleet->mission());
        self::assertSame(2, $fleet->amount());
        self::assertSame('202,5;203,3;', $fleet->raw('fleet_array'));
        self::assertSame(100, (int) $fleet->raw('fleet_start_time'));
        self::assertSame(200, (int) $fleet->raw('fleet_end_time'));
        self::assertFalse($fleet->isReturning());
        self::assertFalse($fleet->isStayMission());
    }

    public function testNewKeepsOptionalResourcesAndStayTime(): void
    {
        $fleet = Fleet::new(
            7,
            3,
            '202,1;',
            ['galaxy' => 1, 'system' => 2, 'planet' => 3, 'type' => 1, 'time' => 100],
            [
                'galaxy' => 1, 'system' => 2, 'planet' => 4, 'type' => 1, 'time' => 200,
                'stay' => 3600, 'metal' => 500, 'crystal' => 250, 'deuterium' => 100, 'owner' => 42,
            ]
        );

        self::assertSame(3600, (int) $fleet->raw('fleet_end_stay'));
        self::assertSame(500, (int) $fleet->raw('fleet_resource_metal'));
        self::assertSame(250, (int) $fleet->raw('fleet_resource_crystal'));
        self::assertSame(100, (int) $fleet->raw('fleet_resource_deuterium'));
        self::assertSame(42, $fleet->targetOwner());
        self::assertTrue($fleet->isStayMission());
    }

    public function testTargetOwnerIsAlwaysAnInteger(): void
    {
        // Expedition (ou recyclage) : la cible est une position vide, donc sans
        // proprietaire. La colonne `fleet_target_owner` etant un entier, la chaine
        // vide etait refusee par MySQL en mode strict : l'enregistrement du vol
        // echouait et les vaisseaux, retires avant, disparaissaient.
        $fleet = Fleet::new(
            7,
            15,
            '202,1;',
            ['galaxy' => 1, 'system' => 2, 'planet' => 3, 'type' => 1, 'time' => 100],
            ['galaxy' => 1, 'system' => 2, 'planet' => 16, 'type' => 1, 'time' => 200, 'owner' => '']
        );

        self::assertSame(0, $fleet->targetOwner());
        self::assertSame(0, $fleet->raw('fleet_target_owner'));

        $sansProprietaire = Fleet::new(
            7,
            15,
            '202,1;',
            ['galaxy' => 1, 'system' => 2, 'planet' => 3, 'type' => 1, 'time' => 100],
            ['galaxy' => 1, 'system' => 2, 'planet' => 16, 'type' => 1, 'time' => 200]
        );

        self::assertSame(0, $sansProprietaire->targetOwner());

        // Ligne lue en base ou construite par le code legacy.
        self::assertSame(0, Fleet::fromRow(['fleet_target_owner' => ''])->targetOwner());
        self::assertSame(7, Fleet::fromRow(['fleet_target_owner' => '7'])->targetOwner());
    }

    public function testDetectsReturningFleet(): void
    {
        self::assertTrue(Fleet::fromRow(['fleet_mess' => 1])->isReturning());
        self::assertFalse(Fleet::fromRow(['fleet_mess' => 0])->isReturning());
    }

    public function testExposesCoordinatesTimesAndCargo(): void
    {
        $fleet = Fleet::fromRow([
            'fleet_start_galaxy' => '2',
            'fleet_start_system' => '17',
            'fleet_start_planet' => '4',
            'fleet_start_type' => '1',
            'fleet_end_galaxy' => '2',
            'fleet_end_system' => '18',
            'fleet_end_planet' => '9',
            'fleet_end_type' => '3',
            'fleet_start_time' => '1700000000',
            'fleet_end_time' => '1700003600',
            'fleet_end_stay' => '3600',
            'fleet_resource_metal' => '1200',
            'fleet_resource_crystal' => '300',
            'fleet_resource_deuterium' => '50',
        ]);

        self::assertSame(2, $fleet->startGalaxy());
        self::assertSame(17, $fleet->startSystem());
        self::assertSame(4, $fleet->startPlanet());
        self::assertSame(1, $fleet->startType());
        self::assertSame(2, $fleet->endGalaxy());
        self::assertSame(18, $fleet->endSystem());
        self::assertSame(9, $fleet->endPlanet());
        self::assertSame(3, $fleet->endType());
        self::assertSame('2:17:4', $fleet->startCoordinates());
        self::assertSame('2:18:9', $fleet->endCoordinates());
        self::assertSame(1700000000, $fleet->departureTime());
        self::assertSame(1700003600, $fleet->arrivalTime());
        self::assertSame(3600, $fleet->stayTime());
        self::assertSame(1200, $fleet->cargoMetal());
        self::assertSame(300, $fleet->cargoCrystal());
        self::assertSame(50, $fleet->cargoDeuterium());
    }

    public function testCoordinatesTimesAndCargoDefaultToZero(): void
    {
        $fleet = Fleet::fromRow([]);

        self::assertSame(0, $fleet->startGalaxy());
        self::assertSame('0:0:0', $fleet->startCoordinates());
        self::assertSame('0:0:0', $fleet->endCoordinates());
        self::assertSame(0, $fleet->arrivalTime());
        self::assertSame(0, $fleet->stayTime());
        self::assertSame(0, $fleet->cargoMetal());
    }
}
