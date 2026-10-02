<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

/**
 * Historique des sessions (`sessions`).
 *
 * Aucune règle ici : les seuils, la durée et les seaux des courbes vivent dans
 * `App\Services\SessionService`. Ce dépôt ne fait que lire et écrire les lignes.
 *
 * Le type de compte (« robots ») est un **point de surcharge** : le Coeur d'application ne connaît
 * aucune colonne de robot, le module qui en possède une dérive cette classe et
 * complète les requêtes (`extraUserColumns()`, `botCondition()`).
 */
class SessionRepository extends BaseRepository
{
    /** Ouvre une connexion et renvoie son identifiant. */
    public function open(int $userId, int $moment, string $ip): int
    {
        return $this->preparedInsertId(
            "INSERT INTO {{table}} (id_owner, login_time, last_activity, logout_time, end_reason, user_lastip)
            VALUES (?, ?, ?, 0, '', ?)",
            array($userId, $moment, $moment, $ip),
            'sessions'
        );
    }

    /** Dernière connexion encore ouverte d'un joueur (la plus récente). */
    public function findOpen(int $userId): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE id_owner = ? AND logout_time = 0 ORDER BY id DESC LIMIT 1",
            array($userId),
            'sessions'
        );
    }

    public function find(int $id): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE id = ? LIMIT 1",
            array($id),
            'sessions'
        );
    }

    /** Dernier rafraîchissement vu (l'écriture est limitée en amont). */
    public function touch(int $id, int $moment): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET last_activity = ? WHERE id = ? AND logout_time = 0",
            array($moment, $id),
            'sessions'
        );
    }

    /** Ferme une connexion (déconnexion explicite ou inactivité). */
    public function close(int $id, int $moment, string $reason): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET logout_time = ?, end_reason = ? WHERE id = ? AND logout_time = 0",
            array($moment, $reason, $id),
            'sessions'
        );
    }

    /**
     * Ferme les connexions qui n'ont plus donné signe de vie depuis le seuil.
     * Leur fin est datée au seuil, pas au moment du balayage.
     *
     * @return int nombre de connexions fermées
     */
    public function closeIdle(int $idleSeconds): int
    {
        return $this->preparedExecute(
            "UPDATE {{table}} SET logout_time = last_activity + ?, end_reason = 'timeout'
            WHERE logout_time = 0 AND last_activity < UNIX_TIMESTAMP() - ?",
            array($idleSeconds, $idleSeconds),
            'sessions'
        );
    }

    /** Dernières connexions, avec le pseudo (filtres facultatifs compte / type). */
    /** Colonnes de tri de l'historique (liste blanche). */
    public const SORTS = array(
        'id' => 's.id',
        'login' => 's.login_time',
        'user' => 'u.username',
        'ip' => 's.user_lastip',
        'activity' => 's.last_activity',
        'duration' => 's.last_activity - s.login_time',
        'end' => 's.logout_time',
    );

    public function findRecent(
        int $limit,
        int $playerId = 0,
        string $type = 'all',
        int $offset = 0,
        string $sort = 'id',
        string $order = 'desc'
    ): array {
        $params = array();
        $column = self::SORTS[$sort] ?? self::SORTS['id'];
        $direction = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        return $this->preparedFetchAll(
            "SELECT s.*, u.username, u.authlevel" . $this->extraUserColumns() . "
            FROM {{table}} AS s
            LEFT JOIN " . Connection::table('users') . " AS u ON u.id = s.id_owner
            WHERE 1 = 1" . $this->playerCondition($playerId, $params) . $this->botCondition($type)
                . ' ORDER BY ' . $column . ' ' . $direction . ', s.id ' . $direction
                . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params,
            'sessions'
        );
    }

    /** Nombre de connexions d'un filtre (total de la liste paginée). */
    public function countRecent(int $playerId = 0, string $type = 'all'): int
    {
        $params = array();
        $row = $this->preparedFetchOne(
            // La jointure est celle de la liste : la condition de type parle d'une
            // colonne de module, et un compte est un compte, filtré ou compté.
            "SELECT COUNT(*) AS total FROM {{table}} AS s"
                . ' LEFT JOIN ' . Connection::table('users') . ' AS u ON u.id = s.id_owner'
                . ' WHERE 1 = 1'
                . $this->playerCondition($playerId, $params) . $this->botCondition($type),
            $params,
            'sessions'
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Connexions qui chevauchent une fenêtre (pour les courbes).
     *
     * Une connexion ouverte compte jusqu'à la fin de la fenêtre : la durée est
     * bornée par `SessionService`.
     */
    public function findOverlapping(int $from, int $to, int $playerId = 0, string $type = 'all'): array
    {
        $params = array($to, $from);

        return $this->preparedFetchAll(
            "SELECT s.*" . $this->extraUserColumns() . " FROM {{table}} AS s
            LEFT JOIN " . Connection::table('users') . " AS u ON u.id = s.id_owner
            WHERE s.login_time <= ? AND (s.logout_time = 0 OR s.logout_time >= ?)"
                . $this->playerCondition($playerId, $params) . $this->botCondition($type) . "
            ORDER BY s.login_time ASC",
            $params,
            'sessions'
        );
    }

    /** Temps de connexion par compte sur une fenêtre, du plus long au plus court. */
    public function findTotalsByUser(
        int $from,
        int $to,
        int $limit,
        int $playerId = 0,
        string $type = 'all'
    ): array {
        $params = array($to, $to, $from, $to, $from);

        return $this->preparedFetchAll(
            "SELECT u.username" . $this->extraUserColumns() . ",
                COUNT(*) AS nb_sessions,
                SUM(LEAST(COALESCE(NULLIF(s.logout_time, 0), ?), ?) - GREATEST(s.login_time, ?)) AS seconds
            FROM {{table}} AS s
            LEFT JOIN " . Connection::table('users') . " AS u ON u.id = s.id_owner
            WHERE s.login_time <= ? AND (s.logout_time = 0 OR s.logout_time >= ?)"
                . $this->playerCondition($playerId, $params) . $this->botCondition($type) . "
            GROUP BY s.id_owner, u.username" . $this->extraUserColumns() . "
            HAVING seconds > 0
            ORDER BY seconds DESC
            LIMIT " . max(1, $limit),
            $params,
            'sessions'
        );
    }

    /**
     * Comptes proposés à la saisie : joueurs et/ou robots, par ordre de nom.
     *
     * `$limit` plafonne la proposition (une saisie de nom n'a pas besoin de
     * lister tout l'univers) : la saisie reste libre, même au-delà.
     *
     * @return list<array<string, mixed>>
     */
    public function findAccounts(string $type = 'all', int $limit = 0): array
    {
        $sql = "SELECT id, username" . $this->extraUserColumns('') . " FROM " . Connection::table('users') . "
            WHERE 1 = 1" . $this->botCondition($type, '') . "
            ORDER BY username ASC";

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        return $this->preparedFetchAll($sql, array());
    }

    /**
     * Identifiant d'un compte d'après son nom (le plus ancien si le nom est en
     * double). 0 si aucun compte ne porte ce nom.
     */
    public function findIdByName(string $name, string $type = 'all'): int
    {
        $row = $this->preparedFetchOne(
            "SELECT id FROM " . Connection::table('users') . "
            WHERE username = ?" . $this->botCondition($type, '') . "
            ORDER BY id ASC LIMIT 1",
            array($name)
        );

        return (int) ($row['id'] ?? 0);
    }

    /**
     * Condition de filtre sur un compte choisi, et le paramètre qui va avec.
     *
     * Seul `0` vaut « tous » : un identifiant impossible (-1, nom saisi sans
     * correspondance) doit filtrer pour de bon, donc ne rien renvoyer.
     *
     * @param list<mixed> $params complété sur place : l'ordre suit celui du SQL
     */
    private function playerCondition(int $playerId, array &$params): string
    {
        if ($playerId === 0) {
            return '';
        }

        $params[] = $playerId;

        return ' AND s.id_owner = ?';
    }

    /**
     * Nom de la colonne qui dit qu'un compte est un robot, ou `''` quand le Coeur d'application
     * n'en connaît aucune — c'est le cas sans le module : aucun compte n'est un robot.
     *
     * **Point de surcharge** : un module qui possède une telle colonne dans `users`
     * dérive cette classe et renvoie son nom. Le Coeur d'application compose le reste (la colonne
     * jointe et le filtre de type), donc ses requêtes ne changent pas.
     */
    protected function botColumn(): string
    {
        return '';
    }

    /**
     * Colonnes lues sur `users` **en plus** des siennes, jointes à chaque ligne.
     *
     * Inséré tel quel à la suite de la liste des colonnes (virgule comprise) et, quand
     * la requête groupe, à la suite du `GROUP BY` — la colonne lue doit y figurer.
     * `$alias` est vide pour les requêtes qui lisent `users` sans alias.
     */
    private function extraUserColumns(string $alias = 'u'): string
    {
        $column = $this->botColumn();

        return $column === '' ? '' : ', ' . ($alias === '' ? $column : $alias . '.' . $column);
    }

    /** Condition de type de compte : les robots jouent aussi, mais à part. */
    private function botCondition(string $type, string $alias = 'u'): string
    {
        $column = $this->botColumn();

        if ($column === '') {
            return $type === 'bots' ? ' AND 0' : '';
        }

        $column = $alias === '' ? $column : $alias . '.' . $column;

        if ($type === 'bots') {
            return ' AND ' . $column . ' = 1';
        }

        if ($type === 'players') {
            return ' AND (' . $column . ' = 0 OR ' . $column . ' IS NULL)';
        }

        return '';
    }

    /**
     * Referme les connexions ouvertes d'un compte.
     *
     * Appelée quand un compte est écarté du jeu (suppression logique) : sans
     * cela, la vue générale continuerait de l'afficher « en ligne ».
     */
    public function closeOpenFor(int $userId, int $moment, string $reason): int
    {
        return $this->preparedExecute(
            "UPDATE {{table}} SET logout_time = ?, last_activity = ?, end_reason = ?
            WHERE id_owner = ? AND logout_time = 0",
            array($moment, $moment, $reason, $userId),
            'sessions'
        );
    }
}
