<?php

/**
 * Fonctions legacy : CheckPlanetBuildingQueue, CheckPlanetUsedFields, CreateOneMoonRecord, CreateOnePlanetRecord, IsTechnologieAccessible, GetBuildingTime, GetBuildingTimeLevel, GetRestPrice, GetElementPrice, GetBuildingPrice, IsElementBuyable, GetMaxConstructibleElements, GetElementRessources, ElementBuildListBox, ElementBuildListQueue, FleetBuildingPage, DefensesBuildingPage, ResearchBuildingPage, BatimentBuildingPage, CheckLabSettingsInQueue, InsertBuildListScript, AddBuildingToQueue, ShowBuildingQueue, HandleTechnologieBuild, BuildingSavePlanetRecord, RemoveBuildingFromQueue, CancelBuildingFromQueue, SetNextQueueElementOnTop, PlanetResourceUpdate, HandleElementBuildingQueue, UpdatePlanetBatimentQueueList, IsOfficierAccessible, SortUserPlanets, SetSelectedPlanet, IsVacationMode
 */

// ===== CheckPlanetBuildingQueue =====
function CheckPlanetBuildingQueue(&$CurrentPlanet, &$CurrentUser)
{
	$service = \App\Services\ModuleService::instance(\App\Services\BuildingService::class);

	return $service->checkPlanetBuildingQueue($CurrentPlanet, $CurrentUser);
}

//

// ===== CheckPlanetUsedFields =====
function CheckPlanetUsedFields(&$planet)
{
	$resource = \App\Core\GameData::resource();

	$cfc  = $planet[$resource[1]]  + $planet[$resource[2]]  + $planet[$resource[3]];
	$cfc += $planet[$resource[4]]  + $planet[$resource[12]] + $planet[$resource[14]];
	$cfc += $planet[$resource[15]] + $planet[$resource[16]] + $planet[$resource[21]] + $planet[$resource[22]];
	$cfc += $planet[$resource[23]] + $planet[$resource[24]] + $planet[$resource[31]];
	$cfc += $planet[$resource[33]] + $planet[$resource[34]] + $planet[$resource[44]];

	if ($planet['planet_type'] == '3') {
		$cfc += $planet[$resource[41]] + $planet[$resource[42]] + $planet[$resource[43]];
	}

	if ($planet['field_current'] != $cfc) {
		$planet['field_current'] = $cfc;

		$repository = new \App\Repositories\BuildingQueueRepository();
		$repository->syncFieldCurrent($planet, $cfc);
	}
}

//

// ===== CreateOneMoonRecord =====
function CreateOneMoonRecord($Galaxy, $System, $Planet, $Owner, $MoonID, $MoonName, $Chance)
{
	global $lang;

	$PlanetName = "";

	$planets  = new \App\Repositories\PlanetRepository();
	$galaxies = new \App\Repositories\GalaxyRepository();

	// La planete qui porte la lune (type 1) : son nom et ses temperatures servent au calcul.
	$MoonPlanet = $planets->findByCoords((int) $Galaxy, (int) $System, (int) $Planet, 1);
	$MoonGalaxy = $galaxies->findPosition((int) $Galaxy, (int) $System, (int) $Planet);

	if ((int) ($MoonGalaxy['id_luna'] ?? 0) === 0) {
		if (is_array($MoonPlanet) && (int) $MoonPlanet['id'] !== 0) {
			$SizeMin = 2000 + ($Chance * 100);
			$SizeMax = 6000 + ($Chance * 200);

			$PlanetName = $MoonPlanet['name'];

			$maxtemp = $MoonPlanet['temp_max'] - rand(10, 45);
			$mintemp = $MoonPlanet['temp_min'] - rand(10, 45);
			$size    = rand($SizeMin, $SizeMax);

			// Le registre des lunes d'abord : `insertMoonRegistry()` annonce l'entree
			// **existante** (`destruyed = 0`) et rend son identifiant, celui que la galaxie
			// doit annoncer - le legacy relisait la ligne pour le retrouver.
			$RegistryId = $planets->insertMoonRegistry(array(
				'id'       => (int) $MoonID,
				'name'     => ($MoonName == '') ? $lang['sys_moon'] : $MoonName,
				'id_owner' => (int) $Owner,
				'galaxy'   => (int) $Galaxy,
				'system'   => (int) $System,
				'planet'   => (int) $Planet,
				'temp_min' => (int) $mintemp,
				'temp_max' => (int) $maxtemp,
				'diameter' => (int) $size,
			));

			// Puis la ligne de galaxie, qui annonce la lune.
			$galaxies->linkMoon((int) $Galaxy, (int) $System, (int) $Planet, $RegistryId);

			// Enfin la planète lui-meme. Attention, inversion historique : `temp_min` recoit la
			// **maximale** et `temp_max` la minimale, comme dans l'original. Ne pas « corriger ».
			$planets->insertPlanetRecord(array(
				'name'              => $lang['sys_moon'],
				'id_owner'          => (int) $Owner,
				'galaxy'            => (int) $Galaxy,
				'system'            => (int) $System,
				'planet'            => (int) $Planet,
				'last_update'       => time(),
				'planet_type'       => 3,
				'image'             => 'moon',
				'diameter'          => (int) $size,
				'field_max'         => 1,
				'temp_min'          => (int) $maxtemp,
				'temp_max'          => (int) $mintemp,
				'metal'             => 0,
				'metal_perhour'     => 0,
				'metal_max'         => BASE_STORAGE_SIZE,
				'crystal'           => 0,
				'crystal_perhour'   => 0,
				'crystal_max'       => BASE_STORAGE_SIZE,
				'deuterium'         => 0,
				'deuterium_perhour' => 0,
				'deuterium_max'     => BASE_STORAGE_SIZE,
			));
		}
	}

	return $PlanetName;
}

//

// ===== CreateOnePlanetRecord =====
function PlanetSizeRandomiser($Position, $HomeWorld = false)
{
	global $game_config;

	if (!$HomeWorld) {
		$ClassicBase      = 163;
		$SettingSize      = $game_config['initial_fields'];
		$PlanetRatio      = floor(($ClassicBase / $SettingSize) * 10000) / 100;
		$RandomMin        = array(40,  50,  55, 100,  95,  80, 115, 120, 125,  75,  80,  85,  60,  40,  50);
		$RandomMax        = array(90,  95,  95, 240, 240, 230, 180, 180, 190, 125, 120, 130, 160, 300, 150);
		$CalculMin        = floor($RandomMin[$Position - 1] + ($RandomMin[$Position - 1] * $PlanetRatio) / 100);
		$CalculMax        = floor($RandomMax[$Position - 1] + ($RandomMax[$Position - 1] * $PlanetRatio) / 100);
		$RandomSize       = mt_rand($CalculMin, $CalculMax);
		$MaxAddon         = mt_rand(0, 110);
		$MinAddon         = mt_rand(0, 100);
		$Addon            = ($MaxAddon - $MinAddon);
		$PlanetFields     = ($RandomSize + $abweichung);
	} else {
		$PlanetFields     = $game_config['initial_fields'];
	}
	// `^` est le XOR historique (et non une puissance) : PHP tronque donc
	// `14 / 1.5` en 9. Le cast explicite évite la dépréciation PHP 8.1
	// « Implicit conversion from float ... to int » sans changer le résultat.
	$PlanetSize           = ($PlanetFields ^ (int) (14 / 1.5)) * 75;

	$return['diameter']   = $PlanetSize;
	$return['field_max']  = $PlanetFields;
	return $return;
}

function CreateOnePlanetRecord($Galaxy, $System, $Position, $PlanetOwnerID, $PlanetName = '', $HomeWorld = false)
{
	global $lang, $game_config;

	$planets  = new \App\Repositories\PlanetRepository();
	$galaxies = new \App\Repositories\GalaxyRepository();

	// Avant tout, on verifie s'il existe deja une planete a cet endroit. Seuls les planètes
	// **existants** comptent : une colonie abandonnee garde sa ligne, et elle bloquerait sinon
	// la colonisation d'une position redevenue libre.
	$PlanetExist = $planets->countLivePlanetsAt((int) $Galaxy, (int) $System, (int) $Position) > 0;

	// La position est occupee : je ne peux pas m'y poser.
	if (!$PlanetExist) {
		// Une colonie abandonnée **récemment** réserve encore ses coordonnées : la
		// position redevient colonisable une fois le délai du jeu écoulé (24 h par
		// défaut, voir `GameConstants::abandonedPositionDelay()`).
		$ReservedDelay = \App\Core\GameConstants::abandonedPositionDelay();

		if ($planets->isPositionReserved((int) $Galaxy, (int) $System, (int) $Position, $ReservedDelay)) {
			return false;
		}

		$planet                      = PlanetSizeRandomiser($Position, $HomeWorld);
		// Même XOR historique que dans `PlanetSizeRandomiser()` : le cast
		// explicite de `14 / 1.5` (9.333...) évite la dépréciation PHP 8.1.
		$planet['diameter']          = ($planet['field_max'] ^ (int) (14 / 1.5)) * 75;
		$planet['metal']             = BUILD_METAL;
		$planet['crystal']           = BUILD_CRISTAL;
		$planet['deuterium']         = BUILD_DEUTERIUM;
		$planet['metal_perhour']     = $game_config['metal_basic_income'];
		$planet['crystal_perhour']   = $game_config['crystal_basic_income'];
		$planet['deuterium_perhour'] = $game_config['deuterium_basic_income'];
		$planet['metal_max']         = BASE_STORAGE_SIZE;
		$planet['crystal_max']       = BASE_STORAGE_SIZE;
		$planet['deuterium_max']     = BASE_STORAGE_SIZE;

		// Posistion  1 -  3: 80% entre  40 et  70 Cases (  55+ / -15 )
		// Posistion  4 -  6: 80% entre 120 et 310 Cases ( 215+ / -95 )
		// Posistion  7 -  9: 80% entre 105 et 195 Cases ( 150+ / -45 )
		// Posistion 10 - 12: 80% entre  75 et 125 Cases ( 100+ / -25 )
		// Posistion 13 - 15: 80% entre  60 et 190 Cases ( 125+ / -65 )

		$planet['galaxy'] = $Galaxy;
		$planet['system'] = $System;
		$planet['planet'] = $Position;

		if ($Position == 1 || $Position == 2 || $Position == 3) {
			$PlanetType         = array('trocken');
			$PlanetClass        = array('planet');
			$PlanetDesign       = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10');
			$planet['temp_min'] = rand(0, 100);
			$planet['temp_max'] = $planet['temp_min'] + 40;
		} elseif ($Position == 4 || $Position == 5 || $Position == 6) {
			$PlanetType         = array('dschjungel');
			$PlanetClass        = array('planet');
			$PlanetDesign       = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10');
			$planet['temp_min'] = rand(-25, 75);
			$planet['temp_max'] = $planet['temp_min'] + 40;
		} elseif ($Position == 7 || $Position == 8 || $Position == 9) {
			$PlanetType         = array('normaltemp');
			$PlanetClass        = array('planet');
			$PlanetDesign       = array('01', '02', '03', '04', '05', '06', '07');
			$planet['temp_min'] = rand(-50, 50);
			$planet['temp_max'] = $planet['temp_min'] + 40;
		} elseif ($Position == 10 || $Position == 11 || $Position == 12) {
			$PlanetType         = array('wasser');
			$PlanetClass        = array('planet');
			$PlanetDesign       = array('01', '02', '03', '04', '05', '06', '07', '08', '09');
			$planet['temp_min'] = rand(-75, 25);
			$planet['temp_max'] = $planet['temp_min'] + 40;
		} elseif ($Position == 13 || $Position == 14 || $Position == 15) {
			$PlanetType         = array('eis');
			$PlanetClass        = array('planet');
			$PlanetDesign       = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10');
			$planet['temp_min'] = rand(-100, 10);
			$planet['temp_max'] = $planet['temp_min'] + 40;
		} else {
			$PlanetType         = array('dschjungel', 'gas', 'normaltemp', 'trocken', 'wasser', 'wuesten', 'eis');
			$PlanetClass        = array('planet');
			$PlanetDesign       = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '00',);
			$planet['temp_min'] = rand(-120, 10);
			$planet['temp_max'] = $planet['temp_min'] + 40;
		}

		$planet['image']       = $PlanetType[rand(0, count($PlanetType) - 1)];
		$planet['image']      .= $PlanetClass[rand(0, count($PlanetClass) - 1)];
		$planet['image']      .= $PlanetDesign[rand(0, count($PlanetDesign) - 1)];
		$planet['planet_type'] = 1;
		$planet['id_owner']    = $PlanetOwnerID;
		$planet['last_update'] = time();
		$planet['name']        = ($PlanetName == '') ? $lang['sys_colo_defaultname'] : $PlanetName;

		// La colonie : ses vingt et une colonnes vivent dans le depot des planetes, et son
		// identifiant est celui que l'insertion vient de rendre - plus de relecture a la
		// recherche d'un « nouvel » identifiant que la colonie abandonnee de la meme position
		// aurait pu faire prendre.
		$PlanetId = $planets->insertPlanetRecord(array(
			'name'              => $planet['name'],
			'id_owner'          => $planet['id_owner'],
			'galaxy'            => $planet['galaxy'],
			'system'            => $planet['system'],
			'planet'            => $planet['planet'],
			'last_update'       => $planet['last_update'],
			'planet_type'       => $planet['planet_type'],
			'image'             => $planet['image'],
			'diameter'          => $planet['diameter'],
			'field_max'         => $planet['field_max'],
			'temp_min'          => $planet['temp_min'],
			'temp_max'          => $planet['temp_max'],
			'metal'             => $planet['metal'],
			'metal_perhour'     => $planet['metal_perhour'],
			'metal_max'         => $planet['metal_max'],
			'crystal'           => $planet['crystal'],
			'crystal_perhour'   => $planet['crystal_perhour'],
			'crystal_max'       => $planet['crystal_max'],
			'deuterium'         => $planet['deuterium'],
			'deuterium_perhour' => $planet['deuterium_perhour'],
			'deuterium_max'     => $planet['deuterium_max'],
		));

		// La ligne de galaxie annonce la planete : `linkPlanet()` l'ecrit ou la cree
		// (`INSERT ... ON DUPLICATE KEY UPDATE`), la ou le legacy lisait la ligne pour
		// choisir entre un UPDATE et un INSERT.
		$galaxies->linkPlanet(
			(int) $planet['galaxy'],
			(int) $planet['system'],
			(int) $planet['planet'],
			$PlanetId
		);

		$RetValue = true;
	} else {

		$RetValue = false;
	}

	return $RetValue;
}

