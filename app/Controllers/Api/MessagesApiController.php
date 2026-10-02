<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\GameConfig;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Services\MessageComposer;

/**
 * POST /game/api/messages/send  { id, subject, text }
 *
 * Envoi d'un message privé, identique au formulaire
 * /game/profil/messages?mode=write&id=... : le sujet est nettoyé et le corps
 * passe par le BBCode lorsque l'option du jeu est active.
 */
final class MessagesApiController extends ApiController
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function sendAction(Request $request): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        $payload = $this->payload($request);
        $recipientId = (int) ($payload['id'] ?? $payload['to_id'] ?? 0);
        $recipient = $recipientId > 0 ? $this->users->findFullById($recipientId) : false;

        if (!is_array($recipient)) {
            throw ApiException::validation(
                'unknown_recipient',
                'Destinataire inconnu.',
                array('id' => 'Identifiant de destinataire invalide.')
            );
        }

        $subject = MessageComposer::subject($payload['subject'] ?? '');

        if ($subject === '') {
            throw ApiException::validation(
                'missing_subject',
                'Le sujet est obligatoire.',
                array('subject' => 'Sujet manquant.')
            );
        }

        if (MessageComposer::isEmptyMessage($payload['text'] ?? '')) {
            throw ApiException::validation(
                'missing_text',
                'Le message est vide.',
                array('text' => 'Message manquant.')
            );
        }

        $body = MessageComposer::body(
            $payload['text'] ?? '',
            GameConfig::get('enable_bbcode', '0') === '1'
        );
        $from = $user['username'] . ' [' . $user['galaxy'] . ':' . $user['system'] . ':' . $user['planet'] . ']';

        SendSimpleMessage($recipientId, (int) $user['id'], '', 1, $from, $subject, $body);

        return $this->success(
            array('to' => $recipientId),
            array(),
            array(array('type' => 'success', 'text' => 'Message envoyé.'))
        );
    }
}
