<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pagination des tableaux d'administration.
 *
 * Une seule arithmétique pour toutes les listes : nombre de pages, page retenue
 * (bornée à ce qui existe), décalage à passer au dépôt, tri par colonne et taille
 * de page. Les liens sont construits par les gabarits ; ici, il n'y a que des
 * nombres, des clés de tri et des adresses.
 *
 * Les gabarits (`pagination.tpl`, `pagination_sizes.tpl`, `pagination_link.tpl`,
 * `pagination_size.tpl`) vivent à la racine des gabarits du jeu : le jeu comme le
 * panneau d'administration s'en servent.
 */
final class Paginator
{
    /** Lignes affichées par page, quand la page n'en demande pas d'autre. */
    public const PER_PAGE = 10;

    /** Tailles de page proposées par la barre de pagination. */
    public const PER_PAGE_CHOICES = array(10, 25, 50, 100);

    /** Nombre de pages montrées avant et après la page courante. */
    public const AROUND = 2;

    /**
     * Fenêtre d'affichage d'une page.
     *
     * @return array{page: int, pages: int, offset: int, per_page: int, total: int, first: int, last: int}
     */
    public static function window(int $total, mixed $page = 1, int $perPage = self::PER_PAGE): array
    {
        $perPage = max(1, $perPage);
        $total = max(0, $total);
        $pages = self::count($total, $perPage);
        $current = min(max(1, self::page($page)), $pages);
        $offset = ($current - 1) * $perPage;

        return array(
            'page' => $current,
            'pages' => $pages,
            'offset' => $offset,
            'per_page' => $perPage,
            'total' => $total,
            'first' => $total === 0 ? 0 : $offset + 1,
            'last' => min($total, $offset + $perPage),
        );
    }

    /** Page demandée, ramenée à un entier positif (toute saisie illisible vaut 1). */
    public static function page(mixed $value): int
    {
        return is_numeric($value) ? max(1, (int) $value) : 1;
    }

    /** Nombre de pages nécessaires pour un total (jamais zéro : il y a toujours une page). */
    public static function count(int $total, int $perPage = self::PER_PAGE): int
    {
        return max(1, (int) ceil(max(0, $total) / max(1, $perPage)));
    }

    /**
     * Numéros de pages à afficher : la première, la dernière, et la fenêtre autour
     * de la page courante (sans doublon).
     *
     * @return list<int>
     */
    public static function links(int $pages, int $page, int $around = self::AROUND): array
    {
        $pages = max(1, $pages);
        $page = min(max(1, $page), $pages);
        $numbers = array(1, $pages);

        for ($offset = -$around; $offset <= $around; $offset++) {
            $candidate = $page + $offset;

            if ($candidate >= 1 && $candidate <= $pages) {
                $numbers[] = $candidate;
            }
        }

        $numbers = array_unique($numbers);
        sort($numbers);

        return array_values($numbers);
    }

    /**
     * Navigation du bas : les numéros de page, en liens (aucun script : la page se
     * recharge).
     *
     * Le choix de la taille de page vit au-dessus du tableau (`sizeBar()`) : ici il
     * ne reste que la navigation, masquée quand une seule page suffit.
     *
     * Le rendu vit ici, comme celui des autres blocs du noyau (`FleetBar`,
     * `QueueRenderer`), parce que plusieurs pages d'administration l'utilisent avec
     * les mêmes gabarits.
     *
     * @param array<string, string> $query filtres à conserver dans les liens
     * @param array{page: int, pages: int, per_page: int, total: int, first: int, last: int} $window
     * @param array<string, string> $labels libellés fournis par la page
     * @param array{field: string, order: string} $sort tri courant (vide = aucun tri)
     */
    public static function render(string $path, array $query, array $window, array $labels, array $sort = array()): string
    {
        $pages = (int) $window['pages'];
        $page = (int) $window['page'];
        $links = '';

        // Le tri et la taille de page suivent dans chaque lien : tourner la page
        // ne doit pas remettre le tableau dans son ordre initial.
        $query['per_page'] = (string) $window['per_page'];

        if ($sort !== array()) {
            $query['sort'] = (string) $sort['field'];
            $query['order'] = (string) $sort['order'];
        }

        // Les liens impossibles (premier, précédent au début ; suivant, dernier à
        // la fin) ne sont pas rendus : un lien grisé resterait cliquable, et un
        // `<span>` gris n'apprendrait rien.
        if ($page > 1) {
            $links .= self::link($path, $query, 1, (string) ($labels['first'] ?? ''), '');
            $links .= self::link($path, $query, $page - 1, (string) ($labels['previous'] ?? ''), '');
        }

        foreach (self::links($pages, $page) as $number) {
            $links .= self::link($path, $query, $number, (string) $number, $number === $page ? ' active' : '');
        }

        if ($page < $pages) {
            $links .= self::link($path, $query, $page + 1, (string) ($labels['next'] ?? ''), '');
            $links .= self::link($path, $query, $pages, (string) ($labels['last'] ?? ''), '');
        }

        return TemplateEngine::render('pagination', array(
            // Une seule page : rien à proposer, et le total est déjà en haut.
            'pagination_class' => $pages > 1 ? '' : ' d-none',
            'pagination_label' => (string) ($labels['label'] ?? ''),
            'pagination_links' => $links,
        ));
    }

