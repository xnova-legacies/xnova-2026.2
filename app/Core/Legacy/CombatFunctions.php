<?php

/**
 * Fonctions legacy : moteur de combat
 */

/**
 * Lignes du tableau des unités d'un camp : un gabarit unique, rempli en boucle.
 */
function BattleReportUnits(array $units, array $lang): string
{
    $rows = '';

    foreach ($units as $Ship => $Data) {
        if (!is_numeric($Ship) || (int) ($Data['count'] ?? 0) <= 0) {
            continue;
        }

        $count = (int) $Data['count'];

        $rows .= \App\Core\TemplateEngine::render('combat_report_unit', array(
            'unit_name' => $lang['tech_rc'][$Ship] ?? '',
            'unit_count' => pretty_number($count),
            'unit_weapon' => pretty_number(round($Data['attack'] / $count)),
            'unit_shield' => pretty_number(round($Data['shield'] / $count)),
            'unit_armour' => pretty_number(round($Data['armour'] / $count)),
        ));
    }

    return $rows;
}

/**
 * Rendu d'un camp (le même gabarit sert à l'attaquant et au défenseur : c'est ce
 * qui remplace les deux blocs identiques du rapport historique).
 */
function BattleReportSide(string $title, string $tech, array $units, array $lang): string
{
    $rows = BattleReportUnits($units, $lang);

    return \App\Core\TemplateEngine::render('combat_report_side', $lang + array(
        'side_title' => $title,
        'side_tech' => $tech,
        'side_units' => $rows,
        'side_units_class' => ($rows === '') ? ' d-none' : '',
        'side_destroyed_class' => ($rows === '') ? '' : ' d-none',
    ));
}

/**
 * Rendu du RapidFire d'un camp : le même gabarit sert aux deux camps, et la
 * même ligne est répétée pour chaque « unité / cible » du combat.
 *
 * @param list<array{unit: string, target: string, shots: int}> $rows
 */
function BattleReportRapidFire(string $title, array $rows, array $lang): string
{
    $list = '';

    foreach ($rows as $row) {
        $list .= \App\Core\TemplateEngine::render('combat_report_rapidfire_row', array(
            'rf_unit' => $row['unit'],
            'rf_target' => $row['target'],
            'rf_shots' => pretty_number($row['shots']),
        ));
    }

    return \App\Core\TemplateEngine::render('combat_report_rapidfire', $lang + array(
        'rapidfire_title' => $title,
        'rapidfire_list' => $list,
        'rapidfire_table_class' => ($list === '') ? ' d-none' : '',
        'rapidfire_empty_class' => ($list === '') ? '' : ' d-none',
    ));
}

// ===== MissionCaseAttack =====

/**
 * Ligne du tableau « qui a engagé quoi / qui a perdu quoi ».
 *
 * Le moteur de combat ne connaît qu'une force par camp : les pertes sont donc
 * réparties au prorata de ce que chaque participant a engagé (ForceSplit).
 *
 * @param array<int, int>      $engaged
 * @param array<int, int>      $survivors
 * @param array<string, mixed> $lang
 */
function BattleReportForceRow(string $name, string $side, array $engaged, array $survivors, array $lang, string $sideClass = ''): string
{
    $names = $lang['tech_rc'] ?? array();
    $lost = array();

    foreach ($engaged as $shipId => $count) {
        $missing = (int) $count - (int) ($survivors[$shipId] ?? 0);

        if ($missing > 0) {
            $lost[(int) $shipId] = $missing;
        }
    }

    return \App\Core\TemplateEngine::render('combat_report_forces_row', $lang + array(
        'force_name' => $name,
        'force_side' => $side,
        'force_side_class' => $sideClass,
        'force_engaged' => \App\Services\FlyingFleetService::unitsLabel($engaged, $names),
        'force_lost' => ($lost === array())
            ? (string) ($lang['sys_attack_no_loss'] ?? '')
            : \App\Services\FlyingFleetService::unitsLabel($lost, $names),
    ));
}

