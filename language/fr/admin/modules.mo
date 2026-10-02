<?php
/**
 * Libellés de la page d'administration des modules (/back/modules).
 *
 * L'état (`mod_saved`, `mod_notfound`, `mod_bad_token`) sert aussi de motif de refus
 * renvoyé par `App\Services\ModuleService` : le contrôleur affiche la clé telle
 * quelle, sans table de correspondance. Les libellés des modules eux-mêmes viennent
 * des manifestes (`modules/<nom>/package.json`, clés `mod_*` de `modules.mo`).
 */

$lang['mod_title']            = "Modules";
$lang['mod_hdr_module']       = "Module";
$lang['mod_hdr_description']  = "Description";
$lang['mod_hdr_permission']   = "Permission";
$lang['mod_hdr_page']         = "Adresse";
$lang['mod_hdr_dependencies'] = "D&eacute;pendances";
$lang['mod_hdr_state']        = "&Eacute;tat";
$lang['mod_hdr_action']       = "Action";

$lang['mod_state_on']         = "allum&eacute;";
$lang['mod_state_off']        = "&eacute;teint";
$lang['mod_turn_on']          = "Allumer";
$lang['mod_turn_off']         = "&Eacute;teindre";
$lang['mod_native_badge']     = "du jeu";
$lang['mod_external_badge']   = "d&eacute;pos&eacute;";
$lang['mod_no_module']        = "Aucun module install&eacute; : d&eacute;posez un dossier dans `modules/`.";

$lang['mod_note'] = "Un module &eacute;teint dispara&icirc;t du jeu : ses pages et ses appels JSON refusent, et son entr&eacute;e quitte le menu. Vous continuez de le voir, pour pouvoir le rallumer. Un r&ocirc;le qui ne porte pas la permission du module le ferme aussi &agrave; ses comptes &mdash; un compte sans r&ocirc;le garde l'acc&egrave;s.";

$lang['mod_dep_problem'] = "D&eacute;pendance non satisfaite &mdash;";

$lang['mod_saved']     = "Module mis &agrave; jour.";
$lang['mod_notfound']  = "Ce module n'existe plus.";
$lang['mod_bad_token'] = "Jeton de s&eacute;curit&eacute; manquant : rien n'a &eacute;t&eacute; enregistr&eacute;.";
$lang['mod_dep_needed'] = "Un autre module allum&eacute; d&eacute;pend de celui-ci : &eacute;teignez-le d'abord.";

// --- D&eacute;sinstallation (archivage) et r&eacute;installation ---
// Archiver ne supprime rien : le module change de dossier (`modules/.archive/`), donc le
// jeu le voit exactement comme s'il n'avait jamais &eacute;t&eacute; d&eacute;pos&eacute;.
$lang['mod_archive']           = "D&eacute;sinstaller";
$lang['mod_archive_hint']      = "Range le module dans `modules/.archive/` : rien n'est supprim&eacute;, la r&eacute;installation le remet en place.";
$lang['mod_archive_note']      = "Ces modules ne sont plus d&eacute;pos&eacute;s : leurs pages, leurs routes et leurs surcharges ont disparu, et les modules qui en d&eacute;pendaient sont suspendus. Leurs fichiers et leurs r&eacute;glages sont conserv&eacute;s.";
$lang['mod_archived_badge']    = "archiv&eacute;";
$lang['mod_archive_restore']   = "R&eacute;installer";
$lang['mod_archive_saved']     = "Module d&eacute;sinstall&eacute; (archiv&eacute;).";
$lang['mod_restore_saved']     = "Module r&eacute;install&eacute;.";
$lang['mod_archive_exists']    = "Un module du m&ecirc;me nom est d&eacute;j&agrave; archiv&eacute; : r&eacute;installez-le ou d&eacute;placez l'ancien.";
$lang['mod_archive_missing']   = "Ce module n'est plus dans l'archive.";
$lang['mod_archive_conflict']  = "Un module du m&ecirc;me nom est d&eacute;j&agrave; d&eacute;pos&eacute;.";

// --- Liste : filtre d'état, recherche et tri ---
// La m&ecirc;me page montre les modules d&eacute;pos&eacute;s et ceux qui sont archiv&eacute;s : le filtre
// choisit, la recherche (nom, libell&eacute;, description, permission, adresse) et le tri portent
// sur ce qui reste.
$lang['mod_filter_installed'] = "Install&eacute;s";
$lang['mod_filter_archived']  = "Archiv&eacute;s";
$lang['mod_filter_all']       = "Tous";
$lang['mod_search']           = "Rechercher";
$lang['mod_search_hint']      = "nom, description, permission&hellip;";
$lang['mod_search_apply']     = "Filtrer";
$lang['mod_no_match']         = "Aucun module ne correspond &agrave; cette recherche.";
$lang['mod_no_filter']        = "Aucun module dans cette liste.";
$lang['mod_archive_failed']    = "Le d&eacute;placement du module a &eacute;chou&eacute; : le dossier `modules/` n'est pas inscriptible.";

// --- Téléversement d'un module ---
// L'archive est déposée en quarantaine, analysée, puis installée **sur décision**.
// Un refus arrête tout ; une alerte s'affiche et n'empêche rien : l'administrateur
// décide en connaissance de cause (c'est la règle de `App\Core\ModuleScan`).
$lang['mod_upload_title']       = "T&eacute;l&eacute;verser un module";
$lang['mod_upload_hint']        = "Archive (zip, tar ou tar.gz) contenant le dossier du module et son package.json.";
$lang['mod_upload_send']        = "Analyser";
$lang['mod_upload_install']     = "Installer";
$lang['mod_upload_force']       = "Installer malgr&eacute; les alertes";
$lang['mod_upload_report']      = "Rapport d'analyse";
$lang['mod_upload_clean']       = "Aucun refus, aucune alerte : le module est propre.";
$lang['mod_upload_refusals']    = "Refus : l'installation est impossible en l'&eacute;tat.";
$lang['mod_upload_alerts']      = "Alertes : elles n'emp&ecirc;chent pas l'installation, elles l'&eacute;clairent.";
$lang['mod_upload_fingerprint'] = "Empreinte SHA-256 de l'archive";
$lang['mod_upload_files']       = "fichiers analys&eacute;s";
$lang['mod_upload_stale']       = "Le module pr&eacute;par&eacute; n'est plus en quarantaine : relancez l'analyse.";
$lang['mod_upload_none']        = "Aucune archive re&ccedil;ue.";
$lang['mod_upload_bad']         = "Dossier de quarantaine introuvable : relancez l'analyse.";
$lang['mod_upload_installed']   = "Module install&eacute;. Il est allum&eacute; : v&eacute;rifiez sa page, puis ses migrations (db/migrate.php).";
$lang['mod_upload_confirm']     = "Ce module porte des alertes : cochez la case pour assumer l'installation.";
$lang['mod_upload_warning_note'] = "L'analyse est un filtre, pas un certificat : un module peut nuire sans employer une seule fonction signal&eacute;e.";
