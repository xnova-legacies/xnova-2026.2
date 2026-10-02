<?php

declare(strict_types=1);

namespace App\Core\Ws;

/**
 * Protocole du canal WebSocket.
 *
 * Le navigateur n'appelle plus /game/api directement : il envoie des requêtes
 * {id, action, path, payload} et reçoit soit une réponse {id, ok|error, ...}
 * (même tableau que l'API JSON), soit un événement poussé {event, ...}.
 *
 * Le serveur WebSocket relaie ces actions vers l'API HTTP, qui reste la seule
 * source de vérité (règles du jeu, session, CSRF). Aucune logique de jeu n'est
 * dupliquée ici : ce fichier ne contient que de la mise en forme et des
 * contrôles, donc uniquement des fonctions pures.
 */
final class Protocol
{
    /** Actions acceptées depuis le navigateur. */
    public const ACTIONS = array('hello', 'state', 'get', 'post');

    /** Seul préfixe d'API relayable. */
    public const API_PREFIX = 'game/api/';

    /**
     * Événement poussé quand le tchat a changé.
     *
     * Le signal ne transporte ni HTML ni état : le rendu reste en PHP, le client
     * redemande /game/api/chat/messages par le canal.
     */
    public const EVENT_CHAT = 'chat';

    /** Action demandée, ou null si le message est illisible. Fonction pure. */
    public static function action(array $message): ?string
    {
        $action = $message['action'] ?? null;

        return is_string($action) && in_array($action, self::ACTIONS, true) ? $action : null;
    }

    /** Identifiant de requête (0 si absent). Fonction pure. */
    public static function id(array $message): int
    {
        $id = $message['id'] ?? null;

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * Chemin d'API autorisé, ou null. On refuse tout ce qui sort de /game/api/
     * ou tente de remonter l'arborescence. Fonction pure.
     */
    public static function allowedPath(mixed $path): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        // Contrôlé avant normalisation : trim() retire l'octet nul de ses
        // caractères par défaut, ce qui blanchirait un chemin piégé.
        foreach (array('..', "\0", '\\') as $forbidden) {
            if (str_contains($path, $forbidden)) {
                return null;
            }
        }

        $path = '/' . ltrim(trim($path), '/');

        if (!str_starts_with(ltrim($path, '/'), self::API_PREFIX) || str_contains($path, '//')) {
            return null;
        }

        return $path;
    }

    /** Décode un message client, ou null s'il n'est pas exploitable. Fonction pure. */
    public static function decode(string $raw): ?array
    {
        $message = json_decode($raw, true);

        return is_array($message) ? $message : null;
    }

