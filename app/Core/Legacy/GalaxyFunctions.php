<?php

/**
 * Fonctions legacy : SpyTarget, RestoreFleetToPlanet, InsertGalaxyScripts, GalaxyCheckFunctions, ShowGalaxyRows, GetPhalanxRange, GetMissileRange, GalaxyRowPos, GalaxyRowPlanet, GalaxyRowPlanetName, GalaxyRowMoon, GalaxyRowDebris, GalaxyRowUser, GalaxyRowAlly, GalaxyRowActions, ShowGalaxySelector, ShowGalaxyMISelector, ShowGalaxyTitles, GalaxyLegendPopup, ShowGalaxyFooter
 */

// ===== SpyTarget =====
/**
 * Relevé d'espionnage d'une catégorie.
 *
 * Les règles restent ici (quelles plages d'éléments sont parcourues, et le total
 * des quantités vues, qui sert au calcul du niveau d'information) ; la mise en
 * forme est déléguée à App\Core\SpyReport et à ses gabarits, comme pour le
 * rapport de combat. La réponse ne change pas :
 * `['String' => HTML, 'Count' => total]`.
 */
function SpyTarget($TargetPlanet, $Mode, $TitleString)
{
	global $lang;

	if ($Mode == 0) {
		return array(
			'String' => \App\Core\SpyReport::resources($TargetPlanet, $TitleString, $lang),
			'Count'  => 0,
		);
	}

	// Plages d'éléments révélés, par catégorie (flotte, défenses, bâtiments, technos).
	$ranges = array(
		1 => array(array(200, 299)),
		2 => array(array(400, 499), array(500, 599)),
		3 => array(array(1, 99)),
		4 => array(array(100, 199)),
	);

	if (!isset($ranges[$Mode])) {
		return array('String' => '', 'Count' => 0);
	}

	$Found = \App\Core\SpyReport::rows($TargetPlanet, $ranges[$Mode], $lang['tech'] ?? array());

	return array(
		'String' => \App\Core\SpyReport::section($TitleString, $Found['rows']),
		'Count'  => $Found['count'],
	);
}

// ===== RestoreFleetToPlanet =====
function RestoreFleetToPlanet($FleetRow, $Start = true)
{
	global $resource;

	// Les vaisseaux du vol et ce qu'il transporte : deux rendus de colonnes, jamais du SQL.
	// La colonne d'un vaisseau vient du tableau des ressources du jeu (`$resource`).
	$Ships     = array();
	$Resources = array();

	$FleetRecord = explode(';', $FleetRow['fleet_array']);

	foreach ($FleetRecord as $Item => $Group) {
		if ($Group != '') {
			$Class = explode(',', $Group);

			if (isset($resource[$Class[0]])) {
				$Ships[$resource[$Class[0]]] = (int) $Class[1];
			}
		}
	}

	$Resources['metal']     = $FleetRow['fleet_resource_metal'];
	$Resources['crystal']   = $FleetRow['fleet_resource_crystal'];
	$Resources['deuterium'] = $FleetRow['fleet_resource_deuterium'];

	if ($Start == true) {
		$Galaxy = (int) $FleetRow['fleet_start_galaxy'];
		$System = (int) $FleetRow['fleet_start_system'];
		$Planet = (int) $FleetRow['fleet_start_planet'];
		$Type   = (int) $FleetRow['fleet_start_type'];
	} else {
		$Galaxy = (int) $FleetRow['fleet_end_galaxy'];
		$System = (int) $FleetRow['fleet_end_system'];
		$Planet = (int) $FleetRow['fleet_end_planet'];
		$Type   = (int) $FleetRow['fleet_end_type'];
	}

	(new \App\Repositories\PlanetRepository())->addFleetCargoAtPosition(
		$Galaxy,
		$System,
		$Planet,
		$Type,
		$Ships,
		$Resources
	);
}

//

// ===== InsertGalaxyScripts =====
function InsertGalaxyScripts($CurrentPlanet)
{
	global $lang, $resource;
	$Script  = "<div class=\"xnova-galaxy\">";
	$Script .= "<script language=\"JavaScript\">\n";
	$Script .= "function galaxy_submit(value) {\n";
	$Script .= "	document.getElementById('auto').name = value;\n";
	$Script .= "	document.getElementById('galaxy_form').submit();\n";
	$Script .= "}\n\n";

	$Script .= "function fenster(target_url,win_name) {\n";
	$Script .= "	var new_win = window.open(target_url,win_name,'resizable=yes,scrollbars=yes,menubar=no,toolbar=no,width=640,height=480,top=0,left=0');\n";
	$Script .= "	new_win.focus();\n";
	$Script .= "}\n";
	$Script .= "</script>\n";

	$Script .= "<script language=\"JavaScript\" src=\"/scripts/tw-sack.js\"></script>\n";

	$Script .= "<script type=\"text/javascript\">\n\n";
	$Script .= "var ajax = new sack();\n";
	$Script .= "var strInfo = \"\";\n";
	// Vaisseaux à engager pour les missions de champ de débris déclarées par un
	// module (l'extraction des extracteurs) : mission => vaisseau => ce que le
	// joueur possède sur sa planète. La quantité demandée est portée par le lien
	// (comme pour le recyclage) : cette table n'est que le repli quand l'appelant
	// n'en donne pas. Le Coeur d'application l'écrit depuis les manifestes, sans nommer aucun module.
	$DebrisShips = array();
	foreach (\App\Services\FleetDispatchService::galaxyDebrisMissions() as $DebrisMission => $DeclaredShips) {
		$Owned = array();
		foreach ($DeclaredShips as $DeclaredShip) {
			$OwnedShip = (int) ($CurrentPlanet[$resource[$DeclaredShip] ?? ''] ?? 0);
			if ($OwnedShip > 0) {
				$Owned[(string) $DeclaredShip] = $OwnedShip;
			}
		}
		if ($Owned !== array()) {
			$DebrisShips[(string) $DebrisMission] = $Owned;
		}
	}
	$Script .= "var galaxyMissionShips = " . json_encode($DebrisShips) . ";\n";

	$Script .= "function whenResponse () {\n";
	$Script .= "	retVals   = this.response.split(\"|\");\n";
	$Script .= "	Message   = retVals[0];\n";
	$Script .= "	Infos     = retVals[1];\n";
	$Script .= "	retVals   = Infos.split(\" \");\n";
	$Script .= "	UsedSlots = retVals[0];\n";
	$Script .= "	SpyProbes = retVals[1];\n";
	$Script .= "	Recyclers = retVals[2];\n";
	$Script .= "	Missiles  = retVals[3];\n";
	$Script .= "	retVals   = Message.split(\";\");\n";
	$Script .= "	CmdCode   = retVals[0];\n";
	$Script .= "	strInfo   = retVals[1];\n";
	$Script .= "	addToTable(strInfo, CmdCode >= 600 ? \"error\" : \"success\");\n";
	$Script .= "	notifyFleetResult(CmdCode, strInfo);\n";
	$Script .= "	changeSlots( UsedSlots );\n";
	$Script .= "	setShips(\"probes\", SpyProbes );\n";
	$Script .= "	setShips(\"recyclers\", Recyclers );\n";
	$Script .= "	setShips(\"missiles\", Missiles );\n";
	$Script .= "}\n\n";

	// Le retour du raccourci doit être **visible depuis la galaxie** : le tableau de
	// l'état des flottes est en pied de page, et la bulle `XNova.notify` du Coeur d'application
	// ajax le rend impossible à manquer. 600 = envoi accepté, les 6xx sont les refus.
	$Script .= "function notifyFleetResult(code, text) {\n";
	$Script .= "\tif (!window.XNova || !window.XNova.notify) { return; }\n";
	$Script .= "\twindow.XNova.notify(code >= 600 ? \"error\" : \"success\", text);\n";
	$Script .= "}\n\n";

	$Script .= "function doit (order, galaxy, system, planet, planettype, shipcount) {\n";
	$Script .= "	ajax.requestFile = \"/game/fleet/flotenajax?action=send\";\n";
	$Script .= "	ajax.runResponse = whenResponse;\n";
	$Script .= "	ajax.execute = true;\n\n";
	$Script .= "	ajax.setVar(\"thisgalaxy\", " . $CurrentPlanet["galaxy"] . ");\n";
	$Script .= "	ajax.setVar(\"thissystem\", " . $CurrentPlanet["system"] . ");\n";
	$Script .= "	ajax.setVar(\"thisplanet\", " . $CurrentPlanet["planet"] . ");\n";
	$Script .= "	ajax.setVar(\"thisplanettype\", " . $CurrentPlanet["planet_type"] . ");\n";
	$Script .= "	ajax.setVar(\"mission\", order);\n";
	$Script .= "	ajax.setVar(\"galaxy\", galaxy);\n";
	$Script .= "	ajax.setVar(\"system\", system);\n";
	$Script .= "	ajax.setVar(\"planet\", planet);\n";
	$Script .= "	ajax.setVar(\"planettype\", planettype);\n";
	$Script .= "	if (order == 6)\n";
	$Script .= "		ajax.setVar(\"ship210\", shipcount);\n";
	$Script .= "	if (order == 7) {\n";
	$Script .= "		ajax.setVar(\"ship208\", 1);\n\n";
	$Script .= "		ajax.setVar(\"ship203\", 2);\n\n";
	$Script .= "	}\n";
	$Script .= "	if (order == 8)\n";
	$Script .= "		ajax.setVar(\"ship209\", shipcount);\n\n";
	// Missions de champ de débris déclarées par un module (extraction) : les
	// vaisseaux à engager viennent de la table construite plus haut.
	$Script .= "	var declared = galaxyMissionShips[order];\n";
	$Script .= "	if (declared) {\n";
	$Script .= "		for (var ship in declared) {\n";
	$Script .= "			if (declared.hasOwnProperty(ship)) { ajax.setVar(\"ship\" + ship, shipcount > 0 ? shipcount : declared[ship]); }\n";
	$Script .= "		}\n";
	$Script .= "	}\n";
	$Script .= "	ajax.runAJAX();\n";
	$Script .= "}\n\n";

	$Script .= "function addToTable(strDataResult, strClass) {\n";
	$Script .= "	var e = document.getElementById('fleetstatusrow');\n";
	$Script .= "	var e2 = document.getElementById('fleetstatustable');\n";
	$Script .= "	e.style.display = '';\n";
	$Script .= "	if(e2.rows.length > 2) {\n";
	$Script .= "		e2.deleteRow(2);\n";
	$Script .= "	}\n";
	$Script .= "	var row = e2.insertRow(0);\n";
	$Script .= "	var td1 = document.createElement(\"td\");\n";
	$Script .= "	var td1text = document.createTextNode(strInfo);\n";
	$Script .= "	td1.className = 'text-secondary';\n";
	$Script .= "	td1.appendChild(td1text);\n";
	$Script .= "	var td2 = document.createElement(\"td\");\n";
	$Script .= "	var span = document.createElement(\"span\");\n";
	$Script .= "	var spantext = document.createTextNode(strDataResult);\n";
	$Script .= "	var statusClasses = {\n";
	$Script .= "		error: 'text-danger',\n";
	$Script .= "		success: 'text-success',\n";
	$Script .= "		notice: 'text-warning',\n";
	$Script .= "		vacation: 'text-info'\n";
	$Script .= "	};\n";
	$Script .= "	var spanclass = document.createAttribute(\"class\");\n";
	$Script .= "	spanclass.nodeValue = 'fw-semibold ' + (statusClasses[strClass] || 'text-body-secondary');\n";
	$Script .= "	span.setAttributeNode(spanclass);\n";
	$Script .= "	span.appendChild(spantext);\n";
	$Script .= "	td2.appendChild(span);\n";
	$Script .= "	row.appendChild(td1);\n";
	$Script .= "	row.appendChild(td2);\n";
	$Script .= "}\n\n";

	$Script .= "function changeSlots(slotsInUse) {\n";
	$Script .= "	var e = document.getElementById('slots');\n";
	$Script .= "	if (!e) { return; }\n";
	$Script .= "	e.innerHTML = slotsInUse;\n";
	$Script .= "}\n\n";

	$Script .= "function setShips(ship, count) {\n";
	$Script .= "	var e = document.getElementById(ship);\n";
	$Script .= "	if (!e) { return; }\n";
	$Script .= "	e.innerHTML = count;\n";
	$Script .= "}\n";

	$Script .= "</script>\n";

	return $Script;
}

