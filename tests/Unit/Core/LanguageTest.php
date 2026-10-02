<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Language;
use App\Core\Modules;
use PHPUnit\Framework\TestCase;

/**
 * Libellés : ceux du jeu (`language/<lang>/`) et ceux d'un module de module
 * (`modules/<nom>/language/<lang>/`).
 *
 * Les textes d'un module appartiennent à son module : ils voyagent avec lui, et le
 * jeu n'en garde qu'un Coeur d'application commun (le refus d'une fonctionnalité éteinte), pour
 * que le message existe même si le module a disparu du disque.
 */
final class LanguageTest extends TestCase
{
    public function testTheDisabledMessageBelongsToTheGame(): void
    {
        Language::include('modules');

        self::assertNotSame('', (string) Language::get('mod_disabled_title'));
        self::assertStringContainsString(
            '%s',
            (string) Language::get('mod_disabled_message'),
            'Le refus nomme le module : c\'est son libellé qui remplace le marqueur.'
        );
    }

    /** Chaque module apporte le libellé et la description de son module. */
    public function testAModuleCarriesItsOwnLabels(): void
    {
        if (Modules::names() === array()) {
            self::markTestSkipped('Aucun module déposé : rien à charger depuis la version livrée.');
        }

        foreach (Modules::names() as $name) {
            $label = Modules::label($name);
            $description = Modules::description($name);
            $file = Modules::directory() . $name . '/language/fr/module.mo';

            self::assertFileExists($file, $name . ' doit livrer ses libellés dans son module.');
            self::assertStringContainsString(
                "\$lang['" . $label . "']",
                (string) file_get_contents($file),
                $name . ' doit définir la clé de son libellé.'
            );

            if ($description !== '') {
                self::assertStringContainsString(
                    "\$lang['" . $description . "']",
                    (string) file_get_contents($file),
                    $name . ' doit définir la clé de sa description.'
                );
            }

            Language::includeModule($name);

            self::assertNotSame('', (string) Language::get($label), $label . ' doit être chargé depuis le module.');
        }
    }

    /** Un module sans dossier `language/` ne casse rien : aucune clé n'est chargée. */
    public function testAModuleWithoutLanguageIsNotFatal(): void
    {
        Language::includeModule('module_inexistant');

        self::assertNull(Language::get('mod_module_inexistant'));
    }
}
