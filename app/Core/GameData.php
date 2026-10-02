<?php

namespace App\Core;

/**
 * Charge les données de jeu (tables de correspondance, prix, capacités...).
 * La source unique est App\Core\GameTables (ex includes/vars.php) ; les variables
 * legacy restent exposées dans $GLOBALS pour le code non migré, qui les lit en
 * `global` — c'est aussi le cas de BattleEngine.
 */
final class GameData
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $GLOBALS['messfields'] = GameTables::MESSAGE_FIELDS;
        $GLOBALS['resource'] = GameTables::RESOURCE;
        $GLOBALS['requeriments'] = GameTables::REQUIREMENTS;
        $GLOBALS['pricelist'] = GameTables::PRICE_LIST;
        $GLOBALS['CombatCaps'] = GameTables::COMBAT_CAPS;
        $GLOBALS['ProdGrid'] = GameTables::PRODUCTION_GRID;
        $GLOBALS['reslist'] = GameTables::RES_LIST;

        self::loadModuleTables();

        self::$loaded = true;
    }

    /**
     * Tables de jeu **déposées par les modules**.
     *
     * Un module qui apporte une unité (vaisseau, défense) la décrit chez lui : son
     * manifeste déclare la classe des tables (`"tables": "Tables"`, dans `core/`) et
     * le Coeur d'application fusionne ce qu'elle annonce, comme s'il s'agissait des tables du jeu.
     *
     * Un module **absent** est ignoré. Un module **éteint** garde ses définitions
     * (l'unité existe, comme les officiers d'un module éteint) : l'interrupteur
     * commande ses règles — page, mission, surcharges —, jamais la table des éléments.
     *
     * Lecture des manifestes uniquement, jamais de la base : `load()` reste appelable
     * partout, y compris par les tests.
     */
    private static function loadModuleTables(): void
    {
        foreach (Modules::names() as $name) {
            $class = Modules::tables($name);

            if ($class === null || !class_exists($class) || !method_exists($class, 'all')) {
                continue;
            }

            $tables = (array) $class::all();

            $GLOBALS['resource'] = array_replace($GLOBALS['resource'], (array) ($tables['resource'] ?? array()));
            $GLOBALS['requeriments'] = array_replace($GLOBALS['requeriments'], (array) ($tables['requirements'] ?? array()));
            $GLOBALS['pricelist'] = array_replace($GLOBALS['pricelist'], (array) ($tables['pricelist'] ?? array()));
            $GLOBALS['CombatCaps'] = array_replace($GLOBALS['CombatCaps'], (array) ($tables['combatcaps'] ?? array()));

            foreach ((array) ($tables['reslist'] ?? array()) as $category => $elements) {
                $GLOBALS['reslist'][$category] = array_values(array_unique(array_merge(
                    (array) ($GLOBALS['reslist'][$category] ?? array()),
                    (array) $elements
                )));
            }
        }
    }

    public static function messfields(): array
    {
        self::load();

        return $GLOBALS['messfields'];
    }

    public static function resource(): array
    {
        self::load();

        return $GLOBALS['resource'];
    }

    public static function resList(): array
    {
        self::load();

        return $GLOBALS['reslist'];
    }

    public static function priceList(): array
    {
        self::load();

        return $GLOBALS['pricelist'];
    }

    /**
     * Éléments uniques (boucliers) : le schéma les stocke en `enum('0','1')`.
     *
     * Ils ne sont pas recopiés dans une globale : la table est lue directement,
     * comme la règle qui les applique (`ShipyardService::uniqueAllowance()`).
     *
     * @return list<int>
     */
    public static function uniqueUnits(): array
    {
        return GameTables::UNIQUE_UNITS;
    }

    public static function combatCaps(): array
    {
        self::load();

        return $GLOBALS['CombatCaps'];
    }

    public static function prodGrid(): array
    {
        self::load();

        return $GLOBALS['ProdGrid'];
    }

    public static function requirements(): array
    {
        self::load();

        return $GLOBALS['requeriments'];
    }
}
