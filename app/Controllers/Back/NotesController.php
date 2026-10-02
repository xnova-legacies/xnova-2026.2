<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Api\CsrfToken;
use App\Core\Format;
use App\Core\Modules;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use Modules\Notes\Repositories\NotesRepository;

/**
 * Panneau d'administration : les notes des joueurs.
 *
 * Les notes ne vivaient que dans le jeu (`/game/profil/notes`) : une note effacée
 * disparaissait pour tout le monde. La suppression est désormais **logique**
 * (`App\Core\Flags::DELETED`) : cette page liste les notes existantes et les
 * supprimées, et permet de les supprimer ou de les rétablir — à la ligne comme à
 * la sélection.
 *
 * Les règles sont celles du jeu (titre et texte bornés, priorité numérique), la
 * page ne fait que les lire et changer l'état.
 */
final class NotesController extends AdminController
{
    /** Adresse de la page, reprise par les filtres et les liens. */
    private const PATH = '/back/notes';

    /**
     * Dépôt des notes, chargé **paresseusement**.
     *
     * Il vit dans le module `notes` : un Coeur d'application sans ce module n'a pas la classe du tout,
     * et une propriété promue par défaut ferait tomber la page **à l'instanciation**
     * (« Class Modules\Notes\Repositories\NotesRepository not found »), avant toute garde.
     */
    private ?NotesRepository $notesRepository = null;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    private function notes(): NotesRepository
    {
        return $this->notesRepository ??= new NotesRepository();
    }

    protected function requiredPermission(): string
    {
        return 'admin.notes';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        // Sans le module `notes`, il n'y a rien à modérer : la page le dit, au lieu de
        // tomber sur une classe introuvable (le Coeur d'application ne charge rien d'un module absent).
        if (!Modules::exists('notes')) {
            return $this->moduleMissing('notes');
        }

        $this->includeLang('admin/notes');

        if (is_string($request->post('do')) && $request->post('do') !== '') {
            return $this->apply($request);
        }

        if (is_numeric($request->post('delid')) || is_numeric($request->post('restid'))) {
            return $this->apply($request);
        }

        return $this->page($request);
    }

