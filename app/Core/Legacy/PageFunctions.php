<?php

/**
 * Tis file is part of XNova:Legacies
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @see http://www.xnova-ng.org/
 *
 * Copyright (c) 2009-Present, XNova Support Team <http://www.xnova-ng.org>
 * All rights reserved.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *                                --> NOTICE <--
 *  This file is part of the core development branch, changing its contents will
 * make you unable to use the automatic updates manager. Please refer to the
 * documentation for further information about customizing XNova.
 *
 */

function GetTargetDistance($OrigGalaxy, $DestGalaxy, $OrigSystem, $DestSystem, $OrigPlanet, $DestPlanet)
{
    // Implémentation unique : App\Core\FleetMath.
    return \App\Core\FleetMath::targetDistance(
        (int) $OrigGalaxy,
        (int) $DestGalaxy,
        (int) $OrigSystem,
        (int) $DestSystem,
        (int) $OrigPlanet,
        (int) $DestPlanet
    );
}

// Calcul de la durée de vol d'une flotte par rapport a sa vitesse max
function GetMissionDuration($GameSpeed, $MaxFleetSpeed, $Distance, $SpeedFactor)
{
    // Implémentation unique : App\Core\FleetMath (facteur d'accélération inclus).
    return \App\Core\FleetMath::missionDuration($GameSpeed, $MaxFleetSpeed, $Distance, $SpeedFactor);
}

// Retourne la valeur ajustée de vitesse des flottes
function GetGameSpeedFactor()
{
    return \App\Core\FleetMath::gameSpeedFactor();
}

/**
 *  Calcul de la vitesse de la flotte par rapport aux technos du joueur
 *  Avec prise en compte
 */
function GetFleetMaxSpeed($FleetArray, $Fleet, $Player)
{
    // Implémentation unique : App\Core\FleetMath (cast array + garde-fous PHP 8).
    return \App\Core\FleetMath::fleetMaxSpeed($FleetArray, $Fleet, (array) $Player);
}

// ----------------------------------------------------------------------------------------------------------------
// Calcul de la consommation de base d'un vaisseau au regard des technologies
function GetShipConsumption($Ship, $Player)
{
    // Implémentation unique : App\Core\FleetMath.
    return \App\Core\FleetMath::shipConsumption($Ship, (array) $Player);
}

// ----------------------------------------------------------------------------------------------------------------
// Calcul de la consommation de la flotte pour cette mission
function GetFleetConsumption($FleetArray, $SpeedFactor, $MissionDuration, $MissionDistance, $FleetMaxSpeed, $Player)
{
    // Implémentation unique : App\Core\FleetMath.
    return \App\Core\FleetMath::fleetConsumption(
        $FleetArray,
        $SpeedFactor,
        $MissionDuration,
        $MissionDistance,
        $FleetMaxSpeed,
        (array) $Player
    );
}

// ----------------------------------------------------------------------------------------------------------------
//
// Mise en forme de chaines pour affichage
//

// Mise en forme de la durée sous forme xj xxh xxm xxs
function pretty_time($seconds)
{
    // Implémentation unique : App\Core\Format (troncature PHP 8.3 comprise).
    return \App\Core\Format::prettyTime($seconds);
}

// Mise en forme de la durée sous forme xxxmin
function pretty_time_hour($seconds)
{
    return \App\Core\Format::prettyTimeHour($seconds);
}

// Mise en forme du temps de construction (avec la phrase de description)
function ShowBuildTime($time)
{
    global $lang;

    // Balisage legacy conservé : seul le formatage de la durée devient moderne.
    return '<span class="text-body-secondary">' . $lang['ConstructionTime'] . ": " . \App\Core\Format::prettyTime($time) . '</span>';
}

// ----------------------------------------------------------------------------------------------------------------
//
function add_points($resources, $userid)
{
    return false;
}

function remove_points($resources, $userid)
{
    return false;
}

function get_userdata()
{
    return '';
}

