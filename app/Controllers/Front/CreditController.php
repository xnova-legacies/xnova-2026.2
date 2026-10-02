<?php

namespace App\Controllers\Front;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

final class CreditController extends AbstractController
{
    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('credit');

        $lang = $this->lang();
        $gameConfig = $this->gameConfig();
        $parse = $lang;

        // Bandeau de copyright externe : le balisage vit dans credit_body.tpl, seul
        // son affichage change (classe vide ou `d-none`).
        $parse['credit_ext_class'] = ($gameConfig['ExtCopyFrame'] == '1') ? '' : ' d-none';
        $parse['credit_ext_owner'] = nl2br(htmlspecialchars((string) $gameConfig['ExtCopyOwner'], ENT_QUOTES));
        $parse['credit_ext_funct'] = nl2br(htmlspecialchars((string) $gameConfig['ExtCopyFunct'], ENT_QUOTES));

        $page = $this->parse($this->template('credit_body'), $parse);

        return $this->renderPage($page, $lang['cred_credit']);
    }
}
