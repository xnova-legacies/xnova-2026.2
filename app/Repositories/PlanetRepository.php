<?php

namespace App\Repositories;

use App\Core\Flags;
use App\Core\Phalanx;

final class PlanetRepository extends BaseRepository
{
    /**
     * Condition des planètes **existants** : une lune détruite ou une planète
     * abandonnée garde sa ligne (drapeau `DELETED`, migration 014) et le jeu ne
     * doit plus la voir.
     *
     * Le drapeau est une **constante du code** (`Flags::DELETED`), jamais une valeur venue
     * d'un appelant : elle entre en clair dans la condition, qui n'a donc pas de `?`.
     */
    private static function liveFilter(): string
    {
        return ' AND (`flags` & ' . Flags::DELETED . ') = 0';
    }

    public function findAllByOwner(int $ownerId, int $sort = 0, string $order = 'ASC'): array
    {
        // Les colonies abandonnées gardent leur ligne (et leur propriétaire) sans
        // figurer dans les listes du joueur.
        //
        // Le tri est une **liste blanche** (la colonne vient du numéro, jamais de l'URL) et la
        // direction une valeur fermée : ni l'une ni l'autre ne peut être un paramètre lié.
        $sorts = array(
            0 => '`id`',
            1 => '`galaxy`, `system`, `planet`, `planet_type`',
            2 => '`name`',
        );
        $column = $sorts[$sort] ?? $sorts[0];
        $direction = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $sql = 'SELECT * FROM {{table}} WHERE `id_owner` = ?' . self::liveFilter()
            . ' ORDER BY ' . $column . ' ' . $direction;

        return $this->preparedFetchAll($sql, array($ownerId), 'planets');
    }