// ----------------------------------------------------------------------------------------------------------------
//
// Fonction de lecture / ecriture / exploitation de templates
//
function ReadFromFile($filename)
{
    $content = @file_get_contents($filename);
    return $content;
}

function saveToFile($filename, $content)
{
    $content = file_put_contents($filename, $content);
}

function parsetemplate($template, $array)
{
    return preg_replace_callback('#\{([a-z0-9\-_]*?)\}#Ssi', function ($matches) use ($array) {
        return isset($array[$matches[1]]) ? $array[$matches[1]] : '';
    }, $template);
}

function getTemplate($templateName)
{
    // Implémentation unique : App\Core\TemplateEngine (gabarits du jeu **et** des
    // modules, `modules/<nom>/view/`).
    return \App\Core\TemplateEngine::load((string) $templateName);
}

/**
 * Gestion de la localisation des chaînes
 *
 * @param string $filename
 * @param string $extension
 * @return void
 */
function includeLang($filename, $extension = '.mo')
{
    // Implémentation unique : App\Core\Language (libellés du jeu **et** des modules,
    // `modules/<nom>/language/`).
    \App\Core\Language::include((string) $filename, (string) $extension);

    return;
}

// ----------------------------------------------------------------------------------------------------------------
//
// Affiche une adresse de depart sous forme de lien
function GetStartAdressLink($FleetRow, $FleetType)
{
    $Link  = "<a href=\"/game/galaxy?mode=3&galaxy=" . $FleetRow['fleet_start_galaxy'] . "&system=" . $FleetRow['fleet_start_system'] . "\" " . $FleetType . " >";
    $Link .= "[" . $FleetRow['fleet_start_galaxy'] . ":" . $FleetRow['fleet_start_system'] . ":" . $FleetRow['fleet_start_planet'] . "]</a>";
    return $Link;
}

// Affiche une adresse de cible sous forme de lien
function GetTargetAdressLink($FleetRow, $FleetType)
{
    $Link  = "<a href=\"/game/galaxy?mode=3&galaxy=" . $FleetRow['fleet_end_galaxy'] . "&system=" . $FleetRow['fleet_end_system'] . "\" " . $FleetType . " >";
    $Link .= "[" . $FleetRow['fleet_end_galaxy'] . ":" . $FleetRow['fleet_end_system'] . ":" . $FleetRow['fleet_end_planet'] . "]</a>";
    return $Link;
}

// Affiche une adresse de planete sous forme de lien
function BuildPlanetAdressLink($CurrentPlanet)
{
    $Link  = "<a href=\"/game/galaxy?mode=3&galaxy=" . $CurrentPlanet['galaxy'] . "&system=" . $CurrentPlanet['system'] . "\">";
    $Link .= "[" . $CurrentPlanet['galaxy'] . ":" . $CurrentPlanet['system'] . ":" . $CurrentPlanet['planet'] . "]</a>";
    return $Link;
}

// Création d'un lien pour le joueur hostile
function BuildHostileFleetPlayerLink($FleetRow)
{
    global $lang;

    // La lecture du pseudo appartient au depot des comptes (une seule implementation).
    $PlayerName = (new \App\Repositories\UserRepository())->username((int) $FleetRow['fleet_owner']);
    $Link  = $PlayerName . " ";
    $Link .= "<a href=\"/game/profil/messages?mode=write&id=" . $FleetRow['fleet_owner'] . "\" title=\"" . $lang['ov_message'] . "\">";
    $Link .= "<i class=\"bi bi-envelope\" aria-hidden=\"true\"></i></a>";
    return $Link;
}

