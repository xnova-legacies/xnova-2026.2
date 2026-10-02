<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\GalaxyRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\StatsRepository;
use App\Repositories\UserRepository;
use App\Services\ImperiumService;
use App\Services\ModuleService;
use App\Services\ResourceService;
use App\Services\TechTreeService;

class OverviewController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly GalaxyRepository $galaxies = new GalaxyRepository(),
        private readonly StatsRepository $stats = new StatsRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('resources');
        $this->includeLang('overview');

        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();
        $galaxyrow = $this->galaxyRow();
        $dpath = $this->skinPath();
        $gameConfig = $this->gameConfig();
        $resource = $this->resource();

        $lunarow = $this->planets->findMoonAt($planetrow);

        if (!empty($lunarow)) {
            CheckPlanetUsedFields($lunarow);
        }

        $mode = $_GET['mode'] ?? '';
        $_POST['deleteid'] = intval($_POST['deleteid'] ?? null);

        switch ($mode) {
            case 'renameplanet':
                // Deux formulaires partagent cette adresse : le renommage (bouton `namer`)
                // et la demande d'abandon (`colony_abandon`), puis la confirmation, qui ne
                // porte que `delete_colony`. Le bouton d'abandon porte `data-ajax-skip`
                // pour sortir de l'AJAX du renommage : c'est ce POST classique qui mène à
                // la confirmation, puis à la suppression.
                if (($_POST['action'] ?? null) == $lang['namer']) {
                    $UserPlanet = addslashes(CheckInputStrings($_POST['newname'] ?? null));
                    $newname = mysql_escape_string(trim($UserPlanet));
                    if ($newname != "") {
                        $planetrow['name'] = $newname;
                        $this->planets->renamePlanet((int) $user['current_planet'], $newname);
                        if ($planetrow['planet_type'] == 3) {
                            $this->planets->renameMoonByPosition($planetrow, $newname);
                        }
                    }
                } elseif (($_POST['action'] ?? null) == $lang['colony_abandon']) {
                    $parse = $lang;
                    $parse['planet_id'] = $planetrow['id'];
                    $parse['galaxy_galaxy'] = $planetrow['galaxy'];
                    $parse['galaxy_system'] = $planetrow['system'];
                    $parse['galaxy_planet'] = $planetrow['planet'];
                    $parse['planet_name'] = $planetrow['name'];

                    $page = $this->parse($this->template('overview_deleteplanet'), $parse);
                    return $this->renderPage($page, $lang['rename_and_abandon_planet']);
                } elseif (($_POST['delete_colony'] ?? null) == 1 && ($_POST['deleteid'] ?? null) == $user['current_planet']) {
                    if (md5($_POST['pw'] ?? null) == $user["password"] && $user['id_planet'] != $user['current_planet']) {
                        if (CheckFleets($planetrow)) {
                            $strMessage = "Vous ne pouvez pas abandonner la colonie, il y a de la flotte en vol !";
                            return $this->renderMessage($strMessage, $lang['colony_abandon'], '/game/overview?mode=renameplanet', 3);
                        }

                        AbandonColony($user, $planetrow);

                        $this->users->resetCurrentPlanet((int) $user['id']);

                        // La position reste réservée un moment : le joueur doit le lire
                        // au moment où il abandonne, pas seulement dans la galaxie.
                        $message = (string) $lang['deletemessage_ok'];
                        $delay = \App\Core\GameConstants::abandonedPositionDelay();

                        if ($delay > 0) {
                            $message .= ' ' . sprintf(
                                (string) ($lang['deletemessage_reserved'] ?? ''),
                                '[' . $planetrow['galaxy'] . ':' . $planetrow['system'] . ':' . $planetrow['planet'] . ']',
                                (int) round($delay / 3600)
                            );
                        }

                        return $this->renderMessage($message, $lang['colony_abandon'], '/game/overview', 3);
                    } elseif ($user['id_planet'] == $user["current_planet"]) {
                        return $this->renderMessage($lang['deletemessage_wrong'], $lang['colony_abandon'], '/game/overview?mode=renameplanet');
                    } else {
                        return $this->renderMessage($lang['deletemessage_fail'], $lang['colony_abandon'], '/game/overview?mode=renameplanet');
                    }
                }

                $parse = $lang;

                $parse['planet_id'] = $planetrow['id'];
                $parse['galaxy_galaxy'] = $planetrow['galaxy'];
                $parse['galaxy_system'] = $planetrow['system'];
                $parse['galaxy_planet'] = $planetrow['planet'];
                $parse['planet_name'] = $planetrow['name'];

                $page = $this->parse($this->template('overview_renameplanet'), $parse);
                return $this->renderPage($page, $lang['rename_and_abandon_planet']);

            default:
                if ($user['id'] != '') {
                    $Have_new_message = "";
                    if ($user['new_message'] != 0) {
                        $Have_new_message .= '<div class="alert alert-info d-flex align-items-center gap-2 m-3 mb-0">';
                        $Have_new_message .= '<i class="bi bi-envelope-fill" aria-hidden="true"></i>';
                        if ($user['new_message'] == 1) {
                            $Have_new_message .= '<a class="alert-link" href="/game/profil/messages">' . $lang['Have_new_message'] . '</a>';
                        } elseif ($user['new_message'] > 1) {
                            $Have_new_message .= '<a class="alert-link" href="/game/profil/messages">';
                            $m = pretty_number($user['new_message']);
                            $Have_new_message .= str_replace('%m', $m, $lang['Have_new_messages']);
                            $Have_new_message .= '</a>';
                        }
                        $Have_new_message .= '</div>';
                    }

                    // Les flottes en vol (les siennes, les hostiles et les attaques de
                    // missiles) ne sont plus listées ici : elles s'affichent sur toutes
                    // les pages dans le bandeau du bas (App\Core\FleetBar), qui reprend
                    // exactement les mêmes conditions d'événement (BuildFleetEventTable).
                    $Order = ($user['planet_sort_order'] == 1) ? "DESC" : "ASC";
                    $Sort = $user['planet_sort'];

                    $AllPlanets = "";
                    foreach ($this->planets->findAllByOwner((int) $user['id'], (int) $Sort, $Order) as $UserPlanet) {
                        PlanetResourceUpdate($user, $UserPlanet, time());
                        if ($UserPlanet["id"] != $user["current_planet"] && $UserPlanet['planet_type'] != 3) {
                            if ($UserPlanet['b_building'] != 0) {
                                UpdatePlanetBatimentQueueList($UserPlanet, $user);
                                if ($UserPlanet['b_building'] != 0) {
                                    $BuildQueue = $UserPlanet['b_building_id'];
                                    $QueueArray = explode(";", $BuildQueue);
                                    $CurrentBuild = explode(",", $QueueArray[0]);
                                    $BuildElement = $CurrentBuild[0];
                                    $BuildLevel = $CurrentBuild[1];
                                    $BuildRestTime = pretty_time($CurrentBuild[3] - time());
                                    $PlanetStatus = $lang['tech'][$BuildElement] . ' (' . $BuildLevel . ')'
                                        . '<br><span class="text-body-secondary">(' . $BuildRestTime . ')</span>';
                                } else {
                                    CheckPlanetUsedFields($UserPlanet);
                                    $PlanetStatus = $lang['Free'];
                                }
                            } else {
                                $PlanetStatus = $lang['Free'];
                            }

                            $AllPlanets .= '<div class="col">';
                            $AllPlanets .= '<div class="card h-100 text-center xnova-mini-planet">';
                            $AllPlanets .= '<div class="card-body p-2">';
                            $AllPlanets .= '<div class="small fw-semibold text-truncate" title="' . $UserPlanet['name'] . '">' . $UserPlanet['name'] . '</div>';
                            $AllPlanets .= '<a href="?cp=' . $UserPlanet['id'] . '&re=0" title="' . $UserPlanet['name'] . '">';
                            $AllPlanets .= '<img src="' . $dpath . 'planeten/small/s_' . $UserPlanet['image'] . '.jpg" width="50" height="50" class="rounded my-1" alt="' . $UserPlanet['name'] . '">';
                            $AllPlanets .= '</a>';
                            $AllPlanets .= '<div class="small">' . $PlanetStatus . '</div>';
                            $AllPlanets .= '</div>';
                            $AllPlanets .= '</div>';
                            $AllPlanets .= '</div>';
                        }
                    }

                    // Les attaques de missiles en approche sont annoncées par le
                    // bandeau des flottes (App\Core\FleetBar), comme les flottes.
                    $parse = $lang;

                    // Quatre onglets, rendus côté serveur (aucun script pour changer
                    // d'onglet) : la vue générale, la page des ressources, la vue Empire
                    // et l'arbre des technologies. Ces trois dernières y ont été déportées
                    // (`/game/resources`, `/game/profil/imperium` et `/game/profil`
                    // redirigent ici) ; le contenu vient des services concernés, donc les
                    // formulaires gardent leur POST classique.
                    $tabs = array('overview', 'resources', 'empire', 'tech');
                    $tab = in_array($_GET['tab'] ?? '', $tabs, true) ? (string) $_GET['tab'] : 'overview';

                    foreach ($tabs as $name) {
                        $parse['tab_' . $name . '_active'] = ($tab === $name) ? ' active' : '';
                    }

                    $parse['tab_situation_hidden'] = $tab === 'overview' ? '' : ' d-none';
                    $parse['tab_resources_hidden'] = $tab === 'resources' ? '' : ' d-none';
                    $parse['tab_empire_hidden'] = $tab === 'empire' ? '' : ' d-none';
                    $parse['tab_tech_hidden'] = $tab === 'tech' ? '' : ' d-none';
                    $parse['overview_resources'] = '';
                    $parse['overview_empire'] = '';
                    $parse['overview_tech'] = '';

                    if ($tab === 'resources') {
                        $parse['overview_resources'] = \App\Services\ModuleService::instance(ResourceService::class)->buildPage($user, $planetrow);
                    } elseif ($tab === 'empire') {
                        $parse['overview_empire'] = (new ImperiumService())->buildPage($user);
                    } elseif ($tab === 'tech') {
                        $parse['overview_tech'] = \App\Services\ModuleService::instance(TechTreeService::class)->buildPage($user, $planetrow);
                    }

                    if ($gameConfig['OverviewNewsFrame'] == '1') {
                        $parse['NewsFrame'] = '<div class="alert alert-secondary m-3 mb-0">'
                            . '<strong>' . $lang['ov_news_title'] . ' :</strong> '
                            . stripslashes($gameConfig['OverviewNewsText'])
                            . '</div>';
                    }
                    if ($gameConfig['OverviewExternChat'] == '1') {
                        $parse['ExternalTchatFrame'] = '<div class="xnova-extern-chat border-top p-3">'
                            . stripslashes($gameConfig['OverviewExternChatCmd'])
                            . '</div>';
                    }
                    if ($gameConfig['OverviewClickBanner'] != '') {
                        $parse['ClickBanner'] = stripslashes($gameConfig['OverviewClickBanner']);
                    }
                    if ($gameConfig['ForumBannerFrame'] == '1') {
                        $BannerURL = "" . dirname($_SERVER["HTTP_REFERER"] ?? '') . "/scripts/createbanner.php?id=" . $user['id'] . "";

                        $parse['bannerframe'] = '<div class="border-top p-3 text-center">'
                            . '<img src="/scripts/createbanner.php?id=' . $user['id'] . '" alt="" class="img-fluid">'
                            . '<p class="small text-body-secondary mb-1 mt-2">' . ($lang['InfoBanner'] ?? '') . '</p>'
                            . '<input name="bannerlink" type="text" id="bannerlink" readonly'
                            . ' class="form-control form-control-sm xnova-banner-input mx-auto"'
                            . ' value="[img]' . $BannerURL . '[/img]">'
                            . '</div>';
                    }

                    $parse['moon_block'] = '';
                    if (is_array($lunarow) && ($lunarow['id'] ?? 0) <> 0) {
                        if ($planetrow['planet_type'] == 1) {
                            $lune = $this->planets->findMoonPlanet($planetrow);
                            $parse['moon_img'] = '<a href="?cp=' . $lune['id'] . '&amp;re=0" title="' . htmlspecialchars($lune['name'], ENT_QUOTES) . '"><img src="' . $dpath . 'planeten/' . $lune['image'] . '.jpg" alt="' . htmlspecialchars($lune['name'], ENT_QUOTES) . '" height="50" width="50" class="rounded"></a>';
                            $parse['moon'] = $lune['name'];
                            $parse['moon_block'] = '<div class="d-inline-flex align-items-center gap-2 mb-2">'
                                . $parse['moon_img']
                                . '<span class="small"><span class="text-body-secondary">' . $lang['moon'] . ' :</span> ' . htmlspecialchars($lune['name'], ENT_QUOTES) . '</span>'
                                . '</div>';
                        } else {
                            $parse['moon_img'] = "";
                            $parse['moon'] = "";
                        }
                    } else {
                        $parse['moon_img'] = "";
                        $parse['moon'] = "";
                    }

                    $parse['planet_name'] = $planetrow['name'];
                    $parse['planet_diameter'] = pretty_number($planetrow['diameter']);
                    $parse['planet_field_current'] = $planetrow['field_current'];
                    $parse['planet_field_max'] = CalculateMaxPlanetFields($planetrow);
                    $parse['planet_temp_min'] = $planetrow['temp_min'];
                    $parse['planet_temp_max'] = $planetrow['temp_max'];
                    $parse['galaxy_galaxy'] = $planetrow['galaxy'];
                    $parse['galaxy_planet'] = $planetrow['planet'];
                    $parse['galaxy_system'] = $planetrow['system'];
                    $StatRecord = $this->stats->findUserStatRow((int) $user['id']);
                    if ($StatRecord === false) {
                        $StatRecord = array('build_points' => 0, 'fleet_points' => 0, 'tech_points' => 0, 'total_points' => 0, 'total_rank' => 0, 'total_old_rank' => 0);
                    }

                    $parse['user_points'] = pretty_number($StatRecord['build_points']);
                    $parse['user_fleet'] = pretty_number($StatRecord['fleet_points']);
                    $parse['player_points_tech'] = pretty_number($StatRecord['tech_points']);
                    $parse['total_points'] = pretty_number($StatRecord['total_points']);

                    $parse['user_rank'] = $StatRecord['total_rank'];
                    $ile = $StatRecord['total_old_rank'] - $StatRecord['total_rank'];
                    if ($ile >= 1) {
                        $parse['ile'] = "<span class=\"text-success\">+" . $ile . "</span>";
                    } elseif ($ile < 0) {
                        $parse['ile'] = "<span class=\"text-danger\">" . $ile . "</span>";
                    } elseif ($ile == 0) {
                        $parse['ile'] = "<span class=\"text-body-secondary\">0</span>";
                    }
                    $parse['u_user_rank'] = $StatRecord['total_rank'];
                    $parse['user_username'] = $user['username'];

                    $parse['energy_used'] = $planetrow["energy_max"] - $planetrow["energy_used"];

                    $parse['Have_new_message'] = $Have_new_message;
                    $parse['time'] = "<div id=\"dateheure\"></div>";
                    $parse['dpath'] = $dpath;
                    $parse['planet_image'] = $planetrow['image'];
                    $parse['anothers_planets'] = $AllPlanets;
                    $parse['max_users'] = $gameConfig['users_amount'];

                    $parse['metal_debris'] = pretty_number($galaxyrow['metal']);
                    $parse['crystal_debris'] = pretty_number($galaxyrow['crystal']);
                    // Champs de débris ajoutés par un module (le deutérium des
                    // extracteurs) : le Coeur d'application affiche ce que le jeu déclare, à la suite
                    // du métal et du cristal — et rien du tout sans module.
                    $DebrisExtra = '';
                    foreach ((new ModuleService())->debrisFields() as $DebrisColumn => $DebrisLabel) {
                        if ((int) ($galaxyrow[$DebrisColumn] ?? 0) === 0) {
                            continue;
                        }

                        $DebrisExtra .= ' / ' . (string) ($lang[$DebrisLabel] ?? $DebrisLabel)
                            . ' : ' . pretty_number((float) $galaxyrow[$DebrisColumn]);
                    }
                    $parse['debris_extra'] = $DebrisExtra;
                    if (($galaxyrow['metal'] != 0 || $galaxyrow['crystal'] != 0) && $planetrow[$resource[209]] != 0) {
                        $parse['get_link'] = " (<a href=\"/game/fleet/quickfleet?mode=8&g=" . $galaxyrow['galaxy'] . "&s=" . $galaxyrow['system'] . "&p=" . $galaxyrow['planet'] . "&t=2\">" . $lang['type_mission'][8] . "</a>)";
                    } else {
                        $parse['get_link'] = '';
                    }

                    if ($planetrow['b_building'] != 0) {
                        UpdatePlanetBatimentQueueList($planetrow, $user);
                        if ($planetrow['b_building'] != 0) {
                            $BuildQueue = explode(";", $planetrow['b_building_id']);
                            $CurrBuild = explode(",", $BuildQueue[0]);
                            $RestTime = $planetrow['b_building'] - time();
                            $PlanetID = $planetrow['id'];
                            $Build = InsertBuildListScript("overview");
                            $Build .= $lang['tech'][$CurrBuild[0]] . ' (' . ($CurrBuild[1]) . ')';
                            $Build .= "<br /><div id=\"blc\" class=\"z\">" . pretty_time($RestTime) . "</div>";
                            $Build .= "\n<script language=\"JavaScript\">";
                            $Build .= "\n	pp = \"" . $RestTime . "\";\n";
                            $Build .= "\n	pk = \"" . 1 . "\";\n";
                            $Build .= "\n	pm = \"cancel\";\n";
                            $Build .= "\n	pl = \"" . $PlanetID . "\";\n";
                            $Build .= "\n	t();\n";
                            $Build .= "\n</script>\n";

                            $parse['building'] = $Build;
                        } else {
                            $parse['building'] = $lang['Free'];
                        }
                    } else {
                        $parse['building'] = $lang['Free'];
                    }
                    $query = $this->users->findLastRegistered();
                    $parse['last_user'] = $query['username'];
                    $query = $this->users->countOnlineSince(time() - 900);
                    $parse['online_users'] = $query['online'] ?? 0;
                    $parse['users_amount'] = $gameConfig['users_amount'];
                    $FieldsMax = max(1, CalculateMaxPlanetFields($planetrow));
                    $FieldsPourcent = (int) floor($planetrow["field_current"] / $FieldsMax * 100);
                    $parse['case_pourcentage'] = $FieldsPourcent . $lang['o/o'];
                    $parse['case_barre_pourcent'] = min(100, $FieldsPourcent);
                    $parse['case_barre'] = $FieldsPourcent * 4.0;
                    if ($parse['case_barre'] > (100 * 4.0)) {
                        $parse['case_barre'] = 400;
                        $parse['case_barre_barcolor'] = '#C00000';
                    } elseif ($parse['case_barre'] > (80 * 4.0)) {
                        $parse['case_barre_barcolor'] = '#C0C000';
                    } else {
                        $parse['case_barre_barcolor'] = '#00C000';
                    }
                    // Le bloc de progression (niveaux et expérience du joueur) appartient au
                    // module `officier` : il rend ses alertes et ses lignes lui-même, et le Coeur
                    // de l'application ne fait que les placer.
                    $progression = $this->progression($user, $lang);
                    $parse['progression_alerts'] = $progression['alerts'];
                    $parse['progression_block'] = $progression['block'];
                    $parse['Raids'] = $lang['Raids'];
                    $parse['NumberOfRaids'] = $lang['NumberOfRaids'];
                    $parse['RaidsWin'] = $lang['RaidsWin'];
                    $parse['RaidsLoose'] = $lang['RaidsLoose'];

                    $parse['raids'] = $user['raids'];
                    $parse['raidswin'] = $user['raidswin'];
                    $parse['raidsloose'] = $user['raidsloose'];
                    $OnlineUsers = $this->users->countOnlineSinceInclusive(time() - 15 * 60);
                    $parse['NumberMembersOnline'] = $OnlineUsers['online'] ?? 0;

                    $page = $this->parse($this->template('overview_body'), $parse);

                    return $this->renderPage($page, $lang['Overview'] ?? 'Overview');
                }
        }

        return Response::html('');
    }

    /**
     * Bloc de progression de la vue générale : alertes de montée de niveau, et lignes du tableau.
     *
     * Le Coeur de l'application n'en rend **rien** : cette progression appartient au module
     * `officier`, dont le contrôleur surcharge cette page (même nom court, même couche :
     * `modules/officier/controllers/OverviewController.php`). Le module rend son propre balisage —
     * ses gabarits et ses libellés voyagent avec lui — et le Coeur de l'application ne garde que les
     * deux emplacements du gabarit : les alertes (`{progression_alerts}`) et les lignes du tableau
     * (`{progression_block}`). Sans module, l'un et l'autre restent vides : la page est exactement
     * celle du jeu.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $lang
     * @return array{alerts: string, block: string}
     */
    protected function progression(array $user, array $lang): array
    {
        return array('alerts' => '', 'block' => '');
    }
}