function MissionCaseAttack($FleetRow)
{
    global $user, $xnova_root_path, $pricelist, $lang, $resource, $CombatCaps;

    // Les dépôts vivent au-dessus des deux passages : la fonction sert aussi bien l'arrivée
    // (`fleet_mess = 0`) que le retour, qui relit le vol et rend son chargement à la planète.
    $planets  = new \App\Repositories\PlanetRepository();
    // Le depot est resolu : le module officier le derive pour verser le bonus de combat
    // de l'amiral (findTechnologies) et le point de raid (rewardRaid).
    $users    = \App\Services\ModuleService::instance(\App\Repositories\UserRepository::class);
    $missions = new \App\Repositories\MissionRepository();

    if ($FleetRow['fleet_start_time'] <= time()) {
        if ($FleetRow['fleet_mess'] == 0) {
            if (!isset($CombatCaps[202]['sd'])) {
                message("<font color=\"red\">" . $lang['sys_no_vars'] . "</font>", $lang['sys_error'], "fleet." . PHPEXT, 2);
            }

            // La cible se lit par sa position, comme partout dans le jeu : une planète abandonné
            // ou une lune détruite ne s'y trouve plus (drapeau `DELETED`).
            $TargetPlanet = $planets->findByCoords(
                (int) $FleetRow['fleet_end_galaxy'],
                (int) $FleetRow['fleet_end_system'],
                (int) $FleetRow['fleet_end_planet'],
                (int) $FleetRow['fleet_end_type']
            );

            // La planète visé a disparu pendant le vol : il n'y a plus rien à attaquer. Le vol
            // achève sa route, sans combat (le legacy, lui, lisait la ligne sans regarder son
            // drapeau et poursuivait sur une planète effacé).
            if ($TargetPlanet === false) {
                return;
            }

            $TargetUserID = (int) $TargetPlanet['id_owner'];

            $CurrentUser   = $users->findFullById((int) $FleetRow['fleet_owner']);
            $CurrentUserID = $CurrentUser['id'];

            $TargetUser = $users->findFullById($TargetUserID);

            // Actualisation des ressources de la planete.
            PlanetResourceUpdate($TargetUser, $TargetPlanet, time());

            $TargetTechno  = $users->findTechnologies($TargetUserID);
            $CurrentTechno = $users->findTechnologies((int) $CurrentUserID);

            for ($SetItem = 200; $SetItem < 500; $SetItem++) {
                if ($TargetPlanet[$resource[$SetItem]] > 0) {
                    $TargetSet[$SetItem]['count'] = $TargetPlanet[$resource[$SetItem]];
                }
            }

            // Défense groupée : les flottes en stationnement chez le défenseur (mission 5,
            // arrivée déjà annoncée) combattent à ses côtés. Le moteur ne connaît
            // qu'une force par camp : on fusionne les compositions, et les
            // survivants seront répartis au prorata après le combat.
            $DefenderUnits = array();
            foreach ($TargetSet as $DefenderShip => $DefenderCount) {
                $DefenderUnits[(int) $DefenderShip] = (int) $DefenderCount['count'];
            }

            // Les flottes en stationnement chez le défenseur (mission 5 arrivée, drapeau compris) :
            // le dépôt des vols porte la condition, le Coeur d'application ne la réécrit pas.
            $DefenderGuards = (new \App\Repositories\FleetRepository())->findGuardsAtPosition(
                (int) $FleetRow['fleet_end_galaxy'],
                (int) $FleetRow['fleet_end_system'],
                (int) $FleetRow['fleet_end_planet'],
                (int) $FleetRow['fleet_end_type']
            );

            $GuardUnits = array();

            foreach ($DefenderGuards as $GuardRow) {
                $GuardUnits[] = \App\Services\FlyingFleetService::parseUnits($GuardRow['fleet_array']);
            }

            $MergedDefenders = \App\Core\Combat\ForceSplit::merge(array_merge(array($DefenderUnits), $GuardUnits));
            $TargetSet = array();

            foreach ($MergedDefenders as $DefenderShip => $DefenderCount) {
                $TargetSet[$DefenderShip]['count'] = $DefenderCount;
            }

            $TheFleet = explode(";", $FleetRow['fleet_array']);
            foreach ($TheFleet as $a => $b) {
                if ($b != '') {
                    $a = explode(",", $b);
                    $CurrentSet[$a[0]]['count'] = $a[1];
                }
            }

            // Calcul de la duree de traitement (initialisation)
            $mtime = microtime();
            $mtime = explode(" ", $mtime);
            $mtime = $mtime[1] + $mtime[0];
            $starttime = $mtime;

            // Point de surcharge : un module peut dériver le moteur (même nom, même
            // couche) ; sans module, c'est la classe du Coeur d'application qui répond.
            $Engine = \App\Services\ModuleService::resolve(\App\Core\Combat\BattleEngine::class);
            $battle = $Engine::resolve($CurrentSet, $TargetSet, $CurrentTechno, $TargetTechno);
            // Calcul de la duree de traitement (calcul)
            $mtime = microtime();
            $mtime = explode(" ", $mtime);
            $mtime = $mtime[1] + $mtime[0];
            $endtime = $mtime;
            $totaltime = ($endtime - $starttime);
            // Ce qu'il reste de l'attaquant
            $CurrentSet = $battle["attacker"];
            // Ce qu'il reste de l'attaqué
            $TargetSet = $battle["defender"];
            // Le resultat de la bataille
            $FleetResult = $battle["victory"];
            // Rapport long (rapport de bataille detaillé)
            $battleRounds = $battle["rounds"];
            // Rapport court (cdr + unitées perdues)
            $debris = $battle["debris"];

            $FleetArray = "";
            $FleetAmount = 0;
            $FleetStorage = 0;
            foreach ($CurrentSet as $Ship => $Count) {
                $FleetStorage += $pricelist[$Ship]["capacity"] * $Count['count'];
                $FleetArray .= $Ship . "," . $Count['count'] . ";";
                $FleetAmount += $Count['count'];
            }
            // Au cas ou le p'tit rigolo qu'a envoyé la flotte y avait mis des ressources ...
            $FleetStorage -= $FleetRow["fleet_resource_metal"];
            $FleetStorage -= $FleetRow["fleet_resource_crystal"];
            $FleetStorage -= $FleetRow["fleet_resource_deuterium"];

            $TargetPlanetColumns = array();

            // Survivants répartis entre la planète et chaque flotte en stationnement : le
            // premier demandeur de la répartition est la planète elle-même.
            $DefenderSurvivors = array();
            foreach ((array) $TargetSet as $Ship => $Count) {
                $DefenderSurvivors[(int) $Ship] = (int) ($Count['count'] ?? 0);
            }

            $DefenderShares = \App\Core\Combat\ForceSplit::survivors(array_merge(array($DefenderUnits), $GuardUnits), $DefenderSurvivors);
            $PlanetShare = $DefenderShares[0] ?? array();

            // Tous les types engagés sont écrits, y compris ceux qui ont été
            // entièrement détruits (sinon la planète gardait ses vaisseaux perdus).
            $DefenderTypes = array_keys($DefenderUnits);

            foreach ($GuardUnits as $GuardForce) {
                $DefenderTypes = array_merge($DefenderTypes, array_keys($GuardForce));
            }

            foreach (array_unique(array_map('intval', $DefenderTypes)) as $DefenderShip) {
                if (!isset($resource[$DefenderShip])) {
                    continue;
                }

                $TargetPlanetColumns[$resource[$DefenderShip]] = (int) ($PlanetShare[$DefenderShip] ?? 0);
            }

            // Chaque flotte en stationnement repart avec sa part de survivants ; anéantie,
            // elle disparaît (son propriétaire est prévenu par le rapport).
            foreach ($DefenderGuards as $GuardIndex => $GuardRow) {
                $GuardShare = $DefenderShares[$GuardIndex + 1] ?? array();
                $GuardArray = \App\Services\FlyingFleetService::unitsArray($GuardShare);

                if ($GuardArray === '') {
                    $missions->deleteFleet((int) $GuardRow['fleet_id']);
                    continue;
                }

                $missions->setFleetUnits((int) $GuardRow['fleet_id'], $GuardArray, (int) array_sum($GuardShare));
            }
            // Determination des ressources pillées
            $Mining['metal'] = 0;
            $Mining['crystal'] = 0;
            $Mining['deuter'] = 0;
            if ($FleetResult == "a") {
                if ($FleetStorage > 0) {
                    $metal = $TargetPlanet['metal'] / 2;
                    $crystal = $TargetPlanet['crystal'] / 2;
                    $deuter = $TargetPlanet["deuterium"] / 2;
                    if (($metal) > $FleetStorage / 3) {
                        $Mining['metal'] = $FleetStorage / 3;
                        $FleetStorage = $FleetStorage - $Mining['metal'];
                    } else {
                        $Mining['metal'] = $metal;
                        $FleetStorage = $FleetStorage - $Mining['metal'];
                    }

                    if (($crystal) > $FleetStorage / 2) {
                        $Mining['crystal'] = $FleetStorage / 2;
                        $FleetStorage = $FleetStorage - $Mining['crystal'];
                    } else {
                        $Mining['crystal'] = $crystal;
                        $FleetStorage = $FleetStorage - $Mining['crystal'];
                    }

                    if (($deuter) > $FleetStorage) {
                        $Mining['deuter'] = $FleetStorage;
                        $FleetStorage = $FleetStorage - $Mining['deuter'];
                    } else {
                        $Mining['deuter'] = $deuter;
                        $FleetStorage = $FleetStorage - $Mining['deuter'];
                    }
                }
            }
            $Mining['metal'] = round($Mining['metal']);
            $Mining['crystal'] = round($Mining['crystal']);
            $Mining['deuter'] = round($Mining['deuter']);
            // Mise a jour de l'enregistrement de la planete attaquée : ses survivants
            // (tous les types engagés, ceux tombés à zéro compris) puis le pillage.
            $planets->updateColumns((int) $TargetPlanet['id'], $TargetPlanetColumns);
            $planets->addResources(
                (int) $TargetPlanet['id'],
                -$Mining['metal'],
                -$Mining['crystal'],
                -$Mining['deuter']
            );
            // Mise a jour du champ de débris devant la planete attaquée.
            // Les colonnes écrites sont celles que le jeu **déclare** comme champs de
            // débris (deux pour le Coeur d'application, plus celles qu'un module ajoute) et que le
            // moteur a renseignées dans `debris` : le Coeur d'application n'a pas à connaître le
            // deutérium en particulier.
            $DebrisFields  = array('metal' => 'Metal', 'crystal' => 'Crystal')
                + (new \App\Services\ModuleService())->debrisFields();
            $DebrisAmounts = array();
            foreach ($DebrisFields as $DebrisColumn => $DebrisLabel) {
                if (!array_key_exists($DebrisColumn, $debris)) {
                    continue;
                }

                $DebrisAmounts[$DebrisColumn] = $debris[$DebrisColumn];
            }

            (new \App\Repositories\GalaxyRepository())->addDebrisAt(
                (int) $FleetRow['fleet_end_galaxy'],
                (int) $FleetRow['fleet_end_system'],
                (int) $FleetRow['fleet_end_planet'],
                $DebrisAmounts
            );
            // Là on va discuter le bout de gras pour voir s'il y a moyen d'avoir une Lune !
            $FleetDebris = $debris['metal'] + $debris['crystal'];
            $StrAttackerUnits = sprintf($lang['sys_attacker_lostunits'], pretty_number($debris["attacker"]));
            $StrDefenderUnits = sprintf($lang['sys_defender_lostunits'], pretty_number($debris["defender"]));
            $StrRuins = sprintf($lang['sys_gcdrunits'], pretty_number($debris["metal"]), $lang['Metal'], pretty_number($debris['crystal']), $lang['Crystal'])
                . (new \App\Services\ModuleService())->debrisExtraReport($debris, $lang);
            $DebrisField = $StrAttackerUnits . "<br />" . $StrDefenderUnits . "<br />" . $StrRuins;
            $MoonChance = $FleetDebris / 100000;
            if ($FleetDebris > 2000000) {
                $MoonChance = 20;
            }
            if ($FleetDebris < 100000) {
                $UserChance = 0;
                $ChanceMoon = "";
            } elseif ($FleetDebris >= 100000) {
                $UserChance = mt_rand(1, 100);
                $ChanceMoon = sprintf($lang['sys_moonproba'], $MoonChance);
            }

            if (($UserChance > 0) and ($UserChance <= $MoonChance) and $galenemyrow['id_luna'] == 0) {
                $TargetPlanetName = CreateOneMoonRecord($FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet'], $TargetUserID, $FleetRow['fleet_start_time'], '', $MoonChance);
                $GottenMoon = sprintf($lang['sys_moonbuilt'], $TargetPlanetName, $FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet']);
            } elseif ($UserChance = 0 or $UserChance > $MoonChance) {
                $GottenMoon = "";
            }

            $AttackDate = date("r", $FleetRow["fleet_start_time"]);
            $title = sprintf($lang['sys_attack_title'], $AttackDate);
            $destroyed = false;
            $rounds = '';
            $struck = 0;
            $AttackTechon['A'] = $CurrentTechno["military_tech"] * 10;
            $AttackTechon['B'] = $CurrentTechno["defence_tech"] * 10;
            $AttackTechon['C'] = $CurrentTechno["shield_tech"] * 10;
            $AttackerData = sprintf($lang['sys_attack_attacker_pos'], $CurrentUser["username"], $FleetRow['fleet_start_galaxy'], $FleetRow['fleet_start_system'], $FleetRow['fleet_start_planet']);

            // Attaque groupée : le rapport nomme chaque attaquant et la flotte qu'il
            // engage. Les tableaux de tours, eux, montrent la force réunie (c'est
            // elle que le moteur fait combattre, en un seul combat).
            if (!empty($FleetRow['acs_attackers']) && is_array($FleetRow['acs_attackers'])) {
                $AttackerLines = array();

                foreach ($FleetRow['acs_attackers'] as $Membre) {
                    $AttackerLines[] = sprintf($lang['sys_attack_attacker_pos'], $Membre['name'], $Membre['galaxy'], $Membre['system'], $Membre['planet'])
                        . '<br />' . sprintf($lang['sys_attack_attacker_fleet'], \App\Services\FlyingFleetService::unitsLabel($Membre['units'] ?? array(), $lang['tech_rc'] ?? array()));
                }

                if (count($AttackerLines) > 1) {
                    $AttackerLines[] = $lang['sys_attack_acs_combined'];
                }

                $AttackerData = implode('<br />', $AttackerLines);
            }
            $AttackerTech = sprintf($lang['sys_attack_techologies'], $AttackTechon['A'], $AttackTechon['B'], $AttackTechon['C']);

            $DefendTechon['A'] = $TargetTechno["military_tech"] * 10;
            $DefendTechon['B'] = $TargetTechno["defence_tech"] * 10;
            $DefendTechon['C'] = $TargetTechno["shield_tech"] * 10;
            $DefenderData = sprintf($lang['sys_attack_defender_pos'], $TargetUser["username"], $FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet']);
            $DefenderTech = sprintf($lang['sys_attack_techologies'], $DefendTechon['A'], $DefendTechon['B'], $DefendTechon['C']);

            foreach ($battleRounds as $a => $b) {
                // Le balisage du rapport vit dans des gabarits : le même bloc sert
                // aux deux camps, seul le contenu change.
                $round = array(
                    'round_title' => sprintf($lang['sys_battle_round'], $a),
                    'round_attacker' => BattleReportSide($AttackerData, $AttackerTech, $b["attacker"], $lang),
                    'round_defender' => BattleReportSide($DefenderData, $DefenderTech, $b["defender"], $lang),
                    'round_waves_class' => ' d-none',
                    'round_attack_wave' => '',
                    'round_defend_wave' => '',
                );

                if ((int) ($b["attacker"]['count'] ?? 0) <= 0) {
                    if ($a == 2) {
                        $struck = 1;
                    }
                    $destroyed = true;
                }

                if ((int) ($b["defender"]['count'] ?? 0) <= 0) {
                    $destroyed = true;
                }

                if (($destroyed == false) and !($a == 8)) {
                    $round['round_waves_class'] = '';
                    $round['round_attack_wave'] = sprintf($lang['sys_attack_attack_wave'], pretty_number(floor($b["attacker"]["attack"])), pretty_number(floor($b["defender"]["shield"])));
                    $round['round_defend_wave'] = sprintf($lang['sys_attack_defend_wave'], pretty_number(floor($b["defender"]["attack"])), pretty_number(floor($b["attacker"]["shield"])));
                }

                $rounds .= \App\Core\TemplateEngine::render('combat_report_round', $lang + $round);
            }

            switch ($FleetResult) {
                case "a":
                    $Statut = array('label' => $lang['sys_attacker_won'], 'badge' => 'text-bg-success', 'alert' => 'alert-success');
                    // Les noms de ressources des clés minuscules n'existent pas : le
                    // rapport affichait « unités de ,  », on prend les mêmes clés que
                    // la ligne du champ de débris.
                    $Pillage = sprintf($lang['sys_stealed_ressources'], pretty_number($Mining['metal']), $lang['Metal'], pretty_number($Mining['crystal']), $lang['Crystal'], pretty_number($Mining['deuter']), $lang['Deuterium']);
                    break;
                case "r":
                    $Statut = array('label' => $lang['sys_both_won'], 'badge' => 'text-bg-warning', 'alert' => 'alert-warning');
                    $Pillage = '';
                    break;
                case "w":
                    $Statut = array('label' => $lang['sys_defender_won'], 'badge' => 'text-bg-danger', 'alert' => 'alert-danger');
                    $Pillage = '';
                    $missions->deleteFleet((int) $FleetRow['fleet_id']);
                    break;
                default:
                    $Statut = array('label' => '', 'badge' => 'text-bg-secondary', 'alert' => 'alert-secondary');
                    $Pillage = '';
                    break;
            }

            // RapidFire : ce que chaque camp peut enchaîner sur les unités
            // réellement présentes en face (composition du premier tour).
            // Noms complets plutôt que les abréviations `tech_rc` du tableau des
            // unités : la colonne est large, « Art.ions » n'apprenait rien.
            $PremierTour = reset($battleRounds);
            $AttaquantEnFace = is_array($PremierTour) ? ($PremierTour['attacker'] ?? array()) : array();
            $DefenseurEnFace = is_array($PremierTour) ? ($PremierTour['defender'] ?? array()) : array();
            $NomsUnites = (is_array($lang['tech'] ?? null) && $lang['tech'] !== array())
                ? $lang['tech']
                : ($lang['tech_rc'] ?? array());

            $RapidFireAttaquant = \App\Core\Combat\RapidFire::rows($AttaquantEnFace, $DefenseurEnFace, $CombatCaps, $NomsUnites);
            $RapidFireDefenseur = \App\Core\Combat\RapidFire::rows($DefenseurEnFace, $AttaquantEnFace, $CombatCaps, $NomsUnites);

            $RapidFireReport = '';
            if ($RapidFireAttaquant !== array() || $RapidFireDefenseur !== array()) {
                $RapidFireReport = BattleReportRapidFire($lang['sys_rf_attacker'], $RapidFireAttaquant, $lang)
                    . BattleReportRapidFire($lang['sys_rf_defender'], $RapidFireDefenseur, $lang);
            }

            // Forces engagées et pertes, participant par participant : le moteur ne
            // combat qu'une force par camp, les pertes sont donc réparties au prorata
            // de ce que chacun a engage (ForceSplit).
            $AttackerEngaged = array();

            if (!empty($FleetRow['acs_attackers']) && is_array($FleetRow['acs_attackers'])) {
                foreach ($FleetRow['acs_attackers'] as $Membre) {
                    $AttackerEngaged[] = array(
                        'name' => (string) ($Membre['name'] ?? ''),
                        'units' => is_array($Membre['units'] ?? null) ? $Membre['units'] : array(),
                    );
                }
            } else {
                $AttackerEngaged[] = array(
                    'name' => (string) $CurrentUser['username'],
                    'units' => \App\Services\FlyingFleetService::parseUnits($FleetRow['fleet_array']),
                );
            }

            $AttackerSurvivors = array();
            foreach ((array) $CurrentSet as $Ship => $Count) {
                $AttackerSurvivors[(int) $Ship] = (int) ($Count['count'] ?? 0);
            }

            $AttackerShares = \App\Core\Combat\ForceSplit::survivors(array_column($AttackerEngaged, 'units'), $AttackerSurvivors);
            $ForcesRows = '';

            foreach ($AttackerEngaged as $Index => $Membre) {
                $ForcesRows .= BattleReportForceRow($Membre['name'], $lang['sys_attack_attacker_side'], $Membre['units'], $AttackerShares[$Index] ?? array(), $lang, 'text-bg-danger');
            }

            // Défenseurs : le propriétaire de la planète puis chaque flotte en stationnement
            // chez lui (défense groupée), avec sa propre part de survivants.
            $DefenderReport = array(array('name' => (string) $TargetUser['username'], 'units' => $DefenderUnits));

            foreach ($DefenderGuards as $GuardIndex => $GuardRow) {
                $GuardName = $users->username((int) $GuardRow['fleet_owner']);

                $DefenderReport[] = array(
                    'name' => ($GuardName !== '') ? $GuardName : ('#' . (int) $GuardRow['fleet_owner']),
                    'units' => $GuardUnits[$GuardIndex] ?? array(),
                );
            }

            foreach ($DefenderReport as $Index => $Membre) {
                $ForcesRows .= BattleReportForceRow($Membre['name'], $lang['sys_attack_defender_side'], $Membre['units'], $DefenderShares[$Index] ?? array(), $lang, 'text-bg-success');
            }

            $ForcesReport = \App\Core\TemplateEngine::render('combat_report_forces', $lang + array('forces_rows' => $ForcesRows));

            $raport = \App\Core\TemplateEngine::render('combat_report', $lang + array(
                'report_title' => $title,
                'report_result' => $Statut['label'],
                'report_result_class' => $Statut['badge'],
                'report_result_alert' => $Statut['alert'],
                'report_conclusion' => $Statut['label'],
                'report_rounds' => $rounds,
                'report_rapidfire_list' => $RapidFireReport,
                'report_rapidfire_class' => ($RapidFireReport === '') ? ' d-none' : '',
                'report_forces' => $ForcesReport,
                'report_loss_attacker' => $StrAttackerUnits,
                'report_loss_defender' => $StrDefenderUnits,
                'report_plunder' => $Pillage,
                'report_plunder_class' => ($Pillage === '') ? ' d-none' : '',
                'report_debris' => $StrRuins,
                'report_moon' => $ChanceMoon,
                'report_moon_class' => ($ChanceMoon === '') ? ' d-none' : '',
                // La création de la lune est annoncée à part, en évidence : c'est
                // l'information que le joueur cherche dans le rapport.
                'report_moon_built' => $GottenMoon,
                'report_moon_built_class' => ($GottenMoon === '') ? ' d-none' : '',
                'report_sim' => sprintf($lang['sys_rapport_build_time'], $totaltime),
            ));

            $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];
            $rid = md5($raport);

            (new \App\Repositories\RwRepository())->insertReport(
                (int) $FleetRow['fleet_owner'],
                $TargetUserID,
                $rid,
                (int) $struck,
                $raport
            );
            // Colorisation du résumé de rapport pour l'attaquant
            $raport = "<a href # OnClick=\"f( '/game/rw?raport=" . $rid . "', '');\" >";
            $raport .= "<center>";
            if ($FleetResult == "a") {
                $raport .= "<font color=\"green\">";
            } elseif ($FleetResult == "r") {
                $raport .= "<font color=\"orange\">";
            } elseif ($FleetResult == "w") {
                $raport .= "<font color=\"red\">";
            }
            $raport .= $lang['sys_mess_attack_report'] . " [" . $FleetRow['fleet_end_galaxy'] . ":" . $FleetRow['fleet_end_system'] . ":" . $FleetRow['fleet_end_planet'] . "] </font></a><br /><br />";
            $raport .= "<font color=\"red\">" . $lang['sys_perte_attaquant'] . ": " . pretty_number($debris["attacker"]) . "</font>";
            $raport .= "<font color=\"green\">   " . $lang['sys_perte_defenseur'] . ":" . pretty_number($debris["defender"]) . "</font><br />";
            $raport .= $lang['sys_gain'] . " " . $lang['Metal'] . ":<font color=\"#adaead\">" . pretty_number($Mining['metal']) . "</font>   " . $lang['Crystal'] . ":<font color=\"#ef51ef\">" . pretty_number($Mining['crystal']) . "</font>   " . $lang['Deuterium'] . ":<font color=\"#f77542\">" . pretty_number($Mining['deuter']) . "</font><br />";
            $raport .= $lang['sys_debris'] . " " . $lang['Metal'] . ":<font color=\"#adaead\">" . pretty_number($debris['metal']) . "</font>   " . $lang['Crystal'] . ":<font color=\"#ef51ef\">" . pretty_number($debris['crystal']) . "</font><br /></center>";

            $Mining['metal'] = $Mining['metal'] + $FleetRow["fleet_resource_metal"];
            $Mining['crystal'] = $Mining['crystal'] + $FleetRow["fleet_resource_crystal"];
            $Mining['deuter'] = $Mining['deuter'] + $FleetRow["fleet_resource_deuterium"];

            // Le vol rentre : sa composition, son chargement et sa marque de retour (les
            // trois écritures sont liées, le dépôt ne connaît que des valeurs).
            $missions->setFleetUnits((int) $FleetRow['fleet_id'], $FleetArray, (int) $FleetAmount);
            $missions->updateFleetCargo((int) $FleetRow['fleet_id'], $Mining['metal'], $Mining['crystal'], $Mining['deuter']);
            $missions->markFleetReturning((int) $FleetRow['fleet_id']);

            SendSimpleMessage($CurrentUserID, '', $FleetRow['fleet_start_time'], 3, $lang['sys_mess_tower'], $lang['sys_mess_attack_report'], $raport);
            // Ajout du petit point raideur
            $users->rewardRaid((int) $CurrentUserID);
            // Ajout d'un point au compteur de raids. Le raid groupé compte pour
            // chacun de ses participants ; les deux compteurs sont écrits ensemble,
            // car le raid perdu écrivait `raidsloose` dans la colonne `raidswin`
            // (« Raids Perdus » restait à zéro, « Raids Gagnés » montait à chaque
            // défaite).
            if ($FleetResult == "a" || $FleetResult == "r" || $FleetResult == "w") {
                $Participants = (!empty($FleetRow['acs_attackers']) && is_array($FleetRow['acs_attackers']))
                    ? $FleetRow['acs_attackers']
                    : array(array('id' => $CurrentUserID));

                foreach ($Participants as $Participant) {
                    $OwnerID = (int) ($Participant['id'] ?? 0);

                    if ($OwnerID <= 0) {
                        continue;
                    }

                    $RaidsCounters = \App\Services\FleetMissionService::raidCounters(
                        $users->findRaidCounters($OwnerID),
                        $FleetResult == "a"
                    );

                    $users->setRaidCounters($OwnerID, $RaidsCounters);
                }
            }
            // Colorisation du résumé de rapport pour l'attaquant
            $raport2 = "<a href # OnClick=\"f( '/game/rw?raport=" . $rid . "', '');\" >";
            $raport2 .= "<center>";
            if ($FleetResult == "a") {
                $raport2 .= "<font color=\"green\">";
            } elseif ($FleetResult == "r") {
                $raport2 .= "<font color=\"orange\">";
            } elseif ($FleetResult == "w") {
                $raport2 .= "<font color=\"red\">";
            }
            $raport2 .= $lang['sys_mess_attack_report'] . " [" . $FleetRow['fleet_end_galaxy'] . ":" . $FleetRow['fleet_end_system'] . ":" . $FleetRow['fleet_end_planet'] . "] </font></a><br /><br />";

            SendSimpleMessage($TargetUserID, '', $FleetRow['fleet_start_time'], 3, $lang['sys_mess_tower'], $lang['sys_mess_attack_report'], $raport2);
        }
        // Retour de flotte (s'il en reste)
        if ($FleetRow['fleet_end_time'] <= time()) {
            // Ce que le vol ramène : sa composition en mémoire quand le combat vient d'avoir
            // lieu, sinon celle qu'il porte en base (deuxième passage, au retour).
            $ReturnShips = array();

            if (!is_null($CurrentSet)) {
                foreach ($CurrentSet as $Ship => $Count) {
                    $ReturnShips[$resource[$Ship]] = (int) $Count['count'];
                }
            } else {
                foreach (\App\Services\FlyingFleetService::parseUnits($FleetRow['fleet_array']) as $Ship => $Count) {
                    if (!isset($resource[$Ship])) {
                        continue;
                    }

                    $ReturnShips[$resource[$Ship]] = (int) $Count;
                }
            }

            $missions->deleteFleet((int) $FleetRow['fleet_id']);

            if (!($FleetResult == "w")) {
                $planets->addFleetCargoAtPosition(
                    (int) $FleetRow['fleet_start_galaxy'],
                    (int) $FleetRow['fleet_start_system'],
                    (int) $FleetRow['fleet_start_planet'],
                    (int) $FleetRow['fleet_start_type'],
                    $ReturnShips,
                    array(
                        'metal' => $FleetRow['fleet_resource_metal'],
                        'crystal' => $FleetRow['fleet_resource_crystal'],
                        'deuterium' => $FleetRow['fleet_resource_deuterium'],
                    )
                );
            }
        }
    }
}

//

// ===== MissionCaseDestruction =====
function MissionCaseDestruction($FleetRow)
{
    global $user, $ugamela_root_path, $pricelist, $lang, $resource, $CombatCaps;

    includeLang('system');

    if ($FleetRow['fleet_start_time'] <= time()) {

        if ($FleetRow['fleet_mess'] == 0) {

            if (!isset($CombatCaps[202]['sd'])) {

                message("<font color=\"red\">" . $lang['sys_no_vars'] . "</font>", $lang['sys_error'], "fleet." . PHPEXT, 2);
            }

            $planets  = new \App\Repositories\PlanetRepository();
            // Le depot est resolu : le module officier le derive pour verser le bonus de combat
    // de l'amiral (findTechnologies) et le point de raid (rewardRaid).
    $users    = \App\Services\ModuleService::instance(\App\Repositories\UserRepository::class);
            $fleets   = new \App\Repositories\FleetRepository();
            $missions = new \App\Repositories\MissionRepository();

            // La cible d'une attaque de lune se lit par sa position, drapeau compris : une lune
            // déjà détruite ne s'y trouve plus, et l'attaque s'arrête au lieu de combattre une
            // ligne effacée (le legacy, lui, la lisait sans regarder son état).
            $TargetPlanet = $planets->findByCoords(
                (int) $FleetRow['fleet_end_galaxy'],
                (int) $FleetRow['fleet_end_system'],
                (int) $FleetRow['fleet_end_planet'],
                (int) $FleetRow['fleet_end_type']
            );

            if ($TargetPlanet === false) {
                return;
            }

            $TargetUserID = (int) $TargetPlanet['id_owner'];

            // La planète de départ, pour le nom du rapport.
            $DepPlanet = $planets->findByCoords(
                (int) $FleetRow['fleet_start_galaxy'],
                (int) $FleetRow['fleet_start_system'],
                (int) $FleetRow['fleet_start_planet'],
                (int) $FleetRow['fleet_start_type']
            );

            $DepName = (string) ($DepPlanet['name'] ?? '');

            $CurrentUser   = $users->findFullById((int) $FleetRow['fleet_owner']);
            $CurrentUserID = $CurrentUser['id'];

            $TargetUser = $users->findFullById($TargetUserID);

            $TargetTechno  = $users->findTechnologies($TargetUserID);
            $CurrentTechno = $users->findTechnologies((int) $CurrentUserID);

            for ($SetItem = 200; $SetItem < 500; $SetItem++) {

                if ($TargetPlanet[$resource[$SetItem]] > 0) {

                    $TargetSet[$SetItem]['count'] = $TargetPlanet[$resource[$SetItem]];
                }
            }

            $TheFleet = explode(";", $FleetRow['fleet_array']);

            foreach ($TheFleet as $a => $b) {

                if ($b != '') {

                    $a = explode(",", $b);

                    $CurrentSet[$a[0]]['count'] = $a[1];
                }
            }

            // Calcul de la duree de traitement (initialisation)

            $mtime        = microtime();

            $mtime        = explode(" ", $mtime);

            $mtime        = $mtime[1] + $mtime[0];

            $starttime    = $mtime;

            // Point de surcharge : voir MissionCaseAttack(). L'appel au moteur avait disparu de
            // cette fonction lors du renommage `implementation()` -> `resolve()` : sans lui,
            // `$battle` restait indéfini, aucun combat n'avait lieu, le rapport sortait sans issue
            // et la destruction de la lune ne pouvait jamais être tirée.
            $Engine = \App\Services\ModuleService::resolve(\App\Core\Combat\BattleEngine::class);
            $battle  = $Engine::resolve($CurrentSet, $TargetSet, $CurrentTechno, $TargetTechno);

            // Calcul de la duree de traitement (calcul)

            $mtime        = microtime();

            $mtime        = explode(" ", $mtime);

            $mtime        = $mtime[1] + $mtime[0];

            $endtime      = $mtime;

            $totaltime    = ($endtime - $starttime);

            // Ce qu'il reste de l'attaquant

            $CurrentSet   = $battle["attacker"];

            // Ce qu'il reste de l'attaqué

            $TargetSet    = $battle["defender"];

            // Le resultat de la bataille

            $FleetResult  = $battle["victory"];

            // Rapport long (rapport de bataille détaillé)

            $battleRounds = $battle["rounds"];

            // Rapport court (cdr + unités perdues)

            $debris       = $battle["debris"];

            $FleetArray   = "";

            $FleetAmount  = 0;

            $FleetStorage = 0;

            foreach ($CurrentSet as $Ship => $Count) {

                $FleetStorage += $pricelist[$Ship]["capacity"] * $Count['count'];

                $FleetArray   .= $Ship . "," . $Count['count'] . ";";

                $FleetAmount  += $Count['count'];
            }

            $TargetPlanetColumns = array();

            if (!is_null($TargetSet)) {

                foreach ($TargetSet as $Ship => $Count) {

                    $TargetPlanetColumns[$resource[$Ship]] = (int) $Count['count'];
                }
            }

            if ($FleetResult == "a") {
                //debut des probabilite de destruction
                //Nous y voila! l attaquant a gagne, nous allons voir ses chances de detruire la lune
                $destructionl1 = 100 - sqrt($TargetPlanet['diameter']);
                $destructionl21 = $destructionl1 * sqrt($CurrentSet['214']['count']);

                $destructionl2 = $destructionl21 / 1; //ici c est la sensibilite de la destruction 1 c est l equivalent d ogame a 12 on a environ 2% pour 1000 rip
                //maintenant qu on sait quelle chance tantons la destruction, faites vos jeux croupier
                //$chance = round($destructionl2); // En pourcentage
                if ($destructionl2 > 100) {
                    $chance = '100';
                } else {
                    $chance = round($destructionl2); // En pourcentage
                }
                $tirage = mt_rand(0, 100);
                $probalune       = sprintf($lang['sys_destruc_lune'], $chance);
                if ($tirage <= $chance) {
                    $resultat = '1'; // lune detruite
                    $finmess = $lang['sys_destruc_reussi'];
                    //destruction de la lune dabord dans la liste des planetes puis dans la liste des lunes et enfin dans la galaxie
                    //La destruction est **logique** : la ligne de la lune reste en base (drapeau
                    //`DELETED`), ses lectures ne la trouvent plus, le panneau peut la rétablir.
                    (new \App\Repositories\PlanetRepository())->markMoonDeletedById((int) $TargetPlanet['id']);

                    // Le registre des lunes n'est jamais supprimé : l'entrée est marquée détruite.
                    $planets->markMoonRegistryDestroyed(
                        (int) $FleetRow['fleet_end_galaxy'],
                        (int) $FleetRow['fleet_end_system'],
                        (int) $FleetRow['fleet_end_planet']
                    );

                    // La position ne doit plus annoncer de lune (le champ de débris reste).
                    (new \App\Repositories\GalaxyRepository())->clearMoonLink(
                        (int) $FleetRow['fleet_end_galaxy'],
                        (int) $FleetRow['fleet_end_system'],
                        (int) $FleetRow['fleet_end_planet']
                    );
                    //la lune est detruite, alors on redirige les flottes sur la planete
                    // La lune n'existe plus : les vols qui partent de cette position — ou qui la
                    // visent — se rabattent sur la planète du même endroit (les deux branches).
                    $fleets->setWorldTypeAtPosition(
                        (int) $FleetRow['fleet_end_galaxy'],
                        (int) $FleetRow['fleet_end_system'],
                        (int) $FleetRow['fleet_end_planet'],
                        1,
                        'start'
                    );
                    $fleets->setWorldTypeAtPosition(
                        (int) $FleetRow['fleet_end_galaxy'],
                        (int) $FleetRow['fleet_end_system'],
                        (int) $FleetRow['fleet_end_planet'],
                        1,
                        'end'
                    );

                    //maintenant on va verifier si la vue du joueur n est pas calee sur la lune qui est detruite
                    // La vue du joueur était peut-être calée sur la lune qui vient de disparaître :
                    // il repasse sur la planète du même endroit.
                    if ($TargetUser['current_planet'] == $TargetPlanet['id']) {
                        $HomePlanet = $planets->findByCoords(
                            (int) $FleetRow['fleet_end_galaxy'],
                            (int) $FleetRow['fleet_end_system'],
                            (int) $FleetRow['fleet_end_planet'],
                            1
                        );

                        if ($HomePlanet !== false) {
                            $users->setCurrentPlanet((int) $HomePlanet['id'], $TargetUserID);
                        }
                    }
                } else {
                    $resultat = '0';
                } // la lune a resistee
                //la lune a resistee, alors voyons les chances que les rip soient detruites
                $destructionrip = sqrt($TargetPlanet['diameter']) / 2;
                //maintenant qu on sait quelle chance tantons la destruction, allez roule croupier
                $chance2 = round($destructionrip); // En pourcentage
                if ($resultat == 0) {
                    $tirage2 = mt_rand(0, 100);
                    $probarip       = sprintf($lang['sys_destruc_rip'], $chance2);
                    if ($tirage2 <= $chance2) {
                        $resultat2 = ' detruite 1'; // RIP detruite
                        $finmess = $lang['sys_destruc_echec'];
                        $missions->deleteFleet((int) $FleetRow['fleet_id']);
                    } else {
                        $resultat2 = 'sauvees 0'; // les RIP sont saines et sauves
                        $finmess = $lang['sys_destruc_null'];
                    }
                }
                //fin
            }

            $introdestruc       = sprintf($lang['sys_destruc_mess'], $DepName, $FleetRow['fleet_start_galaxy'], $FleetRow['fleet_start_system'], $FleetRow['fleet_start_planet'], $FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet']);

            // Mise a jour de l'enregistrement de la planète attaqué : ses survivants. Le legacy
            // retranchait ici un pillage qui n'est jamais calculé dans cette fonction (`$Mining`
            // n'y existe pas) : le montant est donc nul, comme MySQL le lisait de la chaîne vide.
            $planets->updateColumns((int) $TargetPlanet['id'], $TargetPlanetColumns);
            $planets->addResources((int) $TargetPlanet['id'], 0.0, 0.0, 0.0);

            // Mise a jour du champ de débris devant la planète attaqué.
            (new \App\Repositories\GalaxyRepository())->addDebrisAt(
                (int) $FleetRow['fleet_end_galaxy'],
                (int) $FleetRow['fleet_end_system'],
                (int) $FleetRow['fleet_end_planet'],
                array('metal' => $debris['metal'], 'crystal' => $debris['crystal'])
            );

            // Là on va discuter le bout de gras pour voir s'il y a moyen d'avoir une Lune !

            $FleetDebris      = $debris['metal'] + $debris['crystal'];

            $StrAttackerUnits = sprintf($lang['sys_attacker_lostunits'], $debris["attacker"]);

            $StrDefenderUnits = sprintf($lang['sys_defender_lostunits'], $debris["defender"]);

            $StrRuins         = sprintf($lang['sys_gcdrunits'], $debris["metal"], $lang['Metal'], $debris['crystal'], $lang['Crystal'])
                . (new \App\Services\ModuleService())->debrisExtraReport($debris, $lang);

            $DebrisField      = $StrAttackerUnits . "<br />" . $StrDefenderUnits . "<br />" . $StrRuins;

            $MoonChance       = $FleetDebris / 100000;

            if ($FleetDebris > 2000000) {

                $MoonChance = 20;
            }

            if ($FleetDebris < 100000) {

                $UserChance = 0;

                $ChanceMoon = "";
            } elseif ($FleetDebris >= 100000) {

                $UserChance = mt_rand(1, 100);

                $ChanceMoon       = sprintf($lang['sys_moonproba'], $MoonChance);
            }

            if (($UserChance > 0) and ($UserChance <= $MoonChance) and $galenemyrow['id_luna'] == 0) {

                $TargetPlanetName = CreateOneMoonRecord($FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet'], $TargetUserID, $FleetRow['fleet_start_time'], '', $MoonChance);

                $GottenMoon       = sprintf($lang['sys_moonbuilt'], $TargetPlanetName, $FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet']);
            } elseif ($UserChance = 0 or $UserChance > $MoonChance) {

                $GottenMoon = "";
            }

            $AttackDate        = date("r", $FleetRow["fleet_start_time"]);

            $title             = sprintf($lang['sys_destruc_title'], $AttackDate);

            // Le rapport est habillé par le même gabarit que le rapport de combat
            // (voir la fin de la fonction) : en-tête, issue, résumé. Le corps
            // historique (tableaux des unités et des vagues) y est inséré tel quel.
            $raport            = '';

            $destroyed         = false;

            $struck            = 0;

            $AttackTechon['A'] = $CurrentTechno["military_tech"] * 10;

            $AttackTechon['B'] = $CurrentTechno["defence_tech"] * 10;

            $AttackTechon['C'] = $CurrentTechno["shield_tech"] * 10;

            $AttackerData      = sprintf($lang['sys_attack_attacker_pos'], $CurrentUser["username"], $FleetRow['fleet_start_galaxy'], $FleetRow['fleet_start_system'], $FleetRow['fleet_start_planet']);

            $AttackerTech      = sprintf($lang['sys_attack_techologies'], $AttackTechon['A'], $AttackTechon['B'], $AttackTechon['C']);

            $DefendTechon['A'] = $TargetTechno["military_tech"] * 10;

            $DefendTechon['B'] = $TargetTechno["defence_tech"] * 10;

            $DefendTechon['C'] = $TargetTechno["shield_tech"] * 10;

            $DefenderData      = sprintf($lang['sys_attack_defender_pos'], $TargetUser["username"], $FleetRow['fleet_end_galaxy'], $FleetRow['fleet_end_system'], $FleetRow['fleet_end_planet']);

            $DefenderTech      = sprintf($lang['sys_attack_techologies'], $DefendTechon['A'], $DefendTechon['B'], $DefendTechon['C']);

            foreach ($battleRounds as $a => $b) {

                $raport .= "<table border=1 width=100%><tr><th><br /><center>" . $AttackerData . "<br />" . $AttackerTech . "<table border=1>";

                if ($b["attacker"]['count'] > 0) {

                    $raport1 = "<tr><th>" . $lang['sys_ship_type'] . "</th>";

                    $raport2 = "<tr><th>" . $lang['sys_ship_count'] . "</th>";

                    $raport3 = "<tr><th>" . $lang['sys_ship_weapon'] . "</th>";

                    $raport4 = "<tr><th>" . $lang['sys_ship_shield'] . "</th>";

                    $raport5 = "<tr><th>" . $lang['sys_ship_armour'] . "</th>";

                    foreach ($b["attacker"] as $Ship => $Data) {

                        if (is_numeric($Ship)) {

                            if ($Data['count'] > 0) {

                                $raport1 .= "<th>" . $lang["tech_rc"][$Ship] . "</th>";

                                $raport2 .= "<th>" . $Data['count'] . "</th>";

                                $raport3 .= "<th>" . round($Data["attack"] / $Data['count']) . "</th>";

                                $raport4 .= "<th>" . round($Data["shield"] / $Data['count']) . "</th>";

                                $raport5 .= "<th>" . round($Data["armour"] / $Data['count']) . "</th>";
                            }
                        }
                    }

                    $raport1 .= "</tr>";

                    $raport2 .= "</tr>";

                    $raport3 .= "</tr>";

                    $raport4 .= "</tr>";

                    $raport5 .= "</tr>";

                    $raport .= $raport1 . $raport2 . $raport3 . $raport4 . $raport5;
                } else {

                    if ($a == 2) {

                        $struck = 1;
                    }

                    $destroyed = true;

                    $raport .= "<br />" . $lang['sys_destroyed'];
                }

                $raport .= "</table></center></th></tr></table>";

                $raport .= "<table border=1 width=100%><tr><th><br /><center>" . $DefenderData . "<br />" . $DefenderTech . "<table border=1>";

                if ($b["defender"]['count'] > 0) {

                    $raport1 = "<tr><th>" . $lang['sys_ship_type'] . "</th>";

                    $raport2 = "<tr><th>" . $lang['sys_ship_count'] . "</th>";

                    $raport3 = "<tr><th>" . $lang['sys_ship_weapon'] . "</th>";

                    $raport4 = "<tr><th>" . $lang['sys_ship_shield'] . "</th>";

                    $raport5 = "<tr><th>" . $lang['sys_ship_armour'] . "</th>";

                    foreach ($b["defender"] as $Ship => $Data) {

                        if (is_numeric($Ship)) {

                            if ($Data['count'] > 0) {

                                $raport1 .= "<th>" . $lang["tech_rc"][$Ship] . "</th>";

                                $raport2 .= "<th>" . $Data['count'] . "</th>";

                                $raport3 .= "<th>" . round($Data["attack"] / $Data['count']) . "</th>";

                                $raport4 .= "<th>" . round($Data["shield"] / $Data['count']) . "</th>";

                                $raport5 .= "<th>" . round($Data["armour"] / $Data['count']) . "</th>";
                            }
                        }
                    }

                    $raport1 .= "</tr>";

                    $raport2 .= "</tr>";

                    $raport3 .= "</tr>";

                    $raport4 .= "</tr>";

                    $raport5 .= "</tr>";

                    $raport .= $raport1 . $raport2 . $raport3 . $raport4 . $raport5;
                } else {

                    $destroyed = true;

                    $raport .= "<br />" . $lang['sys_destroyed'];
                }

                $raport .= "</table></center></th></tr></table>";

                if (($destroyed == false) and !($a == 8)) {

                    $AttackWaveStat    = sprintf($lang['sys_attack_attack_wave'], floor($b["attacker"]["attack"]), floor($b["defender"]["shield"]));

                    $DefendWavaStat    = sprintf($lang['sys_attack_defend_wave'], floor($b["defender"]["attack"]), floor($b["attacker"]["shield"]));

                    $raport           .= "<br /><center>" . $AttackWaveStat . "<br />" . $DefendWavaStat . "</center>";
                }
            }

            switch ($FleetResult) {

                case "a":

                    // La victoire, les pertes et le champ de débris sont déjà dans le
                    // résumé du rapport : le corps ne garde que la destruction lunaire,
                    // présentée comme un rendu.
                    $raport           .= \App\Core\TemplateEngine::render('combat_report_destruction', $lang + array(
                        'destruc_title' => $lang['sys_destruc_report_title'],
                        'destruc_badge' => ($resultat == '1') ? 'text-bg-danger' : 'text-bg-secondary',
                        'destruc_result' => ($resultat == '1') ? $lang['sys_destruc_ok'] : $lang['sys_destruc_ko'],
                        'destruc_intro' => $introdestruc . ' ' . $lang['sys_destruc_mess1'],
                        'destruc_chance' => $probalune,
                        'destruc_chance_class' => '',
                        'destruc_rips' => $probarip,
                        'destruc_rips_class' => ($probarip === '') ? ' d-none' : '',
                        'destruc_outcome' => $finmess,
                    ));

                    break;

                case "r":

                    $raport           .= \App\Core\TemplateEngine::render('combat_report_destruction', $lang + array(
                        'destruc_title' => $lang['sys_destruc_report_title'],
                        'destruc_badge' => 'text-bg-warning',
                        'destruc_result' => $lang['sys_destruc_stop'],
                        'destruc_intro' => $introdestruc,
                        'destruc_chance' => '',
                        'destruc_chance_class' => ' d-none',
                        'destruc_rips' => '',
                        'destruc_rips_class' => ' d-none',
                        'destruc_outcome' => $lang['sys_destruc_stop'],
                    ));

                    break;

                case "w":

                    $raport           .= \App\Core\TemplateEngine::render('combat_report_destruction', $lang + array(
                        'destruc_title' => $lang['sys_destruc_report_title'],
                        'destruc_badge' => 'text-bg-success',
                        'destruc_result' => $lang['sys_destruc_stop'],
                        'destruc_intro' => $introdestruc,
                        'destruc_chance' => '',
                        'destruc_chance_class' => ' d-none',
                        'destruc_rips' => '',
                        'destruc_rips_class' => ' d-none',
                        'destruc_outcome' => $lang['sys_destruc_stop'],
                    ));

                    $missions->deleteFleet((int) $FleetRow['fleet_id']);

                    break;

                default:

                    break;
            }

            $SimMessage        = sprintf($lang['sys_rapport_build_time'], $totaltime);

            // Même habillage que le rapport de combat : l'issue sert de badge et de
            // conclusion, le résumé reprend les pertes, le champ de débris et l'issue
            // de la tentative de destruction.
            $DestrucReussie    = (($resultat ?? '') == '1');

            $raport = \App\Core\TemplateEngine::render('combat_report', $lang + array(
                'report_title' => $title,
                'report_result' => $DestrucReussie ? $lang['sys_destruc_ok'] : $lang['sys_destruc_ko'],
                'report_result_class' => $DestrucReussie ? 'text-bg-danger' : 'text-bg-secondary',
                'report_result_alert' => $DestrucReussie ? 'alert-danger' : 'alert-secondary',
                'report_conclusion' => $finmess,
                'report_rounds' => $raport,
                'report_rapidfire_list' => '',
                'report_rapidfire_class' => ' d-none',
                'report_forces' => '',
                'report_loss_attacker' => $StrAttackerUnits,
                'report_loss_defender' => $StrDefenderUnits,
                'report_plunder' => '',
                'report_plunder_class' => ' d-none',
                'report_debris' => $StrRuins,
                'report_moon' => '',
                'report_moon_class' => ' d-none',
                'report_moon_built' => '',
                'report_moon_built_class' => ' d-none',
                'report_sim' => $SimMessage,
            ));

            $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];

            $rid   = md5($raport);

            (new \App\Repositories\RwRepository())->insertReport(
                (int) $FleetRow['fleet_owner'],
                $TargetUserID,
                $rid,
                (int) $struck,
                $raport
            );

            // Colorisation du résumé de rapport pour l'attaquant

            $raport  = "<a href # OnClick=\"f( '/game/rw?raport=" . $rid . "', '');\" >";

            $raport .= "<center>";

            if ($FleetResult == "a") {

                $raport .= "<font color=\"green\">";
            } elseif ($FleetResult == "r") {

                $raport .= "<font color=\"orange\">";
            } elseif ($FleetResult == "w") {

                $raport .= "<font color=\"red\">";
            }

            $raport .= $lang['sys_mess_destruc_report'] . " [" . $FleetRow['fleet_end_galaxy'] . ":" . $FleetRow['fleet_end_system'] . ":" . $FleetRow['fleet_end_planet'] . "] </font></a><br /><br />";

            $raport .= "<font color=\"red\">" . $lang['sys_perte_attaquant'] . ": " . $debris["attacker"] . "</font>";

            $raport .= "<font color=\"green\">   " . $lang['sys_perte_defenseur'] . ":" . $debris["defender"] . "</font><br />";

            $raport .= $lang['sys_debris'] . " " . $lang['Metal'] . ":<font color=\"#adaead\">" . $debris['metal'] . "</font>   " . $lang['Crystal'] . ":<font color=\"#ef51ef\">" . $debris['crystal'] . "</font><br /></center>";

            // Le vol rentre : sa composition et sa marque de retour (le rapport de destruction ne
            // transporte aucun pillage, le chargement n'est donc pas réécrit).
            $missions->setFleetUnits((int) $FleetRow['fleet_id'], $FleetArray, (int) $FleetAmount);
            $missions->markFleetReturning((int) $FleetRow['fleet_id']);

            SendSimpleMessage($CurrentUserID, '', $FleetRow['fleet_start_time'], 3, $lang['sys_mess_tower'], $lang['sys_mess_destruc_report'], $raport);

            // Colorisation du résumé de rapport pour le defenseur

            $raport2  = "<a href # OnClick=\"f( '/game/rw?raport=" . $rid . "', '');\" >";

            $raport2 .= "<center>";

            if ($FleetResult == "a") {

                $raport2 .= "<font color=\"red\">";
            } elseif ($FleetResult == "r") {

                $raport2 .= "<font color=\"orange\">";
            } elseif ($FleetResult == "w") {

                $raport2 .= "<font color=\"green\">";
            }

            $raport2 .= $lang['sys_mess_destruc_report'] . " [" . $FleetRow['fleet_end_galaxy'] . ":" . $FleetRow['fleet_end_system'] . ":" . $FleetRow['fleet_end_planet'] . "] </font></a><br /><br />";

            SendSimpleMessage($TargetUserID, '', $FleetRow['fleet_start_time'], 3, $lang['sys_mess_tower'], $lang['sys_mess_destruc_report'], $raport2);
        }

        // Retour de flotte (s'il en reste)
        if ($FleetRow['fleet_end_time'] <= time()) {
            // Ce que le vol ramène : sa composition en mémoire quand le combat vient d'avoir lieu,
            // sinon celle qu'il porte en base (deuxième passage, au retour).
            $ReturnShips = array();

            if (!is_null($CurrentSet)) {
                foreach ($CurrentSet as $Ship => $Count) {
                    $ReturnShips[$resource[$Ship]] = (int) $Count['count'];
                }
            } else {
                foreach (\App\Services\FlyingFleetService::parseUnits($FleetRow['fleet_array']) as $Ship => $Count) {
                    if (!isset($resource[$Ship])) {
                        continue;
                    }

                    $ReturnShips[$resource[$Ship]] = (int) $Count;
                }
            }

            $missions->deleteFleet((int) $FleetRow['fleet_id']);

            if (!($FleetResult == "w")) {
                $planets->addFleetCargoAtPosition(
                    (int) $FleetRow['fleet_start_galaxy'],
                    (int) $FleetRow['fleet_start_system'],
                    (int) $FleetRow['fleet_start_planet'],
                    (int) $FleetRow['fleet_start_type'],
                    $ReturnShips,
                    array(
                        'metal' => $FleetRow['fleet_resource_metal'],
                        'crystal' => $FleetRow['fleet_resource_crystal'],
                        'deuterium' => $FleetRow['fleet_resource_deuterium'],
                    )
                );
            }
        }
    }
}

//
