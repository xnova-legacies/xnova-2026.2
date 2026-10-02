<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BuildingService;
use PHPUnit\Framework\TestCase;

final class BuildingServiceTest extends TestCase
{
    private BuildingService $service;

    protected function setUp(): void
    {
        $this->service = new BuildingService();
    }

    public function testBuildingPriceScalesWithTheLevelFactor(): void
    {
        // Mine de métal (1) : 60 métal / 15 cristal, facteur 1.5 -> niveau 2.
        $price = $this->service->buildingPrice(['metal_mine' => 2], ['metal_mine' => 2], 1);

        self::assertEquals(
            ['metal' => 135, 'crystal' => 33, 'deuterium' => 0, 'energy' => 0],
            $price
        );
    }

    public function testBuildingPriceFallsBackOnUserLevelWhenPlanetHasNoLevel(): void
    {
        // La planète n'a pas encore la mine : le niveau du joueur (recherche) est utilisé.
        $price = $this->service->buildingPrice(['metal_mine' => 2], ['metal_mine' => 0], 1);

        self::assertEquals(
            ['metal' => 135, 'crystal' => 33, 'deuterium' => 0, 'energy' => 0],
            $price
        );
    }

    public function testBuildingPriceWithoutIncrementalUsesBaseCost(): void
    {
        $price = $this->service->buildingPrice([], ['metal_mine' => 5], 1, false);

        self::assertEquals(
            ['metal' => 60, 'crystal' => 15, 'deuterium' => 0, 'energy' => 0],
            $price
        );
    }

    public function testBuildingPriceForDestructionIsHalved(): void
    {
        $price = $this->service->buildingPrice([], ['metal_mine' => 0], 1, false, true);

        self::assertEquals(
            ['metal' => 30, 'crystal' => 7, 'deuterium' => 0, 'energy' => 0],
            $price
        );
    }

    public function testBuildingPriceIncludesEnergyCost(): void
    {
        // Terraformeur (33) : 0 métal, 50000 cristal, 100000 deutérium, 1000 énergie, facteur 2.
        $price = $this->service->buildingPrice(['terraformer' => 0], ['terraformer' => 0], 33);

        self::assertEquals(
            ['metal' => 0, 'crystal' => 50000, 'deuterium' => 100000, 'energy' => 0],
            $price
        );

        $levelOne = $this->service->buildingPrice(['terraformer' => 1], ['terraformer' => 1], 33);

        self::assertEquals(
            ['metal' => 0, 'crystal' => 100000, 'deuterium' => 200000, 'energy' => 1000],
            $levelOne
        );
    }

    public function testMaxConstructibleElementsUsesThePrimaryResource(): void
    {
        // 10000 métal / 60 = 166 mines de métal.
        self::assertSame(166, $this->service->maxConstructibleElements(1, ['metal' => 10000]));
        self::assertSame(0, $this->service->maxConstructibleElements(1, ['metal' => 59]));
    }

    public function testMaxConstructibleElementsFallsBackOnCrystalWithoutMetal(): void
    {
        // Terraformeur (33) : aucun coût en métal -> calcul sur le cristal (100000 / 50000).
        self::assertSame(2, $this->service->maxConstructibleElements(33, ['metal' => 0, 'crystal' => 100000]));
    }

    public function testTheCoreAppliesNoOfficerBonusToItsQueues(): void
    {
        // Les points de surcharge des officiers sur les chantiers : aucune durée raccourcie,
        // aucune quantité doublée et aucune colonne d'expérience écrite sans module (le module
        // `officier` les remplit — voir `modules/officier/tests/BuildingServiceTest.php`).
        self::assertSame(0.0, BuildingService::durationBonus(array('rpg_constructeur' => 5), 'build'));
        self::assertSame(3, BuildingService::shipCount(array('rpg_destructeur' => 1), 214, 3));

        $rewards = new \ReflectionMethod(BuildingService::class, 'constructionRewards');
        $rewards->setAccessible(true);

        self::assertSame(
            array(),
            $rewards->invoke(null, array('xpminier' => 10), 1, false, array('metal' => 1000, 'crystal' => 0, 'deuterium' => 0))
        );
    }
}
