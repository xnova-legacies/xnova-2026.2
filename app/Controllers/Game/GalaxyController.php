<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\FleetRepository;
use App\Repositories\GalaxyRepository;
use App\Repositories\PlanetRepository;

final class GalaxyController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly FleetRepository $fleets = new FleetRepository(),
        private readonly GalaxyRepository $galaxies = new GalaxyRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('galaxy');

        $lang = $this->lang();
        $user = $this->user();
        $resource = $this->resource();

        $CurrentPlanet = $this->planets->findCurrentById((int) $user['current_planet']);
        $lunarow = $this->planets->fetchOneLuna((int) ($user['current_luna'] ?? 0));
        $galaxyrow = $this->galaxies->findByPlanetId((int) $CurrentPlanet['id']);

        $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];
        $fleetmax = $user['computer_tech'] + 1;
        $CurrentPlID = $CurrentPlanet['id'];
        $CurrentMIP = $CurrentPlanet['interplanetary_misil'];
        $CurrentRC = $CurrentPlanet['recycler'];
        $CurrentSP = $CurrentPlanet['spy_sonde'];
        $HavePhalanx = $CurrentPlanet['phalanx'];
        $CurrentSystem = $CurrentPlanet['system'];
        $CurrentGalaxy = $CurrentPlanet['galaxy'];
        $CanDestroy = $CurrentPlanet[$resource[213]] + $CurrentPlanet[$resource[214]];

        $maxfleet_count = count($this->fleets->findByOwner((int) $user['id']));

        $GLOBALS['maxfleet_count'] = $maxfleet_count;
        $GLOBALS['fleetmax'] = $fleetmax;
        $GLOBALS['planetcount'] = $planetcount;
        $GLOBALS['lunacount'] = $lunacount;
        $GLOBALS['CurrentPlID'] = $CurrentPlID;
        $GLOBALS['CurrentMIP'] = $CurrentMIP;
        $GLOBALS['CurrentRC'] = $CurrentRC;
        $GLOBALS['CurrentSP'] = $CurrentSP;
        $GLOBALS['HavePhalanx'] = $HavePhalanx;
        $GLOBALS['CurrentSystem'] = $CurrentSystem;
        $GLOBALS['CurrentGalaxy'] = $CurrentGalaxy;
        $GLOBALS['CanDestroy'] = $CanDestroy;

        CheckPlanetUsedFields($CurrentPlanet);
        CheckPlanetUsedFields($lunarow);

        $mode = null;
        if (!isset($mode)) {
            if (isset($_GET['mode'])) {
                $mode = intval($_GET['mode']);
            } else {
                $mode = 0;
            }
        }

        $galaxy = null;
        $system = null;
        $planet = null;

        if ($mode == 0) {
            $galaxy = $CurrentPlanet['galaxy'];
            $system = $CurrentPlanet['system'];
            $planet = $CurrentPlanet['planet'];
        } elseif ($mode == 1) {
            $postedGalaxy = (int) ($_POST["galaxy"] ?? 0);
            $postedSystem = (int) ($_POST["system"] ?? 0);

            if (($_POST["galaxyLeft"] ?? null)) {
                if ($postedGalaxy < 1) {
                    $_POST["galaxy"] = 1;
                    $galaxy = 1;
                } elseif ($postedGalaxy == 1) {
                    $_POST["galaxy"] = 1;
                    $galaxy = 1;
                } else {
                    $galaxy = $postedGalaxy - 1;
                }
            } elseif (($_POST["galaxyRight"] ?? null)) {
                if ($postedGalaxy > MAX_GALAXY_IN_WORLD) {
                    $_POST["galaxy"] = MAX_GALAXY_IN_WORLD;
                    $galaxy = MAX_GALAXY_IN_WORLD;
                } elseif ($postedGalaxy == MAX_GALAXY_IN_WORLD) {
                    $_POST["galaxy"] = MAX_GALAXY_IN_WORLD;
                    $galaxy = MAX_GALAXY_IN_WORLD;
                } else {
                    $galaxy = max(1, $postedGalaxy) + 1;
                }
            } else {
                $galaxy = $postedGalaxy;
            }

            if (($_POST["systemLeft"] ?? null)) {
                if ($postedSystem < 1) {
                    $_POST["system"] = 1;
                    $system = 1;
                } elseif ($postedSystem == 1) {
                    $_POST["system"] = 1;
                    $system = 1;
                } else {
                    $system = $postedSystem - 1;
                }
            } elseif (($_POST["systemRight"] ?? null)) {
                if ($postedSystem > MAX_SYSTEM_IN_GALAXY) {
                    $_POST["system"] = MAX_SYSTEM_IN_GALAXY;
                    $system = MAX_SYSTEM_IN_GALAXY;
                } elseif ($postedSystem == MAX_SYSTEM_IN_GALAXY) {
                    $_POST["system"] = MAX_SYSTEM_IN_GALAXY;
                    $system = MAX_SYSTEM_IN_GALAXY;
                } else {
                    $system = max(1, $postedSystem) + 1;
                }
            } else {
                $system = $postedSystem;
            }
        } elseif ($mode == 2) {
            $galaxy = $_GET['galaxy'] ?? null;
            $system = $_GET['system'] ?? null;
            $planet = $_GET['planet'] ?? null;
        } elseif ($mode == 3) {
            $galaxy = $_GET['galaxy'] ?? null;
            $system = $_GET['system'] ?? null;
        } else {
            $galaxy = 1;
            $system = 1;
        }

        $planetcount = 0;
        $lunacount = 0;

        // Le balisage de la page vit dans galaxy_table.tpl : les fonctions legacy
        // (en-têtes, lignes, sélecteurs) fournissent ici leur contenu.
        $mISelector = '';

        if ($mode == 2) {
            $CurrentPlanetID = $_GET['current'] ?? null;
            $mISelector = ShowGalaxyMISelector($galaxy, $system, $planet, $CurrentPlanetID, $CurrentMIP);
        }

        $page = $this->partial('galaxy_table', array(
            'galaxy_titles' => ShowGalaxyTitles($galaxy, $system),
            'galaxy_rows' => ShowGalaxyRows($galaxy, $system, $CurrentPlanet),
            'galaxy_footer' => ShowGalaxyFooter($galaxy, $system, $CurrentMIP, $CurrentRC, $CurrentSP),
        ));

        $page = InsertGalaxyScripts($CurrentPlanet) . ShowGalaxySelector($galaxy, $system) . $mISelector . $page;

        return $this->renderPage($page, $lang['Galaxy'], true, '', false);
    }
}
