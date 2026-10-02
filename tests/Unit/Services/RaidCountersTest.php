<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FleetMissionService;
use PHPUnit\Framework\TestCase;

/**
 * Compteurs de raids de la Vue générale : trois colonnes, dont « Raids Perdus »
 * qui restait à zéro (le raid perdu écrivait dans la colonne des raids gagnés).
 */
final class RaidCountersTest extends TestCase
{
    public function testAWonRaidCountsAsAWin(): void
    {
        self::assertSame(
            array('raids' => 4, 'raidswin' => 3, 'raidsloose' => 1),
            FleetMissionService::raidCounters(array('raids' => 3, 'raidswin' => 2, 'raidsloose' => 1), true)
        );
    }

    public function testALostRaidCountsAsALoss(): void
    {
        self::assertSame(
            array('raids' => 4, 'raidswin' => 2, 'raidsloose' => 2),
            FleetMissionService::raidCounters(array('raids' => 3, 'raidswin' => 2, 'raidsloose' => 1), false)
        );
    }

    public function testAMissingColumnStartsAtZero(): void
    {
        self::assertSame(
            array('raids' => 1, 'raidswin' => 0, 'raidsloose' => 1),
            FleetMissionService::raidCounters(array(), false)
        );
    }
}
