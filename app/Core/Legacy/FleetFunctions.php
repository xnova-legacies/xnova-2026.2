<?php

/**
 * Fonctions legacy : FlyingFleetHandler, MissionCaseAttack, MissionCaseStay, MissionCaseStayAlly, MissionCaseTransport, MissionCaseSpy, MissionCaseRecycling, MissionCaseDestruction, MissionCaseColonisation, MissionCaseExpedition, MissionCaseOrbit, MissionCaseMissile, SendSimpleMessage, StoreGoodsToPlanet, BuildFleetEventTable, AbandonColony
 */

// ===== FlyingFleetHandler =====
function FlyingFleetHandler(&$planet)
{
    global $resource;

    // Toute table lue pendant ce traitement doit figurer dans le verrou : MySQL
    // refuse l'accès à une table absente de la liste (« Table … was not locked with
    // LOCK TABLES »), même en lecture et même depuis le passage en InnoDB.
    //
    // `aks` pour la suppression d'un groupe d'attaque, et `modules` en **lecture** :
    // le moteur de combat est un point de surcharge, donc `MissionCaseAttack` demande
    // au registre des modules lequel répond (`ModuleService::resolve()`) — et cette
    // première lecture tombe ici, avant que `index.php` n'ait posé le garde des
    // modules. En lecture, et non en écriture : le registre n'est jamais modifié par
    // un combat, et un verrou d'écriture bloquerait la lecture des autres requêtes
    // (chaque page passe par ici).
    doquery("LOCK TABLE {{table}}lunas WRITE, {{table}}rw WRITE, {{table}}messages WRITE, {{table}}fleets WRITE, {{table}}planets WRITE, {{table}}galaxy WRITE ,{{table}}users WRITE, {{table}}aks WRITE, {{table}}modules READ", "");

    // Les vols a traiter pour cette position (departs et arrivees echues, hors vols
    // supprimes logiquement) viennent du depot des missions : la requete y est liee.
    $fleetquery = (new \App\Repositories\MissionRepository())->findFleetsToProcess($planet);

    foreach ($fleetquery as $CurrentFleet) {
        switch ($CurrentFleet["fleet_mission"]) {
            case 1:
                MissionCaseAttack($CurrentFleet);
                break;

            case 2:
                // Attaque groupée : la règle vit dans FleetMissionService::acs()
                // (fusion des flottes du groupe, un seul combat). Le switch legacy
                // doit s'y conformer : il tourne à chaque affichage de page.
                (new \App\Services\FleetMissionService())->acs($CurrentFleet);
                break;

            case 3:
                MissionCaseTransport($CurrentFleet);
                break;

            case 4:
                MissionCaseStay($CurrentFleet);
                break;

            case 5:
                MissionCaseStayAlly($CurrentFleet);
                break;

            case 6:
                MissionCaseSpy($CurrentFleet);
                break;

            case 7:
                MissionCaseColonisation($CurrentFleet);
                break;

            case 8:
                MissionCaseRecycling($CurrentFleet);
                break;

            case 9:
                MissionCaseDestruction($CurrentFleet);
                break;

            case 10:
                MissionCaseOrbit($CurrentFleet);
                break;

            case 11:
                // Attaque de missiles : une salve est une vraie mission de flotte
                // (table `fleets`), traitée comme les autres.
                MissionCaseMissile($CurrentFleet);
                break;

            case 15:
                MissionCaseExpedition($CurrentFleet);
                break;

            default:
                // Mission déclarée par un module (clé `missions` du manifeste) : le
                // switch legacy lui délègue, comme `FleetMissionService::handle()`.
                (new \App\Services\FleetMissionService())->handleModuleMission($CurrentFleet);
        }
    }

    doquery("UNLOCK TABLES", "");
}

//

// ===== MissionCaseStay =====
function MissionCaseStay($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->stay($FleetRow);
}

//

// ===== MissionCaseStayAlly =====
function MissionCaseStayAlly($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->stayAlly($FleetRow);
}

//

// ===== MissionCaseTransport =====
function MissionCaseTransport($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->transport($FleetRow);
}

//

// ===== MissionCaseSpy =====
function MissionCaseSpy($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->spy($FleetRow);
}

