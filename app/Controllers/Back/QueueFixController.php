<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Api\CsrfToken;
use App\Core\Request;
use App\Core\Response;
use App\Services\QueueService;

/**
 * Panneau d'administration : réparation des files du hangar.
 *
 * Reprend `admin/ElementQueueFixer.php`. La page vidait la file de toute planète
 * dont une ligne dépassait le plafond autorisé — une file ouverte avant la
 * correction du plafond restait donc bloquée pour toujours. La règle vit
 * maintenant dans `QueueService::repairHangarQueues()`, et la page l'annonce.
 */
final class QueueFixController extends AdminController
{
    public function __construct(
        private readonly QueueService $queues = new QueueService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.queue';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $lang = $this->lang();

        if (is_string($request->post('run')) && $request->post('run') !== '') {
            if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
                return $this->renderMessage(
                    (string) ($lang['sys_noaccess'] ?? ''),
                    (string) ($lang['sys_noalloaw'] ?? ''),
                    '/back/queue-fixer',
                    3,
                    'red'
                );
            }

            $repaired = $this->queues->repairHangarQueues();

            return $this->renderMessage(
                ((string) ($lang['adm_cleaner_title'] ?? '')) . ' '
                    . ((string) ($lang['adm_cleaned'] ?? '')) . ' ' . $repaired,
                (string) ($lang['adm_cleaner_title'] ?? ''),
                '/back/queue-fixer',
                4,
                'lime'
            );
        }

        $body = $this->adminPanel('queue_fixer', $lang + array(
            'queue_action' => '/back/queue-fixer',
            'csrf_token' => CsrfToken::token(),
        ), (string) ($lang['adm_cleaner_title'] ?? ''), 'bi-tools', '', true);

        return $this->adminPage($body, (string) ($lang['adm_cleaner_title'] ?? 'Administration'));
    }
}
