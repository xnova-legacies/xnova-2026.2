<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

final class FramesController extends AbstractController
{
    public static function legacyConstants(): array
    {
        return ['INSIDE' => true, 'INSTALL' => false, 'DISABLE_IDENTITY_CHECK' => true];
    }

    public function indexAction(Request $request): Response
    {
        return $this->redirect('/game/overview');
    }
}
