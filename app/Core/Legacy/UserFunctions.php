<?php

/**
 * Fonctions legacy : CheckCookies, ChekUser, DeleteSelectedUser, ResetThisFuckingCheater, MessageForm, CheckInputStrings, RevisionTime
 */

// ===== CheckCookies =====
function CheckCookies($IsUserChecked)
{
	$authenticator = new \App\Services\Authenticator();

	return $authenticator->checkUser($IsUserChecked);
}

//

// ===== ChekUser =====
function CheckTheUser($IsUserChecked)
{
	global $user;

	includeLang('admin');

	$authenticator = new \App\Services\Authenticator();
	$Result = $authenticator->checkUser($IsUserChecked);

	$IsUserChecked = $Result['state'];

	if ($Result['record'] != false) {
		$user = $Result['record'];
		$RetValue['record'] = $user;
		$RetValue['state'] = $IsUserChecked;
	} else {
		$RetValue['record'] = array();
		$RetValue['state'] = false;
	}

	return $RetValue;
}

//

// ===== DeleteSelectedUser =====
/**
 * Efface **physiquement** un compte et tout ce qu'il possede (contre-mesure anti-triche).
 *
 * La suppression d'un compte par le panneau ne passe **pas** par ici : elle est **logique**
 * (`PlayerAdminService::softDelete()`, drapeau `DELETED`). Cette routine n'est atteinte que par
 * `ResetThisFuckingCheater()`, declenche par le controle anti-triche de la file de batiment
 * (`BuildFunctions`) : c'est le seul endroit du jeu qui efface pour de bon.
 */
function DeleteSelectedUser($UserID)
{
	$UserID = (int) $UserID;

	$users    = new \App\Repositories\UserRepository();
	$planets  = new \App\Repositories\PlanetRepository();
	$galaxies = new \App\Repositories\GalaxyRepository();

	$TheUser = $users->findFullById($UserID);

	if (is_array($TheUser) && (int) $TheUser['ally_id'] !== 0) {
		// L'alliance appartient au module `alliance` : le Coeur d'application ne nomme pas sa table, il lui
		// retire un membre (et l'alliance vidée part avec ses points, dans le classement).
		$TheAlly = \App\Database\Connection::preparedFetchOne(
			'SELECT * FROM {{table}} WHERE `id` = ?',
			array((int) $TheUser['ally_id']),
			'alliance'
		);

		if (is_array($TheAlly)) {
			$Members = (int) $TheAlly['ally_members'] - 1;

			if ($Members > 0) {
				\App\Database\Connection::preparedExecute(
					'UPDATE {{table}} SET `ally_members` = ? WHERE `id` = ?',
					array($Members, (int) $TheAlly['id']),
					'alliance'
				);
			} else {
				\App\Database\Connection::preparedExecute(
					'DELETE FROM {{table}} WHERE `id` = ?',
					array((int) $TheAlly['id']),
					'alliance'
				);
				(new \App\Repositories\StatsRepository())->purgeStatpoints((int) $TheAlly['id'], 2);
			}
		}
	}

	(new \App\Repositories\StatsRepository())->purgeStatpoints($UserID, 1);

	// Les planètes du compte et leurs traces : le depot des planetes efface les planetes et le
	// registre des lunes, celui de la galaxie les positions qu'elles annoncaient.
	$galaxies->purgePositions($planets->purgeWorldsOfOwner($UserID));

	// Ce qui porte encore le compte, table par table.
	(new \App\Repositories\MessageRepository())->purgeByOwner($UserID);
	(new \App\Repositories\FleetRepository())->purgeByOwner($UserID);
	(new \App\Repositories\RwRepository())->purgeByOwner($UserID);
	(new \App\Repositories\BuddyRepository())->purgeByOwner($UserID);

	// Les notes et les annonces appartiennent aux modules `notes` et `annonces` : le Coeur d'application
	// nomme le geste, et chaque module déposé et allumé efface ses propres tables.
	(new \App\Services\ModuleService())->purgeAccountData($UserID);

	$users->purgeAccount($UserID);
}

//

// ===== ResetThisFuckingCheater =====
/**
 * Remet un compte a zero apres une triche detectee : efface le compte (voir
 * `DeleteSelectedUser()`) puis le recree a l'identique, avec sa planete mere.
 */
