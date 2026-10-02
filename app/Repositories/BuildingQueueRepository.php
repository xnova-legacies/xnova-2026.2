<?php

namespace App\Repositories;

final class BuildingQueueRepository extends BaseRepository
{
    /**
     * Écrit la production calculée et l'état du chantier du hangar.
     *
     * Seuls les **noms de colonnes** sont composés ici : toutes les valeurs
     * partent en paramètres. Le nom de la colonne d'une unité en cours de
     * construction vient de `$builded`, donc de la table `GameData::resource()`
     * (la liste des unités du jeu), jamais de la requête.
     */
    public function savePlanetProduction(array $planet, array $builded): void
    {
        $resource = \App\Core\GameData::resource();

        $sets = array();
        $params = array();

        $columns = array(
            'metal', 'crystal', 'deuterium', 'last_update', 'b_hangar_id',
            'metal_perhour', 'crystal_perhour', 'deuterium_perhour',
            'energy_used', 'energy_max',
        );

        foreach ($columns as $column) {
            $sets[] = '`' . $column . '` = ?';
            $params[] = $planet[$column];
        }

        // Capacités de stockage : copie persistée de ProductionService::storageCapacities()
        // (le rendu legacy et la table des planètes les lisent telles quelles).
        foreach (array('metal_max', 'crystal_max', 'deuterium_max') as $cap) {
            if (isset($planet[$cap])) {
                $sets[] = '`' . $cap . '` = ?';
                $params[] = $planet[$cap];
            }
        }

        if ($builded != '') {
            $unique = \App\Core\GameData::uniqueUnits();

            foreach ($builded as $element => $count) {
                if ($element === '') {
                    continue;
                }

                // Les boucliers sont des colonnes enum('0','1') : un seul exemplaire,
                // sinon l'enregistrement de la production est refusé par MySQL.
                $value = in_array((int) $element, $unique, true)
                    ? min(1, (int) $planet[$resource[$element]])
                    : $planet[$resource[$element]];

                $sets[] = '`' . $resource[$element] . '` = ?';
                $params[] = $value;
            }
        }

        $sets[] = '`b_hangar` = ?';
        $params[] = $planet['b_hangar'];
        $params[] = $planet['id'];

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . implode(', ', $sets) . ' WHERE `id` = ?',
            $params,
            'planets'
        );
    }

    /** Enregistrement b_building/b_building_id (ex BuildingSavePlanetRecord). */
    public function saveBuildingQueueState(array $planet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET b_building = ?, b_building_id = ? WHERE id = ?",
            array($planet['b_building'], $planet['b_building_id'], $planet['id']),
            'planets'
        );
    }

    /**
     * Relit la file de construction telle qu'elle est en base.
     *
     * Une action du joueur part d'une copie de la planète chargée en début de
     * requête : sans cette relecture, une écriture concurrente (chantier terminé
     * par la synchronisation temps réel) était écrasée par l'ancienne valeur.
     *
     * @return array{b_building: mixed, b_building_id: mixed}|null
     */
    public function findQueueState(int $planetId): ?array
    {
        $row = $this->preparedFetchOne(
            "SELECT b_building, b_building_id FROM {{table}} WHERE id = ?",
            array($planetId),
            'planets'
        );

        return is_array($row) ? $row : null;
    }

    public function saveBuildingLevel(array $planet, string $elementField): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `" . $this->escape($elementField) . "` = ?, b_building = ?, b_building_id = ?, field_current = ?, field_max = ? WHERE id = ?",
            array($planet[$elementField], $planet['b_building'], $planet['b_building_id'], $planet['field_current'], $planet['field_max'], $planet['id']),
            'planets'
        );
    }

    /**
     * Écrit les colonnes du compte demandées par l'appelant (fin d'une construction).
     *
     * Les **noms** viennent de l'appelant — `BuildingService::constructionRewards()`, surchargé
     * par un module — donc le Coeur de l'application n'en nomme aucun : c'est le module des
     * officiers qui désigne la sienne, et ce point de passage l'écrit (le test de consolidation
     * refuse la moindre mention, commentaire compris). La forme d'un nom est vérifiée
     * (`isColumnName()`, même garde que `incrementField()`), les valeurs partent liées.
     *
     * @param array<string, int|float> $values colonne => nouvelle valeur
     */
    public function saveUserFields(int $userId, array $values): void
    {
        $assignments = array();
        $params = array();

        foreach ($values as $column => $value) {
            if (!self::isColumnName((string) $column)) {
                continue;
            }

            $assignments[] = '`' . (string) $column . '` = ?';
            $params[] = $value;
        }

        if ($assignments === array()) {
            return;
        }

        $params[] = $userId;

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . implode(', ', $assignments) . ' WHERE `id` = ?',
            $params,
            'users'
        );
    }

    public function saveHangarShips(array $planet): void
    {
        $this->query("LOCK TABLE {{table}} WRITE", 'planets');
        $this->preparedExecute(
            "UPDATE {{table}} SET b_hangar = ?, b_hangar_id = ?, metal = ?, crystal = ?, deuterium = ?, last_update = ? WHERE id = ?",
            array($planet['b_hangar'], $planet['b_hangar_id'], $planet['metal'], $planet['crystal'], $planet['deuterium'], $planet['last_update'], $planet['id']),
            'planets'
        );
        $this->query("UNLOCK TABLES", '');
    }

    /** Mise à jour de field_current si incohérent (ex CheckPlanetUsedFields). */
    public function syncFieldCurrent(array &$planet, int $cfc): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET field_current = ? WHERE id = ?",
            array($cfc, $planet['id']),
            'planets'
        );
    }
}
