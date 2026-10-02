<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

/**
 * Journal des actions des joueurs (table `actions`).
 *
 * Une écriture (l'insertion d'une action) et deux lectures : les dernières
 * actions d'une fenêtre, et le décompte par action. Aucune règle ici — le
 * libellé, la nature et la fenêtre de conservation vivent dans `ActionService`.
 *
 * Le type de compte (« robots ») est un **point de surcharge** : le Coeur d'application ne connaît
 * aucune colonne de robot, le module qui en possède une dérive cette classe.
 */
class ActionRepository extends BaseRepository
{
    /** Enregistre une action. */
    public function record(
        int $userId,
        string $action,
        string $kind,
        string $method,
        string $detail,
        string $payload,
        string $ip,
        int $moment
    ): void {
        $this->preparedExecute(
            "INSERT INTO {{table}}
                (`id_owner`, `action`, `kind`, `method`, `detail`, `payload`, `user_lastip`, `action_time`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            array($userId, $action, $kind, $method, $detail, $payload, $ip, $moment),
            'actions'
        );
    }

    /** Un compte est-il un robot ? (le journal ne garde que les joueurs) */
    public function isBot(int $userId): bool
    {
        $column = $this->botColumn();

        // Sans module, le Coeur d'application ne connaît aucune colonne de robot : aucun compte n'en
        // est un, et le journal garde tout le monde plutôt que d'écarter des comptes
        // sur une colonne qui n'existe pas.
        if ($column === '') {
            return false;
        }

        $row = $this->preparedFetchOne(
            'SELECT ' . $column . ' FROM ' . Connection::table('users') . ' WHERE id = ?',
            array($userId)
        );

        return (int) ($row[$column] ?? 0) === 1;
    }

    /**
     * Nom de la colonne qui dit qu'un compte est un robot, ou `''` quand le Coeur d'application
     * n'en connaît aucune.
     *
     * **Point de surcharge** : un module qui possède une telle colonne dans `users`
     * dérive cette classe et renvoie son nom ; le Coeur d'application compose le reste.
     */
    protected function botColumn(): string
    {
        return '';
    }

    /** Colonnes lues sur `users` en plus des siennes (fragment inséré tel quel). */
    private function extraUserColumns(string $alias = 'u'): string
    {
        $column = $this->botColumn();

        return $column === '' ? '' : ', ' . ($alias === '' ? $column : $alias . '.' . $column);
    }

    /**
     * Dernières actions d'une fenêtre, de la plus récente à la plus ancienne.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Colonnes de tri proposées (clé d'URL => colonne SQL).
     *
     * La page s'en sert comme liste blanche : aucune valeur venue de l'URL n'entre
     * dans la requête.
     */
    public const SORTS = array(
        'time' => 'a.action_time',
        'user' => 'u.username',
        'action' => 'a.action',
        'kind' => 'a.kind',
        'method' => 'a.method',
        'ip' => 'a.user_lastip',
    );

    /** Clause `ORDER BY`, bornée aux colonnes de la liste blanche. */
    private function orderBy(string $sort, string $order, string $default): string
    {
        $column = self::SORTS[$sort] ?? self::SORTS[$default];
        $direction = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        // L'identifiant départage deux valeurs égales : sans lui, une ligne pourrait
        // apparaître deux fois d'une page à l'autre et une autre disparaître.
        return ' ORDER BY ' . $column . ' ' . $direction . ', a.id ' . $direction;
    }

    public function findRecent(
        int $limit,
        int $from,
        int $to,
        int $playerId = 0,
        string $kind = 'all',
        int $offset = 0,
        string $sort = 'time',
        string $order = 'desc'
    ): array {
        $params = array($from, $to);

        return $this->preparedFetchAll(
            "SELECT a.*, u.username" . $this->extraUserColumns() . "
            FROM {{table}} AS a
            LEFT JOIN " . Connection::table('users') . " AS u ON u.id = a.id_owner
            WHERE a.action_time >= ? AND a.action_time <= ?"
                . $this->playerCondition($playerId, $params) . $this->kindCondition($kind, $params)
                . $this->orderBy($sort, $order, 'time')
                . "
            LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params,
            'actions'
        );
    }

    /**
     * Décompte par action sur une fenêtre, de la plus fréquente à la plus rare.
     *
     * @return list<array<string, mixed>>
     */
    public function findTotalsByAction(
        int $from,
        int $to,
        int $limit,
        int $playerId = 0,
        string $kind = 'all'
    ): array {
        $params = array($from, $to);

        return $this->preparedFetchAll(
            "SELECT a.action, a.kind, a.method, COUNT(*) AS nb, MAX(a.action_time) AS last_time
            FROM {{table}} AS a
            WHERE a.action_time >= ? AND a.action_time <= ?"
                . $this->playerCondition($playerId, $params) . $this->kindCondition($kind, $params) . "
            GROUP BY a.action, a.kind, a.method
            ORDER BY nb DESC, a.action ASC
            LIMIT " . max(1, $limit),
            $params,
            'actions'
        );
    }

    /** Nombre total d'actions d'une fenêtre (pied de rendu). */
    public function countIn(int $from, int $to, int $playerId = 0, string $kind = 'all'): int
    {
        $params = array($from, $to);
        $row = $this->preparedFetchOne(
            "SELECT COUNT(*) AS nb FROM {{table}} AS a
            WHERE a.action_time >= ? AND a.action_time <= ?"
                . $this->playerCondition($playerId, $params) . $this->kindCondition($kind, $params),
            $params,
            'actions'
        );

        return (int) ($row['nb'] ?? 0);
    }

    /** Supprime les actions antérieures à une date. @return int lignes effacées */
    public function prune(int $before): int
    {
        return $this->preparedExecute(
            "DELETE FROM {{table}} WHERE action_time < ?",
            array($before),
            'actions'
        );
    }

    /**
     * Condition de filtre sur un compte, et le paramètre qui va avec.
     *
     * `0` = tous ; tout autre identifiant filtre pour de bon (le `-1` de la
     * saisie sans correspondance vide alors le journal).
     *
     * @param list<mixed> $params complété sur place : l'ordre suit celui du SQL
     */
    private function playerCondition(int $playerId, array &$params): string
    {
        if ($playerId === 0) {
            return '';
        }

        $params[] = $playerId;

        return ' AND a.id_owner = ?';
    }

    /**
     * Condition de nature : une page servie, une route JSON, ou une adresse inconnue.
     *
     * La nature entre en **paramètre lié** (comme le filtre de compte, `$params` est complété
     * sur place dans l'ordre du SQL) : la liste fermée ici ne dispense pas du marqueur.
     *
     * @param list<mixed> $params complété sur place : l'ordre suit celui du SQL
     */
    private function kindCondition(string $kind, array &$params): string
    {
        if (in_array($kind, array('page', 'api', 'unknown'), true)) {
            $params[] = $kind;

            return ' AND a.kind = ?';
        }

        return '';
    }
}
