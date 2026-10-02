<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Api\CsrfToken;
use App\Core\Format;
use App\Core\Modules;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use Modules\Chat\Repositories\ChatRepository;

/**
 * Panneau d'administration : modération du tchat.
 *
 * Reprend `admin/chat.php`. Les suppressions se faisaient par des liens
 * (`?delete=N`, `?deleteall=yes`) lus avec `extract($_GET)` : elles passent par un
 * formulaire `POST` avec jeton CSRF. Les messages sont échappés à l'affichage —
 * la page historique les insérait bruts, donc un joueur pouvait faire exécuter du
 * script dans le navigateur de l'administrateur.
 */
final class ChatController extends AdminController
{
    /** Adresse de la page, reprise par les liens de pagination. */
    private const PATH = '/back/chat';

    /**
     * Dépôt du tchat, chargé **paresseusement**.
     *
     * Il vit dans le module `chat` : un Coeur d'application sans ce module n'a pas la classe du tout,
     * et une propriété promue par défaut ferait tomber la page **à l'instanciation**
     * (« Class Modules\Chat\Repositories\ChatRepository not found »), avant toute garde.
     */
    private ?ChatRepository $chatRepository = null;

    private function chat(): ChatRepository
    {
        return $this->chatRepository ??= new ChatRepository();
    }

    protected function requiredPermission(): string
    {
        return 'admin.chat';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        // Sans le module `chat`, il n'y a rien à modérer : la page le dit, au lieu de
        // tomber sur une classe introuvable.
        if (!Modules::exists('chat')) {
            return $this->moduleMissing('chat');
        }

        $lang = $this->lang();

        if (is_string($request->post('do')) && $request->post('do') !== '') {
            return $this->apply($request);
        }

        $rows = '';

        // Le tchat peut être long : paginé, triable et à taille de page réglable.
        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : null,
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            array_keys(ChatRepository::SORTS),
            'time'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $labels = $this->paginationLabels((string) ($lang['adm_ch_ttle'] ?? ''));
        $window = Paginator::window($this->chat()->count(), $request->get('page'), $perPage);

        $head = Paginator::header(self::PATH, array('per_page' => (string) $perPage), $sort, 'time', (string) ($lang['adm_ch_time'] ?? 'time'), $labels)
            . Paginator::header(self::PATH, array('per_page' => (string) $perPage), $sort, 'user', (string) ($lang['adm_ch_play'] ?? 'user'), $labels)
            . $this->adminTemplate('table_head', array('th_label' => (string) ($lang['adm_ch_msg'] ?? ''), 'th_class' => ''))
            . $this->adminTemplate('table_head', array('th_label' => (string) ($lang['adm_ch_delet'] ?? ''), 'th_class' => 'text-end'));

        foreach ($this->chat()->findPage($sort['field'], $sort['order'], $window['per_page'], $window['offset']) as $message) {
            $rows .= $this->adminTemplate('chat_row', $lang + array(
                'chat_time' => gmdate('d/m/Y H:i:s', (int) $message['timestamp']),
                'chat_user' => Format::escape((string) $message['user']),
                'chat_message' => Format::escape((string) $message['message']),
                'chat_delete' => $this->adminTemplate('row_delete', array(
                    'delete_action' => '/back/chat',
                    'delete_id' => (int) $message['messageid'],
                    'delete_token' => CsrfToken::token(),
                    'delete_label' => (string) ($lang['adm_ch_delet'] ?? ''),
                    'delete_confirm' => (string) ($lang['adm_confirm_delete'] ?? ''),
                )),
            ));
        }

        $body = $this->adminPanel(
            'chat_body',
            $lang + array(
                'msg_list' => $rows,
                'chat_head' => $head,
                'chat_page_bar' => Paginator::sizeBar(self::PATH, array(), $window, $labels, $sort),
                'chat_pagination' => Paginator::render(self::PATH, array(), $window, $labels, $sort),
            ),
            (string) ($lang['adm_ch_ttle'] ?? ''),
            'bi-chat-square-text',
            $this->adminTemplate('row_clear', array(
                'clear_action' => '/back/chat',
                'clear_token' => CsrfToken::token(),
                'clear_label' => (string) ($lang['adm_ch_clear'] ?? ''),
                'clear_confirm' => (string) ($lang['adm_confirm_clear'] ?? ''),
            ))
        );

        return $this->adminPage($body, (string) ($lang['adm_ch_ttle'] ?? 'Administration'));
    }

    /** Suppression d'un message ou vidage du tchat. */
    private function apply(Request $request): Response
    {
        $lang = $this->lang();
        $what = (string) $request->post('do');

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['sys_noaccess'] ?? ''),
                (string) ($lang['sys_noalloaw'] ?? ''),
                '/back/chat',
                3,
                'red'
            );
        }

        if ($what === 'clear') {
            $removed = $this->chat()->deleteAll();
        } else {
            $id = is_numeric($request->post('id')) ? (int) $request->post('id') : 0;
            $removed = $id > 0 ? $this->chat()->deleteById($id) : 0;
        }

        return $this->renderMessage(
            (string) ($lang['adm_ch_removed'] ?? '') . ' ' . $removed,
            (string) ($lang['adm_ch_ttle'] ?? ''),
            '/back/chat',
            3,
            'lime'
        );
    }
}
