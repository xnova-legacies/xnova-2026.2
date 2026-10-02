<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ActionService;
use PHPUnit\Framework\TestCase;

/**
 * Journal des actions : parties pures (ce qui n'est pas journalisé, la nature
 * d'une route, le détail et les libellés). Aucun accès à MySQL.
 */
final class ActionServiceTest extends TestCase
{
    public function testPollingRoutesAreNotActions(): void
    {
        // Les sondages et les fragments rafraîchissent une page : ce ne sont pas
        // des actions du joueur.
        self::assertTrue(ActionService::ignored('/game/api/state'));
        self::assertTrue(ActionService::ignored('game/api/fleets'));
        self::assertTrue(ActionService::ignored('/game/api/chat/messages'));

        self::assertFalse(ActionService::ignored('game/buildings'));
        self::assertFalse(ActionService::ignored('game/api/fleet/send'));
    }

    public function testKindTellsPagesFromApiCalls(): void
    {
        self::assertSame('api', ActionService::kind('game/api/buildings/add'));
        self::assertSame('api', ActionService::kind('/game/api/fleet/send'));
        self::assertSame('page', ActionService::kind('game/buildings'));
        self::assertSame('page', ActionService::kind('/game/overview'));
        self::assertSame('page', ActionService::kind('game/apiculteur'));
    }

    public function testDetailKeepsOnlyTheQueryString(): void
    {
        self::assertSame('', ActionService::detail('/game/buildings'));
        self::assertSame('mode=fleet', ActionService::detail('/game/buildings?mode=fleet'));
        self::assertSame('a=1&b=2', ActionService::detail('/game/overview?a=1&b=2'));
        self::assertSame(ActionService::MAX_DETAIL, mb_strlen(ActionService::detail('/x?' . str_repeat('a', 400))));
    }

    public function testAnUnknownAddressIsItsOwnKind(): void
    {
        self::assertSame('unknown', ActionService::KIND_UNKNOWN);
        self::assertContains(ActionService::KIND_UNKNOWN, ActionService::KINDS);
        self::assertSame('unknown', ActionService::kindFilter('unknown'));
        // Le chemin brut garde sa nature de page : c'est `journalUnknown()` qui la marque.
        self::assertSame('page', ActionService::kind('game/inconnu-bidon'));
    }

    public function testThePayloadSummaryHidesSecretsAndBoundsValues(): void
    {
        $summary = ActionService::summarize(array(
            'username' => 'admin',
            'password' => 'secret123',
            '_token' => 'abcdef',
            'element' => '15',
            'units' => array('401' => 3),
            'long' => str_repeat('x', 400),
        ));

        self::assertStringContainsString('username=admin', $summary);
        self::assertStringNotContainsString('secret123', $summary);
        self::assertStringContainsString('password=***', $summary);
        self::assertStringContainsString('_token=***', $summary);
        self::assertStringContainsString('element=15', $summary);
        self::assertStringContainsString('units.401=3', $summary, 'Un tableau est aplati.');
        self::assertStringNotContainsString(str_repeat('x', 200), $summary, 'Une valeur est bornée.');
    }

    public function testThePayloadSummaryReadsJsonBodies(): void
    {
        // Les écritures de l'API JSON n'alimentent pas `$_POST`.
        $summary = ActionService::summarize(array(), '{"kind":"defense","units":{"401":3}}');

        self::assertSame('kind=defense&units.401=3', $summary);
        self::assertSame('', ActionService::summarize(array(), 'pas du json'));
    }

    public function testThePayloadSummaryStopsAtTheFieldLimit(): void
    {
        $fields = array();

        for ($index = 0; $index < 60; $index++) {
            $fields['champ' . $index] = (string) $index;
        }

        self::assertSame(
            ActionService::MAX_FIELDS,
            substr_count(ActionService::summarize($fields), '=')
        );
    }

    public function testAResumedActionNamesTheElements(): void
    {
        $lang = array('tech' => array(15 => 'Usine de robots', 401 => 'Lanceur de missiles'));

        self::assertSame(
            'element=15 (Usine de robots), units.401=3 (Lanceur de missiles)',
            ActionService::describe($lang, 'element=15&units.401=3')
        );

        // Un champ qui ne désigne pas un élément garde sa valeur brute.
        self::assertSame('count=15, mode=fleet', ActionService::describe($lang, 'count=15&mode=fleet'));
        self::assertSame('', ActionService::describe($lang, ''));
        self::assertSame('element=99', ActionService::describe($lang, 'element=99'), 'Identifiant inconnu.');
    }

    public function testFiltersAreBoundedToTheirKnownValues(): void
    {
        self::assertSame(30, ActionService::daysFilter(null));
        self::assertSame(30, ActionService::daysFilter('hier'));
        self::assertSame(7, ActionService::daysFilter('7'));
        self::assertSame(90, ActionService::daysFilter('90'));

        self::assertSame('all', ActionService::kindFilter('inconnu'));
        self::assertSame('api', ActionService::kindFilter('API'));
        self::assertSame('page', ActionService::kindFilter('page'));
    }

    public function testLabelsFallBackOnTheRoute(): void
    {
        $lang = array('act_api_fleet_send' => 'Flotte envoyée', 'act_pseudo_changed' => 'Changement de pseudo');

        self::assertSame('Flotte envoyée', ActionService::label($lang, 'game/api/fleet/send'));
        // Route inconnue : le chemin brut reste lisible pour un administrateur.
        self::assertSame('game/inconnu', ActionService::label($lang, 'game/inconnu'));
        // Clé absente de la langue : repli sur le chemin.
        self::assertSame('game/api/market', ActionService::label(array(), 'game/api/market'));

        // Un changement de pseudo a son propre libellé, quelle que soit la route.
        self::assertSame(
            'Changement de pseudo',
            ActionService::label($lang, 'game/api/options/save', 'db_character=Nouveau&pseudo_avant=Ancien')
        );
        self::assertContains('db_character', ActionService::USERNAME_FIELDS, 'Le champ du pseudo est celui du jeu.');
    }

    public function testFieldsPreferTheFormAndFallBackOnJson(): void
    {
        self::assertSame(array('a' => '1'), ActionService::fields(array('a' => '1'), '{"b":2}'));
        self::assertSame(array('b' => 2), ActionService::fields(array(), '{"b":2}'));
        self::assertSame(array(), ActionService::fields(array(), 'pas du json'));
        self::assertSame(array(), ActionService::fields(array(), ''));
    }

    public function testTheRetentionWindowIsCountedInDays(): void
    {
        $now = 1_800_000_000;

        self::assertSame($now - (7 * 86400), ActionService::from(7, $now));
        self::assertSame($now - 86400, ActionService::from(0, $now), 'Une période nulle vaut un jour.');
        self::assertSame(90, ActionService::RETENTION_DAYS);
    }
}
