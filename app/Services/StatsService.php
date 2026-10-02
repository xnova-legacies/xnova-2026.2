<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\GameConfig;
use App\Core\GameData;
use App\Database\Connection;

/**
 * Classement des joueurs et des alliances (ex `admin/statbuilder.php` + `statfunctions.php`).
 *
 * La page historique recalculait tout l'univers à chaque ouverture : un
 * administrateur qui l'ouvrait figeait la requête le temps du calcul, et rien ne
 * rafraîchissait les points le reste du temps. Le calcul vit maintenant ici et
 * s'appelle depuis la ligne de commande (`db/stats.php`), donc depuis une tâche
 * planifiée : même résultat, mais déclenchable sans navigateur.
 *
 * Les formules sont reprises telles quelles :
 *   - un niveau de bâtiment ou de recherche compte les niveaux **déjà** construits
 *     (de 1 à niveau-1), pondérés par le facteur de prix de l'élément ;
 *   - les défenses et les vaisseaux comptent pour leur prix unitaire × quantité ;
 *   - tout est divisé par le réglage `stat_settings` de l'univers.
 */
final class StatsService
{
    public const STAT_PLAYER = 1;
    public const STAT_ALLY = 2;

    /** Code des lignes courantes (`stat_code`) ; le précédent devient `2`. */
    public const CODE_CURRENT = 1;
    public const CODE_PREVIOUS = 2;

    /** Colonnes de points classées, dans l'ordre utilisé pour les rangs. */
    private const RANKED = array('tech_points', 'build_points', 'defs_points', 'fleet_points', 'total_points');

    /**
     * Points d'un élément construit par niveaux.
     *
     * Le niveau en cours de construction n'est pas compté : un bâtiment de niveau
     * 5 vaut les niveaux 1 à 4 (règle historique, conservée).
     */
    public static function levelPoints(array $price, int $level): float
    {
        $unit = self::unitPrice($price);
        $factor = (float) ($price['factor'] ?? 1);
        $points = 0.0;

        for ($built = 1; $built < $level; $built++) {
            $points += $unit * ($factor ** $built);
        }

        return $points;
    }

    /** Points d'une quantité d'unités (défenses, vaisseaux) : prix unitaire × quantité. */
    public static function unitPoints(array $price, int $count): float
    {
        return self::unitPrice($price) * max(0, $count);
    }

    /**
     * Points et quantités d'une catégorie d'éléments.
     *
     * @param array<string, mixed> $row      ligne de compte (recherches) ou de planète
     * @param list<int>            $elements identifiants d'éléments (tech, build, defense, fleet)
     * @param bool                 $byLevel  true pour un bâtiment/recherche, false pour une unité
     *
     * @return array{points: float, count: int}
     */
    public static function categoryPoints(array $row, array $elements, bool $byLevel): array
    {
        $resource = GameData::resource();
        $prices = GameData::priceList();
        $points = 0.0;
        $count = 0;

        foreach ($elements as $element) {
            $column = (string) ($resource[$element] ?? '');

            if ($column === '' || !array_key_exists($column, $row)) {
                continue;
            }

            $value = (int) $row[$column];

            if ($value <= 0) {
                continue;
            }

            $price = is_array($prices[$element] ?? null) ? $prices[$element] : array();

            if ($byLevel) {
                $points += self::levelPoints($price, $value);
                $count += $value - 1;
            } else {
                $points += self::unitPoints($price, $value);
                $count += $value;
            }
        }

        return array('points' => $points, 'count' => $count);
    }

    /**
     * Recalcule le classement complet.
     *
     * @return array{players: int, alliances: int} nombre de lignes écrites
     */
    public function rebuild(): array
    {
        $settings = (float) GameConfig::get('stat_settings', '1000');
        $settings = $settings > 0 ? $settings : 1000.0;
        $statDate = time();

        $this->rotateSnapshots();

        $players = 0;

        foreach (Connection::fetchAll('SELECT * FROM {{table}}', 'users') as $user) {
            $this->rebuildPlayer($user, $settings, $statDate);
            $players++;
        }

        $this->rankStatements(self::STAT_PLAYER);

        $alliances = $this->rebuildAlliances($statDate);
        $this->rankStatements(self::STAT_ALLY);

        return array('players' => $players, 'alliances' => $alliances);
    }

