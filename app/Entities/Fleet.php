<?php

namespace App\Entities;

final class Fleet extends AbstractEntity
{
    /** Mission « mise en orbite » : la flotte reste sur place jusqu'à son rappel. */
    public const MISSION_ORBIT = 10;

    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public static function new(int $ownerId, int $mission, string $fleetArray, array $start, array $end): self
    {
        return new self(array(
            'fleet_owner' => $ownerId,
            'fleet_mission' => $mission,
            'fleet_amount' => count(explode(';', rtrim($fleetArray, ';'))),
            'fleet_array' => $fleetArray,
            'fleet_start_galaxy' => $start['galaxy'],
            'fleet_start_system' => $start['system'],
            'fleet_start_planet' => $start['planet'],
            'fleet_start_type' => $start['type'],
            'fleet_start_time' => $start['time'],
            'fleet_end_galaxy' => $end['galaxy'],
            'fleet_end_system' => $end['system'],
            'fleet_end_planet' => $end['planet'],
            'fleet_end_type' => $end['type'],
            'fleet_end_time' => $end['time'],
            'fleet_end_stay' => $end['stay'] ?? 0,
            'fleet_resource_metal' => $end['metal'] ?? 0,
            'fleet_resource_crystal' => $end['crystal'] ?? 0,
            'fleet_resource_deuterium' => $end['deuterium'] ?? 0,
            // Colonne entiere : une expedition vise une position vide (aucun
            // proprietaire), '' y etait refuse par MySQL en mode strict.
            'fleet_target_owner' => (int) ($end['owner'] ?? 0),
            'start_time' => time(),
        ));
    }

    public function id(): int
    {
        return (int) $this->raw('fleet_id');
    }

    /**
     * Destinataire du vol (0 = position vide : expedition, recyclage).
     * Toujours entier : c'est le type de la colonne `fleet_target_owner`.
     */
    public function targetOwner(): int
    {
        return (int) $this->raw('fleet_target_owner');
    }

    public function ownerId(): int
    {
        return (int) $this->raw('fleet_owner');
    }

    public function mission(): int
    {
        return (int) $this->raw('fleet_mission');
    }

    public function amount(): int
    {
        return (int) $this->raw('fleet_amount');
    }

    public function isReturning(): bool
    {
        return (int) $this->raw('fleet_mess') === 1;
    }

    /** Groupe d'attaque groupée de la flotte (0 = attaque seule). */
    public function groupId(): int
    {
        return (int) $this->raw('fleet_group');
    }

    public function isStayMission(): bool
    {
        return (int) $this->raw('fleet_end_stay') !== 0;
    }

    /**
     * Flotte de garde arrivée et en stationnement (mission 5).
     *
     * `fleet_mess = 2` : l'arrivée a déjà été annoncée et le maintien court
     * (voir MissionRepository::markFleetOnStation()).
     */
    public function isOnStation(): bool
    {
        return (int) $this->raw('fleet_mess') === 2;
    }

    public function startGalaxy(): int
    {
        return (int) $this->raw('fleet_start_galaxy');
    }

    public function startSystem(): int
    {
        return (int) $this->raw('fleet_start_system');
    }

    public function startPlanet(): int
    {
        return (int) $this->raw('fleet_start_planet');
    }

    public function startType(): int
    {
        return (int) $this->raw('fleet_start_type');
    }

    public function endGalaxy(): int
    {
        return (int) $this->raw('fleet_end_galaxy');
    }

    public function endSystem(): int
    {
        return (int) $this->raw('fleet_end_system');
    }

    public function endPlanet(): int
    {
        return (int) $this->raw('fleet_end_planet');
    }

    public function endType(): int
    {
        return (int) $this->raw('fleet_end_type');
    }

    /** Date de départ (colonne `fleet_start_time`). */
    public function departureTime(): int
    {
        return (int) $this->raw('fleet_start_time');
    }

    /** Date d'arrivée (colonne `fleet_end_time`). */
    public function arrivalTime(): int
    {
        return (int) $this->raw('fleet_end_time');
    }

    /** Échéance du stationnement (colonne `fleet_end_stay`). */
    public function stayTime(): int
    {
        return (int) $this->raw('fleet_end_stay');
    }

    /**
     * Flotte arrivée en orbite autour de sa planète de départ.
     *
     * Une orbite n'a pas d'heure de fin : son arrivée est marquée par
     * `fleet_end_stay = -1` (voir MissionRepository::markFleetInOrbit()).
     */
    public function isOrbiting(): bool
    {
        return $this->mission() === self::MISSION_ORBIT && $this->stayTime() < 0;
    }

    public function cargoMetal(): int
    {
        return (int) $this->raw('fleet_resource_metal');
    }

    public function cargoCrystal(): int
    {
        return (int) $this->raw('fleet_resource_crystal');
    }

    public function cargoDeuterium(): int
    {
        return (int) $this->raw('fleet_resource_deuterium');
    }

    public function startCoordinates(): string
    {
        return $this->startGalaxy() . ':' . $this->startSystem() . ':' . $this->startPlanet();
    }

    public function endCoordinates(): string
    {
        return $this->endGalaxy() . ':' . $this->endSystem() . ':' . $this->endPlanet();
    }

    public function array(): array
    {
        $ships = [];
        foreach (explode(';', $this->raw('fleet_array') ?? '') as $entry) {
            if ($entry === '') {
                continue;
            }
            list($shipId, $count) = explode(',', $entry);
            $ships[(int) $shipId] = (int) $count;
        }

        return $ships;
    }

    public function start(): array
    {
        return array(
            'galaxy' => (int) $this->raw('fleet_start_galaxy'),
            'system' => (int) $this->raw('fleet_start_system'),
            'planet' => (int) $this->raw('fleet_start_planet'),
            'type' => (int) $this->raw('fleet_start_type'),
        );
    }

    public function end(): array
    {
        return array(
            'galaxy' => (int) $this->raw('fleet_end_galaxy'),
            'system' => (int) $this->raw('fleet_end_system'),
            'planet' => (int) $this->raw('fleet_end_planet'),
            'type' => (int) $this->raw('fleet_end_type'),
        );
    }
}
