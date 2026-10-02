<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Réglages de la table `config` — source unique.
 *
 * Ce sont les valeurs écrites par l'installateur (migration 001, figée) : elles
 * servent de référence à une installation neuve, et chacune peut être
 * surchargée par l'environnement (`.env.<env>`, clé `CONFIG_<NOM>`).
 *
 * L'environnement n'a la main **qu'à l'installation** (voir
 * `GameConfig::seed()`). Une fois le jeu démarré, la table `config` fait foi :
 * c'est elle que lisent le moteur et le panneau d'administration, donc une
 * variable ajoutée après coup ne change pas un réglage déjà en base.
 */
final class ConfigDefaults
{
    /** Préfixe des variables d'environnement d'un réglage. */
    public const ENV_PREFIX = 'CONFIG_';

    /**
     * Valeurs d'une installation neuve : celles de la migration 001, corrigées
     * par la mise à niveau 002 (figées, elles aussi).
     */
    public const DEFAULTS = array(
        'users_amount' => '0',
        'game_speed' => '2500',
        'fleet_speed' => '2500',
        'resource_multiplier' => '1',
        'Fleet_Cdr' => '30',
        'Defs_Cdr' => '30',
        'initial_fields' => '163',
        'COOKIE_NAME' => 'XNova',
        'game_name' => 'XNova',
        'game_disable' => '0',
        'close_reason' => '',
        'metal_basic_income' => '20',
        'crystal_basic_income' => '10',
        'deuterium_basic_income' => '0',
        'energy_basic_income' => '0',
        'BuildLabWhileRun' => '0',
        'LastSettedGalaxyPos' => '1',
        'LastSettedSystemPos' => '9',
        'LastSettedPlanetPos' => '1',
        'vacation_mode_enforced' => '1',
        'noobprotection' => '1',
        'noobprotectiontime' => '5000',
        'noobprotectionmulti' => '5',
        'forum_url' => 'http://board.xnova-ng.org/',
        'OverviewNewsFrame' => '1',
        'OverviewNewsText' => 'Vous avez correctement mis votre serveur UGamela sous XNova!',
        'OverviewExternChat' => '0',
        'OverviewExternChatCmd' => '',
        'OverviewBanner' => '0',
        'OverviewClickBanner' => '',
        'ExtCopyFrame' => '0',
        'ExtCopyOwner' => '',
        'ExtCopyFunct' => '',
        'ForumBannerFrame' => '0',
        'stat_settings' => '1000',
        'link_enable' => '0',
        'link_name' => '',
        'link_url' => '',
        'enable_announces' => '1',
        'enable_marchand' => '1',
        'enable_notes' => '1',
        'bot_name' => 'XNoviana Reali',
        'bot_adress' => 'xnova@xnova.fr',
        'banner_source_post' => '../images/bann.png',
        'ban_duration' => '30',
        'enable_bot' => '0',
        'enable_bbcode' => '1',
        'debug' => '0',
    );

    /** Variable d'environnement d'un réglage : `initial_fields` → `CONFIG_INITIAL_FIELDS`. */
    public static function envKey(string $name): string
    {
        return self::ENV_PREFIX . strtoupper($name);
    }

    /**
     * Valeur demandée par l'environnement, ou null quand la variable est muette.
     * Le lecteur est injectable pour rendre la règle testable : elle n'appelle
     * alors ni les fichiers `.env` ni le processus.
     */
    public static function envValue(string $name, ?callable $reader = null): ?string
    {
        $reader ??= static fn (string $key): string => self::env($key);
        $raw = trim((string) $reader(self::envKey($name)));

        return $raw === '' ? null : $raw;
    }

    /**
     * Réglages explicitement demandés par l'environnement.
     * Eux seuls écrasent une valeur déjà en base à l'installation.
     */
    public static function overrides(?callable $reader = null): array
    {
        $values = array();

        foreach (array_keys(self::DEFAULTS) as $name) {
            $value = self::envValue($name, $reader);

            if ($value !== null) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /** Toutes les valeurs d'une installation neuve : environnement sinon défaut. */
    public static function values(?callable $reader = null): array
    {
        $values = array();

        foreach (self::DEFAULTS as $name => $default) {
            $values[$name] = self::envValue($name, $reader) ?? $default;
        }

        return $values;
    }

    /**
     * Lecture d'une variable : les fichiers `.env` / `.env.<APP_ENV>`, comme les
     * autres réglages d'univers (marché, robots, base de données) — donc lus à
     * l'exécution, sans recréer le conteneur.
     */
    private static function env(string $key): string
    {
        return Env::get($key);
    }
}
