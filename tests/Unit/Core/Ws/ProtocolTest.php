<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ws;

use App\Core\Ws\Protocol;
use PHPUnit\Framework\TestCase;

final class ProtocolTest extends TestCase
{
    public function testActionIsRestrictedToTheKnownSet(): void
    {
        self::assertSame('state', Protocol::action(array('action' => 'state')));
        self::assertSame('post', Protocol::action(array('action' => 'post')));
        self::assertNull(Protocol::action(array('action' => 'delete')));
        self::assertNull(Protocol::action(array()));
    }

    public function testIdIsAlwaysAnInteger(): void
    {
        self::assertSame(7, Protocol::id(array('id' => 7)));
        self::assertSame(7, Protocol::id(array('id' => '7')));
        self::assertSame(0, Protocol::id(array('id' => 'abc')));
        self::assertSame(0, Protocol::id(array()));
    }

    public function testAllowedPathKeepsOnlyTheApiArea(): void
    {
        self::assertSame('/game/api/state', Protocol::allowedPath('/game/api/state'));
        self::assertSame('/game/api/state', Protocol::allowedPath('game/api/state'));
        self::assertSame('/game/api/buildings/add', Protocol::allowedPath('/game/api/buildings/add'));
        self::assertSame('/game/api/chat/messages', Protocol::allowedPath('/game/api/chat/messages'));
        self::assertSame('/game/api/chat/send', Protocol::allowedPath('/game/api/chat/send'));
    }

    public function testAllowedPathRefusesEverythingElse(): void
    {
        self::assertNull(Protocol::allowedPath('/game/api/../../config.php'));
        self::assertNull(Protocol::allowedPath('/game/overview'));
        self::assertNull(Protocol::allowedPath('/admin/'));
        self::assertNull(Protocol::allowedPath('/game/api//state'));
        self::assertNull(Protocol::allowedPath('/game/api/state' . "\0"));
        self::assertNull(Protocol::allowedPath(null));
        self::assertNull(Protocol::allowedPath(42));
    }

    public function testDecodeRejectsBrokenPayloads(): void
    {
        self::assertSame(array('action' => 'state'), Protocol::decode('{"action":"state"}'));
        self::assertNull(Protocol::decode('pas du json'));
        self::assertNull(Protocol::decode('"chaine"'));
    }

    public function testOriginAllowList(): void
    {
        // Vide : tout est accepté (développement).
        self::assertTrue(Protocol::originAllowed('http://localhost:8080', ''));
        self::assertTrue(Protocol::originAllowed('', ''));

        // Sinon : correspondance exacte, y compris dans une liste.
        self::assertTrue(Protocol::originAllowed('https://jeu.exemple.org', 'https://jeu.exemple.org'));
        self::assertTrue(Protocol::originAllowed('https://jeu.exemple.org', 'https://autre.org, https://jeu.exemple.org'));
        self::assertFalse(Protocol::originAllowed('https://jeu.exemple.org', 'https://autre.org'));
        self::assertFalse(Protocol::originAllowed('', 'https://jeu.exemple.org'));
        self::assertFalse(Protocol::originAllowed('https://jeu.exemple.org.evil.net', 'https://jeu.exemple.org'));
    }

    public function testCookiesAreParsedFromTheHandshake(): void
    {
        self::assertSame(
            array('PHPSESSID' => 'abc123', 'xnova_theme' => 'dark'),
            Protocol::cookies('PHPSESSID=abc123; xnova_theme=dark')
        );
        self::assertSame(array(), Protocol::cookies(''));
        self::assertSame(array('seul' => 'valeur=avec=egal'), Protocol::cookies('seul=valeur=avec=egal'));
    }

    public function testSessionIdIsExtractedFromTheCookie(): void
    {
        self::assertSame('abc123', Protocol::sessionIdOf('PHPSESSID=abc123'));
        self::assertSame('abc123', Protocol::sessionIdOf('theme=dark; PHPSESSID=abc123; langue=fr'));
        self::assertSame('abc123', Protocol::sessionIdOf('phpsessid=abc123'));
        self::assertSame('', Protocol::sessionIdOf('theme=dark'));
        self::assertSame('', Protocol::sessionIdOf(''));
    }

