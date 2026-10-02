<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use App\Core\GameData;
use PHPUnit\Framework\TestCase;

/**
 * Accélérateur de particules (élément 16) : il divise par deux la durée des
 * recherches par niveau, exactement comme l'usine de nanites le fait pour les
 * bâtiments, vaisseaux et défenses.
 *
 * `GetBuildingTime()` et `GetBuildingTimeLevel()` ne lisent que des globales
 * ($pricelist, $resource, $reslist, $game_config) : le test peut donc les
 * appeler sans base de données, avec un `game_speed` de 1 pour des durées
 * lisibles.
 */
final class ResearchAccelerationTest extends TestCase
{
    protected function setUp(): void
    {
        GameData::load();

        $GLOBALS['game_config'] = array('game_speed' => 1, 'resource_multiplier' => 1);
    }

    /**
     * Planète minimale : un laboratoire, des usines, et l'accélérateur au niveau
     * demandé. Les niveaux de recherche sont portés par le joueur.
     */
    private function planet(int $accelerator): array
    {
        return array(
            'laboratory' => 3,
            'robot_factory' => 2,
            'nano_factory' => 0,
            'particle_accelerator' => $accelerator,
            'metal_mine' => 5,
            'spy_tech' => 0,
        );
    }

    private function user(): array
    {
        return array(
            'spy_tech' => 4,
            'intergalactic_tech' => 0,
            'rpg_scientifique' => 0,
            'rpg_constructeur' => 0,
            'rpg_technocrate' => 0,
            'rpg_defenseur' => 0,
        );
    }

    public function testTheAcceleratorDividesTheResearchDuration(): void
    {
        $user = $this->user();
        $none = GetBuildingTime($user, $this->planet(0), 106);
        $one = GetBuildingTime($user, $this->planet(1), 106);
        $two = GetBuildingTime($user, $this->planet(2), 106);

        self::assertGreaterThan(0, $none);
        self::assertSame((int) ($none / 2), (int) $one);
        self::assertSame((int) ($none / 4), (int) $two);
    }

    public function testTheLevelVariantFollowsTheSameRule(): void
    {
        $user = $this->user();
        $none = GetBuildingTimeLevel($user, $this->planet(0), 106, 5);
        $two = GetBuildingTimeLevel($user, $this->planet(2), 106, 5);

        self::assertGreaterThan(0, $none);
        self::assertSame((int) ($none / 4), (int) $two);
    }

    public function testBuildingsKeepTheirOwnDuration(): void
    {
        // L'accélérateur ne concerne que les recherches : l'usine de nanites reste
        // seule à accélérer les bâtiments (ici la mine de métal).
        $user = $this->user();

        self::assertSame(
            GetBuildingTime($user, $this->planet(0), 1),
            GetBuildingTime($user, $this->planet(3), 1)
        );
    }
}
