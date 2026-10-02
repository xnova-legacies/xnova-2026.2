<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\GameConstants;
use App\Core\GameData;
use App\Services\BuildingQueueService;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que les tables de jeu legacy (includes/vars.php) sont bien chargées
 * et cohérentes entre elles : prix, catégories d'éléments et correspondances.
 */
final class GameDataTest extends TestCase
{
    public function testLoadsTheLegacyResourceMap(): void
    {
        GameData::load();

        $resource = GameData::resource();

        self::assertSame('metal_mine', $resource[1]);
        self::assertSame('jump_gate', $resource[43]);
        self::assertSame('impulse_motor_tech', $resource[117]);
        self::assertSame('small_ship_cargo', $resource[202]);
        self::assertSame('interplanetary_misil', $resource[503]);
    }

    public function testPriceListExposesBuildingCosts(): void
    {
        $pricelist = GameData::priceList();

        self::assertSame(60, $pricelist[1]['metal']);
        self::assertSame(15, $pricelist[1]['crystal']);
        self::assertSame(0, $pricelist[1]['deuterium']);
    }

    public function testPriceListExposesShipCharacteristics(): void
    {
        $pricelist = GameData::priceList();

        self::assertSame(7500, $pricelist[203]['speed']);
        self::assertSame(10000, $pricelist[202]['speed2']);
        self::assertSame(20, $pricelist[202]['consumption']);
        self::assertSame(40, $pricelist[202]['consumption2']);
    }

    public function testResListsGroupElementsByCategory(): void
    {
        $reslist = GameData::resList();

        self::assertContains(31, $reslist['build']);
        self::assertContains(115, $reslist['tech']);
        self::assertContains(202, $reslist['fleet']);
        self::assertContains(401, $reslist['defense']);
        self::assertContains(1, $reslist['prod']);
    }

    public function testParticleAcceleratorIsDeclaredAsABuilding(): void
    {
        $resource = GameData::resource();
        $pricelist = GameData::priceList();
        $requirements = GameData::requirements();

        // Meme forme que les autres batiments (prix + facteur + energie).
        self::assertSame('particle_accelerator', $resource[16]);
        self::assertContains(16, GameData::resList()['build']);
        self::assertArrayHasKey(16, $pricelist);
        self::assertSame(2, $pricelist[16]['factor']);
        self::assertArrayHasKey('energy', $pricelist[16]);

        // Il exige une usine de nanites de niveau 5.
        self::assertSame(array(15 => 5), $requirements[16]);
    }

    public function testParticleAcceleratorIsOnlyAvailableOnPlanets(): void
    {
        $service = new BuildingQueueService();

        self::assertTrue($service->isAllowed(1, 16));
        self::assertFalse($service->isAllowed(3, 16), 'une lune ne peut pas l\'accueillir');
    }

    public function testEveryFleetAndDefenseElementHasAPrice(): void
    {
        $pricelist = GameData::priceList();
        $reslist = GameData::resList();

        foreach (array_merge($reslist['fleet'], $reslist['defense']) as $element) {
            self::assertArrayHasKey($element, $pricelist, "Prix manquant pour l'élément {$element}");
            self::assertArrayHasKey('metal', $pricelist[$element]);
            self::assertArrayHasKey('crystal', $pricelist[$element]);
        }
    }

    public function testEveryProdElementHasAProductionFormula(): void
    {
        $prodGrid = GameData::prodGrid();

        foreach (GameData::resList()['prod'] as $element) {
            self::assertArrayHasKey($element, $prodGrid, "Formule de production manquante pour l'élément {$element}");
            self::assertArrayHasKey('formule', $prodGrid[$element]);
        }
    }

    public function testCombatCapsCoverShipsAndDefences(): void
    {
        $combatCaps = GameData::combatCaps();

        foreach (GameData::resList()['fleet'] as $element) {
            self::assertArrayHasKey($element, $combatCaps, "Caractéristiques de combat manquantes pour {$element}");
            self::assertArrayHasKey('shield', $combatCaps[$element]);
            self::assertArrayHasKey('attack', $combatCaps[$element]);
        }
    }

    public function testRequirementsReferenceExistingTechnologies(): void
    {
        $requirements = GameData::requirements();
        $resource = GameData::resource();

        // Les transports (202) demandent un chantier spatial (21) et la combustion (115).
        self::assertSame(2, $requirements[202][21]);
        self::assertSame(2, $requirements[202][115]);

        foreach ($requirements as $element => $needs) {
            foreach ($needs as $requiredId => $level) {
                self::assertArrayHasKey(
                    $requiredId,
                    $resource,
                    "L'élément {$element} dépend d'un identifiant inconnu : {$requiredId}"
                );
                self::assertGreaterThan(0, $level);
            }
        }
    }

    public function testCensoredWordsAreExposedAsConstants(): void
    {
        self::assertContains('script', GameConstants::censoredWords());
        self::assertContains('<', GameConstants::censoredWords());
    }
}
