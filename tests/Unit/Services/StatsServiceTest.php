<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\StatsService;
use PHPUnit\Framework\TestCase;

/**
 * Les formules de points sont pures : le recalcul lui-même écrit en base et n'est
 * pas testé unitairement (voir `db/stats.php`).
 */
final class StatsServiceTest extends TestCase
{
    private const PRICE = array('metal' => 100, 'crystal' => 50, 'deuterium' => 10, 'factor' => 2);

    /** Un bâtiment de niveau 5 compte les niveaux 1 à 4 (règle historique). */
    public function testLevelPointsCountOnlyBuiltLevels(): void
    {
        // 160 × 2^1 + 160 × 2^2 + 160 × 2^3 + 160 × 2^4 = 320 + 640 + 1280 + 2560
        self::assertSame(4800.0, StatsService::levelPoints(self::PRICE, 5));
    }

    public function testLevelPointsOfANewElementAreZero(): void
    {
        self::assertSame(0.0, StatsService::levelPoints(self::PRICE, 0));
        self::assertSame(0.0, StatsService::levelPoints(self::PRICE, 1));
    }

    public function testUnitPointsMultiplyTheUnitPrice(): void
    {
        self::assertSame(1600.0, StatsService::unitPoints(self::PRICE, 10));
        self::assertSame(0.0, StatsService::unitPoints(self::PRICE, 0));
        self::assertSame(0.0, StatsService::unitPoints(self::PRICE, -5));
    }

    public function testLevelPointsWithoutFactorAddUpThePrice(): void
    {
        $price = array('metal' => 10, 'crystal' => 0, 'deuterium' => 0, 'factor' => 1);

        self::assertSame(20.0, StatsService::levelPoints($price, 3));
    }
}
