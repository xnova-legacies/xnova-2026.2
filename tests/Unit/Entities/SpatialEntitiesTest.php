<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\Aks;
use App\Entities\GalaxyEntry;
use App\Entities\Moon;
use PHPUnit\Framework\TestCase;

/**
 * Entités des objets spatiaux : lunes, cases galactiques, groupes ACS.
 */
final class SpatialEntitiesTest extends TestCase
{
    public function testMoonExposesItsPositionAndClimate(): void
    {
        $moon = Moon::fromRow(array(
            'id' => '12',
            'id_luna' => '34',
            'name' => 'Séléné',
            'image' => 'luna1',
            'destruyed' => '0',
            'id_owner' => '7',
            'galaxy' => '3',
            'system' => '44',
            'lunapos' => '9',
            'temp_min' => '-18',
            'temp_max' => '2',
            'diameter' => '8772',
        ));

        self::assertSame(12, $moon->id());
        self::assertSame(34, $moon->moonId());
        self::assertSame('Séléné', $moon->name());
        self::assertSame(7, $moon->ownerId());
        self::assertSame('3:44:9', $moon->coordinates());
        self::assertSame(-18, $moon->temperatureMin());
        self::assertSame(2, $moon->temperatureMax());
        self::assertSame(8772, $moon->diameter());
        self::assertFalse($moon->isDestroyed());
    }

    public function testMoonInDestructionIsFlagged(): void
    {
        self::assertTrue(Moon::fromRow(array('destruyed' => '1'))->isDestroyed());
    }

    public function testMoonIsForgivingWhenTheRowIsEmpty(): void
    {
        $moon = Moon::fromRow(array());

        self::assertSame(0, $moon->id());
        self::assertSame('', $moon->name());
        self::assertSame('0:0:0', $moon->coordinates());
        self::assertFalse($moon->isDestroyed());

        // `has()` décrit la ligne reçue, pas le schéma de la table.
        self::assertFalse($moon->has('id'));
        self::assertFalse($moon->has('colonne_inconnue'));
    }

    public function testGalaxyEntryDescribesAnOccupiedCell(): void
    {
        $cell = GalaxyEntry::fromRow(array(
            'galaxy' => '2',
            'system' => '17',
            'planet' => '5',
            'id_planet' => '1002',
            'metal' => '1500',
            'crystal' => '400',
            'id_luna' => '0',
            'luna' => '0',
        ));

        self::assertSame('2:17:5', $cell->coordinates());
        self::assertTrue($cell->isOccupied());
        self::assertFalse($cell->hasMoon());
        self::assertSame(1002, $cell->planetId());
        self::assertSame(1500, $cell->metal());
        self::assertSame(400, $cell->crystal());
    }

    public function testGalaxyEntryDetectsAMoon(): void
    {
        $cell = GalaxyEntry::fromRow(array('id_planet' => '0', 'id_luna' => '55', 'luna' => '3'));

        self::assertFalse($cell->isOccupied());
        self::assertTrue($cell->hasMoon());
        self::assertSame(55, $cell->moonId());
        self::assertSame(3, $cell->moonPosition());
    }

    public function testAksExposesItsRendezVousAndKeepsLegacyPayloadsRaw(): void
    {
        $aks = Aks::fromRow(array(
            'id' => '8',
            'name' => 'Alpha',
            'participants' => 'a:1;b:2;',
            'flotten' => '{"1":[203,2]}',
            'arrival' => '1700',
            'galaxy' => '3',
            'system' => '4',
            'planet' => '5',
            'invited' => '2',
        ));

        self::assertSame(8, $aks->id());
        self::assertSame('Alpha', $aks->name());
        self::assertSame(1700, $aks->arrival());
        self::assertSame('3:4:5', $aks->coordinates());
        self::assertSame(2, $aks->invited());

        // Les listes sérialisées restent accessibles telles quelles.
        self::assertSame('a:1;b:2;', $aks->raw('participants'));
        self::assertSame('{"1":[203,2]}', $aks->raw('flotten'));
        self::assertSame('a:1;b:2;', $aks->toArray()['participants']);
    }
}
