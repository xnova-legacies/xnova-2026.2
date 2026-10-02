<?php

namespace App\Repositories;

use App\Core\Flags;

/**
 * Chiffres de l'univers : classements et totaux du panneau.
 *
 * Le compte des robots est un **point de surcharge** : le Coeur d'application ne connaît aucune
 * colonne de robot, le module qui en possède une dérive cette classe.
 */
class StatsRepository extends BaseRepository
{
    /**
     * Nom de la colonne qui dit qu'un compte est un robot, ou `''` quand le Coeur d'application
     * n'en connaît aucune — c'est le cas sans le module.
     */
    protected function botColumn(): string
    {
        return '';
    }

    public function countAlliances(): array|false
    {
        return $this->fetchOne("SELECT COUNT(*) AS `count` FROM {{table}} WHERE 1;", 'alliance');
    }

    public function countActiveUsers(): array|false
    {
        return $this->fetchOne("SELECT COUNT(*) AS `count` FROM {{table}} WHERE `no_javascript` = '0';", 'users');
    }

    /**
     * Chiffres de l'univers, pour la page de gestion des joueurs.
     *
     * « Actif » est la notion du jeu (`no_javascript`), « en ligne » celle du
     * panneau (connecté depuis moins de quinze minutes, celle de la vue
     * générale). Les ressources sont un total, en unités de métal.
     *
     * @return array<string, int>
     */
    public function universeCounts(int $onlineSince): array
    {
        // Sans le module qui possède la colonne, l'univers ne compte aucun robot : la
        // tuile du panneau n'annonce pas une population qui n'existe plus.
        $botColumn = $this->botColumn();
        $botCondition = $botColumn === '' ? '0' : $botColumn . ' = 1';

        $accounts = $this->preparedFetchOne(
            "SELECT
                COUNT(*) AS accounts,
                SUM(CASE WHEN " . $botCondition . " THEN 1 ELSE 0 END) AS bots,
                SUM(CASE WHEN bana = 1 THEN 1 ELSE 0 END) AS banned,
                SUM(CASE WHEN (`flags` & ?) = 1 THEN 1 ELSE 0 END) AS removed,
                SUM(CASE WHEN no_javascript = '0' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN no_javascript = '1' THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN onlinetime >= ? THEN 1 ELSE 0 END) AS online
            FROM {{table}}",
            array((string) Flags::DELETED, $onlineSince),
            'users'
        );

        $places = $this->preparedFetchOne(
            "SELECT
                SUM(CASE WHEN planet_type = 1 AND (`flags` & ?) = 0 THEN 1 ELSE 0 END) AS planets,
                SUM(CASE WHEN planet_type = 3 AND (`flags` & ?) = 0 THEN 1 ELSE 0 END) AS moons,
                SUM(field_current) AS fields,
                SUM(metal) AS metal,
                SUM(crystal) AS crystal,
                SUM(deuterium) AS deuterium
            FROM {{table}}",
            array((string) Flags::DELETED, (string) Flags::DELETED),
            'planets'
        );

        $fleets = $this->preparedFetchOne(
            'SELECT COUNT(*) AS fleets FROM {{table}} WHERE (`flags` & ?) = 0',
            array((string) Flags::DELETED),
            'fleets'
        );

        return array(
            'accounts' => (int) ($accounts['accounts'] ?? 0),
            'bots' => (int) ($accounts['bots'] ?? 0),
            'banned' => (int) ($accounts['banned'] ?? 0),
            'removed' => (int) ($accounts['removed'] ?? 0),
            'active' => (int) ($accounts['active'] ?? 0),
            'inactive' => (int) ($accounts['inactive'] ?? 0),
            'online' => (int) ($accounts['online'] ?? 0),
            'planets' => (int) ($places['planets'] ?? 0),
            'moons' => (int) ($places['moons'] ?? 0),
            'fields' => (int) ($places['fields'] ?? 0),
            'metal' => (int) ($places['metal'] ?? 0),
            'crystal' => (int) ($places['crystal'] ?? 0),
            'deuterium' => (int) ($places['deuterium'] ?? 0),
            'fleets' => (int) ($fleets['fleets'] ?? 0),
        );
    }

    /** Colonnes de tri acceptées par le classement (liste blanche, jamais l'URL). */
    public const SORTS = array('total_points', 'fleet_points', 'tech_points', 'tech_count', 'build_points', 'defs_points');

    /** Colonnes de rang acceptées (liste blanche des écritures du classement). */
    public const RANK_COLUMNS = array(
        'total_rank', 'total_old_rank',
        'fleet_rank', 'fleet_old_rank',
        'tech_rank', 'tech_old_rank',
        'build_rank', 'build_old_rank',
        'defs_rank', 'defs_old_rank',
    );

    /**
     * Colonne de tri du classement : la **liste blanche** décide, pas la valeur reçue.
     */
    private function sortColumn(string $order): string
    {
        return in_array($order, self::SORTS, true) ? $order : self::SORTS[0];
    }

    /** Nom de colonne de rang accepté par les écritures du classement. */
    private function isRankColumn(string $column): bool
    {
        return in_array($column, self::RANK_COLUMNS, true);
    }