    /**
     * Ajoute — ou retire — des ressources à une planète (panneau d'administration).
     *
     * Les colonnes de ressources ne sont pas signées : c'est la requête qui fait le
     * plancher (`GREATEST(0, …)`). Un montant négatif retire donc bien, sans jamais
     * échouer sur une valeur négative ; l'appelant borne le retrait à ce que la
     * planète possède (`PlayerAdminService::bounded()`).
     */
    public function addResources(int $planetId, float $metal, float $crystal, float $deuterium): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `metal` = GREATEST(0, `metal` + ?),'
            . ' `crystal` = GREATEST(0, `crystal` + ?),'
            . ' `deuterium` = GREATEST(0, `deuterium` + ?)'
            . ' WHERE `id` = ?',
            array(
                (string) $metal,
                (string) $crystal,
                (string) $deuterium,
                (string) $planetId,
            ),
            'planets'
        );
    }

    /** Écrit les trois ressources **telles quelles** (l'appelant a déjà fait son calcul). */
    public function updateResources(int $planetId, $metal, $crystal, $deuterium): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `metal` = ?, `crystal` = ?, `deuterium` = ? WHERE `id` = ?',
            array((string) $metal, (string) $crystal, (string) $deuterium, $planetId),
            'planets'
        );
    }

    /**
     * Écrit des pourcentages de production : l'appelant donne les **colonnes** et leurs
     * valeurs, la méthode compose le `SET` (forme du nom vérifiée, valeurs liées).
    /**
     * Écrit une ou plusieurs colonnes d'une planète, à partir d'un tableau
     * (colonne => nouvelle valeur).
     *
     * La forme de chaque nom est vérifiée (`isColumnName`) — un nom de colonne ne peut pas
     * être un paramètre lié — et chaque valeur part en paramètre lié. C'est la recette que les
     * pages legacy bâtissaient à la main (`SetNextQueueElementOnTop`, `IsVacationMode`) et que
     * `updatePorcents()` portait pour les seuls pourcentages : elle délègue maintenant ici.
     *
     * @param array<string, int|float|string> $columns colonne => nouvelle valeur
     */
    public function updateColumns(int $planetId, array $columns): void
    {
        $sets = array();
        $params = array();

        foreach ($columns as $column => $value) {
            if (!is_string($column) || !self::isColumnName($column)) {
                continue;
            }

            $sets[] = '`' . $column . '` = ?';
            $params[] = is_scalar($value) ? (string) $value : '';
        }

        if ($sets === array()) {
            return;
        }

        $params[] = $planetId;

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . implode(', ', $sets) . ' WHERE `id` = ?',
            $params,
            'planets'
        );
    }

    /**
     * Enregistre les pourcentages de production d'une planète.
     *
     * L'ancienne signature recevait un fragment SQL tout fait (`$subQuery`), donc les valeurs
     * et les noms de colonnes étaient concaténés chez l'appelant ; le `SET \`id\` = \`id\``
     * historique ne servait qu'à éviter un `SET` vide, on sort maintenant sans écrire.
     *
     * @param array<string, int|float|string> $columns colonne => valeur
     */
    public function updatePorcents(int $planetId, array $columns): void
    {
        $this->updateColumns($planetId, $columns);
    }

    public function setOwnerLevel(int $ownerId, int $level): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `id_level` = ? WHERE `id_owner` = ?',
            array($level, $ownerId),
            'planets'
        );
    }

    public function findProtectionLevel(int $ownerId): array|false
    {
        return $this->preparedFetchOne(
            'SELECT `id_level` FROM {{table}} WHERE `id_owner` = ?' . self::liveFilter() . ' LIMIT 1',
            array($ownerId),
            'planets'
        );
    }

    public function findMoonAt(array $planetRow): array|false
    {
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `id_owner` = ? AND `galaxy` = ? AND `system` = ? AND `lunapos` = ?',
            array(
                (int) ($planetRow['id_owner'] ?? 0),
                (int) ($planetRow['galaxy'] ?? 0),
                (int) ($planetRow['system'] ?? 0),
                (int) ($planetRow['planet'] ?? 0),
            ),
            'lunas'
        );
    }

    public function findMoonPlanet(array $planetRow): array|false
    {
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `galaxy` = ? AND `system` = ? AND `planet` = ?'
                . ' AND `planet_type` = 3' . self::liveFilter(),
            array(
                (int) ($planetRow['galaxy'] ?? 0),
                (int) ($planetRow['system'] ?? 0),
                (int) ($planetRow['planet'] ?? 0),
            ),
            'planets'
        );
    }

    /**
     * Marque une planète **abandonnée** : sa ligne reste en base, avec tout ce
     * qu'elle porte, et le jeu ne la voit plus.
     *
     * `destruyed` garde la date de l'abandon (0 = jamais abandonnée) : c'est un
     * repère, plus une échéance de purge. `id_owner` est **conservé** : sans lui,
     * l'ancien propriétaire serait perdu et le rétablissement ne pourrait plus
     * rendre la colonie à son compte.
     */
    /**
     * Efface tous les planètes d'un compte, et leurs traces (nettoyage anti-triche).
     *
     * Les entrées du registre des lunes partent avec les lunes ; les positions à nettoyer
     * dans la table `galaxy` sont **rendues** à l'appelant, parce que c'est
     * `GalaxyRepository::purgePositions()` qui possède cette table. La lecture est
     * volontairement large (une colonie abandonnée garde sa ligne : un compte effacé n'en
     * laisse aucune derrière lui).
     *
     * @return list<array{galaxy: int, system: int, planet: int}>
     */
    public function purgeWorldsOfOwner(int $ownerId): array
    {
        $rows = $this->preparedFetchAll(
            'SELECT `id`, `galaxy`, `system`, `planet`, `planet_type` FROM {{table}} WHERE `id_owner` = ?',
            array($ownerId),
            'planets'
        );

        $positions = array();

        foreach ($rows as $row) {
            if ((int) $row['planet_type'] === 3) {
                $this->preparedExecute(
                    'DELETE FROM {{table}} WHERE `galaxy` = ? AND `system` = ? AND `lunapos` = ?',
                    array((int) $row['galaxy'], (int) $row['system'], (int) $row['planet']),
                    'lunas'
                );
            } else {
                // La position d'une **planète** est celle que la table `galaxy` annonce.
                $positions[] = array(
                    'galaxy' => (int) $row['galaxy'],
                    'system' => (int) $row['system'],
                    'planet' => (int) $row['planet'],
                );
            }

            $this->purgeRows('planets', 'id', (int) $row['id']);
        }

        return $positions;
    }

    public function markPlanetAbandoned(int $planetId): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `flags` = `flags` | ?, `destruyed` = UNIX_TIMESTAMP() WHERE id = ?',
            array((string) Flags::DELETED, $planetId),
            'planets'
        );
    }

    /**
     * Rétablit une planète abandonnée (l'administrateur annule le geste).
     *
     * `last_update` repart de maintenant : la production est calculée par écart de
     * date à chaque affichage, et une planète rendu des semaines plus tard encaisserait
     * sinon tout le temps de l'abandon.
     */
    public function restorePlanetAt(int $galaxy, int $system, int $planet, int $ownerId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `flags` = `flags` & ~?, `destruyed` = 0, `last_update` = UNIX_TIMESTAMP()"
            . ' WHERE galaxy = ? AND `system` = ? AND planet = ? AND planet_type = 1 AND id_owner = ?',
            array((string) Flags::DELETED, $galaxy, $system, $planet, $ownerId),
            'planets'
        );
    }

    /** Colonies abandonnées d'un compte (le panneau peut les rétablir). */
    public function findAbandonedPlanetsByOwner(int $ownerId): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE planet_type = 1 AND (`flags` & ?) = ? AND id_owner = ?",
            array((string) Flags::DELETED, (string) Flags::DELETED, $ownerId),
            'planets'
        );
    }

    /** Combien de planètes **existants** à cette position ? (garde du rétablissement) */
    public function countLivePlanetsAt(int $galaxy, int $system, int $planet): int
    {
        $row = $this->preparedFetchOne(
            "SELECT COUNT(*) AS total FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ?"
            . ' AND (`flags` & ?) = 0',
            array($galaxy, $system, $planet, (string) Flags::DELETED),
            'planets'
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Les coordonnées d'une colonie abandonnée restent **réservées** un moment :
     * la position redevient colonisable une fois le délai écoulé (`delay` en
     * secondes, 0 = tout de suite). Le repère est `destruyed`, la date de l'abandon.
     */
    public function isPositionReserved(int $galaxy, int $system, int $planet, int $delay): bool
    {
        return isset($this->reservedPositions($galaxy, $system, $delay)[$planet]);
    }

    /**
     * Colonies abandonnées dont les coordonnées sont encore réservées, indexées par
     * position : la vue de la galaxie les annonce « Planète détruite » le temps du
     * délai, alors même que le lien de la galaxie est déjà détaché.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reservedPositions(int $galaxy, int $system, int $delay): array
    {
        if ($delay <= 0) {
            return array();
        }

        $rows = $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet_type = 1'
            . ' AND (`flags` & ?) = ? AND `destruyed` > UNIX_TIMESTAMP() - ?',
            array($galaxy, $system, (string) Flags::DELETED, (string) Flags::DELETED, $delay),
            'planets'
        );

        $positions = array();

        foreach ($rows as $row) {
            $positions[(int) $row['planet']] = $row;
        }

        return $positions;
    }

    public function findByCoords(int $galaxy, int $system, int $planet, int $type): array|false
    {
        // Une planète détruit (lune) ou abandonné (planète) ne se trouve plus : c'est
        // lui qu'on vise quand une flotte ou la phalange cherche à ces coordonnées.
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ? AND planet_type = ?'
                . self::liveFilter(),
            array($galaxy, $system, $planet, $type),
            'planets'
        );
    }

    public function resetProductionPercent(int $planetId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET energy_used = '10', energy_max = '10',"
            . " metal_mine_porcent = '10', crystal_mine_porcent = '10',"
            . " deuterium_sintetizer_porcent = '10', solar_plant_porcent = '10',"
            . " fusion_plant_porcent = '10', solar_satelit_porcent = '10'"
            . ' WHERE id = ? AND `planet_type` = 1',
            array($planetId),
            'planets'
        );
    }

    /**
     * Remet la production **de base** : les trois revenus prennent la valeur du réglage du jeu
     * (`metal_basic_income`, le même pour les trois — c'est ainsi que le legacy le faisait).
     */
    public function zeroProductionPercent(int $planetId, array $gameConfig): void
    {
        $income = (string) ($gameConfig['metal_basic_income'] ?? 0);

        $this->preparedExecute(
            'UPDATE {{table}} SET metal_perhour = ?, crystal_perhour = ?, deuterium_perhour = ?,'
            . " energy_used = '0', energy_max = '0', metal_mine_porcent = '0',"
            . " crystal_mine_porcent = '0', deuterium_sintetizer_porcent = '0',"
            . " solar_plant_porcent = '0', fusion_plant_porcent = '0', solar_satelit_porcent = '0'"
            . ' WHERE id = ? AND `planet_type` = 1',
            array($income, $income, $income, $planetId),
            'planets'
        );
    }

    public function renamePlanet(int $planetId, string $name): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `name` = ? WHERE `id` = ? LIMIT 1',
            array($name, $planetId),
            'planets'
        );
    }

    public function renameMoonByPosition(array $planetRow, string $name): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `name` = ? WHERE `galaxy` = ? AND `system` = ? AND `lunapos` = ? LIMIT 1',
            array($name, (int) ($planetRow['galaxy'] ?? 0), (int) ($planetRow['system'] ?? 0), (int) ($planetRow['planet'] ?? 0)),
            'lunas'
        );
    }

    /**
     * Une planète — ou une lune, c'est la même table — par son identifiant.
     *
     * Les variantes `findCurrentByIdSemicolon()` et `findCurrentByIdWord()` du legacy ne
     * différaient que par leur ponctuation (`;`, espaces) : elles écrivaient **la même**
     * requête, elles ont rejoint celle-ci (une règle, une implémentation).
     */
    public function findCurrentById(int $id): array|false
    {
        return $this->preparedFetchOne('SELECT * FROM {{table}} WHERE `id` = ?', array($id), 'planets');
    }

    public function fetchOneLuna(int $id): array|false
    {
        return $this->preparedFetchOne('SELECT * FROM {{table}} WHERE `id` = ?', array($id), 'lunas');
    }

    /**
     * Toutes les planètes d'un type donné (1 = planète, 3 = lune), pour l'admin.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllByType(int $type): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE planet_type = ?" . self::liveFilter() . ' ORDER BY galaxy, `system`, planet',
            array($type),
            'planets'
        );
    }


    public function findListByCoordsType(int $galaxy, int $system, int $planet, int $type): array
    {
        return $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ? AND planet_type = ?' . self::liveFilter(),
            array($galaxy, $system, $planet, $type),
            'planets'
        );
    }

    public function findListByCoordsNoType(int $galaxy, int $system, int $planet): array
    {
        return $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ?' . self::liveFilter(),
            array($galaxy, $system, $planet),
            'planets'
        );
    }

    public function consumePhalanxDeuterium(int $planetId): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `deuterium` = `deuterium` - ? WHERE `id` = ?',
            array((string) Phalanx::SCAN_COST, $planetId),
            'planets'
        );
    }

    public function findJumpGate(int $planetId): array|false
    {
        return $this->preparedFetchOne(
            'SELECT `id`, `jump_gate`, `last_jump_time` FROM {{table}} WHERE `id` = ?',
            array($planetId),
            'planets'
        );
    }

    /**
     * Déplace des vaisseaux par la porte de saut : une quantité **signée** par colonne
     * (négatif au départ, positif à l'arrivée) et l'heure du saut.
     *
     * L'ancienne signature recevait un fragment SQL (`$subQuery`) que le contrôleur construisait
     * à partir de `$_POST` ; les colonnes passent maintenant par la forme vérifiée et les
     * quantités par des paramètres liés, dans **un seul** `UPDATE`.
     *
     * @param array<string, int> $ships colonne (nom de vaisseau) => quantité signée
     */
    public function updateJumpShips(int $planetId, array $ships, int $jumpTime): void
    {
        $sets = array();
        $params = array();

        foreach ($ships as $column => $amount) {
            if (!is_string($column) || !self::isColumnName($column)) {
                continue;
            }

            $sets[] = '`' . $column . '` = `' . $column . '` + ?';
            $params[] = (string) (int) $amount;
        }

        if ($sets === array()) {
            return;
        }

        $params[] = $jumpTime;
        $params[] = $planetId;

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . implode(', ', $sets) . ', `last_jump_time` = ? WHERE `id` = ?',
            $params,
            'planets'
        );
    }

    /**
     * Rend à une planète les vaisseaux et les ressources d'un vol (arrivée ou retour).
     *
     * La planète est désigné par sa **position** : le moteur de mission ne connaît que les
     * coordonnées du vol, et le legacy faisait déjà cette requête unique (`LIMIT 1` compris).
     * Les colonnes de vaisseaux viennent du tableau des ressources du jeu, jamais d'une
     * saisie ; toutes les quantités partent en paramètres liés.
     *
     * @param array<string, int>        $ships     colonne de vaisseau => quantité ajoutée
     * @param array<string, int|string> $resources colonne de ressource => quantité ajoutée
     */
    public function addFleetCargoAtPosition(int $galaxy, int $system, int $planet, int $type, array $ships, array $resources): void
    {
        $sets   = array();
        $params = array();

        foreach ($ships as $column => $amount) {
            if (!is_string($column) || !self::isColumnName($column)) {
                continue;
            }

            $sets[]   = '`' . $column . '` = `' . $column . '` + ?';
            $params[] = (string) (int) $amount;
        }

        foreach ($resources as $column => $amount) {
            if (!is_string($column) || !self::isColumnName($column)) {
                continue;
            }

            $sets[]   = '`' . $column . '` = `' . $column . '` + ?';
            $params[] = is_scalar($amount) ? (string) $amount : '0';
        }

        if ($sets === array()) {
            return;
        }

        $params[] = (string) $galaxy;
        $params[] = (string) $system;
        $params[] = (string) $planet;
        $params[] = (string) $type;

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . implode(', ', $sets)
                . ' WHERE `galaxy` = ? AND `system` = ? AND `planet` = ? AND `planet_type` = ? LIMIT 1',
            $params,
            'planets'
        );
    }

    public function updateMisilCount(int $planetId, int $count): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET interplanetary_misil = ? WHERE id = ?',
            array($count, $planetId),
            'planets'
        );
    }

    public function updateInterceptorCount(int $ownerId, int $count): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `interceptor_misil` = ? WHERE `id` = ?',
            array($count, $ownerId),
            'planets'
        );
    }

    public function resetMisils(int $planetId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `interplanetary_misil` = '0' WHERE `id` = ?",
            array($planetId),
            'planets'
        );
    }

    public function findAll(): array
    {
        return $this->fetchAll("SELECT * FROM {{table}}", 'planets');
    }

    /**
     * Décrément d'une colonne numérique (vaisseaux comme défenses).
     *
     * Le nom de colonne ne peut pas être un paramètre lié : sa **forme** est vérifiée
     * (`BaseRepository::isColumnName()`) — l'échappement d'identifiant ne protégeait rien.
     */
    public function decrementField(int $planetId, string $field, int $amount): void
    {
        if (!self::isColumnName($field)) {
            return;
        }

        $this->preparedExecute(
            'UPDATE {{table}} SET `' . $field . '` = `' . $field . '` - ? WHERE id = ?',
            array($amount, $planetId),
            'planets'
        );
    }

    /** Remise à zéro d'une colonne numérique (ex missiles interceptés). */
    public function setField(int $planetId, string $field, int $value): void
    {
        if (!self::isColumnName($field)) {
            return;
        }

        $this->preparedExecute(
            'UPDATE {{table}} SET `' . $field . '` = ? WHERE id = ?',
            array($value, $planetId),
            'planets'
        );
    }

    public function deductResources(int $planetId, float $metal, float $crystal, float $deuterium): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET metal = metal - ?, crystal = crystal - ?, deuterium = deuterium - ? WHERE id = ?",
            array($metal, $crystal, $deuterium, $planetId),
            'planets'
        );
    }

    public function findMoonsByOwner(int $ownerId): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE planet_type = '3' AND (`flags` & ?) = 0 AND id_owner = ?",
            array((string) Flags::DELETED, $ownerId),
            'planets'
        );
    }

    /** Lunes détruites logiquement d'un compte (le panneau peut les rétablir). */
    public function findDestroyedMoonsByOwner(int $ownerId): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE planet_type = '3' AND (`flags` & ?) = ? AND id_owner = ?",
            array((string) Flags::DELETED, (string) Flags::DELETED, $ownerId),
            'planets'
        );
    }

    /** Déplace une position (les colonnes de coordonnées d'une planète ou d'une lune). */
    public function movePosition(int $planetId, int $galaxy, int $system, int $planet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET galaxy = ?, `system` = ?, planet = ? WHERE id = ?",
            array($galaxy, $system, $planet, $planetId),
            'planets'
        );
    }

    /**
     * Déplace la lune posée à une position : c'est une ligne `planets` de type 3,
     * à côté de sa planète, et elle doit suivre.
     */
    public function moveMoonAt(int $galaxy, int $system, int $planet, int $newGalaxy, int $newSystem, int $newPlanet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET galaxy = ?, `system` = ?, planet = ?"
            . " WHERE galaxy = ? AND `system` = ? AND planet = ? AND planet_type = 3",
            array($newGalaxy, $newSystem, $newPlanet, $galaxy, $system, $planet),
            'planets'
        );
    }

    /**
     * Déplace l'entrée du registre `lunas` (la lune telle qu'elle figure dans la
     * liste du joueur : coordonnées et position, `lunapos`).
     */
    public function moveMoonRegistry(int $galaxy, int $system, int $planet, int $newGalaxy, int $newSystem, int $newPlanet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET galaxy = ?, `system` = ?, lunapos = ?"
            . " WHERE galaxy = ? AND `system` = ? AND lunapos = ?",
            array($newGalaxy, $newSystem, $newPlanet, $galaxy, $system, $planet),
            'lunas'
        );
    }

    /**
     * Crée une ligne `planets` (une colonie ou une lune) et rend son identifiant.
     *
     * Le tableau porte les colonnes **définitives** de la planète : la forme de chaque nom est
     * vérifiée (`isColumnName`) et chaque valeur part en paramètre lié. C'est la recette des
     * deux créations du legacy (`CreateOnePlanetRecord`, `CreateOneMoonRecord`), qui
     * bâtissaient leurs vingt et une colonnes morceau par morceau. `preparedInsertId()` rend
     * l'identifiant de la planète : inutile de le relire ensuite.
     *
     * @param array<string, int|string> $planet colonne => valeur
     */
    public function insertPlanetRecord(array $planet): int
    {
        $columns = array();
        $marks   = array();
        $params  = array();

        foreach ($planet as $column => $value) {
            if (!is_string($column) || !self::isColumnName($column)) {
                continue;
            }

            $columns[] = '`' . $column . '`';
            $marks[]   = '?';
            $params[]  = is_scalar($value) ? (string) $value : '';
        }

        if ($columns === array()) {
            return 0;
        }

        return $this->preparedInsertId(
            'INSERT INTO {{table}} (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $marks) . ')',
            $params,
            'planets'
        );
    }

    /**
     * L'entrée du registre `lunas` d'une position (pour retrouver son identifiant :
     * c'est lui que la galaxie annonce dans `id_luna`).
     */
    public function findMoonRegistryAt(int $galaxy, int $system, int $planet): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ? AND lunapos = ?",
            array($galaxy, $system, $planet),
            'lunas'
        );
    }

    /**
     * Marque l'entrée du registre `lunas` d'une position comme détruite.
     *
     * Le registre se lit **par position** (`renameMoonByPosition` fait de même) :
     * le `lunas.id_luna` historique ne permet pas de retrouver la ligne, et une
     * entrée oubliée suivrait ensuite une planète déplacée.
     *
     * La ligne n'est **pas** supprimée : `destruyed` est la marque du registre, la
     * vérité de l'existence d'une lune restant la ligne `planets` de type 3 (dont
     * le drapeau `DELETED` est posé dans le même geste).
     */
    public function markMoonRegistryDestroyed(int $galaxy, int $system, int $planet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET destruyed = '1' WHERE galaxy = ? AND `system` = ? AND lunapos = ?",
            array($galaxy, $system, $planet),
            'lunas'
        );
    }

    /**
     * Recrée l'entrée du registre `lunas` d'une lune.
     *
     * Le registre est purgé quelque temps après une destruction
     * (`CheckAbandonMoonState`) : sans cette recréation, une lune détruite
     * logiquement ne pourrait plus être rétablie une fois son entrée partie.
     *
     * Le nom est borné à la largeur de la colonne (`varchar(11)`), sinon MySQL
     * strict refuse l'écriture.
     *
     * @param array<string, mixed> $moon ligne `planets` de type 3
     */
    public function insertMoonRegistry(array $moon): int
    {
        return $this->preparedInsertId(
            'INSERT INTO {{table}} (id_luna, name, image, destruyed, id_owner, galaxy, `system`, lunapos, temp_min, temp_max, diameter)'
            . ' VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)',
            array(
                (int) $moon['id'],
                mb_substr((string) ($moon['name'] ?? ''), 0, 11),
                'moon',
                (int) $moon['id_owner'],
                (int) $moon['galaxy'],
                (int) $moon['system'],
                (int) $moon['planet'],
                (int) ($moon['temp_min'] ?? 0),
                (int) ($moon['temp_max'] ?? 0),
                (int) ($moon['diameter'] ?? 0),
            ),
            'lunas'
        );
    }

    /**
     * Retire la marque « détruite » du registre (rétablissement d'une lune).
     */
    public function restoreMoonRegistry(int $galaxy, int $system, int $planet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET destruyed = '0' WHERE galaxy = ? AND `system` = ? AND lunapos = ?",
            array($galaxy, $system, $planet),
            'lunas'
        );
    }

    /**
     * Détruit logiquement une lune : sa ligne `planets` de type 3 reste en base
     * (drapeau `DELETED`), et ne se trouve plus par les lectures de lunes.
     *
     * Reprend la règle du jeu (destruction d'une lune) : le propriétaire est dans
     * la condition, une lune étrangère ne peut donc pas partir avec la planète.
     */
    public function markMoonDeletedAt(int $galaxy, int $system, int $planet, int $ownerId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `flags` = `flags` | ? WHERE galaxy = ? AND `system` = ? AND planet = ?"
            . " AND planet_type = 3 AND id_owner = ?",
            array((string) Flags::DELETED, $galaxy, $system, $planet, $ownerId),
            'planets'
        );
    }

    /** Même geste, par identifiant (la destruction par attaque connaît la lune). */
    public function markMoonDeletedById(int $moonId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `flags` = `flags` | ? WHERE id = ? AND planet_type = 3",
            array((string) Flags::DELETED, $moonId),
            'planets'
        );
    }

    /**
     * Rétablit une lune détruite logiquement : sa ligne n'a jamais bougé.
     *
     * `last_update` repart de maintenant, comme `restorePlanetAt()` : la production
     * se calcule par écart de date depuis `last_update`.
     */
    public function restoreMoonAt(int $galaxy, int $system, int $planet, int $ownerId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `flags` = `flags` & ~?, `last_update` = UNIX_TIMESTAMP()"
            . " WHERE galaxy = ? AND `system` = ? AND planet = ? AND planet_type = 3 AND id_owner = ?",
            array((string) Flags::DELETED, $galaxy, $system, $planet, $ownerId),
            'planets'
        );
    }

    /**
     * Combien de lunes **existantes** à cette position ?
     *
     * Une lune détruite ne libère la position que tant qu'aucune n'a été reposée :
     * rétablir l'ancienne alors qu'une nouvelle occupe les lieux créerait deux
     * lunes au même endroit.
     */
    public function countMoonsAt(int $galaxy, int $system, int $planet): int
    {
        $row = $this->preparedFetchOne(
            "SELECT COUNT(*) AS total FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ?"
            . " AND planet_type = 3 AND (`flags` & ?) = 0",
            array($galaxy, $system, $planet, (string) Flags::DELETED),
            'planets'
        );

        return (int) ($row['total'] ?? 0);
    }
}