//

// ===== MissionCaseRecycling =====
function MissionCaseRecycling($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->recycle($FleetRow);
}

//

// ===== MissionCaseColonisation =====
function MissionCaseColonisation($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->colonise($FleetRow);
}

//

// ===== MissionCaseExpedition =====
function MissionCaseExpedition($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->expedition($FleetRow);
}

//

// ===== MissionCaseMissile =====
function MissionCaseMissile($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->missileStrike($FleetRow);
}

//

// ===== MissionCaseOrbit =====
function MissionCaseOrbit($FleetRow)
{
    $service = new \App\Services\FleetMissionService();

    $service->orbit($FleetRow);
}

//

// ===== SendSimpleMessage =====
function SendSimpleMessage($Owner, $Sender, $Time, $Type, $From, $Subject, $Message)
{
    $service = new \App\Services\MessageService();

    $service->send(
        (int) $Owner,
        (int) $Sender,
        (int) $Type,
        (string) $From,
        (string) $Subject,
        (string) $Message
    );
}

//

// ===== StoreGoodsToPlanet =====
function StoreGoodsToPlanet($FleetRow, $Start = false)
{
    // Les ressources du vol reviennent à la planète vise, par sa **position** (le moteur de
    // mission ne connait que les coordonnees) : meme ecriture que le retour d'une flotte.
    $galaxy = ($Start == true) ? $FleetRow['fleet_start_galaxy'] : $FleetRow['fleet_end_galaxy'];
    $system = ($Start == true) ? $FleetRow['fleet_start_system'] : $FleetRow['fleet_end_system'];
    $planet = ($Start == true) ? $FleetRow['fleet_start_planet'] : $FleetRow['fleet_end_planet'];
    $type   = ($Start == true) ? $FleetRow['fleet_start_type']   : $FleetRow['fleet_end_type'];

    (new \App\Repositories\PlanetRepository())->addFleetCargoAtPosition(
        (int) $galaxy,
        (int) $system,
        (int) $planet,
        (int) $type,
        array(),
        array(
            'metal'     => $FleetRow['fleet_resource_metal'],
            'crystal'   => $FleetRow['fleet_resource_crystal'],
            'deuterium' => $FleetRow['fleet_resource_deuterium'],
        )
    );
}

//

