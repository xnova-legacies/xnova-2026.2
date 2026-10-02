<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\TemplateEngine;
use PHPUnit\Framework\TestCase;

/**
 * Case à cocher globale des tableaux (`AbstractController::checkAll()`).
 *
 * Le balisage vit dans le gabarit partagé `check_all.tpl`, servi au jeu comme à
 * l'administration. Deux règles y tiennent : la case porte le groupe des lignes à
 * cocher (`data-xnova-check-all`), et elle **n'a pas de `name`** — sans quoi elle
 * serait envoyée au serveur avec le formulaire, comme une ligne de plus.
 */
final class CheckAllTest extends TestCase
{
    public function testTemplateCarriesTheGroupAndTheLabel(): void
    {
        $html = TemplateEngine::render('check_all', array(
            'check_group' => 'notes',
            'check_label' => 'Tout s&eacute;lectionner',
        ));

        self::assertStringContainsString('data-xnova-check-all="notes"', $html);
        self::assertStringContainsString('aria-label="Tout s&eacute;lectionner"', $html);
        self::assertStringContainsString('form-check-input', $html);
    }

    public function testTemplateIsNeverSubmittedWithTheForm(): void
    {
        $html = TemplateEngine::render('check_all', array('check_group' => 'messages', 'check_label' => 'Tout'));

        self::assertStringNotContainsString('name=', $html, 'La case globale ne doit pas être envoyée au serveur.');
        self::assertStringNotContainsString('value=', $html);
    }

    public function testScriptBindsTheMasterToItsTable(): void
    {
        $script = (string) file_get_contents(ROOT_PATH . 'scripts/xnova-checkall.js');

        // Le script s'appuie sur les deux attributs, cherche les lignes dans le
        // tableau qui porte la case, et suit les remplacements de softReload().
        self::assertStringContainsString('data-xnova-check-all', $script);
        self::assertStringContainsString('data-xnova-check=', $script);
        self::assertStringContainsString("closest('table')", $script);
        self::assertStringContainsString('xnova:reloaded', $script);
        self::assertStringContainsString('indeterminate', $script);
    }
}
