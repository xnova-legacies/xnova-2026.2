<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\FlyingFleetService;

/**
 * Bandeau « flottes en vol », affiché sur toutes les pages (comme php-debugbar
 * et son onglet en bas de page).
 *
 * Le rendu est serveur : les lignes sont fabriquées ici, une seule fois, et le
 * client ne fait que décompter les échéances (`data-fleet-end`) et replier le
 * panneau. L'injection passe par un tampon de sortie démarré avant le routage
 * (voir index.php) : elle couvre donc aussi les pages legacy, et se retire
 * d'elle-même quand la réponse n'est pas du HTML (API JSON) ou que la page est
 * hors jeu (connexion, installateur, administration).
 *
 * Le fragment est également exposé par `GET /game/api/fleets` : le client
 * remplace alors le contenu du panneau quand un vol apparaît, disparaît ou
 * change d'échéance, sans recharger la page.
 */
final class FleetBar
{
    /** Marqueur d'injection : évite d'insérer le bandeau deux fois. */
    public const MARKER = 'xnova-fleets-bar';

    /** Conteneur dont le client remplace le contenu (fragment). */
    public const LIST_ID = 'xnova-fleets-list';

    /** Élément qui porte l'empreinte connue du client. */
    public const FINGERPRINT_ATTR = 'data-fleet-fingerprint';

    /** Démarre l'injection du bandeau en fin de page (une seule fois). */
    public static function boot(): void
    {
        static $booted = false;

        if ($booted) {
            return;
        }

        $booted = true;

        ob_start(array(self::class, 'flush'));
    }

    /** Callback de tampon de sortie : glisse le bandeau avant </body>. */
    public static function flush(string $buffer): string
    {
        if (!self::shouldInject($buffer)) {
            return $buffer;
        }

        try {
            $entries = (new FlyingFleetService())->entries((int) $GLOBALS['user']['id']);
            $lang = is_array($GLOBALS['lang'] ?? null) ? $GLOBALS['lang'] : array();
            $labels = self::labels($lang);
            $fragment = self::render($entries, $labels);

            // L'alerte d'attaque est rendue à partir des mêmes vols : elle n'a pas
            // sa propre requête, et disparaît quand plus rien ne vise le joueur.
            $buffer = FleetAlert::inject($buffer, FleetAlert::render($entries, FleetAlert::labels($lang)));
        } catch (\Throwable) {
            // Le bandeau ne doit jamais casser une page : on rend la page telle quelle.
            return $buffer;
        }

        return self::inject($buffer, $fragment);
    }

    /** Le bandeau a-t-il sa place dans cette réponse ? */
    public static function shouldInject(string $buffer): bool
    {
        if ($buffer === '' || stripos($buffer, '</body>') === false) {
            return false;
        }

        if (str_contains($buffer, self::MARKER)) {
            return false;
        }

        // Pages sans session de jeu : connexion/inscription, administration
        // (hors périmètre), installateur, fenêtres surgissantes (notes, amis).
        if (defined('LOGIN') || defined('IN_ADMIN') || defined('DISABLE_IDENTITY_CHECK') || defined('POPUP')) {
            return false;
        }

        return !empty($GLOBALS['user']['id']);
    }

    /** Insère le bandeau avant </body>. Fonction pure, testée. */
    public static function inject(string $html, string $fragment): string
    {
        $position = stripos($html, '</body>');

        if ($position === false || $fragment === '') {
            return $html;
        }

        return substr($html, 0, $position) . $fragment . substr($html, $position);
    }

    /**
     * Libellés du bandeau.
     *
     * Les noms de mission viennent de `$lang['type_mission']`, comme dans la vue
     * générale : ils contiennent déjà leurs entités HTML.
     *
     * @param array<string, mixed> $lang
     * @return array<string, string>
     */
    public static function labels(array $lang): array
    {
        $missions = is_array($lang['type_mission'] ?? null) ? $lang['type_mission'] : array();

        return array(
            'title' => (string) ($lang['fleet_bar_title'] ?? 'Flottes en vol'),
            'empty' => (string) ($lang['fleet_bar_empty'] ?? 'Aucune flotte en vol'),
            'incoming' => (string) ($lang['fleet_bar_incoming'] ?? 'Hostile'),
            'missile' => (string) ($lang['fleet_bar_missile'] ?? 'Attaque de missiles'),
            'arrival' => (string) ($lang['fleet_bar_arrival'] ?? 'arrivée'),
            'stay' => (string) ($lang['fleet_bar_stay'] ?? 'stationnement'),
            'orbit' => (string) ($lang['fleet_bar_orbit'] ?? 'en orbite'),
            'return' => (string) ($lang['fleet_bar_return'] ?? 'retour'),
            'incomingEvent' => (string) ($lang['fleet_bar_incoming_event'] ?? 'arrivée'),
            'ships' => (string) ($lang['fleet_bar_ships'] ?? 'vaisseaux'),
            'units' => (string) ($lang['fleet_bar_units'] ?? 'Détail des vaisseaux'),
            'missiles' => (string) ($lang['fleet_bar_missiles'] ?? 'missiles'),
            'recall' => (string) ($lang['fleet_bar_recall'] ?? 'Rappeler'),
            // Noms des vaisseaux : mêmes libellés que la vue générale.
            'tech' => is_array($lang['tech'] ?? null) ? $lang['tech'] : array(),
            'missions' => $missions,
        );
    }

