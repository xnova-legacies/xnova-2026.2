<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rendu serveur des files d'attente (construction, hangar, recherche).
 *
 * Le serveur produit le balisage définitif ; le client ne fait plus que
 * décompter les temps (`data-end-time`) et déplacer les lignes par
 * glisser-déposer. Un seul rendu : il n'y a donc plus de bascule entre une
 * liste de repli et une liste cliente — c'est cette bascule qui décalait la
 * page à chaque action.
 *
 * Les libellés viennent de $lang (repli sur les chaînes déjà employées par les
 * gabarits) et les noms d'éléments de $lang['tech'] sont insérés tels quels :
 * ils contiennent déjà des entités HTML, comme dans le rendu historique.
 */
final class QueueRenderer
{
    public const DOMAIN_BUILDINGS = 'buildings';
    public const DOMAIN_HANGAR = 'hangar';
    public const DOMAIN_RESEARCH = 'research';

    /**
     * Libellés du rendu, par domaine.
     *
     * @param array<string, mixed> $lang
     * @return array<string, string>
     */
    public static function labels(array $lang, string $domain = self::DOMAIN_BUILDINGS): array
    {
        $hangar = $domain === self::DOMAIN_HANGAR;
        $empty = $hangar ? 'File de fabrication vide' : 'Aucun chantier en cours';

        if ($domain === self::DOMAIN_RESEARCH) {
            $empty = 'Aucune recherche en cours';
        }

        return array(
            'running' => (string) ($lang['work_todo'] ?? ($hangar ? 'en fabrication' : 'en cours')),
            'cancel' => (string) ($lang['DelFirstQueue'] ?? 'Interrompre'),
            'remove' => (string) ($lang['DelFromQueue'] ?? 'Retirer'),
            'drag' => (string) ($lang['QueueDragHandle'] ?? 'Glisser pour changer la priorité'),
            'empty' => (string) ($lang['QueueEmpty'] ?? $empty),
            'destroy' => (string) ($lang['destroy'] ?? 'démolition'),
            'level' => (string) ($lang['level'] ?? 'niveau'),
        );
    }

    /**
     * Liste complète : les lignes, ou le message « file vide ».
     *
     * @param list<array<string, mixed>> $items
     * @param array<string, string> $labels
     */
    public static function renderList(string $domain, array $items, array $labels): string
    {
        if ($items === array()) {
            return '<div class="text-body-secondary small">' . $labels['empty'] . '</div>';
        }

        $html = '';

        foreach ($items as $item) {
            $html .= self::renderItem($domain, $item, $labels);
        }

        return $html;
    }

    /**
     * Une ligne : même balisage que celui produit auparavant par le client.
     *
     * @param array<string, mixed> $item
     * @param array<string, string> $labels
     */
    public static function renderItem(string $domain, array $item, array $labels): string
    {
        $position = (int) ($item['position'] ?? 0);
        $endTime = (int) ($item['end_time'] ?? 0);
        $movable = !empty($item['movable']);
        $running = !empty($item['running']);

        return '<div class="list-group-item d-flex flex-wrap align-items-center gap-2 xnova-queue-item"'
            . ' data-position="' . $position . '" data-movable="' . ($movable ? '1' : '0') . '"'
            . ($movable ? ' draggable="true"' : '') . '>'
            . self::renderHandle($movable, $labels)
            . '<span class="badge text-bg-secondary">' . $position . '</span>'
            . self::renderIcon($item)
            . '<span class="flex-grow-1 min-w-0">'
            . '<span class="fw-semibold">' . (string) ($item['name'] ?? '') . '</span>'
            . self::renderQuantity($item, $labels)
            . self::renderMode($item, $labels)
            . ($running ? ' <span class="badge text-bg-primary">' . $labels['running'] . '</span>' : '')
            . '</span>'
            . '<span class="xnova-queue-countdown small fw-semibold" data-end-time="' . $endTime . '">'
            . self::countdown($endTime - time()) . '</span>'
            . self::renderAction($domain, $item, $labels)
            . '</div>';
    }

    /** Compte à rebours « h:mm:ss » (mêmes règles que le client). */
    public static function countdown(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0:00:00';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT)
            . ':' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
    }

    /** @param array<string, string> $labels */
    private static function renderHandle(bool $movable, array $labels): string
    {
        if (!$movable) {
            return '<span class="xnova-queue-handle text-body-secondary opacity-25">&#8226;</span>';
        }

        return '<span class="xnova-queue-handle text-body-secondary" title="' . $labels['drag']
            . '">&#8942;&#8942;</span>';
    }

    /** @param array<string, mixed> $item */
    private static function renderIcon(array $item): string
    {
        $icon = (string) ($item['icon'] ?? '');

        if ($icon === '') {
            return '';
        }

        return '<img src="' . $icon . '" width="24" height="24" class="rounded" alt="">';
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $labels
     */
    private static function renderQuantity(array $item, array $labels): string
    {
        if ((int) ($item['count'] ?? 0) > 0) {
            return ' <span class="text-body-secondary small">×' . (int) $item['count'] . '</span>';
        }

        if ((int) ($item['level'] ?? 0) > 0) {
            return ' <span class="text-body-secondary small">' . $labels['level'] . ' '
                . (int) $item['level'] . '</span>';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $labels
     */
    private static function renderMode(array $item, array $labels): string
    {
        return ((string) ($item['mode'] ?? '')) === 'destroy'
            ? ' <span class="text-danger">' . $labels['destroy'] . '</span>'
            : '';
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $labels
     */
    private static function renderAction(string $domain, array $item, array $labels): string
    {
        $position = (int) ($item['position'] ?? 0);

        // Le hangar n'a pas d'action : l'API ne sait retirer ni une unité en
        // fabrication ni un lot en attente (le code historique ne le faisait pas
        // non plus). Le lien « Interrompre » y visait auparavant la file des
        // bâtiments : il annulait un chantier de la planète à la place.
        if ($domain === self::DOMAIN_HANGAR) {
            return '';
        }

        if (!empty($item['running'])) {
            $href = $domain === self::DOMAIN_RESEARCH
                ? '/game/buildings?mode=research&amp;cmd=cancel&amp;tech=' . (int) ($item['element'] ?? 0)
                : '/game/buildings?cmd=cancel&amp;listid=' . $position;

            return '<a class="btn btn-sm btn-outline-danger" href="' . $href . '">' . $labels['cancel'] . '</a>';
        }

        if ($domain === self::DOMAIN_BUILDINGS) {
            return '<a class="btn btn-sm btn-outline-secondary" href="/game/buildings?listid=' . $position
                . '&amp;cmd=remove">' . $labels['remove'] . '</a>';
        }

        if ($domain === self::DOMAIN_RESEARCH) {
            return '<a class="btn btn-sm btn-outline-secondary" href="/game/buildings?mode=research&amp;cmd=remove&amp;listid='
                . $position . '">' . $labels['remove'] . '</a>';
        }

        return '';
    }
}
