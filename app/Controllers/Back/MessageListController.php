<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Api\CsrfToken;
use App\Core\Format;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\MessageRepository;
use App\Repositories\RwRepository;
use App\Repositories\UserRepository;

/**
 * Panneau d'administration : liste des messages du jeu.
 *
 * Reprend `admin/messagelist.php`. Trois suppressions y vivaient (une sélection,
 * la purge par date, un message isolé) déclenchées par des `<input type="submit">`
 * lus dans `$_POST` : elles restent, mais dans un seul formulaire protégé par le
 * jeton CSRF, et le filtre (type, page) passe par l'URL pour rester partageable.
 *
 * Deux bogues de la page historique sont corrigés au passage : la sélection
 * comparait la valeur cochée avec `=` (affectation) au lieu de `==`, donc tout ce
 * qui était coché était supprimé, et la pagination sautait le premier message
 * (`LIMIT 1 + …`).
 */
final class MessageListController extends AdminController
{
    /** Adresse de la page, reprise par les liens de pagination. */
    private const PATH = '/back/messagelist';

    /** Types de messages proposés au filtre. */
    private const TYPES = array(0, 1, 2, 3, 4, 5, 15, 99);

    public function __construct(
        private readonly MessageRepository $messages = new MessageRepository(),
        private readonly RwRepository $reports = new RwRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.messages';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin/messagelist');

        $lang = $this->lang();

        if (is_string($request->post('do')) && $request->post('do') !== '') {
            return $this->apply($request);
        }

        if (is_numeric($request->post('delid')) || is_numeric($request->post('restid'))) {
            return $this->apply($request);
        }

