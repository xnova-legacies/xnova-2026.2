<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ProductionService;
use PHPUnit\Framework\TestCase;

final class ProductionServiceTest extends TestCase
{
    public function testRatesIncludeResourceMultiplierAndBasicIncome(): void
    {
        $planet = [
            'planet_type' => 1,
            'energy_max' => 100,
            'metal_perhour' => 100,
            'crystal_perhour' => 50,
            'deuterium_perhour' => 25,
        ];
        $gameConfig = [
            'resource_multiplier' => 2,
            'metal_basic_income' => 10,
            'crystal_basic_income' => 5,
            'deuterium_basic_income' => 2,
        ];

        $rates = ProductionService::effectiveRates($planet, $gameConfig);

        // Production des mines x multiplicateur, puis revenu de base x multiplicateur.
        self::assertSame(220.0, $rates['metal']);
        self::assertSame(110.0, $rates['crystal']);
        self::assertSame(54.0, $rates['deuterium']);
        self::assertSame(100, $rates['production_level']);
    }

    public function testWithoutEnergyBasicIncomeReplacesMineProduction(): void
    {
        $planet = ['planet_type' => 1, 'energy_max' => 0, 'metal_perhour' => 100];
        $gameConfig = [
            'resource_multiplier' => 1,
            'metal_basic_income' => 10,
            'crystal_basic_income' => 0,
            'deuterium_basic_income' => 0,
        ];

        $rates = ProductionService::effectiveRates($planet, $gameConfig);

        // Le revenu de base remplace la production des mines, puis s'ajoute
        // encore : comportement legacy conservé à l'identique (10 + 10).
        self::assertSame(20.0, $rates['metal']);
    }

    public function testMoonHasNoMineProductionButKeepsBasicIncome(): void
    {
        $planet = [
            'planet_type' => 3,
            'energy_max' => 100,
            'metal_perhour' => 100,
            'crystal_perhour' => 50,
            'deuterium_perhour' => 25,
        ];
        $gameConfig = [
            'resource_multiplier' => 1,
            'metal_basic_income' => 5,
            'crystal_basic_income' => 5,
            'deuterium_basic_income' => 5,
        ];

        $rates = ProductionService::effectiveRates($planet, $gameConfig);

        self::assertSame(5.0, $rates['metal']);
        self::assertSame(5.0, $rates['crystal']);
        self::assertSame(5.0, $rates['deuterium']);
    }

    public function testMissingConfigurationKeysFallBackToZero(): void
    {
        $planet = ['planet_type' => 1, 'energy_max' => 0];

        $rates = ProductionService::effectiveRates($planet, []);

        self::assertSame(0.0, $rates['metal']);
        self::assertSame(0.0, $rates['crystal']);
        self::assertSame(0.0, $rates['deuterium']);
    }
}
