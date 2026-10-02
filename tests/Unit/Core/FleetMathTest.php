<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\FleetMath;
use PHPUnit\Framework\TestCase;

final class FleetMathTest extends TestCase
{
    public function testDistanceBetweenDifferentGalaxies(): void
    {
        // 1 galaxie d'écart => 20000 unités.
        self::assertSame(40000, FleetMath::targetDistance(1, 3, 10, 20, 4, 8));
    }

    public function testDistanceBetweenDifferentSystems(): void
    {
        // 10 systèmes d'écart => 10 * 5 * 19 + 2700.
        self::assertSame(3650, FleetMath::targetDistance(2, 2, 10, 20, 4, 8));
    }

    public function testDistanceBetweenDifferentPlanetsOfTheSameSystem(): void
    {
        // 5 positions d'écart => 5 * 5 + 1000.
        self::assertSame(1025, FleetMath::targetDistance(2, 2, 10, 10, 4, 9));
    }

    public function testDistanceToTheSamePlanetIsMinimal(): void
    {
        self::assertSame(5, FleetMath::targetDistance(2, 2, 10, 10, 4, 4));
    }

    public function testMissionDurationFollowsTheGameFormula(): void
    {
        // (35000 / 2500 * sqrt(1000 * 10 / 10000) + 10) / 1 = 24
        self::assertSame(24, FleetMath::missionDuration(2500, 10000, 1000, 1));
    }

    public function testMissionDurationGrowsWithDistance(): void
    {
        $near = FleetMath::missionDuration(2500, 10000, 1000, 1);
        $far = FleetMath::missionDuration(2500, 10000, 9000, 1);

        self::assertGreaterThan($near, $far);
    }

    public function testMissionDurationGrowsWhenSpeedFactorDecreases(): void
    {
        $fast = FleetMath::missionDuration(2500, 10000, 1000, 1);
        $slow = FleetMath::missionDuration(2500, 10000, 1000, 0.5);

        self::assertGreaterThan($fast, $slow);
    }

    public function testFleetMaxSpeedUsesImpulseDriveAboveLevelFour(): void
    {
        // Petit transporteur (202) : tech impulsion >= 5 => speed2 + 20% par niveau.
        $player = ['impulse_motor_tech' => 5, 'combustion_tech' => 0];

        self::assertEqualsWithDelta(15000, FleetMath::fleetMaxSpeed('', 202, $player), 0.001);
    }

    public function testFleetMaxSpeedUsesCombustionDriveBelowImpulseFive(): void
    {
        // 5000 + 5000 * 10 * 0.1 = 10000
        $player = ['impulse_motor_tech' => 4, 'combustion_tech' => 10];

        self::assertEqualsWithDelta(10000, FleetMath::fleetMaxSpeed('', 202, $player), 0.001);
    }

    public function testFleetMaxSpeedReturnsOneSpeedPerShipForAFleet(): void
    {
        $player = ['impulse_motor_tech' => 0, 'combustion_tech' => 10, 'hyperspace_motor_tech' => 0];

        $speeds = FleetMath::fleetMaxSpeed(['203' => 2], 0, $player);

        // Grand transporteur (203) : 7500 + 7500 * 10 * 0.1 = 15000
        self::assertSame([203 => 15000.0], $speeds);
    }

    public function testFleetMaxSpeedReturnsZeroForAnUnknownShip(): void
    {
        $player = ['impulse_motor_tech' => 0, 'combustion_tech' => 0];

        self::assertSame(0, FleetMath::fleetMaxSpeed('', 999, $player));
    }

    public function testShipConsumptionSwitchesWithImpulseDrive(): void
    {
        self::assertSame(20, FleetMath::shipConsumption(202, ['impulse_motor_tech' => 0]));
        self::assertSame(40, FleetMath::shipConsumption(202, ['impulse_motor_tech' => 5]));
    }

    public function testFleetConsumptionIsOneWhenNoShipIsSent(): void
    {
        self::assertSame(1, FleetMath::fleetConsumption([], 1, 20, 1000, 10000, ['impulse_motor_tech' => 0]));
    }

    public function testFleetConsumptionIgnoresAZeroDenominator(): void
    {
        // missionDuration * speedFactor - 10 == 0 => aucun vaisseau ne consomme.
        self::assertSame(1, FleetMath::fleetConsumption([205 => 1], 1, 10, 1000, 10000, ['impulse_motor_tech' => 0]));
    }

    public function testFleetConsumptionFollowsTheLegacyFormula(): void
    {
        // Croiseur (205) : vitesse 10000, consommation 75.
        $player = ['impulse_motor_tech' => 0, 'combustion_tech' => 0, 'hyperspace_motor_tech' => 0];

        self::assertSame(264003, FleetMath::fleetConsumption([205 => 1], 1, 20, 1000, 10000, $player));
    }

    public function testNextJumpWaitTimeWithoutAJumpGate(): void
    {
        $result = FleetMath::nextJumpWaitTime(['jump_gate' => 0, 'last_jump_time' => 0]);

        self::assertSame(0, $result['value']);
        self::assertSame('', $result['string']);
    }

    public function testNextJumpWaitTimeReturnsTheRemainingDelay(): void
    {
        // Porte niveau 1 => 1 heure entre deux sauts, dernier saut à l'instant présent.
        $result = FleetMath::nextJumpWaitTime(['jump_gate' => 1, 'last_jump_time' => time()]);

        self::assertGreaterThan(3500, $result['value']);
        self::assertLessThanOrEqual(3600, $result['value']);
        self::assertNotSame('', $result['string']);
    }
}
