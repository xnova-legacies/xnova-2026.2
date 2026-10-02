<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FleetMissionService;
use PHPUnit\Framework\TestCase;

/**
 * La vie d'une flotte de garde (mission 5) est rythmée par `fleet_mess` :
 * le handler repasse sur la flotte à chaque affichage de page, donc sans marque
 * l'arrivée serait annoncée en boucle et la fin du maintien jamais atteinte.
 */
final class FleetHoldStepTest extends TestCase
{
    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function fleet(int $mess, int $start, int $stay, int $end, array $extra = array()): array
    {
        return array(
            'fleet_mess' => $mess,
            'fleet_start_time' => $start,
            'fleet_end_stay' => $stay,
            'fleet_end_time' => $end,
        ) + $extra;
    }

    public function testFleetStillFlyingDoesNothing(): void
    {
        $row = self::fleet(0, 1000, 2000, 2100);

        self::assertSame('waiting', FleetMissionService::holdStep($row, 999));
    }

    public function testArrivalIsAnnouncedOnceThenTheFleetIsOnStation(): void
    {
        $arrival = self::fleet(0, 1000, 2000, 2100);

        self::assertSame('notify', FleetMissionService::holdStep($arrival, 1000), 'À l\'arrivée, la notification part.');
        // Une fois la marque posée (`fleet_mess = 2`), plus aucune notification.
        $onStation = self::fleet(2, 1000, 2000, 2100);
        self::assertSame('on-station', FleetMissionService::holdStep($onStation, 1500));
        // La fin du maintien est atteinte : le tour suivant la fait rentrer.
        self::assertSame('on-station', FleetMissionService::holdStep($onStation, 1999));
    }

    public function testTheFleetGoesHomeWhenTheHoldEnds(): void
    {
        $onStation = self::fleet(2, 1000, 2000, 2100);

        self::assertSame('return-home', FleetMissionService::holdStep($onStation, 2000));
    }

    public function testTheReturnFlightEndsAtHome(): void
    {
        $returning = self::fleet(1, 1000, 2000, 2100);

        self::assertSame('returning', FleetMissionService::holdStep($returning, 2050));
        self::assertSame('arrived-home', FleetMissionService::holdStep($returning, 2100));
    }

    public function testAHoldWithoutStayComesBackImmediately(): void
    {
        // Maintien nul : la flotte annonce son arrivée, puis rentre au tour suivant.
        self::assertSame('notify', FleetMissionService::holdStep(self::fleet(0, 1000, 0, 1100), 1000));
        self::assertSame('return-home', FleetMissionService::holdStep(self::fleet(2, 1000, 0, 1100), 1001));
    }
}