//

// ===== GalaxyCheckFunctions =====
function CheckAbandonMoonState($lunarow)
{
	if (($lunarow['destruyed'] + 172800) <= time() && $lunarow['destruyed'] != 0) {
		$repository = new \App\Repositories\GalaxyRepository();

		$repository->deleteMoon((int) $lunarow['id']);
		\App\Repositories\GalaxyRepository::forgetMoon((int) $lunarow['id']);
	}
}

// Suppression complete d'une planete
function CheckAbandonPlanetState(&$planet)
{
	// Les abandons commences par une ancienne version (marques d'une echeance, sans
	// drapeau) se terminent ici : on ne supprime plus la colonie, on la marque et on
	// detache son lien de la galaxie — la position devient libre, rien n'est perdu.
	// Le garde `destruyed != 0` est essentiel : une planet existante vaut 0, donc
	// `0 <= time()` est toujours vrai et toute l'univers partirait en abandon.
	if (
		(int) $planet['destruyed'] !== 0 && $planet['destruyed'] <= time()
		&& ((int) $planet['flags'] & \App\Core\Flags::DELETED) === 0
	) {
		$repository = new \App\Repositories\PlanetRepository();
		$repository->markPlanetAbandoned((int) $planet['id']);
		(new \App\Repositories\GalaxyRepository())->clearPlanetLink(
			(int) $planet['galaxy'],
			(int) $planet['system'],
			(int) $planet['planet']
		);
		// La ligne préchargée ne vaut plus rien : la vue la relit juste après.
		\App\Repositories\GalaxyRepository::forgetPlanet((int) $planet['id']);
	}
}

//

// ===== ShowGalaxyRows =====
function ShowGalaxyRows($Galaxy, $System, $CurrentPlanet = array())
{
	global $lang, $planetcount, $CurrentRC, $dpath, $user;

	$repository = new \App\Repositories\GalaxyRepository();

	// Une seule lecture par table pour tout le système : la vue interrogeait la base
	// position par position (une requête par ligne, plus la planète, son compte et
	// sa lune) — la même page demandait soixante-dix requêtes.
	// Les tables de données (dont celles qu'apporte un module : le vaisseau de
	// l'extracteur) sont recopiées dans les globales ici, avant de rendre la
	// moindre ligne : sans cela, une colonne apportée par un module peut manquer
	// au moment où la vue la lit, alors qu'elle est disponible un peu plus tard
	// dans la même page. L'appel est idempotent.
	\App\Core\GameData::load();

	$Positions = $repository->prefetchSystem((int) $Galaxy, (int) $System);

	(new \App\Repositories\StatsRepository())->prefetchCurrentPoints(
		array_merge($repository->prefetchedOwnerIds(), array((int) $user['id']))
	);

	// Les alliances se préchargent par le module, jamais en direct : sans lui la
	// colonne reste vide, et aucune classe du module n'est chargée.
	if ((new \App\Services\ModuleService())->available('alliance')) {
		(new \Modules\Alliance\Repositories\AllianceRepository())->prefetch($repository->prefetchedAllyIds());
	}

	// Colonies abandonnées récemment : leurs coordonnées sont réservées, la vue
	// l'annonce « Planète détruite » alors que le lien de la galaxie est déjà détaché.
	$ReservedPlanets = (new \App\Repositories\PlanetRepository())->reservedPositions(
		(int) $Galaxy,
		(int) $System,
		\App\Core\GameConstants::abandonedPositionDelay()
	);

	$Result = "";
	for ($Planet = 1; $Planet < 16; $Planet++) {
		$GalaxyRowPlanet = array('id' => 0, 'id_owner' => 0, 'name' => '', 'destruyed' => 0, 'galaxy' => 0, 'system' => 0, 'planet' => 0, 'planet_type' => 1, 'image' => '', 'field_current' => 0, 'field_max' => 0, 'temp_min' => 0, 'temp_max' => 0, 'metal' => 0, 'crystal' => 0, 'deuterium' => 0, 'energy_max' => 0, 'energy_used' => 0, 'metal_perhour' => 0, 'crystal_perhour' => 0, 'deuterium_perhour' => 0, 'b_building' => 0, 'b_building_id' => '', 'last_update' => 0, 'ally_id' => 0, 'ally_name' => '', 'ally_request' => 0, 'interplanetary_misil' => 0, 'interceptor_misil' => 0);
		$GalaxyRowMoon = array('id' => 0, 'id_owner' => 0, 'name' => '', 'destruyed' => 0, 'galaxy' => 0, 'system' => 0, 'planet' => 0, 'planet_type' => 3, 'temp_min' => 0, 'temp_max' => 0, 'field_current' => 0, 'field_max' => 0);
		$GalaxyRowPlayer = array('id' => 0, 'username' => '', 'ally_id' => 0, 'ally_name' => '', 'ally_request' => 0, 'onlinetime' => 0, 'vacation_mode' => 0, 'bana' => 0, 'banaday' => 0, 'current_planet' => 0, 'galaxy' => 0, 'system' => 0, 'planet' => 0, 'spy_tech' => 0, 'military_tech' => 0, 'defence_tech' => 0, 'authlevel' => 0, 'email' => 0, 'dpath' => '', 'raids' => 0, 'raidswin' => 0, 'raidsloose' => 0, 'new_message' => 0, 'planet_sort' => 0, 'planet_sort_order' => 0, 'fleet_shortcut' => '', 'spy_tech' => 0, 'military_tech' => 0, 'defence_tech' => 0, 'impulse_motor_tech' => 0, 'hyperspace_motor_tech' => 0, 'combustion_tech' => 0);
		$GalaxyRowAlly = array('id' => 0, 'ally_name' => '', 'ally_tag' => '', 'ally_web' => '', 'ally_description' => '', 'ally_members' => 0);

		$GalaxyRow = $Positions[$Planet] ?? false;

		$Result .= "\n";
		$Result .= "<tr>";
		if ($GalaxyRow) {
			if ($GalaxyRow["id_planet"] != 0) {
				$GalaxyRowPlanet = (array) ($repository->findPlanetById((int) $GalaxyRow["id_planet"]) ?: $GalaxyRowPlanet);

				if (
					$GalaxyRowPlanet['destruyed'] != 0 and
					$GalaxyRowPlanet['id_owner'] != '' and
					$GalaxyRow["id_planet"] != ''
				) {
					CheckAbandonPlanetState($GalaxyRowPlanet);
				} else {
					$planetcount++;
					$GalaxyRowPlayer = (array) ($repository->findUserById((int) $GalaxyRowPlanet["id_owner"]) ?: $GalaxyRowPlayer);
				}

				if ($GalaxyRow["id_luna"] != 0) {
					$GalaxyRowMoon = (array) ($repository->findMoonById((int) $GalaxyRow["id_luna"]) ?: $GalaxyRowMoon);
					if ($GalaxyRowMoon["destruyed"] != 0) {
						CheckAbandonMoonState($GalaxyRowMoon);
					}
				}
				// Relecture utile seulement après un abandon traité pendant le rendu :
				// la ligne a changé en base, elle a été oubliée pour être relue.
				$GalaxyRowPlanet = (array) ($repository->findPlanetById((int) $GalaxyRow["id_planet"]) ?: $GalaxyRowPlanet);
			}
		}

		// Colonie abandonnée récemment : la ligne `galaxy` ne l'annonce plus
		// (`id_planet` = 0) mais ses coordonnées restent réservées. On charge sa
		// ligne pour que la cellule du nom affiche « Planète détruite ». Le test
		// porte sur la **position libre**, pas sur l'absence de ligne : l'abandon
		// détache le lien sans supprimer la ligne.
		if ((!$GalaxyRow || (int) $GalaxyRow['id_planet'] === 0) && isset($ReservedPlanets[$Planet])) {
			$GalaxyRowPlanet = (array) $ReservedPlanets[$Planet];
		}
		$Result .= "\n";
		$Result .= GalaxyRowPos($Planet, $GalaxyRow);
		$Result .= "\n";
		$Result .= GalaxyRowPlanet($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowPlayer, $Galaxy, $System, $Planet, 1);
		$Result .= "\n";
		$Result .= GalaxyRowPlanetName($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowPlayer, $Galaxy, $System, $Planet, 1);
		$Result .= "\n";
		$Result .= GalaxyRowMoon($GalaxyRow, $GalaxyRowMoon, $GalaxyRowPlayer, $Galaxy, $System, $Planet, 3);
		$Result .= "\n";
		$Result .= GalaxyRowDebris($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowPlayer, $Galaxy, $System, $Planet, 2, $CurrentPlanet);
		$Result .= "\n";
		$Result .= GalaxyRowUser($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowPlayer, $Galaxy, $System, $Planet, 0);
		$Result .= "\n";
		$Result .= GalaxyRowAlly($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowPlayer, $Galaxy, $System, $Planet, 0);
		$Result .= "\n";
		$Result .= GalaxyRowActions($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowPlayer, $Galaxy, $System, $Planet, 0, $CurrentPlanet);
		$Result .= "\n";
		$Result .= "</tr>";
	}

	return $Result;
}