// ===== BuildFleetEventTable =====
function BuildFleetEventTable($FleetRow, $Status, $Owner, $Label, $Record)
{
    global $lang;

    $FleetStyle  = array(
        1 => 'attack',
        2 => 'federation',
        3 => 'transport',
        4 => 'deploy',
        5 => 'hold',
        6 => 'espionage',
        7 => 'colony',
        8 => 'harvest',
        9 => 'destroy',
        10 => 'missile',
        15 => 'transport',
    );
    $FleetStatus = array(0 => 'flight', 1 => 'holding', 2 => 'return');
    if ($Owner == true) {
        $FleetPrefix = 'own';
    } else {
        $FleetPrefix = '';
    }

    $RowsTPL        = gettemplate('overview_fleet_event');
    $MissionType    = $FleetRow['fleet_mission'];
    $FleetContent   = CreateFleetPopupedFleetLink($FleetRow, $lang['ov_fleet'], $FleetPrefix . $FleetStyle[$MissionType]);
    $FleetCapacity  = CreateFleetPopupedMissionLink($FleetRow, $lang['type_mission'][$MissionType], $FleetPrefix . $FleetStyle[$MissionType]);

    // Le nom de chaque extremite vient du depot des planetes (planète **existant** a ces
    // coordonnees) : la requete y est liee, il n'y a plus deux SELECT ici.
    $planets      = new \App\Repositories\PlanetRepository();
    $StartPlanet  = $planets->findByCoords((int) $FleetRow['fleet_start_galaxy'], (int) $FleetRow['fleet_start_system'], (int) $FleetRow['fleet_start_planet'], (int) $FleetRow['fleet_start_type']);
    $StartType    = $FleetRow['fleet_start_type'];
    $TargetPlanet = $planets->findByCoords((int) $FleetRow['fleet_end_galaxy'], (int) $FleetRow['fleet_end_system'], (int) $FleetRow['fleet_end_planet'], (int) $FleetRow['fleet_end_type']);
    $TargetType   = $FleetRow['fleet_end_type'];

    if ($Status != 2) {
        if ($StartType == 1) {
            $StartID  = $lang['ov_planet_to'];
        } elseif ($StartType == 3) {
            $StartID  = $lang['ov_moon_to'];
        }
        $StartID .= $StartPlanet['name'] . " ";
        $StartID .= GetStartAdressLink($FleetRow, $FleetPrefix . $FleetStyle[$MissionType]);

        if ($MissionType != 15) {
            if ($TargetType == 1) {
                $TargetID  = $lang['ov_planet_to_target'];
            } elseif ($TargetType == 2) {
                $TargetID  = $lang['ov_debris_to_target'];
            } elseif ($TargetType == 3) {
                $TargetID  = $lang['ov_moon_to_target'];
            }
        } else {
            $TargetID  = $lang['ov_explo_to_target'];
        }
        $TargetID .= $TargetPlanet['name'] . " ";
        $TargetID .= GetTargetAdressLink($FleetRow, $FleetPrefix . $FleetStyle[$MissionType]);
    } else {
        if ($StartType == 1) {
            $StartID  = $lang['ov_back_planet'];
        } elseif ($StartType == 3) {
            $StartID  = $lang['ov_back_moon'];
        }
        $StartID .= $StartPlanet['name'] . " ";
        $StartID .= GetStartAdressLink($FleetRow, $FleetPrefix . $FleetStyle[$MissionType]);

        if ($MissionType != 15) {
            if ($TargetType == 1) {
                $TargetID  = $lang['ov_planet_from'];
            } elseif ($TargetType == 2) {
                $TargetID  = $lang['ov_debris_from'];
            } elseif ($TargetType == 3) {
                $TargetID  = $lang['ov_moon_from'];
            }
        } else {
            $TargetID  = $lang['ov_explo_from'];
        }
        $TargetID .= $TargetPlanet['name'] . " ";
        $TargetID .= GetTargetAdressLink($FleetRow, $FleetPrefix . $FleetStyle[$MissionType]);
    }

    if ($Owner == true) {
        $EventString  = $lang['ov_une'];     // 'Une de tes '
        $EventString .= $FleetContent;
    } else {
        $EventString  = $lang['ov_une_hostile']; // 'Une '
        $EventString .= $FleetContent;
        $EventString .= $lang['ov_hostile'];    // ' hostile de '
        $EventString .= BuildHostileFleetPlayerLink($FleetRow);
    }

    if ($Status == 0) {
        $Time         = $FleetRow['fleet_start_time'];
        $Rest         = $Time - time();
        $EventString .= $lang['ov_vennant']; // ' venant '
        $EventString .= $StartID;
        $EventString .= $lang['ov_atteint']; // ' atteint '
        $EventString .= $TargetID;
        $EventString .= $lang['ov_mission']; // '. Elle avait pour mission: '
    } elseif ($Status == 1) {
        $Time         = $FleetRow['fleet_end_stay'];
        $Rest         = $Time - time();
        $EventString .= $lang['ov_vennant']; // ' venant '
        $EventString .= $StartID;
        $EventString .= $lang['ov_explo_stay']; // ' explore '
        $EventString .= $TargetID;
        $EventString .= $lang['ov_explo_mission']; // '. Elle a pour mission: '
    } elseif ($Status == 2) {
        $Time         = $FleetRow['fleet_end_time'];
        $Rest         = $Time - time();
        $EventString .= $lang['ov_rentrant']; // ' rentrant '
        $EventString .= $TargetID;
        $EventString .= $StartID;
        $EventString .= $lang['ov_mission']; // '. Elle avait pour mission: '
    }
    $EventString .= $FleetCapacity;

    $bloc['fleet_status'] = $FleetStatus[$Status];
    $bloc['fleet_prefix'] = $FleetPrefix;
    $bloc['fleet_style']  = $FleetStyle[$MissionType];
    $bloc['fleet_javai']  = InsertJavaScriptChronoApplet($Label, $Record, $Rest, true);
    $bloc['fleet_order']  = $Label . $Record;
    $bloc['fleet_time']   = date("H:i:s", $Time);
    $bloc['fleet_descr']  = $EventString;
    $bloc['fleet_javas']  = InsertJavaScriptChronoApplet($Label, $Record, $Rest, false);

    return parsetemplate($RowsTPL, $bloc);
}

