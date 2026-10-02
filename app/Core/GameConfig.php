<?php

namespace App\Core;

use App\Database\Connection;

/**
 * Configuration de jeu stockée en base (table config).
 * Remplace le bloc doquery de common.php.
 */
final class GameConfig
{
    private static ?array $config = null;

    public static function load(): array
    {
        if (self::$config === null) {
            self::$config = array();

            $rows = Connection::preparedFetchAll(
                "SELECT config_name, config_value FROM {{table}}",
                array(),
                'config'
            );

            foreach ($rows as $row) {
                self::$config[$row['config_name']] = $row['config_value'];
            }
        }

        return self::$config;
    }

    public static function get(string $name, string $default = ''): string
    {
        $config = self::load();

        return $config[$name] ?? $default;
    }

    public static function set(string $name, string $value): void
    {
        Connection::preparedExecute(
            "UPDATE {{table}} SET config_value = ? WHERE config_name = ?",
            array($value, $name),
            'config'
        );

        if (self::$config !== null) {
            self::$config[$name] = $value;
        }
    }

    /**
     * Alimente la table `config` — appelée par l'installateur, et lui seul.
     *
     * `$defaults` : les réglages créés s'ils manquent (jamais écrasés) ;
     * `$overrides` : les valeurs demandées par l'environnement, écrites qu'elles
     * existent ou non. C'est le seul moment où l'environnement a la main : une
     * fois le jeu démarré, la table `config` fait foi (le panneau
     * d'administration la modifie, personne ne la relit depuis le `.env`).
     *
     * @return int nombre de réglages écrits
     */
    public static function seed(array $defaults, array $overrides = array()): int
    {
        $existing = array();

        foreach (Connection::preparedFetchAll("SELECT config_name FROM {{table}}", array(), 'config') as $row) {
            $existing[(string) $row['config_name']] = true;
        }

        $written = 0;

        foreach ($defaults as $name => $value) {
            if (array_key_exists($name, $overrides)) {
                $value = $overrides[$name];
            } elseif (isset($existing[$name])) {
                continue;
            }

            if (isset($existing[$name])) {
                Connection::preparedExecute(
                    "UPDATE {{table}} SET config_value = ? WHERE config_name = ?",
                    array((string) $value, $name),
                    'config'
                );
            } else {
                Connection::preparedExecute(
                    "INSERT INTO {{table}} (config_name, config_value) VALUES (?, ?)",
                    array($name, (string) $value),
                    'config'
                );
            }

            $written++;
        }

        self::$config = null;

        return $written;
    }

    public static function reload(): array
    {
        self::$config = null;

        return self::load();
    }

    /** Force le chargement depuis la config legacy déjà présente en $GLOBALS. */
    public static function fromGlobals(): array
    {
        if (self::$config === null && isset($GLOBALS['game_config']) && is_array($GLOBALS['game_config'])) {
            self::$config = $GLOBALS['game_config'];
        }

        return self::load();
    }
}
