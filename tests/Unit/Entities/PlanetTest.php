<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\Planet;
use PHPUnit\Framework\TestCase;

final class PlanetTest extends TestCase
{
    private array $resourceBackup = [];

    protected function setUp(): void
    {
        $this->resourceBackup = $GLOBALS['resource'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['resource'] = $this->resourceBackup;
    }

    public function testMapsPlanetColumns(): void
    {
        $planet = Planet::fromRow([
            'id' => '42',
            'id_owner' => '7',
            'name' => 'Terre',
            'galaxy' => 1,
            'system' => 99,
            'planet' => 5,
            'planet_type' => 1,
            'metal' => '1234.5',
            'crystal' => '10',
            'deuterium' => '0',
            'field_current' => '20',
            'field_max' => '163',
        ]);

        self::assertSame(42, $planet->id());
        self::assertSame(7, $planet->ownerId());
        self::assertSame('Terre', $planet->name());
        self::assertSame(1, $planet->galaxy());
        self::assertSame(99, $planet->system());
        self::assertSame(5, $planet->planet());
        self::assertSame('1:99:5', $planet->coordinates());
        self::assertSame(1234.5, $planet->metal());
        self::assertSame(10.0, $planet->crystal());
        self::assertSame(0.0, $planet->deuterium());
        self::assertSame(20, $planet->fieldCurrent());
        self::assertSame(163, $planet->fieldMax());
        self::assertFalse($planet->isMoon());
    }

    public function testDetectsAMoon(): void
    {
        self::assertTrue(Planet::fromRow(['planet_type' => 3])->isMoon());
    }

    public function testAppliesDefaultsWhenColumnsAreMissing(): void
    {
        $planet = Planet::fromRow([]);

        self::assertSame(0, $planet->id());
        self::assertSame('', $planet->name());
        self::assertSame('0:0:0', $planet->coordinates());
        self::assertSame(0, $planet->shipCount(210));
    }

    public function testShipCountUsesTheLegacyResourceMap(): void
    {
        $GLOBALS['resource'] = [210 => 'spy_sonde'];

        $planet = Planet::fromRow(['spy_sonde' => '12']);

        self::assertSame('spy_sonde', $planet->shipField(210));
        self::assertSame(12, $planet->shipCount(210));
    }

    public function testShipFieldFallsBackWhenResourceMapIsMissing(): void
    {
        $GLOBALS['resource'] = [];

        self::assertSame('ship_999', Planet::fromRow([])->shipField(999));
    }

    public function testExposesTheRawRow(): void
    {
        $row = ['id' => 1, 'name' => 'X'];

        $planet = Planet::fromRow($row);

        self::assertSame($row, $planet->toArray());
        self::assertTrue($planet->has('name'));
        self::assertFalse($planet->has('missing'));
        self::assertSame('X', $planet->raw('name'));
        self::assertNull($planet->raw('missing'));
    }

    public function testExposesStorageAndEnergyCapacities(): void
    {
        $planet = Planet::fromRow([
            'metal' => '1234.5',
            'crystal' => '10',
            'deuterium' => '2.25',
            'metal_max' => '50000.75',
            'crystal_max' => '25000',
            'deuterium_max' => '12500.5',
            'energy_max' => '800.25',
            'energy_used' => '650',
        ]);

        self::assertSame(1234.5, $planet->metal());
        self::assertSame(2.25, $planet->deuterium());
        self::assertSame(50000.75, $planet->metalMax());
        self::assertSame(25000.0, $planet->crystalMax());
        self::assertSame(12500.5, $planet->deuteriumMax());
        self::assertSame(800.25, $planet->energyMax());
        self::assertSame(650.0, $planet->energyUsed());
    }

    public function testCapacitiesAndEnergyDefaultToZero(): void
    {
        $planet = Planet::fromRow([]);

        self::assertSame(0.0, $planet->metalMax());
        self::assertSame(0.0, $planet->crystalMax());
        self::assertSame(0.0, $planet->deuteriumMax());
        self::assertSame(0.0, $planet->energyMax());
        self::assertSame(0.0, $planet->energyUsed());
    }
}