    /** Affichage : filtre d'état, tri, pagination et tableau. */
    private function page(Request $request): Response
    {
        $lang = $this->lang();
        $deleted = self::stateFilter($request->get('state'));

        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : null,
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            array_keys(NotesRepository::SORTS),
            'time'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $labels = $this->paginationLabels((string) ($lang['nlt_title'] ?? ''));
        $window = Paginator::window($this->notes()->countAll($deleted), $request->get('page', '1'), $perPage);
        $query = array('state' => self::stateKey($deleted));
        $head = $this->adminTemplate('table_head', array(
            'th_label' => $this->checkAll('notes-admin', (string) ($lang['nlt_select_all'] ?? '')),
            'th_class' => '',
        )) . $this->adminTemplate('table_head', array('th_label' => (string) ($lang['nlt_hdr_id'] ?? ''), 'th_class' => ''))
            . Paginator::header(self::PATH, $query, $sort, 'time', (string) ($lang['nlt_hdr_time'] ?? ''), $labels)
            . Paginator::header(self::PATH, $query, $sort, 'owner', (string) ($lang['nlt_hdr_owner'] ?? ''), $labels)
            . Paginator::header(self::PATH, $query, $sort, 'priority', (string) ($lang['nlt_hdr_priority'] ?? ''), $labels)
            . Paginator::header(self::PATH, $query, $sort, 'title', (string) ($lang['nlt_hdr_title'] ?? ''), $labels)
            . $this->adminTemplate('table_head', array('th_label' => (string) ($lang['nlt_hdr_size'] ?? ''), 'th_class' => 'text-end'))
            . $this->adminTemplate('table_head', array('th_label' => (string) ($lang['nlt_hdr_action'] ?? ''), 'th_class' => 'text-end'));

        $notes = $this->notes()->findPage($window['offset'], $window['per_page'], $sort['field'], $sort['order'], $deleted);
        $owners = $this->users->findUsernamesByIds(array_map(
            static fn(array $row): int => (int) $row['owner'],
            $notes
        ));

        $rows = '';

        foreach ($notes as $note) {
            $owner = (int) $note['owner'];

            $rows .= $this->adminTemplate('notes_rows', $lang + array(
                'nlt_row_id' => (int) $note['id'],
                'nlt_row_owner' => Format::text((string) ($owners[$owner] ?? '')) . ' #' . $owner,
                'nlt_row_time' => gmdate('d/m/Y H:i:s', (int) $note['time']),
                'nlt_row_priority' => (string) ($lang['nlt_priority_' . (int) $note['priority']] ?? (int) $note['priority']),
                // Le texte est saisi par le joueur (balises retirées à l'entrée) :
                // on n'affiche que sa taille, jamais son contenu.
                'nlt_row_size' => Format::prettyNumber((float) mb_strlen((string) $note['text'])),
                // Une suppression est logique : c'est l'état affiché qui décide de
                // l'action visible.
                'nlt_row_class' => $deleted ? ' table-warning' : '',
                'nlt_row_del_class' => $deleted ? ' d-none' : '',
                'nlt_row_rest_class' => $deleted ? '' : ' d-none',
                // Le titre de la note est saisi par le joueur : il est échappé ici.
                'nlt_row_title' => Format::text((string) $note['title']),
            ));
        }

        $body = $this->adminPanel('notes_body', $lang + array(
            'page_url' => self::PATH,
            'state' => self::stateKey($deleted),
            'page' => $window['page'],
            'per_page' => $window['per_page'],
            'csrf_token' => CsrfToken::token(),
            'nlt_states' => $this->stateButtons(self::PATH, $lang, $deleted, 'nlt_state_live', 'nlt_state_deleted'),
            'nlt_head' => $head,
            'nlt_page_bar' => Paginator::sizeBar(self::PATH, $query, $window, $labels, $sort),
            'nlt_pagination' => Paginator::render(self::PATH, $query, $window, $labels, $sort),
            'nlt_data_rows' => $rows,
            'nlt_rows_empty_class' => $rows === '' ? '' : ' d-none',
            'nlt_live_class' => $deleted ? ' d-none' : '',
            'nlt_restore_selection_class' => $deleted ? '' : ' d-none',
        ), (string) ($lang['nlt_title'] ?? ''), 'bi-journal-text', $window['total'] . ' ' . (string) ($lang['nlt_count'] ?? ''));

        return $this->adminPage($body, (string) ($lang['nlt_title'] ?? 'Administration'));
    }

    /** Suppression logique ou rétablissement, à la ligne ou par sélection. */
    private function apply(Request $request): Response
    {
        $lang = $this->lang();
        $deleted = self::stateFilter($request->post('state'));
        $page = max(1, (int) $request->post('page', 1));

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['sys_noaccess'] ?? ''),
                (string) ($lang['sys_noalloaw'] ?? ''),
                $this->backUrl($deleted, $page),
                3,
                'red'
            );
        }

        $what = is_string($request->post('do')) ? (string) $request->post('do') : '';
        $count = 0;
        $restore = false;

        if (is_numeric($request->post('restid'))) {
            $count = $this->notes()->restoreMany(array((int) $request->post('restid')));
            $restore = true;
        } elseif (is_numeric($request->post('delid'))) {
            $count = $this->notes()->markDeletedById((int) $request->post('delid'));
        } elseif ($what === 'restore') {
            $count = $this->notes()->restoreMany($this->selection($request));
            $restore = true;
        } elseif ($what === 'delsel') {
            $count = $this->notes()->markDeletedMany($this->selection($request));
        }

        return $this->renderMessage(
            (string) ($lang[$restore ? 'nlt_mess_restore' : 'nlt_mess_del'] ?? '') . ' ' . $count,
            (string) ($lang['nlt_title'] ?? ''),
            $this->backUrl($deleted, $page),
            3,
            'lime'
        );
    }

    /** Retour au même filtre après une suppression ou un rétablissement. */
    private function backUrl(bool $deleted, int $page): string
    {
        return self::PATH . '?state=' . self::stateKey($deleted) . '&page=' . $page;
    }
}
