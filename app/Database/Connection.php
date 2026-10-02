<?php

namespace App\Database;

use App\Core\Debug\DebugBar;
use PDO;
use PDOStatement;

final class Connection
{
    /**
     * Jeu de caractères de la connexion, unique pour tout le jeu.
     *
     * Il part **dans la source** (DSN) : sans lui, MySQL lit les octets UTF-8 du
     * jeu comme du latin1 et les réencode une seconde fois en base (« planète
     * mère » était stocké « planÃ¨te mÃ¨re »). Le schéma est en utf8mb3, la
     * connexion doit l'annoncer.
     */
    public const CHARSET = 'utf8';

    /**
     * Connexion courante, partagée avec le Coeur d'application legacy.
     *
     * Une **seule** connexion pour tout le jeu : le shim `db/mysql.php` appelle
     * `open()`, et les requêtes préparées comme le `doquery()` legacy passent par
     * elle. C'est ce qui fait que le `LOCK TABLES` des flottes s'applique aussi
     * aux écritures des dépôts.
     */
    private static ?PDO $connection = null;

    private static function config(): array
    {
        static $config = null;

        if ($config === null) {
            // xnova_env() est défini dans includes/env.php (chargé par db/mysql.php
            // côté legacy) : on s'assure qu'il est disponible même quand Connection
            // est utilisée avant common.php.
            if (!function_exists('xnova_env')) {
                include_once APP_ROOT . '/includes/env.php';
            }

            $configFile = APP_ROOT . '/configs/config.php';
            $loaded = is_file($configFile) && filesize($configFile) > 0 ? require $configFile : array();
            $database = isset($loaded['global']['database']) ? $loaded['global']['database'] : array();
            $options = isset($database['options']) ? $database['options'] : array();

            $config = array(
                'host' => xnova_env('DB_HOST', isset($options['hostname']) ? $options['hostname'] : 'localhost'),
                'user' => xnova_env('DB_USER', isset($options['username']) ? $options['username'] : ''),
                'password' => xnova_env('DB_PASSWORD', isset($options['password']) ? $options['password'] : ''),
                'database' => xnova_env('DB_NAME', isset($options['database']) ? $options['database'] : ''),
                'prefix' => xnova_env('DB_PREFIX', isset($database['table_prefix']) ? $database['table_prefix'] : ''),
            );
        }

        return $config;
    }

    /** Préfixe des tables du jeu ('' si la configuration est vide). */
    public static function prefix(): string
    {
        $config = self::config();

        return (string) $config['prefix'];
    }

    public static function table(string $name): string
    {
        $config = self::config();

        return $config['prefix'] . $name;
    }

    /**
     * Ouvre la connexion — ou rend celle qui existe déjà.
     *
     * C'est **le seul endroit** qui ouvre une connexion : le shim `db/mysql.php`
     * l'appelle, et l'installateur peut lui donner ses propres identifiants (il se
     * connecte avant que le Coeur d'application n'ait lu `configs/config.php`).
     */
    public static function open(string $host = '', string $user = '', string $password = '', string $database = ''): PDO
    {
        // Le legacy range sa connexion dans ce global : on la reprend plutôt que
        // d'en ouvrir une seconde (deux connexions = deux jeux de caractères, et
        // le `LOCK TABLES` d'un côté ne verrait pas les écritures de l'autre).
        if (isset($GLOBALS['_xnova_mysql_connection']) && $GLOBALS['_xnova_mysql_connection'] instanceof PDO) {
            self::$connection = $GLOBALS['_xnova_mysql_connection'];

            return self::$connection;
        }

        if (self::$connection !== null) {
            return self::$connection;
        }

        if ($host === '') {
            $config = self::config();
            $host = (string) $config['host'];
            $user = (string) $config['user'];
            $password = (string) $config['password'];
            $database = (string) $config['database'];
        }

        $dsn = 'mysql:host=' . $host . ';charset=' . self::CHARSET;

        if ($database !== '') {
            $dsn .= ';dbname=' . $database;
        }

        self::$connection = new PDO($dsn, $user, $password, array(
            // Les erreurs lèvent : un échec silencieux masquait la panne.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Mêmes clés qu'avec mysqli (`MYSQLI_BOTH`) : les appelants lisent
            // indifféremment `$row[0]` et `$row['colonne']`.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH,
            // Émulation : les valeurs restent liées en chaîne, comme les `'s'` de
            // mysqli — les `LIMIT ?` du jeu continuent de passer.
            PDO::ATTR_EMULATE_PREPARES => true,
        ));

        $GLOBALS['_xnova_mysql_connection'] = self::$connection;

        return self::$connection;
    }

