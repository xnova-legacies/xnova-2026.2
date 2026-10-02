<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlanetRepository;

class InfosController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepository $planets = new PlanetRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('infos');

        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();

        $gid = intval($_GET['gid'] ?? 0);

        $page = $this->showBuildingInfoPage($user, $planetrow, $gid);

        return $this->renderPage($page, $lang['nfo_page_title']);
    }

    /**
     * Majoration de production apportée par un module (1 = aucune), par ressource.
     *
     * Le Coeur de l'application n'applique aucun bonus d'officier : la fiche demande la
     * majoration pour les trois ressources et pour l'énergie, et un module la surcharge — le
     * module `officier` applique 5 % par niveau de géologue (d'ingénieur pour l'énergie),
     * exactement comme la page des ressources. Sans module, la fiche affiche la production
     * brute de la formule.
     *
     * @param array<string, mixed> $user
     * @param string               $resource `metal`, `crystal`, `deuterium` ou `energy`
     */
    protected function productionFactor(array $user, string $resource): float
    {
        return 1.0;
    }

    private function buildFleetListRows(array $CurrentPlanet): string
    {
        $resource = $this->resource();
        $lang = $this->lang();

        $RowsTPL = $this->template('gate_fleet_rows');
        $CurrIdx = 1;
        $Result = "";
        for ($Ship = 300; $Ship > 200; $Ship--) {
            if (($resource[$Ship] ?? '') != "") {
                if ($CurrentPlanet[$resource[$Ship]] > 0) {
                    $bloc['idx'] = $CurrIdx;
                    $bloc['fleet_id'] = $Ship;
                    $bloc['fleet_name'] = $lang['tech'][$Ship];
                    $bloc['fleet_max'] = pretty_number($CurrentPlanet[$resource[$Ship]]);
                    $bloc['gate_ship_dispo'] = $lang['gate_ship_dispo'];
                    $Result .= $this->parse($RowsTPL, $bloc);
                    $CurrIdx++;
                }
            }
        }

        return $Result;
    }

    private function buildJumpableMoonCombo(array $CurrentUser, array $CurrentPlanet): string
    {
        $resource = $this->resource();

        $moons = $this->planets->findMoonsByOwner((int) $CurrentUser['id']);
        $Combo = "";
        foreach ($moons as $CurMoon) {
            if ($CurMoon['id'] != $CurrentPlanet['id']) {
                $RestString = GetNextJumpWaitTime($CurMoon);
                if ($CurMoon[$resource[43]] >= 1) {
                    $Combo .= "<option value=\"" . $CurMoon['id'] . "\">[" . $CurMoon['galaxy'] . ":" . $CurMoon['system'] . ":" . $CurMoon['planet'] . "] " . $CurMoon['name'] . $RestString['string'] . "</option>\n";
                }
            }
        }

        return $Combo;
    }

    private function showProductionTable(array $CurrentUser, array $CurrentPlanet, $BuildID, $Template): string
    {
        $ProdGrid = $GLOBALS['ProdGrid'] ?? [];
        $resource = $this->resource();
        $gameConfig = $this->gameConfig();

        $BuildLevelFactor = $CurrentPlanet[$resource[$BuildID] . "_porcent"];
        $CurrentBuildtLvl = $CurrentPlanet[$resource[$BuildID]];

        $BuildLevel = ($CurrentBuildtLvl > 0) ? $CurrentBuildtLvl : 1;
        $Prod[1] = (floor(eval($ProdGrid[$BuildID]['formule']['metal']) * $gameConfig['resource_multiplier']) * $this->productionFactor($CurrentUser, 'metal'));
        $Prod[2] = (floor(eval($ProdGrid[$BuildID]['formule']['crystal']) * $gameConfig['resource_multiplier']) * $this->productionFactor($CurrentUser, 'crystal'));
        $Prod[3] = (floor(eval($ProdGrid[$BuildID]['formule']['deuterium']) * $gameConfig['resource_multiplier']) * $this->productionFactor($CurrentUser, 'deuterium'));
        $Prod[4] = (floor(eval($ProdGrid[$BuildID]['formule']['energy']) * $gameConfig['resource_multiplier']) * $this->productionFactor($CurrentUser, 'energy'));
        $BuildLevel = "";

        $ActualProd = floor($Prod[$BuildID]);
        if ($BuildID != 12) {
            $ActualNeed = floor($Prod[4]);
        } else {
            $ActualNeed = floor($Prod[3]);
        }

        $BuildStartLvl = $CurrentBuildtLvl - 2;
        if ($BuildStartLvl < 1) {
            $BuildStartLvl = 1;
        }
        $Table = "";
        $ProdFirst = 0;
        for ($BuildLevel = $BuildStartLvl; $BuildLevel < $BuildStartLvl + 10; $BuildLevel++) {
            if ($BuildID != 42) {
                $Prod[1] = (floor(eval($ProdGrid[$BuildID]['formule']['metal']) * $gameConfig['resource_multiplier']) * $this->productionFactor($CurrentUser, 'metal'));
                $Prod[2] = (floor(eval($ProdGrid[$BuildID]['formule']['crystal']) * $gameConfig['resource_multiplier']) * $this->productionFactor($CurrentUser, 'crystal'));
                $Prod[3] = (floor(eval($ProdGrid[$BuildID]['formule']['deuterium']) * $gameConfig['resource_multiplier']) * $this->productionFactor($CurrentUser, 'deuterium'));
                $Prod[4] = (floor(eval($ProdGrid[$BuildID]['formule']['energy']) * $gameConfig['resource_multiplier']) * $this->productionFactor($CurrentUser, 'energy'));

                $bloc['build_lvl'] = ($CurrentBuildtLvl == $BuildLevel) ? "<font color=\"#ff0000\">" . $BuildLevel . "</font>" : $BuildLevel;
                if ($ProdFirst > 0) {
                    if ($BuildID != 12) {
                        $bloc['build_gain'] = "<font color=\"lime\">(" . pretty_number(floor($Prod[$BuildID] - $ProdFirst)) . ")</font>";
                    } else {
                        $bloc['build_gain'] = "<font color=\"lime\">(" . pretty_number(floor($Prod[4] - $ProdFirst)) . ")</font>";
                    }
                } else {
                    $bloc['build_gain'] = "";
                }
                if ($BuildID != 12) {
                    $bloc['build_prod'] = pretty_number(floor($Prod[$BuildID]));
                    $bloc['build_prod_diff'] = colorNumber(pretty_number(floor($Prod[$BuildID] - $ActualProd)));
                    $bloc['build_need'] = colorNumber(pretty_number(floor($Prod[4])));
                    $bloc['build_need_diff'] = colorNumber(pretty_number(floor($Prod[4] - $ActualNeed)));
                } else {
                    $bloc['build_prod'] = pretty_number(floor($Prod[4]));
                    $bloc['build_prod_diff'] = colorNumber(pretty_number(floor($Prod[4] - $ActualProd)));
                    $bloc['build_need'] = colorNumber(pretty_number(floor($Prod[3])));
                    $bloc['build_need_diff'] = colorNumber(pretty_number(floor($Prod[3] - $ActualNeed)));
                }
                if ($ProdFirst == 0) {
                    if ($BuildID != 12) {
                        $ProdFirst = floor($Prod[$BuildID]);
                    } else {
                        $ProdFirst = floor($Prod[4]);
                    }
                }
            } else {
                $bloc['build_lvl'] = ($CurrentBuildtLvl == $BuildLevel) ? "<font color=\"#ff0000\">" . $BuildLevel . "</font>" : $BuildLevel;
                $bloc['build_range'] = ($BuildLevel * $BuildLevel) - 1;
            }
            $Table .= $this->parse($Template, $bloc);
        }

        return $Table;
    }

    private function showRapidFireTo($BuildID): string
    {
        $lang = $this->lang();
        $CombatCaps = $GLOBALS['CombatCaps'] ?? [];

        $ResultString = "";
        for ($Type = 200; $Type < 500; $Type++) {
            if (($CombatCaps[$BuildID]['sd'][$Type] ?? 0) > 1) {
                $ResultString .= $lang['nfo_rf_again'] . " " . $lang['tech'][$Type] . " <font color=\"#00ff00\">" . $CombatCaps[$BuildID]['sd'][$Type] . "</font><br>";
            }
        }

        return $ResultString;
    }

    private function showRapidFireFrom($BuildID): string
    {
        $lang = $this->lang();
        $CombatCaps = $GLOBALS['CombatCaps'] ?? [];

        $ResultString = "";
        for ($Type = 200; $Type < 500; $Type++) {
            if (($CombatCaps[$Type]['sd'][$BuildID] ?? 0) > 1) {
                $ResultString .= $lang['nfo_rf_from'] . " " . $lang['tech'][$Type] . " <font color=\"#ff0000\">" . $CombatCaps[$Type]['sd'][$BuildID] . "</font><br>";
            }
        }

        return $ResultString;
    }

    private function showBuildingInfoPage(array $CurrentUser, array $CurrentPlanet, $BuildID): string
    {
        $lang = $this->lang();
        $dpath = $this->skinPath();
        $resource = $this->resource();
        $pricelist = $GLOBALS['pricelist'] ?? [];
        // Les catégories du jeu (`fleet`, `defense`, `officier`…), que les modules
        // complètent : c'est par elles que la fiche choisit son gabarit, jamais par
        // une plage d'identifiants — sinon une unité ajoutée par un module n'aurait
        // pas de fiche (vécu : `?gid=216`, l'extracteur, finissait en erreur fatale).
        $reslist = $GLOBALS['reslist'] ?? [];

        $GateTPL = '';
        $DestroyTPL = '';
        $TableHeadTPL = '';
        $TableTPL = '';

        $parse = $lang;
        $parse['dpath'] = $dpath;
        $parse['name'] = $lang['info'][$BuildID]['name'] ?? '';
        $parse['image'] = $BuildID;
        $parse['description'] = $lang['info'][$BuildID]['description'] ?? '';

        if ($BuildID >= 1 && $BuildID <= 3) {
            $PageTPL = $this->template('info_buildings_table');
            $DestroyTPL = $this->template('info_buildings_destroy');
            $TableHeadTPL = "<tr><td class=\"c\">{nfo_level}</td><td class=\"c\">{nfo_prod_p_hour}</td><td class=\"c\">{nfo_difference}</td><td class=\"c\">{nfo_used_energy}</td><td class=\"c\">{nfo_difference}</td></tr>";
            $TableTPL = "<tr><th>{build_lvl}</th><th>{build_prod} {build_gain}</th><th>{build_prod_diff}</th><th>{build_need}</th><th>{build_need_diff}</th></tr>";
        } elseif ($BuildID == 4) {
            $PageTPL = $this->template('info_buildings_table');
            $DestroyTPL = $this->template('info_buildings_destroy');
            $TableHeadTPL = "<tr><td class=\"c\">{nfo_level}</td><td class=\"c\">{nfo_prod_energy}</td><td class=\"c\">{nfo_difference}</td></tr>";
            $TableTPL = "<tr><th>{build_lvl}</th><th>{build_prod} {build_gain}</th><th>{build_prod_diff}</th></tr>";
        } elseif ($BuildID == 12) {
            $PageTPL = $this->template('info_buildings_table');
            $DestroyTPL = $this->template('info_buildings_destroy');
            $TableHeadTPL = "<tr><td class=\"c\">{nfo_level}</td><td class=\"c\">{nfo_prod_energy}</td><td class=\"c\">{nfo_difference}</td><td class=\"c\">{nfo_used_deuter}</td><td class=\"c\">{nfo_difference}</td></tr>";
            $TableTPL = "<tr><th>{build_lvl}</th><th>{build_prod} {build_gain}</th><th>{build_prod_diff}</th><th>{build_need}</th><th>{build_need_diff}</th></tr>";
        } elseif ($BuildID >= 14 && $BuildID <= 32) {
            $PageTPL = $this->template('info_buildings_general');
            $DestroyTPL = $this->template('info_buildings_destroy');
        } elseif ($BuildID == 33) {
            $PageTPL = $this->template('info_buildings_general');
        } elseif ($BuildID == 34) {
            $PageTPL = $this->template('info_buildings_general');
            $DestroyTPL = $this->template('info_buildings_destroy');
        } elseif ($BuildID == 44) {
            $PageTPL = $this->template('info_buildings_general');
            $DestroyTPL = $this->template('info_buildings_destroy');
        } elseif ($BuildID == 41) {
            $PageTPL = $this->template('info_buildings_general');
        } elseif ($BuildID == 42) {
            $PageTPL = $this->template('info_buildings_table');
            $TableHeadTPL = "<tr><td class=\"c\">{nfo_level}</td><td class=\"c\">{nfo_range}</td></tr>";
            $TableTPL = "<tr><th>{build_lvl}</th><th>{build_range}</th></tr>";
            $DestroyTPL = $this->template('info_buildings_destroy');
        } elseif ($BuildID == 43) {
            $PageTPL = $this->template('info_buildings_general');
            $GateTPL = $this->template('gate_fleet_table');
            $DestroyTPL = $this->template('info_buildings_destroy');
        } elseif ($BuildID >= 106 && $BuildID <= 199) {
            $PageTPL = $this->template('info_buildings_general');
        } elseif (in_array($BuildID, (array) ($reslist['fleet'] ?? array()), true)) {
            $PageTPL = $this->template('info_buildings_fleet');
            $parse['element_typ'] = $lang['tech'][200];
            $parse['rf_info_to'] = $this->showRapidFireTo($BuildID);
            $parse['rf_info_fr'] = $this->showRapidFireFrom($BuildID);
            $parse['hull_pt'] = pretty_number($pricelist[$BuildID]['metal'] + $pricelist[$BuildID]['crystal']);
            $parse['shield_pt'] = pretty_number($GLOBALS['CombatCaps'][$BuildID]['shield']);
            $parse['attack_pt'] = pretty_number($GLOBALS['CombatCaps'][$BuildID]['attack']);
            $parse['capacity_pt'] = pretty_number($pricelist[$BuildID]['capacity']);
            $parse['base_speed'] = pretty_number($pricelist[$BuildID]['speed']);
            $parse['base_conso'] = pretty_number($pricelist[$BuildID]['consumption']);
            if ($BuildID == 202) {
                $parse['upd_speed'] = "<font color=\"yellow\">(" . pretty_number($pricelist[$BuildID]['speed2']) . ")</font>";
                $parse['upd_conso'] = "<font color=\"yellow\">(" . pretty_number($pricelist[$BuildID]['consumption2']) . ")</font>";
            } elseif ($BuildID == 211) {
                $parse['upd_speed'] = "<font color=\"yellow\">(" . pretty_number($pricelist[$BuildID]['speed2']) . ")</font>";
            }
        } elseif ($BuildID >= 502 && $BuildID <= 503) {
            // Les missiles n'ont pas de RapidFire : leur fiche est celle de la défense
            // sans ce bloc. Ils sont testés **avant** la catégorie, qui les contient.
            $PageTPL = $this->template('info_buildings_defense');
            $parse['element_typ'] = $lang['tech'][400];
            $parse['hull_pt'] = pretty_number($pricelist[$BuildID]['metal'] + $pricelist[$BuildID]['crystal']);
            $parse['shield_pt'] = pretty_number($GLOBALS['CombatCaps'][$BuildID]['shield']);
            $parse['attack_pt'] = pretty_number($GLOBALS['CombatCaps'][$BuildID]['attack']);
        } elseif (in_array($BuildID, (array) ($reslist['defense'] ?? array()), true)) {
            $PageTPL = $this->template('info_buildings_defense');
            $parse['element_typ'] = $lang['tech'][400];
            $parse['rf_info_to'] = $this->showRapidFireTo($BuildID);
            $parse['rf_info_fr'] = $this->showRapidFireFrom($BuildID);
            $parse['hull_pt'] = pretty_number($pricelist[$BuildID]['metal'] + $pricelist[$BuildID]['crystal']);
            $parse['shield_pt'] = pretty_number($GLOBALS['CombatCaps'][$BuildID]['shield']);
            $parse['attack_pt'] = pretty_number($GLOBALS['CombatCaps'][$BuildID]['attack']);
        } elseif (in_array($BuildID, (array) ($reslist['officier'] ?? array()), true)) {
            $PageTPL = $this->template('info_officiers_general');
        }

        if (!isset($PageTPL)) {
            // Aucune catégorie ne réclame cet identifiant : la fiche générique évite
            // une page blanche. Un module qui ajoute une unité lui donne sa fiche en
            // la déclarant dans la catégorie du jeu (voir `Modules\Extracteurs\Core\Tables`).
            $PageTPL = $this->template('info_buildings_general');
        }

        if (($TableHeadTPL ?? '') != '') {
            $parse['table_head'] = $this->parse($TableHeadTPL, $lang);
            $parse['table_data'] = $this->showProductionTable($CurrentUser, $CurrentPlanet, $BuildID, $TableTPL);
        }

        $page = $this->parse($PageTPL, $parse);
        if ($GateTPL != '') {
            if ($CurrentPlanet[$resource[$BuildID]] > 0) {
                $RestString = GetNextJumpWaitTime($CurrentPlanet);
                $parse['gate_start_link'] = BuildPlanetAdressLink($CurrentPlanet);
                if ($RestString['value'] != 0) {
                    $parse['gate_time_script'] = InsertJavaScriptChronoApplet("Gate", "1", $RestString['value'], true);
                    $parse['gate_wait_time'] = "<div id=\"bxx" . "Gate" . "1" . "\"></div>";
                    $parse['gate_script_go'] = InsertJavaScriptChronoApplet("Gate", "1", $RestString['value'], false);
                } else {
                    $parse['gate_time_script'] = "";
                    $parse['gate_wait_time'] = "";
                    $parse['gate_script_go'] = "";
                }
                $parse['gate_dest_moons'] = $this->buildJumpableMoonCombo($CurrentUser, $CurrentPlanet);
                $parse['gate_fleet_rows'] = $this->buildFleetListRows($CurrentPlanet);
                $page .= $this->parse($GateTPL, $parse);
            }
        }

        if ($DestroyTPL != '') {
            if ($CurrentPlanet[$resource[$BuildID]] > 0) {
                $NeededRessources = GetBuildingPrice($CurrentUser, $CurrentPlanet, $BuildID, true, true);
                $DestroyTime = GetBuildingTime($CurrentUser, $CurrentPlanet, $BuildID) / 2;
                $parse['destroyurl'] = "/game/buildings?cmd=destroy&building=" . $BuildID;
                $parse['levelvalue'] = $CurrentPlanet[$resource[$BuildID]];
                $parse['nfo_metal'] = $lang['Metal'];
                $parse['nfo_crysta'] = $lang['Crystal'];
                $parse['nfo_deuter'] = $lang['Deuterium'];
                $parse['metal'] = pretty_number($NeededRessources['metal']);
                $parse['crystal'] = pretty_number($NeededRessources['crystal']);
                $parse['deuterium'] = pretty_number($NeededRessources['deuterium']);
                $parse['destroytime'] = pretty_time($DestroyTime);
                $page .= $this->parse($DestroyTPL, $parse);
            }
        }

        return $page;
    }
}
