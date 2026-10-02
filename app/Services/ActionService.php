<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\ActionRepository;
use App\Repositories\UserRepository;

/**
 * Journal des actions des joueurs.
 *
 * Une ligne par action demandée au serveur : la page ouverte ou la route JSON
 * appelée, avec la méthode HTTP (un POST est une écriture) et l'adresse du
 * joueur. Les robots ne sont jamais journalisés (leur activité se lit dans leurs
 * tours de jeu), et les sondages qui rafraîchissent une page ne sont pas des
 * actions : ils sont listés dans `IGNORED`.
 *
 * Le journal est borné : `RETENTION_DAYS` jours, purgés à l'ouverture de la page
 * d'administration (comme les sessions inactives, sans tâche de fond).
 */
final class ActionService
{
    /** Lectures répétées qui ne sont pas des actions (sondages, fragments). */
    public const IGNORED = array(
        'game/api/state',
        'game/api/fleets',
        'game/api/chat/messages',
    );

    /** Natures d'action : une page servie, une route JSON, une adresse inconnue. */
    public const KINDS = array('all', 'page', 'api', 'unknown');

    /** Nature d'une adresse qui ne correspond à aucune route. */
    public const KIND_UNKNOWN = 'unknown';

    /** Périodes proposées par la page, en jours. */
    public const DAYS = array(7, 30, 90);

    public const RECENT_LIMIT = 100;
    public const TOTALS_LIMIT = 25;
    public const RETENTION_DAYS = 90;
    public const MAX_DETAIL = 255;
    public const MAX_PAYLOAD = 1000;
    public const MAX_VALUE = 120;
    public const MAX_FIELDS = 24;

    /** Champs jamais écrits dans le journal : un mot de passe n'a rien à y faire. */
    public const SECRET_PATTERN = 'pass|md5|token|csrf|secret|pw';

    /** Champs qui portent le pseudo d'un compte (formulaires du jeu). */
    public const USERNAME_FIELDS = array('db_character', 'username');

    /** Libellé lisible des routes connues (le chemin brut sert de repli). */
    public const LABELS = array(
        'game/overview' => 'act_game_overview',
        'game/buildings' => 'act_game_buildings',
        'game/fleet' => 'act_game_fleet',
        'game/galaxy' => 'act_game_galaxy',
        'game/marchand' => 'act_game_marchand',
        'game/chat' => 'act_game_chat',
        'game/profil/messages' => 'act_game_messages',
        'game/profil/options' => 'act_game_options',
        'game/profil/officier' => 'act_game_officer',
        'game/profil/notes' => 'act_game_notes',
        'game/alliance' => 'act_game_alliance',
        'game/stat' => 'act_game_stats',
        'game/records' => 'act_game_records',
        'game/api/buildings/add' => 'act_api_buildings_add',
        'game/api/buildings/destroy' => 'act_api_buildings_destroy',
        'game/api/buildings/cancel' => 'act_api_buildings_cancel',
        'game/api/queues/reorder' => 'act_api_queues',
        'game/api/research/start' => 'act_api_research_start',
        'game/api/research/cancel' => 'act_api_research_cancel',
        'game/api/shipyard/add' => 'act_api_shipyard_add',
        'game/api/fleet/send' => 'act_api_fleet_send',
        'game/api/fleet/estimate' => 'act_api_fleet_estimate',
        'game/api/market/buy' => 'act_api_market_buy',
        'game/api/market/sell' => 'act_api_market_sell',
        'game/api/marchand/exchange' => 'act_api_marchand',
        'game/api/messages/send' => 'act_api_messages_send',
        'game/api/notes/save' => 'act_api_notes_save',
        'game/api/notes/delete' => 'act_api_notes_delete',
        'game/api/profil/rename' => 'act_api_rename',
        'game/api/options/save' => 'act_api_options_save',
        'game/api/options/vacation' => 'act_api_vacation',
        'game/api/chat/send' => 'act_api_chat_send',
        'game/api/alliance/make' => 'act_api_alliance_make',
        'game/api/alliance/apply' => 'act_api_alliance_apply',
        'game/api/alliance/leave' => 'act_api_alliance_leave',
        'game/api/alliance/circular' => 'act_api_alliance_circular',
        'game/api/alliance/rename' => 'act_api_alliance_rename',
        'game/api/alliance/request' => 'act_api_alliance_request',
        'game/api/resources/percent' => 'act_api_percent',
    );

