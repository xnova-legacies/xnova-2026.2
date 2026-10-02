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

// Gestion d'un joueur (panneau d'administration)
$lang['pal_title']           = "Gestion d'un joueur";
$lang['pal_pick']            = "Joueur";
$lang['pal_pick_hint']       = "Nom du joueur";
$lang['pal_pick_apply']      = "Ouvrir la fiche";

// Chiffres de l'univers, affich&eacute;s avant le choix du compte
$lang['pal_stat_accounts']   = "Comptes";
$lang['pal_stat_online']     = "En ligne";
$lang['pal_stat_active']     = "Comptes actifs";
$lang['pal_stat_inactive']   = "Comptes inactifs";
$lang['pal_stat_banned']     = "Comptes bannis";
$lang['pal_stat_removed']    = "Comptes supprim&eacute;s";
$lang['pal_stat_bots']       = "Robots";
$lang['pal_stat_planets']    = "Plan&egrave;tes";
$lang['pal_stat_moons']      = "Lunes";
$lang['pal_stat_fleets']     = "Flottes en vol";
$lang['pal_stat_fields']     = "Champs";
$lang['pal_stat_metal']      = "M&eacute;tal";
$lang['pal_stat_crystal']    = "Cristal";
$lang['pal_stat_deuterium']  = "Deut&eacute;rium";

$lang['pal_tab_state']       = "&Eacute;tat du compte";
$lang['pal_tab_planets']     = "Plan&egrave;tes et lunes";
$lang['pal_tab_fleets']      = "Flottes";
$lang['pal_tab_extra']       = "Gestion suppl&eacute;mentaire";

$lang['pal_id']              = "Identifiant";
$lang['pal_name']            = "Pseudo";
$lang['pal_email']           = "Adresse e-mail";
$lang['pal_level']           = "Niveau d'acc&egrave;s";
$lang['pal_registered']      = "Inscrit le";
$lang['pal_last_seen']       = "Derni&egrave;re connexion";
$lang['pal_ip']              = "Derni&egrave;re adresse IP";
$lang['pal_points']          = "Points";
$lang['pal_alliance']        = "Alliance";
$lang['pal_vacation']        = "Mode vacances";
$lang['pal_yes']             = "Oui";
$lang['pal_no']              = "Non";

$lang['pal_state_active']    = "Compte actif";
$lang['pal_state_banned']    = "Compte banni";
$lang['pal_state_deleted']   = "Compte supprim&eacute;";
$lang['pal_ban_until_label'] = "jusqu'au";

$lang['pal_ban_title']       = "Bannir";
$lang['pal_ban_days']        = "Jours";
$lang['pal_ban_hours']       = "Heures";
$lang['pal_ban_minutes']     = "Minutes";
$lang['pal_ban_seconds']     = "Secondes";
$lang['pal_ban_reason']      = "Motif";
$lang['pal_ban_apply']       = "Bannir le compte";
$lang['pal_ban_hint']        = "Le joueur re&ccedil;oit un message avec le motif et la date de fin ; sans dur&eacute;e, rien n'est fait.";
$lang['pal_unban_title']     = "D&eacute;bannir";
$lang['pal_unban_hint']      = "L&egrave;ve la sanction et efface la ligne du pilori.";
$lang['pal_unban_apply']     = "D&eacute;bannir le compte";
$lang['pal_delete_title']    = "Supprimer le compte";
$lang['pal_delete_hint']     = "Suppression logique : le compte ne peut plus se connecter, mais ses plan&egrave;tes, son alliance et ses messages restent en base. La fiche permet de le r&eacute;tablir.";
$lang['pal_delete_apply']    = "Supprimer (r&eacute;versible)";
$lang['pal_restore_title']   = "R&eacute;tablir le compte";
$lang['pal_restore_hint']    = "Le compte retrouve l'acc&egrave;s au jeu : ses donn&eacute;es n'ont jamais &eacute;t&eacute; effac&eacute;es.";
$lang['pal_restore_apply']   = "R&eacute;tablir l'acc&egrave;s";

$lang['pal_msg_ban_subject'] = "Bannissement";
$lang['pal_msg_ban_text']    = "Vous avez &eacute;t&eacute; banni pour la raison suivante : %s. La sanction prend fin le %s.";
$lang['pal_msg_banned']      = "Le joueur %s est banni jusqu'au %s.";
$lang['pal_msg_unbanned']    = "Le joueur %s est d&eacute;banni.";
$lang['pal_msg_deleted']     = "Le joueur %s ne peut plus se connecter (compte supprim&eacute;).";
$lang['pal_msg_restored']    = "Le joueur %s a retrouv&eacute; l'acc&egrave;s au jeu.";
$lang['pal_msg_notfound']    = "Ce joueur n'existe pas.";
$lang['pal_msg_self']        = "Vous ne pouvez pas bannir ni supprimer votre propre compte, ni changer votre propre r&ocirc;le.";
$lang['pal_msg_protected']   = "Le compte d'installation (identifiant 1) est super administrateur : il ne se bannit pas, ne se supprime pas et son r&ocirc;le ne se change pas.";
$lang['pal_msg_nothing']     = "Rien n'a &eacute;t&eacute; modifi&eacute; : v&eacute;rifiez les valeurs saisies.";
$lang['pal_msg_role']        = "Le r&ocirc;le du joueur %s est maintenant : %s.";