    /**
     * Origine acceptée pour la poignée de main. Une valeur vide accepte tout
     * (développement) ; sinon l'origine doit figurer dans la liste séparée par
     * des virgules (production). Fonction pure.
     */
    public static function originAllowed(string $origin, string $allowed): bool
    {
        if (trim($allowed) === '') {
            return true;
        }

        foreach (explode(',', $allowed) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '' && $candidate === $origin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cookies d'une poignée de main, sous forme nom => valeur. Fonction pure.
     *
     * @return array<string, string>
     */
    public static function cookies(string $header): array
    {
        $cookies = array();

        foreach (explode(';', $header) as $pair) {
            $parts = explode('=', trim($pair), 2);

            if (count($parts) === 2 && $parts[0] !== '') {
                $cookies[$parts[0]] = $parts[1];
            }
        }

        return $cookies;
    }

    /**
     * Identifiant de session porté par un en-tête Cookie ('' si absent).
     * Il sert à relier les connexions d'un même joueur : plusieurs onglets
     * partagent la même session, donc le même état de planète. Fonction pure.
     */
    public static function sessionIdOf(string $header): string
    {
        foreach (self::cookies($header) as $name => $value) {
            if (stripos($name, 'phpsessid') !== false) {
                return $value;
            }
        }

        return '';
    }

    /** Tableau d'erreur, identique à celle de l'API HTTP. Fonction pure. */
    public static function error(int $id, string $code, string $message, array $fields = array()): string
    {
        return self::encode(array(
            'id' => $id,
            'ok' => false,
            'error' => array('code' => $code, 'message' => $message, 'fields' => $fields),
        ));
    }

    /** Réponse à une requête : le tableau de l'API est recopiée telle quelle. */
    public static function reply(int $id, array $json): string
    {
        return self::encode(array_merge(array('id' => $id), $json));
    }

    /** Événement poussé par le serveur (sans identifiant de requête). */
    public static function push(string $event, array $data = array()): string
    {
        return self::encode(array_merge(array('event' => $event), $data));
    }

    /** Encodage JSON du canal. Fonction pure. */
    public static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }

    /**
     * Cliché d'état contenu dans une réponse : `state` (écritures) ou `data`
     * (GET /game/api/state, qui renvoie le cliché dans `data`). Fonction pure.
     */
    public static function snapshot(array $json): array
    {
        $state = $json['state'] ?? null;

        if (is_array($state) && $state !== array()) {
            return $state;
        }

        $data = $json['data'] ?? null;

        return is_array($data) ? $data : array();
    }

    /**
     * Empreinte de l'état : elle sert à ne pousser que ce qui a réellement
     * changé. Fonction pure.
     */
    public static function revision(array $json): string
    {
        $revision = self::snapshot($json)['revision'] ?? null;

        return is_string($revision) ? $revision : '';
    }

    /** L'état a-t-il changé depuis la dernière diffusion ? Fonction pure. */
    public static function changed(string $previous, string $current): bool
    {
        return $current !== '' && $current !== $previous;
    }

    /** Ressources affichées par la barre de navigation. */
    public const DISPLAY_RESOURCES = array('metal', 'crystal', 'deuterium');

    /**
     * Tolérance d'affichage, exprimée en secondes de production : elle absorbe
     * l'écart d'horloge entre le navigateur et le serveur, toujours très
     * inférieur à cet intervalle, alors qu'une ressource gagnée ou perdue d'un
     * coup (pillage, marché, attaque) le dépasse toujours.
     */
    public const DISPLAY_TOLERANCE_SECONDS = 2.0;

    /**
     * Valeur qu'affiche le navigateur s'il interpole depuis cet état.
     *
     * Même règle que scripts/xnova-live.js : valeur reçue + débit horaire x
     * secondes écoulées, plafonnée au stockage étendu, et **jamais** inférieure à
     * la valeur du serveur (un pillage ou une prime du marchand déposent des
     * ressources au-delà du stockage, et elles s'affichent en rouge).
     * Fonction pure.
     */
    public static function displayed(array $state, string $key, float $seconds): float
    {
        $planet = $state['planet'] ?? array();
        $base = (float) ($planet['resources'][$key] ?? 0);
        $cap = (float) ($planet['resources'][$key . '_cap'] ?? 0);
        $value = $base + (float) ($planet['rates'][$key] ?? 0) * $seconds / 3600;
        $ceiling = $cap > $base ? $cap : $base;

        return $value > $ceiling ? $ceiling : $value;
    }

    /**
     * L'affichage du navigateur est-il devenu faux ?
     *
     * L'interpolation ne peut pas deviner ce qui ne vient pas de la production :
     * un pillage, une prime du marché, une attaque subie, un changement de débit
     * (énergie, officier, mine), un nouveau message, une demande d'ami ou un
     * changement de planète.
     * L'empreinte du contenu (`state.revision`) ne bouge pas dans ces cas : sans
     * cette comparaison, la barre de navigation resterait figée sur une valeur
     * fausse jusqu'au prochain rechargement de page. Fonction pure.
     */
    public static function displayStale(array $previous, array $current, float $seconds): bool
    {
        if ($previous === array() || $current === array()) {
            return false;
        }

        foreach (self::DISPLAY_RESOURCES as $key) {
            $expected = self::displayed($previous, $key, $seconds);
            $actual = (float) ($current['planet']['resources'][$key] ?? 0);
            $tolerance = max(1.0, abs(self::rate($previous, $key)) * self::DISPLAY_TOLERANCE_SECONDS / 3600);

            if (abs($actual - $expected) > $tolerance) {
                return true;
            }
        }

        // Valeurs affichées sans débit à interpoler : elles ne changent que sur
        // un événement, donc toute différence signifie un affichage périmé.
        return self::energy($previous) !== self::energy($current)
            || self::messages($previous) !== self::messages($current)
            || self::requests($previous) !== self::requests($current);
    }

    /** Débit horaire affiché par un état (0 si absent). Fonction pure. */
    private static function rate(array $state, string $key): float
    {
        return (float) ($state['planet']['rates'][$key] ?? 0);
    }

    /** Énergie affichée (max, utilisée), sous forme comparable. Fonction pure. */
    private static function energy(array $state): array
    {
        $energy = $state['planet']['energy'] ?? array();

        return array((float) ($energy['max'] ?? 0), (float) ($energy['used'] ?? 0));
    }

    /** Nombre de messages non lus affiché. Fonction pure. */
    private static function messages(array $state): int
    {
        return (int) ($state['user']['new_message'] ?? 0);
    }

    /** Nombre de demandes d'ami en attente affiché. Fonction pure. */
    private static function requests(array $state): int
    {
        return (int) ($state['user']['buddy_requests'] ?? 0);
    }
}
