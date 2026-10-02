<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\QueueService;

/**
 * Panneau latéral des files d'attente, affiché sur toutes les pages de jeu.
 *
 * Il double le menu de gauche : une colonne à droite, quatre listes repliables
 * (bâtiments, recherche, vaisseaux, défenses) et une échéance par ligne. Les
 * vaisseaux et les défenses partagent la même file dans le jeu : la répartition
 * vient de QueueService::splitHangar(), jamais d'un découpage recopié ici.
 *
 * La colonne est écrite par le serveur, DANS LE GABARIT DE PAGE (voir
 * TemplateEngine::renderDisplay() et renderDisplay() de functions.php). Elle vit
 * donc hors de `.xnova-content`, que le rechargement à chaud remplace — sans quoi
 * un rafraîchissement de la page effacerait le panneau et décalerait la mise en
 * page.
 *
 * Rien n'est produit quand aucune file n'est garnie : la page garde toute sa
 * largeur, et un joueur qui n'a rien en chantier ne voit rien.
 *
 * Le décompte est celui de scripts/xnova-queue.js (`data-end-time`) : le panneau
 * n'ajoute aucune règle de temps, il fournit la réponse que le client attend
 * déjà. Comme partout, c'est le CHARGEMENT de la page qui fait avancer les
 * files (PlanetStateService) : le script recharge donc la page à l'échéance.
 */
final class QueueBar
{
    /** Marqueur du panneau : sert aussi au client à reconnaître la colonne. */
    public const MARKER = 'xnova-queues';

    /**
     * Les quatre listes, dans l'ordre d'affichage : groupe affiché => domaine de
     * file qu'il commande. Les vaisseaux et les défenses se partagent la file du
     * hangar, d'où deux groupes pour un seul domaine — et c'est ce domaine qui
     * part au glisser-déposer comme aux liens d'action.
     */
    private const GROUPES = array(
        'buildings' => QueueService::DOMAIN_BUILDINGS,
        'research' => QueueService::DOMAIN_RESEARCH,
        'fleet' => QueueService::DOMAIN_HANGAR,
        'defense' => QueueService::DOMAIN_HANGAR,
    );

    /**
     * Colonne complète, ou chaîne vide quand rien n'est en cours.
     *
     * Ne lève jamais : le panneau ne doit pas priver le joueur de sa page.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $planet
     */
    public static function column(array $user, array $planet): string
    {
        if (empty($user['id'])) {
            return '';
        }

        try {
            $groupes = self::groups($user, $planet);
        } catch (\Throwable) {
            return '';
        }

        if (self::total($groupes) === 0) {
            return '';
        }

        return self::render($groupes, self::labels(Language::all()));
    }

    /**
     * Les quatre listes, garnies.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $planet
     * @return array<string, list<array<string, mixed>>>
     */
    public static function groups(array $user, array $planet): array
    {
        $queues = new QueueService();
        $hangar = QueueService::splitHangar($queues->hangarItems($planet, $user));

        return array(
            'buildings' => $queues->buildingItems($planet),
            'research' => $queues->researchItems($user, self::researchPlanet($user, $queues)),
            'fleet' => $hangar['fleet'],
            'defense' => $hangar['defense'],
        );
    }

    /** Nombre d'éléments en file, tous domaines confondus. */
    public static function total(array $groupes): int
    {
        $total = 0;

        foreach ($groupes as $items) {
            $total += count($items);
        }

        return $total;
    }