    /** Un robot est interrogé une fois par requête, pas à chaque action. */
    private static array $bots = array();

    private readonly ActionRepository $actions;
    private readonly UserRepository $users;

    /**
     * Le dépôt est **résolu** (`ModuleService`) : le module qui possède la colonne des
     * robots dérive la classe du Coeur d'application, et c'est la sienne qui répond.
     */
    public function __construct(?ActionRepository $actions = null)
    {
        $this->actions = $actions ?? ModuleService::instance(ActionRepository::class);
        $this->users = new UserRepository();
    }

    // ------------------------------------------------------------ parties pures

    /** Un sondage ou un fragment n'est pas une action. */
    public static function ignored(string $path): bool
    {
        return in_array(ltrim($path, '/'), self::IGNORED, true);
    }

    /** Nature d'une route : JSON ou page servie. */
    public static function kind(string $path): string
    {
        return str_starts_with(ltrim($path, '/'), 'game/api/') ? 'api' : 'page';
    }

    /** Ce qui suit le `?` — le reste de l'URL, borné à la colonne. */
    public static function detail(string $uri): string
    {
        $mark = strpos($uri, '?');

        if ($mark === false) {
            return '';
        }

        return mb_substr(substr($uri, $mark + 1), 0, self::MAX_DETAIL);
    }

    /** Libellé d'une action : la langue si elle est connue, le chemin sinon. */
    public static function label(array $lang, string $action, string $payload = ''): string
    {
        // Un changement de pseudo se reconnaît à la note que `noteRename()` a
        // ajoutée : il a son propre libellé, quelle que soit la route appelée.
        if (str_contains($payload, 'pseudo_avant')) {
            return (string) ($lang['act_pseudo_changed'] ?? $action);
        }

        $key = self::LABELS[$action] ?? '';

        if ($key !== '' && isset($lang[$key])) {
            return (string) $lang[$key];
        }

        return $action;
    }

    /**
     * Champs d'une demande : `$_POST`, sinon le corps JSON.
     *
     * Les écritures de l'API JSON n'alimentent pas `$_POST` : leur contenu doit
     * être décodé pour être journalisé (et pour repérer un changement de pseudo).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function fields(array $post, string $raw = ''): array
    {
        if ($post !== array() || $raw === '') {
            return $post;
        }

        $decoded = json_decode(trim($raw), true);

        return is_array($decoded) ? $decoded : array();
    }

    /** Période demandée par la page, bornée aux valeurs proposées. */
    public static function daysFilter(?string $days): int
    {
        $value = (int) trim((string) $days);

        return in_array($value, self::DAYS, true) ? $value : 30;
    }

    /** Nature demandée par la page : `all`, `page` ou `api`. */
    public static function kindFilter(?string $kind): string
    {
        $value = mb_strtolower(trim((string) $kind));

        return in_array($value, self::KINDS, true) ? $value : 'all';
    }

    /** Date de début d'une période (« il y a n jours », heure comprise). */
    public static function from(int $days, int $now): int
    {
        return $now - (max(1, $days) * 86400);
    }

    /**
     * Résumé du contenu d'une demande, tel qu'il est enregistré.
     *
     * Les champs sont aplatis en paires `clé=valeur` (`units.401=3` pour un tableau),
     * bornés en nombre et en longueur, et les valeurs sensibles sont masquées :
     * un mot de passe ou un jeton ne doit jamais se retrouver dans le journal.
     * Les écritures JSON, qui n'alimentent pas `$_POST`, passent par `$raw`.
     *
     * @param array<string, mixed> $fields
     */
    public static function summarize(array $fields, string $raw = ''): string
    {
        $fields = self::fields($fields, $raw);
        $pairs = array();
        self::flatten($fields, '', $pairs);

        return mb_substr(implode('&', array_slice($pairs, 0, self::MAX_FIELDS)), 0, self::MAX_PAYLOAD);
    }