//

// ===== IsTechnologieAccessible =====
function IsTechnologieAccessible($user, $planet, $Element)
{
	global $requeriments, $resource;

	if (isset($requeriments[$Element])) {
		$enabled = true;
		foreach ($requeriments[$Element] as $ReqElement => $EleLevel) {
			if (@$user[$resource[$ReqElement]] && $user[$resource[$ReqElement]] >= $EleLevel) {
				// break;
			} elseif ($planet[$resource[$ReqElement]] && $planet[$resource[$ReqElement]] >= $EleLevel) {
				$enabled = true;
			} else {
				return false;
			}
		}
		return $enabled;
	} else {
		return true;
	}
}

//

// ===== GetBuildingTime =====
function GetBuildingTime($user, $planet, $Element)
{
	global $pricelist, $resource, $reslist, $game_config;

	// La duree vient du service resolu (un module peut le surcharger) : la formule
	// reste ici, seule la part des officiers lui est demandee.
	$bonusService = \App\Services\ModuleService::resolve(\App\Services\BuildingService::class);

	$level = ($planet[$resource[$Element]]) ? $planet[$resource[$Element]] : $user[$resource[$Element]];
	if (in_array($Element, $reslist['build'])) {
		// Pour un batiment ...
		$cost_metal   = floor($pricelist[$Element]['metal']   * pow($pricelist[$Element]['factor'], $level));
		$cost_crystal = floor($pricelist[$Element]['crystal'] * pow($pricelist[$Element]['factor'], $level));
		$time         = ((($cost_crystal) + ($cost_metal)) / $game_config['game_speed']) * (1 / ($planet[$resource['14']] + 1)) * pow(0.5, $planet[$resource['15']]);
		$time         = floor(($time * 60 * 60) * (1 - $bonusService::durationBonus($user, 'build')));
	} elseif (in_array($Element, $reslist['tech'])) {
		// Pour une recherche
		$cost_metal   = floor($pricelist[$Element]['metal']   * pow($pricelist[$Element]['factor'], $level));
		$cost_crystal = floor($pricelist[$Element]['crystal'] * pow($pricelist[$Element]['factor'], $level));
		$intergal_lab = $user[$resource[123]];
		if ($intergal_lab < "1") {
			$lablevel = $planet[$resource['31']];
		} elseif ($intergal_lab >= "1") {
			// Seuls les laboratoires des planètes **existants** comptent : une colonie
			// abandonnée ne doit plus servir le laboratoire intergalactique.
			$empire = (new \App\Repositories\PlanetRepository())->findAllByOwner((int) $user['id']);
			$NbLabs = 0;
			// `findAllByOwner()` rend le tableau des lignes : la boucle du legacy attendait
			// une ressource de requête (`mysql_fetch_array`), ce qui levait une erreur fatale
			// dès que le laboratoire intergalactique était construit.
			foreach ($empire as $colonie) {
				$techlevel[$NbLabs] = $colonie[$resource['31']];
				$NbLabs++;
			}
			if ($intergal_lab >= "1") {
				$lablevel = 0;
				for ($lab = 1; $lab <= $intergal_lab; $lab++) {
					asort($techlevel);
					$lablevel += $techlevel[$lab - 1];
				}
			}
		}
		$time         = (($cost_metal + $cost_crystal) / $game_config['game_speed']) / (($lablevel + 1) * 2);
		// Accelerateur de particules : la duree d'une recherche est divisee par deux par niveau.
		$time        *= \App\Core\ResearchMath::accelerationFactor((int) ($planet[$resource[16]] ?? 0));
		$time         = floor(($time * 60 * 60) * (1 - $bonusService::durationBonus($user, 'research')));
	} elseif (in_array($Element, $reslist['defense'])) {
		// Pour les defenses ou la flotte 'tarif fixe' durée adaptée a u niveau nanite et usine robot
		$time         = (($pricelist[$Element]['metal'] + $pricelist[$Element]['crystal']) / $game_config['game_speed']) * (1 / ($planet[$resource['21']] + 1)) * pow(1 / 2, $planet[$resource['15']]);
		$time         = floor(($time * 60 * 60) * (1 - $bonusService::durationBonus($user, 'defense')));
	} elseif (in_array($Element, $reslist['fleet'])) {
		$time         = (($pricelist[$Element]['metal'] + $pricelist[$Element]['crystal']) / $game_config['game_speed']) * (1 / ($planet[$resource['21']] + 1)) * pow(1 / 2, $planet[$resource['15']]);
		$time         = floor(($time * 60 * 60) * (1 - $bonusService::durationBonus($user, 'fleet')));
	}

	// La duree du chantier ne depend que de game_speed (game_config) : la file
	// enregistree et le temps affiche restent donc coherents.
	return $time;
}

//

// ===== GetBuildingTimeLevel =====
function GetBuildingTimeLevel($user, $planet, $Element, $level)
{
	global $pricelist, $resource, $reslist, $game_config;

	// Meme service que GetBuildingTime() : les deux durees doivent rester identiques.
	$bonusService = \App\Services\ModuleService::resolve(\App\Services\BuildingService::class);

	$level -= 1;

	if (in_array($Element, $reslist['build'])) {
		// Pour un batiment ...
		$cost_metal   = floor($pricelist[$Element]['metal']   * pow($pricelist[$Element]['factor'], $level));
		$cost_crystal = floor($pricelist[$Element]['crystal'] * pow($pricelist[$Element]['factor'], $level));
		$time         = ((($cost_crystal) + ($cost_metal)) / $game_config['game_speed']) * (1 / ($planet[$resource['14']] + 1)) * pow(0.5, $planet[$resource['15']]);
		$time         = floor(($time * 60 * 60) * (1 - $bonusService::durationBonus($user, 'build')));
	} elseif (in_array($Element, $reslist['tech'])) {
		// Pour une recherche
		$cost_metal   = floor($pricelist[$Element]['metal']   * pow($pricelist[$Element]['factor'], $level));
		$cost_crystal = floor($pricelist[$Element]['crystal'] * pow($pricelist[$Element]['factor'], $level));
		$intergal_lab = $user[$resource[123]];
		if ($intergal_lab < "1") {
			$lablevel = $planet[$resource['31']];
		} elseif ($intergal_lab >= "1") {
			// Seuls les laboratoires des planètes **existants** comptent : une colonie
			// abandonnée ne doit plus servir le laboratoire intergalactique.
			$empire = (new \App\Repositories\PlanetRepository())->findAllByOwner((int) $user['id']);
			$NbLabs = 0;
			foreach ($empire as $colonie) {
				$techlevel[$NbLabs] = $colonie[$resource['31']];
				$NbLabs++;
			}
			if ($intergal_lab >= "1") {
				$lablevel = 0;
				for ($lab = 1; $lab <= $intergal_lab; $lab++) {
					asort($techlevel);
					$lablevel += $techlevel[$lab - 1];
				}
			}
		}
		$time         = (($cost_metal + $cost_crystal) / $game_config['game_speed']) / (($lablevel + 1) * 2);
		// Accelerateur de particules : la duree d'une recherche est divisee par deux par niveau.
		$time        *= \App\Core\ResearchMath::accelerationFactor((int) ($planet[$resource[16]] ?? 0));
		$time         = floor(($time * 60 * 60) * (1 - $bonusService::durationBonus($user, 'research')));
	} elseif (in_array($Element, $reslist['defense'])) {
		// Pour les defenses ou la flotte 'tarif fixe' durée adaptée a u niveau nanite et usine robot
		$time         = (($pricelist[$Element]['metal'] + $pricelist[$Element]['crystal']) / $game_config['game_speed']) * (1 / ($planet[$resource['21']] + 1)) * pow(1 / 2, $planet[$resource['15']]);
		$time         = floor(($time * 60 * 60) * (1 - $bonusService::durationBonus($user, 'defense')));
	} elseif (in_array($Element, $reslist['fleet'])) {
		$time         = (($pricelist[$Element]['metal'] + $pricelist[$Element]['crystal']) / $game_config['game_speed']) * (1 / ($planet[$resource['21']] + 1)) * pow(1 / 2, $planet[$resource['15']]);
		$time         = floor(($time * 60 * 60) * (1 - $bonusService::durationBonus($user, 'fleet')));
	}

	// Meme duree que GetBuildingTime() : les durees affichees pour la file
	// correspondent au temps reellement applique.
	return $time;
}

