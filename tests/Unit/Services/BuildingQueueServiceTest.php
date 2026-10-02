<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\GameConstants;
use App\Core\GameData;
use App\Services\BuildingQueueService;
use PHPUnit\Framework\TestCase;

final class BuildingQueueServiceTest extends TestCase
{
    private BuildingQueueService $service;
    private array $resourceBackup = [];

    protected function setUp(): void
    {
        $this->service = new BuildingQueueService();
        // Les prix et la place disponible viennent des tables de jeu (vars.php).
        GameData::load();
        $this->resourceBackup = $GLOBALS['resource'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['resource'] = $this->resourceBackup;
    }

    public function testAllowedElementsDependOnPlanetType(): void
    {
        $onPlanet = $this->service->allowedElements(1);
        $onMoon = $this->service->allowedElements(3);

        self::assertContains(1, $onPlanet);
        self::assertContains(31, $onPlanet);
        self::assertNotContains(41, $onPlanet); // base lunaire : lune uniquement

        self::assertContains(41, $onMoon);
        self::assertNotContains(1, $onMoon);    // mine de métal : planète uniquement
    }

    public function testUnknownPlanetTypeHasNoAllowedElement(): void
    {
        self::assertSame([], $this->service->allowedElements(2));
        self::assertFalse($this->service->isAllowed(2, 1));
    }

    public function testIsAllowed(): void
    {
        self::assertTrue($this->service->isAllowed(1, 1));
        self::assertFalse($this->service->isAllowed(1, 41));
    }

    public function testQueueLengthHandlesEmptyValues(): void
    {
        self::assertSame(0, $this->service->queueLength(['b_building_id' => '0']));
        self::assertSame(0, $this->service->queueLength(['b_building_id' => '']));
        self::assertSame(0, $this->service->queueLength([]));
    }

    public function testQueueLengthCountsEntries(): void
    {
        self::assertSame(1, $this->service->queueLength(['b_building_id' => '1,3,10,100,build']));
        self::assertSame(
            3,
            $this->service->queueLength([
                'b_building_id' => '1,3,10,100,build;2,4,20,200,build;3,5,30,300,destroy',
            ])
        );
    }

    public function testIsQueueFullUsesTheGameConstant(): void
    {
        $entry = '1,3,10,100,build';
        $full = implode(';', array_fill(0, GameConstants::maxBuildingQueueSize(), $entry));

        self::assertTrue($this->service->isQueueFull(['b_building_id' => $full]));
        self::assertFalse($this->service->isQueueFull(['b_building_id' => $entry]));
    }

    public function testQueueContainsDetectsAnElement(): void
    {
        $planet = ['b_building_id' => '1,3,10,100,build;24,2,5,150,build'];

        self::assertTrue($this->service->queueContains($planet, 24));
        self::assertFalse($this->service->queueContains($planet, 25));
    }

    public function testHasRoomComparesFieldsWithQueue(): void
    {
        $planet = ['field_max' => 100, 'terraformer' => 0, 'field_current' => 99];

        self::assertTrue($this->service->hasRoom($planet));
        self::assertFalse($this->service->hasRoom(['field_max' => 100, 'terraformer' => 0, 'field_current' => 100]));
    }

    public function testHasRoomTakesTheQueueIntoAccount(): void
    {
        $planet = ['field_max' => 100, 'terraformer' => 0, 'field_current' => 98];

        self::assertTrue($this->service->hasRoom($planet, 1));
        self::assertFalse($this->service->hasRoom($planet, 2));
    }

    public function testHasRoomBenefitsFromTerraformerLevels(): void
    {
        // Chaque niveau de terraformeur ajoute 5 champs.
        $planet = ['field_max' => 100, 'terraformer' => 2, 'field_current' => 105];

        self::assertTrue($this->service->hasRoom($planet));
    }

    public function testIsAffordableComparesTheNextLevelPrice(): void
    {
        // Mine de métal (1) : 60 métal / 15 cristal, facteur 1.5.
        $planet = ['metal_mine' => 0, 'metal' => 100, 'crystal' => 20];

        self::assertTrue($this->service->isAffordable([], $planet, 1));
        self::assertFalse($this->service->isAffordable([], ['metal_mine' => 0, 'metal' => 50, 'crystal' => 20], 1));
        self::assertFalse($this->service->isAffordable([], ['metal_mine' => 0, 'metal' => 100, 'crystal' => 10], 1));
    }

    public function testIsAffordableFollowsTheCurrentLevel(): void
    {
        // Niveau 2 : floor(60 * 1.5^2) = 135 métal et floor(15 * 2.25) = 33 cristal.
        $planet = ['metal_mine' => 2, 'metal' => 140, 'crystal' => 40];

        self::assertTrue($this->service->isAffordable([], $planet, 1));
        self::assertFalse($this->service->isAffordable([], ['metal_mine' => 2, 'metal' => 130, 'crystal' => 40], 1));
    }

    public function testIsAffordableFallsBackOnTheUserLevel(): void
    {
        // La planète n'a pas encore la mine : c'est le niveau du joueur qui compte.
        $user = ['metal_mine' => 2];
        $planet = ['metal_mine' => 0, 'metal' => 140, 'crystal' => 40];

        self::assertTrue($this->service->isAffordable($user, $planet, 1));
    }

    public function testIsAffordableIsFalseInVacationMode(): void
    {
        $planet = ['metal_mine' => 0, 'metal' => 999999, 'crystal' => 999999];

        self::assertFalse($this->service->isAffordable(['vacation_mode' => 1], $planet, 1));
    }

    public function testIsAffordableRejectsAnUnknownElement(): void
    {
        self::assertFalse($this->service->isAffordable([], ['metal' => 999999, 'crystal' => 999999], 999));
    }
}
