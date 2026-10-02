<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\GameSpeed;
use PHPUnit\Framework\TestCase;

/**
 * Vitesses du jeu : le formulaire d'administration demande un multiplicateur
 * (1 = normal), le moteur divise par la valeur stockée (2500 = ×1).
 */
final class GameSpeedTest extends TestCase
{
    public function testMultiplierOfStoredValues(): void
    {
        self::assertSame(1.0, GameSpeed::multiplier('2500'));
        self::assertSame(1.0, GameSpeed::multiplier(GameSpeed::NORMAL));
        self::assertSame(0.4, GameSpeed::multiplier('1000'));
        self::assertSame(1000.0, GameSpeed::multiplier('2500000'));
    }

    public function testMultiplierOfInvalidValuesIsNormal(): void
    {
        self::assertSame(1.0, GameSpeed::multiplier('abc'));
        self::assertSame(1.0, GameSpeed::multiplier(''));
        self::assertSame(1.0, GameSpeed::multiplier(null));
    }

    public function testMultiplierNeverReachesZero(): void
    {
        self::assertSame(GameSpeed::MIN_MULTIPLIER, GameSpeed::multiplier('0'));
        self::assertSame(GameSpeed::MIN_MULTIPLIER, GameSpeed::multiplier('-5000'));
    }

    public function testStoredValuesStayWholeWhenTheyAre(): void
    {
        self::assertSame('2500', GameSpeed::stored(1));
        self::assertSame('1000', GameSpeed::stored(0.4));
        self::assertSame('2500000', GameSpeed::stored('1000'));
    }

    public function testStoredRoundsTrip(): void
    {
        foreach (array('2500', '1000', '2500000', '25') as $stored) {
            self::assertSame($stored, GameSpeed::stored(GameSpeed::multiplier($stored)));
        }
    }

    public function testStoredOfInvalidValueIsNormal(): void
    {
        self::assertSame('2500', GameSpeed::stored('abc'));
        self::assertSame('25', GameSpeed::stored(0));
        self::assertSame('25', GameSpeed::stored(-3));
    }

    public function testBuildAndFleetMultipliers(): void
    {
        self::assertSame(1.0, GameSpeed::buildMultiplier(array('game_speed' => '2500')));
        self::assertSame(0.5, GameSpeed::buildMultiplier(array('game_speed' => '1250')));
        self::assertSame(2.0, GameSpeed::fleetMultiplier(array('fleet_speed' => '5000')));
    }

    public function testMultipliersDefaultToNormalWhenMissing(): void
    {
        self::assertSame(1.0, GameSpeed::buildMultiplier(array()));
        self::assertSame(1.0, GameSpeed::fleetMultiplier(array()));
    }
}
