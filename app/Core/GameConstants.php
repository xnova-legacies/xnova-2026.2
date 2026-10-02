<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Constantes de jeu — source unique.
 *
 * Les valeurs par défaut sont celles du jeu ; chacune peut être surchargée par
 * l'environnement (`.env`, voir ENV_KEYS). `includes/constants.php` (legacy) pose
 * ses `define()` à partir de all(), donc une seule valeur circule : pour le code
 * historique comme pour le code moderne, qui passe par les accesseurs.
 */
final class GameConstants
{
    /** Valeurs par défaut, identiques à l'historique. */
    public const DEFAULTS = array(
        'ADMINEMAIL' => 'admin@xnova.fr',
        'MAX_GALAXY_IN_WORLD' => 9,
        'MAX_SYSTEM_IN_GALAXY' => 499,
        'MAX_PLANET_IN_SYSTEM' => 15,
        'SPY_REPORT_ROW' => 2,
        'FIELDS_BY_MOONBASIS_LEVEL' => 4,
        'MAX_PLAYER_PLANETS' => 21,
        'ABANDONED_POSITION_DELAY' => 86400,
        'MAX_BUILDING_QUEUE_SIZE' => 5,
        'MAX_TECHNOLOGY_QUEUE_SIZE' => 10,
        'MAX_FLEET_OR_DEFS_PER_ROW' => 1000,
        'MAX_OVERFLOW' => 1.1,
        'SHOW_ADMIN_IN_RECORDS' => 0,
        'BASE_STORAGE_SIZE' => 1000000,
        'BUILD_METAL' => 500,
        'BUILD_CRISTAL' => 500,
        'BUILD_DEUTERIUM' => 500,
    );

    /** Variable d'environnement associée (clé = nom de la constante historique). */
    public const ENV_KEYS = array(
        'ADMINEMAIL' => 'ADMIN_EMAIL',
        'MAX_GALAXY_IN_WORLD' => 'UNIVERSE_GALAXIES',
        'MAX_SYSTEM_IN_GALAXY' => 'UNIVERSE_SYSTEMS',
        'MAX_PLANET_IN_SYSTEM' => 'UNIVERSE_PLANETS',
        'SPY_REPORT_ROW' => 'SPY_REPORT_ROWS',
        'FIELDS_BY_MOONBASIS_LEVEL' => 'MOON_FIELDS_PER_LEVEL',
        'MAX_PLAYER_PLANETS' => 'MAX_PLAYER_PLANETS',
        'ABANDONED_POSITION_DELAY' => 'ABANDONED_POSITION_DELAY',
        'MAX_BUILDING_QUEUE_SIZE' => 'MAX_BUILDING_QUEUE',
        'MAX_TECHNOLOGY_QUEUE_SIZE' => 'MAX_TECHNOLOGIE_QUEUE',
        'MAX_FLEET_OR_DEFS_PER_ROW' => 'MAX_UNITS_PER_ROW',
        'MAX_OVERFLOW' => 'STORAGE_OVERFLOW',
        'SHOW_ADMIN_IN_RECORDS' => 'RECORDS_SHOW_ADMINS',
        'BASE_STORAGE_SIZE' => 'COLONY_STORAGE',
        'BUILD_METAL' => 'COLONY_METAL',
        'BUILD_CRISTAL' => 'COLONY_CRISTAL',
        'BUILD_DEUTERIUM' => 'COLONY_DEUTERIUM',
    );

    /**
     * Valeur effective d'une constante : environnement si défini, sinon défaut.
     * Le type suit celui de la valeur par défaut (entier, décimal ou texte).
     */
    public static function value(string $name): int|float|string
    {
        $default = self::DEFAULTS[$name] ?? 0;
        $key = self::ENV_KEYS[$name] ?? '';
        $raw = $key !== '' ? getenv($key) : false;

        if ($raw === false || trim((string) $raw) === '') {
            return $default;
        }

        if (is_int($default)) {
            return (int) $raw;
        }

        if (is_float($default)) {
            return (float) str_replace(',', '.', (string) $raw);
        }

        return (string) $raw;
    }

    /** Toutes les valeurs effectives (utilisé par includes/constants.php). */
    public static function all(): array
    {
        $values = array();

        foreach (array_keys(self::DEFAULTS) as $name) {
            $values[$name] = self::value($name);
        }

        return $values;
    }

    // --- Accesseurs typés : le code moderne n'utilise plus de constante en dur. ---

    public static function adminEmail(): string
    {
        return (string) self::value('ADMINEMAIL');
    }

    public static function maxGalaxyInWorld(): int
    {
        return (int) self::value('MAX_GALAXY_IN_WORLD');
    }

    public static function maxSystemInGalaxy(): int
    {
        return (int) self::value('MAX_SYSTEM_IN_GALAXY');
    }

    public static function maxPlanetInSystem(): int
    {
        return (int) self::value('MAX_PLANET_IN_SYSTEM');
    }

    public static function spyReportRows(): int
    {
        return (int) self::value('SPY_REPORT_ROW');
    }

    public static function fieldsByMoonbasisLevel(): int
    {
        return (int) self::value('FIELDS_BY_MOONBASIS_LEVEL');
    }

    public static function maxPlayerPlanets(): int
    {
        return (int) self::value('MAX_PLAYER_PLANETS');
    }

    /**
     * Délai pendant lequel les coordonnées d'une colonie abandonnée restent
     * réservées avant d'être colonisables de nouveau (secondes ; 0 = tout de suite).
     */
    public static function abandonedPositionDelay(): int
    {
        return (int) self::value('ABANDONED_POSITION_DELAY');
    }

    public static function maxBuildingQueueSize(): int
    {
        return (int) self::value('MAX_BUILDING_QUEUE_SIZE');
    }

    /**
     * Nombre de recherches que le laboratoire peut garder en file (la première
     * travaille, les suivantes attendent). Valeur par défaut : 10.
     */
    public static function maxTechnologyQueueSize(): int
    {
        return (int) self::value('MAX_TECHNOLOGY_QUEUE_SIZE');
    }

    public static function maxUnitsPerRow(): int
    {
        return (int) self::value('MAX_FLEET_OR_DEFS_PER_ROW');
    }

    public static function maxOverflow(): float
    {
        return (float) self::value('MAX_OVERFLOW');
    }

    public static function showAdminInRecords(): bool
    {
        return (int) self::value('SHOW_ADMIN_IN_RECORDS') === 1;
    }

    public static function baseStorageSize(): int
    {
        return (int) self::value('BASE_STORAGE_SIZE');
    }

    /** Valeurs de départ d'une colonie fraîchement créée. */
    public static function colonyResources(): array
    {
        return array(
            'metal' => (int) self::value('BUILD_METAL'),
            'crystal' => (int) self::value('BUILD_CRISTAL'),
            'deuterium' => (int) self::value('BUILD_DEUTERIUM'),
        );
    }

    /** Liste de mots interdits à la saisie (ex constants.php $ListCensure). */
    public static function censoredWords(): array
    {
        return array('<', '>', 'script', 'doquery', 'http', 'javascript', "'");
    }
}