    /**
     * Libellés du panneau (titres des groupes et messages de file vide).
     *
     * @param array<string, mixed> $lang
     * @return array<string, string>
     */
    public static function labels(array $lang): array
    {
        return array(
            'title' => (string) ($lang['qb_title'] ?? 'Files d\'attente'),
            'buildings' => (string) ($lang['qb_buildings'] ?? 'Bâtiments'),
            'research' => (string) ($lang['qb_research'] ?? 'Recherche'),
            'fleet' => (string) ($lang['qb_fleet'] ?? 'Vaisseaux'),
            'defense' => (string) ($lang['qb_defense'] ?? 'Défenses'),
            // Les autres libellés du rendu (Interrompre, Retirer, poignée,
            // niveau, démolition) viennent de QueueRenderer : une seule source.
            'empty_buildings' => (string) ($lang['qb_empty_buildings'] ?? 'Aucun chantier en cours'),
            'empty_research' => (string) ($lang['qb_empty_research'] ?? 'Aucune recherche en cours'),
            'empty_fleet' => (string) ($lang['qb_empty_fleet'] ?? 'Aucun vaisseau en fabrication'),
            'empty_defense' => (string) ($lang['qb_empty_defense'] ?? 'Aucune défense en fabrication'),
        );
    }

    /**
     * La colonne elle-même.
     *
     * Un `<details>` par domaine : le repli est celui du navigateur, sans script,
     * et un groupe garni s'ouvre d'office — on ne cache pas ce que le joueur est
     * venu voir.
     *
     * @param array<string, list<array<string, mixed>>> $groupes
     * @param array<string, string> $labels
     */
    public static function render(array $groupes, array $labels): string
    {
        $lang = Language::all();
        $html = '<aside class="col-12 col-lg-3 col-xxl-2 xnova-queuebar" id="' . self::MARKER . '">'
            . '<div class="xnova-queuebar-card">'
            . '<div class="xnova-queuebar-head">'
            . '<span class="xnova-queuebar-title">' . $labels['title'] . '</span>'
            . '<span class="badge text-bg-primary">' . self::total($groupes) . '</span>'
            . '</div>';

        foreach (self::GROUPES as $domaine => $domaineFile) {
            $items = $groupes[$domaine] ?? array();

            // C'est le rendu DES FILES qui sert ici, avec ses liens (Interrompre,
            // Retirer) et sa poignée de glisser-déposer : une deuxième façon de
            // dessiner une ligne finirait par diverger. Seul le message de file
            // vide est propre au groupe — « aucun vaisseau » ne se dit pas comme
            // « aucun chantier ».
            $libelles = QueueRenderer::labels($lang, $domaineFile);
            $libelles['empty'] = $labels['empty_' . $domaine];

            $html .= '<details class="xnova-queuebar-group"' . ($items === array() ? '' : ' open') . '>'
                . '<summary class="xnova-queuebar-summary">'
                . '<span>' . $labels[$domaine] . '</span>'
                . '<span class="badge text-bg-secondary">' . count($items) . '</span>'
                . '</summary>'
                // data-xnova-queue porte le domaine RÉEL : c'est lui que le
                // glisser-déposer envoie à l'API. Les positions affichées sont
                // celles de la file entière (les deux groupes du hangar compris),
                // donc exactement celles du jeu — un déplacement y est valide.
                . '<div class="xnova-queuebar-list list-group list-group-flush"'
                . ' data-xnova-queue="' . $domaineFile . '" data-xnova-queue-live>'
                . QueueRenderer::renderList($domaineFile, $items, $libelles)
                . '</div>'
                . '</details>';
        }

        return $html . '</div></aside>';
    }

    /**
     * La planète qui porte la recherche « héritée » (`b_tech` / `b_tech_id`).
     *
     * La file vit désormais sur le compte (`users.b_tech_queue`) : on ne va
     * chercher la planète du laboratoire que si cette file est vide, sinon on
     * paierait une requête à chaque page pour rien.
     *
     * @param array<string, mixed> $user
     */
    private static function researchPlanet(array $user, QueueService $queues): ?array
    {
        $file = (string) ($user['b_tech_queue'] ?? '');

        if ($file !== '' && $file !== '0') {
            return null;
        }

        $planetId = $queues->researchPlanetId($user);

        return $planetId > 0 ? $queues->findPlanet($planetId) : null;
    }
}