//

// ===== GetPhalanxRange =====
function GetPhalanxRange($PhalanxLevel)
{
	// Niveau                       1  2  3  4  5  6  7  = lvl
	// Portée ajouté                0  3  5  7  9 11 13  = (lvl * 2) - 1
	// Phalanx en nbre de systemes  0  3  8 15 24 35 48  =
	$PhalanxRange = 0;
	if ($PhalanxLevel > 1) {
		for ($Level = 2; $Level < $PhalanxLevel + 1; $Level++) {
			$lvl           = ($Level * 2) - 1;
			$PhalanxRange += $lvl;
		}
	}
	return $PhalanxRange;
}

//

// ===== GetMissileRange =====
function GetMissileRange()
{
	global $resource, $user;

	if ($user[$resource[117]] > 0) {
		$MissileRange = ($user[$resource[117]] * 5) - 1;
	} elseif ($user[$resource[117]] == 0) {
		$MissileRange = 0;
	}

	return $MissileRange;
}

//

// ===== GalaxyRowPos =====
function GalaxyRowPos($Planet, $GalaxyRow)
{
	// Pos
	$Result  = "<td class=\"text-center xnova-galaxy-pos\">";
	$Result .= "<a href=\"#\"";
	if ($GalaxyRow) {
		$Result .= " tabindex=\"" . ($Planet + 1) . "\"";
	}
	$Result .= ">" . $Planet . "</a>";
	$Result .= "</td>";

	return $Result;
}

//

// ===== GalaxyRowPlanet =====
function GalaxyRowPlanet($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowUser, $Galaxy, $System, $Planet, $PlanetType)
{
	global $lang, $dpath, $user, $HavePhalanx, $CurrentSystem, $CurrentGalaxy;

	// Planete (Image)
	$Result  = "<td class=\"text-center\">";

	$GalaxyRowUser = (new \App\Repositories\GalaxyRepository())->findUserById((int) $GalaxyRowPlanet['id_owner']);
	if ($GalaxyRow && $GalaxyRowPlanet['id'] != 0 && $GalaxyRowPlanet["destruyed"] == 0 && $GalaxyRow["id_planet"] != 0) {
		if ($HavePhalanx <> 0) {
			if ($GalaxyRowUser['id'] != $user['id']) {
				if ($GalaxyRowPlanet["galaxy"] == $CurrentGalaxy) {
					$Range = GetPhalanxRange($HavePhalanx);
					if ($SystemLimitMin < 1) {
						$SystemLimitMin = 1;
					}
					$SystemLimitMax = $CurrentSystem + $Range;
					if ($System <= $SystemLimitMax) {
						if ($System >= $SystemLimitMin) {
							$PhalanxTypeLink = "<a href=# onclick=fenster(&#039;/game/phalanx?galaxy=" . $Galaxy . "&amp;system=" . $System . "&amp;planet=" . $Planet . "&amp;planettype=" . $PlanetType . "&#039;) >" . $lang['gl_phalanx'] . "</a><br />";
						} else {
							$PhalanxTypeLink = "";
						}
					} else {
						$PhalanxTypeLink = "";
					}
				} else {
					$PhalanxTypeLink = "";
				}
			} else {
				$PhalanxTypeLink = "";
			}
		} else {
			$PhalanxTypeLink = "";
		}

		if ($GalaxyRowUser['id'] != $user['id']) {
			$MissionType6Link = "<a href=# onclick=&#039javascript:doit(6, " . $Galaxy . ", " . $System . ", " . $Planet . ", " . $PlanetType . ", " . $user["spy_count"] . ");&#039 >" . $lang['type_mission'][6] . "</a><br /><br />";
		} elseif ($GalaxyRowUser['id'] == $user['id']) {
			$MissionType6Link = "";
		}
		if ($GalaxyRowUser['id'] != $user['id']) {
			$MissionType1Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&amp;system=" . $System . "&amp;planet=" . $Planet . "&amp;planettype=" . $PlanetType . "&amp;target_mission=1>" . $lang['type_mission'][1] . "</a><br />";
		} elseif ($GalaxyRowUser['id'] == $user['id']) {
			$MissionType1Link = "";
		}
		if ($GalaxyRowUser['id'] != $user['id']) {
			$MissionType5Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "&planettype=" . $PlanetType . "&target_mission=5>" . $lang['type_mission'][5] . "</a><br />";
		} elseif ($GalaxyRowUser['id'] == $user['id']) {
			$MissionType5Link = "";
		}
		if ($GalaxyRowUser['id'] == $user['id']) {
			$MissionType4Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "&planettype=" . $PlanetType . "&target_mission=4>" . $lang['type_mission'][4] . "</a><br />";
		} elseif ($GalaxyRowUser['id'] != $user['id']) {
			$MissionType4Link = "";
		}
		$MissionType3Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "&planettype=" . $PlanetType . "&target_mission=3>" . $lang['type_mission'][3] . "</a>";

		$Result .= "<a style=\"cursor: pointer;\"";
		$Result .= " onmouseover='return overlib(\"";
		$Result .= "<table width=260 class=xnova-tip>";
		$Result .= "<tr>";
		$Result .= "<td class=xnova-tip-head colspan=2>";
		$Result .= $lang['gl_planet'] . " " . stripslashes($GalaxyRowPlanet["name"]) . " [" . $Galaxy . ":" . $System . ":" . $Planet . "]";
		$Result .= "</td>";
		$Result .= "</tr>";
		$Result .= "<tr>";
		$Result .= "<td class=xnova-tip-media width=80>";
		$Result .= "<img src=" . $dpath . "planeten/small/s_" . $GalaxyRowPlanet["image"] . ".jpg height=75 width=75 />";
		$Result .= "</td>";
		$Result .= "<td class=xnova-tip-body>";
		$Result .= $MissionType6Link;
		$Result .= $PhalanxTypeLink;
		$Result .= $MissionType1Link;
		$Result .= $MissionType5Link;
		$Result .= $MissionType4Link;
		$Result .= $MissionType3Link;
		$Result .= "</td>";
		$Result .= "</tr>";
		$Result .= "</table>\"";
		//		$Result .= ", STICKY, MOUSEOFF, DELAY, ". ($user["settings_tooltiptime"] * 1000) .", CENTER, OFFSETX, -40, OFFSETY, -40 );'";
		$Result .= ", STICKY, MOUSEOFF, DELAY, 750, CENTER, OFFSETX, -40, OFFSETY, -40 );'";
		$Result .= " onmouseout='return nd();'>";
		$Result .= "<img class=xnova-galaxy-img src=" .	$dpath . "planeten/small/s_" . $GalaxyRowPlanet["image"] . ".jpg height=30 width=30 alt=\"\">";
		$Result .= "</a>";
	}
	$Result .= "</td>";

	return $Result;
}

