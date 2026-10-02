<?php

/**
 * Fonctions legacy : divers (JS, helpers)
 */

// ===== InsertJavaScriptChronoApplet =====
function InsertJavaScriptChronoApplet($Type, $Ref, $Value, $Init)
{
	if ($Init == true) {
		$JavaString  = "<script type=\"text/javascript\">\n";
		$JavaString .= "function t" . $Type . $Ref . "() {\n";
		$JavaString .= "v = new Date();\n";
		$JavaString .= "var bxx" . $Type . $Ref . " = document.getElementById('bxx" . $Type . $Ref . "');\n";
		$JavaString .= "n = new Date();\n";
		$JavaString .= "ss" . $Type . $Ref . " = pp" . $Type . $Ref . ";\n";
		$JavaString .= "ss" . $Type . $Ref . " = ss" . $Type . $Ref . " - Math.round((n.getTime() - v.getTime()) / 1000.);\n";
		$JavaString .= "m" . $Type . $Ref . " = 0;\n";
		$JavaString .= "h" . $Type . $Ref . " = 0;\n";
		$JavaString .= "if (ss" . $Type . $Ref . " < 0) {\n";
		$JavaString .= "	bxx" . $Type . $Ref . ".innerHTML = \"-\";\n";
		$JavaString .= "} else {\n";
		$JavaString .= "	if (ss" . $Type . $Ref . " > 59) {\n";
		$JavaString .= "		m" . $Type . $Ref . " = Math.floor(ss" . $Type . $Ref . " / 60);\n";
		$JavaString .= "		ss" . $Type . $Ref . " = ss" . $Type . $Ref . " - m" . $Type . $Ref . " * 60;\n";
		$JavaString .= "	}\n";
		$JavaString .= "	if (m" . $Type . $Ref . " > 59) {\n";
		$JavaString .= "		h" . $Type . $Ref . " = Math.floor(m" . $Type . $Ref . " / 60);\n";
		$JavaString .= "		m" . $Type . $Ref . " = m" . $Type . $Ref . " - h" . $Type . $Ref . " * 60;\n";
		$JavaString .= "	}\n";
		$JavaString .= "	if (ss" . $Type . $Ref . " < 10) {\n";
		$JavaString .= "		ss" . $Type . $Ref . " = \"0\" + ss" . $Type . $Ref . ";\n";
		$JavaString .= "	}\n";
		$JavaString .= "	if (m" . $Type . $Ref . " < 10) {\n";
		$JavaString .= "		m" . $Type . $Ref . " = \"0\" + m" . $Type . $Ref . ";\n";
		$JavaString .= "	}\n";
		$JavaString .= "	bxx" . $Type . $Ref . ".innerHTML = h" . $Type . $Ref . " + \":\" + m" . $Type . $Ref . " + \":\" + ss" . $Type . $Ref . ";\n";
		$JavaString .= "}\n";
		$JavaString .= "pp" . $Type . $Ref . " = pp" . $Type . $Ref . " - 1;\n";
		$JavaString .= "window.setTimeout(\"t" . $Type . $Ref . "();\", 999);\n";
		$JavaString .= "}\n";
		$JavaString .= "</script>\n";
	} else {
		$JavaString  = "<script language=\"JavaScript\">\n";
		$JavaString .= "pp" . $Type . $Ref . " = " . $Value . ";\n";
		$JavaString .= "t" . $Type . $Ref . "();\n";
		$JavaString .= "</script>\n";
	}

	return $JavaString;
}

//

