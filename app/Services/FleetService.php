<?php

namespace App\Services;

use App\Entities\Fleet;
use App\Repositories\FleetRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;

final class FleetService
{
    public function __construct(
        private readonly FleetRepository $fleets = new FleetRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function dispatch(
        int $ownerId,
        int $mission,
        array $shipCounts,
        array $origin,
        array $destination,
        int $startTime,
        int $endTime,
        int $stayTime = 0,
        array $resources = array(),
    ): Fleet {
        $fleetArray = '';
        $totalShips = 0;
        $resource = $GLOBALS['resource'] ?? array();

        foreach ($shipCounts as $shipId => $count) {
            if ($count <= 0) {
                continue;
            }
            $shipId = (int) $shipId;
            $count = (int) $count;
            $totalShips += $count;
            $fleetArray .= $shipId . ',' . $count . ';';
        }

        $fleet = Fleet::new(
            $ownerId,
            $mission,
            $fleetArray,
            array(
                'galaxy' => (int) $origin['galaxy'],
                'system' => (int) $origin['system'],
                'planet' => (int) $origin['planet'],
                'type' => (int) $origin['planet_type'],
                'time' => $startTime,
            ),
            array(
                'galaxy' => (int) $destination['galaxy'],
                'system' => (int) $destination['system'],
                'planet' => (int) $destination['planet'],
                'type' => (int) $destination['planet_type'],
                'time' => $endTime,
                'stay' => $stayTime,
                'metal' => $resources['metal'] ?? 0,
                'crystal' => $resources['crystal'] ?? 0,
                'deuterium' => $resources['deuterium'] ?? 0,
                'owner' => $destination['owner'] ?? '',
            )
        );

        $id = $this->fleets->insertFleet($fleet);

        // Les vaisseaux ne sont retires qu'apres l'enregistrement du vol : les
        // tables sont en MyISAM (aucune transaction pour rattraper un ordre
        // inverse), et un enregistrement refuse faisait disparaitre la flotte
        // sans qu'aucun vol n'existe (expedition vers une position vide).
        foreach ($shipCounts as $shipId => $count) {
            if ($count <= 0) {
                continue;
            }

            $field = (string) ($resource[(int) $shipId] ?? 'ship_' . (int) $shipId);
            $this->planets->decrementField((int) $origin['id'], $field, (int) $count);
        }

        return $fleet;
    }

    public function recall(int $fleetId, int $userId): bool
    {
        $row = $this->fleets->findById($fleetId);

        if ($row === false || (int) $row['fleet_owner'] !== $userId) {
            return false;
        }

        if ((int) $row['fleet_mess'] === 1) {
            return false;
        }

        // Une salve de missiles est un tir, pas un aller-retour : rien ne rentre.
        if ((int) $row['fleet_mission'] === FlyingFleetService::MISSION_MISSILE) {
            return false;
        }

        $this->fleets->updateReturn($fleetId, self::recallReturnTime($row, time()), $userId);

        return true;
    }

    /**
     * Heure d'arrivée du vol retour d'une flotte rappelée.
     *
     * Deux cas seulement : une flotte encore en vol rentre avec le temps déjà
     * parcouru (règle historique), une flotte déjà arrivée (en stationnement chez un allié
     * ou en orbite) rentre avec le temps de son aller — jamais avec le temps passé
     * sur place.
     *
     * @param array<string, mixed> $row
     */
    public static function recallReturnTime(array $row, int $now): int
    {
        $start = (int) ($row['start_time'] ?? 0);
        $arrival = (int) ($row['fleet_start_time'] ?? 0);
        $flightTime = max(0, $arrival - $start);

        if ((int) ($row['fleet_mess'] ?? 0) === 2 || $flightTime === 0 || $arrival <= $now) {
            return $now + $flightTime;
        }

        return $now + max(0, $now - $start);
    }

    public function countActive(int $ownerId): int
    {
        return (int) ($this->fleets->countByOwner($ownerId)['Number'] ?? 0);
    }

    /** Nombre de flottes en vol pour une mission donnée (ex. 15 = expédition). */
    public function countByMission(int $ownerId, int $mission): int
    {
        return (int) ($this->fleets->findByOwnerMissionCount($ownerId, $mission)['expedi'] ?? 0);
    }

    /**
     * Flottes d'une mission visant cette position (hors retours).
     *
     * @param array{galaxy?: int, system?: int, planet?: int, planet_type?: int} $position
     */
    public function countByMissionAt(int $ownerId, int $mission, array $position): int
    {
        return $this->fleets->countByMissionAt(
            $ownerId,
            $mission,
            (int) ($position['galaxy'] ?? 0),
            (int) ($position['system'] ?? 0),
            (int) ($position['planet'] ?? 0),
            (int) ($position['planet_type'] ?? 1)
        );
    }

    public function removeShipsAndResources(int $planetId, array $shipCounts, float $metal, float $crystal, float $deuterium): void
    {
        $resource = $GLOBALS['resource'] ?? array();

        foreach ($shipCounts as $shipId => $count) {
            $field = (string) ($resource[$shipId] ?? 'ship_' . $shipId);
            $this->planets->decrementField($planetId, $field, (int) $count);
        }

        $this->planets->deductResources($planetId, $metal, $crystal, $deuterium);
    }
}
