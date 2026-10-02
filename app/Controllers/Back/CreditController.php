<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Api\CsrfToken;
use App\Core\GameConfig;
use App\Core\Request;
use App\Core\Response;

/**
 * Panneau d'administration : copyright étendu.
 *
 * Reprend `admin/credit.php` : trois réglages (`ExtCopyFrame`, `ExtCopyOwner`,
 * `ExtCopyFunct`) et la liste des auteurs. Mêmes réglages, écrits par
 * `GameConfig::set()`.
 */
final class CreditController extends AdminController
{
    private const KEYS = array('ExtCopyFrame', 'ExtCopyOwner', 'ExtCopyFunct');

    protected function requiredPermission(): string
    {
        return 'admin.credit';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('credit');

        $lang = $this->lang();

        if (is_string($request->post('opt_save')) && $request->post('opt_save') !== '') {
            return $this->save($request);
        }

        $config = GameConfig::load();

        $body = $this->adminPanel(
            'credit_form',
            $lang + $config + array(
                'credit_action' => '/back/credit',
                'csrf_token' => CsrfToken::token(),
                'checked_extcopy' => (int) ($config['ExtCopyFrame'] ?? 0) === 1 ? ' checked' : '',
                'ext_owner' => (string) ($config['ExtCopyOwner'] ?? ''),
                'ext_funct' => (string) ($config['ExtCopyFunct'] ?? ''),
            ),
            (string) ($lang['cred_credit'] ?? ''),
            'bi-award',
            '',
            true
        );

        return $this->adminPage($body, (string) ($lang['cred_credit'] ?? 'Administration'));
    }

    private function save(Request $request): Response
    {
        $lang = $this->lang();

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['sys_noaccess'] ?? ''),
                (string) ($lang['sys_noalloaw'] ?? ''),
                '/back/credit',
                3,
                'red'
            );
        }

        $enabled = $request->post('ExtCopyFrame') === 'on';

        // La page historique vidait les deux textes quand la case était décochée.
        GameConfig::set('ExtCopyFrame', $enabled ? '1' : '0');
        GameConfig::set('ExtCopyOwner', $enabled ? trim((string) $request->post('ExtCopyOwner', '')) : '');
        GameConfig::set('ExtCopyFunct', $enabled ? trim((string) $request->post('ExtCopyFunct', '')) : '');

        return $this->renderMessage(
            (string) ($lang['cred_done'] ?? ''),
            (string) ($lang['cred_ext'] ?? ''),
            '/back/credit',
            3,
            'lime'
        );
    }
}
