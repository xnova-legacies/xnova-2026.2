<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Services\MultiService;

/**
 * La déclaration de multi-comptes est un onglet de la page Options
 * (`/game/profil/options?tab=multi`) : les liens et favoris existants sont
 * redirigés. Le POST des anciens formulaires reste traité ici.
 */
final class MultiController extends AbstractController
{
    public function __construct(
        private readonly MultiService $multi = new MultiService(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        if (($_GET['mode'] ?? null) === 'add' && $request->method() === 'POST') {
            $this->includeLang('multi');
            $this->includeLang('system');

            $lang = $this->lang();
            $user = $this->user();

            if ($this->multi->declare((int) $user['id'], (string) ($_POST['texte'] ?? ''))) {
                return $this->renderMessage($lang['sys_request_ok'], $lang['sys_ok'], '/game/profil/options?tab=multi');
            }

            return $this->renderMessage($lang['DeclarationText'], $lang['multi_title'], '/game/profil/options?tab=multi');
        }

        return Response::redirect('/game/profil/options?tab=multi');
    }
}
