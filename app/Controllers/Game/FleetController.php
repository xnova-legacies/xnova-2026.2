<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\BuddyRepository;
use App\Repositories\FleetRepository;
use App\Repositories\GalaxyRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\StatsRepository;
use App\Repositories\UserRepository;
use App\Services\FleetDispatchService;
use App\Services\FleetService;

/**
 * La classe n'est **pas** `final` : un module peut en dériver pour compléter les
 * missions proposées (`extraMissions()`), voir la convention de surcharge
 * (`Modules::overrideFor()`).
 */
class FleetController extends AbstractController
{
    public function __construct(
        private readonly FleetRepository $fleets = new FleetRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly StatsRepository $stats = new StatsRepository(),
        private readonly GalaxyRepository $galaxies = new GalaxyRepository(),
        private readonly BuddyRepository $buddies = new BuddyRepository(),
        private readonly FleetService $fleetService = new FleetService(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('fleet');

        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();
        $resource = $this->resource();
        $reslist = $this->resList();
        $pricelist = $GLOBALS['pricelist'] ?? [];

        $maxfleet_count = count($this->fleets->findByOwner((int) $user['id']));
        $MaxFlyingFleets = $maxfleet_count;

        $MaxExpedition = $user[($resource[124] ?? 124)];
        $ExpeditionEnCours = null;
        $EnvoiMaxExpedition = null;
        if ($MaxExpedition >= 1) {
            $ExpeditionEnCours = $this->fleets->findByOwnerMissionCount((int) $user['id'], 15)['expedi'];
            $EnvoiMaxExpedition = 1 + floor($MaxExpedition / 3);
        }

        $MaxFlottes = 1 + $user[($resource[108] ?? 108)];

        CheckPlanetUsedFields($planetrow);

        $this->includeLang('fleet');
        $lang = $this->lang();

        $missiontype = array(
            1 => $lang['type_mission'][1],
            2 => $lang['type_mission'][2],
            3 => $lang['type_mission'][3],
            4 => $lang['type_mission'][4],
            5 => $lang['type_mission'][5],
            6 => $lang['type_mission'][6],
            7 => $lang['type_mission'][7],
            8 => $lang['type_mission'][8],
            9 => $lang['type_mission'][9],
            10 => $lang['type_mission'][10],
            15 => $lang['type_mission'][15]
        ) + $this->missionLabels($lang);

        $galaxy = ($_GET['galaxy'] ?? null);
        $system = ($_GET['system'] ?? null);
        $planet = ($_GET['planet'] ?? null);
        $planettype = ($_GET['planettype'] ?? null);
        $target_mission = ($_GET['target_mission'] ?? null);

        if (!$galaxy) {
            $galaxy = $planetrow['galaxy'];
        }
        if (!$system) {
            $system = $planetrow['system'];
        }
        if (!$planet) {
            $planet = $planetrow['planet'];
        }
        if (!$planettype) {
            $planettype = $planetrow['planet_type'];
        }

        $page = "<script src=\"/scripts/fleet.js\"></script>\n";

        // ---- Flottes en vol ----
        $page .= "<div class=\"card xnova-panel border-0 shadow-sm mb-3 xnova-fleet\">";
        $page .= "<div class=\"card-header d-flex flex-wrap justify-content-between align-items-center gap-2\">";
        $page .= "<span class=\"fw-semibold\">" . $lang['fl_title'] . " <span class=\"text-body-secondary fw-normal\">" . $MaxFlyingFleets . " " . $lang['fl_sur'] . " " . $MaxFlottes . "</span></span>";
        $page .= "<span class=\"text-body-secondary\">" . $ExpeditionEnCours . "/" . $EnvoiMaxExpedition . " " . $lang['fl_expttl'] . "</span>";
        $page .= "</div>";
        $page .= "<div class=\"table-responsive\">";
        $page .= "<table class=\"table table-sm table-hover align-middle mb-0 xnova-fleet-table\">";
        $page .= "<thead><tr>";
        $page .= "<th scope=\"col\">" . $lang['fl_id'] . "</th>";
        $page .= "<th scope=\"col\">" . $lang['fl_mission'] . "</th>";
        $page .= "<th scope=\"col\">" . $lang['fl_count'] . "</th>";
        $page .= "<th scope=\"col\">" . $lang['fl_from'] . "</th>";
        $page .= "<th scope=\"col\">" . $lang['fl_start_t'] . "</th>";
        $page .= "<th scope=\"col\">" . $lang['fl_dest'] . "</th>";
        $page .= "<th scope=\"col\">" . $lang['fl_dest_t'] . "</th>";
        $page .= "<th scope=\"col\">" . $lang['fl_back_in'] . "</th>";
        $page .= "<th scope=\"col\">" . $lang['fl_order'] . "</th>";
        $page .= "</tr></thead><tbody>";

        $rows = $this->fleets->findByOwnerRaw((int) $user['id']);
        $i = 0;

        foreach ($rows as $f) {
            $i++;
            $page .= "<tr>";
            $page .= "<td>" . $i . "</td>";

            $page .= "<td>";
            $page .= $missiontype[$f['fleet_mission']];
            if (($f['fleet_start_time'] + 1) == $f['fleet_end_time']) {
                $page .= "<br><span class=\"text-body-secondary small\">" . $lang['fl_back_to'] . "</span>";
            } else {
                $page .= "<br><span class=\"text-body-secondary small\">" . $lang['fl_get_to'] . "</span>";
            }
            $page .= "</td>";

            $fleetDetails = array();
            foreach (explode(";", $f['fleet_array']) as $group) {
                if ($group != '') {
                    $ship = explode(",", $group);
                    $fleetDetails[] = $lang['tech'][$ship[0]] . ": " . pretty_number((int) $ship[1]);
                }
            }
            $page .= "<td title=\"" . htmlspecialchars(implode(' | ', $fleetDetails), ENT_QUOTES) . "\">" . pretty_number($f['fleet_amount']) . "</td>";
            $page .= "<td class=\"text-nowrap\">[" . $f['fleet_start_galaxy'] . ":" . $f['fleet_start_system'] . ":" . $f['fleet_start_planet'] . "]</td>";
            $page .= "<td class=\"text-nowrap\">" . gmdate("d.m.Y H:i:s", $f['fleet_start_time']) . "</td>";
            $page .= "<td class=\"text-nowrap\">[" . $f['fleet_end_galaxy'] . ":" . $f['fleet_end_system'] . ":" . $f['fleet_end_planet'] . "]</td>";
            // Mise en orbite : aucune heure de retour n'est prévue avant le rappel.
            $orbiting = (int) $f['fleet_mission'] === FleetDispatchService::MISSION_ORBIT
                && (int) $f['fleet_mess'] === 0
                && (int) $f['fleet_end_stay'] < 0;
            $page .= "<td class=\"text-nowrap\">" . ($orbiting ? '&ndash;' : gmdate("d.m.Y H:i:s", $f['fleet_end_time'])) . "</td>";

            $rest = (int) floor($f['fleet_end_time'] + 1 - time());
            if ($orbiting) {
                $page .= "<td class=\"xnova-fleet-countdown text-body-secondary\">" . $lang['fl_orbit_in_orbit'] . "</td>";
            } else {
                $page .= "<td class=\"xnova-fleet-countdown\"><span id=\"bxxfleet" . (int) $f['fleet_id'] . "\">" . pretty_time($rest) . "</span></td>";
            }

            $page .= "<td class=\"text-nowrap\">";
            if ($f['fleet_mess'] == 0) {
                $page .= "<form action=\"/game/fleet/back\" method=\"post\" class=\"d-inline\">";
                $page .= "<input name=\"fleetid\" value=\"" . $f['fleet_id'] . "\" type=\"hidden\">";
                $page .= "<button class=\"btn btn-sm btn-outline-secondary\" type=\"submit\" name=\"send\"><i class=\"bi bi-arrow-counterclockwise\" aria-hidden=\"true\"></i> " . $lang['fl_back_to_ttl'] . "</button>";
                $page .= "</form>";
                if ($f['fleet_mission'] == 1) {
                    $page .= " <form action=\"/game/acs\" method=\"post\" class=\"d-inline\">";
                    $page .= "<input name=\"fleetid\" value=\"" . $f['fleet_id'] . "\" type=\"hidden\">";
                    $page .= "<button class=\"btn btn-sm btn-outline-primary\" type=\"submit\"><i class=\"bi bi-people\" aria-hidden=\"true\"></i> " . $lang['fl_associate'] . "</button>";
                    $page .= "</form>";
                }
            } else {
                $page .= "<span class=\"text-body-secondary\">&ndash;</span>";
            }
            $page .= "</td>";
            $page .= "</tr>";

            // Compte a rebours JS (meme mecanisme que la page Vue generale).
            if (empty($orbiting)) {
                $page .= InsertJavaScriptChronoApplet('fleet', (int) $f['fleet_id'], $rest, true);
                $page .= InsertJavaScriptChronoApplet('fleet', (int) $f['fleet_id'], $rest, false);
            }
        }

        if ($i == 0) {
            $page .= "<tr>";
            for ($c = 0; $c < 9; $c++) {
                $page .= "<td class=\"text-center text-body-secondary\">&ndash;</td>";
            }
            $page .= "</tr>";
        }

        $page .= "</tbody></table>";
        $page .= "</div>";

        if ($MaxFlottes == $MaxFlyingFleets) {
            $page .= "<div class=\"card-body pt-0\"><div class=\"alert alert-warning mb-0\"><i class=\"bi bi-exclamation-triangle\" aria-hidden=\"true\"></i> " . $lang['fl_noslotfree'] . "</div></div>";
        }

        $page .= "</div>";

        if (!$planetrow) {
            return $this->renderMessage($lang['fl_noplanetrow'], $lang['fl_error']);
        }

        $galaxy = intval($_POST['galaxy'] ?? $_GET['galaxy'] ?? 0);
        $system = intval($_POST['system'] ?? $_GET['system'] ?? 0);
        $planet = intval($_POST['planet'] ?? $_GET['planet'] ?? 0);
        $planettype = intval($_POST['planettype'] ?? $_GET['planettype'] ?? 0);
        // Le retour depuis l'etape suivante arrive en POST : la cible et la
        // mission deja choisies sont reprises telles quelles.
        $target_mission = intval($_POST['target_mission'] ?? $_POST['mission'] ?? $_GET['target_mission'] ?? 0);
        $ShipData = "";
        $have_ships = false;

        // ---- Nouvelle mission ----
        $page .= "<div class=\"card xnova-panel border-0 shadow-sm mb-3 xnova-fleet-form\">";
        $page .= "<form action=\"/game/fleet/floten1\" method=\"post\">";
        $page .= "<div class=\"card-header fw-semibold\">" . $lang['fl_new_miss'] . "</div>";
        $page .= "<div class=\"table-responsive\">";
        $page .= "<table class=\"table table-sm align-middle mb-0 xnova-fleet-table\">";
        $page .= "<thead><tr>";
        $page .= "<th scope=\"col\">" . $lang['fl_fleet_typ'] . "</th>";
        $page .= "<th scope=\"col\" class=\"text-end\">" . $lang['fl_fleet_disp'] . "</th>";
        $page .= "<th scope=\"col\"><span class=\"visually-hidden\">" . $lang['fl_selmax'] . "</span></th>";
        $page .= "<th scope=\"col\"><span class=\"visually-hidden\">" . $lang['fl_count'] . "</span></th>";
        $page .= "</tr></thead><tbody>";

        foreach ($reslist['fleet'] as $n => $i) {
            if ($planetrow[$resource[$i]] > 0) {
                $have_ships = true;
                $shipCount = (int) $planetrow[$resource[$i]];

                $page .= "<tr>";
                $page .= "<td>" . $lang['tech'][$i] . "</td>";
                $page .= "<td class=\"text-end text-body-secondary\">" . pretty_number($shipCount) . "</td>";

                $ShipData .= "<input type=\"hidden\" name=\"maxship" . $i . "\" value=\"" . $shipCount . "\">";
                $ShipData .= "<input type=\"hidden\" name=\"consumption" . $i . "\" value=\"" . GetShipConsumption($i, $user) . "\">";
                $ShipData .= "<input type=\"hidden\" name=\"speed" . $i . "\" value=\"" . GetFleetMaxSpeed("", $i, $user) . "\">";
                $ShipData .= "<input type=\"hidden\" name=\"capacity" . $i . "\" value=\"" . $pricelist[$i]['capacity'] . "\">";

                if ($i == 212) {
                    $page .= "<td></td><td></td>";
                } else {
                    // Retour depuis l'etape suivante : les quantites deja saisies
                    // sont reprises au lieu d'etre remises a zero. Les raccourcis de
                    // la vue galaxie passent par l'adresse (GET) : la quantite
                    // demandee arrive donc par `$_GET`, sinon le vaisseau resterait
                    // a zero et le lien n'aurait servi a rien.
                    $prefill = min($shipCount, max(0, (int) ($_POST['ship' . $i] ?? $_GET['ship' . $i] ?? 0)));

                    $page .= "<td><a class=\"btn btn-sm btn-outline-secondary\" href=\"javascript:maxShip('ship" . $i . "');shortInfo();\">" . $lang['fl_selmax'] . "</a></td>";
                    $page .= "<td class=\"xnova-fleet-ship\"><input class=\"form-control form-control-sm\" type=\"text\" name=\"ship" . $i . "\" size=\"10\" value=\"" . $prefill . "\" onfocus=\"javascript:if(this.value == '0') this.value='';\" onblur=\"javascript:if(this.value == '') this.value='0';\" onChange=\"shortInfo()\" onKeyUp=\"shortInfo()\" aria-label=\"" . htmlspecialchars(html_entity_decode($lang['tech'][$i]), ENT_QUOTES) . "\"></td>";
                }
                $page .= "</tr>";
            }
        }

        $page .= "</tbody></table>";
        $page .= "</div>";

        if (!$have_ships) {
            $page .= "<div class=\"card-body\"><div class=\"alert alert-info mb-0\">" . $lang['fl_noships'] . "</div></div>";
            $page .= "<div class=\"card-footer text-end\">";
            $page .= "<button class=\"btn btn-primary\" type=\"submit\">" . $lang['fl_continue'] . "</button>";
            $page .= "</div>";
        } else {
            $page .= "<div class=\"card-body d-flex flex-wrap justify-content-between gap-2\">";
            $page .= "<a class=\"btn btn-sm btn-outline-secondary\" href=\"javascript:noShips();shortInfo();noResources();\">" . $lang['fl_unselectall'] . "</a>";
            $page .= "<a class=\"btn btn-sm btn-outline-secondary\" href=\"javascript:maxShips();shortInfo();\">" . $lang['fl_selectall'] . "</a>";
            $page .= "</div>";

            if ($MaxFlottes > $MaxFlyingFleets) {
                $page .= "<div class=\"card-footer text-end\">";
                $page .= "<button class=\"btn btn-primary\" type=\"submit\"><i class=\"bi bi-arrow-right\" aria-hidden=\"true\"></i> " . $lang['fl_continue'] . "</button>";
                $page .= "</div>";
            }
        }

        $page .= $ShipData;
        $page .= "<input type=\"hidden\" name=\"galaxy\" value=\"" . $galaxy . "\">";
        $page .= "<input type=\"hidden\" name=\"system\" value=\"" . $system . "\">";
        $page .= "<input type=\"hidden\" name=\"planet\" value=\"" . $planet . "\">";
        $page .= "<input type=\"hidden\" name=\"planet_type\" value=\"" . $planettype . "\">";
        $page .= "<input type=\"hidden\" name=\"mission\" value=\"" . $target_mission . "\">";
        $page .= "<input type=\"hidden\" name=\"maxepedition\" value=\"" . $EnvoiMaxExpedition . "\">";
        $page .= "<input type=\"hidden\" name=\"curepedition\" value=\"" . $ExpeditionEnCours . "\">";
        $page .= "<input type=\"hidden\" name=\"target_mission\" value=\"" . $target_mission . "\">";
        $page .= "</form>";
        $page .= "</div>";

        return $this->renderPage($page, $lang['fl_title']);
    }

    /**
     * La mise en orbite est-elle possible avec cette cible ? (mission 10)
     *
     * Elle se fait autour de la planete de depart uniquement, et une seule flotte
     * peut y etre en orbite a la fois : elle reste sur place jusqu'a son rappel.
     *
     * @param array<string, mixed> $origin
     * @param array<string, mixed> $target
     */
    /**
     * Missions **en plus** de celles du Coeur d'application, pour le sélecteur d'envoi.
     *
     * Le Coeur d'application ne connaît que ses propres missions : un module qui en apporte une
     * **dérive ce contrôleur** (même nom court, même couche : le Coeur d'application la détecte tout
     * seul) et complète ce
     * tableau, par exemple quand la flotte envoyée transporte ses vaisseaux.
     *
     * @param array<string, mixed> $lang
     * @param array<string, mixed> $post champs du formulaire d'envoi (`ship<id>`)
     * @return array<int, string>
     */
    protected function extraMissions(array $lang, array $post): array
    {
        return array();
    }

    /**
     * Libellés des missions d'un module, pour afficher un vol déjà parti.
     *
     * @param array<string, mixed> $lang
     * @return array<int, string>
     */
    protected function missionLabels(array $lang): array
    {
        return array();
    }

    /**
     * La cible est-elle la planète d'où part la flotte ?
     *
     * Stationner ou transporter vers l'endroit où les vaisseaux se trouvent déjà
     * n'a aucun sens : ces deux missions disparaissent alors du sélecteur — mais
     * elles restent proposées depuis ma planète vers ma lune, et l'inverse.
     *
     * @param array<string, mixed>|null $origin planète de départ (coordonnées + type)
     * @param array<string, mixed>|null $target planète visé
     */
    private function sameWorld(?array $origin, ?array $target): bool
    {
        if ($origin === null || $target === null) {
            return false;
        }

        return (int) ($origin['galaxy'] ?? 0) === (int) ($target['galaxy'] ?? -1)
            && (int) ($origin['system'] ?? 0) === (int) ($target['system'] ?? -1)
            && (int) ($origin['planet'] ?? 0) === (int) ($target['planet'] ?? -1)
            && (int) ($origin['planet_type'] ?? 1) === (int) ($target['planet_type'] ?? 1);
    }

    private function orbitAllowed(array $user, array $origin, array $target): bool
    {
        if (!FleetDispatchService::orbitTargetOk($origin, $target)) {
            return false;
        }

        return $this->fleets->countByMissionAt(
            (int) $user['id'],
            FleetDispatchService::MISSION_ORBIT,
            (int) $target['galaxy'],
            (int) $target['system'],
            (int) $target['planet'],
            (int) $target['planet_type']
        ) === 0;
    }

    public function backAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('fleet');
        $lang = $this->lang();
        $user = $this->user();

        $BoxTitle = $lang['fl_error'];
        $TxtColor = "red";
        $BoxMessage = $lang['fl_notback'];

        if (is_numeric($_POST['fleetid'] ?? null)) {
            $fleetid = intval($_POST['fleetid']);
            $FleetRow = $this->fleets->findById($fleetid);

            if ($FleetRow === false) {
                $BoxMessage = $lang['fl_notback'];
            } elseif ((int) $FleetRow['fleet_owner'] !== (int) $user['id']) {
                $BoxMessage = $lang['fl_onlyyours'];
            } elseif ($this->fleetService->recall($fleetid, (int) $user['id'])) {
                // La règle du rappel et le temps de retour vivent dans FleetService :
                // elles servent aussi à l'API et acceptent une flotte en stationnement chez
                // un allié (`fleet_mess = 2`) comme une flotte encore en vol.
                $BoxTitle = $lang['fl_sback'];
                $TxtColor = "lime";
                $BoxMessage = $lang['fl_isback'];
            }
        }

        return $this->renderMessage("<strong>" . $BoxMessage . "</strong>", $BoxTitle, "/game/fleet", 2, $TxtColor);
    }