function ResetThisFuckingCheater($UserID)
{
	$UserID = (int) $UserID;

	$users   = new \App\Repositories\UserRepository();
	$planets = new \App\Repositories\PlanetRepository();

	$TheUser = $users->findFullById($UserID);

	if (!is_array($TheUser)) {
		return;
	}

	$PlanetRow  = $planets->findCurrentById((int) $TheUser['id_planet']);
	$PlanetName = is_array($PlanetRow) ? (string) $PlanetRow['name'] : '';

	DeleteSelectedUser($UserID);

	if ($PlanetName != '') {
		// Creation de l'utilisateur
		$users->insertResetAccount($TheUser);

		// La planete mere : le compte a repris son identifiant, la colonie est recreee pour lui.
		CreateOnePlanetRecord(
			(int) $TheUser['galaxy'],
			(int) $TheUser['system'],
			(int) $TheUser['planet'],
			(int) $TheUser['id'],
			$PlanetName,
			true
		);

		// Recherche de la reference de la nouvelle planete (qui est unique normalement !
		$NewPlanets = $planets->findAllByOwner((int) $TheUser['id']);

		if ($NewPlanets !== array()) {
			// Mise a jour de l'enregistrement utilisateur avec les infos de sa planete mere
			$users->updateHomePlanet(
				(int) $TheUser['id'],
				(int) $NewPlanets[0]['id'],
				(int) $TheUser['galaxy'],
				(int) $TheUser['system'],
				(int) $TheUser['planet']
			);
		}
	}

	return;
}

//

// ===== MessageForm =====
function MessageForm($Title, $Message, $Goto = '', $Button = ' ok ', $TwoLines = false)
{
	$Form = "<form action=\"" . $Goto . "\" method=\"post\" class=\"card xnova-panel border-0 shadow-sm mb-3\">";
	$Form .= "<div class=\"card-header fw-semibold\">" . $Title . "</div>";
	$Form .= "<div class=\"card-body\">";
	if ($TwoLines == true) {
		$Form .= "<p class=\"mb-3\">" . $Message . "</p>";
		$Form .= "<div class=\"text-center\"><button type=\"submit\" class=\"btn btn-primary\">" . $Button . "</button></div>";
	} else {
		$Form .= "<div class=\"d-flex flex-wrap align-items-center justify-content-between gap-3\">";
		$Form .= "<span>" . $Message . "</span>";
		$Form .= "<button type=\"submit\" class=\"btn btn-primary\">" . $Button . "</button>";
		$Form .= "</div>";
	}
	$Form .= "</div>";
	$Form .= "</form>";

	return $Form;
}
// Release History
// - 1.0 Mise en fonction, Documentation

//

// ===== CheckInputStrings =====
function CheckInputStrings($String)
{
	global $ListCensure;

	$ValidString = $String;
	for ($Mot = 0; $Mot < count($ListCensure); $Mot++) {
		$pattern = '/' . preg_quote($ListCensure[$Mot], '/') . '/i';
		$ValidString = preg_replace($pattern, '*', $ValidString);
	}
	return ($ValidString);
}

//

// ===== RevisionTime =====
function RevisionTime($seconds)
{
	$days      = floor($seconds / 86400);
	$hours     = (floor(($seconds % 86400) / 3600));
	$minutes   = floor(($seconds % 3600) / 60);
	$secs      = $seconds % 60;
	$month_len = array(0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
	$year      = 1970;
	$done      = 0;
	$month_id  = 1;
	while ($days > $month_lenght) {
		$month_lenght  = ($month_id == 2 ? ($year % 4 == 0 ? 29 : $month_len[$month_id]) : $month_len[$month_id]);
		$days         -= $month_lenght;
		if ($month_id > 12) {
			$month_id = 1;
			$year++;
		} else
			$month_id++;
	}
	$days++;
	$days    = ($days < 10 ? "0" . $days : $days);
	$month   = ($month_id < 10 ? "0" . $month_id : $month_id);
	$hours   = ($hours < 10 ? "0" . $hours : $hours);
	$minutes = ($minutes < 10 ? "0" . $minutes : $minutes);
	$secs    = ($secs < 10 ? "0" . $secs : $secs);
	$ret     = ($seconds > 0 ? "$year-$month-$days<br>GMT $hours:$minutes:$secs" : "");
	return $ret;
}

//