//

// ===== GalaxyRowPlanetName =====
function GalaxyRowPlanetName($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowUser, $Galaxy, $System, $Planet, $PlanetType)
{
	global $lang, $user, $HavePhalanx, $CurrentSystem, $CurrentGalaxy;

	// Planete (Nom)
	$Result  = "<td class=\"text-nowrap\" width=130>";

	if (
		$GalaxyRowUser['ally_id'] == $user['ally_id'] and
		$GalaxyRowUser['id']      != $user['id']      and
		$user['ally_id']          != ''
	) {
		$TextColor = "<span class=\"text-success\">";
		$EndColor  = "</span>";
	} elseif ($GalaxyRowUser['id'] == $user['id']) {
		$TextColor = "<span class=\"text-danger\">";
		$EndColor  = "</span>";
	} else {
		$TextColor = '';
		$EndColor  = "";
	}

	if (
		$GalaxyRowPlanet['last_update'] > (time() - 59 * 60) and
		$GalaxyRowUser['id'] != $user['id']
	) {
		$Inactivity = pretty_time_hour(time() - $GalaxyRowPlanet['last_update']);
	}
	if ($GalaxyRow && $GalaxyRowPlanet["destruyed"] == 0) {
		if ($HavePhalanx <> 0) {
			if ($GalaxyRowPlanet["galaxy"] == $CurrentGalaxy) {
				$Range = GetPhalanxRange($HavePhalanx);
				if (
					$CurrentGalaxy + $Range <= $CurrentSystem and
					$CurrentSystem >= $CurrentGalaxy - $Range
				) {
					$PhalanxTypeLink = "<a href=# onclick=fenster('/game/phalanx?galaxy=" . $Galaxy . "&amp;system=" . $System . "&amp;planet=" . $Planet . "&amp;planettype=" . $PlanetType . "')  title=\"" . $lang['gl_phalanx'] . "\">" . $GalaxyRowPlanet['name'] . "</a><br />";
				} else {
					$PhalanxTypeLink = stripslashes($GalaxyRowPlanet['name']);
				}
			} else {
				$PhalanxTypeLink = stripslashes($GalaxyRowPlanet['name']);
			}
		} else {
			$PhalanxTypeLink = stripslashes($GalaxyRowPlanet['name']);
		}

		$Result .= $TextColor . $PhalanxTypeLink . $EndColor;

		if (
			$GalaxyRowPlanet['last_update']  > (time() - 59 * 60) and
			$GalaxyRowUser['id']            != $user['id']
		) {
			if (
				$GalaxyRowPlanet['last_update']  > (time() - 10 * 60) and
				$GalaxyRowUser['id']            != $user['id']
			) {
				$Result .= "(*)";
			} else {
				$Result .= " (" . $Inactivity . ")";
			}
		}
	} elseif ($GalaxyRowPlanet["destruyed"] != 0) {
		$Result .= $lang['gl_destroyedplanet'];

		// Les coordonnées restent réservées un moment après l'abandon : le joueur
		// doit pouvoir lire combien de temps avant de pouvoir coloniser à nouveau.
		$ReservedDelay = \App\Core\GameConstants::abandonedPositionDelay();
		$ReservedLeft  = ((int) $GalaxyRowPlanet['destruyed'] + $ReservedDelay) - time();

		if ($ReservedDelay > 0 && $ReservedLeft > 0) {
			$Result .= sprintf($lang['gl_destroyedplanet_reserved'], (int) ceil($ReservedLeft / 3600));
		}
	}

	$Result .= "</td>";

	return $Result;
}

//

// ===== GalaxyRowMoon =====
function GalaxyRowMoon($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowUser, $Galaxy, $System, $Planet, $PlanetType)
{
	global $lang, $user, $dpath, $HavePhalanx, $CurrentSystem, $CurrentGalaxy, $CanDestroy;

	// Lune
	$Result  = "<td class=\"text-center\">";
	if ($GalaxyRowUser['id'] != $user['id']) {
		$MissionType6Link = "<a href=# onclick=&#039javascript:doit(6, " . $Galaxy . ", " . $System . ", " . $Planet . ", " . $PlanetType . ", " . $user["spy_count"] . ");&#039 >" . $lang['type_mission'][6] . "</a><br /><br />";
	} elseif ($GalaxyRowUser['id'] == $user['id']) {
		$MissionType6Link = "";
	}
	if ($GalaxyRowUser['id'] != $user['id']) {
		$MissionType1Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&amp;system=" . $System . "&amp;planet=" . $Planet . "&amp;planettype=" . $PlanetType . "&amp;target_mission=1>" . $lang['type_mission'][1] . "</a><br />";
	} elseif ($GalaxyRowUser['id'] == $user['id']) {
		$MissionType1Link = "";
	}

	if ($GalaxyRowUser['id'] != $user['id']) {
		$MissionType5Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "&planettype=" . $PlanetType . "&target_mission=5>" . $lang['type_mission'][5] . "</a><br />";
	} elseif ($GalaxyRowUser['id'] == $user['id']) {
		$MissionType5Link = "";
	}
	if ($GalaxyRowUser['id'] == $user['id']) {
		$MissionType4Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "&planettype=" . $PlanetType . "&target_mission=4>" . $lang['type_mission'][4] . "</a><br />";
	} elseif ($GalaxyRowUser['id'] != $user['id']) {
		$MissionType4Link = "";
	}

	if ($GalaxyRowUser['id'] != $user['id']) {
		if ($CanDestroy > 0) {
			$MissionType9Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "&planettype=" . $PlanetType . "&target_mission=9>" . $lang['type_mission'][9] . "</a>";
		} else {
			$MissionType9Link = "";
		}
	} elseif ($GalaxyRowUser['id'] == $user['id']) {
		$MissionType9Link = "";
	}

	$MissionType3Link = "<a href=/game/fleet?galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "&planettype=" . $PlanetType . "&target_mission=3>" . $lang['type_mission'][3] . "</a><br />";

	if ($GalaxyRow && $GalaxyRowPlanet["destruyed"] == 0 && $GalaxyRow["id_luna"] != 0) {
		$Result .= "<a style=\"cursor: pointer;\"";
		$Result .= " onmouseover='return overlib(\"";
		$Result .= "<table width=260 class=xnova-tip>";
		$Result .= "<tr>";
		$Result .= "<td class=xnova-tip-head colspan=2>";
		$Result .= $lang['Moon'] . ": " . $GalaxyRowPlanet["name"] . " [" . $Galaxy . ":" . $System . ":" . $Planet . "]";
		$Result .= "</td>";
		$Result .= "</tr><tr>";
		$Result .= "<td class=xnova-tip-media width=80>";
		$Result .= "<img src=" . $dpath . "planeten/mond.jpg height=75 width=75 />";
		$Result .= "</td>";
		$Result .= "<td class=xnova-tip-body>";
		$Result .= "<table class=xnova-tip-sub>";
		$Result .= "<tr>";
		$Result .= "<td class=xnova-tip-head colspan=2>" . $lang['caracters'] . "</td>";
		$Result .= "</tr><tr>";
		$Result .= "<td class=xnova-tip-label>" . $lang['diameter'] . "</td>";
		$Result .= "<td class=xnova-tip-value>" . number_format($GalaxyRowPlanet['diameter'], 0, '', '.') . "</td>";
		$Result .= "</tr><tr>";
		$Result .= "<td class=xnova-tip-label>" . $lang['temperature'] . "</td><td class=xnova-tip-value>" . number_format($GalaxyRowPlanet['temp_min'], 0, '', '.') . "</td>";
		$Result .= "</tr><tr>";
		$Result .= "<td class=xnova-tip-head colspan=2>" . $lang['Actions'] . "</td>";
		$Result .= "</tr><tr>";
		$Result .= "<td class=xnova-tip-body colspan=2>";
		$Result .= $MissionType6Link;
		$Result .= $MissionType3Link;
		$Result .= $MissionType4Link;
		$Result .= $MissionType1Link;
		$Result .= $MissionType5Link;
		$Result .= $MissionType9Link;
		$Result .= "</td>";
		$Result .= "</tr>";
		$Result .= "</table>";
		$Result .= "</td>";
		$Result .= "</tr>";
		$Result .= "</table>\"";
		//        $Result .= ", STICKY, MOUSEOFF, DELAY, ". ($user["settings_tooltiptime"] * 1000) .", CENTER, OFFSETX, -40, OFFSETY, -40 );'";
		$Result .= ", STICKY, MOUSEOFF, DELAY, 750, CENTER, OFFSETX, -40, OFFSETY, -40 );'";
		$Result .= " onmouseout='return nd();'>";
		$Result .= "<img src=" . $dpath . "planeten/small/s_mond.jpg height=22 width=22 alt=\"\">";
		$Result .= "</a>";
	}
	$Result .= "</td>";

	return $Result;
}

