<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FlyingFleetService;
use PHPUnit\Framework\TestCase;

/**
 * Les vols en vol se lisent en base ; seules les fonctions pures (échéance
 * affichée et empreinte utilisée par le client) sont testées ici.
 */
final class FlyingFleetServiceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    /** @param array<string, mixed> $extra */
    private function fleet(array $extra = array()): array
    {
        return array_merge(array(
            'fleet_id' => 12,
            'fleet_mission' => 3,
            'fleet_mess' => 0,
            'fleet_start_time' => self::NOW + 600,
            'fleet_end_stay' => 0,
            'fleet_end_time' => self::NOW + 1200,
        ), $extra);
    }

    public function testOwnFleetShowsItsArrivalFirst(): void
    {
        $next = FlyingFleetService::nextEvent($this->fleet(), self::NOW, false);

        self::assertSame(FlyingFleetService::EVENT_ARRIVAL, $next['kind']);
        self::assertSame(self::NOW + 600, $next['time']);
    }

    public function testOwnFleetOnItsWayBackShowsTheReturn(): void
    {
        $next = FlyingFleetService::nextEvent(
            $this->fleet(array('fleet_mess' => 1, 'fleet_start_time' => self::NOW - 600)),
            self::NOW,
            false
        );

        self::assertSame(FlyingFleetService::EVENT_RETURN, $next['kind']);
        self::assertSame(self::NOW + 1200, $next['time']);
    }

    public function testOrbitFleetOnItsWayIsAnnouncedByItsArrival(): void
    {
        $next = FlyingFleetService::nextEvent(
            $this->fleet(array('fleet_mission' => FlyingFleetService::MISSION_ORBIT)),
            self::NOW,
            false
        );

        self::assertSame(FlyingFleetService::EVENT_ARRIVAL, $next['kind']);
        self::assertSame(self::NOW + 600, $next['time']);
    }

    public function testFleetInOrbitHasNoDeadline(): void
    {
        // Mise en orbite arrivée : rien à décompter avant le rappel, mais la ligne
        // reste affichée (contrairement à un transfert qui disparaît).
        $next = FlyingFleetService::nextEvent(
            $this->fleet(array(
                'fleet_mission' => FlyingFleetService::MISSION_ORBIT,
                'fleet_start_time' => self::NOW - 600,
                'fleet_end_time' => self::NOW - 600,
                'fleet_end_stay' => -1,
            )),
            self::NOW,
            false
        );

        self::assertSame(FlyingFleetService::EVENT_ORBIT, $next['kind']);
    }

    public function testOrbitFleetRecalledShowsItsReturn(): void
    {
        $next = FlyingFleetService::nextEvent(
            $this->fleet(array(
                'fleet_mission' => FlyingFleetService::MISSION_ORBIT,
                'fleet_mess' => 1,
                'fleet_start_time' => self::NOW - 600,
                'fleet_end_stay' => 0,
            )),
            self::NOW,
            false
        );

        self::assertSame(FlyingFleetService::EVENT_RETURN, $next['kind']);
        self::assertSame(self::NOW + 1200, $next['time']);
    }

    public function testOwnFleetInStayingShowsTheEndOfTheStay(): void
    {
        // Expédition : la flotte est arrivée, elle explore encore.
        $next = FlyingFleetService::nextEvent(
            $this->fleet(array(
                'fleet_mission' => 15,
                'fleet_start_time' => self::NOW - 600,
                'fleet_end_stay' => self::NOW + 300,
            )),
            self::NOW,
            false
        );

        self::assertSame(FlyingFleetService::EVENT_STAY, $next['kind']);
        self::assertSame(self::NOW + 300, $next['time']);
    }

    public function testOwnDeployHasNoReturnEvent(): void
    {
        // Transfert (mission 4) : la flotte est absorbée à l'arrivée.
        $next = FlyingFleetService::nextEvent(
            $this->fleet(array(
                'fleet_mission' => FlyingFleetService::MISSION_DEPLOY,
                'fleet_start_time' => self::NOW - 10,
            )),
            self::NOW,
            false
        );

        self::assertNull($next);
    }

    public function testReturnedFleetDisappearsFromTheList(): void
    {
        // Échéance dépassée : le prochain affichage de page la fera atterrir.
        self::assertNull(FlyingFleetService::nextEvent(
            $this->fleet(array('fleet_mess' => 1, 'fleet_end_time' => self::NOW - 1)),
            self::NOW,
            false
        ));
    }

    public function testIncomingFleetIsAnnouncedUntilItsArrival(): void
    {
        $next = FlyingFleetService::nextEvent($this->fleet(), self::NOW, true);

        self::assertSame(FlyingFleetService::EVENT_INCOMING, $next['kind']);
        self::assertSame(self::NOW + 600, $next['time']);

        // Arrivée passée : plus rien à annoncer (le joueur a reçu le rapport).
        self::assertNull(FlyingFleetService::nextEvent(
            $this->fleet(array('fleet_start_time' => self::NOW - 5, 'fleet_end_time' => self::NOW + 600)),
            self::NOW,
            true
        ));
    }

    public function testIncomingHoldMissionStillAnnouncesTheStay(): void
    {
        $next = FlyingFleetService::nextEvent(
            $this->fleet(array(
                'fleet_mission' => FlyingFleetService::MISSION_HOLD,
                'fleet_start_time' => self::NOW - 60,
                'fleet_end_stay' => self::NOW + 900,
            )),
            self::NOW,
            true
        );

        self::assertSame(FlyingFleetService::EVENT_STAY, $next['kind']);
    }

    public function testFingerprintIsStableAndReactsToComposition(): void
    {
        $entry = array(
            'kind' => FlyingFleetService::EVENT_ARRIVAL,
            'time' => self::NOW + 600,
            'fleet_id' => 12,
            'mission' => 3,
            'incoming' => false,
            'ships' => 5,
        );

        $reference = FlyingFleetService::fingerprint(array($entry));

        self::assertSame($reference, FlyingFleetService::fingerprint(array($entry)));
        self::assertSame(md5(''), FlyingFleetService::fingerprint(array()));
        self::assertNotSame($reference, FlyingFleetService::fingerprint(array($entry, $entry)));
        self::assertNotSame($reference, FlyingFleetService::fingerprint(array(array_merge($entry, array('time' => self::NOW + 900)))));
        self::assertNotSame($reference, FlyingFleetService::fingerprint(array(array_merge($entry, array('incoming' => true)))));
    }

    public function testParseUnitsReadsTheFleetArray(): void
    {
        // Champ du jeu : couples « id,nombre; », dans l'ordre de la vue générale.
        self::assertSame(
            array(202 => 7, 203 => 5, 210 => 1),
            FlyingFleetService::parseUnits('202,7;203,5;210,1;')
        );
    }

    public function testParseUnitsIgnoresEmptyAndBrokenParts(): void
    {
        self::assertSame(array(), FlyingFleetService::parseUnits(null));
        self::assertSame(array(), FlyingFleetService::parseUnits(''));
        self::assertSame(array(), FlyingFleetService::parseUnits(';;'));
        self::assertSame(array(), FlyingFleetService::parseUnits('202'));
        self::assertSame(array(202 => 3), FlyingFleetService::parseUnits('202,0;202,3;0,4;'));
    }

    public function testParseUnitsAddsUpDuplicatedTypes(): void
    {
        self::assertSame(array(202 => 5), FlyingFleetService::parseUnits('202,2;202,3;'));
    }

    public function testFingerprintReactsToTheShipBreakdown(): void
    {
        $entry = array(
            'kind' => FlyingFleetService::EVENT_ARRIVAL,
            'time' => self::NOW + 600,
            'fleet_id' => 12,
            'mission' => 3,
            'incoming' => false,
            'ships' => 5,
            'units' => array(202 => 5),
        );

        $reference = FlyingFleetService::fingerprint(array($entry));

        // Même total, composition différente : le détail affiché doit se rafraîchir.
        self::assertNotSame(
            $reference,
            FlyingFleetService::fingerprint(array(array_merge($entry, array('units' => array(202 => 3, 203 => 2)))))
        );
    }
}