    /**
     * Barre du haut : le total affiché et le choix de la taille de page.
     *
     * La taille se choisit avant de lire le tableau, donc au-dessus de lui. Cette
     * barre reste visible même quand il n'y a qu'une page : elle porte le total et
     * le choix, là où la navigation du bas n'aurait rien à montrer.
     *
     * @param array<string, string> $query filtres à conserver dans les liens
     * @param array{page: int, pages: int, per_page: int, total: int, first: int, last: int} $window
     * @param array<string, string> $labels libellés fournis par la page
     * @param array{field: string, order: string} $sort tri courant (vide = aucun tri)
     */
    public static function sizeBar(string $path, array $query, array $window, array $labels, array $sort = array()): string
    {
        // Changer de taille revient à la première page : l'ancien décalage ne veut
        // plus rien dire avec un autre nombre de lignes.
        unset($query['page']);
        $query['per_page'] = (string) $window['per_page'];

        if ($sort !== array()) {
            $query['sort'] = (string) $sort['field'];
            $query['order'] = (string) $sort['order'];
        }

        return TemplateEngine::render('pagination_sizes', array(
            'pagination_label' => (string) ($labels['label'] ?? ''),
            'pagination_summary' => self::summary($labels, $window),
            'pagination_size_label' => (string) ($labels['size'] ?? ''),
            'pagination_sizes' => self::sizes($path, $query, (int) $window['per_page']),
        ));
    }

    /** Ligne « 1 à 10 sur 669 », partagée par les deux barres. */
    private static function summary(array $labels, array $window): string
    {
        return sprintf(
            (string) ($labels['summary'] ?? '%d'),
            (int) $window['first'],
            (int) $window['last'],
            (int) $window['total']
        );
    }

    /** Liens de taille de page : « 10 25 50 100 ». */
    private static function sizes(string $path, array $query, int $current): string
    {
        $html = '';

        foreach (self::PER_PAGE_CHOICES as $size) {
            unset($query['page']);
            $query['per_page'] = (string) $size;

            $html .= TemplateEngine::render('pagination_size', array(
                'size_class' => $size === $current ? ' active' : '',
                'size_href' => $path . '?' . str_replace('&', '&amp;', http_build_query($query)),
                'size_label' => (string) $size,
            ));
        }

        return $html;
    }

    /**
     * Tri demandé : la clé est validée contre la liste blanche de la page, le sens
     * contre les deux valeurs possibles.
     *
     * @param list<string> $allowed clés de tri de la page (celle du dépôt, jamais recopiée)
     * @return array{field: string, order: string}
     */
    public static function sort(?string $field, ?string $order, array $allowed, string $default): array
    {
        $key = trim((string) $field);
        $dir = strtolower(trim((string) $order)) === 'desc' ? 'desc' : 'asc';

        return array(
            'field' => in_array($key, $allowed, true) ? $key : $default,
            'order' => $dir,
        );
    }

    /** Sens à appliquer quand on clique sur l'en-tête d'une colonne. */
    public static function nextOrder(array $sort, string $field): string
    {
        return $sort['field'] === $field && $sort['order'] === 'asc' ? 'desc' : 'asc';
    }

    /** Taille de page demandée : un des choix proposés, sinon la taille par défaut. */
    public static function perPage(mixed $value, int $default = self::PER_PAGE): int
    {
        $size = is_numeric($value) ? (int) $value : 0;

        return in_array($size, self::PER_PAGE_CHOICES, true) ? $size : $default;
    }

    /**
     * En-tête de colonne triable : un lien qui bascule le sens du tri.
     *
     * @param array<string, string> $query filtres de la page
     * @param array{field: string, order: string} $sort tri courant
     * @param array<string, string> $labels flèches fournies par la page (`asc`, `desc`, `none`)
     */
    public static function header(
        string $path,
        array $query,
        array $sort,
        string $field,
        string $label,
        array $labels = array(),
        string $align = ''
    ): string {
        $active = ($sort['field'] ?? '') === $field;
        $query['sort'] = $field;
        $query['order'] = self::nextOrder($sort, $field);

        // Un tri change l'ordre des lignes : la page courante n'a plus de sens.
        unset($query['page']);

        return TemplateEngine::render('admin/table_header', array(
            'th_label' => $label,
            'th_href' => $path . '?' . str_replace('&', '&amp;', http_build_query($query)),
            'th_arrow' => $active
                ? (string) ($labels[$sort['order'] === 'asc' ? 'asc' : 'desc'] ?? '')
                : (string) ($labels['none'] ?? ''),
            'th_class' => ($active ? ' active' : '') . ($align === 'end' ? ' text-end' : ''),
        ));
    }

    /** Un lien de la barre : le numéro de page remplace `page` dans la requête. */
    private static function link(string $path, array $query, int $number, string $label, string $state): string
    {
        $query['page'] = (string) $number;

        return TemplateEngine::render('pagination_link', array(
            'page_class' => $state,
            'page_href' => $path . '?' . str_replace('&', '&amp;', http_build_query($query)),
            'page_label' => $label,
        ));
    }
}