//

// ===== GalaxyRowDebris =====
function GalaxyRowDebris($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowUser, $Galaxy, $System, $Planet, $PlanetType, $CurrentPlanet = array())
{
	global $lang, $dpath, $CurrentRC, $user, $pricelist, $resource;
	// Cdr
	$Result  = "<td class=\"text-center\">";
	if ($GalaxyRow) {
		// Un champ de débris peut ne contenir que les ressources ajoutées par un
		// module : l'affichage se decide sur l'ensemble des champs déclarés, jamais
		// sur le seul métal.
		$DebrisFields = (new \App\Services\ModuleService())->debrisFields();
		$DebrisTotal  = 0;
		foreach (array('metal' => 'Metal', 'crystal' => 'Crystal') + $DebrisFields as $DebrisColumn => $DebrisLabel) {
			$DebrisTotal += (int) ($GalaxyRow[$DebrisColumn] ?? 0);
		}
		if ($DebrisTotal > 0) {
			$RecNeeded = ceil(($GalaxyRow["metal"] + $GalaxyRow["crystal"]) / $pricelist[209]['capacity']);
			if ($RecNeeded < $CurrentRC) {
				$RecSended = $RecNeeded;
			} elseif ($RecNeeded >= $CurrentRC) {
				$RecSended = $CurrentRC;
			} else {
				$RecSended = $RecyclerCount;
			}
			$Result  = "<td class=\"text-center xnova-debris-cell\" style=\"";
			if (($GalaxyRow["metal"] + $GalaxyRow["crystal"]) >= 10000000) {
				$Result .= "background-color: rgb(100, 0, 0);";
			} elseif (($GalaxyRow["metal"] + $GalaxyRow["crystal"]) >= 1000000) {
				$Result .= "background-color: rgb(100, 100, 0);";
			} elseif (($GalaxyRow["metal"] + $GalaxyRow["crystal"]) >= 100000) {
				$Result .= "background-color: rgb(0, 100, 0);";
			}
			$Result .= "background-image: none;\" width=30>";
			$Result .= "<a style=\"cursor: pointer;\"";
			$Result .= " onmouseover='return overlib(\"";
			$Result .= "<table width=260 class=xnova-tip>";
			$Result .= "<tr>";
			$Result .= "<td class=xnova-tip-head colspan=2>";
			$Result .= $lang['Debris'] . " [" . $Galaxy . ":" . $System . ":" . $Planet . "]";
			$Result .= "</td>";
			$Result .= "</tr><tr>";
			$Result .= "<td class=xnova-tip-media width=80>";
			$Result .= "<img src=" . $dpath . "planeten/debris.jpg height=75 width=75 />";
			$Result .= "</td>";
			$Result .= "<td class=xnova-tip-body>";
			$Result .= "<table class=xnova-tip-sub>";
			$Result .= "<tr>";
			$Result .= "<td class=xnova-tip-head colspan=2>" . $lang['gl_ressource'] . "</td>";
			$Result .= "</tr><tr>";
			$Result .= "<td class=xnova-tip-label>" . $lang['Metal'] . "</td><td class=xnova-tip-value>" . number_format($GalaxyRow['metal'], 0, '', '.') . "</td>";
			$Result .= "</tr><tr>";
			$Result .= "<td class=xnova-tip-label>" . $lang['Crystal'] . "</td><td class=xnova-tip-value>" . number_format($GalaxyRow['crystal'], 0, '', '.') . "</td>";
			// Champs de débris ajoutés par un module (extracteurs : le deutérium) :
			// le Coeur d'application affiche ce que le jeu déclare, sans les connaître.
			foreach ($DebrisFields as $DebrisColumn => $DebrisLabel) {
				if (!isset($GalaxyRow[$DebrisColumn])) {
					continue;
				}
				$Result .= "</tr><tr>";
				$Result .= "<td class=xnova-tip-label>" . ($lang[$DebrisLabel] ?? $DebrisLabel) . "</td><td class=xnova-tip-value>" . number_format($GalaxyRow[$DebrisColumn], 0, '', '.') . "</td>";
			}
			$Result .= "</tr><tr>";
			$Result .= "<td class=xnova-tip-head colspan=2>" . $lang['gl_action'] . "</td>";
			// Liens d'action : le recyclage du Coeur d'application, puis les missions **de champ de
			// débris** déclarées par un module (l'extraction des extracteurs). Le
			// Coeur d'application propose ce que le module annonce, et seulement si le joueur
			// possède le vaisseau requis sur la planète d'où part le vol.
			// Chaque action annonce le **nombre de vaisseaux nécessaires**, calculé
			// sur la capacité de soute du vaisseau : le recyclage sur le métal et le
			// cristal (sa règle historique), une mission de module sur **tout** le
			// champ, ressource ajoutée comprise. Quand le joueur en a moins, le
			// second nombre le dit — il sait alors qu'il ne prendra pas tout.
			$GalaxyActions = array(
				8 => 'javascript:doit(8, ' . $Galaxy . ', ' . $System . ', ' . $Planet . ', ' . $PlanetType . ', ' . $RecSended . ')',
			);
			$GalaxyShips = array(
				8 => array($RecNeeded, $CurrentRC),
			);

			foreach (\App\Services\FleetDispatchService::galaxyDebrisMissions() as $DebrisMission => $DeclaredShips) {
				$DebrisOwned = 0;
				$DebrisNeeded = 0;
				foreach ($DeclaredShips as $DeclaredShip) {
					$DebrisOwned += (int) ($CurrentPlanet[$resource[$DeclaredShip] ?? ''] ?? 0);
					$ShipCapacity = (float) ($pricelist[$DeclaredShip]['capacity'] ?? 0);
					if ($ShipCapacity > 0) {
						$DebrisNeeded += (int) ceil($DebrisTotal / $ShipCapacity);
					}
				}
				if ($DebrisOwned < 1) {
					continue;
				}
				$GalaxyActions[$DebrisMission] = 'javascript:doit(' . $DebrisMission . ', ' . $Galaxy . ', ' . $System . ', ' . $Planet . ', ' . $PlanetType . ', ' . $DebrisNeeded . ')';
				$GalaxyShips[$DebrisMission] = array($DebrisNeeded, $DebrisOwned);
			}

			foreach ($GalaxyActions as $ActionMission => $ActionCall) {
				$ActionNeeded = (int) ($GalaxyShips[$ActionMission][0] ?? 0);
				$ActionOwned = (int) ($GalaxyShips[$ActionMission][1] ?? 0);
				$ActionLabel = ($lang['type_mission'][$ActionMission] ?? $ActionMission);
				if ($ActionNeeded > 0) {
					// Ce qui part **vraiment** : la quantité demandée quand le joueur en a
					// assez, sinon tout ce qu'il possède — le serveur plafonne de toute
					// façon à ce que la planète porte. Le libellé le dit, pour qu'un clic
					// sur le raccourci n'ait pas de surprise.
					if ($ActionOwned > 0 && $ActionOwned < $ActionNeeded) {
						$ActionLabel .= ' (' . sprintf($lang['gl_debris_ships_all'], number_format($ActionOwned, 0, '', ' '), number_format($ActionNeeded, 0, '', ' ')) . ')';
					} else {
						$ActionLabel .= ' (' . number_format($ActionNeeded, 0, '', ' ') . ')';
					}
				}
				$Result .= "</tr><tr>";
				$Result .= "<td class=xnova-tip-body colspan=2>";
				$Result .= "<a href=# onclick=&#039;" . $ActionCall . ";&#039; >" . $ActionLabel . "</a>";
			}
			$Result .= "</td>";
			$Result .= "</tr>";
			$Result .= "</table>";
			$Result .= "</td>";
			$Result .= "</tr>";
			$Result .= "</table>\"";
			//			$Result .= ", STICKY, MOUSEOFF, DELAY, ". ($user["settings_tooltiptime"] * 1000) .", CENTER, OFFSETX, -40, OFFSETY, -40 );'";
			$Result .= ", STICKY, MOUSEOFF, DELAY, 750, CENTER, OFFSETX, -40, OFFSETY, -40 );'";
			$Result .= " onmouseout='return nd();'>";
			$Result .= "<img src=" . $dpath . "planeten/debris.jpg height=22 width=22 alt=\"\"></a>";
		}
	}
	$Result .= "</td>";

	return $Result;
}

//