    /**
     * Contenu d'une action, rendu lisible pour un administrateur.
     *
     * Les identifiants d'éléments sont complétés du nom du bâtiment, de la
     * recherche, du vaisseau ou de la défense (`element=15 (Usine de robots)`),
     * d'après la langue du jeu ; le reste s'affiche tel quel.
     *
     * @param array<string, mixed> $lang
     */
    public static function describe(array $lang, string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        $tech = is_array($lang['tech'] ?? null) ? (array) $lang['tech'] : array();
        $parts = array();

        foreach (explode('&', $payload) as $pair) {
            if ($pair === '') {
                continue;
            }

            $split = array_pad(explode('=', $pair, 2), 2, '');
            $name = (string) $split[0];
            $value = (string) $split[1];
            $element = self::elementName($tech, $name, $value);

            $parts[] = $element === ''
                ? $name . '=' . $value
                : $name . '=' . $value . ' (' . $element . ')';
        }

        return implode(', ', $parts);
    }

    /**
     * Nom de l'élément désigné par un champ, s'il y en a un.
     *
     * On ne devine pas : le nom du champ doit désigner un élément (`element`,
     * `techid`, `gid`, `units.401`…) ou être un identifiant numérique (une clé de
     * tableau aplatie) — `count=15` ne parle pas de l'élément 15.
     *
     * @param array<int|string, mixed> $tech
     */
    private static function elementName(array $tech, string $name, string $value): string
    {
        $segments = explode('.', $name);
        $leaf = mb_strtolower((string) end($segments));
        $numericLeaf = ctype_digit($leaf) ? $leaf : '';
        $fields = array('element', 'tech', 'techid', 'gid', 'ship', 'unit', 'units', 'building', 'defense');

        if ($numericLeaf !== '' && isset($tech[$numericLeaf])) {
            return (string) $tech[$numericLeaf];
        }

        if (in_array($leaf, $fields, true) && ctype_digit($value) && isset($tech[$value])) {
            return (string) $tech[$value];
        }

        return '';
    }

    /**
     * Aplatit les champs en paires `clé=valeur` (`parent.enfant=valeur`).
     *
     * @param array<string, mixed> $fields
     * @param list<string> $pairs
     */
    private static function flatten(array $fields, string $prefix, array &$pairs): void
    {
        foreach ($fields as $key => $value) {
            if (count($pairs) >= self::MAX_FIELDS) {
                return;
            }

            $name = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;

            if (is_array($value)) {
                self::flatten($value, $name, $pairs);

                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $text = (string) $value;
            $pairs[] = $name . '=' . (self::secret($name) ? '***' : mb_substr($text, 0, self::MAX_VALUE));
        }
    }

    /** Un champ qui parle de mot de passe, de jeton ou de signature reste masqué. */
    private static function secret(string $name): bool
    {
        return preg_match('/(' . self::SECRET_PATTERN . ')/i', $name) === 1;
    }

    // -------------------------------------------------------------- écriture

    /**
     * Enregistre l'action demandée.
     *
     * Appelé une fois par requête, avant l'exécution du contrôleur : l'intention
     * est journalisée même si la page échoue ensuite. Ne journalise rien sans
     * joueur identifié, ni pour un robot.
     *
     * @return bool vrai si une ligne a été écrite
     */
    public function journal(Request $request, int $now = 0): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $path = ltrim($request->path(), '/');

        if ($userId <= 0 || $path === '' || self::ignored($path) || $this->isBot($userId)) {
            return false;
        }

        $fields = $this->noteRename($userId, self::fields($_POST, self::body()));

        $this->record(
            $userId,
            $path,
            self::kind($path),
            $request->method(),
            self::detail($request->uri()),
            self::summarize($fields),
            $now
        );

        return true;
    }

