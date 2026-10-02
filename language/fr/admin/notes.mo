<?php
/**
 * Libellés de la page d'administration des notes (/back/notes).
 *
 * La suppression d'une note est **logique** (drapeau `DELETED`) : la page montre
 * les notes existantes ou les supprimées, et permet de les rétablir.
 */

$lang['nlt_title']      = "Notes des joueurs";
$lang['nlt_count']      = "note(s)";

$lang['nlt_state_live']    = "Notes";
$lang['nlt_state_deleted'] = "Supprim&eacute;es";

$lang['nlt_hdr_id']       = "ID";
$lang['nlt_hdr_time']     = "Date";
$lang['nlt_hdr_owner']    = "Compte";
$lang['nlt_hdr_priority'] = "Priorit&eacute;";
$lang['nlt_hdr_title']    = "Titre";
$lang['nlt_hdr_size']     = "Taille";
$lang['nlt_hdr_action']   = "Action";

$lang['nlt_priority_0'] = "Basse";
$lang['nlt_priority_1'] = "Normale";
$lang['nlt_priority_2'] = "Haute";

$lang['nlt_select_all']     = "Tout s&eacute;lectionner";
$lang['nlt_del_one']        = "Effacer";
$lang['nlt_bt_delsel']      = "Effacer la selection";
$lang['nlt_restore_one']    = "R&eacute;tablir";
$lang['nlt_bt_restore']     = "R&eacute;tablir la selection";
$lang['nlt_confirm_del']    = "Marquer ces notes comme supprim&eacute;es ? Elles restent en base et se r&eacute;tablissent ici.";
$lang['nlt_mess_del']       = "Notes supprim&eacute;es :";
$lang['nlt_mess_restore']   = "Notes r&eacute;tablies :";
$lang['nlt_no_note']        = "Aucune note &agrave; afficher.";