// ===== GalaxyRowUser =====
function GalaxyRowUser($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowUser, $Galaxy, $System, $Planet, $PlanetType)
{
	global $lang, $user;

	// Joueur
	$Result  = "<td width=150>";
	// $GalaxyRowUser est en realite $GalaxyRowPlayer (voir ShowGalaxyRows) :
	// sur une position vide il vaut un tableau par defaut avec id = 0, ce qui
	// declenchait a tort l'etiquette "inactif depuis 28 jours".
	if (($GalaxyRowUser['id'] ?? 0) != 0 && $GalaxyRowPlanet["destruyed"] == 0) {
		$NoobProt      = array('config_value' => \App\Core\GameConfig::get('noobprotection', '0'));
		$NoobTime      = array('config_value' => \App\Core\GameConfig::get('noobprotectiontime', '0'));
		$NoobMulti     = array('config_value' => \App\Core\GameConfig::get('noobprotectionmulti', '0'));
		// Points du classement : préchargés pour tout le système (une requête au lieu
		// de deux par ligne, dont une qui reposait la même question sur mon compte).
		$points        = new \App\Repositories\StatsRepository();
		$UserPoints    = $points->currentPoints((int) $user['id']);
		$User2Points   = $points->currentPoints((int) $GalaxyRowUser['id']);
		$CurrentPoints = $UserPoints['total_points'];
		$RowUserPoints = $User2Points['total_points'];
		$CurrentLevel  = $CurrentPoints * $NoobMulti['config_value'];
		$RowUserLevel  = $RowUserPoints * $NoobMulti['config_value'];
		if (
			$GalaxyRowUser['bana'] == 1 and
			$GalaxyRowUser['vacation_mode'] == 1
		) {
			$Systemtatus2 = $lang['vacation_shortcut'] . " <a href=\"/front/banned\"><span class=\"banned\">" . $lang['banned_shortcut'] . "</span></a>";
			$Systemtatus  = "<span class=\"vacation\">";
		} elseif ($GalaxyRowUser['bana'] == 1) {
			$Systemtatus2 = "<a href=\"/front/banned\"><span class=\"banned\">" . $lang['banned_shortcut'] . "</span></a>";
			$Systemtatus  = "";
		} elseif ($GalaxyRowUser['vacation_mode'] == 1) {
			$Systemtatus2 = "<span class=\"vacation\">" . $lang['vacation_shortcut'] . "</span>";
			$Systemtatus  = "<span class=\"vacation\">";
		} elseif (
			$GalaxyRowUser['onlinetime'] < (time() - 60 * 60 * 24 * 7) and
			$GalaxyRowUser['onlinetime'] > (time() - 60 * 60 * 24 * 28)
		) {
			$Systemtatus2 = "<span class=\"inactive\">" . $lang['inactif_7_shortcut'] . "</span>";
			$Systemtatus  = "<span class=\"inactive\">";
		} elseif ($GalaxyRowUser['onlinetime'] < (time() - 60 * 60 * 24 * 28)) {
			$Systemtatus2 = "<span class=\"inactive\">" . $lang['inactif_7_shortcut'] . "</span><span class=\"longinactive\"> " . $lang['inactif_28_shortcut'] . "</span>";
			$Systemtatus  = "<span class=\"longinactive\">";
		} elseif (
			$RowUserLevel < $CurrentPoints and
			$NoobProt['config_value'] == 1 and
			$NoobTime['config_value'] * 1000 > $RowUserPoints
		) {
			$Systemtatus2 = "<span class=\"noob\">" . $lang['weak_player_shortcut'] . "</span>";
			$Systemtatus  = "<span class=\"noob\">";
		} elseif (
			$RowUserPoints > $CurrentLevel and
			$NoobProt['config_value'] == 1 and
			$NoobTime['config_value'] * 1000 > $CurrentPoints
		) {
			$Systemtatus2 = $lang['strong_player_shortcut'];
			$Systemtatus  = "<span class=\"strong\">";
		} else {
			$Systemtatus2 = "";
			$Systemtatus  = "";
		}
		$Systemtatus4 = $User2Points['total_rank'];
		if ($Systemtatus2 != '') {
			$Systemtatus6 = "<span class=\"text-body-secondary\">(</span>";
			$Systemtatus7 = "<span class=\"text-body-secondary\">)</span>";
		}
		if ($Systemtatus2 == '') {
			$Systemtatus6 = "";
			$Systemtatus7 = "";
		}
		$admin = "";
		if ($GalaxyRowUser['authlevel'] > 0) {
			$admin = "<span class=\"text-success fw-bold\" title=\"Admin\">A</span>";
		}
		// Statut supplémentaire : compte piloté par le jeu (voir le module « bot »). Sans
		// le module, le Coeur d'application ne connaît aucun robot et la marque disparaît avec eux.
		$bot = "";
		if (\App\Services\BotService::present() && (int) ($GalaxyRowUser['bot'] ?? 0) === 1) {
			$botLabel = $lang['gl_bot'] ?? 'Bot';
			$bot = "<span class=\"badge text-bg-info gl-bot\" title=\"" . $botLabel . "\">" . $botLabel . "</span>";
		}
		$Systemtart = $User2Points['total_rank'];
		if (strlen($Systemtart) < 3) {
			$Systemtart = 1;
		} else {
			$Systemtart = (floor($User2Points['total_rank'] / 100) * 100) + 1;
		}
		$Result .= "<a style=\"cursor: pointer;\"";
		$Result .= " onmouseover='return overlib(\"";
		$Result .= "<table width=230 class=xnova-tip>";
		$Result .= "<tr>";
		$Result .= "<td class=xnova-tip-head colspan=2>" . $lang['Player'] . " " . $GalaxyRowUser['username'] . " " . $lang['Place'] . " " . $Systemtatus4 . "</td>";
		$Result .= "</tr><tr>";
		if ($GalaxyRowUser['id'] != $user['id']) {
			$Result .= "<td class=xnova-tip-body colspan=2><a href=/game/profil/messages?mode=write&id=" . $GalaxyRowUser['id'] . ">" . $lang['gl_sendmess'] . "</a></td>";
			$Result .= "</tr><tr>";
			$Result .= "<td class=xnova-tip-body colspan=2><a href=/game/buddy?a=2&u=" . $GalaxyRowUser['id'] . ">" . $lang['gl_buddyreq'] . "</a></td>";
			$Result .= "</tr><tr>";
		}
		$Result .= "<td class=xnova-tip-body colspan=2><a href=/game/stat?who=player&start=" . $Systemtart . ">" . $lang['gl_stats'] . "</a></td>";
		$Result .= "</tr>";
		$Result .= "</table>\"";
		$Result .= ", STICKY, MOUSEOFF, DELAY, 750, CENTER, OFFSETX, -40, OFFSETY, -40 );'";
		$Result .= " onmouseout='return nd();'>";
		$Result .= $Systemtatus;
		$Result .= $GalaxyRowUser["username"] . "</span>";
		$Result .= $Systemtatus6;
		$Result .= $Systemtatus;
		$Result .= $Systemtatus2;
		$Result .= $Systemtatus7 . " " . $admin . " " . $bot;
		$Result .= "</span></a>";
	}
	$Result .= "</td>";

	return $Result;
}

//

// ===== GalaxyRowAlly =====
function GalaxyRowAlly($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowUser, $Galaxy, $System, $Planet, $PlanetType)
{
	global $lang, $user;

	// Alliances
	$Result  = "<td class=\"text-center\" width=80>";

	// Le module des alliances peut être éteint (jeu sans alliances) : la colonne ne
	// montre alors rien, et le dépôt du module n'est même pas chargé.
	$allianceModule = new \App\Services\ModuleService();

	if ($allianceModule->available('alliance') && $GalaxyRowUser['ally_id'] && $GalaxyRowUser['ally_id'] != 0) {
		$allyRepository = new \Modules\Alliance\Repositories\AllianceRepository();
		// Lignes et effectifs préchargés pour tout le système (deux requêtes au lieu
		// de deux par ligne) ; la lecture directe reste le repli hors préchargement.
		$allyquery = $allyRepository->prefetchedById((int) $GalaxyRowUser['ally_id'])
			?? $allyRepository->findByIdRaw((int) $GalaxyRowUser['ally_id']);
		if ($allyquery) {
			$members_count = $allyRepository->prefetchedMemberCount((int) $allyquery['id']);
			if ($members_count === null) {
				$members_count = (int) ($allyRepository->countMembers((int) $allyquery['id'])['n'] ?? 0);
			}

			if ($members_count > 1) {
				$add = "s";
			} else {
				$add = "";
			}

			$Result .= "<a style=\"cursor: pointer;\"";
			$Result .= " onmouseover='return overlib(\"";
			$Result .= "<table width=240 class=xnova-tip>";
			$Result .= "<tr>";
			$Result .= "<td class=xnova-tip-head colspan=2>" . $lang['Alliance'] . " " . $allyquery['ally_name'] . " " . $lang['gl_with'] . " " . $members_count . " " . $lang['gl_membre'] . $add . "</td>";
			$Result .= "</tr>";
			$Result .= "<tr>";
			$Result .= "<td class=xnova-tip-body colspan=2>";
			$Result .= "<table class=xnova-tip-sub>";
			$Result .= "<tr>";
			$Result .= "<td><a href=/game/alliance?mode=ainfo&a=" . $allyquery['id'] . ">" . $lang['gl_ally_internal'] . "</a></td>";
			$Result .= "</tr><tr>";
			$Result .= "<td><a href=/game/stat?start=101&who=ally>" . $lang['gl_stats'] . "</a></td>";
			if ($allyquery["ally_web"] != "") {
				$Result .= "</tr><tr>";
				$Result .= "<td><a href=" . $allyquery["ally_web"] . " target=_new>" . $lang['gl_ally_web'] . "</a></td>";
			}
			$Result .= "</tr>";
			$Result .= "</table>";
			$Result .= "</td>";
			$Result .= "</tr>";
			$Result .= "</table>\"";
			$Result .= ", STICKY, MOUSEOFF, DELAY, 750, CENTER, OFFSETX, -40, OFFSETY, -40 );'";
			$Result .= " onmouseout='return nd();'>";
			if ($user['ally_id'] == $GalaxyRowUser['ally_id']) {
				$Result .= "<span class=\"allymember\">" . $allyquery['ally_tag'] . "</span></a>";
			} else {
				$Result .= $allyquery['ally_tag'] . "</a>";
			}
		}
	}
	$Result .= "</td>";

	return $Result;
}

