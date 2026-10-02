<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\Modules;
use RuntimeException;

/**
 * Exécuteur de migrations.
 *
 * Chaque fichier de `db/migrations/` retourne une liste d'instructions, sous
 * deux formes acceptées :
 *   - des paires [table, SQL] — le marqueur {{table}} est remplacé par la table
 *     préfixée (forme du schéma initial, reprise de l'ancien databaseinfos.php) ;
 *   - des chaînes SQL contenant {{prefix}} (forme des anciens scripts de mise à
 *     niveau).
 *
 * L'état est suivi dans la table `<prefix>migrations` (nom + date d'application).
 *
 * Réversibilité : MySQL valide chaque DDL immédiatement, aucune transaction ne
 * peut l'annuler. Un fichier de migration peut donc fournir un `down` explicite
 * (clé `down` du tableau retourné, `null` si la migration n'est pas
 * réversible) : `rollback` le joue, mais seulement avec `--force`, car annuler le
 * schéma initial supprime des tables. Les migrations qui ne contiennent que du
 * DML sont enveloppées dans une transaction — attention, le schéma du jeu est en
 * MyISAM, qui ignore les transactions : la protection ne vaut que pour de
 * futures tables InnoDB.
 *
 * Une migration interrompue reste à moitié appliquée, mais les erreurs « existe
 * déjà » sont tolérées et signalées, ce qui permet de rejouer une migration sans
 * casser une base déjà à jour.
 */
final class Migrator
{
    /** Table de suivi (sans préfixe). */
    public const TABLE = 'migrations';

    /**
     * Erreurs MySQL tolérées car un rejeu les provoque naturellement :
     * 1050 table déjà présente, 1060 colonne déjà présente, 1062 entrée en
     * doublon, 1091 objet absent. Chaque instruction ignorée est signalée.
     */
    public const TOLERATED_ERRNOS = array(1050, 1060, 1062, 1091);

    /** @var list<string> instructions ignorées lors du dernier run */
    private array $tolerated = array();

    /**
     * Les migrations des **modules** ne sont prises que si le dossier du Coeur d'application est
     * celui du jeu : un dossier imposé (test, jeu d'essai) décrit exactement ce
     * qu'on lui donne.
     */
    private readonly bool $withModules;

    public function __construct(
        private readonly string $directory = '',
    ) {
        $this->withModules = $directory === '';
    }

    /** Dossier des migrations (db/migrations par défaut). */
    public function directory(): string
    {
        return $this->directory !== '' ? $this->directory : dirname(__DIR__, 2) . '/db/migrations';
    }

    /**
     * Migrations disponibles, triées par nom de fichier.
     *
     * Le schéma du **Coeur d'application** d'abord (`db/migrations/`), puis celui de chaque
     * **module** (`modules/<nom>/db/migrations/`, dans l'ordre du catalogue) : un
     * module peut donc compter sur les tables du jeu. Les migrations d'un module
     * portent un nom **qualifié** (`extracteurs/001_debris_deuterium`) : leurs
     * numéros vivent chez elles, sans collision avec ceux du Coeur d'application.
     *
     * @return array<string, string> nom logique => chemin du fichier
     */
    public function available(): array
    {
        $migrations = array();

        foreach ($this->filesIn($this->directory()) as $file) {
            $migrations[basename($file, '.php')] = $file;
        }

        if (!$this->withModules) {
            return $migrations;
        }

        foreach (Modules::migrationDirectories() as $name => $directory) {
            foreach ($this->filesIn($directory) as $file) {
                $migrations[$name . '/' . basename($file, '.php')] = $file;
            }
        }

        return $migrations;
    }

    /**
     * Fichiers d'un dossier de migrations, triés par nom.
     *
     * @return list<string>
     */
    private function filesIn(string $directory): array
    {
        $files = glob(rtrim($directory, '/\\') . '/*.php') ?: array();
        sort($files, SORT_STRING);

        return array_values($files);
    }

    /**
     * Migrations restant à appliquer. Fonction pure.
     *
     * @param array<string, string> $available
     * @param list<string>          $applied
     * @return array<string, string>
     */
    public static function plan(array $available, array $applied): array
    {
        return array_diff_key($available, array_flip($applied));
    }

    /**
     * Clés SQL qui rendent une migration non transactionnelle.
     */
    public const DDL_KEYWORDS = array('create', 'alter', 'drop', 'truncate', 'rename', 'grant', 'revoke', 'lock', 'unlock');

