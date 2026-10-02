<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;

final class JumpgateController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('infos');

        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();
        $resource = $this->resource();

        $RetMessage = null;

        if ($_POST) {
            $RestString = GetNextJumpWaitTime($planetrow);
            $NextJumpTime = $RestString['value'];
            $JumpTime = time();
            if ($NextJumpTime == 0) {
                $TargetPlanet = $_POST['jmpto'] ?? null;
                $TargetGate = $this->planets->findJumpGate((int) $TargetPlanet);
                if ($TargetGate['jump_gate'] > 0) {
                    $RestString = GetNextJumpWaitTime($TargetGate);
                    $NextDestTime = $RestString['value'];
                    if ($NextDestTime == 0) {
                        $ShipArray = array();
                        $depart = array();
                        $arrivee = array();
                        for ($Ship = 200; $Ship < 300; $Ship++) {
                            $ShipLabel = "c" . $Ship;
                            if (($_POST[$ShipLabel] ?? null) > $planetrow[$resource[$Ship]]) {
                                $ShipArray[$Ship] = $planetrow[$resource[$Ship]];
                            } else {
                                $ShipArray[$Ship] = $_POST[$ShipLabel] ?? null;
                            }
                            if ((int) $ShipArray[$Ship] !== 0) {
                                // Quantités **signées** : la planète de départ perd, celui d'arrivée
                                // gagne. La colonne vient de la table des unités, la quantité de
                                // la saisie (bornée par `(int)`, elle était concaténée).
                                $depart[$resource[$Ship]] = -(int) $ShipArray[$Ship];
                                $arrivee[$resource[$Ship]] = (int) $ShipArray[$Ship];
                            }
                        }
                        if ($depart !== array()) {
                            $this->planets->updateJumpShips((int) $planetrow['id'], $depart, $JumpTime);
                            $this->planets->updateJumpShips((int) $TargetGate['id'], $arrivee, $JumpTime);

                            $this->users->setCurrentPlanet((int) $TargetGate['id'], (int) $user['id']);

                            $planetrow['last_jump_time'] = $JumpTime;
                            $RestString = GetNextJumpWaitTime($planetrow);
                            $RetMessage = $lang['gate_jump_done'] . " - " . $RestString['string'];
                        } else {
                            $RetMessage = $lang['gate_wait_data'];
                        }
                    } else {
                        $RetMessage = $lang['gate_wait_dest'] . " - " . $RestString['string'];
                    }
                } else {
                    $RetMessage = $lang['gate_no_dest_g'];
                }
            } else {
                $RetMessage = $lang['gate_wait_star'] . " - " . $RestString['string'];
            }
        } else {
            $RetMessage = $lang['gate_wait_data'];
        }

        return $this->renderMessage($RetMessage, $lang['tech'][43], "/game/infos?gid=43", 4);
    }
}