//

// ===== GetRestPrice =====
function GetRestPrice($user, $planet, $Element, $userfactor = true)
{
	global $pricelist, $resource, $lang;

	if ($userfactor) {
		$level = ($planet[$resource[$Element]]) ? $planet[$resource[$Element]] : $user[$resource[$Element]];
	}

	$array = array(
		'metal'      => $lang["Metal"],
		'crystal'    => $lang["Crystal"],
		'deuterium'  => $lang["Deuterium"],
		'energy_max' => $lang["Energy"]
	);

	$text  = '<span class="text-body-secondary">' . $lang['Rest_ress'] . ": ";
	foreach ($array as $ResType => $ResTitle) {
		if ($pricelist[$Element][$ResType] != 0) {
			$text .= $ResTitle . ": ";
			if ($userfactor) {
				$cost = floor($pricelist[$Element][$ResType] * pow($pricelist[$Element]['factor'], $level));
			} else {
				$cost = floor($pricelist[$Element][$ResType]);
			}
			$class = ($cost > $planet[$ResType]) ? 'text-danger' : 'text-success';
			$text .= '<strong class="' . $class . '">' . pretty_number($planet[$ResType] - $cost) . '</strong> ';
		}
	}
	$text .= "</span>";

	return $text;
}

//

// ===== GetElementPrice =====
function GetElementPrice($user, $planet, $Element, $userfactor = true)
{
	global $pricelist, $resource, $lang;

	if ($userfactor) {
		$level = ($planet[$resource[$Element]]) ? $planet[$resource[$Element]] : $user[$resource[$Element]];
	}

	$is_buyeable = true;
	$array = array(
		'metal'      => $lang["Metal"],
		'crystal'    => $lang["Crystal"],
		'deuterium'  => $lang["Deuterium"],
		'energy_max' => $lang["Energy"]
	);

	$text = $lang['Requires'] . ": ";
	foreach ($array as $ResType => $ResTitle) {
		if ($pricelist[$Element][$ResType] != 0) {
			$text .= $ResTitle . ": ";
			if ($userfactor) {
				$cost = floor($pricelist[$Element][$ResType] * pow($pricelist[$Element]['factor'], $level));
			} else {
				$cost = floor($pricelist[$Element][$ResType]);
			}
			if ($cost > $planet[$ResType]) {
				$text .= '<strong class="text-danger" title="-' . pretty_number($cost - $planet[$ResType]) . '">';
				$text .= '<span class="noresources">' . pretty_number($cost) . '</span></strong> ';
				$is_buyeable = false;
			} else {
				$text .= '<strong class="text-success">';
				$text .= '<span class="noresources">' . pretty_number($cost) . '</span></strong> ';
			}
		}
	}
	return $text;
}

//

// ===== GetBuildingPrice =====
function GetBuildingPrice($CurrentUser, $CurrentPlanet, $Element, $Incremental = true, $ForDestroy = false)
{
	global $pricelist, $resource;

	if ($Incremental) {
		$level = ($CurrentPlanet[$resource[$Element]]) ? $CurrentPlanet[$resource[$Element]] : $CurrentUser[$resource[$Element]];
	}

	$array = array('metal', 'crystal', 'deuterium', 'energy_max');
	foreach ($array as $ResType) {
		if ($Incremental) {
			$cost[$ResType] = floor($pricelist[$Element][$ResType] * pow($pricelist[$Element]['factor'], $level));
		} else {
			$cost[$ResType] = floor($pricelist[$Element][$ResType]);
		}

		if ($ForDestroy == true) {
			$cost[$ResType]  = floor($cost[$ResType]) / 2;
			$cost[$ResType] /= 2;
		}
	}

	return $cost;
}

//

// ===== IsElementBuyable =====
function IsElementBuyable($CurrentUser, $CurrentPlanet, $Element, $Incremental = true, $ForDestroy = false)
{
	global $pricelist, $resource;

	if (IsVacationMode($CurrentUser)) {
		return false;
	}

	if ($Incremental) {
		$level  = ($CurrentPlanet[$resource[$Element]]) ? $CurrentPlanet[$resource[$Element]] : $CurrentUser[$resource[$Element]];
	}

	$RetValue = true;
	$array    = array('metal', 'crystal', 'deuterium', 'energy_max');

	foreach ($array as $ResType) {
		if ($pricelist[$Element][$ResType] != 0) {
			if ($Incremental) {
				$cost[$ResType]  = floor($pricelist[$Element][$ResType] * pow($pricelist[$Element]['factor'], $level));
			} else {
				$cost[$ResType]  = floor($pricelist[$Element][$ResType]);
			}

			if ($ForDestroy) {
				$cost[$ResType]  = floor($cost[$ResType] / 2);
			}

			if ($cost[$ResType] > $CurrentPlanet[$ResType]) {
				$RetValue = false;
			}
		}
	}
	return $RetValue;
}

//

// ===== GetMaxConstructibleElements =====
function GetMaxConstructibleElements($Element, $Ressources)
{
	global $pricelist;
	// On test les 4 Type de ressource pour voir si au moins on sait en construire 1
	if ($pricelist[$Element]['metal'] != 0) {
		$ResType_1_Needed = $pricelist[$Element]['metal'];
		$Buildable        = floor($Ressources["metal"] / $ResType_1_Needed);
		$MaxElements      = $Buildable;
	}

	if ($pricelist[$Element]['crystal'] != 0) {
		$ResType_2_Needed = $pricelist[$Element]['crystal'];
		$Buildable        = floor($Ressources["crystal"] / $ResType_2_Needed);
	}
	if (!isset($MaxElements)) {
		$MaxElements      = $Buildable;
	} elseif ($MaxElements > $Buildable) {
		$MaxElements      = $Buildable;
	}

	if ($pricelist[$Element]['deuterium'] != 0) {
		$ResType_3_Needed = $pricelist[$Element]['deuterium'];
		$Buildable        = floor($Ressources["deuterium"] / $ResType_3_Needed);
	}
	if (!isset($MaxElements)) {
		$MaxElements      = $Buildable;
	} elseif ($MaxElements > $Buildable) {
		$MaxElements      = $Buildable;
	}

	if ($pricelist[$Element]['energy'] != 0) {
		$ResType_4_Needed = $pricelist[$Element]['energy'];
		$Buildable        = floor($Ressources["energy_max"] / $ResType_4_Needed);
	}
	if ($Buildable < 1) {
		$MaxElements      = 0;
	}

	return $MaxElements;
}
// Verion History
// - 1.0 Version initiale (creation)
// - 1.1 Correction bug ressources n�gatives ...
// - 1.2 Correction bug quand pas de m�tal

//

// ===== GetElementRessources =====
function GetElementRessources($Element, $Count)
{
	global $pricelist;

	$ResType['metal']     = ($pricelist[$Element]['metal']     * $Count);
	$ResType['crystal']   = ($pricelist[$Element]['crystal']   * $Count);
	$ResType['deuterium'] = ($pricelist[$Element]['deuterium'] * $Count);

	return $ResType;
}

//

// ===== ElementBuildListBox =====
function ElementBuildListBox($CurrentUser, $CurrentPlanet)
{
	global $lang;

	// File du hangar rendue par le serveur (App\Core\QueueRenderer).
	$queueService = new \App\Services\QueueService();
	$items        = $queueService->hangarItems($CurrentPlanet, $CurrentUser);
	$labels       = \App\Core\QueueRenderer::labels($lang ?? array(), \App\Core\QueueRenderer::DOMAIN_HANGAR);

	$parse = $lang;
	$parse['QueueList'] = \App\Core\QueueRenderer::renderList(
		\App\Core\QueueRenderer::DOMAIN_HANGAR,
		$items,
		$labels
	);

	// Temps restant de la file : fin du dernier element (meme calcul que les lignes).
	$lastItem = $items === array() ? null : $items[count($items) - 1];
	$restTime = $lastItem === null ? 0 : max(0, (int) $lastItem['end_time'] - time());
	$parse['pretty_time_b_hangar'] = \App\Core\Format::prettyTime($restTime);

	return parsetemplate(gettemplate('buildings_script'), $parse);
}

//

// ===== ElementBuildListQueue =====
function ElementBuildListQueue($CurrentUser, $CurrentPlanet)
{
	// Jamais appelé pour le moment donc totalement modifiable !

	/*
alter table `ogame`.`game_planets`
change `name` `name` varchar (255) NULL COLLATE latin1_general_ci,
change `b_building_id` `b_building_id` text NULL COLLATE latin1_general_ci,
change `b_tech_id` `b_tech_id` text NULL COLLATE latin1_general_ci,
change `b_hangar_id` `b_hangar_id` text NULL COLLATE latin1_general_ci,
change `image` `image` varchar (32) DEFAULT 'normaltempplanet01' NOT NULL COLLATE latin1_general_ci,
change `b_building_queue` `b_building_queue` text NULL COLLATE latin1_general_ci,
change `unbau` `unbau` varchar (100) NULL COLLATE latin1_general_ci;

*/
	global $lang, $pricelist;

	// Array del b_hangar_id
	$b_building_id = explode(';', $CurrentPlanet['b_building_queue']);

	$a = $b = $c = "";
	foreach ($b_hangar_id as $n => $array) {
		if ($array != '') {
			$array = explode(',', $array);
			// calculamos el tiempo
			$time = GetBuildingTime($user, $CurrentPlanet, $array[0]);
			$totaltime += $time * $array[1];
			$c .= "$time,";
			$b .= "'{$lang['tech'][$array[0]]}',";
			$a .= "{$array[1]},";
		}
	}

	$parse = $lang;
	$parse['a'] = $a;
	$parse['b'] = $b;
	$parse['c'] = $c;
	$parse['b_hangar_id_plus'] = $CurrentPlanet['b_hangar'];

	$parse['pretty_time_b_hangar'] = pretty_time($totaltime - $CurrentPlanet['b_hangar']); // //$CurrentPlanet['last_update']

	$text .= parsetemplate(gettemplate('buildings_script'), $parse);

	return $text;
}

//

