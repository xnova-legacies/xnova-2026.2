<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Api\CsrfToken;
use App\Core\Request;
use App\Core\Response;
use App\Services\ResetService;

/**
 * Panneau d'administration : remise à zéro de l'univers.
 *
 * Reprend `admin/XNovaResetUnivers.php`. L'action était déclenchée par le simple
 * envoi du formulaire (`mode=reset`) : elle exige maintenant le jeton CSRF **et**
 * une case de confirmation cochée — c'est l'action la plus destructrice du
 * panneau. Le travail lui-même vit dans `ResetService`.
 */
final class ResetController extends AdminController
{
    public function __construct(
        private readonly ResetService $reset = new ResetService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.reset';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $lang = $this->lang();

        if (is_string($request->post('mode')) && $request->post('mode') !== '') {
            return $this->run($request);
        }

        $body = $this->adminPanel('reset_form', $lang + array(
            'reset_action' => '/back/reset',
            'csrf_token' => CsrfToken::token(),
        ), (string) ($lang['adm_rz_ttle'] ?? ''), 'bi-exclamation-octagon', '', true);

        return $this->adminPage($body, (string) ($lang['adm_rz_ttle'] ?? 'Administration'));
    }

    private function run(Request $request): Response
    {
        $lang = $this->lang();

        if (
            !CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)
            || $request->post('confirm') !== 'on'
        ) {
            return $this->renderMessage(
                (string) ($lang['adm_rz_need_confirm'] ?? ''),
                (string) ($lang['adm_rz_ttle'] ?? ''),
                '/back/reset',
                4,
                'red'
            );
        }

        $kept = $this->reset->reset();

        return $this->renderMessage(
            $kept . ' ' . (string) ($lang['adm_rz_done'] ?? ''),
            (string) ($lang['adm_rz_ttle'] ?? ''),
            '/back/overview',
            6,
            'lime'
        );
    }
}
