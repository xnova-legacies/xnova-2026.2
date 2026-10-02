<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Sauvegarde de la base en SQL, **sans outil externe**.
 *
 * `mysqldump` vit dans le conteneur `db` (`mysql:5.7`), pas dans l'image de
 * l'application : `php:8.3-apache` n'installe que `unzip` et `pdo_mysql`. Un
 * processus PHP ne peut donc pas l'appeler — et la mise a jour se declenche
 * **depuis** l'application. Le vidage passe donc par la connexion du Coeur
 * d'application (`Connection::open()`), ce qui le fait fonctionner partout,
 * Docker ou non, et evite d'ajouter un client MySQL a l'image pour un seul geste.
 *
 * Le fichier produit est volontairement modeste : `DROP TABLE IF EXISTS`, la
 * definition `SHOW CREATE TABLE` et les `INSERT`. Il se rejoue tel quel :
 * `docker compose exec -T db mysql -uxnova -p... xnova < backups/xnova-....sql`.
 *
 * Seules les fonctions **pures** sont testees unitairement (`tuple()`,
 * `insert()`, `header()`, `fileName()`) : le vidage lui-meme touche MySQL.
 */
class DumpService
{
    /** Colonnes listees dans un `INSERT` avant de le couper. */
    public const ROWS_PER_INSERT = 200;

    /** Taille au-dela de laquelle l'`INSERT` en cours est ecrit (octets). */
    public const CHUNK_BYTES = 262144;

    /**
     * Nom du fichier de sauvegarde. Fonction pure : l'etiquette est nettoyee
     * (jamais de chemin) et l'horodatage reduit a ses quatorze chiffres.
     */
    public static function fileName(string $label, string $date): string
    {
        $stamp = substr(str_pad((string) preg_replace('/[^0-9]/', '', $date), 14, '0'), 0, 14);
        $name  = trim((string) preg_replace('/[^A-Za-z0-9._-]/', '-', $label), '-');

        return 'xnova-' . ($name === '' ? '' : $name . '-') . $stamp . '.sql';
    }

    /**
     * Une ligne de valeurs : `(1, 'texte', NULL)`. Fonction pure — l'echappement
     * est **injecte** (`PDO::quote()` en production, une fonction de test a
     * l'unite), ce qui evite d'avoir besoin d'une base pour verifier le rendu.
     */
    public static function tuple(array $columns, array $row, callable $quote): string
    {
        $values = array();

        foreach ($columns as $column) {
            $values[] = self::value($row[$column] ?? null, $quote);
        }

        return '(' . implode(', ', $values) . ')';
    }

    /**
     * Un `INSERT` groupé a partir de lignes déjà rendues par `tuple()`. Fonction
     * pure : la liste de colonnes est citee et les tuples recopies tels quels.
     */
    public static function insert(string $table, array $columns, array $tuples): string
    {
        if ($columns === array() || $tuples === array()) {
            return '';
        }

        $list = array();

        foreach ($columns as $column) {
            $list[] = '`' . str_replace('`', '``', (string) $column) . '`';
        }

        return 'INSERT INTO `' . $table . '` (' . implode(', ', $list) . ') VALUES '
            . implode(', ', $tuples) . ";\n";
    }

    /** En-tete du fichier, avec la version du jeu et l'horodatage. Fonction pure. */
    public static function header(string $label, string $date, string $database): string
    {
        return "-- XNova -- sauvegarde de la base\n"
            . '-- Version : ' . $label . "\n"
            . '-- Date    : ' . $date . "\n"
            . '-- Base    : ' . $database . "\n"
            . "SET NAMES utf8;\n"
            . "SET FOREIGN_KEY_CHECKS=0;\n\n";
    }

    /** Pied du fichier : les contraintes sont rendues telles qu'on les a trouvees. */
    public static function footer(): string
    {
        return "\nSET FOREIGN_KEY_CHECKS=1;\n";
    }

    /**
     * Ecrit la sauvegarde dans `<racine>/backups/` et rend son chemin.
     *
     * @throws \RuntimeException si le dossier ou le fichier ne s'ouvre pas
     */
    public function dump(string $root, string $label = '', string $date = ''): string
    {
        $date = $date === '' ? date('Y-m-d H:i:s') : $date;
        $pdo  = Connection::open();

        $directory = rtrim(str_replace('\\', '/', $root), '/') . '/backups';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('backups');
        }

        $target = $directory . '/' . self::fileName($label, $date);
        $handle = fopen($target, 'w');

        if ($handle === false) {
            throw new \RuntimeException('backup_file');
        }

        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        fwrite($handle, self::header($label, $date, $database));

        $quote = static function (string $value) use ($pdo): string {
            return (string) $pdo->quote($value);
        };

        foreach ($this->tables($pdo) as $table) {
            fwrite($handle, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
            fwrite($handle, $this->definition($pdo, $table) . ";\n\n");

            $rows    = $pdo->query('SELECT * FROM `' . $table . '`', \PDO::FETCH_ASSOC);
            $columns = array();
            $tuples  = array();
            $bytes   = 0;

            foreach ($rows as $row) {
                if ($columns === array()) {
                    $columns = array_keys($row);
                }

                $tuple   = self::tuple($columns, $row, $quote);
                $tuples[] = $tuple;
                $bytes   += strlen($tuple);

                if (count($tuples) >= self::ROWS_PER_INSERT || $bytes >= self::CHUNK_BYTES) {
                    fwrite($handle, self::insert($table, $columns, $tuples));
                    $tuples = array();
                    $bytes  = 0;
                }
            }

            if ($tuples !== array()) {
                fwrite($handle, self::insert($table, $columns, $tuples));
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, self::footer());
        fclose($handle);

        return $target;
    }

    /**
     * Les tables de la base courante, triees : `SHOW TABLES` ne trie pas, et un
     * dump qui change d'ordre a chaque appel est impossible a comparer.
     *
     * @return list<string>
     */
    private function tables(\PDO $pdo): array
    {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        sort($tables);

        return array_values(array_map('strval', $tables));
    }

    /**
     * La definition d'une table (`SHOW CREATE TABLE`), dont la seconde colonne
     * porte le SQL — son nom differe selon l'objet (`Create Table`, `Create View`).
     */
    private function definition(\PDO $pdo, string $table): string
    {
        $row = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? (string) array_values($row)[1] : '';
    }

    /** Rend une valeur : `NULL` tel quel, les nombres nus, le reste echappe. */
    private static function value(mixed $value, callable $quote): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $quote((string) $value);
    }
}