// ===== FleetBuildingPage =====
function FleetBuildingPage(&$CurrentPlanet, $CurrentUser)
{
	global $pricelist, $lang, $resource, $dpath, $_POST;

	// La quantite produite vient du service resolu (un module peut la surcharger) :
	// la boucle du hangar reste ici, seule la part des officiers lui est demandee.
	$bonusService = \App\Services\ModuleService::resolve(\App\Services\BuildingService::class);

	if (isset($_POST['amounts'])) {
		// On vient de Cliquer ' Construire '
		// Et y a une liste de doléances
		$AddedInQueue                     = false;
		// Ici, on sait precisement ce qu'on aimerait bien construire ...
		foreach ($_POST['amounts'] as $Element => $Count) {
			// Construction d'Element recuperés sur la page de Flotte ...
			// ATTENTION ! La file d'attente Flotte est Commune a celle des Defenses
			// Dans amounts, on devrait trouver un tableau des elements constructibles et du nombre d'elements souhaités

			$Element = intval($Element);
			$Count   = preg_replace('/[^0-9]/', '', $Count);
			if ($Count > MAX_FLEET_OR_DEFS_PER_ROW) {
				$Count = MAX_FLEET_OR_DEFS_PER_ROW;
			}

			if ($Count != 0) {
				// On verifie si on a les technologies necessaires a la construction de l'element
				if (IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $Element)) {
					// On verifie combien on sait faire de cet element au max
					$MaxElements   = GetMaxConstructibleElements($Element, $CurrentPlanet);
					// Si pas assez de ressources, on ajuste le nombre d'elements
					if ($Count > $MaxElements) {
						$Count = $MaxElements;
					}
					$Ressource = GetElementRessources($Element, $Count);
					$BuildTime = GetBuildingTime($CurrentUser, $CurrentPlanet, $Element);
					if ($Count >= 1) {
						if ($BuildTime > 0) {
							$CurrentPlanet['metal']           -= $Ressource['metal'];
							$CurrentPlanet['crystal']         -= $Ressource['crystal'];
							$CurrentPlanet['deuterium']       -= $Ressource['deuterium'];
							// Le « destructeur » double les EDLM : c'est le service resolu qui le dit,
							// la boucle garde sa formule et son ordre d'ecriture.
							$Count = $bonusService::shipCount($CurrentUser, $Element, $Count);
							$CurrentPlanet['b_hangar_id']     .= "" . $Element . "," . $Count . ";";
						} else {
							$PlanetRow = (new \App\Repositories\PlanetRepository())->findCurrentById((int) $CurrentPlanet['id']);
							$NombreVaisseauxActuel = (int) ($PlanetRow[$resource[$Element]] ?? 0);

							$CurrentPlanet['metal'] -= $Ressource['metal'];
							$CurrentPlanet['crystal'] -= $Ressource['crystal'];
							$CurrentPlanet['deuterium'] -= $Ressource['deuterium'];
							$NewFleetNumber = $CurrentPlanet[$resource[$Element]] + $Count;
							// Meme quantite que la branche ci-dessus : l'ecriture juste en dessous
							// depend de $Count.
							$Count = $bonusService::shipCount($CurrentUser, $Element, $Count);
							// Colonne de vaisseau verifiee par le depot, quantite liee : l'ancienne valeur a
							// ete relue juste au-dessus, la ligne est celle de la planete courante.
							(new \App\Repositories\PlanetRepository())->setField(
								(int) $CurrentPlanet['id'],
								(string) $resource[$Element],
								$NombreVaisseauxActuel + $Count
							);
						}
					}
				}
			}
		}

		// Persiste la file du hangar et les ressources consommees
		$HangarRepository = new \App\Repositories\BuildingQueueRepository();
		$HangarRepository->saveHangarShips($CurrentPlanet);

		// ShowTopNavigationBar() reecrit la planete globale : on la resynchronise
		// sinon la file qui vient d'etre creee serait ecrasee.
		if (isset($GLOBALS['planetrow']) && is_array($GLOBALS['planetrow'])) {
			foreach (array('metal', 'crystal', 'deuterium', 'b_hangar_id', 'b_hangar') as $SyncField) {
				$GLOBALS['planetrow'][$SyncField] = $CurrentPlanet[$SyncField];
			}
		}

		// Mode « application seule » (API) : la commande a ete fournie dans
		// $_POST['amounts'] et le rendu termine la requete (display() -> die()).
		if (!empty($GLOBALS['xnova_shipyard_apply'])) {
			return;
		}
	}
	// -------------------------------------------------------------------------------------------------------
	// S'il n'y a pas de Chantier ...
	if ($CurrentPlanet[$resource[21]] == 0) {
		// Veuillez avoir l'obligeance de construire le Chantier Spacial !!
		message($lang['need_hangar'], $lang['tech'][21]);
	}

	// -------------------------------------------------------------------------------------------------------
	// Construction de la page du Chantier (car si j'arrive ici ... c'est que j'ai tout ce qu'il faut pour ...
	// Les rendus sont communes aux deux pages du hangar (App\Core\UnitCard) :
	// elles ne peuvent donc plus diverger entre le chantier spatial et la defense.
	$parse = $lang;
	$parse['buildlist']    = \App\Core\UnitCard::forRange($CurrentUser, $CurrentPlanet, 202, 399);
	$parse['buildinglist'] = \App\Core\UnitCard::queue($CurrentUser, $CurrentPlanet);
	$page .= parsetemplate(gettemplate('buildings_fleet'), $parse);

	display($page, $lang['Fleet']);
}

//

// ===== DefensesBuildingPage =====
function DefensesBuildingPage(&$CurrentPlanet, $CurrentUser)
{
	global $lang, $resource, $dpath, $_POST;

	if (isset($_POST['amounts'])) {
		// On vient de Cliquer ' Construire '

		// Et y a une liste de doléances
		// Ici, on sait precisement ce qu'on aimerait bien construire ...

		// Gestion de la place disponible dans les silos !
		$Missiles[502] = $CurrentPlanet[$resource[502]];
		$Missiles[503] = $CurrentPlanet[$resource[503]];
		$SiloSize      = $CurrentPlanet[$resource[44]];
		$MaxMissiles   = $SiloSize * 10;
		// On prend les missiles deja dans la queue de fabrication aussi (ca aide)
		$BuildQueue    = $CurrentPlanet['b_hangar_id'];
		$BuildArray    = explode(";", $BuildQueue);
		for ($QElement = 0; $QElement < count($BuildArray); $QElement++) {
			$ElmentArray = explode(",", $BuildArray[$QElement]);
			if ($ElmentArray[502] != 0) {
				$Missiles[502] += $ElmentArray[502];
			} elseif ($ElmentArray[503] != 0) {
				$Missiles[503] += $ElmentArray[503];
			}
		}
		foreach ($_POST['amounts'] as $Element => $Count) {
			// Construction d'Element recuperés sur la page de Flotte ...
			// ATTENTION ! La file d'attente Flotte est Commune a celle des Defenses
			// Dans amounts, on devrait trouver un tableau des elements constructibles et du nombre d'elements souhaités

			$Element = intval($Element);
			$Count   = intval($Count);
			if ($Count > MAX_FLEET_OR_DEFS_PER_ROW) {
				$Count = MAX_FLEET_OR_DEFS_PER_ROW;
			}

			if ($Count != 0) {
				// Cas particulier des éléments uniques (petit et grand boucliers) :
				// un seul exemplaire, ni construit ni déjà en file. La règle est
				// partagée avec la consommation de la file (ShipyardService).
				$Allowance = \App\Services\ShipyardService::uniqueAllowance($Element, $CurrentPlanet);
				if ($Allowance >= 0) {
					$Count = $Allowance;
				}

				// On verifie si on a les technologies necessaires a la construction de l'element
				if (IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $Element)) {
					// On verifie combien on sait faire de cet element au max
					$MaxElements   = GetMaxConstructibleElements($Element, $CurrentPlanet);

					// Testons si on a de la place pour ces nouveaux missiles !
					if ($Element == 502 || $Element == 503) {
						// Cas particulier des missiles
						$ActuMissiles  = $Missiles[502] + (2 * $Missiles[503]);
						$MissilesSpace = $MaxMissiles - $ActuMissiles;
						if ($Element == 502) {
							if ($Count > $MissilesSpace) {
								$Count = $MissilesSpace;
							}
						} else {
							if ($Count > floor($MissilesSpace / 2)) {
								$Count = floor($MissilesSpace / 2);
							}
						}
						if ($Count > $MaxElements) {
							$Count = $MaxElements;
						}
						$Missiles[$Element] += $Count;
					} else {
						// Si pas assez de ressources, on ajuste le nombre d'elements
						if ($Count > $MaxElements) {
							$Count = $MaxElements;
						}
					}

					$Ressource = GetElementRessources($Element, $Count);
					$BuildTime = GetBuildingTime($CurrentUser, $CurrentPlanet, $Element);
					if ($Count >= 1) {
						$CurrentPlanet['metal']           -= $Ressource['metal'];
						$CurrentPlanet['crystal']         -= $Ressource['crystal'];
						$CurrentPlanet['deuterium']       -= $Ressource['deuterium'];
						$CurrentPlanet['b_hangar_id']     .= "" . $Element . "," . $Count . ";";
					}
				}
			}
		}

		// Persiste la file du hangar et les ressources consommees
		$HangarRepository = new \App\Repositories\BuildingQueueRepository();
		$HangarRepository->saveHangarShips($CurrentPlanet);

		// ShowTopNavigationBar() reecrit la planete globale : on la resynchronise
		// sinon la file qui vient d'etre creee serait ecrasee.
		if (isset($GLOBALS['planetrow']) && is_array($GLOBALS['planetrow'])) {
			foreach (array('metal', 'crystal', 'deuterium', 'b_hangar_id', 'b_hangar') as $SyncField) {
				$GLOBALS['planetrow'][$SyncField] = $CurrentPlanet[$SyncField];
			}
		}

		// Mode « application seule » (API) : la commande a ete fournie dans
		// $_POST['amounts'] et le rendu termine la requete (display() -> die()).
		if (!empty($GLOBALS['xnova_shipyard_apply'])) {
			return;
		}
	}

	// -------------------------------------------------------------------------------------------------------
	// S'il n'y a pas de Chantier ...
	if ($CurrentPlanet[$resource[21]] == 0) {
		// Veuillez avoir l'obligeance de construire le Chantier Spacial !!
		message($lang['need_hangar'], $lang['tech'][21]);
	}

	// -------------------------------------------------------------------------------------------------------
	// Construction de la page du Chantier (car si j'arrive ici ... c'est que j'ai tout ce qu'il faut pour ...
	// Memes rendus que le chantier spatial (App\Core\UnitCard), avec les
	// exceptions de la defense : les boucliers n'existent qu'une fois.
	$parse = $lang;
	$parse['buildlist']    = \App\Core\UnitCard::forRange(
		$CurrentUser,
		$CurrentPlanet,
		401,
		599,
		function (int $Element) use ($CurrentPlanet, $lang) {
			// Règle unique des éléments uniques (ShipyardService) : elle regarde la
			// bonne colonne **et** la file de cet élément-là. L'ancienne version
			// testait toujours le petit bouclier : dès qu'il était construit, le
			// grand n'était plus commandable.
			$Allowance = \App\Services\ShipyardService::uniqueAllowance($Element, $CurrentPlanet);

			if ($Allowance < 0) {
				return '';
			}

			return $Allowance === 0 ? $lang['only_one'] : '';
		}
	);
	$parse['buildinglist'] = \App\Core\UnitCard::queue($CurrentUser, $CurrentPlanet);
	// fragmento de template
	$page .= parsetemplate(gettemplate('buildings_defense'), $parse);

	display($page, $lang['Defense']);
}
// Version History
// - 1.0 Modularisation
// - 1.1 Correction mise en place d'une limite max d'elements constructibles par ligne
// - 1.2 Correction limitation bouclier meme si en queue de fabrication
//

//

