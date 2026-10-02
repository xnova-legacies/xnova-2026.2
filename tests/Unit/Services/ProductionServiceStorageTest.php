<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\GameConstants;
use App\Services\ProductionService;
use PHPUnit\Framework\TestCase;

/**
 * Capacités de stockage : une seule implémentation (ProductionService), utilisée
 * par la production, la page /game/resources et l'état temps réel.
 *
 * Le bug corrigé venait d'une lecture de la colonne persistée de la planète,
 * restée à la constante de création (BASE_STORAGE_SIZE) : le stockage affiché
 * ne suivait alors plus les silos réellement construits.
 */
final class ProductionServiceStorageTest extends TestCase
{
    private float $base;

    protected function setUp(): void
    {
        $this->base = (float) GameConstants::baseStorageSize();
    }

    public function testCapsFollowTheStorageBuildingLevels(): void
    {
        $caps = ProductionService::storageCapacities(
            array('metal_store' => 5, 'crystal_store' => 5, 'deuterium_store' => 3),
            array()
        );

        self::assertSame((float) floor($this->base * 1.5 ** 5), $caps['metal']);
        self::assertSame((float) floor($this->base * 1.5 ** 5), $caps['crystal']);
        self::assertSame((float) floor($this->base * 1.5 ** 3), $caps['deuterium']);
        self::assertGreaterThan($this->base, $caps['metal']);
    }

    public function testAnEmptyStorageFallsBackToTheBaseSize(): void
    {
        $caps = ProductionService::storageCapacities(array(), array());

        self::assertSame($this->base, $caps['metal']);
        self::assertSame($this->base, $caps['crystal']);
        self::assertSame($this->base, $caps['deuterium']);
    }

    public function testTheCoreAppliesNoOfficerBonus(): void
    {
        // La majoration du stockeur appartient au module `officier` : le Coeur de l'application rend
        // les silos seuls, et c'est `ModuleService::resolve()` qui décide de la classe à
        // utiliser. Le test de l'officier vit donc dans le module.
        $planet = array('metal_store' => 5, 'crystal_store' => 0, 'deuterium_store' => 0);

        $withOfficerSet = ProductionService::storageCapacities($planet, array('rpg_stockeur' => 2));

        self::assertSame((float) floor($this->base * 1.5 ** 5), $withOfficerSet['metal']);
    }
}
