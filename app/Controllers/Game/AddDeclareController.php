<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\DeclareRepository;

final class AddDeclareController extends AbstractController
{
    public function __construct(
        private readonly DeclareRepository $declares = new DeclareRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('admin');
        $this->includeLang('declare');

        $user = $this->user();
        $lang = $this->lang();

        $mode = $_POST['mode'] ?? null;

        $PageTpl = $this->template('add_declare');
        $parse = $lang;

        if ($mode == 'addit') {
            $declarator = $user['id'];
            $declarator_name = addslashes(htmlspecialchars($user['username']));
            $decl1 = addslashes(htmlspecialchars($_POST['dec1'] ?? null));
            $decl2 = addslashes(htmlspecialchars($_POST['dec2'] ?? null));
            $decl3 = addslashes(htmlspecialchars($_POST['dec3'] ?? null));
            $reason1 = addslashes(htmlspecialchars($_POST['reason'] ?? null));

            $this->declares->insert($declarator, $declarator_name, $decl1, $decl2, $decl3, $reason1);
            $this->declares->validateUser($user['username']);

            return $this->renderMessage("Merci, votre demande a ete prise en compte. Les autres joueurs que vous avez implique doivent egalement et imperativement suivre cette procedure aussi.", "Ajout", '', '3', 'red');
        }

        $Page = $this->parse($PageTpl, $parse);

        return $this->renderPage($Page, $lang['declaration_title']);
    }
}
