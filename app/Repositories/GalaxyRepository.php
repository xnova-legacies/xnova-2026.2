<?php

namespace App\Repositories;

final class GalaxyRepository extends BaseRepository
{
    /**
     * Préchargement de la vue galaxie.
     *
     * La vue interrogeait la base position par position : une requête pour la
     * ligne `galaxy`, une pour la planète, une pour son propriétaire, une pour sa
     * lune — soixante-dix requêtes pour un système. `prefetchSystem()` lit chaque
     * table **une seule fois** ; les lectures par identifiant servent ensuite le
     * préchargement au lieu de la base.
     */
    private static array $prefetch = array('primed' => false, 'planets' => array(), 'moons' => array(), 'users' => array(), 'allies' => array());

    /** Identifiants périmés en cours de rendu : relus en base au prochain appel. */
    private static array $stale = array();

    /**
     * Lit tout un système d'un coup et précharge ce qu'il désigne.
     *
     * @return array<int, array> lignes `galaxy` indexées par numéro de planète
     */
    public function prefetchSystem(int $galaxy, int $system): array
    {
        $positions = $this->findSystem($galaxy, $system);

        $planetIds = array();
        $moonIds = array();
        foreach ($positions as $position) {
            if ((int) $position['id_planet'] > 0) {
                $planetIds[] = (int) $position['id_planet'];
            }
            if ((int) $position['id_luna'] > 0) {
                $moonIds[] = (int) $position['id_luna'];
            }
        }

        $planets = $this->findPlanetsByIds($planetIds);

        $owners = array();
        $allies = array();
        foreach ($planets as $planet) {
            if ((int) $planet['id_owner'] > 0) {
                $owners[] = (int) $planet['id_owner'];
            }
        }

        $users = $this->findUsersByIds($owners);
        foreach ($users as $account) {
            if ((int) ($account['ally_id'] ?? 0) > 0) {
                $allies[] = (int) $account['ally_id'];
            }
        }

        self::$prefetch = array(
            'primed' => true,
            'planets' => $planets,
            'moons' => $this->findMoonsByIds($moonIds),
            'users' => $users,
            'allies' => array_values(array_unique($allies)),
        );
        self::$stale = array();

        return $positions;
    }

    /** Comptes préchargés (identifiants), pour qui doit précharger à son tour. */
    public function prefetchedOwnerIds(): array
    {
        return array_keys(self::$prefetch['users']);
    }

    /** Alliances des comptes préchargés, à passer au module des alliances. */
    public function prefetchedAllyIds(): array
    {
        return self::$prefetch['allies'];
    }

    /**
     * Oublie une planète préchargée : une écriture en cours de rendu l'a périmée,
     * la prochaine lecture doit revenir à la base (abandons traités par la vue).
     */
    public static function forgetPlanet(int $id): void
    {
        self::$stale['planets'][$id] = true;
    }

    public static function forgetMoon(int $id): void
    {
        self::$stale['moons'][$id] = true;
    }

    /**
     * Les positions d'un système en une requête, indexées par numéro de planète.
     *
     * @return array<int, array>
     */
    public function findSystem(int $galaxy, int $system): array
    {
        $rows = $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ?",
            array($galaxy, $system),
            'galaxy'
        );

        $indexed = array();
        foreach ($rows as $row) {
            $indexed[(int) $row['planet']] = $row;
        }