    /**
     * Bascule la connexion sur une autre base (l'installateur, qui vient de la
     * créer). Un nom de base ne peut pas être un paramètre lié : sa **forme** est
     * vérifiée avant l'interpolation.
     */
    public static function useDatabase(string $database): bool
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            return false;
        }

        self::pdo()->exec('USE `' . $database . '`');

        return true;
    }

    public static function pdo(): PDO
    {
        return self::open();
    }

    public static function prepare(string $sql, string $table = ''): PDOStatement
    {
        return self::pdo()->prepare(str_replace('{{table}}', self::table($table), $sql));
    }

    public static function preparedFetchAll(string $sql, array $params = array(), string $table = ''): array
    {
        $statement = self::executePrepared($sql, $params, $table);
        $rows = array();

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function preparedFetchOne(string $sql, array $params = array(), string $table = '')
    {
        $row = self::executePrepared($sql, $params, $table)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? false : $row;
    }

    public static function preparedExecute(string $sql, array $params = array(), string $table = ''): int
    {
        return self::executePrepared($sql, $params, $table)->rowCount();
    }

    public static function preparedInsertId(string $sql, array $params = array(), string $table = ''): int
    {
        self::executePrepared($sql, $params, $table);

        return (int) self::pdo()->lastInsertId();
    }

    private static function executePrepared(string $sql, array $params, string $table): PDOStatement
    {
        // Mesure réservée à la barre de debug : enabled() est mis en cache, le
        // surcoût est donc nul quand DEBUG_BAR n'est pas actif.
        $started = DebugBar::enabled() ? microtime(true) : 0.0;

        $statement = self::prepare($sql, $table);

        // Le type du paramètre décide de la liaison, comme le faisaient les `'i'`
        // / `'d'` / `'s'` de mysqli : un entier **non cité** est indispensable à un
        // `LIMIT ?` (PDO, en émulation, écrirait `LIMIT '2'` pour une chaîne, et
        // MySQL refuse une chaîne citée à cet endroit).
        foreach (array_values($params) as $index => $value) {
            if ($value === null) {
                $statement->bindValue($index + 1, null, PDO::PARAM_NULL);

                continue;
            }

            if (is_bool($value)) {
                $statement->bindValue($index + 1, (int) $value, PDO::PARAM_INT);

                continue;
            }

            if (is_int($value)) {
                $statement->bindValue($index + 1, $value, PDO::PARAM_INT);

                continue;
            }

            $statement->bindValue($index + 1, is_scalar($value) ? (string) $value : '', PDO::PARAM_STR);
        }

        $statement->execute();

        if ($started > 0.0) {
            DebugBar::logQuery(
                str_replace('{{table}}', self::table($table), $sql),
                (microtime(true) - $started) * 1000,
                $table,
                $params
            );
        }

        return $statement;
    }

    public static function query(string $sql, string $table = '')
    {
        return doquery($sql, $table);
    }

    public static function fetchOne(string $sql, string $table = '')
    {
        $row = doquery($sql, $table, true);

        return $row === null ? false : $row;
    }

    public static function fetchAll(string $sql, string $table = ''): array
    {
        $statement = doquery($sql, $table);
        $rows = array();

        while (($row = $statement->fetch(PDO::FETCH_BOTH)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function fetchObject(string $sql, string $table = '')
    {
        $row = doquery($sql, $table)->fetch(PDO::FETCH_OBJ);

        return $row === false ? false : $row;
    }

    public static function escape(string $value): string
    {
        // `quote()` ajoute les guillemets : on les retire, comme le faisait
        // `mysql_real_escape_string()`. Les guillemets internes, eux, restent
        // échappés.
        $quoted = self::pdo()->quote($value);

        return substr($quoted, 1, -1);
    }

    public static function escapeInt(mixed $value): int
    {
        return (int) $value;
    }
}
