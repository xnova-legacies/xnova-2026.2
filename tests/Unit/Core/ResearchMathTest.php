<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ResearchMath;
use PHPUnit\Framework\TestCase;

/**
 * Facteur d'accélération des recherches (accélérateur de particules).
 */
final class ResearchMathTest extends TestCase
{
    public function testWithoutAcceleratorTheDurationIsUntouched(): void
    {
        self::assertSame(1.0, ResearchMath::accelerationFactor(0));
    }

    public function testEachLevelHalvesTheResearchDuration(): void
    {
        self::assertSame(0.5, ResearchMath::accelerationFactor(1));
        self::assertSame(0.25, ResearchMath::accelerationFactor(2));
        self::assertSame(0.125, ResearchMath::accelerationFactor(3));
        self::assertSame(0.0625, ResearchMath::accelerationFactor(4));
    }
}
