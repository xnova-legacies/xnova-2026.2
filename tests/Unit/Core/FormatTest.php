<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Format;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    private array $langBackup = [];

    protected function setUp(): void
    {
        $this->langBackup = $GLOBALS['lang'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['lang'] = $this->langBackup;
    }

    public function testPrettyNumberRoundsDownByDefault(): void
    {
        self::assertSame('1.234', Format::prettyNumber(1234.9));
    }

    /**
     * Les labels du jeu sont stockés en latin1, souvent déjà en entités HTML :
     * `text()` doit décoder une fois puis échapper une fois — sans jamais
     * renvoyer une chaîne vide (l'ancienne version décodait en Windows-1252 et
     * `htmlspecialchars()` rejetait alors les octets obtenus).
     */
    public function testTextDecodesEntitiesThenEscapesOnce(): void
    {
        self::assertSame('Tour de contrôle', Format::text('Tour de contr&ocirc;le'));
        self::assertSame('Expédition terminée', Format::text('Exp&eacute;dition termin&eacute;e'));
    }

    public function testTextRepairsLatin1Bytes(): void
    {
        self::assertSame('Boréal 2', Format::text("Bor\xe9al 2"));
    }

    public function testTextEscapesHtml(): void
    {
        self::assertSame('&lt;script&gt;', Format::text('<script>'));
        self::assertSame('l&#039;équipe', Format::text("l'\xe9quipe"));
    }

    public function testTextLeavesPlainAsciiUntouched(): void
    {
        self::assertSame('Homeworld', Format::text('Homeworld'));
    }

    /**
     * Les noms de planètes sont stockés en UTF-8 dans une colonne latin1 : la
     * connexion moderne (UTF-8) les ré-encode, et l'affichage doit retrouver les
     * octets d'origine — ceux que rendaient les pages historiques.
     */
    public function testLegacyPlanetNameRestoresTheStoredBytes(): void
    {
        // « BorÃ©al » (double encodage) redevient « Boréal » (UTF-8 d'origine).
        self::assertSame("Bor\xc3\xa9al 2", Format::legacyPlanetName("Bor\xc3\x83\xc2\xa9al 2"));
    }

    public function testLegacyPlanetNameLeavesAsciiNamesUntouched(): void
    {
        self::assertSame('Homeworld', Format::legacyPlanetName('Homeworld'));
        self::assertSame('Colonie 7 [1:2:3]', Format::legacyPlanetName('Colonie 7 [1:2:3]'));
    }

    public function testPrettyNumberKeepsDecimalsWhenAskedNotToFloor(): void
    {
        self::assertSame('1.235', Format::prettyNumber(1234.9, false));
    }

    public function testPrettyNumberGroupsThousands(): void
    {
        self::assertSame('1.234.567', Format::prettyNumber(1234567));
    }

    public function testDecimalKeepsTheFractionWithAFrenchComma(): void
    {
        self::assertSame('1,165', Format::decimal(1.1649, 4));
        self::assertSame('0,627', Format::decimal(0.6269, 4));
        self::assertSame('2.164,73', Format::decimal(2164.73, 2));
    }

    public function testDecimalNeverShowsMoreThanThreeDecimals(): void
    {
        self::assertSame(3, Format::MAX_DECIMALS);
        self::assertSame('6.563.073,366', Format::decimal(6563073.36649698, 8));
        self::assertSame('1,165', Format::decimal(1.164867, 4), 'Le quatrième chiffre est arrondi, pas affiché.');
    }

    public function testDecimalDropsUselessZeros(): void
    {
        self::assertSame('2', Format::decimal(2.0, 4));
        self::assertSame('100.000', Format::decimal(100000, 2));
        self::assertSame('0', Format::decimal(-0.001, 2));
        self::assertSame('0', Format::decimal(0.0, 2));
    }

    public function testDecimalRoundsLikeTheGameDoes(): void
    {
        self::assertSame('1.501', Format::decimal(1500.5, 0));
        self::assertSame('-12,34', Format::decimal(-12.34, 2));
    }

    public function testSignedPercentAlwaysCarriesItsSign(): void
    {
        self::assertSame('+8,4 %', Format::signedPercent(8.4));
        self::assertSame('-12 %', Format::signedPercent(-12.0));
        self::assertSame('0 %', Format::signedPercent(0.0));
        self::assertSame('+1.234,6 %', Format::signedPercent(1234.56));
    }

    public function testColorNumberWrapsPositiveValueInSuccess(): void
    {
        self::assertSame('<span class="text-success">10</span>', Format::colorNumber(10));
    }

    public function testColorNumberWrapsNegativeValueInDanger(): void
    {
        self::assertSame('<span class="text-danger">-10</span>', Format::colorNumber(-10));
    }

    public function testColorNumberLeavesZeroUntouched(): void
    {
        self::assertSame('0', Format::colorNumber(0));
    }

    public function testColorNumberCanLabelAnExplicitValue(): void
    {
        self::assertSame('<span class="text-success">métal</span>', Format::colorNumber(5, 'métal'));
        self::assertSame('métal', Format::colorNumber(0, 'métal'));
    }

    public function testPrettyTimeFormatsAFullDuration(): void
    {
        self::assertSame('1j 01h 01m 01s', Format::prettyTime(90061));
    }

    public function testPrettyTimePadsMissingHoursAndMinutes(): void
    {
        self::assertSame('00h 00m 00s', Format::prettyTime(0));
        self::assertSame('00h 01m 00s', Format::prettyTime(60));
    }

    public function testPrettyTimeHourOnlyDisplaysMinutes(): void
    {
        self::assertSame('59min ', Format::prettyTimeHour(3599));
        self::assertSame('', Format::prettyTimeHour(3600));
    }

    public function testShowBuildTimeUsesTheLocalisedLabel(): void
    {
        $GLOBALS['lang'] = ['ConstructionTime' => 'Durée de construction'];

        self::assertSame('<br>Durée de construction: 00h 01m 00s', Format::showBuildTime(60));
    }
}
