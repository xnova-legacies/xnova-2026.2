<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MissileService;
use PHPUnit\Framework\TestCase;

/**
 * Règle du tir de missiles : portée, autorisation et motif de refus.
 *
 * Ces méthodes sont pures (aucune écriture en base) : c'est la **seule**
 * validation du tir, partagée par les deux pages qui tirent.
 */
final class MissileServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function planet(array $overrides = array()): array
    {
        return $overrides + array(
            'id' => 5,
            'galaxy' => 2,
            'system' => 100,
            'planet' => 4,
            'id_owner' => 3,
            'silo' => MissileService::SILO_LEVEL,
            'interplanetary_misil' => 5,
        );
    }

    /** @return array<string, mixed> */
    private static function user(array $overrides = array()): array
    {
        return $overrides + array('id' => 3, 'impulse_motor_tech' => 3);
    }

    public function testTheRangeFollowsTheImpulseEngine(): void
    {
        // Négative sans moteur à impulsion : `launchAllowed()` refuse alors le tir.
        self::assertSame(-1, MissileService::range(0));
        self::assertSame(1, MissileService::range(1));
        self::assertSame(5, MissileService::range(3));
    }

    public function testTheFlightLastsAtLeastOneSecond(): void
    {
        // Vitesse ×1000 (2500 par défaut, 2 500 000 en jeu) : la formule tombe sous
        // la seconde, mais une salve doit rester en vol pour être une mission.
        self::assertSame(1, MissileService::flightTime(100, 100, 2500000));
        self::assertSame(1, MissileService::flightTime(100, 101, 2500000));

        // Vitesse normale : 30 s sur place, 60 s de plus par système parcouru.
        self::assertSame(30, MissileService::flightTime(100, 100, 2500));
        self::assertSame(90, MissileService::flightTime(100, 101, 2500));
        self::assertSame(90, MissileService::flightTime(101, 100, 2500));
    }

    public function testTheLaunchNeedsASiloAndARange(): void
    {
        self::assertTrue(MissileService::launchAllowed(self::planet(), self::user(), 2, 102));
        self::assertTrue(MissileService::launchAllowed(self::planet(), self::user(), 2, 98));

        // Silo trop bas, hors galaxie, hors portée, sans moteur à impulsion.
        self::assertFalse(MissileService::launchAllowed(self::planet(array('silo' => 3)), self::user(), 2, 100));
        self::assertFalse(MissileService::launchAllowed(self::planet(), self::user(), 3, 100));
        self::assertFalse(MissileService::launchAllowed(self::planet(), self::user(), 2, 106));
        self::assertFalse(MissileService::launchAllowed(self::planet(), self::user(array('impulse_motor_tech' => 0)), 2, 100));
    }

    public function testAnAcceptableLaunchHasNoRefusal(): void
    {
        self::assertNull(MissileService::refusal(self::user(), self::planet(), self::planet(), 2, 100, 5));
    }

    public function testTheRefusalTellsWhatIsMissing(): void
    {
        self::assertStringContainsString(
            'silo',
            (string) MissileService::refusal(self::user(), self::planet(array('silo' => 0)), self::planet(), 2, 100, 1)
        );

        self::assertStringContainsString(
            'port',
            (string) MissileService::refusal(self::user(), self::planet(), self::planet(), 2, 106, 1)
        );

        // Planète disparue : la salve n'a plus de cible.
        self::assertStringContainsString(
            'Aucune planète existante',
            (string) MissileService::refusal(self::user(), self::planet(), false, 2, 100, 1)
        );

        // Stock : zéro missile, ou plus que ce que le silo contient.
        self::assertStringContainsString(
            'assez de missiles',
            (string) MissileService::refusal(self::user(), self::planet(), self::planet(), 2, 100, 0)
        );

        self::assertStringContainsString(
            'assez de missiles',
            (string) MissileService::refusal(self::user(), self::planet(), self::planet(), 2, 100, 6)
        );
    }
}
