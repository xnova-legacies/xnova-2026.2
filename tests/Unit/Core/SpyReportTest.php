<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\SpyReport;
use PHPUnit\Framework\TestCase;

/**
 * Rapport d'espionnage : les règles pures sont le comptage et le rangement des
 * lignes (quels éléments sont vus, et le total qui décide du niveau
 * d'information). La mise en forme, elle, vient des gabarits `spy_report*.tpl`.
 */
final class SpyReportTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $langBackup = [];

    protected function setUp(): void
    {
        $this->langBackup = $GLOBALS['lang'] ?? [];
        $GLOBALS['lang'] = is_array($this->langBackup) ? $this->langBackup : array();
    }

    protected function tearDown(): void
    {
        $GLOBALS['lang'] = $this->langBackup;
    }

    public function testOnlyTheElementsActuallyPresentAreListed(): void
    {
        $planet = array('small_laser' => 3, 'big_laser' => 0, 'gauss_canyon' => 12);
        $names = array(402 => 'Laser léger', 403 => 'Laser lourd', 404 => 'Canon de Gauss');

        $found = SpyReport::rows($planet, array(array(400, 499)), $names);

        self::assertSame(array(
            array('label' => 'Laser léger', 'amount' => '3'),
            array('label' => 'Canon de Gauss', 'amount' => '12'),
        ), $found['rows']);
        self::assertSame(15, $found['count'], 'Le total additionne les quantités vues.');
    }

    public function testTheCountDrivesTheInformationLevel(): void
    {
        // C'est ce total qui décide, dans le service de mission, de la catégorie
        // révélée (plus l'espionnage est fort, plus le total est gros).
        $found = SpyReport::rows(
            array('battleship' => 2, 'bomber_ship' => 3),
            array(array(200, 299)),
            array(215 => 'Vaisseau de bataille', 211 => 'Bombardier')
        );

        self::assertSame(5, $found['count']);
        self::assertCount(2, $found['rows']);
    }

    public function testSeveralRangesAreWalkedInOrder(): void
    {
        $found = SpyReport::rows(
            array('interceptor_misil' => 4, 'gauss_canyon' => 1),
            array(array(400, 499), array(500, 599)),
            array(404 => 'Canon de Gauss', 502 => 'Missile interplanétaire')
        );

        self::assertSame(array('Canon de Gauss', 'Missile interplanétaire'), array_column($found['rows'], 'label'));
        self::assertSame(5, $found['count']);
    }

    public function testUnknownElementsAreIgnored(): void
    {
        $found = SpyReport::rows(array('metal' => 10), array(array(900, 910)), array());

        self::assertSame(array(), $found['rows']);
        self::assertSame(0, $found['count']);
    }

    public function testSectionsAndTheWholeReportGoThroughTemplates(): void
    {
        $section = SpyReport::section('Flotte', array(array('label' => 'Chasseur léger', 'amount' => '7')));

        self::assertStringContainsString('Chasseur léger', $section);
        self::assertStringContainsString('7', $section);
        self::assertStringContainsString('card', $section);

        // Un bloc vide garde son tiret (entité HTML, compatible latin1) et le masque
        // quand il y a des lignes.
        self::assertStringContainsString('&mdash;', SpyReport::section('Défenses', array()));
        self::assertStringNotContainsString('d-none', SpyReport::section('Défenses', array()));
        self::assertStringContainsString('d-none', $section);

        $report = SpyReport::assemble(array(
            'title' => "Rapport d'espionnage",
            'target' => 'Colonie 0',
            'coordinates' => '[1:2:3]',
            'date' => '25-09-2026 14:00:00',
            'sections' => $section,
            'hint' => "Contrôle aérospatial",
            'fate' => 'Probabilité de destruction : 12 %',
            'fate_variant' => 'warning',
            'attack_url' => '/game/fleet?galaxy=1&system=2&planet=3&target_mission=1',
            'attack_label' => 'Attaquer',
        ));

        self::assertStringContainsString('Colonie 0', $report);
        self::assertStringContainsString('[1:2:3]', $report);
        self::assertStringContainsString('target_mission=1', $report);
        self::assertStringContainsString('Chasseur léger', $report, 'Les blocs révélés sont dans le rapport.');
    }

    public function testTheResourcesTileShowsTheCoordinatesOfTheTarget(): void
    {
        $planet = array(
            'name' => 'Homeworld',
            'metal' => 1000000,
            'crystal' => 2500.5,
            'deuterium' => 12,
            'energy_max' => -40,
        );

        $block = SpyReport::resources($planet, 'Matières premières sur', array(
            'Metal' => 'Métal',
            'Crystal' => 'Cristal',
            'Deuterium' => 'Deutérium',
            'Energy' => 'Énergie',
        ), '[1:2:3]');

        self::assertStringContainsString('Homeworld', $block);
        self::assertStringContainsString('[1:2:3]', $block);
        self::assertStringContainsString('Métal', $block);
        self::assertStringContainsString('1.000.000', $block);
        // Les quantités de ressources gardent leurs décimales (3 au maximum).
        self::assertStringContainsString('2.500,5', $block);
    }
}
