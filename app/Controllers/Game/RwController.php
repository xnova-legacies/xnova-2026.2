<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

final class RwController extends AbstractController
{
    public function __construct(
        private readonly \App\Repositories\RwRepository $rw = new \App\Repositories\RwRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('rw');

        $lang = $this->lang();
        $user = $this->user();
        $dpath = $this->skinPath();

        $raportrow = $this->rw->findByRid(mysql_escape_string($_GET["raport"] ?? ''));

        if (
            ($raportrow["id_owner1"] == $user["id"]) or
            ($raportrow["id_owner2"] == $user["id"])
        ) {
            // Page autonome (hors habillage) : tout le document est dans un gabarit.
            $report = (($raportrow["id_owner1"] == $user["id"]) and ((int) $raportrow["struck"] === 1))
                ? (string) ($lang['rw_lost'] ?? '')
                : stripslashes((string) $raportrow["raport"]);

            return Response::raw($this->partial('rw_body', array(
                'rw_title' => $lang['sys_mess_attack_report'] ?? '',
                'rw_css' => $dpath . '/formate.css',
                'rw_report' => $report,
            )));
        }

        return Response::html('');
    }
}