// ===== ResearchBuildingPage =====
function ResearchBuildingPage(&$CurrentPlanet, $CurrentUser, $InResearch, $ThePlanet)
{
	$_GET['tech'] = preg_replace('/[^0-9]/', '', $_GET['tech']);
	global $lang, $resource, $reslist, $dpath, $game_config, $_GET;

	$NoResearchMessage = "";
	$bContinue         = true;
	// Deja est qu'il y a un laboratoire sur la planete ???
	if ($CurrentPlanet[$resource[31]] == 0) {
		message($lang['no_laboratory'], $lang['Research']);
	}
	// Ensuite ... Est ce que la labo est en cours d'upgrade ?
	if (!CheckLabSettingsInQueue($CurrentPlanet)) {
		$NoResearchMessage = $lang['labo_on_update'];
		$bContinue         = false;
	}

	// Boucle d'interpretation des eventuelles commandes
	if (isset($_GET['cmd'])) {
		$TheCommand = $_GET['cmd'];
		$Techno     = $_GET['tech'] ?? '';

		if (is_array($ThePlanet)) {
			$WorkingPlanet = $ThePlanet;
		} else {
			$WorkingPlanet = $CurrentPlanet;
		}

		if ($TheCommand == 'remove') {
			// Retrait d'une recherche en attente : la file est reecrite par le service
			// (jamais la premiere, qui est en cours, et sans remboursement).
			try {
				(new \App\Services\QueueService())->removeResearch($CurrentUser, $WorkingPlanet, (int) $_GET['listid']);
			} catch (\App\Core\Api\ApiException $e) {
				// Position invalide : on ignore, la page reaffiche simplement la file.
			}
		} elseif (is_numeric($Techno)) {
			if (in_array($Techno, $reslist['tech'])) {
				// Bon quand on arrive ici ... On sait deja qu'on a une technologie valide
				try {
					switch ($TheCommand) {
						case 'cancel':
							// Interruption : remboursement puis demarrage de la suivante.
							(new \App\Services\ResearchService())->cancel($CurrentUser, $WorkingPlanet, (int) $Techno);
							$InResearch = false;
							break;
						case 'search':
							// Ajout a la file : les ressources sont debitees tout de suite.
							(new \App\Services\ResearchService())->start($CurrentUser, $WorkingPlanet, (int) $Techno);
							$InResearch = true;
							break;
					}
				} catch (\App\Core\Api\ApiException $e) {
					// La page historique se contente d'ignorer une demande impossible : le
					// bouton n'est actif que si la recherche est faisable.
				}
				// Les ecritures en base (ressources, file, b_tech) sont faites par les services.
				if (is_array($ThePlanet)) {
					$ThePlanet = $WorkingPlanet;
				} else {
					$CurrentPlanet = $WorkingPlanet;
					$ThePlanet     = $WorkingPlanet;
				}
			}
		} else {
			$bContinue = false;
		}
	}

	// File de recherche : rendue une seule fois, en tete de page (comme la file de
	// construction). Le client y branche le decompte et le glisser-deposer.
	$QueueService  = new \App\Services\QueueService();
	$QueuePlanet   = is_array($ThePlanet) ? $ThePlanet : null;
	$QueueItems    = $QueueService->researchItems($CurrentUser, $QueuePlanet);
	$RunningTech   = $QueueItems === array() ? 0 : (int) $QueueItems[0]['element'];
	// La file a une limite (MAX_TECHNOLOGY_QUEUE_SIZE) : au-delà, la page
	// desactive le bouton comme pour les batiments (voir ResearchService::start()).
	$QueueFull     = count($QueueItems) >= MAX_TECHNOLOGY_QUEUE_SIZE;

	$TechRowTPL = gettemplate('buildings_research_row');
	$TechScrTPL = gettemplate('buildings_research_script');

	foreach ($lang['tech'] as $Tech => $TechName) {
		if ($Tech > 105 && $Tech <= 199) {
			if (IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $Tech)) {
				$RowParse                = $lang;
				$RowParse['dpath']       = $dpath;
				$RowParse['tech_id']     = $Tech;
				$building_level          = $CurrentUser[$resource[$Tech]];
				$RowParse['tech_level']  = ($building_level == 0) ? "" : "( " . $lang['level'] . " " . $building_level . " )";
				$RowParse['tech_name']   = $TechName;
				$RowParse['tech_descr']  = $lang['res']['descriptions'][$Tech];
				$RowParse['tech_price']  = GetElementPrice($CurrentUser, $CurrentPlanet, $Tech);
				$SearchTime              = GetBuildingTime($CurrentUser, $CurrentPlanet, $Tech);
				$RowParse['search_time'] = ShowBuildTime($SearchTime);
				$RowParse['tech_restp']  = GetRestPrice($CurrentUser, $CurrentPlanet, $Tech, true);
				$CanBeDone               = IsElementBuyable($CurrentUser, $CurrentPlanet, $Tech);

				// Arbre de decision de ce que l'on met dans la derniere case de la ligne.
				// La file accepte plusieurs recherches : tant que le laboratoire n'est pas
				// en evolution, chaque technologie accessible et payable peut y etre ajoutee
				// (seule la premiere entree de la file travaille).
				$LevelToDo   = $QueueService->researchNextLevel($CurrentUser, $QueuePlanet, $Tech);
				$CanBeDone   = $CanBeDone && CheckLabSettingsInQueue($CurrentPlanet) && !$QueueFull;

				$TechnoLabel = $lang['Rechercher'];
				if ($LevelToDo != 1) {
					$TechnoLabel .= '<br>' . $lang['level'] . ' ' . $LevelToDo;
				}

				if ($RunningTech == $Tech) {
					// C'est la technologie en cours de recherche : son suivi est en tete de page
					$TechnoLink = '<span class="badge text-bg-primary">' . $lang['in_working'] . '</span>';
				} elseif (!$CanBeDone) {
					// Laboratoire en cours d'evolution, ou ressources insuffisantes
					$TechnoLink = '<span class="btn btn-sm btn-outline-danger w-100 disabled">' . $TechnoLabel . '</span>';
				} elseif ($RunningTech != 0) {
					// Une recherche travaille deja : on ajoute a la suite de la file
					$TechnoLink = '<a class="btn btn-sm btn-outline-secondary w-100" href="/game/buildings?mode=research&amp;cmd=search&amp;tech=' . $Tech . '">' . $lang['InBuildQueue'] . '</a>';
				} else {
					$TechnoLink = '<a class="btn btn-sm btn-success w-100" href="/game/buildings?mode=research&amp;cmd=search&amp;tech=' . $Tech . '">' . $TechnoLabel . '</a>';
				}
				$RowParse['tech_link']  = $TechnoLink;
				$TechnoList            .= parsetemplate($TechRowTPL, $RowParse);
			}
		}
	}

	// Bloc « file de recherche » (decompte + glisser-deposer) : au-dessus de la
	// liste des technologies, et **masque** quand rien ne se cherche — la page
	// garde son point de rafraichissement, mais n'affiche rien d'inutile.
	$QueueBloc                = $lang;
	$QueueBloc['tech_time']   = $RunningTech != 0 ? max(0, (int) $QueueItems[0]['end_time'] - time()) : 0;
	$QueueBloc['tech_name']   = ($RunningTech != 0 && $QueuePlanet !== null && $QueuePlanet['id'] != $CurrentPlanet['id'])
		? $lang['on'] . ' ' . $QueuePlanet['name']
		: '';
	$QueueBloc['queue_class'] = ($RunningTech != 0) ? '' : ' d-none';
	$QueueBloc['QueueList']   = \App\Core\QueueRenderer::renderList(
		\App\Core\QueueRenderer::DOMAIN_RESEARCH,
		$QueueItems,
		\App\Core\QueueRenderer::labels($lang ?? array(), \App\Core\QueueRenderer::DOMAIN_RESEARCH)
	);

	$PageParse                = $lang;
	$PageParse['noresearch']  = ($NoResearchMessage != '') ? '<div class="alert alert-warning">' . $NoResearchMessage . '</div>' : '';
	$PageParse['queuelist']   = parsetemplate($TechScrTPL, $QueueBloc);
	$PageParse['technolist']  = $TechnoList;
	$Page                    .= parsetemplate(gettemplate('buildings_research'), $PageParse);

	display($Page, $lang['Research']);
}

// History revision
// 1.0 - Release initiale / modularisation / Reecriture / Commentaire / Mise en forme
// 1.1 - BUG affichage de la techno en cours
// 1.2 - Restructuration modification pour permettre d'annuller proprement une techno en cours

//