$lang['pal_role_title']      = "R&ocirc;le et permissions";
$lang['pal_role_label']      = "R&ocirc;le du compte";
$lang['pal_role_none']       = "Aucun r&ocirc;le (aucun acc&egrave;s au panneau)";
$lang['pal_role_apply']      = "Appliquer";
$lang['pal_role_hint']       = "Le r&ocirc;le d&eacute;cide des pages du panneau que ce compte peut ouvrir. C'est celui de son porteur, pas son niveau de jeu.";
$lang['pal_role_manage']     = "G&eacute;rer les r&ocirc;les";
$lang['pal_role_super_note'] = "Le compte d'installation (identifiant 1) est <strong>super administrateur</strong> : tous les droits, non supprimable, non bannissable, et son r&ocirc;le ne se change pas.";

$lang['pal_planet']          = "Plan&egrave;te";
$lang['pal_moon']            = "Lune";
$lang['pal_planet_name']     = "Nom";
$lang['pal_coords']          = "Position";
$lang['pal_fields']          = "Champs";
$lang['pal_metal']           = "M&eacute;tal";
$lang['pal_crystal']         = "Cristal";
$lang['pal_deuterium']       = "Deut&eacute;rium";
$lang['pal_no_planet']       = "Ce joueur n'a ni plan&egrave;te ni lune.";
$lang['pal_target']          = "Plan&egrave;te ou lune";
$lang['pal_apply']           = "Appliquer";
$lang['pal_display']         = "Afficher";

$lang['pal_res_title']       = "Ressources et champs";
$lang['pal_fields_max']      = "Champs maximum";
$lang['pal_fields_max_hint'] = "Nombre d'emplacements de construction de la plan&egrave;te.";
$lang['pal_diameter']        = "Diam&egrave;tre (km)";
$lang['pal_diameter_hint']   = "Taille de la plan&egrave;te, en kilom&egrave;tres.";
$lang['pal_rename']          = "Nouveau nom de la plan&egrave;te";
$lang['pal_rename_hint']     = "Laisser vide pour garder le nom actuel.";
$lang['pal_unchanged']       = "inchang&eacute;";
$lang['pal_empty_resources'] = "Remettre les ressources &agrave; z&eacute;ro";
$lang['pal_empty_resources_hint'] = "Les trois ressources de la position passent &agrave; z&eacute;ro : les montants saisis &agrave; c&ocirc;t&eacute; sont alors ignor&eacute;s.";
$lang['pal_clear_debris']    = "Vider le champ de d&eacute;bris";
$lang['pal_debris_hint']     = "Retire le m&eacute;tal et le cristal laiss&eacute;s par les combats sur la position choisie ci-dessus.";
$lang['pal_res_hint']        = "Les ressources se saisissent en variation : un montant n&eacute;gatif retire (jamais plus qu'il n'y a), un montant positif ajoute, vide ou z&eacute;ro ne change rien.";

$lang['pal_element_title']   = "&Eacute;l&eacute;ments";

$lang['pal_el_tab_build']    = "B&acirc;timents";
$lang['pal_el_tab_tech']     = "Recherches";
$lang['pal_el_tab_fleet']    = "Vaisseaux";
$lang['pal_el_tab_defense']  = "D&eacute;fenses";
$lang['pal_el_name']         = "Nom";
$lang['pal_el_current']      = "Valeur actuelle";
$lang['pal_el_delta']        = "Variation";
$lang['pal_el_locked']       = "Recherche en cours";
$lang['pal_no_element']      = "Aucun &eacute;l&eacute;ment &agrave; afficher.";
$lang['pal_msg_elements']    = "%s valeur(s) modifi&eacute;e(s).";
$lang['pal_element_hint']    = "Saisir une variation : n&eacute;gative pour retirer, positive pour ajouter, vide ou z&eacute;ro pour ne rien changer. Le niveau d'une recherche en cours ne se modifie pas ici : la file porte le niveau vis&eacute;.";

$lang['pal_moon_title']      = "Lune";
$lang['pal_moon_name']       = "Nom de la lune";
$lang['pal_moon_add']        = "Ajouter la lune";
$lang['pal_moon_remove']     = "Supprimer la lune";
$lang['pal_moon_hint']       = "Une seule lune par position : la cr&eacute;ation &eacute;choue si la plan&egrave;te en a d&eacute;j&agrave; une. La suppression est <b>r&eacute;versible</b> : la lune garde sa ligne (elle ne se voit plus, n'est plus ciblable) et se r&eacute;tablit ci-dessous, sauf si une nouvelle lune occupe la position.";
$lang['pal_moon_default']    = "Lune";
$lang['pal_moon_deleted_title'] = "Lunes d&eacute;truites";
$lang['pal_planet_abandoned_title'] = "Colonies abandonn&eacute;es";
$lang['pal_planet_abandoned_empty'] = "Aucune colonie abandonn&eacute;e pour ce compte.";
$lang['pal_planet_restore']  = "R&eacute;tablir la colonie";
$lang['pal_msg_planet_restored'] = "La colonie %s a &eacute;t&eacute; r&eacute;tablie.";
$lang['pal_msg_planet_restore_no'] = "R&eacute;tablissement impossible : une planète occupe d&eacute;j&agrave; cette position.";
$lang['pal_moon_deleted_empty'] = "Aucune lune d&eacute;truite pour ce compte.";
$lang['pal_moon_restore']    = "R&eacute;tablir la lune";
$lang['pal_moon_th_id']      = "Lune";
$lang['pal_moon_th_name']    = "Nom";
$lang['pal_moon_th_position'] = "Position";
$lang['pal_moon_th_action']  = "Action";

