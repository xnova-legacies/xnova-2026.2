<?php

namespace App\Repositories;

use App\Core\Flags;
use App\Database\Connection;
use App\Entities\Fleet;

final class FleetRepository extends BaseRepository
{
    /** Colonnes de tri de la liste des vols (liste blanche). */
    public const SORTS = array(
        'id' => 'f.fleet_id',
        'mission' => 'f.fleet_mission',
        'owner' => 'o.username',
        'start' => 'f.fleet_start_time',
        'end' => 'f.fleet_end_time',
        'stay' => 'f.fleet_end_stay',
    );

    /**
     * Toutes les flottes en vol de l'univers, avec les noms de départ et
     * d'arrivée (page « Flottes en vol » du panneau d'administration).
     *
     * La page historique bâtissait chaque ligne avec la mécanique d'événements de
     * la vue générale (une requête de nom par extrémité, popups). Ici, deux
     * jointures suffisent.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllInFlight(
        string $sort = 'end',
        string $order = 'asc',
        int $limit = 0,
        int $offset = 0
    ): array {
        $column = self::SORTS[$sort] ?? self::SORTS['end'];
        $direction = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        // La requête joint `users` et `planets`, qui portent elles aussi un `flags`.
        [$filter, $params] = self::stateFilter(false, 'f.');
        $sql = 'SELECT f.*, o.username AS owner_name,'
            . ' start.name AS start_name, target.name AS target_name'
            . ' FROM {{table}} AS f'
            . ' LEFT JOIN ' . Connection::table('users') . ' AS o ON o.id = f.fleet_owner'
            . ' LEFT JOIN ' . Connection::table('planets') . ' AS start'
            . '   ON start.galaxy = f.fleet_start_galaxy AND start.system = f.fleet_start_system'
            . '  AND start.planet = f.fleet_start_planet AND start.planet_type = f.fleet_start_type'
            . ' LEFT JOIN ' . Connection::table('planets') . ' AS target'
            . '   ON target.galaxy = f.fleet_end_galaxy AND target.system = f.fleet_end_system'
            . '  AND target.planet = f.fleet_end_planet AND target.planet_type = f.fleet_end_type'
            . ' WHERE 1 = 1' . $filter
            // L'identifiant départage deux échéances égales : sans lui, une ligne
            // pourrait apparaître deux fois d'une page à l'autre.
            . ' ORDER BY ' . $column . ' ' . $direction . ', f.fleet_id ' . $direction;

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) max(0, $offset);
        }

        return $this->preparedFetchAll($sql, $params, 'fleets');
    }

    /** Nombre de flottes en vol (total de la page « Flottes en vol »). */
    public function countInFlight(): int
    {
        [$filter, $params] = self::stateFilter(false);
        $row = $this->preparedFetchOne('SELECT COUNT(*) AS total FROM {{table}} WHERE 1 = 1' . $filter, $params, 'fleets');

        return (int) ($row['total'] ?? 0);
    }