    /**
     * Migration complète : `up` (obligatoire) et `down` (null si irréversible).
     *
     * Formes acceptées : un tableau qui liste directement les instructions (up
     * implicite), ou un tableau à clés `up` / `down`.
     *
     * @return array{up: list<array{0: string, 1: string}>, down: ?list<array{0: string, 1: string}>}
     */
    public function migrationOf(string $file): array
    {
        $data = include $file;

        if (!is_array($data)) {
            throw new RuntimeException('Migration illisible : ' . $file);
        }

        if (array_key_exists('up', $data)) {
            $up = $data['up'];
            $down = $data['down'] ?? null;
        } else {
            $up = $data;
            $down = null;
        }

        if (!is_array($up)) {
            throw new RuntimeException('Instructions `up` invalides : ' . $file);
        }

        return array(
            'up' => $this->normalize($up),
            'down' => $down === null ? null : $this->normalize((array) $down),
        );
    }

    /**
     * Instructions d'une migration, normalisées en paires [table, SQL].
     *
     * @return list<array{0: string, 1: string}>
     */
    public function statementsOf(string $file): array
    {
        return $this->migrationOf($file)['up'];
    }

    /**
     * Normalise une liste d'instructions : une entrée chaîne est une instruction
     * globale où seul {{prefix}} est remplacé.
     *
     * @param array<array-key, mixed> $statements
     * @return list<array{0: string, 1: string}>
     */
    private function normalize(array $statements): array
    {
        $normalized = array();

        foreach ($statements as $statement) {
            if (is_array($statement)) {
                $normalized[] = array((string) ($statement[0] ?? ''), (string) ($statement[1] ?? ''));

                continue;
            }

            $normalized[] = array('', str_replace('{{prefix}}', Connection::prefix(), (string) $statement));
        }

        return $normalized;
    }

    /**
     * Une migration qui ne fait que du DML peut être enveloppée dans une
     * transaction ; le DDL, lui, est validé immédiatement par MySQL.
     * Fonction pure.
     *
     * @param list<array{0: string, 1: string}> $statements
     */
    public static function isDmlOnly(array $statements): bool
    {
        foreach ($statements as $statement) {
            $parts = preg_split('/\s+/', trim((string) ($statement[1] ?? '')), 2);
            $keyword = strtolower($parts[0] ?? '');

            if (in_array($keyword, self::DDL_KEYWORDS, true)) {
                return false;
            }
        }

        return true;
    }

    /** Instructions ignorées parce qu'elles étaient déjà appliquées. */
    public function tolerated(): array
    {
        return $this->tolerated;
    }

    /**
     * Applique les migrations en attente.
     *
     * `$dryRun` se contente de lister ce qui serait joué. Une migration qui ne
     * contient que du DML est enveloppée dans une transaction (voir la limite
     * MyISAM en tête de classe).
     *
     * @return list<string> noms appliqués (ou à appliquer en simulation)
     */
    public function run(bool $dryRun = false): array
    {
        $this->ensureTable();

        $pending = self::plan($this->available(), $this->applied());
        $done = array();

        foreach ($pending as $name => $file) {
            $this->tolerated = array();

            if ($dryRun) {
                $done[] = (string) $name;

                continue;
            }

            $up = $this->migrationOf($file)['up'];
            $transaction = self::isDmlOnly($up);

            if ($transaction) {
                $this->execute('', 'START TRANSACTION');
            }

            try {
                foreach ($up as $statement) {
                    $this->execute($statement[0], $statement[1]);
                }

                if ($transaction) {
                    $this->execute('', 'COMMIT');
                }
            } catch (RuntimeException $exception) {
                if ($transaction) {
                    $this->execute('', 'ROLLBACK');
                }

                throw $exception;
            }

            $this->record((string) $name);
            $done[] = (string) $name;
        }

        return $done;
    }

    /**
     * Annule les `$steps` dernières migrations appliquées, de la plus récente à
     * la plus ancienne. Sans `$force`, se contente de lister ce qui serait
     * annulé : annuler le schéma initial supprime des tables, donc des données.
     *
     * @return list<string> noms annulés (ou à annuler en simulation)
     */
    public function rollback(int $steps = 1, bool $force = false): array
    {
        $this->ensureTable();

        $targets = array_slice(array_reverse($this->applied()), 0, max(1, $steps));
        $available = $this->available();
        $done = array();

        foreach ($targets as $name) {
            $file = $available[$name] ?? '';

            if ($file === '') {
                throw new RuntimeException('Migration absente du dépôt, annulation impossible : ' . $name);
            }

            $down = $this->migrationOf($file)['down'];

            if ($down === null) {
                throw new RuntimeException('Migration non réversible, aucune instruction `down` : ' . $name);
            }

            if ($force) {
                $this->tolerated = array();

                foreach ($down as $statement) {
                    $this->execute($statement[0], $statement[1]);
                }

                $this->forget($name);
            }

            $done[] = $name;
        }

        return $done;
    }

