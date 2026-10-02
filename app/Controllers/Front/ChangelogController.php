<?php

namespace App\Controllers\Front;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

final class ChangelogController extends AbstractController
{
    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('changelog');

        $lang = $this->lang();
        $template = $this->template('changelog_table');
        $body = '';

        foreach ($lang['changelog'] as $a => $b) {
            $parse['version_number'] = $a;
            $parse['description'] = nl2br($b);

            $body .= $this->parse($template, $parse);
        }

        $parse = $lang;
        $parse['body'] = $body;

        $page = $this->parse($this->template('changelog_body'), $parse);

        return $this->renderPage($page, $lang['changelog_title']);
    }
}