    /** Vols d'un joueur, existants par défaut. */
    public function findByOwner(int $ownerId, ?bool $deleted = false): array
    {
        [$filter, $params] = self::stateFilter($deleted);

        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE fleet_owner = ?" . $filter,
            array_merge(array($ownerId), $params),
            'fleets'
        );
    }

    /** Vols d'un joueur supprimés logiquement (le panneau peut les rétablir). */
    public function findDeletedByOwner(int $ownerId): array
    {
        return $this->findByOwner($ownerId, true);
    }

    public function findByTargetOwner(int $ownerId): array
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE fleet_target_owner = ?" . $filter,
            array_merge(array($ownerId), $params),
            'fleets'
        );
    }

    public function findByOwnerRaw(int $ownerId): array
    {
        return $this->findByOwner($ownerId);
    }

    /**
     * Un vol par son identifiant, **supprimé compris**.
     *
     * La recherche par identifiant reste volontairement sans filtre : c'est elle
     * qui permet au panneau de rétablir un vol supprimé.
     */
    public function findById(int $fleetId): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE fleet_id = ?",
            array($fleetId),
            'fleets'
        );
    }

    public function findListByFleetId(int $fleetId): array|false
    {
        return $this->findById($fleetId);
    }

    public function updateReturn(int $fleetId, int $returnFlyingTime, int $userId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET
            fleet_start_time = ?,
            fleet_end_stay = 0,
            fleet_end_time = ?,
            fleet_target_owner = ?,
            fleet_mess = 1
            WHERE fleet_id = ?",
            array(time() - 1, $returnFlyingTime + 1, $userId, $fleetId),
            'fleets'
        );
    }

    public function countByOwner(int $ownerId): array|false
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchOne(
            "SELECT COUNT(fleet_id) AS Number FROM {{table}} WHERE fleet_owner = ?" . $filter,
            array_merge(array($ownerId), $params),
            'fleets'
        );
    }

    public function insertFleet(Fleet $fleet): int
    {
        return $this->preparedInsertId(
            "INSERT INTO {{table}}
            (fleet_owner, fleet_mission, fleet_amount, fleet_array,
             fleet_start_time, fleet_start_galaxy, fleet_start_system, fleet_start_planet, fleet_start_type,
             fleet_end_time, fleet_end_stay, fleet_end_galaxy, fleet_end_system, fleet_end_planet, fleet_end_type,
             fleet_resource_metal, fleet_resource_crystal, fleet_resource_deuterium,
             fleet_target_owner, start_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array(
                $fleet->ownerId(),
                $fleet->mission(),
                $fleet->amount(),
                $fleet->raw('fleet_array'),
                $fleet->raw('fleet_start_time'),
                $fleet->raw('fleet_start_galaxy'),
                $fleet->raw('fleet_start_system'),
                $fleet->raw('fleet_start_planet'),
                $fleet->raw('fleet_start_type'),
                $fleet->raw('fleet_end_time'),
                $fleet->raw('fleet_end_stay'),
                $fleet->raw('fleet_end_galaxy'),
                $fleet->raw('fleet_end_system'),
                $fleet->raw('fleet_end_planet'),
                $fleet->raw('fleet_end_type'),
                $fleet->raw('fleet_resource_metal'),
                $fleet->raw('fleet_resource_crystal'),
                $fleet->raw('fleet_resource_deuterium'),
                // Passer par l'accesseur garantit le type entier de la colonne,
                // y compris pour les lignes construites par le code legacy.
                $fleet->targetOwner(),
                $fleet->raw('start_time'),
            ),
            'fleets'
        );
    }

    public function insert(array $fleet): void
    {
        $this->insertFleet(Fleet::fromRow($fleet));
    }

    /**
     * Retire des vaisseaux à une planète et écrit, dans la **même** requête, les colonnes
     * qui suivent (ressources, deutérium, type de planète).
     *
     * L'ancienne signature recevait un fragment SQL (`$subQuery`) que le contrôleur
     * construisait à partir du formulaire d'envoi : la quantité de vaisseaux y entrait
     * concaténée. Les colonnes passent maintenant par la forme vérifiée
     * (`BaseRepository::isColumnName()`) et les valeurs par des paramètres liés.
     *
     * @param array<string, int|float|string> $ships   colonne (nom de vaisseau) => quantité retirée
     * @param array<string, int|float|string> $columns colonne => nouvelle valeur (ressources,
     *                                                 deutérium, type de planète)
     */
    public function updateShips(int $planetId, array $ships, array $columns = array()): void
    {
        $sets = array();
        $params = array();

        foreach ($ships as $column => $amount) {
            if (!is_string($column) || !self::isColumnName($column)) {
                continue;
            }

            $sets[] = '`' . $column . '` = `' . $column . '` - ?';
            $params[] = (string) (int) $amount;
        }

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
     * Vols qui partent d'une position ou qui la visent (scan de la phalange).
     *
     * Le contrôleur construisait cette requête à la main, avec les coordonnées de l'URL
     * concaténées : elles passent ici en paramètres liés.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Efface tous les vols d'un compte (nettoyage anti-triche).
     *
     * Le moteur, lui, consomme un vol (`MissionRepository::deleteFleet()`) et le panneau le
     * **marque** (`markDeleted()`) : cette suppression physique n'existe que pour
     * `DeleteSelectedUser()`.
     */
    public function purgeByOwner(int $ownerId): void
    {
        $this->purgeRows('fleets', 'fleet_owner', $ownerId);
    }

    /**
     * Les flottes **en stationnement** sur une planète : la défense groupée d'une attaque.
     *
     * Mission 5 (stationnement) dont l'arrivée est déjà annoncée (`fleet_mess = 2`) — c'est
     * cette marque qui distingue une flotte posée d'une flotte en vol, et sans elle le
     * handler renverrait la notification d'arrivée à chaque affichage de page. Une flotte
     * supprimée logiquement ne défend plus (le drapeau est un paramètre lié).
     *
     * @return list<array<string, mixed>>
     */
    public function findGuardsAtPosition(int $galaxy, int $system, int $planet, int $type): array
    {
        return $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE fleet_end_galaxy = ? AND fleet_end_system = ?'
                . ' AND fleet_end_planet = ? AND fleet_end_type = ? AND fleet_mission = 5'
                . ' AND fleet_mess = 2 AND (`flags` & ?) = 0',
            array($galaxy, $system, $planet, $type, \App\Core\Flags::DELETED),
            'fleets'
        );
    }

    /**
     * Les vols d'une position ne visent plus une **lune** : leur type de planète passe à `$type`.
     *
     * C'est la suite d'une destruction lunaire (mission 9) : les vols qui partent de l'endroit ou
     * qui l'ont visé se rabattent sur la planète qui occupait la position. Les quatre noms de
     * colonnes sont choisis dans une **liste fermée** selon la branche (`start`, `end`) : aucun
     * nom de colonne ne vient d'un appelant, et la position part liée.
     */
    public function setWorldTypeAtPosition(int $galaxy, int $system, int $planet, int $type, string $branch): void
    {
        $branches = array(
            'start' => array('type' => 'fleet_start_type', 'galaxy' => 'fleet_start_galaxy',
                'system' => 'fleet_start_system', 'planet' => 'fleet_start_planet'),
            'end' => array('type' => 'fleet_end_type', 'galaxy' => 'fleet_end_galaxy',
                'system' => 'fleet_end_system', 'planet' => 'fleet_end_planet'),
        );

        if (!isset($branches[$branch])) {
            return;
        }

        $columns = $branches[$branch];

        $this->preparedExecute(
            'UPDATE {{table}} SET `' . $columns['type'] . '` = ?'
                . ' WHERE `' . $columns['galaxy'] . '` = ? AND `' . $columns['system'] . '` = ?'
                . ' AND `' . $columns['planet'] . '` = ?',
            array($type, $galaxy, $system, $planet),
            'fleets'
        );
    }

    public function findAtPosition(int $galaxy, int $system, int $planet, int $type): array
    {
        return $this->preparedFetchAll(
            'SELECT * FROM {{table}}'
            . ' WHERE (fleet_start_galaxy = ? AND fleet_start_system = ?'
            . ' AND fleet_start_planet = ? AND fleet_start_type = ?)'
            . ' OR (fleet_end_galaxy = ? AND fleet_end_system = ?'
            . ' AND fleet_end_planet = ? AND fleet_end_type = ?)'
            . ' ORDER BY `fleet_start_time`',
            array($galaxy, $system, $planet, $type, $galaxy, $system, $planet, $type),
            'fleets'
        );
    }

    /**
     * Un vol part de cette position, ou la vise-t-il ? (abandon d'une colonie)
     *
     * Reprise **exacte** de la condition du legacy (`CheckFleets`) : le type n'est exigé que
     * pour une **lune**, et `fleet_mess <> 1` ne porte que sur la branche d'arrivée — un `OR`
     * ayant la précédence la plus faible, MySQL lit « départ, ou bien arrivée d'un vol qui
     * n'est pas rentrant ». Ne pas « corriger » la parenthèse : elle décide quels vols
     * bloquent l'abandon d'une planète.
     */
    public function hasFleetAtPosition(int $galaxy, int $system, int $planet, int $type): bool
    {
        $moon = $type === 3;

        $sql = 'SELECT `fleet_id` FROM {{table}} WHERE ('
            . '(fleet_start_galaxy = ? AND fleet_start_system = ? AND fleet_start_planet = ?'
            . ($moon ? ' AND fleet_start_type = ?' : '')
            . ') OR (fleet_end_galaxy = ? AND fleet_end_system = ? AND fleet_end_planet = ?'
            . ($moon ? ' AND fleet_end_type = ?' : '')
            . ' AND fleet_mess <> 1) LIMIT 1';

        $params = array($galaxy, $system, $planet);

        if ($moon) {
            $params[] = $type;
        }

        $params[] = $galaxy;
        $params[] = $system;
        $params[] = $planet;

        if ($moon) {
            $params[] = $type;
        }

        return $this->preparedFetchOne($sql, $params, 'fleets') !== false;
    }

    public function updateFleetGroup(int $fleetId, int $aksId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_group = ? WHERE fleet_id = ?",
            array($aksId, $fleetId),
            'fleets'
        );
    }

    public function countByOwnerWithQuantity(int $ownerId): array|false
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchOne(
            "SELECT COUNT(fleet_owner) AS fleet_count FROM {{table}} WHERE fleet_owner = ?" . $filter,
            array_merge(array($ownerId), $params),
            'fleets'
        );
    }

    public function findByOwnerMissionCount(int $ownerId, int $mission): array|false
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchOne(
            "SELECT COUNT(fleet_owner) AS expedi FROM {{table}} WHERE fleet_owner = ? AND fleet_mission = ?" . $filter,
            array_merge(array($ownerId, $mission), $params),
            'fleets'
        );
    }

    /**
     * Flottes d'une mission qui visent une position, hors trajets de retour
     * (sert à n'autoriser qu'une seule flotte en orbite par planète).
     */
    public function countByMissionAt(int $ownerId, int $mission, int $galaxy, int $system, int $planet, int $type): int
    {
        [$filter, $params] = self::stateFilter(false);
        $row = $this->preparedFetchOne(
            "SELECT COUNT(*) AS total FROM {{table}}"
            . " WHERE fleet_owner = ? AND fleet_mission = ? AND fleet_mess <> 1"
            . " AND fleet_end_galaxy = ? AND fleet_end_system = ? AND fleet_end_planet = ? AND fleet_end_type = ?"
            . $filter,
            array_merge(array($ownerId, $mission, $galaxy, $system, $planet, $type), $params),
            'fleets'
        );

        return (int) ($row['total'] ?? 0);
    }

    public function countFleetId(int $fleetId): int
    {
        return $this->preparedFetchOne(
            "SELECT COUNT(*) AS n FROM {{table}} WHERE fleet_id = ?",
            array($fleetId),
            'fleets'
        )['n'] ?? 0;
    }

    /** Verrou d'écriture sur la table planets (ex LOCK TABLE du floten3). */
    public function lockPlanets(): void
    {
        $this->query("LOCK TABLE {{table}} WRITE", 'planets');
    }

    /** Déverrouillage (ex UNLOCK TABLES). */
    public function unlockTables(): void
    {
        $this->query("UNLOCK TABLES", '');
    }

    /**
     * Enregistre un tir de missiles (mission 11) : une salve = une ligne `fleets`.
     *
     * `fleet_array` porte le missile lui-même (`503,n;`), comme un vol porte ses
     * vaisseaux : le bandeau, le panneau et le moteur lisent donc la salve sans
     * code dédié.
     *
     * @param array{owner: int, from: array<int, int>, to: array<int, int>, targetOwner: int, missiles: int, departure: int, impact: int, primary: int} $strike
     */
    public function insertMissileStrike(array $strike): void
    {
        $this->preparedExecute(
            'INSERT INTO {{table}} SET'
            . ' fleet_owner = ?, fleet_mission = 11, fleet_amount = ?, fleet_array = ?,'
            . ' fleet_start_time = ?, fleet_start_galaxy = ?, fleet_start_system = ?, fleet_start_planet = ?, fleet_start_type = 1,'
            . ' fleet_end_time = ?, fleet_end_stay = 0, fleet_end_galaxy = ?, fleet_end_system = ?, fleet_end_planet = ?, fleet_end_type = ?,'
            . ' fleet_taget_owner = 0, fleet_resource_metal = 0, fleet_resource_crystal = 0, fleet_resource_deuterium = 0,'
            . ' fleet_target_owner = ?, fleet_group = 0, fleet_mess = 0, start_time = ?, flags = ?, fleet_primary = ?',
            array(
                $strike['owner'],
                $strike['missiles'],
                '503,' . $strike['missiles'] . ';',
                $strike['departure'],
                $strike['from'][0],
                $strike['from'][1],
                $strike['from'][2],
                $strike['impact'],
                $strike['to'][0],
                $strike['to'][1],
                $strike['to'][2],
                $strike['targetType'],
                $strike['targetOwner'],
                $strike['departure'],
                (string) Flags::DEFAULT,
                $strike['primary'],
            ),
            'fleets'
        );
    }

    /** Salves de missiles en route vers une position (aide au déplacement du panneau). */
    public function findMissileStrikesToPosition(int $galaxy, int $system, int $planet): array
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchAll(
            'SELECT * FROM {{table}}'
            . ' WHERE fleet_mission = 11 AND fleet_end_galaxy = ? AND fleet_end_system = ? AND fleet_end_planet = ?'
            . $filter,
            array_merge(array($galaxy, $system, $planet), $params),
            'fleets'
        );
    }

    /**
     * Flottes qui visent une position (leur arrivée est prévue là).
     *
     * Aucun filtre sur le type : la planète et sa lune partagent les mêmes
     * coordonnées et partent ensemble.
     */
    public function findTargetingPosition(int $galaxy, int $system, int $planet): array
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchAll(
            "SELECT * FROM {{table}}"
            . " WHERE fleet_end_galaxy = ? AND fleet_end_system = ? AND fleet_end_planet = ?"
            . $filter,
            array_merge(array($galaxy, $system, $planet), $params),
            'fleets'
        );
    }

    /**
     * Fait suivre une position à toutes les flottes en vol qui la référencent :
     * celles qui en partent comme celles qui la visent. Sans cela, un vol
     * rentrerait — ou arriverait — à une adresse vide.
     */
    public function movePositionReferences(int $galaxy, int $system, int $planet, int $newGalaxy, int $newSystem, int $newPlanet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_start_galaxy = ?, fleet_start_system = ?, fleet_start_planet = ?"
            . " WHERE fleet_start_galaxy = ? AND fleet_start_system = ? AND fleet_start_planet = ?",
            array($newGalaxy, $newSystem, $newPlanet, $galaxy, $system, $planet),
            'fleets'
        );

        $this->preparedExecute(
            "UPDATE {{table}} SET fleet_end_galaxy = ?, fleet_end_system = ?, fleet_end_planet = ?"
            . " WHERE fleet_end_galaxy = ? AND fleet_end_system = ? AND fleet_end_planet = ?",
            array($newGalaxy, $newSystem, $newPlanet, $galaxy, $system, $planet),
            'fleets'
        );
    }
}