    /**
     * Lignes d'un classement d'alliances, triées par la colonne demandée.
     *
     * La taille de page traverse l'appel (la requête portait un `100` figé, donc la page
     * affichait cent lignes quelle que soit la taille choisie) ; le décalage est un entier
     * borné, jamais une valeur liée (`LIMIT` reçoit un nombre, pas une chaîne liée).
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Efface les points d'un propriétaire dans un classement (nettoyage anti-triche).
     *
     * `stat_type` 1 = joueurs, 2 = alliances : les deux relevés d'un compte qui part.
     */
    public function purgeStatpoints(int $ownerId, int $statType): void
    {
        $this->preparedExecute(
            'DELETE FROM {{table}} WHERE `stat_type` = ? AND `id_owner` = ?',
            array((string) $statType, $ownerId),
            'statpoints'
        );
    }

    public function findAllyStats(string $order, int $offset, int $limit): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE `stat_type` = '2' AND `stat_code` = '1' ORDER BY `"
            . $this->sortColumn($order) . '` DESC LIMIT ' . (int) $offset . ', ' . (int) $limit,
            array(),
            'statpoints'
        );
    }

    /**
     * Préchargement des points de classement (vue galaxie).
     *
     * La colonne « joueur » interrogeait `statpoints` deux fois par position — une
     * fois pour le compte affiché, une fois pour son propre compte, avec la même
     * question répétée quinze fois. Une requête suffit pour tout un système.
     */
    private static ?array $points = null;

    /**
     * Points du classement courant des comptes demandés, indexés par compte.
     *
     * @param int[] $ownerIds
     * @return array<int, array>
     */
    public function findCurrentPointsByIds(array $ownerIds): array
    {
        $ownerIds = array_values(array_unique(array_filter(
            array_map('intval', $ownerIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($ownerIds === array()) {
            return array();
        }

        $rows = $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE stat_type = '1' AND stat_code = '1'"
            . ' AND id_owner IN (' . implode(', ', array_fill(0, count($ownerIds), '?')) . ')',
            $ownerIds,
            'statpoints'
        );

        $indexed = array();
        foreach ($rows as $row) {
            $indexed[(int) $row['id_owner']] = $row;
        }

        return $indexed;
    }

    /** @param int[] $ownerIds */
    public function prefetchCurrentPoints(array $ownerIds): void
    {
        self::$points = $this->findCurrentPointsByIds($ownerIds);
    }

    /**
     * Ligne du classement courant d'un compte, ou null s'il n'en a pas — la même
     * réponse que `doquery(..., true)` sur une ligne absente.
     */
    public function currentPoints(int $ownerId): ?array
    {
        if (self::$points !== null) {
            return self::$points[$ownerId] ?? null;
        }

        $rows = $this->findCurrentPointsByIds(array($ownerId));

        return $rows[$ownerId] ?? null;
    }

    /** Même lecture pour le classement des joueurs (`stat_type` = 1). */
    public function findUserStats(string $order, int $offset, int $limit): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE `stat_type` = '1' AND `stat_code` = '1' ORDER BY `"
            . $this->sortColumn($order) . '` DESC LIMIT ' . (int) $offset . ', ' . (int) $limit,
            array(),
            'statpoints'
        );
    }

    /**
     * Mise à jour d'un rang et de l'ancien rang dans statpoints.
     *
     * `$statType` : 1 = joueur, 2 = alliance. Les deux noms de colonnes passent par la
     * **liste blanche** (`RANK_COLUMNS`) : un identifiant ne peut pas être un paramètre lié,
     * il se vérifie. Le rang et le compte partent liés.
     */
    public function updateRank(int $statType, string $rankColumn, string $oldRankColumn, int $rank, int $idOwner): void
    {
        if (!$this->isRankColumn($rankColumn) || !$this->isRankColumn($oldRankColumn)) {
            return;
        }

        $this->preparedExecute(
            'UPDATE {{table}} SET `' . $rankColumn . '` = ?, `' . $oldRankColumn . '` = ?'
            . ' WHERE `stat_type` = ? AND `stat_code` = 1 AND `id_owner` = ?',
            array($rank, $rank, $this->statType($statType), $idOwner),
            'statpoints'
        );
    }

    /** Mise à jour d'un rang quand l'ancien rang valait 0. */
    public function updateRankOnly(int $statType, string $rankColumn, int $rank, int $idOwner): void
    {
        if (!$this->isRankColumn($rankColumn)) {
            return;
        }

        $this->preparedExecute(
            'UPDATE {{table}} SET `' . $rankColumn . '` = ?'
            . ' WHERE `stat_type` = ? AND `stat_code` = 1 AND `id_owner` = ?',
            array($rank, $this->statType($statType), $idOwner),
            'statpoints'
        );
    }

    /** stat_type accepté : 1 (joueur) ou 2 (alliance). */
    private function statType(int $statType): int
    {
        if (!in_array($statType, array(1, 2), true)) {
            throw new \InvalidArgumentException('stat_type inconnu : ' . $statType);
        }

        return $statType;
    }

    public function findUserStatRow(int $idOwner): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE `stat_type` = '1' AND `stat_code` = '1' AND `id_owner` = ?",
            array($idOwner),
            'statpoints'
        );
    }
}