// ===== BatimentBuildingPage =====
function BatimentBuildingPage(&$CurrentPlanet, $CurrentUser)
{
	global $lang, $resource, $reslist, $dpath, $game_config, $_GET;

	CheckPlanetUsedFields($CurrentPlanet);

	// Tables des batiments possibles par type de planete : la regle vit dans
	// BuildingQueueService, que l'API et le controle d'affichage consultent aussi.
	$AllowedService = new \App\Services\BuildingQueueService();
	$Allowed['1']   = $AllowedService->allowedElements(1);
	$Allowed['3']   = $AllowedService->allowedElements(3);

	// Boucle d'interpretation des eventuelles commandes
	if (isset($_GET['cmd'])) {
		// On passe une commande
		$bThisIsCheated = false;
		$bDoItNow       = false;
		$TheCommand     = $_GET['cmd'];
		$Element        = $_GET['building'];
		$ListID         = $_GET['listid'];
		if (isset($Element)) {
			if (!strchr($Element, " ")) {
				if (!strchr($Element, ",")) {
					if (!strchr($Element, ";")) {
						if (in_array(trim($Element), $Allowed[$CurrentPlanet['planet_type']])) {
							$bDoItNow = true;
						} else {
							$bThisIsCheated = true;
						}
					} else {
						$bThisIsCheated = true;
					}
				} else {
					$bThisIsCheated = true;
				}
			} else {
				$bThisIsCheated = true;
			}
		} elseif (isset($ListID)) {
			$bDoItNow = true;
		}
		if ($bDoItNow == true) {
			switch ($TheCommand) {
				case 'cancel':
					// Interrompre le premier batiment de la queue
					CancelBuildingFromQueue($CurrentPlanet, $CurrentUser);
					break;
				case 'remove':
					// Supprimer un element de la queue (mais pas le premier)
					// $RemID -> element de la liste a supprimer
					RemoveBuildingFromQueue($CurrentPlanet, $CurrentUser, $ListID);
					break;
				case 'insert':
					// Insere un element dans la queue
					AddBuildingToQueue($CurrentPlanet, $CurrentUser, $Element, true);
					break;
				case 'destroy':
					// Detruit un batiment deja construit sur la planete !
					AddBuildingToQueue($CurrentPlanet, $CurrentUser, $Element, false);
					break;
				default:
					break;
			} // switch
		} elseif ($bThisIsCheated == true) {
			ResetThisFuckingCheater($CurrentUser['id']);
		}
	}

	SetNextQueueElementOnTop($CurrentPlanet, $CurrentUser);

	// File rendue par le serveur (App\Core\QueueRenderer) : le client ne fait
	// plus que decompter les temps et gerer le glisser-deposer.
	$QueueService      = new \App\Services\QueueService();
	$BuildingQueueItems = $QueueService->buildingItems($CurrentPlanet);
	$Queue = array(
		'lenght' => count($BuildingQueueItems),
		'buildlist' => \App\Core\QueueRenderer::renderList(
			\App\Core\QueueRenderer::DOMAIN_BUILDINGS,
			$BuildingQueueItems,
			\App\Core\QueueRenderer::labels($lang ?? array(), \App\Core\QueueRenderer::DOMAIN_BUILDINGS)
		),
	);

	// On enregistre ce que l'on a modifi� dans planet !
	BuildingSavePlanetRecord($CurrentPlanet);

	if ($Queue['lenght'] < MAX_BUILDING_QUEUE_SIZE) {
		$CanBuildElement = true;
	} else {
		$CanBuildElement = false;
	}

	$SubTemplate         = gettemplate('buildings_builds_row');
	$BuildingPage        = "";
	foreach ($lang['tech'] as $Element => $ElementName) {
		if (in_array($Element, $Allowed[$CurrentPlanet['planet_type']])) {
			$CurrentMaxFields      = CalculateMaxPlanetFields($CurrentPlanet);
			if ($CurrentPlanet["field_current"] < ($CurrentMaxFields - $Queue['lenght'])) {
				$RoomIsOk = true;
			} else {
				$RoomIsOk = false;
			}

			if (IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $Element)) {
				$HaveRessources        = IsElementBuyable($CurrentUser, $CurrentPlanet, $Element, true, false);
				$parse                 = array();
				$parse['dpath']        = $dpath;
				$parse['i']            = $Element;
				$BuildingLevel         = $CurrentPlanet[$resource[$Element]];
				$parse['nivel']        = ($BuildingLevel == 0) ? "" : " (" . $lang['level'] . " " . $BuildingLevel . ")";
				$parse['n']            = $ElementName;
				$parse['descriptions'] = $lang['res']['descriptions'][$Element];
				$ElementBuildTime      = GetBuildingTime($CurrentUser, $CurrentPlanet, $Element);
				$parse['time']         = ShowBuildTime($ElementBuildTime);
				$parse['price']        = GetElementPrice($CurrentUser, $CurrentPlanet, $Element);
				$parse['rest_price']   = GetRestPrice($CurrentUser, $CurrentPlanet, $Element);
				$parse['click']        = '';
				$NextBuildLevel        = $CurrentPlanet[$resource[$Element]] + 1;

				if ($Element == 31) {
					// Sp�cial Laboratoire
					if (
						$CurrentUser["b_tech_planet"] != 0 &&     // Si pas 0 y a une recherche en cours
						$game_config['BuildLabWhileRun'] != 1
					) {  // Variable qui contient le parametre
						// On verifie si on a le droit d'evoluer pendant les recherches (Setting dans config)
						$parse['click'] = '<span class="btn btn-sm btn-outline-danger w-100 disabled">' . $lang['in_working'] . '</span>';
					}
				}
				$BuildLabel = ($NextBuildLevel == 1) ? $lang['BuildFirstLevel'] : ($lang['BuildNextLevel'] . ' ' . $NextBuildLevel);

				if ($parse['click'] != '') {
					// Bin on ne fait rien, vu que l'on l'a deja fait au dessus !!
				} elseif ($RoomIsOk && $CanBuildElement) {
					if ($Queue['lenght'] == 0) {
						if ($HaveRessources == true) {
							$parse['click'] = '<a class="btn btn-sm btn-success w-100" href="?cmd=insert&amp;building=' . $Element . '">' . $BuildLabel . '</a>';
						} else {
							$parse['click'] = '<span class="btn btn-sm btn-outline-danger w-100 disabled">' . $BuildLabel . '</span>';
						}
					} else {
						$parse['click'] = '<a class="btn btn-sm btn-outline-secondary w-100" href="?cmd=insert&amp;building=' . $Element . '">' . $lang['InBuildQueue'] . '</a>';
					}
				} elseif ($RoomIsOk && !$CanBuildElement) {
					$parse['click'] = '<span class="btn btn-sm btn-outline-danger w-100 disabled">' . $BuildLabel . '</span>';
				} else {
					$parse['click'] = '<span class="text-danger fw-semibold">' . $lang['NoMoreSpace'] . '</span>';
				}

				$BuildingPage .= parsetemplate($SubTemplate, $parse);
			}
		}
	}

	$parse                         = $lang;

	$parse['QueueList']        = $Queue['buildlist'];

	$parse['planet_field_current'] = $CurrentPlanet["field_current"];
	$parse['planet_field_max']     = $CurrentPlanet['field_max'] + ($CurrentPlanet[$resource[33]] * 5);
	$parse['field_libre']          = $parse['planet_field_max']  - $CurrentPlanet['field_current'];

	$parse['BuildingsList']        = $BuildingPage;

	$page                         .= parsetemplate(gettemplate('buildings_builds'), $parse);

	display($page, $lang['Builds']);
}

//

// ===== CheckLabSettingsInQueue =====
function CheckLabSettingsInQueue($CurrentPlanet)
{
	global $lang, $game_config;

	if ($CurrentPlanet['b_building_id'] != "0") {
		$BuildQueue = $CurrentPlanet['b_building_id'];
		if (strpos($BuildQueue, ";")) {
			$Queue = explode(";", $BuildQueue);
			$CurrentBuilding = $Queue[0];
		} else {
			// Y a pas de queue de construction la liste n'a qu'un seul element
			$CurrentBuilding = $BuildQueue;
		}

		if ($CurrentBuilding == 31 && $game_config['BuildLabWhileRun'] != 1) {
			$return = false;
		} else {
			$return = true;
		}
	} else {
		$return = true;
	}

	return $return;
}

//

// ===== InsertBuildListScript =====
function InsertBuildListScript($CallProgram)
{
	global $lang;

	$BuildListScript  = "<script type=\"text/javascript\">\n";
	$BuildListScript .= "<!--\n";
	$BuildListScript .= "function t() {\n";
	$BuildListScript .= "	v           = new Date();\n";
	$BuildListScript .= "	var blc     = document.getElementById('blc');\n";
	$BuildListScript .= "	var timeout = 1;\n";
	$BuildListScript .= "	n           = new Date();\n";
	$BuildListScript .= "	ss          = pp;\n";
	$BuildListScript .= "	aa          = Math.round( (n.getTime() - v.getTime() ) / 1000. );\n";
	$BuildListScript .= "	s           = ss - aa;\n";
	$BuildListScript .= "	m           = 0;\n";
	$BuildListScript .= "	h           = 0;\n\n";
	$BuildListScript .= "	if ( (ss + 3) < aa ) {\n";
	$BuildListScript .= "		blc.innerHTML = \"" . $lang['completed'] . "<br><a href=/game/" . $CallProgram . "?planet=\" + pl + \">" . $lang['continue'] . "</a>\";\n";
	$BuildListScript .= "		if ((ss + 6) >= aa) {\n";
	$BuildListScript .= "			window.setTimeout('document.location.href=\"/game/" . $CallProgram . "?planet=' + pl + '\";', 3500);\n";
	$BuildListScript .= "		}\n";
	$BuildListScript .= "	} else {\n";
	$BuildListScript .= "		if ( s < 0 ) {\n";
	$BuildListScript .= "			if (1) {\n";
	$BuildListScript .= "				blc.innerHTML = \"" . $lang['completed'] . "<br><a href=/game/" . $CallProgram . "?planet=\" + pl + \">" . $lang['continue'] . "</a>\";\n";
	$BuildListScript .= "				window.setTimeout('document.location.href=\"/game/" . $CallProgram . "?planet=' + pl + '\";', 2000);\n";
	$BuildListScript .= "			} else {\n";
	$BuildListScript .= "				timeout = 0;\n";
	$BuildListScript .= "				blc.innerHTML = \"" . $lang['completed'] . "<br><a href=/game/" . $CallProgram . "?planet=\" + pl + \">" . $lang['continue'] . "</a>\";\n";
	$BuildListScript .= "			}\n";
	$BuildListScript .= "		} else {\n";
	$BuildListScript .= "			if ( s > 59) {\n";
	$BuildListScript .= "				m = Math.floor( s / 60);\n";
	$BuildListScript .= "				s = s - m * 60;\n";
	$BuildListScript .= "			}\n";
	$BuildListScript .= "			if ( m > 59) {\n";
	$BuildListScript .= "				h = Math.floor( m / 60);\n";
	$BuildListScript .= "				m = m - h * 60;\n";
	$BuildListScript .= "			}\n";
	$BuildListScript .= "			if ( s < 10 ) {\n";
	$BuildListScript .= "				s = \"0\" + s;\n";
	$BuildListScript .= "			}\n";
	$BuildListScript .= "			if ( m < 10 ) {\n";
	$BuildListScript .= "				m = \"0\" + m;\n";
	$BuildListScript .= "			}\n";
	$BuildListScript .= "			if (1) {\n";
	$BuildListScript .= "				blc.innerHTML = h + \":\" + m + \":\" + s + \"<br><a href=/game/" . $CallProgram . "?listid=\" + pk + \"&amp;cmd=\" + pm + \"&amp;planet=\" + pl + \">" . $lang['DelFirstQueue'] . "</a>\";\n";
	$BuildListScript .= "			} else {\n";
	$BuildListScript .= "				blc.innerHTML = h + \":\" + m + \":\" + s + \"<br><a href=/game/" . $CallProgram . "?listid=\" + pk + \"&amp;cmd=\" + pm + \"&amp;planet=\" + pl + \">" . $lang['DelFirstQueue'] . "</a>\";\n";
	$BuildListScript .= "			}\n";
	$BuildListScript .= "		}\n";
	$BuildListScript .= "		pp = pp - 1;\n";
	$BuildListScript .= "		if (timeout == 1) {\n";
	$BuildListScript .= "			window.setTimeout(\"t();\", 999);\n";
	$BuildListScript .= "		}\n";
	$BuildListScript .= "	}\n";
	$BuildListScript .= "}\n";
	$BuildListScript .= "//-->\n";
	$BuildListScript .= "</script>\n";

	return $BuildListScript;
}

//

// ===== AddBuildingToQueue =====
function AddBuildingToQueue(&$CurrentPlanet, $CurrentUser, $Element, $AddMode = true)
{
	global $lang, $resource;

	$CurrentQueue  = trim((string) $CurrentPlanet['b_building_id']);

	// Une installation fraiche peut stocker une file vide en chaine vide : sans
	// cette normalisation, explode() produit une entree bidon et l'ecriture
	// suivante enregistre une valeur invalide.
	if ($CurrentQueue === '') {
		$CurrentQueue = '0';
	}

	if ($CurrentQueue != 0) {
		$QueueArray    = explode(";", $CurrentQueue);
		$ActualCount   = count($QueueArray);
	} else {
		$QueueArray    = array();
		$ActualCount   = 0;
	}

	if ($AddMode == true) {
		$BuildMode = 'build';
	} else {
		$BuildMode = 'destroy';
	}

	if ($ActualCount < MAX_BUILDING_QUEUE_SIZE) {
		$QueueID      = $ActualCount + 1;
	} else {
		$QueueID      = false;
	}

	if ($QueueID != false) {
		// Faut verifier si l'Element que l'on veut integrer est deja dans le tableau !
		if ($QueueID > 1) {
			$InArray = 0;
			for ($QueueElement = 0; $QueueElement < $ActualCount; $QueueElement++) {
				$QueueSubArray = explode(",", $QueueArray[$QueueElement]);
				if ($QueueSubArray[0] == $Element) {
					$InArray++;
				}
			}
		} else {
			$InArray = 0;
		}

		if ($InArray != 0) {
			$ActualLevel  = $CurrentPlanet[$resource[$Element]];
			if ($AddMode == true) {
				$BuildLevel   = $ActualLevel + 1 + $InArray;
				$CurrentPlanet[$resource[$Element]] += $InArray;
				$BuildTime    = GetBuildingTime($CurrentUser, $CurrentPlanet, $Element);
				$CurrentPlanet[$resource[$Element]] -= $InArray;
			} else {
				$BuildLevel   = $ActualLevel - 1 + $InArray;
				$CurrentPlanet[$resource[$Element]] -= $InArray;
				$BuildTime    = GetBuildingTime($CurrentUser, $CurrentPlanet, $Element) / 2;
				$CurrentPlanet[$resource[$Element]] += $InArray;
			}
		} else {
			$ActualLevel  = $CurrentPlanet[$resource[$Element]];
			if ($AddMode == true) {
				$BuildLevel   = $ActualLevel + 1;
				$BuildTime    = GetBuildingTime($CurrentUser, $CurrentPlanet, $Element);
			} else {
				$BuildLevel   = $ActualLevel - 1;
				$BuildTime    = GetBuildingTime($CurrentUser, $CurrentPlanet, $Element) / 2;
			}
		}

		if ($QueueID == 1) {
			$BuildEndTime = time() + $BuildTime;
		} else {
			$PrevBuild = explode(",", $QueueArray[$ActualCount - 1]);
			$BuildEndTime = $PrevBuild[3] + $BuildTime;
		}
		$QueueArray[$ActualCount]       = $Element . "," . $BuildLevel . "," . $BuildTime . "," . $BuildEndTime . "," . $BuildMode;
		$NewQueue                       = implode(";", $QueueArray);
		$CurrentPlanet['b_building_id'] = $NewQueue;
	}
	return $QueueID;
}

