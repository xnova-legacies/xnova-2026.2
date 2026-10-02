<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\UnitCard;
use PHPUnit\Framework\TestCase;

/**
 * Zone de saisie commune au chantier spatial et a la defense.
 *
 * `forElement()` et `forRange()` s'appuient sur les primitives legacy (prix,
 * duree, accessibilite : base de donnees) et ne sont donc pas testables ici :
 * seule la mise en forme l'est.
 */
final class UnitCardTest extends TestCase
{
    public function testInputCarriesTheQuantityAndTheMaxButton(): void
    {
        $html = UnitCard::input(401, 'Lanceur de missiles', 3, 1000);

        self::assertStringContainsString('name="amounts[401]"', $html);
        self::assertStringContainsString('id="amounts[401]"', $html);
        self::assertStringContainsString('value="0"', $html);
        self::assertStringContainsString('tabindex="3"', $html);
        self::assertStringContainsString('input-group-sm', $html);
        self::assertStringContainsString("getElementById('amounts[401]').value='1000'", $html);
        self::assertStringContainsString('Nombre max (1000)', $html);
    }

    public function testInputEscapesTheLabelTakenFromTheLanguageFile(): void
    {
        // Les noms de $lang contiennent deja des entites HTML : elles sont
        // decodees puis re-echappees pour l'attribut aria-label.
        $html = UnitCard::input(202, 'Petit transporteur &amp; co', 1, 5);

        self::assertStringContainsString('aria-label="Petit transporteur &amp; co"', $html);
    }

    public function testInputIsReplacedByADashWhenNothingCanBeOrdered(): void
    {
        $html = UnitCard::input(401, 'Lanceur de missiles', 1, 0);

        self::assertStringNotContainsString('<input', $html);
        self::assertStringNotContainsString('<button', $html);
        self::assertStringContainsString('text-body-secondary', $html);
    }

    public function testNoteReplacesTheInput(): void
    {
        self::assertSame(
            '<span class="text-danger small">Un seul exemplaire</span>',
            UnitCard::note('Un seul exemplaire')
        );
    }
}
