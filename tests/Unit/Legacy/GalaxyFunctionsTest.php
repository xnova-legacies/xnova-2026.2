<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use PHPUnit\Framework\TestCase;

final class GalaxyFunctionsTest extends TestCase
{
    private array $resourceBackup = [];
    private array $userBackup = [];

    protected function setUp(): void
    {
        $this->resourceBackup = $GLOBALS['resource'] ?? [];
        $this->userBackup = $GLOBALS['user'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['resource'] = $this->resourceBackup;
        $GLOBALS['user'] = $this->userBackup;
    }

    public function testPhalanxRangeGrowsWithLevel(): void
    {
        self::assertSame(0, GetPhalanxRange(0));
        self::assertSame(0, GetPhalanxRange(1));
        self::assertSame(3, GetPhalanxRange(2));
        self::assertSame(8, GetPhalanxRange(3));
        self::assertSame(15, GetPhalanxRange(4));
        self::assertSame(24, GetPhalanxRange(5));
        self::assertSame(48, GetPhalanxRange(7));
    }

    public function testMissileRangeDependsOnImpulseDriveLevel(): void
    {
        $GLOBALS['resource'] = [117 => 'impulse_motor_tech'];
        $GLOBALS['user'] = ['impulse_motor_tech' => 3];

        // (3 * 5) - 1 = 14 systèmes.
        self::assertSame(14, GetMissileRange());
    }

    public function testMissileRangeIsZeroWithoutImpulseDrive(): void
    {
        $GLOBALS['resource'] = [117 => 'impulse_motor_tech'];
        $GLOBALS['user'] = ['impulse_motor_tech' => 0];

        self::assertSame(0, GetMissileRange());
    }
}
