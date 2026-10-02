<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\FleetBar;
use App\Services\FlyingFleetService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Le bandeau est injecté dans chaque page HTML : le rendu et l'insertion sont
 * testés ici (aucune base de données), l'alimentation réelle étant faite par
 * FlyingFleetService.
 */
final class FleetBarTest extends TestCase
{
    private array $userBackup = [];
    private bool $hadUser = false;

    protected function setUp(): void
    {
        $this->hadUser = array_key_exists('user', $GLOBALS);
        $this->userBackup = $GLOBALS['user'] ?? [];
    }

    protected function tearDown(): void
    {
        if ($this->hadUser) {
            $GLOBALS['user'] = $this->userBackup;
        } else {
            unset($GLOBALS['user']);
        }
    }

    /** @return array<string, mixed> */
    private function labels(): array
    {
        return FleetBar::labels(array(
            'type_mission' => array(3 => 'Transport', 10 => 'Mise en orbite', 15 => 'Expédition'),
            'tech' => array(202 => 'Petit transporteur', 203 => 'Grand transporteur', 210 => 'Espionnage'),
        ));
    }

    private function entry(array $extra = array()): array
    {
        return array_merge(array(
            'kind' => FlyingFleetService::EVENT_ARRIVAL,
            'time' => time() + 3661,
            'fleet_id' => 4,
            'mission' => 3,
            'incoming' => false,
            'returning' => false,
            'ships' => 12,
            'units' => array(202 => 7, 203 => 5),
            'from' => array('galaxy' => 1, 'system' => 2, 'planet' => 3, 'type' => 1),
            'to' => array('galaxy' => 4, 'system' => 5, 'planet' => 6, 'type' => 3),
        ), $extra);
    }

    public function testLabelsFallBackToFrenchStrings(): void
    {
        $labels = $this->labels();

        self::assertSame('Flottes en vol', $labels['title']);
        self::assertSame('Transport', $labels['missions'][3]);
        self::assertSame('retour', $labels['return']);
    }

    public function testEmptyListExplainsThereIsNothingFlying(): void
    {
        $html = FleetBar::renderList(array(), $this->labels());

        self::assertStringContainsString('Aucune flotte en vol', $html);
        self::assertStringNotContainsString('data-fleet-end', $html);
    }

    public function testItemCarriesMissionRouteAndCountdown(): void
    {
        $html = FleetBar::renderItem($this->entry(), $this->labels());

        self::assertStringContainsString('Transport', $html);
        self::assertStringContainsString('[1:2:3]', $html);
        // Position de type lune : le suffixe distingue la lune de la planète.
        self::assertStringContainsString('[4:5:6]', $html);
        self::assertStringContainsString('(L)', $html);
        self::assertStringContainsString('data-fleet-end="' . (time() + 3661) . '"', $html);
        self::assertStringContainsString('1:01:0', $html);
        self::assertStringContainsString('arrivée', $html);
        self::assertStringNotContainsString('xnova-fleets-item-incoming', $html);
    }

    public function testItemExposesTheShipBreakdownInADetailsElement(): void
    {
        $html = FleetBar::renderItem($this->entry(), $this->labels());

        // Repliable sans script : le détail est dans la ligne, pas dans une infobulle
        // native (l'ancien attribut `title` a été retiré).
        self::assertStringContainsString('<details class="xnova-fleets-detail">', $html);
        self::assertStringContainsString('vaisseaux : 12', $html);
        self::assertStringContainsString('Petit transporteur', $html);
        self::assertStringContainsString('Grand transporteur', $html);
        self::assertStringContainsString('<span class="xnova-fleets-ships-count">7</span>', $html);
        self::assertStringContainsString('<span class="xnova-fleets-ships-count">5</span>', $html);
        self::assertStringNotContainsString('title="', $html);
    }

    public function testItemWithoutUnitsShowsTheTotalOnly(): void
    {
        $html = FleetBar::renderItem($this->entry(array('units' => array())), $this->labels());

        self::assertStringContainsString('vaisseaux : 12', $html);
        self::assertStringNotContainsString('<details', $html);
    }

