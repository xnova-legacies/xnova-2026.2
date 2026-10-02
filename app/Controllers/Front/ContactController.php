<?php

namespace App\Controllers\Front;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;

final class ContactController extends AbstractController
{
    public static function legacyConstants(): array
    {
        return ['INSIDE' => true, 'INSTALL' => false, 'DISABLE_IDENTITY_CHECK' => true];
    }

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy(['DISABLE_IDENTITY_CHECK' => true]);

        $this->includeLang('contact');

        $lang = $this->lang();
        $parse = $lang;

        $parse['ctc_admin_list'] = '';

        foreach ($this->users->findGameOps() as $ops) {
            $bloc['ctc_data_name'] = htmlspecialchars((string) $ops['username'], ENT_QUOTES);
            $bloc['ctc_data_auth'] = $lang['user_level'][$ops['authlevel']] ?? '';
            // Le lien mailto vit dans contact_body_rows.tpl : ici, l'adresse seule.
            $bloc['ctc_data_mail'] = htmlspecialchars((string) $ops['email'], ENT_QUOTES);
            $parse['ctc_admin_list'] .= $this->parse($this->template('contact_body_rows'), $bloc);
        }

        $page = $this->parse($this->template('contact_body'), $parse);

        return $this->renderPage($page, $lang['ctc_title']);
    }
}
