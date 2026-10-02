<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AksRepository;
use App\Repositories\FleetRepository;
use App\Repositories\GalaxyRepository;
use App\Repositories\PlanetRepository;

final class AcsController extends AbstractController
{
    public function __construct(
        private readonly FleetRepository $fleets = new FleetRepository(),
        private readonly AksRepository $aksRepository = new AksRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly GalaxyRepository $galaxies = new GalaxyRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('fleet');

        $lang = $this->lang();
        $user = $this->user();
        $resource = $this->resource();
        $reslist = $this->resList();
        $pricelist = $GLOBALS['pricelist'] ?? [];

        $fleetid = $_POST['fleetid'] ?? null;

        if (!is_numeric($fleetid) || empty($fleetid)) {
            header("Location: /game/overview");
            exit();
        }

        $fleetCount = $this->fleets->countFleetId((int) $fleetid);

        if ($fleetCount != 1) {
            return $this->renderMessage('Cette flotte n\'existe pas (ou plus)!', 'Erreur');
        }

        $daten = $this->fleets->findListByFleetId((int) $fleetid);

        if ($daten['fleet_start_time'] <= time() || $daten['fleet_end_time'] < time() || $daten['fleet_mess'] == 1) {
            return $this->renderMessage('Votre flotte est dï¿½jï¿½ sur le chemin du retour!', 'Erreur');
        }

        if (!isset($_POST['send'])) {
            SetSelectedPlanet($user);

            $planetrow = $this->planets->findCurrentById((int) $user['current_planet']);
            $galaxyrow = $this->galaxies->findByPlanetId((int) $planetrow['id']);
            $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];
            $maxfleet = $this->fleets->countByOwnerWithQuantity((int) $user['id']);
            $maxfleet_count = $maxfleet["fleet_count"];

            CheckPlanetUsedFields($planetrow);

            $fleet = $this->fleets->findListByFleetId((int) $fleetid);

            if (empty($fleet['fleet_group'])) {
                $rand = mt_rand(100000, 999999999);

                $this->aksRepository->insert($rand, (int) $user['id'], (int) $fleetid, (int) $fleet['fleet_start_time'], (int) $fleet['fleet_start_galaxy'], (int) $fleet['fleet_start_system'], (int) $fleet['fleet_start_planet']);

                $aks = $this->aksRepository->findCreated($rand, (int) $user['id'], (int) $fleetid, (int) $fleet['fleet_start_time'], (int) $fleet['fleet_start_galaxy'], (int) $fleet['fleet_start_system'], (int) $fleet['fleet_start_planet']);

                $this->fleets->updateFleetGroup((int) $fleetid, (int) $aks['id']);
            } else {
                if ($this->aksRepository->findById((int) $fleet['fleet_group']) === false) {
                    return $this->renderMessage('AKS nicht gefunden!', 'Fehler');
                }
            }

            $missiontype = array(1 => 'Attaquer',
                2 => 'Zerst&ouml;ren',
                3 => 'Transporter',
                4 => 'Stationner',
                5 => 'Halten',
                6 => 'Espionner',
                7 => 'Coloniser',
                8 => 'Recycler',
                9 => 'Coloniser',
            );

            $speed = array(10 => 100,
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

            $galaxy = null;
            $system = null;
            $planet = null;
            $planettype = null;
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
            $ile = '' . ++$user[$resource[108]] . '';
            $page = '<script language="JavaScript" src="scripts/fleet.js"></script>
<script language="JavaScript" src="scripts/ocnt.js"></script>
  <center>
    <table width="519" border="0" cellpadding="0" cellspacing="1">
      <tr height="20">
        <td colspan="9" class="c">Flotte (max. ' . $ile . ')</td>
      </tr>
      <tr height="20">
        <th>ID</th>
        <th>Mission</th>
        <th> Nombre</th>
	<!--<th>Absendezeit</th>-->
        <th>Depart</th>
        <th>Arriv&eacute;e (cible)</th>
        <th>Objectif</th>
        <th>Arriv&eacute;e (retour)</th>
        <th>Retour ï¿½</th>
        <th>Ordre</th>
      </tr>';

            $fq = $this->fleets->findByOwnerRaw((int) $user['id']);

            $i = 0;
            foreach ($fq as $f) {
                $i++;

                $page .= "<tr height=20><th>$i</th><th>";

                $page .= "<a title=\"\">{$missiontype[$f['fleet_mission']]}</a>";
                if (($f['fleet_start_time'] + 1) == $f['fleet_end_time']) {
                    $page .= " <a title=\"R&uuml;ckweg\">(F)</a>";
                }
                $page .= "</th><th><a title=\"";
                $fleetArray = explode(";", $f['fleet_array']);
                $e = 0;
                foreach ($fleetArray as $a => $b) {
                    if ($b != '') {
                        $e++;
                        $a = explode(",", $b);
                        $page .= $lang['tech'][$a[0]] . ": {$a[1]}\n";
                        if ($e > 1) {
                            $page .= "\t";
                        }
                    }
                }
                $page .= "\">" . pretty_number($f['fleet_amount']) . "</a></th>";
                $page .= "<th>[{$f['fleet_start_galaxy']}:{$f['fleet_start_system']}:{$f['fleet_start_planet']}]</th>";
                $page .= "<th>" . gmdate("d. M Y H:i:s", $f['fleet_start_time']) . "</th>";
                $page .= "<th>[{$f['fleet_end_galaxy']}:{$f['fleet_end_system']}:{$f['fleet_end_planet']}]</th>";
                $page .= "<th>" . gmdate("d. M Y H:i:s", $f['fleet_end_time']) . "</th>";
                $page .= " </form>";

                $page .= "<th><font color=\"lime\"><div id=\"time_0\"><font>" . pretty_time(floor($f['fleet_end_time'] + 1 - time())) . "</font></th><th>";

                if ($f['fleet_mess'] == 0) {
                    $page .= "     <form action=\"/game/fleet/back\" method=\"post\">
      <input name=\"zawracanie\" value=" . $f['fleet_id'] . " type=hidden>
         <input value=\" Retour \" type=\"submit\">
       </form></th>";
                } else {
                    $page .= "&nbsp;</th>";
                }

                $page .= "</div></font>
            </tr>";
            }

            if ($i == 0) {
                $page .= "<th>-</th><th>-</th><th>-</th><th>-</th><th>-</th><th>-</th><th>-</th><th>-</th><th>-</th>";
            }
            if ($ile == $maxfleet_count) {
                $maxflot = '<tr height="20"><th colspan="9"><font color="red">Nombre de slot de flotte maximum atteint!</font></th></tr>';
            }
            $page .= '
		' . ($maxflot ?? null) . '</table>
	  </center>
    <table width="519" border="0" cellpadding="0" cellspacing="1">
   <tr height="20">
     <td class="c" colspan="2">	Association de flotte KV50502025</td>
   </tr>

   <form action="/game/acs" method="POST">
   <input type="hidden" name="fleet_id value="' . $fleetid . '" />
   <input type="hidden" name="changename" value="49021" />
   <tr height="20">

  <td class="c" colspan="2">VModifiez le nom de l\'Association</td>
   </tr>
   <tr>
    <th colspan="2"><input name="groupname" value="KV50502025" /> <br /> <input type="submit" value="OK" /></th>
   </tr>
   </form>

   <tr>
    <th>
     <table width="100%" border="0" cellpadding="0" cellspacing="1">
      <tr height="20">
       <td class="c">Invitï¿½s participants</td>
       <td class="c">Inviter des participants</td>
      </tr>
      <tr>

       <th width="50%">
        <select size="5">
                   <option>Thunder Storm</option>
                 </select>
        </th>

	    <form action="index.php?page=flotten1&session=388eb542c1d0" method="POST">
	<input type="hidden" name="order_union" value="49021" />
       <input type="hidden" name="adduser" value="49021" />

       <td><input name="addtogroup" /> <br /><input type="submit" value="OK" /></td>
    </form>
             </tr>
     </table>
    </th>
   </tr>
   <tr>

   </tr>

  </table>
	  <center>
		<form action="/game/fleet/floten1" method="post">
		<table width="519" border="0" cellpadding="0" cellspacing="1">
		  <tr height="20">
			<td colspan="4" class="c">Nouveau marchï¿½: Choix de la flotte</td>
		  </tr>
		  <tr height="20">
			<th>Nom du vaisseau</th>
			<th>Nombre</th>';
            $page .= '
			<th>-</th>
			<th>-</th>
		  </tr>';
            if (!$planetrow) {
                return $this->renderMessage('WTF! FEHLER!', 'ERROR');
            }
            $galaxy = intval($_GET['galaxy'] ?? 0);
            $system = intval($_GET['system'] ?? 0);
            $planet = intval($_GET['planet'] ?? 0);
            $planettype = intval($_GET['planettype'] ?? 0);
            $target_mission = intval($_GET['target_mission'] ?? 0);

            foreach ($reslist['fleet'] as $n => $i) {
                if ($planetrow[$resource[$i]] > 0) {
                    if ($i == 202 or $i == 203 or $i == 204 or $i == 209 or $i == 210) {
                        $pricelist[$i]['speed'] = $pricelist[$i]['speed'] + (($pricelist[$i]['speed'] * $user['combustion_tech']) * 0.1);
                    }
                    if ($i == 205 or $i == 206 or $i == 208 or $i == 211) {
                        $pricelist[$i]['speed'] = $pricelist[$i]['speed'] + (($pricelist[$i]['speed'] * $user['impulse_motor_tech']) * 0.2);
                    }
                    if ($i == 207 or $i == 213 or $i == 214 or $i == 215 or $i == 216) {
                        $pricelist[$i]['speed'] = $pricelist[$i]['speed'] + (($pricelist[$i]['speed'] * $user['hyperspace_motor_tech']) * 0.3);
                    }
                    $page .= '<tr height="20">
			<th><a title="Geschwindigkeit: ' . $pricelist[$i]['speed'] . '">' . $lang['tech'][$i] . '</a></th>
			<th>' . pretty_number($planetrow[$resource[$i]]) . '
			  <input type="hidden" name="maxship' . $i . '" value="' . $planetrow[$resource[$i]] . '"/></th>

			<input type="hidden" name="consumption' . $i . '" value="' . $pricelist[$i]['consumption'] . '"/>

			<input type="hidden" name="speed' . $i . '" value="' . $pricelist[$i]['speed'] . '" />
			<input type="hidden" name="galaxy" value="' . $galaxy . '"/>

			<input type="hidden" name="system" value="' . $system . '"/>
			<input type="hidden" name="planet" value="' . $planet . '"/>
			<input type="hidden" name="planet_type" value="' . $planettype . '"/>
			<input type="hidden" name="mission" value="' . $target_mission . '"/>
			</th>
			<input type="hidden" name="capacity' . $i . '" value="' . $pricelist[$i]['capacity'] . '" />
			</th>';
                    if ($i == 212) {
                        $page .= '<th></th><th></th></tr>';
                    } else {
                        $page .= '<th><a href="javascript:maxShip(\'ship' . $i . '\'); shortInfo();">max</a> </th>
				<th><input name="ship' . $i . '" size="10" value="0" onfocus="javascript:if(this.value == \'0\') this.value=\'\';" onblur="javascript:if(this.value == \'\') this.value=\'0\';" alt="' . $lang['tech'][$i] . $planetrow[$resource[$i]] . '"  onChange="shortInfo()" onKeyUp="shortInfo()"/></th>
				</tr>';
                        $aaaaaaa = $pricelist[$i]['consumption'];
                    }
                    $have_ships = true;
                }
            }

            if (!($have_ships ?? null)) {
                $page .= '<tr height="20">
		<th colspan="4">Aucun vaisseau</th>
		</tr>
		<tr height="20">
		<th colspan="4">
		<input type="button" value="OK" enabled/></th>
		</tr>
		</table>
		</center>
		</form>';
            } else {
                $page .= '
		  <tr height="20">
			<th colspan="2"><a href="javascript:noShips();shortInfo();noResources();" >Aucun Vaisseaux</a></th>
			<th colspan="2"><a href="javascript:maxShips();shortInfo();" >Tout les vaisseaux</a></th>
		  </tr>';

                $przydalej = '<tr height="20"><th colspan="4"><input type="submit" value="OK" /></th></tr>';
                if ($ile == $maxfleet_count) {
                    $przydalej = '';
                }
                $page .= '
		' . $przydalej . '
		<tr><th colspan="4">
		<br><center></center><br>
		</th></tr>
		</table>
	  </center>
	</form>';
            }
        }

        return $this->renderPage($page, "Flotten");
    }
}
