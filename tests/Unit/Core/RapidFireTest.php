<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Combat\RapidFire;
use PHPUnit\Framework\TestCase;

/**
 * Le RapidFire est lu dans la table des capacités de combat (`sd`), mais
 * seulement contre les unités réellement engagées en face : le rapport ne liste
 * pas ce qui aurait pu se passer dans un autre combat.
 */
final class RapidFireTest extends TestCase
{
    /** @var array<int, array{sd: array<int, int>}> */
    private array $caps = array(
        204 => array('sd' => array(210 => 5, 212 => 5)),
        206 => array('sd' => array(204 => 6, 401 => 10)),
        211 => array('sd' => array(402 => 20, 403 => 20)),
        402 => array('sd' => array()),
    );

    /** @var array<int, string> */
    private array $names = array(
        204 => 'Chasseur léger',
        206 => 'Croiseur',
        210 => 'Sonde d\'espionnage',
        211 => 'Bombardier',
        212 => 'Satellite solaire',
        402 => 'Artillerie laser légère',
    );

    public function testOnlyUnitsPresentOnTheOtherSideAreListed(): void
    {
        // Le chasseur léger a du RapidFire contre la sonde et le satellite, mais
        // aucun des deux n'est dans le combat : rien à afficher.
        $rows = RapidFire::rows(
            array(204 => array('count' => 10)),
            array(402 => array('count' => 3)),
            $this->caps,
            $this->names
        );

        self::assertSame(array(), $rows);
    }

    public function testTheRapidFireOfEachEngagedUnitIsListedWithItsTarget(): void
    {
        $rows = RapidFire::rows(
            array(204 => array('count' => 10), 206 => array('count' => 4), 211 => array('count' => 2)),
            array(204 => array('count' => 25), 402 => array('count' => 8)),
            $this->caps,
            $this->names
        );

        self::assertSame(array(
            array('unit' => 'Croiseur', 'target' => 'Chasseur léger', 'shots' => 6),
            array('unit' => 'Bombardier', 'target' => 'Artillerie laser légère', 'shots' => 20),
        ), $rows);
    }

    public function testDestroyedUnitsDoNotFire(): void
    {
        $rows = RapidFire::rows(
            array(206 => array('count' => 0)),
            array(204 => array('count' => 25)),
            $this->caps,
            $this->names
        );

        self::assertSame(array(), $rows);
    }

    public function testAValueOfOneIsNotRapidFire(): void
    {
        $caps = array(204 => array('sd' => array(402 => 1, 401 => 0)));

        $rows = RapidFire::rows(
            array(204 => array('count' => 3)),
            array(402 => array('count' => 3), 401 => array('count' => 3)),
            $caps,
            $this->names
        );

        self::assertSame(array(), $rows);
    }

    public function testAnUnknownUnitFallsBackOnItsIdentifier(): void
    {
        $rows = RapidFire::rows(
            array(206 => array('count' => 1)),
            array(204 => array('count' => 1)),
            $this->caps,
            array()
        );

        self::assertSame(array(array('unit' => '206', 'target' => '204', 'shots' => 6)), $rows);
    }
}