    /**
     * Marque des migrations comme appliquées sans les exécuter : à utiliser sur
     * une base déjà en place, créée avant l'arrivée des migrations.
     *
     * `$only` restreint le marquage à certaines migrations (une base existante
     * possède déjà le schéma initial : on ne marque que celui-ci, puis `run()`
     * applique les mises à niveau).
     *
     * @param list<string>|null $only
     * @return list<string> noms marqués
     */
    public function baseline(?array $only = null): array
    {
        $this->ensureTable();

        $pending = self::plan($this->available(), $this->applied());

        if ($only !== null) {
            $pending = array_intersect_key($pending, array_flip($only));
        }

        $marked = array();

        foreach (array_keys($pending) as $name) {
            $this->record((string) $name);
            $marked[] = (string) $name;
        }

        return $marked;
    }

    /** Migrations déjà appliquées, dans l'ordre d'enregistrement. */
    public function applied(): array
    {
        $this->ensureTable();

        $rows = $this->query('SELECT name FROM `' . Connection::table(self::TABLE) . '` ORDER BY applied_at ASC, name ASC');
        $names = array();

        foreach ($rows as $row) {
            $names[] = (string) $row['name'];
        }

        return $names;
    }

    private function ensureTable(): void
    {
        $this->execute('', 'CREATE TABLE IF NOT EXISTS `' . Connection::table(self::TABLE) . '` ('
            . ' `name` varchar(120) NOT NULL,'
            . ' `applied_at` int(11) NOT NULL default 0,'
            . ' PRIMARY KEY (`name`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci');
    }

    private function record(string $name): void
    {
        Connection::preparedExecute(
            'INSERT INTO `' . Connection::table(self::TABLE) . '` (name, applied_at) VALUES (?, ?)',
            array($name, time())
        );
    }

    /** Retire une migration du suivi (après annulation). */
    private function forget(string $name): void
    {
        Connection::preparedExecute(
            'DELETE FROM `' . Connection::table(self::TABLE) . '` WHERE name = ?',
            array($name)
        );
    }

    /** Exécute une instruction : la table (si fournie) remplace {{table}}. */
    private function execute(string $table, string $sql): void
    {
        if ($table !== '') {
            $sql = str_replace('{{table}}', Connection::table($table), $sql);
        }

        // PDO lève sur erreur (`ERRMODE_EXCEPTION`) : on l'attrape pour appliquer la
        // même tolérance qu'avant — un objet déjà présent n'est pas un échec.
        try {
            Connection::pdo()->query($sql);
            $failed = false;
            $errno = 0;
            $error = '';
        } catch (\PDOException $exception) {
            $failed = true;
            $errno = self::errno($exception);
            $error = $exception->getMessage();
        }

        if (!$failed) {
            return;
        }

        if (in_array($errno, self::TOLERATED_ERRNOS, true)) {
            // SQL multiligne : on aplatit l'extrait pour garder une ligne par message.
            $excerpt = substr(str_replace(array("\r\n", "\n", "\t"), ' ', $sql), 0, 60);
            $this->tolerated[] = $error . ' — ' . $excerpt;

            return;
        }

        throw new RuntimeException('Migration en échec (' . $errno . ') : ' . $error);
    }

    /**
     * Code d'erreur MySQL d'une exception PDO (1060 « duplicate column »…).
     *
     * `getCode()` rend le **SQLSTATE** (« 42S21 », « 42S02 »…) et non le code du
     * pilote : la tolérance ci-dessus ne s'appliquait donc à rien, et une colonne
     * déjà présente faisait échouer une migration pourtant faite pour être rejouée
     * (un module qui ajoute sa colonne à une table du jeu la trouve déjà là quand
     * une installation neuve l'a créée). Le code du pilote vit dans `errorInfo[1]`.
     * Fonction pure : testée sans base.
     */
    public static function errno(\PDOException $exception): int
    {
        return (int) ($exception->errorInfo[1] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    private function query(string $sql): array
    {
        try {
            $statement = Connection::pdo()->query($sql);
        } catch (\PDOException $exception) {
            return array();
        }

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : array();
    }
}