$lang['pal_move_title']      = "D&eacute;placer la plan&egrave;te";
$lang['pal_move_galaxy']     = "Galaxie";
$lang['pal_move_system']     = "Syst&egrave;me";
$lang['pal_move_position']   = "Position";
$lang['pal_move_apply']      = "D&eacute;placer";
$lang['pal_move_hint']       = "La lune, le champ de d&eacute;bris et les flottes en vol suivent la plan&egrave;te. Le d&eacute;placement est refus&eacute; si une attaque vise la position, si une flotte y est pos&eacute;e, ou si la position vis&eacute;e est d&eacute;j&agrave; occup&eacute;e.";

// Motifs de refus (la cl&eacute; vient de PlayerAdminService::moveRefusal)
$lang['pal_move_same']       = "la plan&egrave;te est d&eacute;j&agrave; &agrave; cette position.";
$lang['pal_move_bounds']     = "cette position n'existe pas dans l'univers.";
$lang['pal_move_occupied']   = "la position vis&eacute;e est d&eacute;j&agrave; occup&eacute;e (une autre plan&egrave;te, une lune ou un champ de d&eacute;bris y est d&eacute;j&agrave;).";
$lang['pal_move_attack']     = "une attaque vise cette position : une flotte hostile est en route, ou des missiles sont en vol.";
$lang['pal_move_parked']     = "une flotte est pos&eacute;e sur cette position (stationnement ou transfert arriv&eacute;) : rappelez-la ou d&eacute;placez-la d'abord.";
$lang['pal_msg_move_refused'] = "D&eacute;placement refus&eacute; : %s";
$lang['pal_msg_moved']       = "Plan&egrave;te %s d&eacute;plac&eacute;e en %s.";

$lang['pal_msg_planet']      = "Plan&egrave;te %s mise &agrave; jour.";
$lang['pal_msg_element']     = "Plan&egrave;te %s : l'&eacute;l&eacute;ment vaut maintenant %s.";
$lang['pal_msg_moon_added']  = "Une lune a &eacute;t&eacute; pos&eacute;e autour de %s.";
$lang['pal_msg_moon_removed'] = "La lune de %s a &eacute;t&eacute; d&eacute;truite : elle peut &ecirc;tre r&eacute;tablie.";
$lang['pal_msg_moon_restored'] = "La lune %s a &eacute;t&eacute; r&eacute;tablie.";
$lang['pal_msg_moon_restore_no'] = "R&eacute;tablissement impossible : une lune occupe d&eacute;j&agrave; cette position.";
$lang['pal_msg_moon_restore_planet'] = "R&eacute;tablissement impossible : la colonie de cette lune a disparu. R&eacute;tablissez d'abord la planète.";

$lang['pal_fleet']           = "Flotte";
$lang['pal_fleet_from']      = "D&eacute;part";
$lang['pal_fleet_to']        = "Arriv&eacute;e";
$lang['pal_fleet_units']     = "Composition";
$lang['pal_fleet_recall']    = "Rappeler la flotte";
$lang['pal_fleet_remove_units'] = "Retirer les vaisseaux saisis";
$lang['pal_fleet_delete']    = "Supprimer la flotte";
$lang['pal_fleet_empty']     = "Ce joueur n'a aucune flotte en vol.";
$lang['pal_fleet_removed_title'] = "Vols supprim&eacute;s";
$lang['pal_fleet_restore']   = "R&eacute;tablir le vol";
$lang['pal_fleet_th_id']     = "Flotte";
$lang['pal_fleet_th_mission'] = "Mission";
$lang['pal_fleet_th_route']  = "Trajet";
$lang['pal_fleet_th_end']    = "Arriv&eacute;e pr&eacute;vue";
$lang['pal_fleet_th_action'] = "Action";
$lang['pal_msg_recall']      = "La flotte #%s rentre.";
$lang['pal_msg_units']       = "La flotte #%s a perdu les vaisseaux retir&eacute;s.";
$lang['pal_msg_fleet_deleted'] = "La flotte #%s a &eacute;t&eacute; supprim&eacute;e : elle n'arrivera pas et peut &ecirc;tre r&eacute;tablie.";
$lang['pal_msg_fleet_restored'] = "La flotte #%s repart en mission.";
