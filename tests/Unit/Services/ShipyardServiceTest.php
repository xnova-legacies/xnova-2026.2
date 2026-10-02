<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\GameConstants;
use App\Services\ShipyardService;
use PHPUnit\Framework\TestCase;

final class ShipyardServiceTest extends TestCase
{
    public function testKindIsRestrictedToFleetAndDefense(): void
    {
        self::assertSame('fleet', ShipyardService::kind('fleet'));
        self::assertSame('defense', ShipyardService::kind(' DEFENSE '));
        self::assertNull(ShipyardService::kind('autre'));
        self::assertNull(ShipyardService::kind(null));
    }

    public function testNormalizeUnitsKeepsPositiveCounts(): void
    {
        self::assertSame(
            array(203 => 5, 204 => 2, 210 => 1),
            ShipyardService::normalizeUnits(array('203' => '5', 204 => 2.9, 210 => '1', 205 => 0, 206 => -3, 'x' => 4))
        );
        self::assertSame(array(), ShipyardService::normalizeUnits(array()));
    }

    public function testNormalizeUnitsStripsNonDigitsAndCapsPerRow(): void
    {
        self::assertSame(array(203 => 12), ShipyardService::normalizeUnits(array(203 => '12 unités')));
        self::assertSame(
            array(203 => GameConstants::maxUnitsPerRow()),
            ShipyardService::normalizeUnits(array(203 => GameConstants::maxUnitsPerRow() + 500))
        );
        self::assertSame(array(203 => 7), ShipyardService::normalizeUnits(array(203 => 5000), 7));
    }

    public function testAmountsMatchesTheLegacyPostShape(): void
    {
        self::assertSame(array(203 => 4), ShipyardService::amounts(array('203' => '4')));
    }

    public function testUniqueElementsCanOnlyBeBuiltOnce(): void
    {
        self::assertTrue(ShipyardService::isUnique(407));
        self::assertTrue(ShipyardService::isUnique(408));
        self::assertFalse(ShipyardService::isUnique(401));

        // Rien de construit, rien en file : un exemplaire est permis.
        self::assertSame(1, ShipyardService::uniqueAllowance(408, array(
            'big_protection_shield' => 0,
            'b_hangar_id' => '',
        )));

        // Déjà construit : plus rien à commander.
        self::assertSame(0, ShipyardService::uniqueAllowance(407, array(
            'small_protection_shield' => 1,
            'b_hangar_id' => '',
        )));

        // Déjà en file : c'est le contrôle qui manquait (2 000 grands boucliers
        // avaient pu être mis en file, puis refusés par MySQL à l'écriture).
        self::assertSame(0, ShipyardService::uniqueAllowance(408, array(
            'big_protection_shield' => 0,
            'b_hangar_id' => '408,1000;408,1000;',
        )));

        // L'unicité est propre à chaque élément : le petit bouclier en file ne
        // bloque pas le grand.
        self::assertSame(1, ShipyardService::uniqueAllowance(408, array(
            'big_protection_shield' => 0,
            'b_hangar_id' => '407,1;',
        )));

        // Un élément ordinaire n'est pas concerné (-1 = aucune règle d'unicité).
        self::assertSame(-1, ShipyardService::uniqueAllowance(401, array()));
    }
}