    /**
     * Bandeau complet (coquille + lignes).
     *
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed> $labels
     */
    public static function render(array $entries, array $labels): string
    {
        $count = count($entries);

        return '<div id="' . self::MARKER . '" class="xnova-fleets"'
            . ' ' . self::FINGERPRINT_ATTR . '="' . htmlspecialchars(
                FlyingFleetService::fingerprint($entries),
                ENT_QUOTES
            ) . '"'
            . ' data-fleet-count="' . $count . '">'
            . '<div class="xnova-fleets-panel d-none" id="' . self::LIST_ID . '">'
            . self::renderList($entries, $labels)
            . '</div>'
            . '<button type="button" class="xnova-fleets-toggle" data-fleets-toggle'
            . ' aria-expanded="false" aria-controls="' . self::LIST_ID . '">'
            . '<span class="xnova-fleets-title">' . $labels['title'] . '</span>'
            . '<span class="badge text-bg-secondary xnova-fleets-badge">' . $count . '</span>'
            . '<span class="xnova-fleets-chevron" aria-hidden="true">&#9650;</span>'
            . '</button>'
            // Sans JavaScript le panneau reste lisible : les décomptes sont rendus
            // par le serveur, seule l'actualisation à la seconde manque.
            . '<noscript><style>.xnova-fleets-panel.d-none{display:block !important}</style></noscript>'
            . '</div>';
    }

    /**
     * Lignes du bandeau (ou message « aucune flotte »).
     *
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed> $labels
     */
    public static function renderList(array $entries, array $labels): string
    {
        if ($entries === array()) {
            return '<div class="xnova-fleets-empty text-body-secondary small">'
                . $labels['empty'] . '</div>';
        }

        $html = '';

        foreach ($entries as $entry) {
            $html .= self::renderItem($entry, $labels);
        }

        return $html;
    }