//

// ===== ShowBuildingQueue =====
function ShowBuildingQueue($CurrentPlanet, $CurrentUser)
{
	global $lang;

	$CurrentQueue  = $CurrentPlanet['b_building_id'];
	$QueueID       = 0;
	if ($CurrentQueue != 0) {
		// Queue de fabrication documentée ... Y a au moins 1 element a construire !
		$QueueArray    = explode(";", $CurrentQueue);
		// Compte le nombre d'elements
		$ActualCount   = count($QueueArray);
	} else {
		// Queue de fabrication vide
		$QueueArray    = "0";
		$ActualCount   = 0;
	}

	$ListIDRow    = "";
	if ($ActualCount != 0) {
		$PlanetID     = $CurrentPlanet['id'];
		for ($QueueID = 0; $QueueID < $ActualCount; $QueueID++) {
			// Chaque element de la liste de fabrication est un tableau de 5 données
			// [0] -> Le batiment
			// [1] -> Le niveau du batiment
			// [2] -> La durée de construction
			// [3] -> L'heure théorique de fin de construction
			// [4] -> type d'action
			$BuildArray   = explode(",", $QueueArray[$QueueID]);
			$BuildEndTime = floor($BuildArray[3]);
			$CurrentTime  = floor(time());
			if ($BuildEndTime >= $CurrentTime) {
				$ListID       = $QueueID + 1;
				$Element      = $BuildArray[0];
				$BuildLevel   = $BuildArray[1];
				$BuildMode    = $BuildArray[4];
				$BuildTime    = $BuildEndTime - time();
				$ElementTitle = $lang['tech'][$Element];

				if ($ListID > 0) {
					$ListIDRow .= '<div class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2 xnova-queue-item">';
					$ListIDRow .= '<span><span class="fw-semibold">' . $ListID . '.</span> ' . $ElementTitle . ' ' . $BuildLevel;
					if ($BuildMode != 'build') {
						$ListIDRow .= ' <span class="text-danger">' . $lang['destroy'] . '</span>';
					}
					$ListIDRow .= '</span>';
					$ListIDRow .= '<span class="d-flex align-items-center gap-2">';
					if ($ListID == 1) {
						$ListIDRow .= '<span id="blc" class="xnova-countdown small fw-semibold">';
						$ListIDRow .= '<span class="text-success">' . date("j/m H:i:s", $BuildEndTime) . '</span>';
						$ListIDRow .= '</span>';
						$ListIDRow .= '<script type="text/javascript">';
						$ListIDRow .= 'pp = "' . $BuildTime . '";';
						$ListIDRow .= 'pk = "' . $ListID . '";';
						$ListIDRow .= 'pm = "cancel";';
						$ListIDRow .= 'pl = "' . $PlanetID . '";';
						$ListIDRow .= 't();';
						$ListIDRow .= '</script>';
					} else {
						$ListIDRow .= '<a class="btn btn-sm btn-outline-secondary" href="/game/buildings?listid=' . $ListID . '&amp;cmd=remove&amp;planet=' . $PlanetID . '">' . $lang['DelFromQueue'] . '</a>';
					}
					$ListIDRow .= '</span>';
					$ListIDRow .= '</div>';
				}
			}
		}
	}

	$RetValue['lenght']    = $ActualCount;
	$RetValue['buildlist'] = $ListIDRow;

	return $RetValue;
}

//

// ===== HandleTechnologieBuild =====
function HandleTechnologieBuild(&$CurrentPlanet, &$CurrentUser)
{
	global $resource;

	if ($CurrentUser['b_tech_planet'] != 0) {
		// Y a une technologie en cours sur une de mes colonies
		if ($CurrentUser['b_tech_planet'] != $CurrentPlanet['id']) {
			// Et ce n'est pas sur celle ci !!
			$WorkingPlanet = (new \App\Repositories\PlanetRepository())->findCurrentById((int) $CurrentUser['b_tech_planet']);
		}

		if ($WorkingPlanet) {
			$ThePlanet = $WorkingPlanet;
		} else {
			$ThePlanet = $CurrentPlanet;
		}

		if (
			$ThePlanet['b_tech']    <= time() &&
			$ThePlanet['b_tech_id'] != 0
		) {
			// La recherche en cours est terminée ...
			$CurrentUser[$resource[$ThePlanet['b_tech_id']]]++;

			// Le niveau appartient au compte. Le miroir de la file (`planets.b_tech`,
			// `b_tech_id` et `users.b_tech_planet`) n'est **pas** écrit ici : la file le
			// réécrit elle-même juste en dessous, avec la recherche suivante s'il y en a une.
			(new \App\Repositories\UserRepository())->setColumn(
				(int) $CurrentUser['id'],
				(string) $resource[$ThePlanet['b_tech_id']],
				(int) $CurrentUser[$resource[$ThePlanet['b_tech_id']]]
			);

			// File de recherche : l'element termine est retire. S'il reste une recherche,
			// elle demarre (b_tech / b_tech_id / b_tech_planet reecrits par la file).
			$OnWork = (new \App\Services\QueueService())->advanceResearch($CurrentUser, $ThePlanet);

			if (isset($WorkingPlanet)) {
				$WorkingPlanet = $ThePlanet;
			} else {
				$CurrentPlanet = $ThePlanet;
			}
			$Result['WorkOn'] = $OnWork ? $ThePlanet : "";
			$Result['OnWork'] = $OnWork;
		} elseif ($ThePlanet["b_tech_id"] == 0) {
			// Il n'y a rien a l'ouest ...
			// Pas de Technologie en cours devait y avoir un bug lors de la derniere connexion
			// On met l'enregistrement informant d'une techno en cours de recherche a jours
			(new \App\Services\QueueService())->clearResearchPlanet($CurrentUser);
			$Result['WorkOn'] = "";
			$Result['OnWork'] = false;
		} else {
			// Bin on bosse toujours ici ... Alors ne nous derangez pas !!!
			$Result['WorkOn'] = $ThePlanet;
			$Result['OnWork'] = true;
		}
	} else {
		$Result['WorkOn'] = "";
		$Result['OnWork'] = false;
	}

	return $Result;
}

// History revision
// 1.0 - mise en forme modularisation version initiale
// 1.1 - Correction retour de fonction (retourne un tableau a la place d'un flag)

//

// ===== BuildingSavePlanetRecord =====
function BuildingSavePlanetRecord($CurrentPlanet)
{
	$repository = new \App\Repositories\BuildingQueueRepository();

	$repository->saveBuildingQueueState($CurrentPlanet);
}

//

// ===== RemoveBuildingFromQueue =====
function RemoveBuildingFromQueue(&$CurrentPlanet, $CurrentUser, $QueueID)
{

	if ($QueueID > 1) {
		$CurrentQueue  = $CurrentPlanet['b_building_id'];
		if ($CurrentQueue != 0) {
			$QueueArray    = explode(";", $CurrentQueue);
			$ActualCount   = count($QueueArray);
			$ListIDArray   = explode(",", $QueueArray[$QueueID - 2]);
			$BuildEndTime  = $ListIDArray[3];
			$ListIDArray   = explode(",", $QueueArray[$QueueID - 1]);
			$Element = $ListIDArray[0];
			for ($ID = $QueueID; $ID < $ActualCount; $ID++) {
				$ListIDArray          = explode(",", $QueueArray[$ID]);
				if ($Element == $ListIDArray[0]) {
					$ListIDArray[1]		 -= 1;
					$ListIDArray[2]		  = GetBuildingTimeLevel($CurrentUser, $CurrentPlanet, $ListIDArray[0], $ListIDArray[1]);
				}
				$BuildEndTime        += $ListIDArray[2];
				$ListIDArray[3]       = $BuildEndTime;
				$QueueArray[$ID - 1]  = implode(",", $ListIDArray);
			}
			unset($QueueArray[$ActualCount - 1]);
			$NewQueue     = implode(";", $QueueArray);
		}
		$CurrentPlanet['b_building_id'] = $NewQueue;
	}

	return $QueueID;
}

//

// ===== CancelBuildingFromQueue =====
function CancelBuildingFromQueue(&$CurrentPlanet, &$CurrentUser)
{

	$CurrentQueue  = $CurrentPlanet['b_building_id'];
	if ($CurrentQueue != 0) {
		// Creation du tableau de la liste de construction
		$QueueArray          = explode(";", $CurrentQueue);
		// Comptage du nombre d'elements dans la liste
		$ActualCount         = count($QueueArray);

		// Stockage de l'element a 'interrompre'
		$CanceledIDArray     = explode(",", $QueueArray[0]);
		$Element             = $CanceledIDArray[0];
		$BuildMode           = $CanceledIDArray[4]; // pour savoir si on construit ou detruit

		$nb_item = $Element;

		if ($ActualCount > 1) {
			array_shift($QueueArray);
			$NewCount        = count($QueueArray);
			// Mise a jour de l'heure de fin de construction theorique du batiment
			$BuildEndTime        = time();

			for ($ID = 0; $ID < $NewCount; $ID++) {
				$ListIDArray          = explode(",", $QueueArray[$ID]);

				// Pour diminuer le niveau et le temps de construction
				// si le bâtiment qui est annulé se trouve plusieurs fois dans la queue
				// Exemple de queue de construction :
				// Mine de métal (Niveau 40) | Silo de missile (Niveau 30) | Silo de missiles (Niveau 31) | Mine de métal (Niveau 41)

				// Si on supprime le premier bâtiment, on aura dans la queue de construction :
				// Silo de missile (Niveau 30) | Silo de missiles (Niveau 31) | Mine de métal (Niveau 40)
				if ($nb_item == $ListIDArray[0]) {
					$ListIDArray[1]		 -= 1;
					$ListIDArray[2]		  = GetBuildingTimeLevel($CurrentUser, $CurrentPlanet, $ListIDArray[0], $ListIDArray[1]);
				}
				$BuildEndTime        += $ListIDArray[2];
				$ListIDArray[3]       = $BuildEndTime;
				$QueueArray[$ID]      = implode(",", $ListIDArray);
			}
			$NewQueue        = implode(";", $QueueArray);
			$ReturnValue     = true;
			$BuildEndTime    = '0';
		} else {
			$NewQueue        = '0';
			$ReturnValue     = false;
			$BuildEndTime    = '0';
		}

		// Ici on va rembourser les ressources engagées ...
		// Deja le mode (car quand on detruit ca ne coute que la moitié du prix de construction classique
		if ($BuildMode == 'destroy') {
			$ForDestroy = true;
		} else {
			$ForDestroy = false;
		}

		if ($Element != false) {
			$Needed                        = GetBuildingPrice($CurrentUser, $CurrentPlanet, $Element, true, $ForDestroy);
			$CurrentPlanet['metal']       += $Needed['metal'];
			$CurrentPlanet['crystal']     += $Needed['crystal'];
			$CurrentPlanet['deuterium']   += $Needed['deuterium'];
		}
	} else {
		$NewQueue          = '0';
		$BuildEndTime      = '0';
		$ReturnValue       = false;
	}

	$CurrentPlanet['b_building_id']  = $NewQueue;
	$CurrentPlanet['b_building']     = $BuildEndTime;

	return $ReturnValue;
}