function GetNextJumpWaitTime($CurMoon)
{
    global $resource;

    $JumpGateLevel  = $CurMoon[$resource[43]];
    $LastJumpTime   = $CurMoon['last_jump_time'];
    if ($JumpGateLevel > 0) {
        $WaitBetweenJmp = (60 * 60) * (1 / $JumpGateLevel);
        $NextJumpTime   = $LastJumpTime + $WaitBetweenJmp;
        if ($NextJumpTime >= time()) {
            $RestWait   = $NextJumpTime - time();
            $RestString = " " . \App\Core\Format::prettyTime($RestWait);
        } else {
            $RestWait   = 0;
            $RestString = "";
        }
    } else {
        $RestWait   = 0;
        $RestString = "";
    }
    $RetValue['string'] = $RestString;
    $RetValue['value']  = $RestWait;

    return $RetValue;
}

/**
 * Echappe un libelle destine a une infobulle overlib : le contenu est place
 * dans une chaine JS entre apostrophes, elle-meme dans un attribut HTML entre
 * guillemets doubles.
 */
function EscapeTooltipText($Text)
{
    return str_replace(array("'", '"'), array("\\'", '&quot;'), (string) $Text);
}

/**
 * Création du lien avec popup pour la flotte
 *
 * Le contenu est placé dans une chaine JS entre apostrophes (overlib('...')) :
 * les attributs HTML y sont volontairement non quotes et les apostrophes sont échappées.
 */
function CreateFleetPopupedFleetLink($FleetRow, $Texte, $FleetType)
{
    global $lang;

    $FleetRec     = explode(";", $FleetRow['fleet_array']);
    $FleetPopup   = "<a href='#' onmouseover=\"return overlib('";
    $FleetPopup  .= "<table width=200 class=xnova-tip>";
    foreach ($FleetRec as $Item => $Group) {
        if ($Group != '') {
            $Ship = explode(",", $Group);
            $FleetPopup .= "<tr><td class=xnova-tip-label width=50%>" . EscapeTooltipText($lang['tech'][$Ship[0]]) . "</td><td class=xnova-tip-value width=50%>" . pretty_number($Ship[1]) . "</td></tr>";
        }
    }
    $FleetPopup  .= "</table>";
    $FleetPopup  .= "');\" onmouseout=\"return nd();\" class=\"" . $FleetType . "\">" . $Texte . "</a>";

    return $FleetPopup;
}

// ----------------------------------------------------------------------------------------------------------------
//
// Céation du lien avec popup pour le type de mission avec ou non les ressources si disponibles
function CreateFleetPopupedMissionLink($FleetRow, $Texte, $FleetType)
{
    global $lang;

    $FleetTotalC  = $FleetRow['fleet_resource_metal'] + $FleetRow['fleet_resource_crystal'] + $FleetRow['fleet_resource_deuterium'];
    if ($FleetTotalC <> 0) {
        $FRessource   = "<table width=200 class=xnova-tip>";
        $FRessource  .= "<tr><td class=xnova-tip-label width=50%>" . EscapeTooltipText($lang['Metal']) . "</td><td class=xnova-tip-value width=50%>" . pretty_number($FleetRow['fleet_resource_metal']) . "</td></tr>";
        $FRessource  .= "<tr><td class=xnova-tip-label width=50%>" . EscapeTooltipText($lang['Crystal']) . "</td><td class=xnova-tip-value width=50%>" . pretty_number($FleetRow['fleet_resource_crystal']) . "</td></tr>";
        $FRessource  .= "<tr><td class=xnova-tip-label width=50%>" . EscapeTooltipText($lang['Deuterium']) . "</td><td class=xnova-tip-value width=50%>" . pretty_number($FleetRow['fleet_resource_deuterium']) . "</td></tr>";
        $FRessource  .= "</table>";
    } else {
        $FRessource   = "";
    }

    if ($FRessource <> "") {
        $MissionPopup  = "<a href='#' onmouseover=\"return overlib('" . $FRessource . "');";
        $MissionPopup .= "\" onmouseout=\"return nd();\" class=\"" . $FleetType . "\">" . $Texte . "</a>";
    } else {
        $MissionPopup  = $Texte . "";
    }

    return $MissionPopup;
}
