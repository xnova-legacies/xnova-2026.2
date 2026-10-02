<?php

declare(strict_types=1);

/**
 * Serveur WebSocket (Workerman) — transport temps réel de XNova.
 *
 * Le navigateur ne parle plus qu'à ce canal :
 *  - à la connexion, le cookie de session du jeu est rejoué sur
 *    /game/api/state : une session absente ou expirée ferme la connexion ;
 *  - chaque requête du client est relayée à l'API HTTP (/game/api/...) avec ce
 *    cookie et le jeton CSRF fourni par la page. L'application reste la seule
 *    source de vérité : règles du jeu, sessions et CSRF ne sont pas dupliqués ;
 *  - l'état de la planète est poussé dès que son empreinte (state.revision)
 *    change, ou dès que l'affichage du navigateur devient faux (ressource gagnée
 *    ou perdue hors production : pillage, marché, attaque) ;
 *    ce qui remplace le polling du navigateur.
 *
 * Lancement : php ws/server.php start   (voir le service `ws` du compose)
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Ws\Protocol;
use App\Core\Ws\Settings;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

$appUrl = rtrim((string) (getenv('WS_APP_URL') ?: 'http://app'), '/');
$listen = (string) (getenv('WS_LISTEN') ?: '0.0.0.0:8081');
$allowedOrigin = trim((string) (getenv('WS_ALLOWED_ORIGIN') ?: ''));
$pollSeconds = max(0.5, ((int) (getenv('WS_POLL_MS') ?: 1000)) / 1000);
$apiTimeout = max(1, (int) (getenv('WS_API_TIMEOUT') ?: 5));

/** Contexte par connexion : cookie, jeton CSRF, empreinte connue, état diffusé, erreurs. */
$contexts = array();

Worker::$pidFile = sys_get_temp_dir() . '/xnova-ws.pid';
Worker::$logFile = sys_get_temp_dir() . '/xnova-ws.log';

/**
 * Appel de l'API HTTP avec le cookie de la connexion. Bloquant : les échanges
 * se font en boucle locale, ce qui coûte quelques millisecondes.
 *
 * @return array{status: int, json: ?array}
 */