    /** Le relevé précédent recule d'un cran, l'avant-dernier disparaît. */
    private function rotateSnapshots(): void
    {
        Connection::query(
            "DELETE FROM {{table}} WHERE `stat_code` = '" . self::CODE_PREVIOUS . "'",
            'statpoints'
        );
        Connection::query('UPDATE {{table}} SET `stat_code` = `stat_code` + 1', 'statpoints');
    }

    /**
     * Recalcule un joueur : ses points par catégorie, ses planètes et sa ligne.
     *
     * @param array<string, mixed> $user
     */
    private function rebuildPlayer(array $user, float $settings, int $statDate): void
    {
        $userId = (int) $user['id'];
        $old = $this->readRanks(self::STAT_PLAYER, $userId);

        $this->deleteStat(self::STAT_PLAYER, $userId);

        $resList = GameData::resList();
        $tech = self::categoryPoints($user, $resList['tech'] ?? array(), true);

        $counts = array('build' => 0, 'defs' => 0, 'fleet' => 0);
        $points = array('build' => 0.0, 'defs' => 0.0, 'fleet' => 0.0);

        foreach ($this->planetsOf($userId) as $planet) {
            $planetPoints = 0.0;

            foreach (array('build' => true, 'defense' => false, 'fleet' => false) as $category => $byLevel) {
                $key = $category === 'defense' ? 'defs' : $category;
                $totals = self::categoryPoints($planet, $resList[$category] ?? array(), $byLevel);
                $counts[$key] += $totals['count'];
                $points[$key] += $totals['points'] / $settings;
                $planetPoints += $totals['points'] / $settings;
            }

            $this->writePlanetPoints((int) $planet['id'], $planetPoints);
        }

        $techPoints = $tech['points'] / $settings;

        $this->insertStat(self::STAT_PLAYER, $userId, (int) $user['ally_id'], $statDate, array(
            'tech_points' => $techPoints,
            'tech_count' => $tech['count'],
            'build_points' => $points['build'],
            'build_count' => $counts['build'],
            'defs_points' => $points['defs'],
            'defs_count' => $counts['defs'],
            'fleet_points' => $points['fleet'],
            'fleet_count' => $counts['fleet'],
            'total_points' => $techPoints + $points['build'] + $points['defs'] + $points['fleet'],
            'total_count' => $tech['count'] + $counts['build'] + $counts['defs'] + $counts['fleet'],
        ), $old);
    }

    /**
     * Recalcule chaque alliance : la somme des lignes de ses membres.
     *
     * La page historique posait une ligne d'alliance sans jamais lui attribuer de
     * rang (`*_rank` restaient à zéro) : ils sont maintenant classés comme les
     * joueurs, ce que lit l'affichage.
     */
    private function rebuildAlliances(int $statDate): int
    {
        $count = 0;

        foreach (Connection::fetchAll('SELECT `id` FROM {{table}}', 'alliance') as $ally) {
            $allyId = (int) $ally['id'];
            $old = $this->readRanks(self::STAT_ALLY, $allyId);

            $this->deleteStat(self::STAT_ALLY, $allyId);

            $totals = $this->sumMembers($allyId);

            $this->insertStat(self::STAT_ALLY, $allyId, 0, $statDate, $totals, $old);
            $count++;
        }

        return $count;
    }

    /**
     * Somme des lignes courantes des membres d'une alliance.
     *
     * @return array<string, float|int>
     */
    private function sumMembers(int $allyId): array
    {
        $sums = array(
            'tech_points' => 0.0, 'tech_count' => 0,
            'build_points' => 0.0, 'build_count' => 0,
            'defs_points' => 0.0, 'defs_count' => 0,
            'fleet_points' => 0.0, 'fleet_count' => 0,
            'total_points' => 0.0, 'total_count' => 0,
        );

        $columns = implode(', ', array_map(static fn (string $name): string => 'SUM(`' . $name . '`) AS `' . $name . '`', array_keys($sums)));

        $row = Connection::preparedFetchOne(
            'SELECT ' . $columns . ' FROM {{table}} WHERE `stat_type` = ? AND `stat_code` = ? AND `id_ally` = ?',
            array((string) self::STAT_PLAYER, (string) self::CODE_CURRENT, (string) $allyId),
            'statpoints'
        );

        if (is_array($row)) {
            foreach ($sums as $name => $unused) {
                $sums[$name] = (float) ($row[$name] ?? 0);
            }
        }

        return $sums;
    }