//

// ===== AbandonColony =====
function AbandonColony($user, $planetrow)
{
    $destruyed = time() + 3600; //Temps avant la suppression dans la galaxie
    $DeleteMoon = false;
    if ($planetrow["planet_type"] == 1) {
        //Selectionne si il y a une lune sur la colonie a supprim�e
        // L'entree **existante** du registre, et celle du compte : le registre garde les
        // lunes detruites, c'est donc au proprietaire de les ecarter.
        $Registry = (new \App\Repositories\PlanetRepository())->findMoonRegistryAt(
            (int) $planetrow['galaxy'],
            (int) $planetrow['system'],
            (int) $planetrow['planet']
        );
        $IsMoon = is_array($Registry)
            && (int) ($Registry['destruyed'] ?? 0) === 0
            && (int) ($Registry['id_owner'] ?? 0) === (int) $user['id'];

        if ($IsMoon) {
            //Envoi la demande de suppression de la lune associ� a la colonie
            $DeleteMoon = true; // borrar luna
        }

        //Abandon **logique** : la ligne de la colonie reste en base (drapeau
        //`DELETED`, tout ce qu'elle porte est conserve), `destruyed` garde la date
        //et le lien de la galaxie est detache : la position redevient libre tout de
        //suite, sans fausse colonie sans proprietaire pendant une heure.
        $planetRepository = new \App\Repositories\PlanetRepository();
        $planetRepository->markPlanetAbandoned((int) $user['current_planet']);
        (new \App\Repositories\GalaxyRepository())->clearPlanetLink(
            (int) $planetrow['galaxy'],
            (int) $planetrow['system'],
            (int) $planetrow['planet']
        );

        //Si on veut supprimer une lune
    } elseif ($planetrow["planet_type"] == 3) {
        $DeleteMoon = true; //borrar luna
    }

    if ($DeleteMoon) {
        //Lune détruite **logiquement** : la ligne `planets` de type 3 reste en base
        //(drapeau `DELETED`), le registre garde sa marque, et le panneau peut
        //rétablir la lune. Même geste que la destruction par attaque (mission 9).
        $moonRepository = new \App\Repositories\PlanetRepository();
        $moonRepository->markMoonDeletedAt(
            (int) $planetrow['galaxy'],
            (int) $planetrow['system'],
            (int) $planetrow['planet'],
            (int) $user['id']
        );
        $moonRepository->markMoonRegistryDestroyed(
            (int) $planetrow['galaxy'],
            (int) $planetrow['system'],
            (int) $planetrow['planet']
        );

        //Un compte qui abandonnait une lune depuis cette lune doit revenir sur un
        //planète qui existe encore (sans effet si personne n'y était).
        (new \App\Repositories\UserRepository())->relocateFromPlanet((int) $planetrow['id']);

        // Le lien de la galaxie est detache par le depot (il remet aussi la marque `luna`).
        (new \App\Repositories\GalaxyRepository())->clearMoonLink(
            (int) $planetrow['galaxy'],
            (int) $planetrow['system'],
            (int) $planetrow['planet']
        );
    }
}

function CheckFleets($planetrow)
{
    // Un vol part de cette position, ou la vise-t-il ? La condition historique est dans le
    // depot des flottes (elle est asymetrique : le type n'est exige que pour une lune).
    return (new \App\Repositories\FleetRepository())->hasFleetAtPosition(
        (int) $planetrow['galaxy'],
        (int) $planetrow['system'],
        (int) $planetrow['planet'],
        (int) $planetrow['planet_type']
    );
}

//

// ===== MipCombatEngine (supprimé) =====
//
// La règle des missiles vit dans `App\Core\Combat\MissileStrike`, portée par la
// mission de flotte 11 (`MissileService`). L'ancien `MipAttack()` tirait ses
// défenses au hasard et écrivait lui-même les colonnes de la planète.

//
