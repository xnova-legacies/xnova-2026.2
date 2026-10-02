<?php
/**
 * Libellés de la page d'administration des rôles (/back/roles).
 *
 * Un rôle porte une liste de permissions (`App\Core\Acl`) : cocher une permission
 * ouvre la page correspondante. Les libellés des permissions elles-mêmes viennent
 * du menu d'administration (`leftmenu.mo`), pas d'ici.
 *
 * Les clés `acl_*` sans accent (`acl_role_used`, `acl_label_length`…) sont aussi
 * les motifs de refus renvoyés par `App\Services\AclService` : le contrôleur les
 * affiche telles quelles, sans table de correspondance.
 */

$lang['acl_title'] = "R&ocirc;les et permissions";

$lang['acl_state_live']    = "R&ocirc;les";
$lang['acl_state_deleted'] = "Supprim&eacute;s";

$lang['acl_hdr_label']       = "R&ocirc;le";
$lang['acl_hdr_order']       = "Ordre";
$lang['acl_hdr_name']        = "Nom technique";
$lang['acl_hdr_description'] = "Description";
$lang['acl_hdr_users']       = "Comptes";
$lang['acl_hdr_permissions'] = "Permissions";
$lang['acl_hdr_action']      = "Action";

$lang['acl_no_role']      = "Aucun r&ocirc;le &agrave; afficher.";
$lang['acl_all_short']    = "Tous";
$lang['acl_default_badge'] = "par d&eacute;faut";
$lang['acl_used_badge']    = "utilis&eacute;";
$lang['acl_edit']          = "Modifier";
$lang['acl_delete']        = "Supprimer";
$lang['acl_restore']       = "R&eacute;tablir";
$lang['acl_move_up']       = "Monter d'une place";
$lang['acl_move_down']     = "Descendre d'une place";
$lang['acl_above_badge']   = "non modifiable";

$lang['acl_new_title']  = "Nouveau r&ocirc;le";
$lang['acl_edit_title'] = "R&eacute;glage du r&ocirc;le";
$lang['acl_label']       = "Libell&eacute; (affich&eacute; aux comptes)";
$lang['acl_description'] = "Description";
$lang['acl_permissions'] = "Permissions";
$lang['acl_save']        = "Enregistrer";
$lang['acl_name_hint']   = "Nom technique :";
$lang['acl_all_note']    = "Ce r&ocirc;le porte <strong>tous les droits</strong>, y compris les pages qui n\'existent pas encore : ses permissions ne se d&eacute;cochent pas ici.";

$lang['acl_group_admin']  = "Panneau d'administration";
$lang['acl_group_module'] = "Modules";

$lang['acl_super_admin_note'] = "Le compte d'installation (identifiant 1) est <strong>super administrateur</strong> : tous les droits, non supprimable, non bannissable, et son r&ocirc;le ne se change pas.";
$lang['acl_order_note']       = "Les r&ocirc;les sont <strong>hi&eacute;rarchis&eacute;s</strong> : un r&ocirc;le ne g&egrave;re que ceux plac&eacute;s sous lui. Le sien, et ceux au-dessus, ne se modifient pas depuis son compte &mdash; un r&ocirc;le qui porte tous les droits g&egrave;re tout le monde.";
$lang['acl_locked_note']      = "Vous ne pouvez pas modifier ce r&ocirc;le : il est plac&eacute; plus haut que le v&ocirc;tre, ou c'est le v&ocirc;tre.";

$lang['acl_saved']         = "R&ocirc;le enregistr&eacute;.";
$lang['acl_moved']         = "Ordre des r&ocirc;les mis &agrave; jour.";
$lang['acl_deleted']       = "R&ocirc;le supprim&eacute; : il ne donne plus aucun droit et se r&eacute;tablit depuis la liste des r&ocirc;les supprim&eacute;s.";
$lang['acl_restored']      = "R&ocirc;le r&eacute;tabli.";
$lang['acl_bad_token']     = "Jeton de s&eacute;curit&eacute; manquant : rien n'a &eacute;t&eacute; enregistr&eacute;.";

// Motifs de refus d'App\Services\AclService.
$lang['acl_label_length']      = "Le libell&eacute; doit faire entre 3 et 100 caract&egrave;res.";
$lang['acl_name_taken']        = "Un r&ocirc;le porte d&eacute;j&agrave; ce nom.";
$lang['acl_role_notfound']     = "Ce r&ocirc;le n'existe plus.";
$lang['acl_role_default']      = "Un r&ocirc;le par d&eacute;faut ne se supprime pas : modifiez ses permissions.";
$lang['acl_role_used']         = "Des comptes portent encore ce r&ocirc;le : donnez-leur un autre r&ocirc;le avant de le supprimer.";
$lang['acl_role_above']        = "Ce r&ocirc;le est plac&eacute; plus haut que le v&ocirc;tre (ou c'est le v&ocirc;tre) : il ne se modifie pas depuis votre compte.";
$lang['acl_role_edge']         = "Ce r&ocirc;le est d&eacute;j&agrave; en bout d'&eacute;chelle.";
$lang['acl_user_notfound']     = "Ce compte n'existe plus.";
$lang['acl_super_admin_role']  = "Le compte d'installation est super administrateur par d&eacute;finition : son r&ocirc;le ne se change pas.";
$lang['acl_unknown_action']    = "Action inconnue.";
