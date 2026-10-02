<?php

namespace App\Repositories;

use App\Core\Flags;
use App\Entities\User;

class UserRepository extends BaseRepository
{
    public function toUser(array|false $row): ?User
    {
        return $row === false || $row === null ? null : User::fromRow($row);
    }
    public function findGameOps(): array
    {
        return $this->preparedFetchAll(
            "SELECT username, email, authlevel FROM {{table}} WHERE authlevel != '0' ORDER BY authlevel DESC",
            array(),
            'users'
        );
    }

    public function attemptLogin(string $username, string $password): array|false
    {
        $row = $this->preparedFetchOne(
            "SELECT id, username, banaday, password FROM {{table}} WHERE username = ? LIMIT 1",
            array($username),
            'users'
        );

        if ($row === false) {
            return false;
        }

        $salt = substr(md5((string) mt_rand()), 0, 4);

        return array(
            'id' => $row['id'],
            'username' => $row['username'],
            'banaday' => $row['banaday'],
            'login_success' => (md5($password) === $row['password']) ? 1 : 0,
            'login_rememberme' => $salt . sha1($row['username'] . $row['password'] . $salt),
        );
    }

    public function clearBan(string $username): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET banaday = '0', bana = '0', vacation_mode = '0' WHERE username = ? LIMIT 1",
            array($username),
            'users'
        );
        $this->preparedExecute(
            'DELETE FROM {{table}} WHERE who = ?',
            array(BannedRepository::key($username)),
            'banned'
        );
    }

    /** Pose un bannissement : le drapeau et la date d'échéance du compte. */
    public function ban(string $username, int $until): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET bana = '1', banaday = ? WHERE username = ? LIMIT 1",
            array((string) $until, $username),
            'users'
        );
    }

    /**
     * Comptes dont le bannissement est arrivé à échéance.
     *
     * @return list<array<string, mixed>>
     */
    public function findExpiredBans(): array
    {
        return $this->preparedFetchAll(
            "SELECT id, username FROM {{table}} WHERE bana = '1' AND banaday > 0 AND banaday <= ?",
            array((string) time()),
            'users'
        );
    }

    public function touchOnline(int $id): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET onlinetime = UNIX_TIMESTAMP() WHERE id = ?",
            array($id),
            'users'
        );
    }

    /**
     * Identifiants de tous les comptes (message à tous les joueurs).
     *
     * @return list<array<string, mixed>>
     */
    public function findAllIds(): array
    {
        return $this->fetchAll('SELECT id FROM {{table}}', 'users');
    }

    public function countPlayers(): array|false
    {
        return $this->fetchOne('SELECT COUNT(DISTINCT users.id) AS `players` FROM {{table}} AS users WHERE users.authlevel < 3', 'users');
    }

    public function findLastRegisteredUsername(): array|false
    {
        return $this->fetchOne('SELECT users.`username` FROM {{table}} AS users ORDER BY `register_time` DESC LIMIT 1', 'users');
    }

    /**
     * Joueurs connectés depuis un instant donné (panneau d'administration).
     *
     * Le tri est borné à une liste blanche : la page admin triait sur la colonne
     * demandée dans l'URL (`ORDER BY \`{$_GET['type']}\``), donc injectable.
     *
     * @return list<array<string, mixed>>
     */
    public function findOnlineSince(int $timestamp, string $sort = 'id'): array
    {
        $columns = array('id', 'username', 'user_lastip', 'onlinetime', 'current_page', 'ally_name');

        if (!in_array($sort, $columns, true)) {
            $sort = 'id';
        }

        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE `onlinetime` >= ? ORDER BY `" . $sort . "` ASC",
            array($timestamp),
            'users'
        );
    }

    public function countOnlinePlayers(): array|false
    {
        return $this->fetchOne("SELECT COUNT(DISTINCT id) AS `onlinenow` FROM {{table}} AS users WHERE `onlinetime` > (UNIX_TIMESTAMP()-900) AND users.authlevel < 3", 'users');
    }

    /**
     * Tous les comptes, pour la liste du panneau d'administration.
     *
     * Le tri est borné à une liste blanche : la page triait sur la colonne
     * demandée dans l'URL (`ORDER BY \`{$_GET['type']}\``), donc injectable.
     *
     * @return list<array<string, mixed>>
     */
    /** Colonnes de tri de la liste des joueurs (liste blanche : rien de l'URL n'entre en SQL). */
    public const SORTS = array('id', 'username', 'email', 'ip_at_reg', 'user_lastip', 'register_time', 'onlinetime', 'bana');

    public function findAllSorted(string $sort = 'id', int $limit = 0, int $offset = 0, string $order = 'asc'): array
    {
        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'id';
        }

        // Le tri et le bornage ne peuvent pas être des paramètres liés : la colonne vient de la
        // liste blanche, la direction d'une valeur fermée, les bornes sont des entiers typés.
        $direction = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        $sql = "SELECT * FROM {{table}} ORDER BY `" . $sort . '` ' . $direction;

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) max(0, $offset);
        }

        return $this->fetchAll($sql, 'users');
    }

    /** Nombre total de comptes (toutes les lignes de la liste des joueurs). */
    public function countAll(): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS `all` FROM {{table}}', 'users');

        return is_array($row) ? (int) ($row['all'] ?? 0) : 0;
    }

    /**
     * Premier compte dont le pseudo contient `$pattern` (recherche admin).
     *
     * @return array<string, mixed>|false
     */
    public function findFirstByUsernameLike(string $pattern): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE `username` LIKE ? ORDER BY `id` ASC LIMIT 1",
            array('%' . $pattern . '%'),
            'users'
        );
    }

    /**
     * Comptes partageant une IP (recherche admin), plafonnés.
     *
     * @return list<array<string, mixed>>
     */
    public function findByLastIp(string $ip, int $limit = 10): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE `user_lastip` = ? LIMIT " . (int) max(1, $limit),
            array($ip),
            'users'
        );
    }

    /** Change le niveau d'accès d'un compte (recherche par pseudo exact). */
    public function updateAuthlevel(string $username, int $level): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `authlevel` = ? WHERE `username` = ?",
            array((string) $level, $username),
            'users'
        );
    }

    /**
     * Pseudos de plusieurs comptes, en une requête.
     *
     * La liste des messages de l'administration affichait le destinataire par une
     * requête par ligne (`SELECT username … WHERE id = …`) : ici, un seul aller.
     *
     * @param list<int> $ids
     *
     * @return array<int, string> identifiant du compte → pseudo
     */
    public function findUsernamesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));

        if ($ids === array()) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->preparedFetchAll(
            'SELECT id, username FROM {{table}} WHERE id IN (' . $placeholders . ')',
            array_map('strval', $ids),
            'users'
        );

        $names = array();

        foreach ($rows as $row) {
            $names[(int) $row['id']] = (string) $row['username'];
        }

        return $names;
    }

    public function findFullById(int $id): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE id = ?",
            array($id),
            'users'
        );
    }

    public function findByUsername(string $username): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE username = ? LIMIT 1",
            array($username),
            'users'
        );
    }

    public function findByEmail(string $email): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE email = ? LIMIT 1",
            array($email),
            'users'
        );
    }

    public function findEmailAndUsernameByName(string $username): array|false
    {
        return $this->preparedFetchOne(
            "SELECT email, username FROM {{table}} WHERE username = ? LIMIT 1",
            array($username),
            'users'
        );
    }

    public function findEmailAndUsernameByEmail(string $email): array|false
    {
        return $this->preparedFetchOne(
            "SELECT email, username FROM {{table}} WHERE email = ? LIMIT 1",
            array($email),
            'users'
        );
    }

    public function updatePasswordForUsername(string $password, string $username): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET password = ? WHERE username = ?",
            array($password, $username),
            'users'
        );
    }

    /**
     * Pseudo (nom de connexion) d'un compte, ou `''` s'il n'existe pas.
     *
     * Une seule implèmentation : le journal la lisait dans son propre dépôt
     * (`ActionRepository::username()`, pour dire d'où venait un renommage) et la page
     * des flottes hostiles la lisait en SQL legacy — deux copies de la même lecture.
     */
    public function username(int $userId): string
    {
        $row = $this->preparedFetchOne(
            'SELECT `username` FROM {{table}} WHERE `id` = ?',
            array($userId),
            'users'
        );

        return (string) ($row['username'] ?? '');
    }

    public function usernameExists(string $username): array|false
    {
        return $this->preparedFetchOne(
            "SELECT username FROM {{table}} WHERE username = ? LIMIT 1",
            array($username),
            'users'
        );
    }

    public function emailExists(string $email): array|false
    {
        return $this->preparedFetchOne(
            "SELECT email FROM {{table}} WHERE email = ? LIMIT 1",
            array($email),
            'users'
        );
    }

    public function usernameTaken(string $username): array|false
    {
        return $this->preparedFetchOne(
            'SELECT id FROM {{table}} WHERE `username` = ? LIMIT 1',
            array($username),
            'users'
        );
    }

    public function insertRegistration(string $username, string $email, string $sex, string $ip, string $md5Password): void
    {
        $this->preparedExecute(
            'INSERT INTO {{table}} (`username`, `email`, `email_2`, `sex`, `ip_at_reg`, `id_planet`, `register_time`, `password`)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                strip_tags($username),
                $email,
                $email,
                $sex,
                $ip,
                '0',
                (string) time(),
                $md5Password,
            ),
            'users'
        );
    }

    public function findIdByUsername(string $username): array|false
    {
        return $this->preparedFetchOne(
            'SELECT `id` FROM {{table}} WHERE `username` = ? LIMIT 1',
            array($username),
            'users'
        );
    }

    public function findPlanetIdByOwner(int $ownerId): array|false
    {
        return $this->preparedFetchOne(
            'SELECT `id` FROM {{table}} WHERE `id_owner` = ? AND (`flags` & ?) = 0 LIMIT 1',
            array($ownerId, (string) Flags::DELETED),
            'planets'
        );
    }

    /**
     * Pose la planète mère d'un compte : la planète, sa position et la vue courante.
     *
     * `current_planet` reçoit le même planète que `id_planet` — les cinq entiers étaient
     * concaténés, ils passent désormais en paramètres liés.
     */
    public function updateHomePlanet(int $userId, int $planetId, int $galaxy, int $system, int $planet): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `id_planet` = ?, `current_planet` = ?, `galaxy` = ?, `system` = ?, `planet` = ?'
                . ' WHERE `id` = ? LIMIT 1',
            array($planetId, $planetId, $galaxy, $system, $planet, $userId),
            'users'
        );
    }

    public function incrementUsersAmount(): void
    {
        $this->query("UPDATE {{table}} SET `config_value` = `config_value` + '1' WHERE `config_name` = 'users_amount' LIMIT 1;", 'config');
    }

    public function updatePassword(int $userId, string $md5Password): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `password` = ? WHERE `id` = ? LIMIT 1',
            array($md5Password, $userId),
            'users'
        );
    }

    public function updateUsername(int $userId, string $username): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `username` = ? WHERE `id` = ? LIMIT 1',
            array($username, $userId),
            'users'
        );
    }

    public function findByRankSort(int $userId): array|false
    {
        return $this->preparedFetchOne(
            "SELECT `total_rank` FROM {{table}} WHERE `stat_code` = '1' AND `stat_type` = '1' AND `id_owner` = ?",
            array($userId),
            'statpoints'
        );
    }

    public function setVacation(int $userId, int $until): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `vacation_mode` = '1', `vacation_until` = ? WHERE `id` = ? LIMIT 1",
            array($until, $userId),
            'users'
        );
    }

    public function clearVacation(int $userId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `vacation_mode` = '0', `vacation_until` = '0' WHERE `id` = ? LIMIT 1",
            array($userId),
            'users'
        );
    }

    public function countFleetsByOwner(int $ownerId): array|false
    {
        return $this->preparedFetchOne(
            'SELECT COUNT(fleet_owner) AS `actcnt` FROM {{table}} WHERE `fleet_owner` = ? AND (`flags` & ?) = 0',
            array($ownerId, (string) Flags::DELETED),
            'fleets'
        );
    }

    public function countBuildingByOwner(int $ownerId): array|false
    {
        return $this->preparedFetchOne(
            'SELECT COUNT(id_owner) AS `building` FROM {{table}} WHERE `id_owner` = ? and `b_building`!=0'
            . ' AND (`flags` & ?) = 0',
            array($ownerId, (string) Flags::DELETED),
            'planets'
        );
    }

    public function countTechInProgress(int $userId): array|false
    {
        return $this->preparedFetchOne(
            'SELECT COUNT(id) AS `tech` FROM {{table}} WHERE `id` = ? and `b_tech_planet`!=0',
            array($userId),
            'users'
        );
    }

    public function countIncomingFleets(int $ownerId): array|false
    {
        return $this->preparedFetchOne(
            // `fleet_taget_owner` : la faute de frappe est dans la colonne, pas ici.
            'SELECT COUNT(fleet_taget_owner) AS `attack` FROM {{table}} WHERE `fleet_taget_owner` = ?'
            . ' AND (`flags` & ?) = 0',
            array($ownerId, (string) Flags::DELETED),
            'fleets'
        );
    }

    /**
     * Enregistre les réglages du compte.
     *
     * Le dépôt n'écrit que les colonnes qu'on lui **donne** : une colonne qu'un module
     * possède arrive par son propre tableau (`UserSettingsService::settings()`), donc le
     * Coeur d'application n'en nomme aucune. La forme du nom est vérifiée — jamais une valeur de
     * l'utilisateur dans le SQL — et les valeurs passent en paramètres liés.
     *
     * @param array<string, mixed> $settings colonne => valeur
     */
    public function updateSettingsFull(array $settings, int $userId): void
    {
        $sets = array();
        $params = array();

        foreach ($settings as $column => $value) {
            if (!is_string($column) || !self::isColumnName($column)) {
                continue;
            }

            $sets[] = '`' . $column . '` = ?';
            $params[] = is_scalar($value) ? (string) $value : '';
        }

        if ($sets === array()) {
            return;
        }

        $params[] = $userId;

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . implode(', ', $sets) . ' WHERE `id` = ? LIMIT 1',
            $params,
            'users'
        );
    }

    public function resetCurrentPlanet(int $userId): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `current_planet` = `id_planet` WHERE `id` = ? LIMIT 1',
            array($userId),
            'users'
        );
    }

    /**
     * Ramène sur sa planète mère tout compte dont la vue était posée sur une planète
     * qui vient d'être détruit (une lune, par exemple).
     *
     * Un compte resté sur une lune détruite continuerait de jouer dessus : la ligne
     * existe toujours, seul son drapeau dit qu'elle n'est plus.
     *
     * @return int nombre de comptes déplacés
     */
    public function relocateFromPlanet(int $planetId): int
    {
        return $this->preparedExecute(
            'UPDATE {{table}} SET current_planet = id_planet WHERE current_planet = ?',
            array($planetId),
            'users'
        );
    }

    public function findLastRegistered(): array|false
    {
        return $this->fetchOne('SELECT username FROM {{table}} ORDER BY register_time DESC', 'users');
    }

    /**
     * Comptes connectés depuis un instant donné.
     *
     * La colonne est **nommée** (`AS \`online\``) : une requête préparée ne rend que des clés
     * associatives, l'index numérique `[0]` du legacy n'existe plus (les appelants lisent
     * `['online']`).
     */
    public function countOnlineSince(int $timestamp): array|false
    {
        return $this->preparedFetchOne(
            'SELECT COUNT(DISTINCT(id)) AS `online` FROM {{table}} WHERE `onlinetime` > ?',
            array($timestamp),
            'users'
        );
    }

    /** Même comptage, borne incluse (l'ancien code construisait `>=` par concaténation). */
    public function countOnlineSinceInclusive(int $timestamp): array|false
    {
        return $this->preparedFetchOne(
            'SELECT COUNT(*) AS `online` FROM {{table}} WHERE `onlinetime` >= ?',
            array($timestamp),
            'users'
        );
    }

    public function incrementUnread(int $ownerId, int $type): void
    {
        $messfields = $GLOBALS['messfields'] ?? array();

        if (!isset($messfields[$type], $messfields[100])) {
            return;
        }

        // Un nom de colonne ne peut pas être un paramètre lié : sa forme est vérifiée (les deux
        // noms viennent de la table fixe `$GLOBALS['messfields']`, jamais d'une saisie).
        $column = (string) $messfields[$type];
        $total = (string) $messfields[100];

        foreach (array($column, $total) as $name) {
            if (!self::isColumnName($name)) {
                return;
            }
        }

        $this->preparedExecute(
            'UPDATE {{table}} SET `' . $column . '` = `' . $column . '` + 1,'
                . ' `' . $total . '` = `' . $total . '` + 1 WHERE `id` = ?',
            array($ownerId),
            'users'
        );
    }

    /**
     * Badge « nouveau message » seul (ex rak.php) : une attaque de missiles
     * n'incrémentait pas le compteur du type de message, on conserve ce
     * comportement d'origine.
     */
    public function incrementNewMessage(int $ownerId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET new_message = new_message + 1 WHERE id = ?",
            array($ownerId),
            'users'
        );
    }

    /**
     * Une colonne numérique du compte, augmentée d'un montant.
     *
     * Un nom de colonne ne peut pas être un paramètre lié : sa forme est donc vérifiée
     * (même garde que `incrementUnread()`), et le montant part lié.
     */
    public function incrementField(int $userId, string $column, int $amount = 1): void
    {
        if (!self::isColumnName($column)) {
            return;
        }

        $this->preparedExecute(
            'UPDATE {{table}} SET `' . $column . '` = `' . $column . '` + ? WHERE `id` = ? LIMIT 1',
            array((string) $amount, $userId),
            'users'
        );
    }

    /**
     * Point de raid de l'attaquant : une récompense de **module**.
     *
     * Le Coeur de l'application n'en attribue aucun — l'expérience appartient au module des
     * officiers, qui surcharge cette méthode et y écrit la sienne (`incrementField()`). Comme
     * partout, le dépôt est donc **résolu** : l'appelant le construit par
     * `ModuleService::instance()`, sans quoi la colonne ne serait jamais écrite.
     */
    public function rewardRaid(int $userId): void
    {
    }

    /**
     * Les trois niveaux de recherche de combat, lus d'un coup.
     *
     * Le moteur de combat demande ces niveaux au **compte**, jamais à la planète.
     *
     * @return array<string, mixed>
     */
    public function findTechnologies(int $userId): array
    {
        $row = $this->preparedFetchOne(
            'SELECT `military_tech`, `defence_tech`, `shield_tech` FROM {{table}} WHERE `id` = ?',
            array($userId),
            'users'
        );

        return $row === false ? array() : $row;
    }

    /**
     * Les trois compteurs de raids du compte (`raids`, `raidswin`, `raidsloose`).
     *
     * @return array<string, mixed>
     */
    public function findRaidCounters(int $userId): array
    {
        $row = $this->preparedFetchOne(
            'SELECT `raids`, `raidswin`, `raidsloose` FROM {{table}} WHERE `id` = ? LIMIT 1',
            array($userId),
            'users'
        );

        return $row === false ? array() : $row;
    }

    /**
     * Écrit les trois compteurs de raids **ensemble**.
     *
     * Le raid perdu écrivait `raidsloose` dans la colonne `raidswin` : « Raids Perdus »
     * restait à zéro et « Raids Gagnés » montait à chaque défaite. Les trois colonnes se
     * posent donc en une fois, dans l'ordre du legacy.
     *
     * @param array<string, int|string> $counters
     */
    public function setRaidCounters(int $userId, array $counters): void
    {
        $sets   = array();
        $params = array();

        foreach (array('raidswin', 'raidsloose', 'raids') as $column) {
            if (!array_key_exists($column, $counters)) {
                continue;
            }

            $sets[]   = '`' . $column . '` = ?';
            $params[] = is_scalar($counters[$column]) ? (string) $counters[$column] : '0';
        }

        if ($sets === array()) {
            return;
        }

        $params[] = $userId;

        $this->preparedExecute(
            'UPDATE {{table}} SET ' . implode(', ', $sets) . ' WHERE `id` = ? LIMIT 1',
            $params,
            'users'
        );
    }

    public function setCurrentPlanet(int $planetId, int $userId): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `current_planet` = ? WHERE `id` = ? LIMIT 1',
            array($planetId, $userId),
            'users'
        );
    }

    public function updateFleetShortcut(int $userId, string $shortcut): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `fleet_shortcut` = ? WHERE `id` = ? LIMIT 1',
            array($shortcut, $userId),
            'users'
        );
    }

    /**
     * Écrit une colonne numérique du compte (niveau de recherche).
     *
     * Un nom de colonne ne peut pas être un paramètre lié : sa **forme** est vérifiée
     * (`BaseRepository::isColumnName()`), et la valeur passe en paramètre. L'appelant ne
     * fournit que des colonnes qu'il a lues dans la ligne.
     */
    public function setColumn(int $userId, string $column, int $value): void
    {
        if (!self::isColumnName($column)) {
            return;
        }

        $this->preparedExecute(
            'UPDATE {{table}} SET `' . $column . '` = ? WHERE `id` = ?',
            array($value, $userId),
            'users'
        );
    }

    /**
     * Attribue un rôle aux comptes d'un niveau donné qui n'en ont pas encore
     * (semis de l'ACL, `AclService::seed()`).
     *
     * Le compte d'installation est exclu : il est super administrateur par
     * définition, pas par son rôle. Seuls les comptes existants sont touchés.
     */
    public function assignRoleToAuthlevel(int $authlevel, int $roleId, int $exceptId = 0): int
    {
        return $this->preparedExecute(
            "UPDATE {{table}} SET `role_id` = ?"
                . " WHERE `authlevel` = ? AND `role_id` = 0 AND `id` <> ? AND (`flags` & ?) = 0",
            array($roleId, $authlevel, $exceptId, (string) Flags::DELETED),
            'users'
        );
    }

    /**
     * Suppression logique : le compte reste en base, il ne se connecte plus.
     *
     * L'état est le drapeau partagé (`Flags::DELETED`), pas une colonne à lui :
     * un bit décrit un état, la date de la suppression reste `deleted_time`.
     */
    /**
     * Efface **physiquement** un compte (nettoyage anti-triche).
     *
     * Le panneau, lui, supprime **logiquement** (`softDelete()`) : cette méthode n'existe que
     * pour `UserFunctions::DeleteSelectedUser()`, le seul endroit du jeu qui efface pour de bon.
     */
    public function purgeAccount(int $userId): void
    {
        $this->purgeRows('users', 'id', $userId);
    }

    /**
     * Recrée un compte à l'identique (nettoyage anti-triche).
     *
     * L'insertion reprend l'identifiant et les colonnes du compte effacé : c'est la seconde
     * moitié du geste de `ResetThisFuckingCheater()` (`id_planet` repart à 0, la planète est
     * recréée ensuite par `CreateOnePlanetRecord()`).
     *
     * @param array<string, mixed> $user ligne du compte effacé
     */
    public function insertResetAccount(array $user): void
    {
        $this->preparedExecute(
            'INSERT INTO {{table}} SET `id` = ?, `username` = ?, `email` = ?, `email_2` = ?, `sex` = ?,'
            . " `id_planet` = '0', `authlevel` = ?, `dpath` = ?, `galaxy` = ?, `system` = ?,"
            . ' `planet` = ?, `register_time` = ?, `password` = ?',
            array(
                (int) $user['id'],
                (string) $user['username'],
                (string) $user['email'],
                (string) $user['email_2'],
                (string) $user['sex'],
                (int) $user['authlevel'],
                (string) $user['dpath'],
                (int) $user['galaxy'],
                (int) $user['system'],
                (int) $user['planet'],
                (int) $user['register_time'],
                (string) $user['password'],
            ),
            'users'
        );
    }

    public function softDelete(int $userId, int $moment): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `flags` = `flags` | ?, `deleted_time` = ? WHERE id = ?",
            array((string) Flags::DELETED, $moment, $userId),
            'users'
        );
    }

    /** Rétablit un compte supprimé : ses données n'ont jamais bougé. */
    public function restore(int $userId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `flags` = `flags` & ~?, `deleted_time` = '0' WHERE id = ?",
            array((string) Flags::DELETED, $userId),
            'users'
        );
    }
}
