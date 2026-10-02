<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use PHPUnit\Framework\TestCase;

/**
 * Réponse de chargement des fonctions legacy.
 *
 * `includes/unlocalised.php`, `includes/strings.php` et `includes/aks.php` ont
 * disparu : les deux premiers sont devenus `app/Core/Legacy/PageFunctions.php` et
 * `app/Core/Legacy/FormatFunctions.php`, chargés par `includes/todofleetcontrol.php`
 * en production et par `tests/bootstrap.php` ici. Ce test échoue si l'un d'eux
 * cesse d'être chargé — ou si une fonction migrée revient.
 */
final class LegacyFunctionSetTest extends TestCase
{
    public function testTheLegacyFunctionSetIsLoaded(): void
    {
        $functions = array(
            'CheckInputStrings',    // UserFunctions
            'pretty_number',        // FormatFunctions (ex includes/strings.php)
            'colorNumber',          // FormatFunctions
            'colorGreen',           // FormatFunctions
            'GetTargetDistance',    // PageFunctions (ex includes/unlocalised.php)
            'GetFleetMaxSpeed',
            'GetShipConsumption',
            'GetStartAdressLink',
            'GetTargetAdressLink',
            'parsetemplate',
            'includeLang',
        );

        foreach ($functions as $function) {
            self::assertTrue(function_exists($function), $function . '() devrait être chargée.');
        }
    }

    public function testMigratedLegacyFunctionsAreGone(): void
    {
        // walka() vivait dans includes/ataki.php, désormais App\Core\Combat\BattleEngine.
        self::assertFalse(function_exists('walka'), 'walka() a été migrée vers BattleEngine.');
        // aks() venait de includes/aks.php, supprimé : l'ACS passe par AcsController.
        self::assertFalse(function_exists('aks'), 'includes/aks.php a été supprimé.');

        // Rendu BBCode : App\Core\BbCode est la seule implémentation. Les globales
        // historiques (includes/functions/BBcodeFunction.php) ont disparu.
        foreach (array('bbcode', 'image', 'sCode', 'sList', 'imagefix', 'urlfix') as $gone) {
            self::assertFalse(function_exists($gone), $gone . '() a été remplacée par App\Core\BbCode.');
        }
    }

    /**
     * Le menu latéral n'a plus de wrapper à la racine : le tableau
     * `ShowLeftMenu()` vit avec les autres fonctions de page, et les deux
     * `renderDisplay()` la chargent de là.
     */
    public function testTheLeftMenuWrapperLivesWithTheLegacyFunctions(): void
    {
        self::assertFileDoesNotExist(ROOT_PATH . 'leftmenu.php', 'leftmenu.php ne doit plus exister à la racine.');

        $wrapper = ROOT_PATH . 'app/Core/Legacy/LeftMenuFunctions.php';
        self::assertFileExists($wrapper);

        $source = (string) file_get_contents($wrapper);
        self::assertStringContainsString('function ShowLeftMenu', $source);
        self::assertStringContainsString('App\\Core\\LeftMenu::render', $source);

        // Même chemin dans les deux rendus : le wrapper n'est plus un fichier racine.
        foreach (array('app/Core/TemplateEngine.php', 'includes/functions.php') as $file) {
            self::assertStringContainsString(
                "'app/Core/Legacy/LeftMenuFunctions.' . PHPEXT",
                (string) file_get_contents(ROOT_PATH . $file),
                $file . ' doit charger l\'tableau depuis app/Core/Legacy/.'
            );
        }
    }

    /**
     * Les tableaux legacy encore appelées par les pages ne doivent plus contenir
     * de règle de jeu : elles délèguent à App\Core\FleetMath / App\Core\Format.
     * On vérifie l'égalité stricte avec l'implémentation moderne sur les fonctions
     * pures (les autres dépendent de GameConfig / GameData, donc de la base).
     */
    public function testLegacyWrappersDelegateToTheModernClasses(): void
    {
        self::assertSame(
            \App\Core\FleetMath::targetDistance(1, 3, 1, 9, 1, 4),
            GetTargetDistance(1, 3, 1, 9, 1, 4)
        );
        self::assertSame(
            \App\Core\FleetMath::missionDuration(10, 5000, 1000, 0.5),
            GetMissionDuration(10, 5000, 1000, 0.5)
        );
        self::assertSame(\App\Core\Format::prettyTime(3661), pretty_time(3661));
        self::assertSame(\App\Core\Format::prettyTimeHour(7200), pretty_time_hour(7200));
        self::assertSame(\App\Core\Format::prettyNumber(1234.6), pretty_number(1234.6));
        self::assertSame(\App\Core\Format::colorGreen(5), colorGreen(5));
        self::assertSame(\App\Core\Format::colorRed(-5), colorRed(-5));
        self::assertSame(\App\Core\Format::colorNumber(-42), colorNumber(-42));
    }
}
