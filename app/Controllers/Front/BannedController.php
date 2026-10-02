<?php

namespace App\Controllers\Front;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\BannedRepository;

final class BannedController extends AbstractController
{
    public function __construct(
        private readonly BannedRepository $banned = new BannedRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('banned');

        $lang = $this->lang();
        $parse = $lang;
        $parse['dpath'] = $this->skinPath();
        $parse['mf'] = $GLOBALS['mf'] ?? '';

        $i = 0;
        $rows = '';
        foreach ($this->banned->findAll() as $u) {
            $rows .= $this->partial('banned_row', array(
                'ban_row_name' => htmlspecialchars((string) $u[1], ENT_QUOTES),
                'ban_row_reason' => htmlspecialchars((string) $u[2], ENT_QUOTES),
                'ban_row_from' => date("d/m/Y G:i:s", (int) $u[4]),
                'ban_row_to' => date("d/m/Y G:i:s", (int) $u[5]),
                'ban_row_by' => htmlspecialchars((string) $u[6], ENT_QUOTES),
            ));
            $i++;
        }

        $rows .= $this->partial('table_message_row', array(
            'colspan' => 5,
            'text' => ($i === 0)
                ? $lang['ban_no']
                : $lang['ban_thereare'] . ' ' . $i . ' ' . $lang['ban_players'],
        ));

        $parse['banned'] = $rows;

        return $this->renderPage($this->parse($this->template('banned_body'), $parse), $lang['ban_title']);
    }
}