//

// ===== SetNextQueueElementOnTop =====
function SetNextQueueElementOnTop(&$CurrentPlanet, $CurrentUser)
{
	global $lang, $resource;

	// Sans initialisation, une file vide laisse les deux variables indefinies :
	// l'UPDATE ecrivait alors une chaine vide dans b_building (colonne INT), ce
	// que MySQL refuse en mode strict.
	$NewQueue     = '0';
	$BuildEndTime = '0';

	// Garde fou ... Si le temps de construction n'est pas 0 on ne fait rien !!!
	if ((int) $CurrentPlanet['b_building'] == 0) {
		$CurrentQueue  = trim((string) $CurrentPlanet['b_building_id']);

		// Meme normalisation que dans AddBuildingToQueue : sans elle, une file
		// vide est lue comme une entree existante (champ de fin vide) et la mise
		// a jour ecrit une chaine vide dans la colonne entiere b_building.
		if ($CurrentQueue === '') {
			$CurrentQueue = '0';
		}

		if ($CurrentQueue != 0) {
			$QueueArray = explode(";", $CurrentQueue);
			$Loop       = true;
			while ($Loop == true) {
				$ListIDArray         = explode(",", $QueueArray[0]);
				$Element             = $ListIDArray[0];
				$Level               = $ListIDArray[1];
				$BuildTime           = $ListIDArray[2];
				$BuildEndTime        = $ListIDArray[3];
				$BuildMode           = $ListIDArray[4]; // pour savoir si on construit ou detruit
				$HaveNoMoreLevel     = false;

				if ($BuildMode == 'destroy') {
					$ForDestroy = true;
				} else {
					$ForDestroy = false;
				}
				$HaveRessources = IsElementBuyable($CurrentUser, $CurrentPlanet, $Element, true, $ForDestroy);
				if ($ForDestroy) {
					if ($CurrentPlanet[$resource[$Element]] == 0) {
						$HaveRessources  = false;
						$HaveNoMoreLevel = true;
					}
				}
				if ($HaveRessources == true) {
					$Needed                        = GetBuildingPrice($CurrentUser, $CurrentPlanet, $Element, true, $ForDestroy);
					$CurrentPlanet['metal']       -= $Needed['metal'];
					$CurrentPlanet['crystal']     -= $Needed['crystal'];
					$CurrentPlanet['deuterium']   -= $Needed['deuterium'];
					$CurrentTime                   = time();
					$BuildEndTime                  = $BuildEndTime;
					$NewQueue                      = implode(";", $QueueArray);
					if ($NewQueue == "") {
						$NewQueue                      = '0';
					}
					$Loop                          = false;
				} else {
					$ElementName = $lang['tech'][$Element];
					if ($HaveNoMoreLevel == true) {
						$Message     = sprintf($lang['sys_nomore_level'], $ElementName);
					} else {
						$Needed      = GetBuildingPrice($CurrentUser, $CurrentPlanet, $Element, true, $ForDestroy);
						$Message     = sprintf(
							$lang['sys_notenough_money'],
							$ElementName,
							pretty_number($CurrentPlanet['metal']),
							$lang['Metal'],
							pretty_number($CurrentPlanet['crystal']),
							$lang['Crystal'],
							pretty_number($CurrentPlanet['deuterium']),
							$lang['Deuterium'],
							pretty_number($Needed['metal']),
							$lang['Metal'],
							pretty_number($Needed['crystal']),
							$lang['Crystal'],
							pretty_number($Needed['deuterium']),
							$lang['Deuterium']
						);
					}

					SendSimpleMessage($CurrentUser['id'], '', '', 99, $lang['sys_buildlist'], $lang['sys_buildlist_fail'], $Message);

					array_shift($QueueArray);
					$ActualCount         = count($QueueArray);
					if ($ActualCount == 0) {
						$BuildEndTime  = '0';
						$NewQueue      = '0';
						$Loop          = false;
					}
				}
			} // while
		} else {
			$BuildEndTime  = '0';
			$NewQueue      = '0';
		}

		// Ecriture de la mise a jour dans la BDD
		$CurrentPlanet['b_building']    = $BuildEndTime;
		$CurrentPlanet['b_building_id'] = $NewQueue;

		// Une seule ecriture, un tableau de colonnes : la ressource et la file de batiment
		// partent ensemble, valeurs liees, colonnes verifiees par le depot.
		(new \App\Repositories\PlanetRepository())->updateColumns((int) $CurrentPlanet['id'], array(
			'metal'         => $CurrentPlanet['metal'],
			'crystal'       => $CurrentPlanet['crystal'],
			'deuterium'     => $CurrentPlanet['deuterium'],
			'b_building'    => $CurrentPlanet['b_building'],
			'b_building_id' => $CurrentPlanet['b_building_id'],
		));
	}

	return;
}

//

// ===== PlanetResourceUpdate =====
function PlanetResourceUpdate($CurrentUser, &$CurrentPlanet, $UpdateTime, $Simul = false)
{
	$service = \App\Services\ModuleService::instance(\App\Services\ProductionService::class);

	$service->updatePlanetResources($CurrentUser, $CurrentPlanet, $UpdateTime, $Simul);
}

//

// ===== HandleElementBuildingQueue =====
function HandleElementBuildingQueue($currentUser, &$currentPlanet, $productionTime)
{
	// La regle vit dans ProductionService (comme PlanetResourceUpdate) : cette
	// tableau appelait encore une methode de depot qui n'existe plus.
	$service = new \App\Services\ProductionService();

	return $service->handleElementBuildingQueue($currentUser, $currentPlanet, $productionTime);
}

//

// ===== UpdatePlanetBatimentQueueList =====
function UpdatePlanetBatimentQueueList(&$CurrentPlanet, &$CurrentUser)
{
	$RetValue = false;
	if ($CurrentPlanet['b_building_id'] != 0) {
		while ($CurrentPlanet['b_building_id'] != 0) {
			if ($CurrentPlanet['b_building'] <= time()) {
				PlanetResourceUpdate($CurrentUser, $CurrentPlanet, $CurrentPlanet['b_building'], false);
				$IsDone = CheckPlanetBuildingQueue($CurrentPlanet, $CurrentUser);
				if ($IsDone == true) {
					SetNextQueueElementOnTop($CurrentPlanet, $CurrentUser);
				}
			} else {
				$RetValue = true;
				break;
			}
		}
	}
	return $RetValue;
}

// Revision History
// - 1.0 Mise en module initiale
// - 1.1 Mise a jour des ressources sur la planete verifi�e (pour prendre en compte les ressources produites
//       pendant la construction et avant l'evolution evantuel d'une mine ou d'en batiment

//

// ===== IsOfficierAccessible =====
function IsOfficierAccessible($CurrentUser, $Officier)
{
	global $requeriments, $resource, $pricelist;

	if (isset($requeriments[$Officier])) {
		$enabled = true;
		foreach ($requeriments[$Officier] as $ReqOfficier => $OfficierLevel) {
			if (
				$CurrentUser[$resource[$ReqOfficier]] &&
				$CurrentUser[$resource[$ReqOfficier]] >= $OfficierLevel
			) {
				$enabled = 1;
			} else {
				return 0;
			}
		}
	}
	if ($CurrentUser[$resource[$Officier]] < $pricelist[$Officier]['max']) {
		return 1;
	} else {
		return -1;
	}
}

//

// ===== SortUserPlanets =====
function SortUserPlanets($CurrentUser)
{
	$Order = ((int) $CurrentUser['planet_sort_order'] === 1) ? 'DESC' : 'ASC';

	// Les planètes du joueur viennent du depot des planetes, tri compris : la liste blanche de
	// `findAllByOwner()` reprend exactement les trois tris historiques (`planet_sort` 0, 1, 2),
	// et les colonies abandonnees restent dehors (c'est le filtre du depot).
	return (new \App\Repositories\PlanetRepository())->findAllByOwner(
		(int) $CurrentUser['id'],
		(int) $CurrentUser['planet_sort'],
		$Order
	);
}

//

// ===== SetSelectedPlanet =====
function SetSelectedPlanet(&$CurrentUser)
{
	if (!isset($_GET['cp']) || !isset($_GET['re'])) {
		return;
	}
	$SelectPlanet  = $_GET['cp'];
	$RestorePlanet = $_GET['re'];

	if (
		isset($SelectPlanet)      &&
		is_numeric($SelectPlanet) &&
		isset($RestorePlanet)     &&
		$RestorePlanet == 0
	) {
		// La planete doit etre **existante** et m'appartenir : une lecture par identifiant, puis
		// les deux conditions en PHP (elles ne sont pas un tri de requete).
		$PlanetRow    = (new \App\Repositories\PlanetRepository())->findCurrentById((int) $SelectPlanet);
		$IsPlanetMine = is_array($PlanetRow)
			&& (int) $PlanetRow['id_owner'] === (int) $CurrentUser['id']
			&& !\App\Core\Flags::isDeleted(\App\Core\Flags::value($PlanetRow['flags'] ?? 0));

		if ($IsPlanetMine) {
			// Ouaip elle est a moi ... Donc ... on met la met comme planete courrante
			$CurrentUser['current_planet'] = (int) $SelectPlanet;
			// Puis tant qu'a faire ... On l'enregistre aussi sait on jamais
			(new \App\Repositories\UserRepository())->setColumn((int) $CurrentUser['id'], 'current_planet', (int) $SelectPlanet);
		}
	}
}

//

// ===== IsVacationMode =====
function IsVacationMode($CurrentUser)
{
	global $game_config;

	if ($CurrentUser['vacation_mode'] == 1) {
		// Tous les planètes du compte : la production s'arrete et les pourcentages retombent a
		// zero. La lecture du depot laisse les colonies abandonnees dehors, et seule la planete
		// (type 1) est remise a plat - une lune n'a pas de mine.
		$planets = (new \App\Repositories\PlanetRepository())->findAllByOwner((int) $CurrentUser['id']);

		foreach ($planets as $planetRow) {
			if ((int) $planetRow['planet_type'] !== 1) {
				continue;
			}

			(new \App\Repositories\PlanetRepository())->updateColumns((int) $planetRow['id'], array(
				'metal_perhour'                => $game_config['metal_basic_income'],
				'crystal_perhour'              => $game_config['crystal_basic_income'],
				'deuterium_perhour'            => $game_config['deuterium_basic_income'],
				'metal_mine_porcent'           => 0,
				'crystal_mine_porcent'         => 0,
				'deuterium_sintetizer_porcent' => 0,
				'solar_plant_porcent'          => 0,
				'fusion_plant_porcent'         => 0,
				'solar_satelit_porcent'        => 0,
			));
		}

		return true;
	}
	return false;
}
