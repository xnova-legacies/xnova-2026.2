<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\AdminAccess;
use App\Core\Api\CsrfToken;
use App\Core\GameConfig;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Services\MessageComposer;
use App\Services\MessageService;

/**
 * Panneau d'administration : message à tous les joueurs.
 *
 * Reprend `admin/messall.php`. La page écrivait `$game_config['tresc']` sans
 * jamais l'enregistrer (deux réglages fantômes) et interrogeait la table des
 * comptes avec `mysql_fetch_array`. Ici : un formulaire POST protégé, un envoi
 * par joueur, et l'expéditeur est l'administrateur connecté.
 */
final class MessageAllController extends AdminController
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly MessageService $messages = new MessageService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.message-all';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('messages');

        $lang = $this->lang();
        $subject = trim((string) $request->post('temat', ''));
        $text = trim((string) $request->post('tresc', ''));

        if ($subject !== '' && $text !== '') {
            return $this->send($request, $subject, $text);
        }

        $body = $this->adminPanel('messall_form', $lang + array(
            'messall_action' => '/back/message-all',
            'csrf_token' => CsrfToken::token(),
        ), (string) ($lang['adm_msg_all'] ?? ''), 'bi-megaphone', '', true);

        return $this->adminPage($body, (string) ($lang['adm_msg_all'] ?? 'Administration'));
    }

    private function send(Request $request, string $subject, string $text): Response
    {
        $lang = $this->lang();
        $user = $this->user();

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['sys_noaccess'] ?? ''),
                (string) ($lang['sys_noalloaw'] ?? ''),
                '/back/message-all',
                3,
                'red'
            );
        }

        // Le message passe par le composeur du jeu (sujet borné, BBCode si
        // l'univers l'active) : c'est la même règle que pour un message de joueur,
        // et une seule mise en forme — la page historique fabriquait son HTML.
        $from = AdminAccess::role((int) ($user['authlevel'] ?? 0)) . ' ' . (string) ($user['username'] ?? '');
        $subject = MessageComposer::subject($subject);
        $body = MessageComposer::body($text, (int) (GameConfig::get('enable_bbcode', '0')) === 1);

        $sent = 0;

        foreach ($this->users->findAllIds() as $player) {
            $this->messages->send((int) $player['id'], (int) ($user['id'] ?? 0), MessageService::TYPE_ADMIN, $from, $subject, $body);
            $sent++;
        }

        return $this->renderMessage(
            (string) ($lang['adm_msg_all_done'] ?? '') . ' ' . $sent,
            (string) ($lang['adm_msg_all'] ?? ''),
            '/back/message-all',
            4,
            'lime'
        );
    }
}