    public function shortcutAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('fleet');
        $lang = $this->lang();

        $user = $this->user();
        $mode = $_GET['mode'] ?? null;
        $a = $_GET['a'] ?? null;

        $page = '';

        if (isset($_GET['mode'])) {
            if ($_POST) {
                if (($_POST["n"] ?? null) == "") {
                    $_POST["n"] = "Unbenannt";
                }

                $r = strip_tags($_POST['n'] ?? null) . "," . intval($_POST['g'] ?? 0) . "," . intval($_POST['s'] ?? 0) . "," . intval($_POST['p'] ?? 0) . "," . intval($_POST['t'] ?? 0) . "\r\n";
                $user['fleet_shortcut'] .= $r;
                $this->users->updateFleetShortcut((int) $user['id'], $user['fleet_shortcut']);
                return $this->renderMessage("Le raccourcis a &eacute;t&eacute; enregistr&eacute; !", "Enregistrment", "/game/fleet/shortcut");
            }
            $g = null;
            $s = null;
            $p = null;
            $t = null;
            $c = array();

            $page = "<form method=\"post\" class=\"card xnova-panel border-0 shadow-sm mb-3\">";
            $page .= "<div class=\"card-header fw-semibold\">Nom [Galaxie / Syst&egrave;me solaire / Plan&egrave;te]</div>";
            $page .= "<div class=\"card-body\"><div class=\"row g-2 align-items-end\">";
            $page .= "<div class=\"col-12 col-md-6\"><label class=\"form-label\" for=\"sc-n\">Nom</label><input class=\"form-control\" type=\"text\" id=\"sc-n\" name=\"n\" value=\"" . $g . "\" maxlength=\"32\"></div>";
            $page .= "<div class=\"col-4 col-md-2\"><label class=\"form-label\" for=\"sc-g\">Galaxie</label><input class=\"form-control\" type=\"text\" id=\"sc-g\" name=\"g\" value=\"" . $s . "\" maxlength=\"1\"></div>";
            $page .= "<div class=\"col-4 col-md-2\"><label class=\"form-label\" for=\"sc-s\">Syst&egrave;me</label><input class=\"form-control\" type=\"text\" id=\"sc-s\" name=\"s\" value=\"" . $p . "\" maxlength=\"3\"></div>";
            $page .= "<div class=\"col-4 col-md-2\"><label class=\"form-label\" for=\"sc-p\">Plan&egrave;te</label><input class=\"form-control\" type=\"text\" id=\"sc-p\" name=\"p\" value=\"" . $t . "\" maxlength=\"3\"></div>";
            $page .= "<div class=\"col-12 col-md-4\"><label class=\"form-label\" for=\"sc-t\">Type</label>";
            $page .= "<select class=\"form-select\" id=\"sc-t\" name=\"t\">";
            $page .= '<option value="1"' . ((($c[4] ?? null) == 1) ? " selected" : "") . ">Plan&egrave;te</option>";
            $page .= '<option value="2"' . ((($c[4] ?? null) == 2) ? " selected" : "") . ">D&eacute;bris</option>";
            $page .= '<option value="3"' . ((($c[4] ?? null) == 3) ? " selected" : "") . ">Lune</option>";
            $page .= "</select></div>";
            $page .= "</div></div>";
            $page .= "<div class=\"card-footer d-flex flex-wrap justify-content-between gap-2\">";
            $page .= "<a class=\"btn btn-outline-secondary\" href=\"/game/fleet/shortcut\"><i class=\"bi bi-arrow-left\" aria-hidden=\"true\"></i> Retour</a>";
            $page .= "<div class=\"d-flex flex-wrap gap-2\">";
            $page .= "<button class=\"btn btn-outline-secondary\" type=\"reset\">R&eacute;initialiser</button>";
            $page .= "<button class=\"btn btn-primary\" type=\"submit\"><i class=\"bi bi-check-lg\" aria-hidden=\"true\"></i> Enregistrer</button>";
            $page .= "</div></div></form>";
        } elseif (isset($_GET['a'])) {
            if ($_POST) {
                $scarray = explode("\r\n", $user['fleet_shortcut']);
                if (($_POST["delete"] ?? null)) {
                    unset($scarray[$a]);
                    $user['fleet_shortcut'] = implode("\r\n", $scarray);
                    $this->users->updateFleetShortcut((int) $user['id'], $user['fleet_shortcut']);
                    return $this->renderMessage("Shortcut wurde gel&ouml;scht", "Gel&ouml;scht", "/game/fleet/shortcut");
                }

                $r = explode(",", $scarray[$a]);
                $r[0] = strip_tags($_POST['n'] ?? null);
                $r[1] = intval($_POST['g'] ?? 0);
                $r[2] = intval($_POST['s'] ?? 0);
                $r[3] = intval($_POST['p'] ?? 0);
                $r[4] = intval($_POST['t'] ?? 0);
                $scarray[$a] = implode(",", $r);
                $user['fleet_shortcut'] = implode("\r\n", $scarray);
                $this->users->updateFleetShortcut((int) $user['id'], $user['fleet_shortcut']);
                return $this->renderMessage("Le raccourcis a &eacute;t&eacute; &eacute;dit&eacute; !.", "Editer", "/game/fleet/shortcut");
            }
            if ($user['fleet_shortcut']) {
                $scarray = explode("\r\n", $user['fleet_shortcut']);
                $c = explode(',', $scarray[$a]);

                $page = "<form method=\"post\" class=\"card xnova-panel border-0 shadow-sm mb-3\">";
                $page .= "<div class=\"card-header fw-semibold\">Editer : " . htmlspecialchars($c[0], ENT_QUOTES) . " [" . $c[1] . ":" . $c[2] . ":" . $c[3] . "]</div>";
                $page .= "<div class=\"card-body\"><div class=\"row g-2 align-items-end\">";
                $page .= "<input type=\"hidden\" name=\"a\" value=\"" . intval($a) . "\">";
                $page .= "<div class=\"col-12 col-md-6\"><label class=\"form-label\" for=\"sc-n\">Nom</label><input class=\"form-control\" type=\"text\" id=\"sc-n\" name=\"n\" value=\"" . htmlspecialchars($c[0], ENT_QUOTES) . "\" maxlength=\"32\"></div>";
                $page .= "<div class=\"col-4 col-md-2\"><label class=\"form-label\" for=\"sc-g\">Galaxie</label><input class=\"form-control\" type=\"text\" id=\"sc-g\" name=\"g\" value=\"" . $c[1] . "\" maxlength=\"1\"></div>";
                $page .= "<div class=\"col-4 col-md-2\"><label class=\"form-label\" for=\"sc-s\">Syst&egrave;me</label><input class=\"form-control\" type=\"text\" id=\"sc-s\" name=\"s\" value=\"" . $c[2] . "\" maxlength=\"3\"></div>";
                $page .= "<div class=\"col-4 col-md-2\"><label class=\"form-label\" for=\"sc-p\">Plan&egrave;te</label><input class=\"form-control\" type=\"text\" id=\"sc-p\" name=\"p\" value=\"" . $c[3] . "\" maxlength=\"3\"></div>";
                $page .= "<div class=\"col-12 col-md-4\"><label class=\"form-label\" for=\"sc-t\">Type</label>";
                $page .= "<select class=\"form-select\" id=\"sc-t\" name=\"t\">";
                $page .= '<option value="1"' . (($c[4] == 1) ? " selected" : "") . ">Plan&egrave;te</option>";
                $page .= '<option value="2"' . (($c[4] == 2) ? " selected" : "") . ">D&eacute;bris</option>";
                $page .= '<option value="3"' . (($c[4] == 3) ? " selected" : "") . ">Lune</option>";
                $page .= "</select></div>";
                $page .= "</div></div>";
                $page .= "<div class=\"card-footer d-flex flex-wrap justify-content-between gap-2\">";
                $page .= "<a class=\"btn btn-outline-secondary\" href=\"/game/fleet/shortcut\"><i class=\"bi bi-arrow-left\" aria-hidden=\"true\"></i> Retour</a>";
                $page .= "<div class=\"d-flex flex-wrap gap-2\">";
                $page .= "<button class=\"btn btn-outline-secondary\" type=\"reset\">Reset</button>";
                $page .= "<button class=\"btn btn-primary\" type=\"submit\"><i class=\"bi bi-check-lg\" aria-hidden=\"true\"></i> Enregistrer</button>";
                $page .= "<button class=\"btn btn-outline-danger\" type=\"submit\" name=\"delete\" value=\"1\"><i class=\"bi bi-trash\" aria-hidden=\"true\"></i> Supprimer</button>";
                $page .= "</div></div></form>";
            } else {
                return $this->renderMessage("Le raccourci a &eacute;t&eacute; enregistr&eacute; !", "Enregistrer", "/game/fleet/shortcut");
            }
        } else {
            $page = "<div class=\"card xnova-panel border-0 shadow-sm mb-3\">";
            $page .= "<div class=\"card-header d-flex flex-wrap justify-content-between align-items-center gap-2\">";
            $page .= "<span class=\"fw-semibold\">Raccourcis</span>";
            $page .= "<a class=\"btn btn-sm btn-primary\" href=\"?mode=add\"><i class=\"bi bi-plus-lg\" aria-hidden=\"true\"></i> Ajout</a>";
            $page .= "</div>";

            if ($user['fleet_shortcut']) {
                $scarray = explode("\r\n", $user['fleet_shortcut']);
                $page .= "<div class=\"list-group list-group-flush\">";
                foreach ($scarray as $idx => $b) {
                    if ($b == "") {
                        continue;
                    }

                    $c = explode(',', $b);
                    $type = ($c[4] == 2) ? " (D)" : (($c[4] == 3) ? " (L)" : "");

                    $page .= "<a class=\"list-group-item list-group-item-action\" href=\"?a=" . $idx . "\">";
                    $page .= "<i class=\"bi bi-bookmark\" aria-hidden=\"true\"></i> ";
                    $page .= htmlspecialchars($c[0], ENT_QUOTES) . " " . $c[1] . ":" . $c[2] . ":" . $c[3] . $type;
                    $page .= "</a>";
                }
                $page .= "</div>";
            } else {
                $page .= "<div class=\"card-body text-body-secondary\">Pas de raccourcis</div>";
            }

            $page .= "<div class=\"card-footer text-center\">";
            $page .= "<a href=\"/game/fleet\"><i class=\"bi bi-arrow-left\" aria-hidden=\"true\"></i> Retour</a>";
            $page .= "</div></div>";
        }