        return $indexed;
    }

    /**
     * Planètes, lunes et comptes demandés en une requête chacun.
     *
     * @param int[] $ids
     * @return array<int, array>
     */
    public function findPlanetsByIds(array $ids): array
    {
        return $this->byIds($ids, 'planets');
    }

    public function findMoonsByIds(array $ids): array
    {
        return $this->byIds($ids, 'lunas');
    }

    public function findUsersByIds(array $ids): array
    {
        return $this->byIds($ids, 'users');
    }

    public function findPlanetById(int $id): array|false
    {
        return $this->byId('planets', $id, 'planets');
    }

    public function findMoonById(int $id): array|false
    {
        return $this->byId('moons', $id, 'lunas');
    }

    /**
     * Compte par identifiant, servi par le **préchargement** de la vue galaxie.
     *
     * La règle de lecture est celle de `UserRepository::findFullById()` ; ce qui compte ici,
     * c'est le préchargement (une requête pour tout un système) : cette porte reste au dépôt
     * de la galaxie, à côté de `findPlanetById()` et `findMoonById()`.
     */
    public function findUserById(int $id): array|false
    {
        return $this->byId('users', $id, 'users');
    }

    /**
     * Lecture par identifiant, servie par le préchargement quand il est en place.
     * Une fois préchargé, un identifiant absent est un identifiant qui n'existe
     * pas : inutile de redemander la base.
     */
    private function byId(string $key, int $id, string $table): array|false
    {
        if (self::$prefetch['primed'] && !isset(self::$stale[$key][$id])) {
            return self::$prefetch[$key][$id] ?? false;
        }

        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE id = ?",
            array($id),
            $table
        );
    }

    /** @return array<int, array> */
    private function byIds(array $ids, string $table): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === array()) {
            return array();
        }

        $rows = $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')',
            $ids,
            $table
        );

        $indexed = array();
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        return $indexed;
    }

    public function deleteMoon(int $id): void
    {
        $this->preparedExecute(
            "DELETE FROM {{table}} WHERE id = ?",
            array($id),
            'lunas'
        );
    }

    /**
     * Efface des lignes de `galaxy` par position (nettoyage anti-triche).
     *
     * Les positions viennent de `PlanetRepository::purgeWorldsOfOwner()` : c'est le dépôt des
     * planètes qui possède les planètes, cette table ne fait que les annoncer.
     *
     * @param list<array{galaxy: int, system: int, planet: int}> $positions
     */
    public function purgePositions(array $positions): void
    {
        foreach ($positions as $position) {
            $this->preparedExecute(
                'DELETE FROM {{table}} WHERE `galaxy` = ? AND `system` = ? AND `planet` = ?',
                array((int) $position['galaxy'], (int) $position['system'], (int) $position['planet']),
                'galaxy'
            );
        }
    }

    /**
     * Retire le lien d'une planète : la position redevient libre (la colonisation
     * teste le nombre de positions occupées), sans perdre le champ de débris.
     */
    public function clearPlanetLink(int $galaxy, int $system, int $planet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET id_planet = 0 WHERE galaxy = ? AND `system` = ? AND planet = ?",
            array($galaxy, $system, $planet),
            'galaxy'
        );
    }

    /**
     * Annonce une planète à une position : la ligne `galaxy` est **mise à jour** si elle
     * existe, créée sinon.
     *
     * La table n'a **pas** de clé unique sur la position (trois index simples, comme le
     * legacy l'a laissée) : un `INSERT ... ON DUPLICATE KEY UPDATE` ne se déclencherait donc
     * jamais et poserait une **seconde** ligne pour la même position — la question se pose
     * donc en deux temps. Les deux appelants sont dans ce cas : le rétablissement d'une
     * colonie abandonnée (la ligne existe, `id_planet` à 0) et la création d'une colonie ou
     * d'une lune, qui suivait déjà ce chemin (`UPDATE` puis `INSERT`).
     */
    public function linkPlanet(int $galaxy, int $system, int $planet, int $planetId): void
    {
        if ($this->findPosition($galaxy, $system, $planet) === false) {
            $this->preparedExecute(
                'INSERT INTO {{table}} (galaxy, `system`, planet, id_planet) VALUES (?, ?, ?, ?)',
                array($galaxy, $system, $planet, $planetId),
                'galaxy'
            );

            return;
        }

        $this->preparedExecute(
            "UPDATE {{table}} SET id_planet = ? WHERE galaxy = ? AND `system` = ? AND planet = ?",
            array($planetId, $galaxy, $system, $planet),
            'galaxy'
        );
    }

    /**
     * Détache la lune d'une position : la ligne `galaxy` de l'endroit ne doit plus
     * annoncer une lune qui n'existe plus.
     */
    public function clearMoonLink(int $galaxy, int $system, int $planet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET id_luna = 0, luna = 0 WHERE galaxy = ? AND `system` = ? AND planet = ?",
            array($galaxy, $system, $planet),
            'galaxy'
        );
    }

    public function deletePlanetAndGalaxyRow(int $planetId): void
    {
        $this->preparedExecute(
            "DELETE FROM {{table}} WHERE id = ?",
            array($planetId),
            'planets'
        );
        $this->preparedExecute(
            "DELETE FROM {{table}} WHERE id_planet = ?",
            array($planetId),
            'galaxy'
        );
    }

    /**
     * Annonce de nouveau la lune d'une position dans la ligne `galaxy`.
     *
     * `id_luna` porte l'identifiant du **registre** (`lunas.id`), pas celui de la
     * ligne `planets` : c'est ce que la galaxie et les pages de lune relisent.
     */
    public function linkMoon(int $galaxy, int $system, int $planet, int $registryId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET id_luna = ?, luna = 0 WHERE galaxy = ? AND `system` = ? AND planet = ?",
            array($registryId, $galaxy, $system, $planet),
            'galaxy'
        );
    }

    public function findHomeByPlanetId(int $planetId): array|false
    {
        return $this->findByPlanetId($planetId);
    }

    public function findByPlanetId(int $planetId): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE id_planet = ?",
            array($planetId),
            'galaxy'
        );
    }

    /**
     * La ligne `galaxy` d'une position : champ de débris, planète et lune liées.
     *
     * Deux questions, une seule requête : « la position est-elle déjà annoncée ? »
     * (inscription, déplacement d'une planète — c'est `id_planet` qui dit si un
     * planète y vit) et « quels débris y a-t-il ? » (`findDebrisAt()`, qui délègue).
     * La table n'a **pas** de clé unique sur la position : le `LIMIT 1` est celui
     * du legacy.
     */
    public function findPosition(int $galaxy, int $system, int $planet): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ? LIMIT 1",
            array($galaxy, $system, $planet),
            'galaxy'
        );
    }

    /** Le champ de débris d'une position : la même ligne, vue sous cet angle. */
    public function findDebrisAt(int $galaxy, int $system, int $planet): array|false
    {
        return $this->findPosition($galaxy, $system, $planet);
    }

    /**
     * Ajoute des débris à la ligne `galaxy` d'une position (fin de combat).
     *
     * Les colonnes écrites sont celles que le jeu **déclare** comme champ de débris
     * (`metal`, `crystal`, plus celles qu'un module ajoute — `ModuleService::debrisFields()`) :
     * les montants arrivent donc sous forme de table, et chaque nom de colonne est vérifié.
     *
     * La **position** et non l'identifiant : une attaque peut viser la lune d'un endroit, dont
     * le champ de débris est celui de la position (le `LIMIT 1` est celui du legacy).
     *
     * @param array<string, float|int|string> $amounts colonne => montant ajouté
     */
    public function addDebrisAt(int $galaxy, int $system, int $planet, array $amounts): void
    {
        $sets   = array();
        $params = array();

        foreach ($amounts as $column => $amount) {
            if (!is_string($column) || !self::isColumnName($column)) {
                continue;
            }

            $sets[]   = '`' . $column . '` = `' . $column . '` + ?';
            $params[] = is_scalar($amount) ? (string) round((float) $amount) : '0';
        }

        if ($sets === array()) {
            return;
        }

        $params[] = (string) $galaxy;
        $params[] = (string) $system;
        $params[] = (string) $planet;

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . implode(', ', $sets)
                . ' WHERE `galaxy` = ? AND `system` = ? AND `planet` = ? LIMIT 1',
            $params,
            'galaxy'
        );
    }

    /**
     * Déplace la ligne d'une position : elle porte le champ de débris, la planète
     * et la lune de l'endroit. La ligne suit la planète, débris compris.
     */
    public function movePosition(int $galaxy, int $system, int $planet, int $newGalaxy, int $newSystem, int $newPlanet): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET galaxy = ?, `system` = ?, planet = ?"
            . " WHERE galaxy = ? AND `system` = ? AND planet = ?",
            array($newGalaxy, $newSystem, $newPlanet, $galaxy, $system, $planet),
            'galaxy'
        );
    }
}