//

// ===== GalaxyRowActions =====
function GalaxyRowActions($GalaxyRow, $GalaxyRowPlanet, $GalaxyRowPlayer, $Galaxy, $System, $Planet, $PlanetType, $CurrentPlanet = array())
{
	global $lang, $user, $dpath, $CurrentMIP, $CurrentSystem, $CurrentGalaxy, $resource;
	// Icones action
	$Result  = "<td class=\"text-center text-nowrap\" width=125>";
	// Position libre : la colonisation en un clic. Le lien ouvre la page d'envoi avec
	// une sonde de colonisation et la mission 7 déjà choisies, **sans JavaScript** (la
	// page reste utilisable telle quelle). Une position abandonnée n'est pas libre :
	// elle reste réservée un moment, et `destruyed` la porte — on ne propose donc rien.
	$ColonyShip = (int) ($CurrentPlanet[$resource[\App\Services\FleetDispatchService::COLONY_SHIP] ?? ''] ?? 0);
	if (($GalaxyRowPlanet['id'] ?? 0) == 0 && ($GalaxyRowPlanet['destruyed'] ?? 0) == 0 && $ColonyShip > 0) {
		$Result .= "<a href=\"/game/fleet?galaxy=" . $Galaxy . "&amp;system=" . $System . "&amp;planet=" . $Planet . "&amp;planettype=1&amp;target_mission=" . \App\Services\FleetDispatchService::MISSION_COLONIZE . "&amp;ship" . \App\Services\FleetDispatchService::COLONY_SHIP . "=1\" title=\"" . ($lang['type_mission'][7] ?? '') . "\">" . ($lang['type_mission'][7] ?? '') . "</a>&nbsp;";
	}
	if ($GalaxyRowPlayer['id'] != $user['id']) {

		if ($CurrentMIP <> 0) {
			if ($GalaxyRowUser['id'] != $user['id']) {
				if ($GalaxyRowPlanet["galaxy"] == $CurrentGalaxy) {
					$Range = GetMissileRange();
					$SystemLimitMin = $CurrentSystem - $Range;
					if ($SystemLimitMin < 1) {
						$SystemLimitMin = 1;
					}
					$SystemLimitMax = $CurrentSystem + $Range;
					if ($System <= $SystemLimitMax) {
						if ($System >= $SystemLimitMin) {
							$MissileBtn = true;
						} else {
							$MissileBtn = false;
						}
					} else {
						$MissileBtn = false;
					}
				} else {
					$MissileBtn = false;
				}
			} else {
				$MissileBtn = false;
			}
		} else {
			$MissileBtn = false;
		}

		if ($GalaxyRowPlayer && $GalaxyRowPlanet["destruyed"] == 0) {
			if (
				$user["settings_esp"] == "1" &&
				$GalaxyRowPlayer['id']
			) {
				$Result .= "<a href=# onclick=\"javascript:doit(6, " . $Galaxy . ", " . $System . ", " . $Planet . ", 1, " . $user["spy_count"] . ");\" >";
				$Result .= "<img class=xnova-action-icon src=\"" . $dpath . "img/e.gif\" alt=\"" . $lang['gl_espionner'] . "\" title=\"" . $lang['gl_espionner'] . "\"></a>";
				$Result .= "&nbsp;";
			}
			if (
				$user["settings_wri"] == "1" &&
				$GalaxyRowPlayer['id']
			) {
				$Result .= "<a href=/game/profil/messages?mode=write&id=" . $GalaxyRowPlayer["id"] . ">";
				$Result .= "<img class=xnova-action-icon src=\"" . $dpath . "img/m.gif\" alt=\"" . $lang['gl_sendmess'] . "\" title=\"" . $lang['gl_sendmess'] . "\"></a>";
				$Result .= "&nbsp;";
			}
			if (
				$user["settings_bud"] == "1" &&
				$GalaxyRowPlayer['id']
			) {
				$Result .= "<a href=/game/buddy?a=2&amp;u=" . $GalaxyRowPlayer['id'] . " >";
				$Result .= "<img class=xnova-action-icon src=\"" . $dpath . "img/b.gif\" alt=\"" . $lang['gl_buddyreq'] . "\" title=\"" . $lang['gl_buddyreq'] . "\"></a>";
				$Result .= "&nbsp;";
			}
			if (
				$user["settings_mis"] == "1" and
				$MissileBtn == true          &&
				$GalaxyRowPlayer['id']
			) {
				$Result .= "<a href=/game/galaxy?mode=2&galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "&current=" . $user['current_planet'] . " >";
				$Result .= "<img class=xnova-action-icon src=\"" . $dpath . "img/r.gif\" alt=\"" . $lang['gl_mipattack'] . "\" title=\"" . $lang['gl_mipattack'] . "\"></a>";
			}
		}
	}
	$Result .= "</td>";

	return $Result;
}

//

// ===== ShowGalaxySelector =====
function ShowGalaxySelector($Galaxy, $System)
{
	global $lang;

	if ($Galaxy > MAX_GALAXY_IN_WORLD) {
		$Galaxy = MAX_GALAXY_IN_WORLD;
	}
	if ($Galaxy < 1) {
		$Galaxy = 1;
	}
	if ($System > MAX_SYSTEM_IN_GALAXY) {
		$System = MAX_SYSTEM_IN_GALAXY;
	}
	if ($System < 1) {
		$System = 1;
	}

	$Result  = "<form action=\"/game/galaxy?mode=1\" method=\"post\" id=\"galaxy_form\" class=\"xnova-galaxy-selector card border-0 shadow-sm mb-3\">";
	$Result .= "<input type=\"hidden\" id=\"auto\" value=\"dr\">";
	$Result .= "<div class=\"card-body\">";
	$Result .= "<div class=\"row g-3 align-items-end\">";

	$Result .= "<div class=\"col-12 col-md-6\">";
	$Result .= "<label class=\"form-label\" for=\"galaxy\">" . $lang['Galaxy'] . "</label>";
	$Result .= "<div class=\"input-group\">";
	$Result .= "<button class=\"btn btn-outline-secondary\" type=\"button\" tabindex=\"3\" aria-label=\"" . $lang['Galaxy'] . " -1\" onclick=\"galaxy_submit('galaxyLeft')\"><i class=\"bi bi-chevron-left\" aria-hidden=\"true\"></i></button>";
	$Result .= "<input class=\"form-control text-center\" id=\"galaxy\" name=\"galaxy\" value=\"" . $Galaxy . "\" size=\"5\" maxlength=\"3\" tabindex=\"1\" type=\"text\">";
	$Result .= "<button class=\"btn btn-outline-secondary\" type=\"button\" tabindex=\"4\" aria-label=\"" . $lang['Galaxy'] . " +1\" onclick=\"galaxy_submit('galaxyRight')\"><i class=\"bi bi-chevron-right\" aria-hidden=\"true\"></i></button>";
	$Result .= "</div>";
	$Result .= "</div>";

	$Result .= "<div class=\"col-12 col-md-6\">";
	$Result .= "<label class=\"form-label\" for=\"system\">" . $lang['Solar_system'] . "</label>";
	$Result .= "<div class=\"input-group\">";
	$Result .= "<button class=\"btn btn-outline-secondary\" type=\"button\" tabindex=\"5\" aria-label=\"" . $lang['Solar_system'] . " -1\" onclick=\"galaxy_submit('systemLeft')\"><i class=\"bi bi-chevron-left\" aria-hidden=\"true\"></i></button>";
	$Result .= "<input class=\"form-control text-center\" id=\"system\" name=\"system\" value=\"" . $System . "\" size=\"5\" maxlength=\"3\" tabindex=\"2\" type=\"text\">";
	$Result .= "<button class=\"btn btn-outline-secondary\" type=\"button\" tabindex=\"6\" aria-label=\"" . $lang['Solar_system'] . " +1\" onclick=\"galaxy_submit('systemRight')\"><i class=\"bi bi-chevron-right\" aria-hidden=\"true\"></i></button>";
	$Result .= "</div>";
	$Result .= "</div>";

	$Result .= "<div class=\"col-12\">";
	$Result .= "<button class=\"btn btn-primary\" type=\"submit\"><i class=\"bi bi-search\" aria-hidden=\"true\"></i> " . $lang['Afficher'] . "</button>";
	$Result .= "</div>";

	$Result .= "</div>";
	$Result .= "</div>";
	$Result .= "</form>";

	return $Result;
}

//