        return $this->page($request);
    }

    /** Affichage : filtre, pagination et tableau. */
    private function page(Request $request): Response
    {
        $lang = $this->lang();
        $type = $this->type($request->get('type'));
        // État affiché : les messages existants (défaut) ou les supprimés logiquement.
        $deleted = self::stateFilter($request->get('state'));
        $total = $this->messages->countByType($type, $deleted);

        // Même pagination que les autres listes : tri par colonne et taille choisie.
        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : null,
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            array_keys(MessageRepository::SORTS),
            'time'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $window = Paginator::window($total, $request->get('page', '1'), $perPage);
        $pages = $window['pages'];
        $page = $window['page'];
        $labels = $this->paginationLabels((string) ($lang['mlst_title'] ?? ''));
        $listQuery = array('type' => (string) $type, 'state' => $deleted ? self::STATE_DELETED : self::STATE_LIVE);
        $head = $this->adminTemplate('table_head', array(
            'th_label' => $this->checkAll('messagelist', (string) ($lang['mlst_select_all'] ?? '')),
            'th_class' => '',
        )) . $this->adminTemplate('table_head', array('th_label' => (string) ($lang['mlst_hdr_id'] ?? ''), 'th_class' => ''))
            . Paginator::header(self::PATH, $listQuery, $sort, 'time', (string) ($lang['mlst_hdr_time'] ?? ''), $labels)
            . Paginator::header(self::PATH, $listQuery, $sort, 'from', (string) ($lang['mlst_hdr_from'] ?? ''), $labels)
            . Paginator::header(self::PATH, $listQuery, $sort, 'owner', (string) ($lang['mlst_hdr_to'] ?? ''), $labels)
            . $this->adminTemplate('table_head', array('th_label' => (string) ($lang['mlst_hdr_text'] ?? ''), 'th_class' => ''))
            . $this->adminTemplate('table_head', array('th_label' => (string) ($lang['mlst_del_mess'] ?? ''), 'th_class' => 'text-end'));

        $messages = $this->messages->findByTypePage($type, $window['offset'], $window['per_page'], $sort['field'], $sort['order'], $deleted);
        $owners = $this->users->findUsernamesByIds(array_map(
            static fn (array $row): int => (int) $row['message_owner'],
            $messages
        ));

        $rows = '';

        foreach ($messages as $message) {
            $owner = (int) $message['message_owner'];

            $rows .= $this->adminTemplate('messagelist_table_rows', $lang + array(
                'mlst_id' => (int) $message['message_id'],
                'mlst_time' => gmdate('d/m/Y H:i:s', (int) $message['message_time']),
                'mlst_from' => Format::text((string) $message['message_from']),
                'mlst_to' => Format::text((string) ($owners[$owner] ?? '')) . ' #' . $owner,
                // Le texte d'un message est stocké en HTML d'entités (colonne
                // latin1) : il s'affiche tel quel, comme dans le jeu.
                'mlst_text' => (string) $message['message_text'],
                'mlst_del_mess' => (string) ($lang['mlst_del_mess'] ?? ''),
                // Une suppression est logique : la ligne supprimée se rétablit, et
                // c'est l'état affiché qui décide du bouton visible.
                'mlst_row_class' => $deleted ? ' table-warning' : '',
                'mlst_del_class' => $deleted ? ' d-none' : '',
                'mlst_rest_class' => $deleted ? '' : ' d-none',
                'mlst_restore_one' => (string) ($lang['mlst_bt_restore_one'] ?? ''),
            ));
        }

        $body = $this->adminTemplate('messagelist_body', $lang + array(
            'page_url' => '/back/messagelist',
            'type' => $type,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => $perPage,
            'csrf_token' => CsrfToken::token(),
            'type_options' => $this->options($this->typeOptions($lang), $type),
            'state_value' => $deleted ? self::STATE_DELETED : self::STATE_LIVE,
            'mlst_states' => $this->stateButtons(
                self::PATH,
                $lang,
                $deleted,
                'mlst_state_live',
                'mlst_state_deleted',
                array('type' => (string) $type)
            ),
            'mlst_restore_selection_class' => $deleted ? '' : ' d-none',
            'mlst_live_class' => $deleted ? ' d-none' : '',
            'mlst_bt_restore' => (string) ($lang['mlst_bt_restore'] ?? ''),
            'mlst_head' => $head,
            'mlst_page_bar' => Paginator::sizeBar(self::PATH, $listQuery, $window, $labels, $sort),
            'mlst_pagination' => Paginator::render(self::PATH, $listQuery, $window, $labels, $sort),
            'mlst_data_rows' => $rows,
            'mlst_confirm_sel' => (string) ($lang['mlst_confirm_sel'] ?? ''),
            'mlst_confirm_date' => (string) ($lang['mlst_confirm_date'] ?? ''),
        ));

        return $this->adminPage($body, (string) ($lang['mlst_title'] ?? 'Administration'));
    }

    /** Une des trois suppressions, ou la purge par date. */
    private function apply(Request $request): Response
    {
        $lang = $this->lang();
        $type = $this->type($request->post('type'));
        $page = max(1, (int) $request->post('page', 1));

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['sys_noaccess'] ?? ''),
                (string) ($lang['sys_noalloaw'] ?? ''),
                $this->backUrl($type, $page),
                3,
                'red'
            );
        }

        $what = is_string($request->post('do')) ? (string) $request->post('do') : '';
        $state = self::stateFilter($request->post('state'));
        $count = 0;
        $restore = false;

        if (is_numeric($request->post('restid'))) {
            $count = $this->messages->restoreMany(array((int) $request->post('restid')));
            $restore = true;
        } elseif (is_numeric($request->post('delid'))) {
            $count = $this->messages->markDeletedById((int) $request->post('delid'));
        } elseif ($what === 'restore') {
            // Une suppression est logique : la sélection se rétablit.
            $count = $this->messages->restoreMany($this->selection($request));
            $restore = true;
        } elseif ($what === 'delsel') {
            $count = $this->messages->markDeletedMany($this->selection($request));
        } elseif ($what === 'deldat') {
            $limit = $this->limit($request->post('deldate'));

            if ($limit !== null) {
                $count = $this->messages->markDeletedBefore($limit);
                // Les rapports de combat du même âge partent avec les messages :
                // c'est ce que faisait la page historique.
                $this->reports->deleteBefore($limit);
            }
        }

        return $this->renderMessage(
            (string) ($lang[$restore ? 'mlst_mess_restore' : 'mlst_mess_del'] ?? '') . ' ' . $count,
            (string) ($lang['mlst_title'] ?? ''),
            $this->backUrl($type, $page, $state),
            3,
            'lime'
        );
    }

    /** Retour au même filtre après une suppression ou un rétablissement. */
    private function backUrl(int $type, int $page, bool $deleted = false): string
    {
        return self::PATH . '?type=' . $type
            . '&state=' . ($deleted ? self::STATE_DELETED : self::STATE_LIVE)
            . '&page=' . $page;
    }

    /** Type demandé, borné à la liste connue. */
    private function type(mixed $value): int
    {
        $type = is_numeric($value) ? (int) $value : 0;

        return in_array($type, self::TYPES, true) ? $type : 0;
    }

    /** Bornes de la purge par date. */
    private function limit(mixed $value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $limit = strtotime(trim($value) . ' 23:59:59');

        return $limit === false ? null : $limit;
    }

    /**
     * @return array<int, array{0: int, 1: string}>
     */
    private function typeOptions(array $lang): array
    {
        $options = array();

        foreach (self::TYPES as $type) {
            $key = 'mlst_mess_typ_' . ($type < 10 ? '_' : '') . $type;
            $options[] = array($type, (string) ($lang[$key] ?? (string) $type));
        }

        return $options;
    }

    /**
     * @return array<int, array{0: int, 1: string}>
     */
    private function pageOptions(int $pages): array
    {
        $options = array();

        for ($page = 1; $page <= $pages; $page++) {
            $options[] = array($page, $page . ' / ' . $pages);
        }

        return $options;
    }

    /**
     * Options d'un `<select>`, rendues par un gabarit unique.
     *
     * @param array<int, array{0: int, 1: string}> $options
     */
    private function options(array $options, int $selected): string
    {
        $html = '';

        foreach ($options as $option) {
            $html .= $this->adminTemplate('option_row', array(
                'option_value' => $option[0],
                'option_label' => $option[1],
                'option_selected' => $option[0] === $selected ? ' selected' : '',
            ));
        }

        return $html;
    }
}
