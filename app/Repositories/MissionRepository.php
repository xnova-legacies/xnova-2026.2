<?php

namespace App\Repositories;

use App\Core\Flags;

final class MissionRepository extends BaseRepository
{
    public function findFleetsToProcess(array $position): array
    {
        $now = time();

        // Un vol supprimé logiquement n'est plus traité : sans ce filtre, sa
        // mission partirait quand même (arrivée, pillage, messages).
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE (
            (fleet_start_galaxy = ? AND fleet_start_system = ? AND fleet_start_planet = ? AND fleet_start_type = ?)
            OR (fleet_end_galaxy = ? AND fleet_end_system = ? AND fleet_end_planet = ? AND fleet_end_type = ?)
            ) AND (fleet_start_time < ? OR fleet_end_time < ?)
            AND (`flags` & ?) = 0",
            array(
                $position['galaxy'],
                $position['system'],
                $position['planet'],
                $position['planet_type'],
                $position['galaxy'],
                $position['system'],
                $position['planet'],
                $position['planet_type'],
                $now,
                $now,
                (string) Flags::DELETED,
            ),
            'fleets'
        );
    }

    /**
     * Suppression d'un vol demandée par le panneau : la ligne reste en base.
     *
     * Réservé au panneau — le moteur, lui, consomme un vol avec `deleteFleet()`
     * (la mission est terminée, la ligne n'a plus de raison d'être).
     */
    public function markDeleted(int $fleetId): void
    {
        $this->setFlag($fleetId, true);
    }

    /** Rétablit un vol supprimé logiquement (le panneau annule son geste). */
    public function restore(int $fleetId): void
    {
        $this->setFlag($fleetId, false);
    }

    private function setFlag(int $fleetId, bool $on): void
    {
        $expression = $on ? '`flags` = `flags` | ?' : '`flags` = `flags` & ~?';

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . $expression . ' WHERE fleet_id = ?',
            array((string) Flags::DELETED, $fleetId),
            'fleets'
        );
    }

    public function deleteFleet(int $fleetId): void
    {
        $this->preparedExecute(
            "DELETE FROM {{table}} WHERE fleet_id = ?",
            array($fleetId),
            'fleets'
        );
    }

    /**
     * Les flottes en vol d'une attaque groupée (même `fleet_group`).
     *
     * @return list<array<string, mixed>>
     */
    public function findGroupFleets(int $groupId): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE fleet_group = ? AND fleet_mess = '0' AND (`flags` & ?) = 0 ORDER BY fleet_id",
            array($groupId, (string) Flags::DELETED),
            'fleets'
        );
    }

    /** Le groupe d'attaque groupée n'a plus de flotte en vol : on le supprime. */
    public function deleteGroup(int $groupId): void
    {
        $this->preparedExecute(
            "DELETE FROM {{table}} WHERE id = ?",
            array($groupId),
            'aks'
        );
    }

    public function markFleetReturning(int $fleetId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_mess = '1' WHERE fleet_id = ?",
            array($fleetId),
            'fleets'
        );
    }

    /**
     * Marque une flotte de garde (mission 5) en stationnement : arrivée annoncée.
     *
     * Le handler repasse sur la flotte à chaque affichage de page ; c'est cette
     * marque (`fleet_mess = 2`) qui évite de renvoyer la notification d'arrivée
     * en boucle et qui permet d'attendre la fin du maintien pour rentrer.
     */
    public function markFleetOnStation(int $fleetId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_mess = 2 WHERE fleet_id = ? LIMIT 1",
            array($fleetId),
            'fleets'
        );
    }

    /**
     * Marque l'arrivée d'une flotte en orbite (mission 10).
     *
     * Une orbite n'a pas d'heure de fin : `fleet_end_stay` sert de marqueur
     * (-1 = en orbite, 0 = pas encore arrivée). `fleet_mess` reste à 0 pour que
     * le bouton « Retour » de la page des flottes reste disponible.
     */
    public function markFleetInOrbit(int $fleetId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_end_stay = -1 WHERE fleet_id = ? LIMIT 1",
            array($fleetId),
            'fleets'
        );
    }

    public function clearFleetCargo(int $fleetId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_resource_metal = '0', fleet_resource_crystal = '0', fleet_resource_deuterium = '0', fleet_mess = '1' WHERE fleet_id = ? LIMIT 1",
            array($fleetId),
            'fleets'
        );
    }

    /** Restauration des vaisseaux + ressources sur une planète (ex RestoreFleetToPlanet). */
    public function restoreFleet(array $fleetRow, bool $start = true): void
    {
        $resource = \App\Core\GameData::resource();
        $fleetRecord = explode(";", $fleetRow['fleet_array']);

        $sets = array();
        $params = array();
        foreach ($fleetRecord as $group) {
            if ($group != '') {
                $class = explode(",", $group);
                $field = $resource[$class[0]];
                $sets[] = "`" . $this->escape($field) . "` = `" . $this->escape($field) . "` + ?";
                $params[] = (int) $class[1];
            }
        }

        $sets[] = "`metal` = `metal` + ?";
        $params[] = $fleetRow['fleet_resource_metal'];
        $sets[] = "`crystal` = `crystal` + ?";
        $params[] = $fleetRow['fleet_resource_crystal'];
        $sets[] = "`deuterium` = `deuterium` + ?";
        $params[] = $fleetRow['fleet_resource_deuterium'];

        if ($start) {
            $where = "galaxy = ? AND `system` = ? AND planet = ? AND planet_type = ?";
            $params[] = $fleetRow['fleet_start_galaxy'];
            $params[] = $fleetRow['fleet_start_system'];
            $params[] = $fleetRow['fleet_start_planet'];
            $params[] = $fleetRow['fleet_start_type'];
        } else {
            $where = "galaxy = ? AND `system` = ? AND planet = ? AND planet_type = ?";
            $params[] = $fleetRow['fleet_end_galaxy'];
            $params[] = $fleetRow['fleet_end_system'];
            $params[] = $fleetRow['fleet_end_planet'];
            $params[] = $fleetRow['fleet_end_type'];
        }

        $this->preparedExecute(
            "UPDATE {{table}} SET " . implode(', ', $sets) . " WHERE " . $where . " LIMIT 1",
            $params,
            'planets'
        );
    }

    /** Déchargement des seules ressources (ex StoreGoodsToPlanet). */
    public function storeGoods(array $fleetRow, bool $start = false): void
    {
        if ($start) {
            $where = array($fleetRow['fleet_start_galaxy'], $fleetRow['fleet_start_system'], $fleetRow['fleet_start_planet'], $fleetRow['fleet_start_type']);
        } else {
            $where = array($fleetRow['fleet_end_galaxy'], $fleetRow['fleet_end_system'], $fleetRow['fleet_end_planet'], $fleetRow['fleet_end_type']);
        }

        $this->preparedExecute(
            "UPDATE {{table}} SET metal = metal + ?, crystal = crystal + ?, deuterium = deuterium + ?
             WHERE galaxy = ? AND `system` = ? AND planet = ? AND planet_type = ? LIMIT 1",
            array(
                $fleetRow['fleet_resource_metal'],
                $fleetRow['fleet_resource_crystal'],
                $fleetRow['fleet_resource_deuterium'],
                $where[0],
                $where[1],
                $where[2],
                $where[3],
            ),
            'planets'
        );
    }

    public function countPlayerPlanets(int $ownerId): int
    {
        $row = $this->preparedFetchOne(
            "SELECT COUNT(*) AS n FROM {{table}} WHERE id_owner = ? AND planet_type = '1' AND (`flags` & ?) = 0",
            array($ownerId, (string) Flags::DELETED),
            'planets'
        );

        return (int) ($row['n'] ?? 0);
    }

    public function countGalaxyPlace(int $galaxy, int $system, int $planet): int
    {
        // Une position est **libre** quand plus aucune planète n'y est annoncée :
        // une colonie abandonnée détache son lien, la ligne `galaxy` peut rester
        // (elle porte le champ de débris).
        $row = $this->preparedFetchOne(
            "SELECT COUNT(*) AS n FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ? AND id_planet <> 0",
            array($galaxy, $system, $planet),
            'galaxy'
        );

        return (int) ($row['n'] ?? 0);
    }

    public function removeColonizerFromFleet(array $fleetRow): void
    {
        $currentFleet = explode(";", $fleetRow['fleet_array']);
        $newFleet = "";
        foreach ($currentFleet as $group) {
            if ($group != '') {
                $class = explode(",", $group);
                if ($class[0] == 208) {
                    if ($class[1] > 1) {
                        $newFleet .= $class[0] . "," . ($class[1] - 1) . ";";
                    }
                } else {
                    if ($class[1] <> 0) {
                        $newFleet .= $class[0] . "," . $class[1] . ";";
                    }
                }
            }
        }

        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_array = ?, fleet_amount = fleet_amount - 1, fleet_mess = '1' WHERE fleet_id = ?",
            array($newFleet, $fleetRow['fleet_id']),
            'fleets'
        );
    }

    public function findGalaxyRow(int $galaxy, int $system, int $planet): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ? LIMIT 1",
            array($galaxy, $system, $planet),
            'galaxy'
        );
    }

    public function decrementGalaxyDebris(int $galaxy, int $system, int $planet, $metal, $crystal, $deuterium = 0): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET metal = metal - ?, crystal = crystal - ?, deuterium = deuterium - ?
             WHERE galaxy = ? AND `system` = ? AND planet = ? LIMIT 1",
            array($metal, $crystal, $deuterium, $galaxy, $system, $planet),
            'galaxy'
        );
    }

    /**
     * Ajoute des débris à la ligne `galaxy` d'une planète (outil d'espionnage).
     *
     * Le champ de débris porte une colonne par ressource écritE (métal, cristal, et
     * celles qu'un module ajoute) : ce dépôt écrit les trois montants.
     */
    public function incrementGalaxyDebris(int $planetId, $metal, $crystal, $deuterium = 0): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET metal = metal + ?, crystal = crystal + ?, deuterium = deuterium + ?
             WHERE id_planet = ? LIMIT 1",
            array($metal, $crystal, $deuterium, $planetId),
            'galaxy'
        );
    }

    public function incrementExplorerMessages(int $ownerId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET menu_exploit = menu_exploit + 1 WHERE id = ?",
            array($ownerId),
            'users'
        );
    }

    public function updateFleetCargo(int $fleetId, $metal, $crystal, $deuterium): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_resource_metal = ?, fleet_resource_crystal = ?, fleet_resource_deuterium = ? WHERE fleet_id = ?",
            array($metal, $crystal, $deuterium, $fleetId),
            'fleets'
        );
    }

    public function updateFleetArray(int $fleetId, string $fleetArray): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_array = ?, fleet_mess = '1' WHERE fleet_id = ?",
            array($fleetArray, $fleetId),
            'fleets'
        );
    }

    /**
     * Réécrit le contenu d'une flotte sans toucher à son état de vol.
     *
     * `updateFleetArray()` marque la flotte comme rentrante (`fleet_mess = 1`) :
     * c'est la règle du retour, pas celle d'un retrait de vaisseaux depuis le
     * panneau. Ici, la flotte garde sa mission et sa trajectoire.
     */
    public function setFleetUnits(int $fleetId, string $fleetArray, int $amount): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_array = ?, fleet_amount = ? WHERE fleet_id = ?",
            array($fleetArray, $amount, $fleetId),
            'fleets'
        );
    }

    public function addFleetCargo(int $fleetId, $metal, $crystal, $deuterium): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_resource_metal = fleet_resource_metal + ?, fleet_resource_crystal = fleet_resource_crystal + ?, fleet_resource_deuterium = fleet_resource_deuterium + ?, fleet_mess = '1' WHERE fleet_id = ?",
            array($metal, $crystal, $deuterium, $fleetId),
            'fleets'
        );
    }
}