    /**
     * Note le pseudo d'avant quand la demande en change un.
     *
     * Un joueur peut modifier son pseudo (page Options) : sans cette note, le
     * journal ne garderait que le nouveau nom, et personne ne saurait d'où il
     * vient. Une seule requête, et seulement pour une demande de renommage.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function noteRename(int $userId, array $fields): array
    {
        foreach (self::USERNAME_FIELDS as $key) {
            $new = trim((string) ($fields[$key] ?? ''));

            if ($new === '') {
                continue;
            }

            $before = $this->users->username($userId);

            if ($before !== '' && $before !== $new) {
                $fields['pseudo_avant'] = $before;
            }

            break;
        }

        return $fields;
    }

    /**
     * Trace d'une adresse qui ne correspond à aucune route (404).
     *
     * Sonder des adresses inexistantes est justement ce qu'un administrateur
     * cherche à voir : la page inconnue n'ouvre pas la session, on la lit donc
     * ici si le client en présente déjà le cookie. L'action est enregistrée même
     * sans compte (`id_owner` = 0) : l'adresse et l'IP restent.
     */
    public function journalUnknown(Request $request, int $now = 0): void
    {
        $this->ensureSession();

        $path = ltrim($request->path(), '/');
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($path === '' || ($userId > 0 && $this->isBot($userId))) {
            return;
        }

        $this->record(
            $userId,
            mb_substr($path, 0, 64),
            self::KIND_UNKNOWN,
            $request->method(),
            self::detail($request->uri()),
            self::summarize(self::fields($_POST, self::body())),
            $now
        );
    }

    /** Écrit une ligne du journal (IP bornée, date posée si absente). */
    private function record(
        int $userId,
        string $path,
        string $kind,
        string $method,
        string $detail,
        string $payload,
        int $now
    ): void {
        $this->actions->record(
            $userId,
            $path,
            $kind,
            $method,
            $detail,
            $payload,
            mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 16),
            $now > 0 ? $now : time()
        );
    }

    /**
     * Corps de la requête quand `$_POST` est vide (les écritures JSON).
     *
     * `php://input` ne se lit pas sur un envoi multipart : les champs de
     * formulaire sont alors déjà dans `$_POST`.
     */
    private static function body(): string
    {
        $type = mb_strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if ($type === '' || str_contains($type, 'multipart/form-data')) {
            return '';
        }

        $raw = file_get_contents('php://input', false, null, 0, 4096);

        return $raw === false ? '' : $raw;
    }

    /** La session n'est pas ouverte par une page inconnue : on la lit si elle existe. */
    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE || !isset($_COOKIE[session_name()])) {
            return;
        }

        session_start();
    }

    /** Le compte est-il un robot ? (réponse retenue pour la requête en cours) */
    private function isBot(int $userId): bool
    {
        if (!array_key_exists($userId, self::$bots)) {
            self::$bots[$userId] = $this->actions->isBot($userId);
        }

        return self::$bots[$userId];
    }

    // -------------------------------------------------------------- lecture

    /** Nombre d'actions de la période (pied de rendu). */
    public function count(int $days, int $playerId = 0, string $kind = 'all', int $now = 0): int
    {
        $now = $now > 0 ? $now : time();

        return $this->actions->countIn(self::from($days, $now), $now, $playerId, self::kindFilter($kind));
    }

    /**
     * Lignes du journal, les plus récentes d'abord.
     *
     * La taille de page est fournie par l'appelant : la page d'administration
     * laisse le choix entre 10, 25, 50 et 100 lignes, et le tableau doit en
     * afficher autant que le résumé l'annonce (`RECENT_LIMIT` n'est que le défaut
     * des appelants qui ne demandent rien).
     *
     * @return list<array<string, mixed>>
     */
    public function recent(
        int $days,
        int $playerId = 0,
        string $kind = 'all',
        int $now = 0,
        int $offset = 0,
        int $limit = self::RECENT_LIMIT,
        string $sort = 'time',
        string $order = 'desc'
    ): array {
        $now = $now > 0 ? $now : time();

        return $this->actions->findRecent(
            max(1, $limit),
            self::from($days, $now),
            $now,
            $playerId,
            self::kindFilter($kind),
            $offset,
            $sort,
            $order
        );
    }

    /** @return list<array<string, mixed>> */
    public function totals(int $days, int $playerId = 0, string $kind = 'all', int $now = 0): array
    {
        $now = $now > 0 ? $now : time();

        return $this->actions->findTotalsByAction(
            self::from($days, $now),
            $now,
            self::TOTALS_LIMIT,
            $playerId,
            self::kindFilter($kind)
        );
    }

    /** Purge les actions plus vieilles que la conservation. @return int lignes effacées */
    public function prune(int $now = 0): int
    {
        $now = $now > 0 ? $now : time();

        return $this->actions->prune($now - (self::RETENTION_DAYS * 86400));
    }
}
