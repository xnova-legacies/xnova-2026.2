<?php

namespace App\Controllers\Front;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Services\SessionService;

final class LogoutController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessions = new SessionService(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        // L'identifiant de la connexion doit être lu avant la fin de session.
        $this->sessions->close(
            isset($_SESSION['xnova_session_id']) ? (int) $_SESSION['xnova_session_id'] : null
        );

        session_destroy();
        setcookie('nova-cookie', null, 0);

        // Redirection immédiate vers la page de connexion : la session est fermée,
        // la page de confirmation ne servait qu'à attendre un compte à rebours.
        return Response::redirect('/front/login');
    }
}
