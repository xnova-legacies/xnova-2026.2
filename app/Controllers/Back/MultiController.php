<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Format;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeclareRepository;
use App\Repositories\MultiRepository;

/**
 * Panneau d'administration : multi-comptes.
 *
 * Une seule page, deux onglets : la liste des multi-comptes déclarés (`multi`) et
 * les IP collectives déclarées par les joueurs (`declared`). Ces deux listes
 * étaient deux pages qui ne faisaient que s'afficher : elles sont désormais côte
 * à côte, comme les autres onglets du jeu (des liens, aucun script).
 *
 * `admin/declare_list.php` et `/back/declarelist` conduisent à l'onglet
 * « IP collective » : l'adresse historique ne disparaît pas, elle se range.
 */
final class MultiController extends AdminController
{
    /** Adresse des onglets : la page s'appelle elle-même. */
    public const PATH = '/back/multi';

    /** Onglets de la page : la liste des multi-comptes, puis les IP collectives. */
    public const TABS = array('list', 'declared');

    /** Colonnes de la liste des multi-comptes : clé de tri, clé de libellé. */
    private const LIST_COLUMNS = array(
        array('player', 'adm_mt_player'),
        array('sharer', 'adm_mt_sharer'),
        array('reason', 'adm_mt_text'),
    );

    /** Colonnes des IP collectives déclarées : clé de tri, clé de libellé. */
    private const DECLARED_COLUMNS = array(
        array('declarator_name', 'adm_dc_declarant'),
        array('declarator', 'adm_dc_declarant_id'),
        array('declared_1', 'adm_dc_player1'),
        array('declared_2', 'adm_dc_player2'),
        array('declared_3', 'adm_dc_player3'),
        array('reason', 'adm_tbl_reason'),
    );

    public function __construct(
        private readonly MultiRepository $multi = new MultiRepository(),
        private readonly DeclareRepository $declarations = new DeclareRepository(),
    ) {
    }

    protected function requiredPermission(): string
    {
        // La page réunie (liste des multi-comptes et IP collectives déclarées)
        // demandait le niveau modérateur : c'est le rôle qui le porte qui décide,
        // la permission est la même pour les deux onglets.
        return 'admin.multi';
    }

    /** Onglet demandé (`list` par défaut). */
    public static function tabFilter(?string $tab): string
    {
        $key = strtolower(trim((string) $tab));

        return in_array($key, self::TABS, true) ? $key : 'list';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin');
        $this->includeLang('admin/multi');

        $lang = $this->lang();
        $tab = self::tabFilter($request->get('tab'));

        $tabs = '';

        foreach (self::TABS as $value) {
            $tabs .= $this->adminTemplate('filter_button', $lang + array(
                'filter_label' => (string) ($lang['adm_mt_tab_' . $value] ?? $value),
                'filter_href' => self::PATH . '?' . str_replace('&', '&amp;', http_build_query(array('tab' => $value))),
                'filter_active' => $value === $tab ? ' active' : '',
            ));
        }

        // Les deux onglets ne sont pas bâtis de la même façon : la liste des
        // multi-comptes a besoin des noms, celle des IP collectives non.
        $declared = $tab === 'declared';
        $rows = '';

        // Tri et taille de page comme les autres listes : les colonnes triables
        // viennent de la liste blanche du dépôt de l'onglet affiché.
        $columns = $declared ? self::DECLARED_COLUMNS : self::LIST_COLUMNS;
        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : null,
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            array_keys($declared ? DeclareRepository::SORTS : MultiRepository::SORTS),
            $declared ? 'declarator_name' : 'id'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $labels = $this->paginationLabels((string) ($lang['adm_mt_title'] ?? ''));
        $query = array('tab' => $tab);
        $head = '';

        foreach ($columns as $column) {
            $head .= Paginator::header(self::PATH, $query, $sort, $column[0], (string) ($lang[$column[1]] ?? $column[1]), $labels);
        }

        $all = $declared
            ? $this->declarations->findAll($sort['field'], $sort['order'])
            : $this->multi->findAllForAdmin($sort['field'], $sort['order']);
        $window = Paginator::window(count($all), $request->get('page'), $perPage);

        foreach (array_slice($all, $window['offset'], $window['per_page']) as $declaration) {
            if ($declared) {
                $rows .= $this->adminTemplate('declarelist_rows', array(
                    'adm_ul_data_id' => Format::text(stripslashes((string) $declaration['declarator_name'])),
                    'adm_ul_data_name' => Format::text(stripslashes((string) $declaration['declarator'])),
                    'adm_ul_data_mail' => Format::text(stripslashes((string) $declaration['declared_1'])),
                    'adm_ul_data_adip' => Format::text(stripslashes((string) $declaration['declared_2'])),
                    'adm_ul_data_detai' => Format::text(stripslashes((string) $declaration['declared_3'])),
                    'adm_ul_data_regd' => Format::text(stripslashes((string) $declaration['reason'])),
                ));

                continue;
            }

            $declarant = (string) ($declaration['declarant_name'] ?? '');
            $sharer = (string) ($declaration['sharer_name'] ?? '');

            $rows .= $this->adminTemplate('multi_rows', array(
                'player' => $declarant !== '' ? Format::escape($declarant) : '#' . (int) $declaration['player'],
                'sharer' => $sharer !== '' ? Format::escape($sharer) : '#' . (int) $declaration['sharer'],
                'text' => Format::escape((string) $declaration['reason']),
            ));
        }

        $content = $declared
            ? $this->adminTemplate('declarelist_table', $lang + array('adm_ul_table' => $rows, 'adm_dc_head' => $head))
            : $this->adminTemplate('multi_table', $lang + array('adm_mt_table' => $rows, 'multi_head' => $head));

        $body = $this->adminPanel('multi_body', $lang + array(
            'multi_tabs' => $tabs,
            'multi_content' => $content,
            'multi_page_bar' => Paginator::sizeBar(self::PATH, $query, $window, $labels, $sort),
            'multi_pagination' => Paginator::render(self::PATH, $query, $window, $labels, $sort),
        ), (string) ($lang['adm_mt_title'] ?? ''), 'bi-people-fill', $window['total'] . ' ' . (string) ($lang['adm_mt_count_' . $tab] ?? ''));

        return $this->adminPage($body, (string) ($lang['adm_mt_title'] ?? 'Administration'));
    }
}
