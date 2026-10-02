<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Phalanx;
use PHPUnit\Framework\TestCase;

/**
 * Une phalange ne couvre que les systèmes voisins du sien : le niveau donne la
 * portée de part et d'autre (niveau 4 = 4 systèmes à gauche et à droite).
 */
final class PhalanxTest extends TestCase
{
    public function testTheRangeFollowsTheLevel(): void
    {
        self::assertSame(0, Phalanx::range(0));
        self::assertSame(4, Phalanx::range(4));
        self::assertSame(0, Phalanx::range(-3), 'Un niveau négatif ne couvre rien.');
    }

    public function testATargetInTheSameSystemIsAlwaysInRange(): void
    {
        self::assertTrue(Phalanx::inRange(1, 28, 1, 28, 1));
        self::assertTrue(Phalanx::inRange(1, 28, 1, 28, 10));
    }

    public function testTheLevelBoundsTheCoveredSystems(): void
    {
        // Phalange niveau 4 en 1:28:1 → systèmes 24 à 32.
        self::assertTrue(Phalanx::inRange(1, 28, 1, 24, 4));
        self::assertTrue(Phalanx::inRange(1, 28, 1, 32, 4));
        self::assertFalse(Phalanx::inRange(1, 28, 1, 23, 4));
        self::assertFalse(Phalanx::inRange(1, 28, 1, 33, 4));
    }

    public function testAnotherGalaxyIsOutOfRange(): void
    {
        self::assertFalse(Phalanx::inRange(1, 28, 2, 28, 10));
    }

    public function testWithoutAPhalanxNothingIsScanned(): void
    {
        self::assertFalse(Phalanx::inRange(1, 28, 1, 28, 0));
    }

    public function testTheCoveredSystemsStayWithinTheUniverse(): void
    {
        // La lune est dans le premier système : la borne basse reste 1.
        self::assertSame(1, Phalanx::firstSystem(2, 5));
        self::assertSame(24, Phalanx::firstSystem(28, 4));
        // La borne haute est bornée au dernier système de la galaxie.
        self::assertSame(32, Phalanx::lastSystem(28, 4, 499));
        self::assertSame(499, Phalanx::lastSystem(497, 9, 499));
    }
}