// ===== ShowGalaxyMISelector =====
function ShowGalaxyMISelector($Galaxy, $System, $Planet, $Current, $MICount)
{
	global $lang;

	$Result  = "<form action=\"/game/missile-attack?c=" . $Current . "&mode=2&galaxy=" . $Galaxy . "&system=" . $System . "&planet=" . $Planet . "\" method=\"POST\" class=\"xnova-galaxy-selector card border-0 shadow-sm mb-3\">";
	$Result .= "<div class=\"card-header fw-semibold\">";
	$Result .= $lang['gm_launch'] . " [" . $Galaxy . ":" . $System . ":" . $Planet . "]";
	$Result .= "</div>";
	$Result .= "<div class=\"card-body\">";
	$Result .= "<div class=\"row g-3 align-items-end\">";
	$String  = sprintf($lang['gm_restmi'], $MICount);
	$Result .= "<div class=\"col-12 col-md-6\">";
	$Result .= "<label class=\"form-label\" for=\"SendMI\">" . $String . "</label>";
	$Result .= "<input class=\"form-control\" type=\"text\" id=\"SendMI\" name=\"SendMI\" size=\"2\" maxlength=\"7\" />";
	$Result .= "</div>";
	$Result .= "<div class=\"col-12 col-md-6\">";
	$Result .= "<label class=\"form-label\" for=\"Target\">" . $lang['gm_target'] . "</label>";
	$Result .= "<select class=\"form-select\" id=\"Target\" name=\"Target\">";
	$Result .= "<option value=\"all\" selected>" . $lang['gm_all'] . "</option>";
	$Result .= "<option value=\"0\">" . $lang['tech'][401] . "</option>";
	$Result .= "<option value=\"1\">" . $lang['tech'][402] . "</option>";
	$Result .= "<option value=\"2\">" . $lang['tech'][403] . "</option>";
	$Result .= "<option value=\"3\">" . $lang['tech'][404] . "</option>";
	$Result .= "<option value=\"4\">" . $lang['tech'][405] . "</option>";
	$Result .= "<option value=\"5\">" . $lang['tech'][406] . "</option>";
	$Result .= "<option value=\"6\">" . $lang['tech'][407] . "</option>";
	$Result .= "<option value=\"7\">" . $lang['tech'][408] . "</option>";
	$Result .= "</select>";
	$Result .= "</div>";
	$Result .= "<div class=\"col-12\">";
	$Result .= "<button class=\"btn btn-danger\" type=\"submit\"><i class=\"bi bi-broadcast\" aria-hidden=\"true\"></i> " . $lang['gm_send'] . "</button>";
	$Result .= "</div>";
	$Result .= "</div>";
	$Result .= "</div>";
	$Result .= "</form>";

	return $Result;
}

//

// ===== ShowGalaxyTitles =====
function ShowGalaxyTitles($Galaxy, $System)
{
	global $lang;

	$Result  = "\n";
	$Result .= "<tr>";
	$Result .= "<td class=\"xnova-section\" colspan=\"8\">" . $lang['Solar_system'] . " " . $Galaxy . ":" . $System . "</td>";
	$Result .= "</tr><tr>";
	$Result .= "<th scope=\"col\" class=\"text-center\">" . $lang['Pos'] . "</th>";
	$Result .= "<th scope=\"col\" class=\"text-center\">" . $lang['Planet'] . "</th>";
	$Result .= "<th scope=\"col\">" . $lang['Name'] . "</th>";
	$Result .= "<th scope=\"col\" class=\"text-center\">" . $lang['Moon'] . "</th>";
	$Result .= "<th scope=\"col\" class=\"text-center\">" . $lang['Debris'] . "</th>";
	$Result .= "<th scope=\"col\">" . $lang['Player'] . "</th>";
	$Result .= "<th scope=\"col\" class=\"text-center\">" . $lang['Alliance'] . "</th>";
	$Result .= "<th scope=\"col\" class=\"text-center\">" . $lang['Actions'] . "</th>";
	$Result .= "</tr>";

	return $Result;
}

//

// ===== GalaxyLegendPopup =====
function GalaxyLegendPopup()
{
	global $lang;

	$Result  = "<a href=# style=\"cursor: pointer;\"";
	$Result .= " onmouseover='return overlib(\"";

	$Result .= "<table width=260 class=xnova-tip>";
	$Result .= "<tr>";
	$Result .= "<td class=xnova-tip-head colspan=2>" . $lang['Legend'] . "</td>";
	$Result .= "</tr><tr>";
	$Result .= "<td class=xnova-tip-label width=220>" . $lang['Strong_player'] . "</td><td class=xnova-tip-body><span class=strong>" . $lang['strong_player_shortcut'] . "</span></td>";
	$Result .= "</tr><tr>";
	$Result .= "<td class=xnova-tip-label width=220>" . $lang['Weak_player'] . "</td><td class=xnova-tip-body><span class=noob>" . $lang['weak_player_shortcut'] . "</span></td>";
	$Result .= "</tr><tr>";
	$Result .= "<td class=xnova-tip-label width=220>" . $lang['Way_vacation'] . "</td><td class=xnova-tip-body><span class=vacation>" . $lang['vacation_shortcut'] . "</span></td>";
	$Result .= "</tr><tr>";
	$Result .= "<td class=xnova-tip-label width=220>" . $lang['Pendent_user'] . "</td><td class=xnova-tip-body><span class=banned>" . $lang['banned_shortcut'] . "</span></td>";
	$Result .= "</tr><tr>";
	$Result .= "<td class=xnova-tip-label width=220>" . $lang['Inactive_7_days'] . "</td><td class=xnova-tip-body><span class=inactive>" . $lang['inactif_7_shortcut'] . "</span></td>";
	$Result .= "</tr><tr>";
	$Result .= "<td class=xnova-tip-label width=220>" . $lang['Inactive_28_days'] . "</td><td class=xnova-tip-body><span class=longinactive>" . $lang['inactif_28_shortcut'] . "</span></td>";
	$Result .= "</tr><tr>";
	$Result .= "<td class=xnova-tip-label width=220>Admin</td><td class=xnova-tip-body><span class=\"text-success fw-bold\">A</span>" . "</td>";
	$Result .= "</tr>";
	$Result .= "</table>";
	$Result .= "\");' onmouseout='return nd();'>";
	$Result .= $lang['Legend'] . "</a>";

	return $Result;
}

//

// ===== ShowGalaxyFooter =====
function ShowGalaxyFooter($Galaxy, $System,  $CurrentMIP, $CurrentRC, $CurrentSP)
{
	global $lang, $maxfleet_count, $fleetmax, $planetcount;

	$Result  = "";
	if ($planetcount == 1) {
		$PlanetCountMessage = $planetcount . " " . $lang['gf_cntmone'];
	} elseif ($planetcount == 0) {
		$PlanetCountMessage = $lang['gf_cntmnone'];
	} else {
		$PlanetCountMessage = $planetcount . " " . $lang['gf_cntmsome'];
	}
	$LegendPopup = GalaxyLegendPopup();
	$Recyclers   = pretty_number($CurrentRC);
	$SpyProbes   = pretty_number($CurrentSP);

	$Result .= "\n";
	$Result .= "<tr>";
	$Result .= "<td class=\"xnova-galaxy-foot text-center xnova-galaxy-pos\">16</td>";
	$Result .= "<td class=\"xnova-galaxy-foot\" colspan=\"7\">";
	$Result .= "<a href=\"/game/fleet?galaxy=" . $Galaxy . "&amp;system=" . $System . "&amp;planet=16&amp;planettype=1&amp;target_mission=15\">" . $lang['gf_unknowsp'] . "</a>";
	$Result .= "</td>";
	$Result .= "</tr>";

	$Result .= "\n";
	$Result .= "<tr>";
	$Result .= "<td class=\"xnova-galaxy-foot text-center\" colspan=\"6\">( " . $PlanetCountMessage . " )</td>";
	$Result .= "<td class=\"xnova-galaxy-foot text-center\" colspan=\"2\">" . $LegendPopup . "</td>";
	$Result .= "</tr>";

	$Result .= "\n";
	$Result .= "<tr>";
	$Result .= "<td class=\"xnova-galaxy-foot text-center\" colspan=\"3\"><span id=\"missiles\">" . $CurrentMIP . "</span> <span class=\"xnova-galaxy-stat\">" . $lang['gf_mi_title'] . "</span></td>";
	$Result .= "<td class=\"xnova-galaxy-foot text-center\" colspan=\"3\"><span id=\"slots\">" . $maxfleet_count . "</span>/" . $fleetmax . " <span class=\"xnova-galaxy-stat\">" . $lang['gf_fleetslt'] . "</span></td>";
	$Result .= "<td class=\"xnova-galaxy-foot text-center\" colspan=\"2\">";
	$Result .= "<span id=\"recyclers\">" . $Recyclers . "</span> <span class=\"xnova-galaxy-stat\">" . $lang['gf_rc_title'] . "</span><br>";
	$Result .= "<span id=\"probes\">" . $SpyProbes . "</span> <span class=\"xnova-galaxy-stat\">" . $lang['gf_sp_title'] . "</span></td>";
	$Result .= "</tr>";

	$Result .= "\n";
	$Result .= "<tr style=\"display: none;\" id=\"fleetstatusrow\">";
	$Result .= "<td class=\"xnova-galaxy-foot\" colspan=\"8\">";
	$Result .= "<table class=\"table table-sm mb-0\" id=\"fleetstatustable\">";
	$Result .= "</table>";
	$Result .= "</td>";
	$Result .= "\n";
	$Result .= "</tr>";

	return $Result;
}

//