    public function testReplyKeepsTheApiEnvelope(): void
    {
        $json = array('ok' => true, 'data' => array('name' => 'Terre'), 'state' => array(), 'messages' => array());

        self::assertSame(
            array('id' => 3, 'ok' => true, 'data' => array('name' => 'Terre'), 'state' => array(), 'messages' => array()),
            json_decode(Protocol::reply(3, $json), true)
        );
    }

    public function testErrorUsesTheApiContract(): void
    {
        $error = json_decode(Protocol::error(5, 'forbidden_path', 'Chemin refusé.', array('path' => 'x')), true);

        self::assertSame(5, $error['id']);
        self::assertFalse($error['ok']);
        self::assertSame('forbidden_path', $error['error']['code']);
        self::assertSame('Chemin refusé.', $error['error']['message']);
        self::assertSame(array('path' => 'x'), $error['error']['fields']);
    }

    public function testPushCarriesNoRequestId(): void
    {
        $push = json_decode(Protocol::push('state', array('state' => array('revision' => 'abc'))), true);

        self::assertSame('state', $push['event']);
        self::assertSame('abc', $push['state']['revision']);
        self::assertArrayNotHasKey('id', $push);
    }

    public function testChatEventIsASignalOnly(): void
    {
        $push = json_decode(Protocol::push(Protocol::EVENT_CHAT), true);

        self::assertSame('chat', $push['event']);
        self::assertSame(array('event' => 'chat'), $push);
        self::assertArrayNotHasKey('state', $push);
    }

    public function testSnapshotReadsBothEnvelopes(): void
    {
        // Les ecritures renvoient le cliche dans `state`.
        self::assertSame(array('revision' => 'abc'), Protocol::snapshot(array('state' => array('revision' => 'abc'))));
        // GET /game/api/state le renvoie dans `data` (state reste vide).
        self::assertSame(array('revision' => 'def'), Protocol::snapshot(array('data' => array('revision' => 'def'), 'state' => array())));
        self::assertSame(array(), Protocol::snapshot(array()));
        self::assertSame(array(), Protocol::snapshot(array('state' => array(), 'data' => 'texte')));
    }

    public function testRevisionComparison(): void
    {
        self::assertSame('abc', Protocol::revision(array('state' => array('revision' => 'abc'))));
        self::assertSame('def', Protocol::revision(array('data' => array('revision' => 'def'), 'state' => array())));
        self::assertSame('', Protocol::revision(array('state' => array())));
        self::assertSame('', Protocol::revision(array()));

        self::assertTrue(Protocol::changed('', 'abc'));
        self::assertTrue(Protocol::changed('abc', 'def'));
        self::assertFalse(Protocol::changed('abc', 'abc'));
        self::assertFalse(Protocol::changed('abc', ''));
    }

    /**
     * Etat minimal : une planete a $metal, plafonnee a $cap, avec $rate de debit
     * horaire. Crystal et deuterium ne bougent pas (base 1, plafond 2).
     */
    private static function state(float $metal, float $cap, float $rate): array
    {
        return array(
            'planet' => array(
                'resources' => array(
                    'metal' => $metal,
                    'metal_cap' => $cap,
                    'metal_max' => 1.0,
                    'crystal' => 1.0,
                    'crystal_cap' => 2.0,
                    'deuterium' => 1.0,
                    'deuterium_cap' => 2.0,
                ),
                'rates' => array('metal' => $rate, 'crystal' => 0.0, 'deuterium' => 0.0),
                'energy' => array('max' => 100.0, 'used' => -10.0),
            ),
            'user' => array('new_message' => 0, 'buddy_requests' => 0),
        );
    }

    public function testDisplayedInterpolatesProductionUnderTheStorage(): void
    {
        $state = self::state(1_000_000, 2_000_000, 3_600_000);

        self::assertSame(1_000_000.0, Protocol::displayed($state, 'metal', 0.0));
        self::assertSame(1_001_000.0, Protocol::displayed($state, 'metal', 1.0));
        // Une heure de production : le plafond du stockage arrete le compteur.
        self::assertSame(2_000_000.0, Protocol::displayed($state, 'metal', 3600.0));
    }

