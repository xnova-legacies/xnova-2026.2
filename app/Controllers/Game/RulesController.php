<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

final class RulesController extends AbstractController
{
    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('rules');

        $lang = $this->lang();
        $parse = $lang;
        $parse['servername'] = $this->gameConfig()['game_name'];

        $page = $this->parse($this->template('rules_body'), $parse);

        // Même habillage que la page Contact : la barre de ressources reste visible.
        return $this->renderPage($page, $lang['rules']);
    }
}