    public function testOrbitingFleetIsDisplayedWithoutCountdown(): void
    {
        // Une flotte en orbite reste affichée, sans échéance à décompter : pas
        // d'attribut `data-fleet-end`, donc rien que le client puisse rafraîchir.
        $html = FleetBar::renderItem(
            $this->entry(array(
                'kind' => FlyingFleetService::EVENT_ORBIT,
                'mission' => 10,
                'time' => PHP_INT_MAX,
            )),
            $this->labels()
        );

        self::assertStringContainsString('Mise en orbite', $html);
        self::assertStringContainsString('en orbite', $html);
        self::assertStringContainsString('text-bg-primary', $html);
        self::assertStringNotContainsString('data-fleet-end', $html);
        self::assertStringNotContainsString('xnova-fleets-countdown', $html);
    }

    public function testIncomingItemIsFlagged(): void
    {
        $html = FleetBar::renderItem(
            $this->entry(array('incoming' => true, 'kind' => FlyingFleetService::EVENT_INCOMING, 'mission' => 1)),
            $this->labels()
        );

        self::assertStringContainsString('xnova-fleets-item-incoming', $html);
        self::assertStringContainsString('text-bg-danger', $html);
    }

    public function testMissileAttackIsDisplayedWithItsCount(): void
    {
        $html = FleetBar::renderItem(
            $this->entry(array(
                'kind' => FlyingFleetService::EVENT_MISSILE,
                'mission' => 0,
                'incoming' => true,
                'ships' => 8,
                'units' => array(),
            )),
            $this->labels()
        );

        self::assertStringContainsString('Attaque de missiles', $html);
        self::assertStringContainsString('missiles : 8', $html);
        // La composition d'une attaque de missiles n'est pas connue du jeu.
        self::assertStringNotContainsString('<details', $html);
    }

    public function testRenderWrapsTheListAndPublishesTheFingerprint(): void
    {
        $entry = $this->entry();
        $html = FleetBar::render(array($entry), $this->labels());
        $fingerprint = FlyingFleetService::fingerprint(array($entry));

        self::assertStringContainsString('id="' . FleetBar::MARKER . '"', $html);
        self::assertStringContainsString('id="' . FleetBar::LIST_ID . '"', $html);
        self::assertStringContainsString('data-fleet-count="1"', $html);
        self::assertStringContainsString('data-fleet-fingerprint="' . $fingerprint . '"', $html);
        // Replié par défaut : c'est le script qui l'ouvre (choix mémorisé).
        self::assertStringContainsString('panel d-none', $html);
    }

    public function testInjectPlacesTheBarBeforeTheEndOfBody(): void
    {
        $page = '<html><body><p>Page</p></body></html>';

        $result = FleetBar::inject($page, '<div id="xnova-fleets-bar"></div>');

        self::assertStringContainsString('<p>Page</p>', $result);
        self::assertSame(
            '<html><body><p>Page</p><div id="xnova-fleets-bar"></div></body></html>',
            $result
        );
    }

    public function testInjectDoesNothingWithoutBodyOrFragment(): void
    {
        self::assertSame('{"ok":true}', FleetBar::inject('{"ok":true}', '<div></div>'));
        self::assertSame('<html></html>', FleetBar::inject('<html></html>', '<div></div>'));
        self::assertSame('<body></body>', FleetBar::inject('<body></body>', ''));
    }

    public function testShouldInjectOnlyOnGamePages(): void
    {
        $page = '<body></body>';
        $GLOBALS['user'] = array('id' => 7);

        self::assertTrue(FleetBar::shouldInject($page));

        // Déjà présente, réponse JSON ou session absente : rien à injecter.
        self::assertFalse(FleetBar::shouldInject('<body>xnova-fleets-bar</body>'));
        self::assertFalse(FleetBar::shouldInject('{"ok":true}'));
        self::assertFalse(FleetBar::shouldInject(''));

        unset($GLOBALS['user']);
        self::assertFalse(FleetBar::shouldInject($page));
    }

    /**
     * Fenêtres surgissantes (notes, liste d'amis) : page nue, donc pas de bandeau.
     * La constante est définie par le contrôleur concerné, comme LOGIN pour la
     * connexion ; le test tourne isolé pour ne pas la laisser définie ailleurs.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testShouldNotInjectIntoPopupPages(): void
    {
        define('POPUP', true);
        $GLOBALS['user'] = array('id' => 7);

        self::assertFalse(FleetBar::shouldInject('<body></body>'));
    }
}
