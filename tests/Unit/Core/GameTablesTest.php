<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\GameData;
use App\Core\GameTables;
use PHPUnit\Framework\TestCase;

/**
 * Les tables de jeu sont des constantes de App\Core\GameTables. GameData ne fait
 * que les recopier dans les variables globales lues par le code legacy — source
 * unique, pas de double définition.
 */
final class GameTablesTest extends TestCase
{
    public function testTheSevenTablesAreConstants(): void
    {
        self::assertCount(10, GameTables::MESSAGE_FIELDS);
        self::assertNotSame(array(), GameTables::RESOURCE);
        self::assertNotSame(array(), GameTables::REQUIREMENTS);
        self::assertNotSame(array(), GameTables::PRICE_LIST);
        self::assertNotSame(array(), GameTables::COMBAT_CAPS);
        self::assertNotSame(array(), GameTables::PRODUCTION_GRID);
        self::assertSame(
            array('build', 'tech', 'fleet', 'defense', 'prod'),
            array_keys(GameTables::RES_LIST)
        );
    }

    public function testGameDataMirrorsTheConstants(): void
    {
        GameData::load();

        self::assertSame(GameTables::MESSAGE_FIELDS, $GLOBALS['messfields']);
        self::assertSame(GameTables::PRODUCTION_GRID, GameData::prodGrid());

        // Les tables du Coeur d'application restent la source : le chargement les recopie à
        // l'identique, et peut seulement y **ajouter** ce qu'un module dépose
        // (`"tables"` de son manifeste, fusionné par `GameData::loadModuleTables()`).
        self::assertSame(array(), array_diff_assoc(GameTables::RESOURCE, GameData::resource()));
        self::assertSame(array(), array_diff_assoc(GameTables::REQUIREMENTS, GameData::requirements()));
        self::assertSame(array(), array_diff_assoc(GameTables::PRICE_LIST, GameData::priceList()));
        self::assertSame(array(), array_diff_assoc(GameTables::COMBAT_CAPS, GameData::combatCaps()));

        foreach (GameTables::RES_LIST as $category => $elements) {
            self::assertSame(
                array(),
                array_diff($elements, GameData::resList()[$category] ?? array()),
                'La catégorie ' . $category . ' garde ses éléments.'
            );
        }
    }

    public function testSampledValuesSurvivedTheMove(): void
    {
        // Valeurs relevées dans l'ancien includes/vars.php.
        self::assertSame(60, GameTables::PRICE_LIST[1]['metal']);
        self::assertSame(1000000, GameTables::PRICE_LIST[15]['metal']);
        self::assertSame(50000, GameTables::COMBAT_CAPS[214]['shield']);
        self::assertSame('spy_sonde', GameTables::RESOURCE[210]);
        self::assertSame(2, GameTables::REQUIREMENTS[210][106], 'La sonde exige le niveau 2 en espionnage.');
    }
}