    public function testDisplayedNeverHidesWhatTheServerStores(): void
    {
        // Pillage au-dela du stockage : le serveur detient 5 000 000 et le
        // plafond (2 000 000) ne doit pas faire afficher moins.
        $state = self::state(5_000_000, 2_000_000, 3_600_000);

        self::assertSame(5_000_000.0, Protocol::displayed($state, 'metal', 0.0));
        self::assertSame(5_000_000.0, Protocol::displayed($state, 'metal', 600.0));
    }

    public function testDisplayStaleIgnoresNormalProduction(): void
    {
        $previous = self::state(1_000_000, 2_000_000, 3_600_000);
        // Trois secondes de production : l'interpolation affiche deja la bonne valeur.
        $current = self::state(1_003_000, 2_000_000, 3_600_000);

        self::assertFalse(Protocol::displayStale($previous, $current, 3.0));
    }

    public function testDisplayStaleDetectsResourcesGainedOutsideProduction(): void
    {
        $previous = self::state(1_000_000, 2_000_000, 3_600_000);
        // Pillage de 500 000 : le client n'affiche que la production.
        $current = self::state(1_503_000, 2_000_000, 3_600_000);

        self::assertTrue(Protocol::displayStale($previous, $current, 3.0));
    }

    public function testDisplayStaleDetectsLosses(): void
    {
        $previous = self::state(1_000_000, 2_000_000, 3_600_000);
        // Attaque subie : le serveur enregistre moins que l'interpolation.
        $current = self::state(400_000, 2_000_000, 3_600_000);

        self::assertTrue(Protocol::displayStale($previous, $current, 3.0));
    }

    public function testDisplayStaleDetectsARateThatCollapses(): void
    {
        $previous = self::state(1_000_000, 2_000_000, 3_600_000);
        // Energie coupee : la production s'arrete, l'affichage continue de grimper.
        $current = self::state(1_000_000, 2_000_000, 0.0);

        self::assertTrue(Protocol::displayStale($previous, $current, 30.0));
    }

    public function testDisplayStaleToleratesClockSkew(): void
    {
        $previous = self::state(1_000_000, 2_000_000, 3_600_000);
        // Une seconde d'avance sur l'horloge du serveur : moins que la tolerance.
        $current = self::state(1_004_000, 2_000_000, 3_600_000);

        self::assertFalse(Protocol::displayStale($previous, $current, 3.0));
    }

    public function testDisplayStaleDetectsEnergyAndMessages(): void
    {
        $previous = self::state(1_000_000, 2_000_000, 3_600_000);
        $energy = self::state(1_000_000, 2_000_000, 3_600_000);
        $energy['planet']['energy'] = array('max' => 100.0, 'used' => -50.0);
        $message = self::state(1_000_000, 2_000_000, 3_600_000);
        $message['user']['new_message'] = 3;

        self::assertFalse(Protocol::displayStale($previous, self::state(1_000_000, 2_000_000, 3_600_000), 0.0));
        self::assertTrue(Protocol::displayStale($previous, $energy, 0.0));
        self::assertTrue(Protocol::displayStale($previous, $message, 0.0));
    }

    public function testDisplayStaleDetectsAFriendRequest(): void
    {
        // Demande d'ami reçue : le badge de la barre de navigation doit apparaître
        // sans attendre un rechargement de page.
        $previous = self::state(1_000_000, 2_000_000, 3_600_000);
        $request = self::state(1_000_000, 2_000_000, 3_600_000);
        $request['user']['buddy_requests'] = 1;

        self::assertTrue(Protocol::displayStale($previous, $request, 0.0));
        self::assertFalse(Protocol::displayStale($previous, self::state(1_000_000, 2_000_000, 3_600_000), 0.0));
    }

    public function testDisplayStaleNeedsTwoStates(): void
    {
        // Rien de diffuse : la poignee de main a deja envoye l'etat au client.
        self::assertFalse(Protocol::displayStale(array(), self::state(1.0, 2.0, 3.0), 3.0));
        self::assertFalse(Protocol::displayStale(self::state(1.0, 2.0, 3.0), array(), 3.0));
    }
}