        return $this->renderPage($page, $lang['fl_shortcut']);
    }

    public function floten1Action(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('fleet');

        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();
        $reslist = $this->resList();
        $resource = $this->resource();
        $pricelist = $GLOBALS['pricelist'] ?? [];

        $speed = array(
            10 => 100,
            9 => 90,
            8 => 80,
            7 => 70,
            6 => 60,
            5 => 50,
            4 => 40,
            3 => 30,
            2 => 20,
            1 => 10,
        );

        $g = $_POST['galaxy'] ?? null;
        $s = $_POST['system'] ?? null;
        $p = $_POST['planet'] ?? null;
        $t = $_POST['planet_type'] ?? null;

        if (!$g) {
            $g = $planetrow['galaxy'];
        }
        if (!$s) {
            $s = $planetrow['system'];
        }
        if (!$p) {
            $p = $planetrow['planet'];
        }
        if (!$t) {
            $t = $planetrow['planet_type'];
        }

        // Retour depuis l'etape suivante : la mission deja choisie est reprise
        // (elle est proposee a nouveau a l'etape des ressources).
        $target_mission = $_POST['target_mission'] ?? $_POST['mission'] ?? null;

        $FleetHiddenBlock = "";
        $page = '';
        $fleet = array('fleetarray' => array(), 'fleetlist' => '', 'amount' => 0);
        $speedalls = array();
        foreach ($reslist['fleet'] as $n => $i) {
            if ($i > 200 && $i < 300 && (($_POST["ship$i"] ?? null) > "0")) {
                if (($_POST["ship$i"] ?? null) > $planetrow[$resource[$i]]) {
                    $page .= $lang['fl_noenought'];
                    $speedalls[$i] = GetFleetMaxSpeed("", $i, $user);
                } else {
                    $fleet['fleetarray'][$i] = $_POST["ship$i"];
                    $fleet['fleetlist'] .= $i . "," . $_POST["ship$i"] . ";";
                    $fleet['amount'] += $_POST["ship$i"];
                    $FleetHiddenBlock .= "<input type=\"hidden\" name=\"consumption" . $i . "\" value=\"" . GetShipConsumption($i, $user) . "\" />";
                    $FleetHiddenBlock .= "<input type=\"hidden\" name=\"speed" . $i . "\"       value=\"" . GetFleetMaxSpeed("", $i, $user) . "\" />";
                    $FleetHiddenBlock .= "<input type=\"hidden\" name=\"capacity" . $i . "\"    value=\"" . $pricelist[$i]['capacity'] . "\" />";
                    $FleetHiddenBlock .= "<input type=\"hidden\" name=\"ship" . $i . "\"        value=\"" . $_POST["ship$i"] . "\" />";
                    $speedalls[$i] = GetFleetMaxSpeed("", $i, $user);
                }
            }
        }

        if (!$fleet['fleetlist']) {
            return $this->renderMessage($lang['fl_unselectall'], $lang['fl_error'], "fleet." . PHPEXT, 1);
        }

        $speedallsmin = min($speedalls);

        $page .= "<script src=\"/scripts/fleet.js\"></script>";
        $page .= "<script type=\"text/javascript\">\n";
        $page .= "function getStorageFaktor() {\n";
        $page .= "	return 1\n";
        $page .= "}\n";
        $page .= "</script>\n";
        $page .= "<form action=\"/game/fleet/floten2\" method=\"post\">";
        $page .= $FleetHiddenBlock;
        $page .= "<input type=\"hidden\" name=\"speedallsmin\"   value=\"" . $speedallsmin . "\" />";
        $page .= "<input type=\"hidden\" name=\"usedfleet\"      value=\"" . str_rot13(base64_encode(serialize($fleet['fleetarray']))) . "\" />";
        $page .= "<input type=\"hidden\" name=\"thisgalaxy\"     value=\"" . $planetrow['galaxy'] . "\" />";
        $page .= "<input type=\"hidden\" name=\"thissystem\"     value=\"" . $planetrow['system'] . "\" />";
        $page .= "<input type=\"hidden\" name=\"thisplanet\"     value=\"" . $planetrow['planet'] . "\" />";
        $page .= "<input type=\"hidden\" name=\"galaxyend\"      value=\"" . intval($_POST['galaxy'] ?? 0) . "\" />";
        $page .= "<input type=\"hidden\" name=\"systemend\"      value=\"" . intval($_POST['system'] ?? 0) . "\" />";
        $page .= "<input type=\"hidden\" name=\"planetend\"      value=\"" . intval($_POST['planet'] ?? 0) . "\" />";
        $page .= "<input type=\"hidden\" name=\"speedfactor\"    value=\"" . GetGameSpeedFactor() . "\" />";
        $page .= "<input type=\"hidden\" name=\"thisplanettype\" value=\"" . $planetrow['planet_type'] . "\" />";
        $page .= "<input type=\"hidden\" name=\"thisresource1\"  value=\"" . floor($planetrow['metal']) . "\" />";
        $page .= "<input type=\"hidden\" name=\"thisresource2\"  value=\"" . floor($planetrow['crystal']) . "\" />";
        $page .= "<input type=\"hidden\" name=\"thisresource3\"  value=\"" . floor($planetrow['deuterium']) . "\" />";

        $page .= "<div class=\"card xnova-panel border-0 shadow-sm mb-3 xnova-fleet-form\">";
        $page .= "<div class=\"card-header fw-semibold\">" . $lang['fl_floten1_ttl'] . "</div>";
        $page .= "<div class=\"card-body\">";

        // ---- Cible et vitesse ----
        $page .= "<div class=\"row g-3\">";
        $page .= "<div class=\"col-12 col-md-6\">";
        $page .= "<label class=\"form-label\" for=\"fl-galaxy\">" . $lang['fl_dest'] . "</label>";
        $page .= "<div class=\"d-flex flex-wrap gap-2\">";
        $page .= "<input class=\"form-control\" type=\"text\" id=\"fl-galaxy\" name=\"galaxy\" maxlength=\"2\" onChange=\"shortInfo()\" onKeyUp=\"shortInfo()\" value=\"" . $g . "\" aria-label=\"Galaxie\">";
        $page .= "<input class=\"form-control\" type=\"text\" name=\"system\" maxlength=\"3\" onChange=\"shortInfo()\" onKeyUp=\"shortInfo()\" value=\"" . $s . "\" aria-label=\"Syst&egrave;me\">";
        $page .= "<input class=\"form-control\" type=\"text\" name=\"planet\" maxlength=\"2\" onChange=\"shortInfo()\" onKeyUp=\"shortInfo()\" value=\"" . $p . "\" aria-label=\"Plan&egrave;te\">";
        $page .= "<select class=\"form-select\" name=\"planettype\" aria-label=\"Type de cible\" onChange=\"shortInfo()\" onKeyUp=\"shortInfo()\">";
        $page .= "<option value=\"1\"" . (($t == 1) ? " selected" : "") . ">" . $lang['fl_planet'] . "</option>";
        $page .= "<option value=\"2\"" . (($t == 2) ? " selected" : "") . ">" . $lang['fl_ruins'] . "</option>";
        $page .= "<option value=\"3\"" . (($t == 3) ? " selected" : "") . ">" . $lang['fl_moon'] . "</option>";
        $page .= "</select>";
        $page .= "</div>";
        $page .= "</div>";

        $page .= "<div class=\"col-12 col-md-6\">";
        $page .= "<label class=\"form-label\" for=\"fl-speed\">" . $lang['fl_speed'] . "</label>";
        $page .= "<select class=\"form-select\" id=\"fl-speed\" name=\"speed\" onChange=\"shortInfo()\" onKeyUp=\"shortInfo()\">";
        foreach ($speed as $a => $b) {
            // Retour depuis l'etape suivante : la vitesse choisie est conservee.
            $page .= "<option value=\"" . $a . "\"" . (((int) ($_POST['speed'] ?? 0) === $a) ? " selected" : "") . ">" . $b . " %</option>";
        }
        $page .= "</select>";
        $page .= "</div>";
        $page .= "</div>";

        $page .= "<div class=\"row g-2 mt-3\">";
        $page .= "<div class=\"col-12 col-md-6 xnova-fleet-info\">";
        $page .= "<div class=\"xnova-fleet-stat\"><span class=\"label\">" . $lang['fl_dist'] . "</span><span id=\"distance\">-</span></div>";
        $page .= "<div class=\"xnova-fleet-stat\"><span class=\"label\">" . $lang['fl_fltime'] . "</span><span id=\"duration\">-</span></div>";
        $page .= "<div class=\"xnova-fleet-stat\"><span class=\"label\">" . $lang['fl_deute_need'] . "</span><span id=\"consumption\">-</span></div>";
        $page .= "</div>";
        $page .= "<div class=\"col-12 col-md-6 xnova-fleet-info\">";
        $page .= "<div class=\"xnova-fleet-stat\"><span class=\"label\">" . $lang['fl_speed_max'] . "</span><span id=\"maxspeed\">-</span></div>";
        $page .= "<div class=\"xnova-fleet-stat\"><span class=\"label\">" . $lang['fl_max_load'] . "</span><span id=\"storage\">-</span></div>";
        $page .= "</div>";
        $page .= "</div>";

        $page .= "<h2 class=\"h6 text-body-secondary text-uppercase mt-4 mb-2\">" . $lang['fl_shortcut'] . " <a class=\"small text-decoration-none\" href=\"/game/fleet/shortcut\">" . $lang['fl_shortlnk'] . "</a></h2>";
        if ($user['fleet_shortcut']) {
            $page .= "<div class=\"d-flex flex-wrap gap-2\">";
            foreach (explode("\r\n", $user['fleet_shortcut']) as $b) {
                if ($b == "") {
                    continue;
                }

                $c = explode(',', $b);
                $type = '';
                if ($c[4] == 1) {
                    $type = $lang['fl_shrtcup1'];
                } elseif ($c[4] == 2) {
                    $type = $lang['fl_shrtcup2'];
                } elseif ($c[4] == 3) {
                    $type = $lang['fl_shrtcup3'];
                }

                $page .= "<a class=\"btn btn-sm btn-outline-secondary\" href=\"javascript:setTarget(" . $c[1] . "," . $c[2] . "," . $c[3] . "," . $c[4] . "); shortInfo();\">";
                $page .= htmlspecialchars($c[0], ENT_QUOTES) . " " . $c[1] . ":" . $c[2] . ":" . $c[3] . " " . $type;
                $page .= "</a>";
            }
            $page .= "</div>";
        } else {
            $page .= "<p class=\"text-body-secondary mb-0\">" . $lang['fl_noshortc'] . "</p>";
        }

        $page .= "<h2 class=\"h6 text-body-secondary text-uppercase mt-4 mb-2\">" . $lang['fl_myplanets'] . "</h2>";

        $colonies = SortUserPlanets($user);
        $currentplanet = $this->planets->findCurrentById((int) $user['current_planet']);

        if (count($colonies) > 1) {
            $page .= "<div class=\"d-flex flex-wrap gap-2\">";
            foreach ($colonies as $row) {
                if (
                    $currentplanet['galaxy'] == $row['galaxy'] &&
                    $currentplanet['system'] == $row['system'] &&
                    $currentplanet['planet'] == $row['planet'] &&
                    $currentplanet['planet_type'] == $row['planet_type']
                ) {
                    continue;
                }

                if ($row['planet_type'] == 3) {
                    $row['name'] .= " " . $lang['fl_shrtcup3'];
                }

                $page .= "<a class=\"btn btn-sm btn-outline-secondary\" href=\"javascript:setTarget(" . $row['galaxy'] . "," . $row['system'] . "," . $row['planet'] . "," . $row['planet_type'] . "); shortInfo();\">";
                $page .= htmlspecialchars((string) $row['name'], ENT_QUOTES) . " " . $row['galaxy'] . ":" . $row['system'] . ":" . $row['planet'];
                $page .= "</a>";
            }
            $page .= "</div>";
        } else {
            $page .= "<p class=\"text-body-secondary mb-0\">" . $lang['fl_nocolonies'] . "</p>";
        }

        $page .= "<h2 class=\"h6 text-body-secondary text-uppercase mt-4 mb-2\">" . $lang['fl_grattack'] . "</h2>";
        $page .= "<p class=\"text-body-secondary mb-0\">&ndash;</p>";

        $page .= "</div>"; // card-body
        $page .= "<div class=\"card-footer d-flex flex-wrap justify-content-between gap-2\">";
        // Retour : meme formulaire, cible differente -> les vaisseaux, la cible
        // et la vitesse repartent vers l'etape precedente.
        $page .= "<button class=\"btn btn-outline-secondary\" type=\"submit\" formaction=\"/game/fleet\" formmethod=\"post\"><i class=\"bi bi-arrow-left\" aria-hidden=\"true\"></i> " . $lang['fl_back_to_ttl'] . "</button>";
        $page .= "<button class=\"btn btn-primary\" type=\"submit\"><i class=\"bi bi-arrow-right\" aria-hidden=\"true\"></i> " . $lang['fl_continue'] . "</button>";
        $page .= "</div>";
        $page .= "</div>"; // card

        $page .= "<input type=\"hidden\" name=\"maxepedition\" value=\"" . ($_POST['maxepedition'] ?? null) . "\">";
        $page .= "<input type=\"hidden\" name=\"curepedition\" value=\"" . ($_POST['curepedition'] ?? null) . "\">";
        $page .= "<input type=\"hidden\" name=\"target_mission\" value=\"" . ($target_mission ?? null) . "\">";
        $page .= "</form>";
        $page .= "<script>shortInfo();</script>";

        return $this->renderPage($page, $lang['fl_title']);
    }

    public function floten2Action(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('fleet');

        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();
        $pricelist = $GLOBALS['pricelist'] ?? [];

        $galaxy = intval($_POST['galaxy'] ?? 0);
        $system = intval($_POST['system'] ?? 0);
        $planet = intval($_POST['planet'] ?? 0);
        $planettype = intval($_POST['planettype'] ?? 0);

        // Mise en orbite : autour de la planete de depart uniquement, une seule
        // flotte a la fois (elle y reste jusqu'a son rappel).
        $orbitOrigin = array(
            'galaxy' => intval($_POST['thisgalaxy'] ?? 0),
            'system' => intval($_POST['thissystem'] ?? 0),
            'planet' => intval($_POST['thisplanet'] ?? 0),
            'planet_type' => intval($_POST['thisplanettype'] ?? 0),
        );
        $orbitTarget = array(
            'galaxy' => $galaxy,
            'system' => $system,
            'planet' => $planet,
            'planet_type' => $planettype,
        );

        $YourPlanet = false;
        $UsedPlanet = false;
        $rows = $this->planets->findAll();
        foreach ($rows as $row) {
            if (
                $galaxy == $row['galaxy'] &&
                $system == $row['system'] &&
                $planet == $row['planet'] &&
                $planettype == $row['planet_type']
            ) {
                if ($row['id_owner'] == $user['id']) {
                    $YourPlanet = true;
                    $UsedPlanet = true;
                } else {
                    $UsedPlanet = true;
                }
                break;
            }
        }

        // Stationner et transporter ne visent pas la planète d'où part la flotte,
        // mais une planète vers sa lune (et l'inverse) reste possible. L'origine
        // vient du formulaire (`this*`) ; `$CurrentPlanet` n'existe pas dans tous
        // les chemins d'envoi.
        $SameWorld = $this->sameWorld(
            array(
                'galaxy' => $_POST['thisgalaxy'] ?? $CurrentPlanet['galaxy'] ?? null,
                'system' => $_POST['thissystem'] ?? $CurrentPlanet['system'] ?? null,
                'planet' => $_POST['thisplanet'] ?? $CurrentPlanet['planet'] ?? null,
                'planet_type' => $_POST['thisplanettype'] ?? $CurrentPlanet['planet_type'] ?? 1,
            ),
            array(
                'galaxy' => $galaxy,
                'system' => $system,
                'planet' => $planet,
                'planet_type' => $planettype,
            )
        );

        $missiontype = array();
        if (($_POST['planettype'] ?? null) == "2") {
            if (($_POST['ship209'] ?? null) >= 1) {
                $missiontype = array(8 => $lang['type_mission'][8]);
            } else {
                $missiontype = array();
            }
        } elseif (($_POST['planettype'] ?? null) == "1" || ($_POST['planettype'] ?? null) == "3") {
            if (($_POST['ship208'] ?? null) >= 1 && !$UsedPlanet) {
                $missiontype = array(7 => $lang['type_mission'][7]);
            } elseif (($_POST['ship210'] ?? null) >= 1 && !$YourPlanet) {
                $missiontype = array(6 => $lang['type_mission'][6]);
            }

            if (
                ($_POST['ship202'] ?? null) >= 1 ||
                ($_POST['ship203'] ?? null) >= 1 ||
                ($_POST['ship204'] ?? null) >= 1 ||
                ($_POST['ship205'] ?? null) >= 1 ||
                ($_POST['ship206'] ?? null) >= 1 ||
                ($_POST['ship207'] ?? null) >= 1 ||
                ($_POST['ship210'] ?? null) >= 1 ||
                ($_POST['ship211'] ?? null) >= 1 ||
                ($_POST['ship213'] ?? null) >= 1 ||
                ($_POST['ship214'] ?? null) >= 1 ||
                ($_POST['ship215'] ?? null) >= 1
            ) {
                if (!$YourPlanet) {
                    $missiontype[1] = $lang['type_mission'][1];
                }
                if (!$SameWorld) {
                    $missiontype[3] = $lang['type_mission'][3];
                }
                $missiontype[5] = $lang['type_mission'][5];
            }
        } elseif (($_POST['ship209'] ?? null) >= 1 || ($_POST['ship208'] ?? null)) {
            if (!$SameWorld) {
                $missiontype[3] = $lang['type_mission'][3];
            }
        }
        if ($YourPlanet && !$SameWorld) {
            $missiontype[4] = $lang['type_mission'][4];
        }

        if ($this->orbitAllowed($user, $orbitOrigin, $orbitTarget)) {
            $missiontype[10] = $lang['type_mission'][10];
        }

        if (
            ($_POST['planettype'] ?? null) == 3 &&
            (($_POST['ship214'] ?? null) ||
                ($_POST['ship213'] ?? null)) &&
            !$YourPlanet &&
            $UsedPlanet
        ) {
            $missiontype[2] = $lang['type_mission'][2];
        }
        if (
            ($_POST['planettype'] ?? null) == 3 &&
            ($_POST['ship214'] ?? null) >= 1 &&
            !$YourPlanet &&
            $UsedPlanet
        ) {
            $missiontype[9] = $lang['type_mission'][9];
        }

        // Missions apportées par un module (voir extraMissions()) : la page les
        // propose quand la flotte envoyée les permet.
        $missiontype += $this->extraMissions($lang, $_POST);

        $fleetarray = unserialize(base64_decode(str_rot13($_POST["usedfleet"] ?? '')));

        // usedfleet peut etre absent ou altere : unserialize() renvoie alors
        // false et min() sur un tableau vide leve une erreur fatale en PHP 8.
        if (!is_array($fleetarray) || $fleetarray === array()) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_no_fleetarray'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        $mission = $_POST['target_mission'] ?? null;
        $SpeedFactor = $_POST['speedfactor'] ?? null;
        $AllFleetSpeed = GetFleetMaxSpeed($fleetarray, 0, $user);
        $GenFleetSpeed = $_POST['speed'] ?? null;
        $MaxFleetSpeed = min($AllFleetSpeed);

        $distance = GetTargetDistance($_POST['thisgalaxy'] ?? null, $_POST['galaxy'] ?? null, $_POST['thissystem'] ?? null, $_POST['system'] ?? null, $_POST['thisplanet'] ?? null, $_POST['planet'] ?? null);
        $duration = GetMissionDuration($GenFleetSpeed, $MaxFleetSpeed, $distance, $SpeedFactor);
        $consumption = GetFleetConsumption($fleetarray, $SpeedFactor, $duration, $distance, $MaxFleetSpeed, $user);

        $MissionSelector = "";
        if (count($missiontype) > 0) {
            if ($planet == 16) {
                $MissionSelector .= "<div class=\"form-check\">";
                $MissionSelector .= "<input class=\"form-check-input\" id=\"inpuT_expe\" type=\"radio\" name=\"mission\" value=\"15\" checked>";
                $MissionSelector .= "<label class=\"form-check-label\" for=\"inpuT_expe\">" . $lang['type_mission'][15] . "</label>";
                $MissionSelector .= "</div>";
                $MissionSelector .= "<p class=\"text-danger small mb-0\">" . $lang['fl_expe_warning'] . "</p>";
            } else {
                $i = 0;
                foreach ($missiontype as $a => $b) {
                    $MissionSelector .= "<div class=\"form-check\">";
                    $MissionSelector .= "<input class=\"form-check-input\" id=\"inpuT_" . $i . "\" type=\"radio\" name=\"mission\" value=\"" . $a . "\"" . ($mission == $a ? " checked" : "") . ">";
                    $MissionSelector .= "<label class=\"form-check-label\" for=\"inpuT_" . $i . "\">" . $b . "</label>";
                    $MissionSelector .= "</div>";
                    $i++;
                }
            }
        } else {
            $MissionSelector .= "<p class=\"text-danger mb-0\">" . $lang['fl_bad_mission'] . "</p>";
        }

        if (($_POST['thisplanettype'] ?? null) == 1) {
            $TableTitle = "" . ($_POST['thisgalaxy'] ?? null) . ":" . ($_POST['thissystem'] ?? null) . ":" . ($_POST['thisplanet'] ?? null) . " - " . $lang['fl_planet'] . "";
        } elseif (($_POST['thisplanettype'] ?? null) == 3) {
            $TableTitle = "" . ($_POST['thisgalaxy'] ?? null) . ":" . ($_POST['thissystem'] ?? null) . ":" . ($_POST['thisplanet'] ?? null) . " - " . $lang['fl_moon'] . "";
        }

        $page = "<script src=\"/scripts/fleet.js\">\n</script>";
        $page .= "<script type=\"text/javascript\">\n";
        $page .= "function getStorageFaktor() {\n";
        $page .= "    return 1;\n";
        $page .= "}\n";
        $page .= "</script>\n";
        // Amelioration progressive : l'envoi part en JSON quand JavaScript est
        // disponible, sinon le POST classique vers floten3 reste utilise.
        $page .= "<form action=\"/game/fleet/floten3\" method=\"post\" class=\"xnova-fleet-form\""
            . " data-ajax=\"/game/api/fleet/send\" data-ajax-fleet=\"1\" data-ajax-redirect=\"/game/fleet\">\n";
        $page .= "<input type=\"hidden\" name=\"thisresource1\"  value=\"" . floor($planetrow["metal"]) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"thisresource2\"  value=\"" . floor($planetrow["crystal"]) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"thisresource3\"  value=\"" . floor($planetrow["deuterium"]) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"consumption\"    value=\"" . $consumption . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"dist\"           value=\"" . $distance . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"speedfactor\"    value=\"" . ($_POST['speedfactor'] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"thisgalaxy\"     value=\"" . ($_POST["thisgalaxy"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"thissystem\"     value=\"" . ($_POST["thissystem"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"thisplanet\"     value=\"" . ($_POST["thisplanet"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"galaxy\"         value=\"" . ($_POST["galaxy"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"system\"         value=\"" . ($_POST["system"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"planet\"         value=\"" . ($_POST["planet"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"thisplanettype\" value=\"" . ($_POST["thisplanettype"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"planettype\"     value=\"" . ($_POST["planettype"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"speedallsmin\"   value=\"" . ($_POST["speedallsmin"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"speed\"          value=\"" . ($_POST['speed'] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"speedfactor\"    value=\"" . ($_POST["speedfactor"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"usedfleet\"      value=\"" . ($_POST["usedfleet"] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"maxepedition\"   value=\"" . ($_POST['maxepedition'] ?? null) . "\" />\n";
        $page .= "<input type=\"hidden\" name=\"curepedition\"   value=\"" . ($_POST['curepedition'] ?? null) . "\" />\n";
        foreach ($fleetarray as $Ship => $Count) {
            $page .= "<input type=\"hidden\" name=\"ship" . $Ship . "\"        value=\"" . $Count . "\" />\n";
            $page .= "<input type=\"hidden\" name=\"capacity" . $Ship . "\"    value=\"" . $pricelist[$Ship]['capacity'] . "\" />\n";
            $page .= "<input type=\"hidden\" name=\"consumption" . $Ship . "\" value=\"" . GetShipConsumption($Ship, $user) . "\" />\n";
            $page .= "<input type=\"hidden\" name=\"speed" . $Ship . "\"       value=\"" . GetFleetMaxSpeed("", $Ship, $user) . "\" />\n";
        }
        $page .= "<div class=\"card xnova-panel border-0 shadow-sm mb-3\">\n";
        $page .= "<div class=\"card-header fw-semibold\">" . $TableTitle . "</div>\n";
        $page .= "<div class=\"card-body\">\n";
        $page .= "<div class=\"row g-4\">\n";

        // ---- Mission ----
        $page .= "<div class=\"col-12 col-md-6\">\n";
        $page .= "<h2 class=\"h6 text-body-secondary text-uppercase mb-2\">" . $lang['fl_mission'] . "</h2>\n";
        $page .= $MissionSelector;
        $page .= "</div>\n";

        // ---- Ressources ----
        $page .= "<div class=\"col-12 col-md-6\">\n";
        $page .= "<h2 class=\"h6 text-body-secondary text-uppercase mb-2\">" . $lang['fl_ressources'] . "</h2>\n";
        $page .= "<div class=\"row g-2\">\n";
        foreach (array(1 => $lang['Metal'], 2 => $lang['Crystal'], 3 => $lang['Deuterium']) as $resourceId => $resourceLabel) {
            $page .= "<div class=\"col-12\">";
            $page .= "<label class=\"form-label\" for=\"resource" . $resourceId . "\">" . $resourceLabel . "</label>";
            $page .= "<div class=\"input-group\">";
            $page .= "<input class=\"form-control\" type=\"text\" id=\"resource" . $resourceId . "\" name=\"resource" . $resourceId . "\" onchange=\"calculateTransportCapacity();\" aria-label=\"" . htmlspecialchars(html_entity_decode($resourceLabel), ENT_QUOTES) . "\">";
            $page .= "<button class=\"btn btn-outline-secondary\" type=\"button\" onclick=\"maxResource('" . $resourceId . "');\">" . $lang['fl_selmax'] . "</button>";
            $page .= "</div></div>\n";
        }
        $page .= "</div>\n";

        $page .= "<div class=\"xnova-fleet-info mt-3\">\n";
        $page .= "<div class=\"xnova-fleet-stat\"><span class=\"label\">" . $lang['fl_space_left'] . "</span><span id=\"remainingresources\">-</span></div>\n";
        $page .= "</div>\n";
        $page .= "<a class=\"btn btn-sm btn-outline-secondary mt-2\" href=\"javascript:maxResources()\">" . $lang['fl_allressources'] . "</a>\n";
        $page .= "</div>\n";

        $page .= "</div>\n";

        // ---- Temps de stationnement / expedition ----
        if ($planet == 16) {
            $page .= "<div class=\"mt-4\">\n";
            $page .= "<label class=\"form-label\" for=\"expeditiontime\">" . $lang['fl_expe_staytime'] . "</label>\n";
            $page .= "<div class=\"d-flex align-items-center gap-2\">\n";
            $page .= "<select class=\"form-select xnova-fleet-select\" id=\"expeditiontime\" name=\"expeditiontime\">";
            $page .= "<option value=\"1\">1</option><option value=\"2\">2</option>";
            $page .= "</select>";
            $page .= "<span>" . $lang['fl_expe_hours'] . "</span>\n";
            $page .= "</div></div>\n";
        } elseif (($missiontype[5] ?? null) != '') {
            $page .= "<div class=\"mt-4\">\n";
            $page .= "<label class=\"form-label\" for=\"holdingtime\">" . $lang['fl_expe_staytime'] . "</label>\n";
            $page .= "<div class=\"d-flex align-items-center gap-2\">\n";
            $page .= "<select class=\"form-select xnova-fleet-select\" id=\"holdingtime\" name=\"holdingtime\">";
            foreach (array(0, 1, 2, 4, 8, 16, 32) as $holdHours) {
                $page .= "<option value=\"" . $holdHours . "\">" . $holdHours . "</option>";
            }
            $page .= "</select>";
            $page .= "<span>" . $lang['fl_expe_hours'] . "</span>\n";
            $page .= "</div></div>\n";
        }

        $page .= "</div>\n"; // card-body
        $page .= "<div class=\"card-footer d-flex flex-wrap justify-content-between gap-2\">\n";
        // Retour : le formulaire porte deja toutes les donnees (vaisseaux, cible,
        // vitesse, mission) -> elles repartent vers l'etape precedente.
        $page .= "<button class=\"btn btn-outline-secondary\" type=\"submit\" formaction=\"/game/fleet/floten1\" formmethod=\"post\" data-ajax-skip=\"1\"><i class=\"bi bi-arrow-left\" aria-hidden=\"true\"></i> " . $lang['fl_back_to_ttl'] . "</button>\n";
        $page .= "<button class=\"btn btn-primary\" type=\"submit\" accesskey=\"z\"><i class=\"bi bi-arrow-right\" aria-hidden=\"true\"></i> " . $lang['fl_continue'] . "</button>\n";
        $page .= "</div>\n";
        $page .= "</div>\n"; // card
        $page .= "</form>\n";

        return $this->renderPage($page, $lang['fl_title']);
    }

    public function floten3Action(Request $request): Response
    {
        $this->bootLegacy();

        $lang = $this->lang();
        $user = $this->user();
        $resource = $this->resource();
        $gameConfig = $this->gameConfig();
        $pricelist = $GLOBALS['pricelist'] ?? [];
        $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];

        $this->includeLang('fleet');
        $lang = $this->lang();

        $CurrentPlanet = $this->planets->findCurrentById((int) $user['current_planet']);
        $TargetPlanet = $this->planets->findByCoords(($_POST['galaxy'] ?? null), ($_POST['system'] ?? null), ($_POST['planet'] ?? null), ($_POST['planettype'] ?? null));
        $MyDBRec = $this->users->findFullById((int) $user['id']);

        $protection = $gameConfig['noobprotection'];
        $protectiontime = $gameConfig['noobprotectiontime'];
        $protectionmulti = $gameConfig['noobprotectionmulti'];
        if ($protectiontime < 1) {
            $protectiontime = 9999999999999999;
        }

        $fleetarray = unserialize(base64_decode(str_rot13($_POST["usedfleet"] ?? '')), ['allowed_classes' => false]);

        if (!is_array($fleetarray)) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_fleet_err'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        foreach ($fleetarray as $Ship => $Count) {
            if ($Count > $CurrentPlanet[$resource[$Ship]]) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_fleet_err'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
        }

        $galaxy = intval($_POST['galaxy'] ?? 0);
        $system = intval($_POST['system'] ?? 0);
        $planet = intval($_POST['planet'] ?? 0);
        $planettype = intval($_POST['planettype'] ?? 0);
        $fleetmission = $_POST['mission'] ?? null;

        if ($planettype != 1 && $planettype != 2 && $planettype != 3) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_fleet_err_pl'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        if ($fleetmission == 8) {
            $YourPlanet = false;
            $UsedPlanet = false;
            $selectRows = $this->planets->findListByCoordsNoType($galaxy, $system, $planet);
        } else {
            $YourPlanet = false;
            $UsedPlanet = false;
            $selectRows = $this->planets->findListByCoordsType($galaxy, $system, $planet, $planettype);
        }

        if (
            $CurrentPlanet['galaxy'] == $galaxy &&
            $CurrentPlanet['system'] == $system &&
            $CurrentPlanet['planet'] == $planet &&
            $CurrentPlanet['planet_type'] == $planettype
        ) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_ownpl_err'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        $selectCount = count($selectRows);
        $select = $selectCount > 0 ? $selectRows[0] : false;

        if (($_POST['mission'] ?? null) != 15) {
            if ($selectCount < 1 && $fleetmission != 7) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_unknow_target'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            } elseif ($fleetmission == 9 && $selectCount < 1) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_used_target'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
        } else {
            $EnvoiMaxExpedition = $_POST['maxepedition'] ?? null;
            $Expedition = $_POST['curepedition'] ?? null;

            if ($EnvoiMaxExpedition == 0) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_expe_notech'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            } elseif ($Expedition >= $EnvoiMaxExpedition) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_expe_max'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
        }


        // Mise en orbite : planete de depart uniquement, une seule flotte a la fois.
        if (($_POST['mission'] ?? null) == FleetDispatchService::MISSION_ORBIT) {
            $orbitOrigin = array(
                'galaxy' => intval($_POST['thisgalaxy'] ?? 0),
                'system' => intval($_POST['thissystem'] ?? 0),
                'planet' => intval($_POST['thisplanet'] ?? 0),
                'planet_type' => intval($_POST['thisplanettype'] ?? 0),
            );
            $orbitTarget = array(
                'galaxy' => intval($_POST['galaxy'] ?? 0),
                'system' => intval($_POST['system'] ?? 0),
                'planet' => intval($_POST['planet'] ?? 0),
                'planet_type' => intval($_POST['planettype'] ?? 0),
            );

            if (!$this->orbitAllowed($user, $orbitOrigin, $orbitTarget)) {
                $message = FleetDispatchService::orbitTargetOk($orbitOrigin, $orbitTarget)
                    ? $lang['fl_orbit_busy']
                    : $lang['fl_orbit_own'];

                return $this->renderMessage("<font color=\"red\"><b>" . $message . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
        }

        if (($select['id_owner'] ?? null) == $user['id']) {
            $YourPlanet = true;
            $UsedPlanet = true;
        } elseif (!empty($select['id_owner'])) {
            $YourPlanet = false;
            $UsedPlanet = true;
        } else {
            $YourPlanet = false;
            $UsedPlanet = false;
        }

        // Même règle que sur le formulaire d'envoi : on ne stationne pas, et on ne
        // transporte pas, vers la planète d'où part la flotte (origine postée dans
        // `this*`, `$CurrentPlanet` n'existe pas dans ce chemin).
        $SameWorld = $this->sameWorld(
            array(
                'galaxy' => $_POST['thisgalaxy'] ?? null,
                'system' => $_POST['thissystem'] ?? null,
                'planet' => $_POST['thisplanet'] ?? null,
                'planet_type' => $_POST['thisplanettype'] ?? 1,
            ),
            $select
        );

        if ($fleetmission == 15) {
            $missiontype = array(15 => $lang['type_mission'][15]);
        } else {
            $missiontype = array();
            if (($_POST['planettype'] ?? null) == "2") {
                if (($_POST['ship209'] ?? null) >= 1) {
                    $missiontype = array(8 => $lang['type_mission'][8]);
                } else {
                    $missiontype = array();
                }
            } elseif (($_POST['planettype'] ?? null) == "1" || ($_POST['planettype'] ?? null) == "3") {
                if (($_POST['ship208'] ?? null) >= 1 && !$UsedPlanet) {
                    $missiontype = array(7 => $lang['type_mission'][7]);
                } elseif (($_POST['ship210'] ?? null) >= 1 && !$YourPlanet) {
                    $missiontype = array(6 => $lang['type_mission'][6]);
                }

                if (
                    ($_POST['ship202'] ?? null) >= 1 ||
                    ($_POST['ship203'] ?? null) >= 1 ||
                    ($_POST['ship204'] ?? null) >= 1 ||
                    ($_POST['ship205'] ?? null) >= 1 ||
                    ($_POST['ship206'] ?? null) >= 1 ||
                    ($_POST['ship207'] ?? null) >= 1 ||
                    ($_POST['ship210'] ?? null) >= 1 ||
                    ($_POST['ship211'] ?? null) >= 1 ||
                    ($_POST['ship213'] ?? null) >= 1 ||
                    ($_POST['ship214'] ?? null) >= 1 ||
                    ($_POST['ship215'] ?? null) >= 1
                ) {
                    if (!$YourPlanet) {
                        $missiontype[1] = $lang['type_mission'][1];
                    }
                    if (!$SameWorld) {
                        $missiontype[3] = $lang['type_mission'][3];
                    }
                    $missiontype[5] = $lang['type_mission'][5];
                }
            } elseif (($_POST['ship209'] ?? null) >= 1 || ($_POST['ship208'] ?? null)) {
                if (!$SameWorld) {
                    $missiontype[3] = $lang['type_mission'][3];
                }
            }
            if ($YourPlanet && !$SameWorld) {
                $missiontype[4] = $lang['type_mission'][4];
            }

            if (
                ($_POST['planettype'] ?? null) == 3 &&
                (($_POST['ship214'] ?? null) ||
                    ($_POST['ship213'] ?? null)) &&
                !$YourPlanet &&
                $UsedPlanet
            ) {
                $missiontype[2] = $lang['type_mission'][2];
            }
            if (
                ($_POST['planettype'] ?? null) == 3 &&
                ($_POST['ship214'] ?? null) >= 1 &&
                !$YourPlanet &&
                $UsedPlanet
            ) {
                $missiontype[9] = $lang['type_mission'][9];
            }
        }

        // Missions apportées par un module : même règle que sur le formulaire d'envoi.
        $missiontype += $this->extraMissions($lang, $_POST);

        if (empty($missiontype[$fleetmission])) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_bad_mission'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        CheckPlanetUsedFields($CurrentPlanet);

        if (($TargetPlanet['id_owner'] ?? '') == '') {
            $HeDBRec = $MyDBRec;
        } elseif (($TargetPlanet['id_owner'] ?? '') != '') {
            $HeDBRec = $this->users->findFullById((int) $TargetPlanet['id_owner']);
        }

        $UserPoints = $this->stats->findUserStatRow((int) $MyDBRec['id']);
        $User2Points = $this->stats->findUserStatRow((int) $HeDBRec['id']);

        $MyGameLevel = $UserPoints['total_points'];
        $HeGameLevel = $User2Points['total_points'];
        $VacationMode = $HeDBRec['vacation_mode'];

        if (
            $MyGameLevel > ($HeGameLevel * $protectionmulti) and
            ($TargetPlanet['id_owner'] ?? '') != '' and
            ($_POST['mission'] ?? null) == 1 and
            $protection == 1 and
            $HeGameLevel < ($protectiontime * 1000)
        ) {
            return $this->renderMessage("<font color=\"lime\"><b>" . $lang['fl_noob_mess_n'] . "</b></font>", $lang['fl_noob_title'], "fleet." . PHPEXT, 2);
        }

        if (
            $MyGameLevel > ($HeGameLevel * $protectionmulti) and
            ($TargetPlanet['id_owner'] ?? '') != '' and
            ($_POST['mission'] ?? null) == 5 and
            $protection == 1 and
            $HeGameLevel < ($protectiontime * 1000)
        ) {
            return $this->renderMessage("<font color=\"lime\"><b>" . $lang['fl_noob_mess_n'] . "</b></font>", $lang['fl_noob_title'], "fleet." . PHPEXT, 2);
        }

        if (
            $MyGameLevel > ($HeGameLevel * $protectionmulti) and
            ($TargetPlanet['id_owner'] ?? '') != '' and
            ($_POST['mission'] ?? null) == 6 and
            $protection == 1 and
            $HeGameLevel < ($protectiontime * 1000)
        ) {
            return $this->renderMessage("<font color=\"lime\"><b>" . $lang['fl_noob_mess_n'] . "</b></font>", $lang['fl_noob_title'], "fleet." . PHPEXT, 2);
        }

        if (
            ($MyGameLevel * $protectionmulti) < $HeGameLevel and
            ($TargetPlanet['id_owner'] ?? '') != '' and
            ($_POST['mission'] ?? null) == 1 and
            $protection == 1 and
            $MyGameLevel < ($protectiontime * 1000)
        ) {
            return $this->renderMessage("<font color=\"lime\"><b>" . $lang['fl_noob_mess_n'] . "</b></font>", $lang['fl_noob_title'], "fleet." . PHPEXT, 2);
        }

        if (
            ($MyGameLevel * $protectionmulti) < $HeGameLevel and
            ($TargetPlanet['id_owner'] ?? '') != '' and
            ($_POST['mission'] ?? null) == 5 and
            $protection == 1 and
            $MyGameLevel < ($protectiontime * 1000)
        ) {
            return $this->renderMessage("<font color=\"lime\"><b>" . $lang['fl_noob_mess_n'] . "</b></font>", $lang['fl_noob_title'], "fleet." . PHPEXT, 2);
        }

        if (
            ($MyGameLevel * $protectionmulti) < $HeGameLevel and
            ($TargetPlanet['id_owner'] ?? '') != '' and
            ($_POST['mission'] ?? null) == 6 and
            $protection == 1 and
            $MyGameLevel < ($protectiontime * 1000)
        ) {
            return $this->renderMessage("<font color=\"lime\"><b>" . $lang['fl_noob_mess_n'] . "</b></font>", $lang['fl_noob_title'], "fleet." . PHPEXT, 2);
        }

        if ($VacationMode and ($_POST['mission'] ?? null) != 8) {
            return $this->renderMessage("<font color=\"lime\"><b>" . $lang['fl_vacation_pla'] . "</b></font>", $lang['fl_vacation_ttl'], "fleet." . PHPEXT, 2);
        }

        $FlyingFleets = $this->fleets->countByOwner((int) $user['id']);
        $ActualFleets = $FlyingFleets["Number"];
        if (($user[$resource[108]] + 1) <= $ActualFleets) {
            return $this->renderMessage("Pas de slot disponible", "Erreur", "fleet." . PHPEXT, 1);
        }

        // Les champs de ressources arrivent en chaine ("") quand ils sont vides :
        // en PHP 8 "" + "" leve un TypeError, on force donc la conversion.
        $postedResources = (float) ($_POST['resource1'] ?? 0) + (float) ($_POST['resource2'] ?? 0) + (float) ($_POST['resource3'] ?? 0);

        if ($postedResources < 1 and ($_POST['mission'] ?? null) == 3) {
            return $this->renderMessage("<font color=\"lime\"><b>" . $lang['fl_noenoughtgoods'] . "</b></font>", $lang['type_mission'][3], "fleet." . PHPEXT, 1);
        }
        if (($_POST['mission'] ?? null) != 15) {
            if (($TargetPlanet['id_owner'] ?? '') == '' and ($_POST['mission'] ?? null) < 7) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_bad_planet01'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
            if (($TargetPlanet['id_owner'] ?? '') != '' and ($_POST['mission'] ?? null) == 7) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_bad_planet02'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
            if (($HeDBRec['ally_id'] ?? null) != ($MyDBRec['ally_id'] ?? null) and ($_POST['mission'] ?? null) == 4) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_dont_stay_here'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
            // « Stationner chez un allié » : la cible doit être un ami accepté ou
            // un membre de la même alliance (règle partagée avec l'API).
            if (($_POST['mission'] ?? null) == 5 and $HeDBRec != $MyDBRec) {
                $BuddyRow = $this->buddies->findBetweenUsers((int) $MyDBRec['id'], (int) $HeDBRec['id']);
                $IsFriend = $BuddyRow !== false && (int) ($BuddyRow['active'] ?? 0) === 1;

                if (!FleetDispatchService::holdTargetAllowed($MyDBRec, $HeDBRec, $IsFriend)) {
                    return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_no_friend_stay'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
                }
            }
            if ((($TargetPlanet["id_owner"] ?? null) == ($CurrentPlanet["id_owner"] ?? null)) and (($_POST["mission"] ?? null) == 1)) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_no_self_attack'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
            if ((($TargetPlanet["id_owner"] ?? null) == ($CurrentPlanet["id_owner"] ?? null)) and (($_POST["mission"] ?? null) == 6)) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_no_self_spy'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
            if ((($TargetPlanet["id_owner"] ?? null) != ($CurrentPlanet["id_owner"] ?? null)) and (($_POST["mission"] ?? null) == 4)) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_only_stay_at_home'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
            }
        }

        $missiontype = array(
            1 => $lang['type_mission'][1],
            2 => $lang['type_mission'][2],
            3 => $lang['type_mission'][3],
            4 => $lang['type_mission'][4],
            5 => $lang['type_mission'][5],
            6 => $lang['type_mission'][6],
            7 => $lang['type_mission'][7],
            8 => $lang['type_mission'][8],
            9 => $lang['type_mission'][9],
            10 => $lang['type_mission'][10],
            15 => $lang['type_mission'][15],
        ) + $this->missionLabels($lang);

        $speed_possible = array(10, 9, 8, 7, 6, 5, 4, 3, 2, 1);

        $AllFleetSpeed = GetFleetMaxSpeed($fleetarray, 0, $user);
        $GenFleetSpeed = $_POST['speed'] ?? null;
        $SpeedFactor = $_POST['speedfactor'] ?? null;
        $MaxFleetSpeed = min($AllFleetSpeed);

        if (!in_array($GenFleetSpeed, $speed_possible)) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_cheat_speed'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        if ($MaxFleetSpeed != ($_POST['speedallsmin'] ?? null)) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_cheat_speed'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        if (!($_POST['planettype'] ?? null)) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_no_planet_type'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        $error = 0;
        $errorlist = "";
        if (!($_POST['galaxy'] ?? null) || !is_numeric($_POST['galaxy']) || $_POST['galaxy'] > MAX_GALAXY_IN_WORLD || $_POST['galaxy'] < 1) {
            $error++;
            $errorlist .= $lang['fl_limit_galaxy'];
        }
        if (!($_POST['system'] ?? null) || !is_numeric($_POST['system']) || $_POST['system'] > MAX_SYSTEM_IN_GALAXY || $_POST['system'] < 1) {
            $error++;
            $errorlist .= $lang['fl_limit_system'];
        }
        if (!($_POST['planet'] ?? null) || !is_numeric($_POST['planet']) || $_POST['planet'] > MAX_PLANET_IN_SYSTEM + 1 || $_POST['planet'] < 1) {
            $error++;
            $errorlist .= $lang['fl_limit_planet'];
        }

        if ($error > 0) {
            return $this->renderMessage("<font color=\"red\"><ul>" . $errorlist . "</ul></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        if (
            ($_POST['thisgalaxy'] ?? null) != $CurrentPlanet['galaxy'] |
            ($_POST['thissystem'] ?? null) != $CurrentPlanet['system'] |
            ($_POST['thisplanet'] ?? null) != $CurrentPlanet['planet'] |
            ($_POST['thisplanettype'] ?? null) != $CurrentPlanet['planet_type']
        ) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_cheat_origine'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        if (!isset($fleetarray)) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_no_fleetarray'] . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        $distance = GetTargetDistance($_POST['thisgalaxy'] ?? null, $_POST['galaxy'] ?? null, $_POST['thissystem'] ?? null, $_POST['system'] ?? null, $_POST['thisplanet'] ?? null, $_POST['planet'] ?? null);
        $duration = GetMissionDuration($GenFleetSpeed, $MaxFleetSpeed, $distance, $SpeedFactor);
        $consumption = GetFleetConsumption($fleetarray, $SpeedFactor, $duration, $distance, $MaxFleetSpeed, $user);

        $fleet = array();
        $fleet['start_time'] = $duration + time();
        if (($_POST['mission'] ?? null) == 15) {
            $StayDuration = ($_POST['expeditiontime'] ?? null) * 3600;
            $StayTime = $fleet['start_time'] + ($_POST['expeditiontime'] ?? null) * 3600;
        } elseif (($_POST['mission'] ?? null) == 5) {
            $StayDuration = ($_POST['holdingtime'] ?? null) * 3600;
            $StayTime = $fleet['start_time'] + ($_POST['holdingtime'] ?? null) * 3600;
        } else {
            $StayDuration = 0;
            $StayTime = 0;
        }
        // Mise en orbite : aucun retour n'est programme (le rappel fixe l'heure),
        // l'echeance de la flotte est donc son arrivee.
        $fleet['end_time'] = (($_POST['mission'] ?? null) == FleetDispatchService::MISSION_ORBIT)
            ? $fleet['start_time']
            : $StayDuration + (2 * $duration) + time();
        $FleetStorage = 0;
        $FleetShipCount = 0;
        $fleet_array = "";
        $FleetShips = array();

        foreach ($fleetarray as $Ship => $Count) {
            $FleetStorage += $pricelist[$Ship]["capacity"] * $Count;
            $FleetShipCount += $Count;
            $fleet_array .= $Ship . "," . $Count . ";";
            $FleetShips[$resource[$Ship]] = (int) $Count;
        }

        $FleetStorage -= $consumption;
        $StorageNeeded = 0;
        if (($_POST['resource1'] ?? null) < 1) {
            $TransMetal = 0;
        } else {
            $TransMetal = $_POST['resource1'];
            $StorageNeeded += $TransMetal;
        }
        if (($_POST['resource2'] ?? null) < 1) {
            $TransCrystal = 0;
        } else {
            $TransCrystal = $_POST['resource2'];
            $StorageNeeded += $TransCrystal;
        }
        if (($_POST['resource3'] ?? null) < 1) {
            $TransDeuterium = 0;
        } else {
            $TransDeuterium = $_POST['resource3'];
            $StorageNeeded += $TransDeuterium;
        }

        $StockMetal = $CurrentPlanet['metal'];
        $StockCrystal = $CurrentPlanet['crystal'];
        $StockDeuterium = $CurrentPlanet['deuterium'];
        $StockDeuterium -= $consumption;

        $StockOk = false;
        if ($StockMetal >= $TransMetal) {
            if ($StockCrystal >= $TransCrystal) {
                if ($StockDeuterium >= $TransDeuterium) {
                    $StockOk = true;
                }
            }
        }
        if (!$StockOk) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_noressources'] . pretty_number($consumption) . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        if ($StorageNeeded > $FleetStorage) {
            return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_nostoragespa'] . pretty_number($StorageNeeded - $FleetStorage) . "</b></font>", $lang['fl_error'], "fleet." . PHPEXT, 2);
        }

        if ((($TargetPlanet['id_level'] ?? 0) > $user['authlevel'])) {
            $Allowed = true;
            switch (($_POST['mission'] ?? null)) {
                case 1:
                case 2:
                case 6:
                case 9:
                    $Allowed = false;
                    break;
                case 3:
                case 4:
                case 5:
                case 7:
                case 8:
                case 10:
                case 15:
                    break;
                default:
            }
            if ($Allowed == false) {
                return $this->renderMessage("<font color=\"red\"><b>" . $lang['fl_adm_attak'] . "</b></font>", $lang['fl_warning'], "fleet." . PHPEXT, 2);
            }
        }

        $this->fleets->insert(array(
            'fleet_owner' => $user['id'],
            'fleet_mission' => ($_POST['mission'] ?? null),
            'fleet_amount' => $FleetShipCount,
            'fleet_array' => $fleet_array,
            'fleet_start_time' => $fleet['start_time'],
            'fleet_start_galaxy' => intval($_POST['thisgalaxy']),
            'fleet_start_system' => intval($_POST['thissystem']),
            'fleet_start_planet' => intval($_POST['thisplanet']),
            'fleet_start_type' => intval($_POST['thisplanettype']),
            'fleet_end_time' => $fleet['end_time'],
            'fleet_end_stay' => $StayTime,
            'fleet_end_galaxy' => intval($_POST['galaxy']),
            'fleet_end_system' => intval($_POST['system']),
            'fleet_end_planet' => intval($_POST['planet']),
            'fleet_end_type' => intval($_POST['planettype']),
            'fleet_resource_metal' => $TransMetal,
            'fleet_resource_crystal' => $TransCrystal,
            'fleet_resource_deuterium' => $TransDeuterium,
            'fleet_target_owner' => ($TargetPlanet['id_owner'] ?? ''),
            'start_time' => time(),
        ));

        $CurrentPlanet["metal"] = $CurrentPlanet["metal"] - $TransMetal;
        $CurrentPlanet["crystal"] = $CurrentPlanet["crystal"] - $TransCrystal;
        $CurrentPlanet["deuterium"] = $CurrentPlanet["deuterium"] - $TransDeuterium;
        $CurrentPlanet["deuterium"] = $CurrentPlanet["deuterium"] - $consumption;

        $this->fleets->lockPlanets();
        $this->fleets->updateShips((int) $CurrentPlanet['id'], $FleetShips, array(
            'metal' => $CurrentPlanet["metal"],
            'crystal' => $CurrentPlanet["crystal"],
            'deuterium' => $CurrentPlanet["deuterium"],
        ));
        $this->fleets->unlockTables();

        // Le plan courant doit refleter les ressources reellement debitees,
        // sinon l'en-tete de page les reecrit avec les valeurs obsoletes.
        $this->setPlanetRow($CurrentPlanet);

        $page = "<div class=\"card xnova-panel border-0 shadow-sm mb-3\">";
        $page .= "<div class=\"card-header fw-semibold text-success\"><i class=\"bi bi-check-circle\" aria-hidden=\"true\"></i> " . $lang['fl_fleet_send'] . "</div>";
        $page .= "<div class=\"card-body\">";
        $page .= "<table class=\"table table-sm align-middle mb-0\">";
        $page .= "<tbody>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_mission'] . "</td><td>" . $missiontype[$_POST['mission']] . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_dist'] . "</td><td>" . pretty_number($distance) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_speed'] . "</td><td>" . pretty_number(($_POST['speedallsmin'] ?? null)) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_deute_need'] . "</td><td>" . pretty_number($consumption) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_from'] . "</td><td>" . ($_POST['thisgalaxy'] ?? null) . ":" . ($_POST['thissystem'] ?? null) . ":" . ($_POST['thisplanet'] ?? null) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_dest'] . "</td><td>" . ($_POST['galaxy'] ?? null) . ":" . ($_POST['system'] ?? null) . ":" . ($_POST['planet'] ?? null) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_time_go'] . "</td><td>" . date("d.m.Y H:i:s", $fleet['start_time']) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_time_back'] . "</td><td>" . date("d.m.Y H:i:s", $fleet['end_time']) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-section\" colspan=\"2\">" . $lang['fl_title'] . "</td></tr>";

        foreach ($fleetarray as $Ship => $Count) {
            $page .= "<tr><td class=\"xnova-label\">" . $lang['tech'][$Ship] . "</td><td>" . pretty_number($Count) . "</td></tr>";
        }
        $page .= "</tbody></table>";
        $page .= "</div>";
        $page .= "<div class=\"card-footer text-center\">";
        $page .= "<a href=\"/game/fleet\"><i class=\"bi bi-arrow-left\" aria-hidden=\"true\"></i> " . $lang['fl_title'] . "</a>";
        $page .= "</div>";
        $page .= "</div>";

        sleep(1);

        return $this->renderPage($page, $lang['fl_title']);
    }

    public function flotenajaxAction(Request $request): Response
    {
        $this->bootLegacy();

        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();
        $resource = $this->resource();
        $reslist = $this->resList();
        $gameConfig = $this->gameConfig();
        $pricelist = $GLOBALS['pricelist'] ?? [];

        $this->includeLang('galaxy');
        $this->includeLang('fleet');
        $lang = $this->lang();

        $UserSpyProbes = $planetrow['spy_sonde'];
        $UserRecycles = $planetrow['recycler'];
        $UserDeuterium = $planetrow['deuterium'];
        $UserMissiles = $planetrow['interplanetary_misil'];

        $fleet = array();
        $speedalls = array();
        $PartialFleet = false;
        $PartialCount = 0;

        foreach ($reslist['fleet'] as $Node => $ShipID) {
            $TName = "ship" . $ShipID;
            if ($ShipID > 200 && $ShipID < 300 && (($_POST[$TName] ?? null) > 0)) {
                if (($_POST[$TName] ?? null) > $planetrow[$resource[$ShipID]]) {
                    $fleet['fleetarray'][$ShipID] = $planetrow[$resource[$ShipID]];
                    $fleet['fleetlist'] .= $ShipID . "," . $planetrow[$resource[$ShipID]] . ";";
                    $fleet['amount'] += $planetrow[$resource[$ShipID]];
                    $PartialCount += $planetrow[$resource[$ShipID]];
                    $PartialFleet = true;
                } else {
                    $fleet['fleetarray'][$ShipID] = $_POST[$TName];
                    $fleet['fleetlist'] .= $ShipID . "," . $_POST[$TName] . ";";
                    $fleet['amount'] += $_POST[$TName];
                    $speedalls[$ShipID] = $_POST[$TName];
                }
            }
        }

        if ($PartialFleet == true) {
            if ($PartialCount < 1) {
                $ResultMessage = "610;" . $lang['gs_c610a'] . $PartialCount . $lang['gs_c610b'] . "|" . ($CurrentFlyingFleets ?? null) . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
                return Response::raw($ResultMessage);
            }
        }

        $PrNoob = $gameConfig['noobprotection'];
        $PrNoobTime = $gameConfig['noobprotectiontime'];
        $PrNoobMulti = $gameConfig['noobprotectionmulti'];

        $galaxy = intval($_POST['galaxy'] ?? 0);
        if ($galaxy > 9 || $galaxy < 1) {
            $ResultMessage = "602;" . $lang['gs_c602'] . "|" . ($CurrentFlyingFleets ?? null) . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        $system = intval($_POST['system'] ?? 0);
        if ($system > 499 || $system < 1) {
            $ResultMessage = "602;" . $lang['gs_c602'] . "|" . ($CurrentFlyingFleets ?? null) . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        $planet = intval($_POST['planet'] ?? 0);
        if ($planet > 15 || $planet < 1) {
            $ResultMessage = "602;" . $lang['gs_c602'] . "|" . ($CurrentFlyingFleets ?? null) . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        $FleetArray = $fleet['fleetarray'];

        $CurrentFlyingFleets = $this->fleets->countByOwner((int) $user['id'])['Nbre'];

        $TargetRow = $this->planets->findByCoords($galaxy, $system, $planet, $_POST['planettype'] ?? null);

        if (($TargetRow['id_owner'] ?? '') == '') {
            $TargetUser = $user;
        } elseif (($TargetRow['id_owner'] ?? '') != '') {
            $TargetUser = (new \App\Repositories\UserRepository())->findFullById((int) $TargetRow['id_owner']);
        }
        $UserPoints = (new \App\Repositories\StatsRepository())->findUserStatRow((int) $user['id']);
        $User2Points = (new \App\Repositories\StatsRepository())->findUserStatRow((int) $TargetUser['id']);

        $CurrentPoints = $UserPoints['total_points'];
        $TargetPoints = $User2Points['total_points'];
        $TargetVacat = $TargetUser['vacation_mode'];

        if (($user[$resource[108]] + 1) <= $CurrentFlyingFleets) {
            $ResultMessage = "612;" . $lang['gs_c612'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        if (!is_array($FleetArray)) {
            $ResultMessage = "618;" . $lang['gs_c618'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        // Le raccourci de la vue galaxie n'ouvre que les gestes qu'elle propose :
        // espionnage (6), colonisation (7), recyclage (8) et les missions de champ
        // de débris déclarées par un module (l'extraction des extracteurs). La règle
        // est dans le Coeur d'application, jamais recopiée ici.
        $Mission = (int) ($_POST['mission'] ?? 0);

        if (!in_array($Mission, \App\Services\FleetDispatchService::galaxyMissionIds(), true)) {
            $ResultMessage = "618;" . $lang['gs_c618'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        foreach ($FleetArray as $Ships => $Count) {
            if ($Count > $planetrow[$resource[$Ships]]) {
                $ResultMessage = "611;" . $lang['gs_c611'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
                return Response::raw($ResultMessage);
            }
        }

        if ($PrNoobTime < 1) {
            $PrNoobTime = 9999999999999999;
        }

        if ($TargetVacat && ($_POST['mission'] ?? null) != 8) {
            $ResultMessage = "605;" . $lang['gs_c605'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        if (
            $CurrentPoints > ($TargetPoints * $PrNoobMulti) and
            ($TargetRow['id_owner'] ?? '') != '' and
            ($_POST['mission'] ?? null) == 6 and
            $PrNoob == 1 and
            $TargetPoints < ($PrNoobTime * 1000)
        ) {
            $ResultMessage = "603;" . $lang['gs_c603'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        if (
            $TargetPoints > ($CurrentPoints * $PrNoobMulti) and
            ($TargetRow['id_owner'] ?? '') != '' and
            ($_POST['mission'] ?? null) == 6 and
            $PrNoob == 1 and
            $CurrentPoints < ($PrNoobTime * 1000)
        ) {
            $ResultMessage = "604;" . $lang['gs_c604'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        // Une position sans planète n'accepte que les missions qui visent une position
        // libre : colonisation, recyclage, et les missions de champ de débris d'un
        // module. Règle unique (le manifeste dit `free_target`), partagée avec l'API
        // et la page d'envoi.
        if (
            ($TargetRow['id_owner'] ?? '') == '' and
            \App\Services\FleetDispatchService::missionNeedsTarget($Mission)
        ) {
            $ResultMessage = "601;" . $lang['gs_c601'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        if (
            (($TargetRow["id_owner"] ?? null) == ($planetrow["id_owner"] ?? null)) and
            (($_POST["mission"] ?? null) == 6)
        ) {
            $ResultMessage = "618;" . $lang['gs_c618'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        if (
            ($_POST['thisgalaxy'] ?? null) != $planetrow['galaxy'] |
            ($_POST['thissystem'] ?? null) != $planetrow['system'] |
            ($_POST['thisplanet'] ?? null) != $planetrow['planet'] |
            ($_POST['thisplanettype'] ?? null) != $planetrow['planet_type']
        ) {
            $ResultMessage = "618;" . $lang['gs_c618'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
            return Response::raw($ResultMessage);
        }

        $Distance = GetTargetDistance($_POST['thisgalaxy'] ?? null, $_POST['galaxy'] ?? null, $_POST['thissystem'] ?? null, $_POST['system'] ?? null, $_POST['thisplanet'] ?? null, $_POST['planet'] ?? null);
        $speedall = GetFleetMaxSpeed($FleetArray, 0, $user);
        $SpeedAllMin = min($speedall);
        $Duration = GetMissionDuration(10, $SpeedAllMin, $Distance, GetGameSpeedFactor());

        $fleet['fly_time'] = $Duration;
        $fleet['start_time'] = $Duration + time();
        $fleet['end_time'] = ($Duration * 2) + time();

        $FleetShipCount = 0;
        $FleetDBArray = "";
        $FleetShips = array();
        $consumption = 0;
        $SpeedFactor = GetGameSpeedFactor();
        foreach ($FleetArray as $Ship => $Count) {
            $ShipSpeed = $pricelist[$Ship]["speed"];
            $spd = 35000 / ($Duration * $SpeedFactor - 10) * sqrt($Distance * 10 / $ShipSpeed);
            $basicConsumption = $pricelist[$Ship]["consumption"] * $Count;
            $consumption += $basicConsumption * $Distance / 35000 * (($spd / 10) + 1) * (($spd / 10) + 1);
            $FleetShipCount += $Count;
            $FleetDBArray .= $Ship . "," . $Count . ";";
            $FleetShips[$resource[$Ship]] = (int) $Count;
        }
        $consumption = round($consumption) + 1;

        if ((($TargetRow['id_level'] ?? 0) > $user['authlevel'])) {
            $Allowed = true;
            switch (($_POST['mission'] ?? null)) {
                case 1:
                case 2:
                case 6:
                case 9:
                    $Allowed = false;
                    break;
                case 3:
                case 4:
                case 5:
                case 7:
                case 8:
                case 15:
                    break;
                default:
            }
            if ($Allowed == false) {
                $ResultMessage = "619;" . $lang['gs_c619'] . "|" . $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;
                return Response::raw($ResultMessage);
            }
        }

        $this->fleets->insert(array(
            'fleet_owner' => $user['id'],
            'fleet_mission' => intval($_POST['mission']),
            'fleet_amount' => $FleetShipCount,
            'fleet_array' => $FleetDBArray,
            'fleet_start_time' => $fleet['start_time'],
            'fleet_start_galaxy' => intval($_POST['thisgalaxy']),
            'fleet_start_system' => intval($_POST['thissystem']),
            'fleet_start_planet' => intval($_POST['thisplanet']),
            'fleet_start_type' => intval($_POST['thisplanettype']),
            'fleet_end_time' => $fleet['end_time'],
            'fleet_end_stay' => 0,
            'fleet_end_galaxy' => intval($_POST['galaxy']),
            'fleet_end_system' => intval($_POST['system']),
            'fleet_end_planet' => intval($_POST['planet']),
            'fleet_end_type' => intval($_POST['planettype']),
            'fleet_resource_metal' => 0,
            'fleet_resource_crystal' => 0,
            'fleet_resource_deuterium' => 0,
            'fleet_target_owner' => ($TargetRow['id_owner'] ?? 0),
            'start_time' => time(),
        ));

        $UserDeuterium -= $consumption;
        $this->fleets->updateShips((int) $planetrow['id'], $FleetShips, array('deuterium' => $UserDeuterium));

        $CurrentFlyingFleets++;

        $ResultMessage = "600;" . $lang['gs_sending'] . " " . $FleetShipCount . " " . $lang['tech'][$Ship] . " " . $lang['gs_to'] . " " . ($_POST['galaxy'] ?? null) . ":" . ($_POST['system'] ?? null) . ":" . ($_POST['planet'] ?? null) . "...|";
        $ResultMessage .= $CurrentFlyingFleets . " " . $UserSpyProbes . " " . $UserRecycles . " " . $UserMissiles;

        return Response::raw($ResultMessage);
    }

    public function quickfleetAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('fleet');
        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();
        $gameConfig = $this->gameConfig();
        $resource = $this->resource();
        $pricelist = $GLOBALS['pricelist'] ?? [];

        $Mode = $_GET['mode'] ?? null;
        $Galaxy = $_GET['g'] ?? null;
        $System = $_GET['s'] ?? null;
        $Planet = $_GET['p'] ?? null;
        $TypePl = $_GET['t'] ?? null;

        $missiontype = array(
            1 => $lang['type_mission'][1],
            2 => $lang['type_mission'][2],
            3 => $lang['type_mission'][3],
            4 => $lang['type_mission'][4],
            5 => $lang['type_mission'][5],
            6 => $lang['type_mission'][6],
            7 => $lang['type_mission'][7],
            8 => $lang['type_mission'][8],
            9 => $lang['type_mission'][9],
            10 => $lang['type_mission'][10],
            15 => $lang['type_mission'][15]
        );

        $FleetArray = array();
        if ($Mode == 8) {
            $TargetGalaxy = $this->galaxies->findDebrisAt((int) $planetrow['galaxy'], (int) $planetrow['system'], (int) $planetrow['planet']);
            $DebrisSize = $TargetGalaxy['metal'] + $TargetGalaxy['crystal'];
            $RecyclerNeeded = floor($DebrisSize / ($pricelist[209]['capacity'])) + 1;

            $RecyclerCount = $planetrow[$resource[209]];
            if ($RecyclerCount > $RecyclerNeeded) {
                $FleetCount = $RecyclerNeeded;
            } else {
                $FleetCount = $RecyclerCount;
            }
            $FleetArray[209] = $FleetCount;
        }

        if ($Mode != 8) {
            return $this->renderMessage("<strong>" . $lang['fl_bad_mission'] . "</strong>", $lang['fl_error'], "/game/galaxy", 2, 'orange');
        }

        if (empty($FleetArray) || array_sum($FleetArray) <= 0) {
            return $this->renderMessage("<strong>" . $lang['fl_noships'] . "</strong>", $lang['fl_error'], "/game/galaxy", 2, 'orange');
        }

        $distance = GetTargetDistance($planetrow['galaxy'], $Galaxy, $planetrow['system'], $System, $planetrow['planet'], $Planet);
        $SpeedFactor = GetGameSpeedFactor();
        $GenFleetSpeed = 10;
        $AllFleetSpeed = GetFleetMaxSpeed($FleetArray, 0, $user);
        $MaxFleetSpeed = empty($AllFleetSpeed) ? 0 : min($AllFleetSpeed);
        $duration = GetMissionDuration($GenFleetSpeed, $MaxFleetSpeed, $distance, $SpeedFactor);
        $consumption = GetFleetConsumption($FleetArray, $SpeedFactor, $duration, $distance, $MaxFleetSpeed, $user);

        $UserDeuterium = (float) $planetrow['deuterium'] - (float) $consumption;
        if ($UserDeuterium < 0) {
            return $this->renderMessage("<strong>" . $lang['fl_nofuel'] . "</strong>", $lang['fl_error'], "/game/galaxy", 2, 'orange');
        }

        $page = "<div class=\"card xnova-panel border-0 shadow-sm mb-3\">";
        $page .= "<div class=\"card-header fw-semibold text-success\"><i class=\"bi bi-check-circle\" aria-hidden=\"true\"></i> " . $lang['fl_fleet_send'] . "</div>";
        $page .= "<div class=\"card-body\">";
        $page .= "<table class=\"table table-sm align-middle mb-0\">";
        $page .= "<tbody>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_mission'] . "</td><td>" . $missiontype[$Mode] . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_dist'] . "</td><td>" . pretty_number($distance) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_fleetspeed'] . "</td><td>" . pretty_number($MaxFleetSpeed) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_deute_need'] . "</td><td>" . pretty_number($consumption) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_from'] . "</td><td>[" . $planetrow['galaxy'] . ":" . $planetrow['system'] . ":" . $planetrow['planet'] . "]</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_dest'] . "</td><td>[" . $Galaxy . ":" . $System . ":" . $Planet . "]</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_time_go'] . "</td><td>" . date("d.m.Y H:i:s", ($duration + time())) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-label\">" . $lang['fl_time_back'] . "</td><td>" . date("d.m.Y H:i:s", (($duration * 2) + time())) . "</td></tr>";
        $page .= "<tr><td class=\"xnova-section\" colspan=\"2\">" . $lang['fl_title'] . "</td></tr>";
        $ShipCount = 0;
        $ShipArray = "";
        $FleetShips = array();
        foreach ($FleetArray as $Ship => $Count) {
            $page .= "<tr><td class=\"xnova-label\">" . $lang['tech'][$Ship] . "</td><td>" . pretty_number($Count) . "</td></tr>";
            $FleetShips[$resource[$Ship]] = (int) $Count;
            $planetrow[$resource[$Ship]] = ($planetrow[$resource[$Ship]] ?? 0) - $Count;
            $ShipArray .= $Ship . "," . $Count . ";";
            $ShipCount += $Count;
        }
        $page .= "</tbody></table>";
        $page .= "</div>";
        $page .= "<div class=\"card-footer text-center\">";
        $page .= "<a href=\"/game/fleet\"><i class=\"bi bi-arrow-left\" aria-hidden=\"true\"></i> " . $lang['fl_title'] . "</a>";
        $page .= "</div>";
        $page .= "</div>";

        if ($Mode == 8) {
            $this->fleets->insert(array(
                'fleet_owner' => $user['id'],
                'fleet_mission' => $Mode,
                'fleet_amount' => $ShipCount,
                'fleet_array' => $ShipArray,
                'fleet_start_time' => $duration + time(),
                'fleet_start_galaxy' => $planetrow['galaxy'],
                'fleet_start_system' => $planetrow['system'],
                'fleet_start_planet' => $planetrow['planet'],
                'fleet_start_type' => $planetrow['planet_type'],
                'fleet_end_time' => ($duration * 2) + time(),
                'fleet_end_stay' => 0,
                'fleet_end_galaxy' => $Galaxy,
                'fleet_end_system' => $System,
                'fleet_end_planet' => $Planet,
                'fleet_end_type' => $TypePl,
                'fleet_resource_metal' => 0,
                'fleet_resource_crystal' => 0,
                'fleet_resource_deuterium' => 0,
                'fleet_target_owner' => 0,
                'start_time' => time(),
            ));

            $this->fleets->lockPlanets();
            $this->fleets->updateShips((int) $planetrow['id'], $FleetShips, array(
                'deuterium' => $UserDeuterium,
                'planet_type' => $planetrow['planet_type'],
            ));
            $this->fleets->unlockTables();

            $planetrow['deuterium'] = $UserDeuterium;
            $this->setPlanetRow($planetrow);
        }

        return $this->renderPage($page, $lang['fl_title']);
    }
}