    /** Attribue les rangs de chaque catégorie, du meilleur au moins bon. */
    private function rankStatements(int $statType): void
    {
        foreach (self::RANKED as $column) {
            $rankColumn = str_replace('_points', '_rank', $column);
            $rank = 0;

            $rows = Connection::preparedFetchAll(
                'SELECT `id_owner` FROM {{table}} WHERE `stat_type` = ? AND `stat_code` = ? ORDER BY `' . $column . '` DESC',
                array((string) $statType, (string) self::CODE_CURRENT),
                'statpoints'
            );

            foreach ($rows as $row) {
                $rank++;

                Connection::preparedExecute(
                    'UPDATE {{table}} SET `' . $rankColumn . '` = ?'
                    . ' WHERE `stat_type` = ? AND `stat_code` = ? AND `id_owner` = ?',
                    array((string) $rank, (string) $statType, (string) self::CODE_CURRENT, (string) $row['id_owner']),
                    'statpoints'
                );
            }
        }
    }

    /**
     * Rangs du relevé précédent (ils alimentent les colonnes « ancien rang »).
     *
     * La ligne précédente porte les rangs **du tour d'avant** dans ses colonnes
     * `*_rank` : c'est cette valeur que la page historique recopiait dans
     * `*_old_rank`, et non l'ancien rang qu'elle contenait déjà.
     *
     * @return array<string, int>
     */
    private function readRanks(int $statType, int $ownerId): array
    {
        $names = array();

        foreach (self::RANKED as $column) {
            $names[str_replace('_points', '_old_rank', $column)] = 0;
        }

        $row = Connection::preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `stat_type` = ? AND `id_owner` = ?',
            array((string) $statType, (string) $ownerId),
            'statpoints'
        );

        if (is_array($row)) {
            foreach (self::RANKED as $column) {
                $names[str_replace('_points', '_old_rank', $column)] = (int) ($row[str_replace('_points', '_rank', $column)] ?? 0);
            }
        }

        return $names;
    }

    private function deleteStat(int $statType, int $ownerId): void
    {
        Connection::preparedExecute(
            'DELETE FROM {{table}} WHERE `stat_type` = ? AND `id_owner` = ?',
            array((string) $statType, (string) $ownerId),
            'statpoints'
        );
    }

    /**
     * @param array<string, float|int> $values
     * @param array<string, int>       $old
     */
    private function insertStat(int $statType, int $ownerId, int $allyId, int $statDate, array $values, array $old): void
    {
        $columns = array('id_owner', 'id_ally', 'stat_type', 'stat_code', 'stat_date');
        $params = array((string) $ownerId, (string) $allyId, (string) $statType, (string) self::CODE_CURRENT, (string) $statDate);

        $categories = array('tech', 'build', 'defs', 'fleet', 'total');

        // Points puis quantités, pour chaque catégorie.
        foreach ($categories as $category) {
            $columns[] = $category . '_points';
            $params[] = (string) ($values[$category . '_points'] ?? 0);
        }

        foreach ($categories as $category) {
            $columns[] = $category . '_count';
            $params[] = (string) ($values[$category . '_count'] ?? 0);
        }

        // Rangs du relevé précédent, puis rangs courants (posés par rankStatements()).
        foreach ($categories as $category) {
            $columns[] = $category . '_old_rank';
            $params[] = (string) ($old[$category . '_old_rank'] ?? 0);
        }

        foreach ($categories as $category) {
            $columns[] = $category . '_rank';
            $params[] = '0';
        }

        $names = array_map(static fn (string $name): string => '`' . $name . '`', $columns);
        $marks = implode(', ', array_fill(0, count($columns), '?'));

        Connection::preparedExecute(
            'INSERT INTO {{table}} (' . implode(', ', $names) . ') VALUES (' . $marks . ')',
            $params,
            'statpoints'
        );
    }

    /** @return list<array<string, mixed>> */
    private function planetsOf(int $userId): array
    {
        return Connection::preparedFetchAll(
            'SELECT * FROM {{table}} WHERE `id_owner` = ? AND (`flags` & ?) = 0',
            array((string) $userId, (string) \App\Core\Flags::DELETED),
            'planets'
        );
    }

    private function writePlanetPoints(int $planetId, float $points): void
    {
        Connection::preparedExecute(
            'UPDATE {{table}} SET `points` = ? WHERE `id` = ?',
            array((string) $points, (string) $planetId),
            'planets'
        );
    }

    /** Prix unitaire d'un élément (métal + cristal + deutérium). */
    private static function unitPrice(array $price): float
    {
        return (float) ($price['metal'] ?? 0)
            + (float) ($price['crystal'] ?? 0)
            + (float) ($price['deuterium'] ?? 0);
    }
}
