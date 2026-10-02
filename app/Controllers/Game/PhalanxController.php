<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\FleetRepository;
use App\Repositories\PlanetRepository;

final class PhalanxController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly FleetRepository $fleets = new FleetRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('overview');
        $this->includeLang('phalanx');

        $lang = $this->lang();
        $user = $this->user();

        $PageTPL = $this->template('phalanx_body');
        $PhalanxMoon = $this->planets->findCurrentById((int) $user['current_planet']);
        $page = '';

        // Règles de la phalange : la lune doit en être équipée, et la cible doit
        // tenir dans sa portée (son système ± son niveau). Hors de là : aucun scan,
        // et donc aucun deutérium débité.
        $PhalanxLevel = (int) ($PhalanxMoon['phalanx'] ?? 0);
        $ScanGalaxy = (int) ($_GET['galaxy'] ?? 0);
        $ScanSystem = (int) ($_GET['system'] ?? 0);
        $ScanPlanet = (int) ($_GET['planet'] ?? 0);
        $ScanType = (int) ($_GET['planettype'] ?? 1);
        $HorsPortee = $ScanSystem > 0
            && !\App\Core\Phalanx::inRange(
                (int) ($PhalanxMoon['galaxy'] ?? 0),
                (int) ($PhalanxMoon['system'] ?? 0),
                $ScanGalaxy,
                $ScanSystem,
                $PhalanxLevel
            );

        // La cible doit exister : une colonie abandonnée ou une lune détruite n'est
        // plus une planète, et un scan ne consomme pas de deutérium pour rien.
        $TargetInfo = ($ScanGalaxy > 0 && $ScanSystem > 0 && $ScanPlanet > 0)
            ? $this->planets->findByCoords($ScanGalaxy, $ScanSystem, $ScanPlanet, $ScanType)
            : false;
        $CibleAbsente = $TargetInfo === false;

        if ($PhalanxMoon['planet_type'] == 3) {
            $parse = $lang;

            $parse['phl_pl_galaxy'] = $PhalanxMoon['galaxy'];
            $parse['phl_pl_system'] = $PhalanxMoon['system'];
            $parse['phl_pl_place'] = $PhalanxMoon['planet'];
            $parse['phl_pl_name'] = $user['username'];

            if ($PhalanxMoon['deuterium'] > \App\Core\Phalanx::SCAN_COST && $PhalanxLevel > 0 && !$HorsPortee && !$CibleAbsente) {
                $this->planets->consumePhalanxDeuterium((int) $user['current_planet']);
                $parse['phl_er_deuter'] = "";
                $DoScan = true;
            } else {
                if ($CibleAbsente) {
                    $parse['phl_er_deuter'] = $lang['phl_no_target'];
                } elseif ($PhalanxLevel <= 0) {
                    $parse['phl_er_deuter'] = $lang['phl_no_phalanx'];
                } elseif ($HorsPortee) {
                    $parse['phl_er_deuter'] = sprintf(
                        $lang['phl_out_of_range'],
                        \App\Core\Phalanx::firstSystem((int) $PhalanxMoon['system'], $PhalanxLevel),
                        \App\Core\Phalanx::lastSystem((int) $PhalanxMoon['system'], $PhalanxLevel, \App\Core\GameConstants::maxSystemInGalaxy())
                    );
                } else {
                    $parse['phl_er_deuter'] = $lang['phl_no_deuter'];
                }

                $DoScan = false;
            }

            $Fleets = '';
            if ($DoScan == true) {
                $Galaxy = $_GET["galaxy"] ?? null;
                $System = $_GET["system"] ?? null;
                $Planet = $_GET["planet"] ?? null;
                $PlType = $_GET["planettype"] ?? null;

                $TargetName = $TargetInfo['name'] ?? '';

                $FleetToTarget = $this->fleets->findAtPosition(
                    (int) $Galaxy,
                    (int) $System,
                    (int) $Planet,
                    (int) $PlType
                );

                $fpage = array();
                $Record = null;
                foreach ($FleetToTarget as $FleetRow) {
                    $Record = ($Record ?? 0) + 1;

                    $StartTime = $FleetRow['fleet_start_time'];
                    $StayTime = $FleetRow['fleet_end_stay'];
                    $EndTime = $FleetRow['fleet_end_time'];

                    if ($FleetRow['fleet_owner'] == $TargetInfo['id_owner']) {
                        $FleetType = true;
                    } else {
                        $FleetType = false;
                    }

                    $FleetRow['fleet_resource_metal'] = 0;
                    $FleetRow['fleet_resource_crystal'] = 0;
                    $FleetRow['fleet_resource_deuterium'] = 0;

                    $Label = "fs";
                    if ($StartTime > time()) {
                        $fpage[$StartTime] = BuildFleetEventTable($FleetRow, 0, $FleetType, $Label, $Record);
                    }

                    if ($FleetRow['fleet_mission'] <> 4) {
                        $Label = "ft";
                        if ($StayTime > time()) {
                            $fpage[$StayTime] = BuildFleetEventTable($FleetRow, 1, $FleetType, $Label, $Record);
                        }

                        if ($FleetType == true) {
                            $Label = "fe";
                            if ($EndTime > time()) {
                                $fpage[$EndTime] = BuildFleetEventTable($FleetRow, 2, $FleetType, $Label, $Record);
                            }
                        }
                    }
                }

                if (count($fpage) > 0) {
                    ksort($fpage);
                    foreach ($fpage as $FleetTime => $FleetContent) {
                        $Fleets .= $FleetContent . "\n";
                    }
                }
            }

            $parse['phl_fleets_table'] = $Fleets;
            $page = $this->parse($PageTPL, $parse);
        }

        return $this->renderPage($page, $lang['sys_phalanx'], false, '', false);
    }
}
