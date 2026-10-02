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

// Journal des actions des joueurs (panneau d'administration)
$lang['act_title']              = "Journal des actions";
$lang['act_count']              = "action(s) sur la p&eacute;riode";
$lang['act_days_label_7']       = "7 jours";
$lang['act_days_label_30']      = "30 jours";
$lang['act_days_label_90']      = "90 jours";
$lang['act_kind_all']           = "Toutes";
$lang['act_kind_page']          = "Pages";
$lang['act_kind_api']           = "Appels API";
$lang['act_kind_unknown']       = "Adresses inconnues";
$lang['act_guest']              = "&mdash; visiteur &mdash;";
$lang['act_pseudo_changed']     = "Changement de pseudo";
$lang['act_payload_title']      = "Contenu de la demande";
$lang['act_filter_player']      = "Compte";
$lang['act_filter_player_hint'] = "Nom du joueur (vide = tous)";
$lang['act_filter_kind']        = "Nature";
$lang['act_filter_apply']       = "Filtrer";
$lang['act_filter_clear']       = "Tout voir";
$lang['act_filtered']           = "Filtr&eacute;";
$lang['act_recent_title']       = "Derni&egrave;res actions";
$lang['act_totals_title']       = "Actions les plus fr&eacute;quentes";
$lang['act_col_time']           = "Date";
$lang['act_col_player']         = "Compte";
$lang['act_col_action']         = "Action";
$lang['act_col_method']         = "M&eacute;thode";
$lang['act_col_ip']             = "IP";
$lang['act_col_kind']           = "Nature";
$lang['act_col_nb']             = "Nombre";
$lang['act_col_last']           = "Derni&egrave;re fois";
$lang['act_no_action']          = "Aucune action enregistr&eacute;e sur la p&eacute;riode.";

// Libell&eacute;s des actions connues (ActionService::LABELS)
$lang['act_game_overview']            = "Vue g&eacute;n&eacute;rale";
$lang['act_game_buildings']           = "B&acirc;timents, recherche et hangar";
$lang['act_game_fleet']               = "Envoi de flotte";
$lang['act_game_galaxy']              = "Galaxie";
$lang['act_game_marchand']            = "Marchand et march&eacute;";
$lang['act_game_chat']                = "Tchat";
$lang['act_game_messages']            = "Messages";
$lang['act_game_options']             = "Options";
$lang['act_game_officer']             = "Officiers";
$lang['act_game_notes']               = "Notes";
$lang['act_game_alliance']            = "Alliance";
$lang['act_game_stats']               = "Statistiques";
$lang['act_game_records']             = "Records";
$lang['act_api_buildings_add']        = "Construction lanc&eacute;e";
$lang['act_api_buildings_destroy']    = "B&acirc;timent d&eacute;truit";
$lang['act_api_buildings_cancel']     = "Construction annul&eacute;e";
$lang['act_api_queues']               = "File r&eacute;ordonn&eacute;e";
$lang['act_api_research_start']       = "Recherche lanc&eacute;e";
$lang['act_api_research_cancel']      = "Recherche annul&eacute;e";
$lang['act_api_shipyard_add']         = "Vaisseaux ou d&eacute;fenses command&eacute;s";
$lang['act_api_fleet_send']           = "Flotte envoy&eacute;e";
$lang['act_api_fleet_estimate']       = "Estimation de flotte";
$lang['act_api_market_buy']           = "Achat au march&eacute;";
$lang['act_api_market_sell']          = "Vente au march&eacute;";
$lang['act_api_marchand']             = "&Eacute;change avec le marchand";
$lang['act_api_messages_send']        = "Message envoy&eacute;";
$lang['act_api_notes_save']           = "Note enregistr&eacute;e";
$lang['act_api_notes_delete']         = "Note supprim&eacute;e";
$lang['act_api_rename']               = "Plan&egrave;te renomm&eacute;e";
$lang['act_api_options_save']         = "Options enregistr&eacute;es";
$lang['act_api_vacation']             = "Mode vacances";
$lang['act_api_chat_send']            = "Message de tchat";
$lang['act_api_alliance_make']        = "Alliance cr&eacute;&eacute;e";
$lang['act_api_alliance_apply']       = "Candidature &agrave; une alliance";
$lang['act_api_alliance_leave']       = "Alliance quitt&eacute;e";
$lang['act_api_alliance_circular']    = "Message circulaire";
$lang['act_api_alliance_rename']      = "Alliance renomm&eacute;e";
$lang['act_api_alliance_request']     = "Demande d\'alliance";
$lang['act_api_percent']              = "R&eacute;partition de production";
