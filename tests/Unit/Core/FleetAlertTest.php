<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\FleetAlert;
use PHPUnit\Framework\TestCase;

/**
 * L'alerte d'attaque ne montre que les vols hostiles, et elle doit s'insérer en
 * haut de page sans jamais abîmer le reste du document.
 */
final class FleetAlertTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $langBackup = [];

    protected function setUp(): void
    {
        $this->langBackup = $GLOBALS['lang'] ?? array();
        $GLOBALS['lang'] = array(
            'type_mission' => array(1 => 'Attaquer', 2 => 'Attaque groupée'),
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['lang'] = $this->langBackup;
    }

    /** @param array<string, mixed> $extra */
    private static function entry(bool $incoming, array $extra = array()): array
    {
        return array_merge(array(
            'mission' => 1,
            'incoming' => $incoming,
            'kind' => 'arrival',
            'time' => time() + 300,
            'from' => array('galaxy' => 1, 'system' => 28, 'planet' => 4),
            'to' => array('galaxy' => 1, 'system' => 1, 'planet' => 1),
        ), $extra);
    }

    public function testOnlyHostileFlightsAreKept(): void
    {
        $entries = array(
            self::entry(false),
            self::entry(true),
            self::entry(false),
            self::entry(true, array('mission' => 6)),
        );

        self::assertCount(2, FleetAlert::hostiles($entries));
        self::assertCount(2, FleetAlert::hostiles(array(self::entry(true), self::entry(true))));
        self::assertSame(array(), FleetAlert::hostiles(array(self::entry(false))));
    }

    public function testNothingToReportRendersNothing(): void
    {
        self::assertSame('', FleetAlert::render(array(self::entry(false)), FleetAlert::labels(array())));
        self::assertSame('', FleetAlert::render(array(), FleetAlert::labels(array())));
    }

    public function testTheAlertNamesTheMissionAndTheRoute(): void
    {
        $html = FleetAlert::render(array(self::entry(true)), FleetAlert::labels($GLOBALS['lang']));

        self::assertStringContainsString('Attaque en approche', $html);
        self::assertStringContainsString('Une flotte hostile vise une de vos positions', $html);
        self::assertStringContainsString('Attaquer', $html);
        self::assertStringContainsString('[1:28:4]', $html);
        self::assertStringContainsString('[1:1:1]', $html);
        self::assertStringContainsString('data-fleet-alert-end', $html);
    }

    public function testAMissileAttackHasItsOwnLabel(): void
    {
        $entry = self::entry(true, array('kind' => 'missile', 'mission' => 0));
        $html = FleetAlert::render(array($entry), FleetAlert::labels($GLOBALS['lang']));

        self::assertStringContainsString('Attaque de missiles', $html);
    }

    public function testTheAlertIsInsertedJustAfterTheBodyTag(): void
    {
        $html = "<!DOCTYPE html>\n<html>\n<body class=\"page\">\n<p>jeu</p>\n</body>";

        self::assertSame(
            "<!DOCTYPE html>\n<html>\n<body class=\"page\"><div>alerte</div>\n<p>jeu</p>\n</body>",
            FleetAlert::inject($html, '<div>alerte</div>')
        );
    }

    public function testAnEmptyAlertOrAnUnknownDocumentLeavesThePageUntouched(): void
    {
        self::assertSame('<html></html>', FleetAlert::inject('<html></html>', ''));
        self::assertSame('<html></html>', FleetAlert::inject('<html></html>', '<div>alerte</div>'));
    }

    public function testTheAlertIsNeverInsertedTwice(): void
    {
        $html = '<body>' . FleetAlert::MARKER . '<div>alerte</div></body>';

        self::assertSame($html, FleetAlert::inject($html, '<div>alerte</div>'));
    }
}
