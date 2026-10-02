<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\FlyingFleetService;

/**
 * Bandeau d'alerte « attaque en approche », affiché en haut de page au-dessus de
 * tout le reste (collant) dès qu'un vol hostile vise une position du joueur.
 *
 * Il ne calcule rien : il filtre les vols déjà préparés par
 * `FlyingFleetService` — les mêmes que le bandeau du bas — et rend le gabarit
 * `fleet_alert.tpl`. Le décompte est mis à jour par le client
 * (`scripts/xnova-fleets.js`), qui recharge la page quand l'échéance tombe :
 * c'est le chargement de page qui fait traiter l'arrivée par le serveur.
 */
final class FleetAlert
{
    /** Marqueur d'injection : évite d'insérer l'alerte deux fois. */
    public const MARKER = 'xnova-fleet-alert';

    /**
     * Libellés de l'alerte (surchargeables par les fichiers de langue, comme le
     * bandeau du bas).
     *
     * @param array<string, mixed> $lang
     * @return array<string, string>
     */
    public static function labels(array $lang): array
    {
        $missions = is_array($lang['type_mission'] ?? null) ? $lang['type_mission'] : array();

        return array(
            'title' => (string) ($lang['fleet_alert_title'] ?? 'Attaque en approche'),
            'hint' => (string) ($lang['fleet_alert_hint'] ?? 'Une flotte hostile vise une de vos positions'),
            'missile' => (string) ($lang['fleet_bar_missile'] ?? 'Attaque de missiles'),
            'missions' => $missions,
        );
    }

    /**
     * Les vols hostiles d'une liste d'événements de flotte.
     *
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public static function hostiles(array $entries): array
    {
        $hostiles = array();

        foreach ($entries as $entry) {
            if (!empty($entry['incoming'])) {
                $hostiles[] = $entry;
            }
        }

        return $hostiles;
    }

    /**
     * Bandeau complet, ou chaîne vide s'il n'y a rien à signaler.
     *
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed>       $labels
     */
    public static function render(array $entries, array $labels): string
    {
        $hostiles = self::hostiles($entries);

        if ($hostiles === array()) {
            return '';
        }

        $items = '';

        foreach ($hostiles as $entry) {
            $mission = (int) ($entry['mission'] ?? 0);
            $isMissile = (string) ($entry['kind'] ?? '') === FlyingFleetService::EVENT_MISSILE;
            $name = $isMissile
                ? $labels['missile']
                : (string) ($labels['missions'][$mission] ?? ('#' . $mission));

            $items .= TemplateEngine::render('fleet_alert_item', array(
                'alert_mission' => $name,
                'alert_from' => self::position($entry['from'] ?? array()),
                'alert_to' => self::position($entry['to'] ?? array()),
                'alert_end' => (int) ($entry['time'] ?? 0),
                'alert_countdown' => QueueRenderer::countdown((int) ($entry['time'] ?? 0) - time()),
            ));
        }

        return TemplateEngine::render('fleet_alert', $labels + array(
            'alert_title' => $labels['title'],
            'alert_hint' => $labels['hint'],
            'alert_items' => $items,
        ));
    }

    /** Insère le bandeau juste après l'ouverture de `<body>`. Fonction pure, testée. */
    public static function inject(string $html, string $fragment): string
    {
        if ($fragment === '' || str_contains($html, self::MARKER)) {
            return $html;
        }

        if (!preg_match('/<body[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $position = $match[0][1] + strlen($match[0][0]);

        return substr($html, 0, $position) . $fragment . substr($html, $position);
    }

    /**
     * Coordonnées d'une position, sous la forme [g:s:p].
     *
     * @param array<string, mixed> $position
     */
    private static function position(array $position): string
    {
        return sprintf(
            '[%d:%d:%d]',
            (int) ($position['galaxy'] ?? 0),
            (int) ($position['system'] ?? 0),
            (int) ($position['planet'] ?? 0)
        );
    }
}