// ===== ShowTopNavigationBar =====
function ShowTopNavigationBar($CurrentUser, $CurrentPlanet)
{
	global $lang, $_GET, $game_config;

	if ($CurrentUser) {
		if (!$CurrentPlanet) {
			// Une seule lecture par identifiant de planète : celle du depot des planetes.
			$CurrentPlanet = (new \App\Repositories\PlanetRepository())->findCurrentById((int) $CurrentUser['current_planet']);
		}

		// Actualisation des ressources de la planete
		PlanetResourceUpdate($CurrentUser, $CurrentPlanet, time());

		$NavigationTPL       = gettemplate('topnav');

		$dpath               = (!$CurrentUser["dpath"]) ? DEFAULT_SKINPATH : $CurrentUser["dpath"];
		$parse               = $lang;
		$parse['dpath']      = $dpath;
		$parse['image']      = $CurrentPlanet['image'];

		// Genearation de la combo des planetes du joueur
		$parse['planetlist'] = '';
		$ThisUsersPlanets    = SortUserPlanets($CurrentUser);
		foreach ($ThisUsersPlanets as $CurPlanet) {
			if (($CurPlanet["destruyed"] ?? 0) == 0) {
				$parse['planetlist'] .= "\n<option ";
				if ($CurPlanet['id'] == $CurrentUser['current_planet']) {
					// Bon puisque deja on s'y trouve autant le marquer
					$parse['planetlist'] .= "selected=\"selected\" ";
				}
				$parse['planetlist'] .= "value=\"?cp=" . $CurPlanet['id'] . "";
				$parse['planetlist'] .= "&amp;mode=" . ($_GET['mode'] ?? '');
				$parse['planetlist'] .= "&amp;re=0\">";

				// Nom et coordonnées de la planete
				$parse['planetlist'] .= "" . $CurPlanet['name'];
				$parse['planetlist'] .= "&nbsp;[" . $CurPlanet['galaxy'] . ":";
				$parse['planetlist'] .= "" . $CurPlanet['system'] . ":";
				$parse['planetlist'] .= "" . $CurPlanet['planet'];
				$parse['planetlist'] .= "]&nbsp;&nbsp;</option>";
			}
		}

		$energy = pretty_number($CurrentPlanet["energy_max"] + $CurrentPlanet["energy_used"]) . "/" . pretty_number($CurrentPlanet["energy_max"]);
		// Energie
		if (($CurrentPlanet["energy_max"] + $CurrentPlanet["energy_used"]) < 0) {
			$parse['energy'] = colorRed($energy);
		} else {
			$parse['energy'] = $energy;
		}
		// Metal
		$metal = pretty_number($CurrentPlanet["metal"]);
		if (($CurrentPlanet["metal"] > $CurrentPlanet["metal_max"])) {
			$parse['metal'] = colorRed($metal);
		} else {
			$parse['metal'] = $metal;
		}
		// Cristal
		$crystal = pretty_number($CurrentPlanet["crystal"]);
		if (($CurrentPlanet["crystal"] > $CurrentPlanet["crystal_max"])) {
			$parse['crystal'] = colorRed($crystal);
		} else {
			$parse['crystal'] = $crystal;
		}
		// Deuterium
		$deuterium = pretty_number($CurrentPlanet["deuterium"]);
		if (($CurrentPlanet["deuterium"] > $CurrentPlanet["deuterium_max"])) {
			$parse['deuterium'] = colorRed($deuterium);
		} else {
			$parse['deuterium'] = $deuterium;
		}

		// Message
		if ($CurrentUser['new_message'] > 0) {
			$parse['message'] = "<a href=\"/game/profil/messages\">[ " . $CurrentUser['new_message'] . " ]</a>";
		} else {
			$parse['message'] = "0";
		}

		// Barre d'icônes, placée au-dessus du choix de la planète : notifications
		// (messages non lus, demandes d'ami en attente) puis raccourcis des fenêtres
		// surgissantes. Les notes restent commandées par le réglage `enable_notes`.
		$pendingRequests = 0;

		try {
			$pendingRequests = (new \App\Repositories\BuddyRepository())->countPendingRequests((int) $CurrentUser['id']);
		} catch (\Throwable) {
			// Un incident de comptage ne doit jamais casser l'affichage de la page.
		}

		$unreadMessages = (int) ($CurrentUser['new_message'] ?? 0);
		$parse['message_notif'] = '<a class="btn btn-outline-secondary btn-sm xnova-notif"'
			. ' href="/game/profil/messages" title="Messages" aria-label="Messages">'
			. '<i class="bi bi-envelope-fill" aria-hidden="true"></i>'
			. '<span class="xnova-notif-badge' . ($unreadMessages > 0 ? '' : ' d-none') . '" id="xnova-notif-message">'
			. ($unreadMessages > 0 ? $unreadMessages : '') . '</span></a>';

		$parse['notes_popup'] = ($game_config['enable_notes'] == 1)
			? '<button type="button" class="btn btn-outline-secondary btn-sm" title="Notes" aria-label="Notes"'
				. ' onclick="var w=window.open(\'/game/profil/notes\',\'Report\',\'resizable=yes,scrollbars=yes,menubar=no,toolbar=no,width=550,height=320,top=0,left=0\'); if(w){w.focus();}">'
				. '<i class="bi bi-journal-text" aria-hidden="true"></i></button>'
			: '';

		$parse['buddy_popup'] = '<button type="button" class="btn btn-outline-secondary btn-sm xnova-notif"'
			. ' title="Liste d\'amis" aria-label="Liste d\'amis"'
			. ' onclick="var w=window.open(\'/game/buddy\',\'Buddy\',\'resizable=yes,scrollbars=yes,menubar=no,toolbar=no,width=550,height=360,top=0,left=0\'); if(w){w.focus();}">'
			. '<i class="bi bi-people-fill" aria-hidden="true"></i>'
			. '<span class="xnova-notif-badge' . ($pendingRequests > 0 ? '' : ' d-none') . '" id="xnova-notif-buddy">'
			. ($pendingRequests > 0 ? $pendingRequests : '') . '</span></button>';

		// Joueurs en ligne : le même compteur que la vue générale (15 dernières
		// minutes), réduit à une icône et son infobulle.
		$playersOnline = 0;

		try {
			$onlineRow = (new \App\Repositories\UserRepository())->countOnlineSinceInclusive(time() - 15 * 60);
			$playersOnline = (int) ($onlineRow['online'] ?? 0);
		} catch (\Throwable) {
			// Un incident de comptage ne doit jamais casser l'affichage de la page.
		}

		$parse['players_online'] = '<span class="btn btn-outline-secondary btn-sm xnova-notif"'
			. ' title="joueurs en ligne" aria-label="joueurs en ligne">'
			. '<i class="bi bi-person-check-fill" aria-hidden="true"></i>'
			. '<span class="xnova-notif-badge">' . $playersOnline . '</span></span>';

		// Le tout passe dans la template
		$TopBar = parsetemplate($NavigationTPL, $parse);
	} else {
		$TopBar = "";
	}

	return $TopBar;
}

//


