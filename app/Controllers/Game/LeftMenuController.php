<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\LeftMenu;
use App\Core\Request;
use App\Core\Response;

final class LeftMenuController extends AbstractController
{
    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $user = $this->user();

        return $this->renderPage(LeftMenu::render((int) $user['authlevel']), 'Menu', '', false);
    }
}