    /**
     * Une ligne : mission, trajet et décompte de la prochaine échéance.
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $labels
     */
    public static function renderItem(array $entry, array $labels): string
    {
        $mission = (int) ($entry['mission'] ?? 0);
        $incoming = !empty($entry['incoming']);
        $kind = (string) ($entry['kind'] ?? '');

        if ($kind === FlyingFleetService::EVENT_MISSILE) {
            $name = $labels['missile'];
            $style = 'text-bg-danger';
        } else {
            $name = (string) ($labels['missions'][$mission] ?? ('#' . $mission));
            $style = $incoming
                ? 'text-bg-danger'
                : ($mission === FlyingFleetService::MISSION_DEPLOY
                    ? 'text-bg-info'
                    : ($mission === FlyingFleetService::MISSION_ORBIT ? 'text-bg-primary' : 'text-bg-secondary'));
        }

        $eventLabel = match ($kind) {
            FlyingFleetService::EVENT_ARRIVAL => $labels['arrival'],
            FlyingFleetService::EVENT_STAY => $labels['stay'],
            FlyingFleetService::EVENT_ORBIT => $labels['orbit'],
            FlyingFleetService::EVENT_RETURN => $labels['return'],
            default => $labels['incomingEvent'],
        };

        $shipCount = (int) ($entry['ships'] ?? 0);
        $shipLabel = ($kind === FlyingFleetService::EVENT_MISSILE ? $labels['missiles'] : $labels['ships'])
            . ' : ' . $shipCount;

        // Détail dépliable (aucun script : un <details> reste dans le flux, donc
        // il n'est pas rogné par le défilement du panneau). Une attaque de
        // missiles n'a pas de composition connue : le total seul est affiché.
        $units = is_array($entry['units'] ?? null) ? $entry['units'] : array();
        $shipsHtml = '<span class="xnova-fleets-ships text-body-secondary">' . $shipLabel . '</span>';

        if ($units !== array()) {
            $shipsHtml = '<details class="xnova-fleets-detail">'
                . '<summary class="xnova-fleets-ships text-body-secondary">' . $shipLabel . '</summary>'
                . '<div class="xnova-fleets-ships-pop">' . self::renderUnits($units, $labels) . '</div>'
                . '</details>';
        }

        // Une flotte en orbite n'a pas d'échéance : pas d'attribut `data-fleet-end`,
        // donc rien à décompter côté client (le libellé reste seul).
        $timeHtml = '<span class="text-body-secondary small">' . $eventLabel . '</span>';
        $endAttribute = '';

        if ($kind !== FlyingFleetService::EVENT_ORBIT) {
            $endAttribute = ' data-fleet-end="' . (int) ($entry['time'] ?? 0) . '"';
            $timeHtml .= ' <span class="xnova-fleets-countdown">'
                . QueueRenderer::countdown((int) ($entry['time'] ?? 0) - time()) . '</span>';
        }

        // « Rappeler » : la flotte revient avec le temps déjà parcouru. Le rappel
        // passe par la page existante (`/game/fleet/back`, POST classique) : le
        // bandeau reste utilisable sans JavaScript. Les vols hostiles n'ont pas de
        // bouton — on ne rappelle que ses propres flottes —, et une salve de
        // missiles non plus : elle frappe, elle ne rentre pas.
        $recall = '';

        if (
            (int) ($entry['owner'] ?? 0) === self::currentUserId()
            && empty($entry['returning'])
            && $kind !== FlyingFleetService::EVENT_MISSILE
            && (int) ($entry['fleet_id'] ?? 0) > 0
        ) {
            $recall = '<form class="xnova-fleets-recall" method="post" action="/game/fleet/back">'
                . '<input type="hidden" name="fleetid" value="' . (int) $entry['fleet_id'] . '">'
                . '<button type="submit" class="xnova-fleets-recall-btn">'
                . '<span aria-hidden="true">&#8629;</span> ' . $labels['recall']
                . '</button>'
                . '</form>';
        }

        return '<div class="xnova-fleets-item' . ($incoming ? ' xnova-fleets-item-incoming' : '') . '">'
            . '<span class="badge ' . $style . '">' . $name . '</span>'
            . '<span class="xnova-fleets-route">'
            . self::renderPosition($entry['from'] ?? array())
            . ' <span class="text-body-secondary">&#8594;</span> '
            . self::renderPosition($entry['to'] ?? array())
            . '</span>'
            . $shipsHtml
            . '<span class="xnova-fleets-time"' . $endAttribute . '>'
            . $timeHtml
            . '</span>'
            . $recall
            . '</div>';
    }

    /** Identifiant du joueur qui regarde la page (0 hors session de jeu). */
    private static function currentUserId(): int
    {
        return (int) ($GLOBALS['user']['id'] ?? 0);
    }

    /**
     * Une ligne par type de vaisseau présent dans le vol.
     *
     * @param array<int, int> $units
     * @param array<string, mixed> $labels
     */
    private static function renderUnits(array $units, array $labels): string
    {
        $tech = is_array($labels['tech'] ?? null) ? $labels['tech'] : array();
        $html = '';

        foreach ($units as $shipId => $count) {
            $html .= '<span class="xnova-fleets-ships-row">'
                . '<span>' . (string) ($tech[$shipId] ?? ('#' . $shipId)) . '</span>'
                . '<span class="xnova-fleets-ships-count">' . (int) $count . '</span>'
                . '</span>';
        }

        return $html;
    }

    /**
     * Position cliquable ([g:s:p], lien vers la vue galaxie comme la vue générale).
     *
     * @param array<string, mixed> $position
     */
    private static function renderPosition(array $position): string
    {
        $galaxy = (int) ($position['galaxy'] ?? 0);
        $system = (int) ($position['system'] ?? 0);
        $planet = (int) ($position['planet'] ?? 0);
        $type = (int) ($position['type'] ?? 1);
        $coords = $galaxy . ':' . $system . ':' . $planet;

        $suffix = match ($type) {
            2 => ' (D)',
            3 => ' (L)',
            default => '',
        };

        return '<a href="/game/galaxy?mode=3&amp;galaxy=' . $galaxy . '&amp;system=' . $system
            . '&amp;planet=' . $planet . '" class="xnova-fleets-coords">[' . $coords . ']</a>'
            . $suffix;
    }
}
