<?php

/**
 * Tis file is part of XNova:Legacies
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @see http://www.xnova-ng.org/
 *
 * Copyright (c) 2009-Present, XNova Support Team <http://www.xnova-ng.org>
 * All rights reserved.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *                                --> NOTICE <--
 *  This file is part of the core development branch, changing its contents will
 * make you unable to use the automatic updates manager. Please refer to the
 * documentation for further information about customizing XNova.
 *
 */

class Database
{
    static $dbHandle = null;
    static $config = null;
}

require_once dirname(dirname(__FILE__)) . '/includes/env.php';

if (!function_exists('mysql_connect')) {
    function mysql_connect($hostname, $username, $password)
    {
        // **Un seul** endroit ouvre une connexion (`App\Database\Connection`) : elle
        // pose le jeu de caractères et la range dans le global que lit le legacy.
        // Deux créateurs de connexion, c'est exactement ce qui avait produit le
        // double encodage des noms de planètes.
        return \App\Database\Connection::open($hostname, $username, $password);
    }

    function mysql_select_db($database, $connection = null)
    {
        return \App\Database\Connection::useDatabase($database);
    }

    function mysql_query($query, $connection = null)
    {
        return \App\Database\Connection::pdo()->query($query);
    }

    function mysql_error($connection = null)
    {
        $information = \App\Database\Connection::pdo()->errorInfo();

        return isset($information[2]) ? (string) $information[2] : '';
    }

    function mysql_fetch_array($result, $resultType = MYSQLI_BOTH)
    {
        return $result->fetch($resultType);
    }

    function mysql_fetch_assoc($result)
    {
        return $result->fetch(PDO::FETCH_ASSOC);
    }

    function mysql_fetch_object($result)
    {
        return $result->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Nombre de lignes d'un résultat.
     *
     * `rowCount()` ne compte pas les lignes d'un SELECT avec PDO/MySQL : aucun
     * appelant ne s'en sert (le jeu compte par `COUNT(*)`), la fonction reste pour
     * la forme.
     */
    function mysql_num_rows($result)
    {
        return $result->rowCount();
    }

    /** Valeur d'une colonne, à la ligne demandée (aucun appelant restant). */
    function mysql_result($result, $row, $field = 0)
    {
        for ($skip = 0; $skip < (int) $row; $skip++) {
            $result->fetch(PDO::FETCH_NUM);
        }

        return $result->fetchColumn((int) $field);
    }

    function mysql_escape_string($value)
    {
        return \App\Database\Connection::escape((string) $value);
    }

    function mysql_real_escape_string($value, $connection = null)
    {
        return \App\Database\Connection::escape((string) $value);
    }

    /**
     * Le jeu garde une seule connexion, partagée par tout le monde : la fermer ici
     * couperait la fin de la requête. Le shim répond donc sans rien fermer.
     */
    function mysql_close($connection = null)
    {
        return true;
    }

    // Les noms que le legacy connaît (`MYSQLI_BOTH`…) valent les modes de PDO : les
    // signatures du shim restent lisibles, et `mysql_fetch_array()` reçoit
    // directement un mode utilisable par `fetch()`.
    if (!defined('MYSQLI_ASSOC')) {
        define('MYSQLI_ASSOC', PDO::FETCH_ASSOC);
    }

    if (!defined('MYSQLI_NUM')) {
        define('MYSQLI_NUM', PDO::FETCH_NUM);
    }

    if (!defined('MYSQLI_BOTH')) {
        define('MYSQLI_BOTH', PDO::FETCH_BOTH);
    }
}

function doquery($query, $table, $fetch = false)
{
    if (!isset(Database::$config)) {
        $configFile = dirname(dirname(__FILE__)) . '/configs/config.php';
        $config = is_file($configFile) && filesize($configFile) > 0 ? require $configFile : array();
        $database = isset($config['global']['database']) ? $config['global']['database'] : array();
        $options = isset($database['options']) ? $database['options'] : array();

        $options['hostname'] = xnova_env('DB_HOST', isset($options['hostname']) ? $options['hostname'] : 'localhost');
        $options['username'] = xnova_env('DB_USER', isset($options['username']) ? $options['username'] : '');
        $options['password'] = xnova_env('DB_PASSWORD', isset($options['password']) ? $options['password'] : '');
        $options['database'] = xnova_env('DB_NAME', isset($options['database']) ? $options['database'] : '');
        $database['options'] = $options;
        $database['table_prefix'] = xnova_env('DB_PREFIX', isset($database['table_prefix']) ? $database['table_prefix'] : '');
        $config['global']['database'] = $database;
        Database::$config = $config;
    }

    $config = Database::$config;

    if (!isset(Database::$dbHandle)) {
        // La connexion est **celle du Coeur d'application** : un seul créateur, donc
        // un seul jeu de caractères, et le `LOCK TABLES` posé ici s'applique aux
        // requêtes préparées des dépôts.
        Database::$dbHandle = \App\Database\Connection::open(
            (string) $config['global']['database']['options']['hostname'],
            (string) $config['global']['database']['options']['username'],
            (string) $config['global']['database']['options']['password'],
            (string) $config['global']['database']['options']['database']
        );
    }

    $sql = str_replace("{{table}}", "{$config['global']['database']['table_prefix']}{$table}", $query);

    $started = microtime(true);

    // PDO lève sur erreur (`ERRMODE_EXCEPTION`) : une requête fautive ne peut plus
    // passer pour un résultat vide.
    $sqlQuery = \App\Database\Connection::pdo()->query($sql);

    // Barre de debug (no-op quand elle est désactivée) : les requêtes legacy
    // doivent apparaître à côté de celles de Connection.
    if (class_exists('App\\Core\\Debug\\DebugBar')) {
        \App\Core\Debug\DebugBar::logQuery($sql, (microtime(true) - $started) * 1000, $table);
    }

    if ($fetch) {
        return mysql_fetch_array($sqlQuery);
    } else {
        return $sqlQuery;
    }
}
