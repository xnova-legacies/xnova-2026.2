<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BanService;
use PHPUnit\Framework\TestCase;

/**
 * Seule la conversion des quatre champs de durée est testable sans base : les
 * écritures (poser ou lever un bannissement) passent par les dépôts.
 */
final class BanServiceTest extends TestCase
{
    public function testDurationAddsEveryField(): void
    {
        self::assertSame(
            86400 + 2 * 3600 + 3 * 60 + 4,
            BanService::duration(1, 2, 3, 4)
        );
    }

    public function testDurationTreatsMissingFieldsAsZero(): void
    {
        self::assertSame(0, BanService::duration(0, 0, 0, 0));
        self::assertSame(2 * 86400, BanService::duration(2, 0, 0, 0));
    }

    public function testDurationIgnoresNegativeFields(): void
    {
        self::assertSame(BanService::DAY, BanService::duration(-5, 24, 0, 0));
        self::assertSame(0, BanService::duration(-5, -5, -5, -5));
    }

    /** Un zéro de trop ne doit pas transformer une semaine en bannissement à vie. */
    public function testDurationIsCappedAtTheMaximum(): void
    {
        $maximum = BanService::duration(3650, 0, 0, 0);

        self::assertSame($maximum, BanService::duration(999999, 0, 0, 0));
        self::assertSame(3650 * BanService::DAY, $maximum);
    }

    /** Un plafond plus serré est respecté. */
    public function testDurationHonoursACustomMaximum(): void
    {
        self::assertSame(10 * BanService::DAY, BanService::duration(999, 0, 0, 0, 10));
    }
}