function wsRequest(
    string $appUrl,
    string $cookie,
    string $method,
    string $path,
    array $payload,
    string $token,
    int $timeout
): array {
    $headers = array('Accept: application/json');

    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $options = array('http' => array(
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'timeout' => $timeout,
        'ignore_errors' => true,
    ));

    if (strtoupper($method) !== 'GET') {
        $options['http']['header'] .= "\r\nContent-Type: application/json\r\nX-CSRF-Token: " . $token;
        $options['http']['content'] = Protocol::encode($payload);
    }

    $raw = @file_get_contents($appUrl . $path, false, stream_context_create($options));

    $status = 0;
    foreach ($http_response_header ?? array() as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    $json = is_string($raw) ? json_decode($raw, true) : null;

    return array('status' => $status, 'json' => is_array($json) ? $json : null);
}

/** En-têtes HTTP de la poignée de main. Fonction pure. */
function wsHeaders(string $raw): array
{
    $headers = array();

    foreach (explode("\n", $raw) as $line) {
        $parts = explode(':', $line, 2);

        if (count($parts) === 2) {
            $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
    }

    return $headers;
}

/**
 * Pousse un état à une connexion et retient ce que le navigateur affichera.
 *
 * Le contexte conserve l'empreinte du contenu (pour ne pas rediffuser deux fois
 * la même page) ET l'état diffusé avec son instant (pour savoir si la valeur
 * affichée est encore juste, voir Protocol::displayStale()).
 */
function pushState(
    TcpConnection $connection,
    array &$context,
    array $state,
    string $revision,
    array $messages = array()
): void {
    $context['revision'] = $revision;
    $context['state'] = $state;
    $context['pushed_at'] = time();

    $connection->send(Protocol::push('state', array(
        'state' => $state,
        'messages' => $messages,
    )));
}

/**
 * L'état d'une connexion a-t-il besoin d'être poussé ? Fonction pure.
 *
 * Le contenu a changé (files, boutons, place disponible) ou l'affichage est
 * devenu faux sans que le contenu ait bougé (pillage encaissé, prime du marché,
 * changement de débit, nouveau message).
 */
function stateStale(array $context, array $state, string $revision): bool
{
    if (Protocol::changed($context['revision'] ?? '', $revision)) {
        return true;
    }

    return Protocol::displayStale(
        $context['state'] ?? array(),
        $state,
        (float) max(0, time() - (int) ($context['pushed_at'] ?? 0))
    );
}

/**
 * Pousse l'état d'une connexion si elle en a besoin.
 *
 * `$json` évite une sonde quand la réponse vient d'être obtenue (écriture
 * relayée) : l'état diffusé est alors exactement celui que l'API a renvoyé.
 *
 * @return array{status: int, json: ?array} la sonde utilisée
 */
function pushIfChanged(
    TcpConnection $connection,
    array &$context,
    string $appUrl,
    int $apiTimeout,
    ?array $json = null
): array {
    $probe = $json !== null
        ? array('status' => 200, 'json' => $json)
        : wsRequest($appUrl, $context['cookie'], 'GET', '/game/api/state', array(), '', $apiTimeout);

    if ($probe['json'] === null) {
        return $probe;
    }

    $revision = Protocol::revision($probe['json']);
    $state = Protocol::snapshot($probe['json']);

    if (stateStale($context, $state, $revision)) {
        pushState($connection, $context, $state, $revision, $probe['json']['messages'] ?? array());
        Worker::log('diffusion : connexion ' . $connection->id . ' revision ' . $revision);
    }

    return $probe;
}

$worker = new Worker('websocket://' . $listen);
$worker->name = 'xnova-ws';
$worker->count = 1;

$worker->onWebSocketConnect = function (TcpConnection $connection, $header) use (
    &$contexts,
    $appUrl,
    $allowedOrigin,
    $apiTimeout
): void {
    $headers = wsHeaders((string) $header);
    $origin = $headers['origin'] ?? '';

    if (!Protocol::originAllowed($origin, $allowedOrigin)) {
        $connection->send(Protocol::error(0, 'forbidden_origin', 'Origine refusée.'));
        $connection->close();

        return;
    }

    $cookie = $headers['cookie'] ?? '';

    if ($cookie === '') {
        $connection->send(Protocol::error(0, 'unauthenticated', 'Session absente.'));
        $connection->close();

        return;
    }

    // La session n'est valide que si l'application répond en JSON.
    $probe = wsRequest($appUrl, $cookie, 'GET', '/game/api/state', array(), '', $apiTimeout);

    if ($probe['status'] !== 200 || $probe['json'] === null || empty($probe['json']['ok'])) {
        $connection->send(Protocol::error(0, 'unauthenticated', 'Session expirée.'));
        $connection->close();

        return;
    }

    $contexts[$connection->id] = array(
        'cookie' => $cookie,
        'session' => Protocol::sessionIdOf($cookie),
        'token' => '',
        'revision' => Protocol::revision($probe['json']),
        'state' => Protocol::snapshot($probe['json']),
        'pushed_at' => time(),
        'errors' => 0,
    );

    Worker::log('session acceptee : connexion ' . $connection->id
        . ($origin !== '' ? ' (origin ' . $origin . ')' : '')
        . ($contexts[$connection->id]['session'] !== '' ? ' session ' . substr($contexts[$connection->id]['session'], 0, 8) : '')
        . ' revision ' . ($contexts[$connection->id]['revision'] ?: '-'));
};

$worker->onMessage = function (TcpConnection $connection, $data) use (&$contexts, $worker, $appUrl, $apiTimeout): void {
    if (!isset($contexts[$connection->id])) {
        $connection->send(Protocol::error(0, 'unauthenticated', 'Session expirée.'));
        $connection->close();

        return;
    }

    $message = Protocol::decode((string) $data);
    $id = is_array($message) ? Protocol::id($message) : 0;
    $action = is_array($message) ? Protocol::action($message) : null;

    if ($action === null) {
        $connection->send(Protocol::error($id, 'invalid_message', 'Message invalide.'));

        return;
    }

    // Le jeton CSRF est fourni par la page au premier message : il suit la
    // session et sert aux écritures relayées.
    if ($action === 'hello') {
        $token = $message['token'] ?? null;
        $contexts[$connection->id]['token'] = is_string($token) ? $token : '';
    }

    if ($action === 'hello' || $action === 'state') {
        $path = '/game/api/state';
    } else {
        $path = Protocol::allowedPath($message['path'] ?? null);

        if ($path === null) {
            $connection->send(Protocol::error($id, 'forbidden_path', 'Chemin refusé.'));

            return;
        }
    }

    $context = $contexts[$connection->id];
    $result = wsRequest(
        $appUrl,
        $context['cookie'],
        $action === 'post' ? 'POST' : 'GET',
        $path,
        is_array($message['payload'] ?? null) ? $message['payload'] : array(),
        $context['token'],
        $apiTimeout
    );

    if ($result['status'] === 401 || $result['status'] === 403) {
        $connection->send(Protocol::reply($id, array(
            'ok' => false,
            'error' => array(
                'code' => $result['status'] === 401 ? 'unauthenticated' : 'forbidden',
                'message' => $result['status'] === 401 ? 'Session expirée.' : 'Action refusée.',
                'fields' => array(),
            ),
        )));

        if ($result['status'] === 401) {
            unset($contexts[$connection->id]);
            $connection->close();
        }

        return;
    }

    if ($result['json'] === null) {
        $connection->send(Protocol::error($id, 'bad_response', 'Réponse serveur invalide.'));

        return;
    }

    $connection->send(Protocol::reply($id, $result['json']));

    // Le tchat est global : un message publié doit prévenir TOUTES les
    // connexions, et pas seulement les onglets d'une même session. Le signal ne
    // transporte aucun HTML (aucune règle de jeu ici) : chaque client redemande
    // la shoutbox à l'API par le canal.
    if ($action === 'post' && $path === '/game/api/chat/send') {
        $signal = Protocol::push(Protocol::EVENT_CHAT);
        $peers = 0;

        foreach ($worker->connections as $peer) {
            if ($peer->id === $connection->id || !isset($contexts[$peer->id])) {
                continue;
            }

            $peer->send($signal);
            $peers++;
        }

        Worker::log('tchat diffuse a ' . $peers . ' connexion(s)');
    }

    // L'action a pu modifier l'état : on retient l'empreinte pour ne pas la
    // rediffuser à cette connexion (elle a déjà la réponse) et on prévient
    // immédiatement les autres onglets de la même session.
    $revision = Protocol::revision($result['json']);
    $state = Protocol::snapshot($result['json']);

    if ($revision !== '') {
        $contexts[$connection->id]['revision'] = $revision;

        // La connexion qui a agi reçoit l'état dans la réponse : on retient ce
        // qu'elle va afficher, pour ne pas le lui rediffuser au cycle suivant.
        if ($state !== array()) {
            $contexts[$connection->id]['state'] = $state;
            $contexts[$connection->id]['pushed_at'] = time();
        }

        if ($action === 'post' && $state !== array()) {
            foreach ($worker->connections as $other) {
                if ($other->id === $connection->id || !isset($contexts[$other->id])) {
                    continue;
                }

                if ($contexts[$other->id]['session'] !== $contexts[$connection->id]['session']) {
                    continue;
                }

                pushState($other, $contexts[$other->id], $state, $revision);

                Worker::log('diffusion immediate : connexion ' . $other->id . ' (session partagee)');
            }
        }
    }
};

/**
 * Diffusion : l'état n'est envoyé que lorsqu'il change réellement, ce qui
 * remplace le polling par seconde du navigateur (voir P2 : notification
 * immédiate côté PHP au lieu de ce sondage).
 *
 * Le minuteur est enregistré au démarrage du worker : c'est le seul moment où
 * la boucle d'événements est en place (un Timer::add() global n'est pas fiable).
 */
$worker->onWorkerStart = function () use ($worker, &$contexts, $appUrl, $apiTimeout, $pollSeconds): void {
    Timer::add($pollSeconds, function () use ($worker, &$contexts, $appUrl, $apiTimeout): void {
        $probes = array();

        foreach ($worker->connections as $connection) {
            if (!isset($contexts[$connection->id])) {
                continue;
            }

            $context = $contexts[$connection->id];

            // Un onglet par connexion, mais une seule sonde par session : les
            // onglets d'un même joueur partagent le même état de planète.
            if (!array_key_exists($context['session'], $probes)) {
                $probes[$context['session']] = wsRequest(
                    $appUrl,
                    $context['cookie'],
                    'GET',
                    '/game/api/state',
                    array(),
                    '',
                    $apiTimeout
                );
            }

            $probe = $probes[$context['session']];

            if ($probe['status'] === 401) {
                $connection->send(Protocol::push('expired', array()));
                unset($contexts[$connection->id]);
                $connection->close();

                continue;
            }

            if ($probe['json'] === null) {
                $context['errors']++;
                $contexts[$connection->id] = $context;

                Worker::log('sonde en echec : connexion ' . $connection->id . ' (code ' . $probe['status'] . ')');

                if ($context['errors'] > 5) {
                    unset($contexts[$connection->id]);
                    $connection->close();
                }

                continue;
            }

            $context['errors'] = 0;
            pushIfChanged($connection, $context, $appUrl, $apiTimeout, $probe['json']);
            $contexts[$connection->id] = $context;
        }
    });
};

$worker->onClose = function (TcpConnection $connection) use (&$contexts): void {
    if (isset($contexts[$connection->id])) {
        Worker::log('deconnexion : connexion ' . $connection->id);
    }

    unset($contexts[$connection->id]);
};

Worker::log('xnova-ws : écoute sur ' . $listen . ' -> ' . $appUrl);

if (!Settings::enabled()) {
    // Le compose ne démarre ce service que par profil, mais un lancement manuel
    // reste possible : on signale simplement que le navigateur ne l'utilisera pas.
    Worker::log('xnova-ws : WS_ENABLED=0, le navigateur utilise le repli HTTP.');
}

Worker::runAll();
