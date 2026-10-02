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

$lang['ins_appname']      = "XNova";
$lang['ins_tx_state']     = "Etape";
$lang['ins_tx_sys']       = "Gestion syst&egrave;me";
$lang['ins_btn_next']     = "Suivant";
$lang['ins_btn_inst']     = "Installer";
$lang['ins_btn_creat']    = "Cr&eacute;er";
$lang['ins_btn_login']    = "Connexion";
$lang['ins_btn_prev']     = "Pr&eacute;c&eacute;dant";

$lang['ins_mnu_intro']    = "Introduction";
$lang['ins_mnu_inst']     = "Installer";
$lang['ins_mnu_upgr']     = "Mise &agrave; Jour";
$lang['ins_mnu_quit']     = "Quitter";

$lang['ins_error']        = "Erreur";
$lang['ins_error1']       = "La connexion &agrave; la base de donn&eacute;e a &eacute;chou&eacute;";
$lang['ins_error2']       = "Le fichier config.php ne pas &ecirc;tre remplacer";

$lang['ins_tx_welco']     = "Bienvenue dans l'installation de XNova";
$lang['ins_tx_intr1']     = "Le projet XNova vous permettra d'installer un clone d'ogame quasi parfait";
$lang['ins_tx_intr2']     = "Le projet XNova est libre, gratuit et OpenSource. Merci de ne pas en faire d'utilisation commerciale";
$lang['ins_tx_intr3']     = "Par respect pour l'equipe de d&eacute;veloppement de ce projet, vous &ecirc;tes pri&eacute;s de ne pas supprimer les copyright des fichiers source.";
$lang['ins_tx_inst1']     = "Le fichier config.php doit &ecirc;tre en CHMOD 777";
$lang['ins_tx_inst2']     = "Vous devez poss&eacute;der une base de donn&eacute;e MySQL";
$lang['ins_tx_inst3']     = "Vous devez remplir le formulaire suivant correctement pour continuer l'installation:";

// Vérification du serveur, **avant** de parler de base de données : une installation
// hors Docker n'a pas forcément PDO, mbstring ou un `configs/` inscriptible.
$lang['ins_chk_php']      = "Version de PHP (8.3 minimum)";
$lang['ins_chk_ext']      = "Extensions PHP requises";
$lang['ins_chk_vendor']   = "Dépendances installées (composer install)";
$lang['ins_chk_dir']      = "Dossier inscriptible";
$lang['ins_chk_ok']       = "OK";
$lang['ins_chk_ko']       = "manquant";
$lang['ins_mnu_next']     = "Continuer";
$lang['ins_tx_check1']    = "Voici ce dont le jeu a besoin sur ce serveur. Tout doit être au vert pour continuer.";
$lang['ins_tx_check3']    = "Un prérequis manque : corrigez-le avant de continuer, l'installation échouerait à l'étape suivante.";
$lang['ins_tx_acc1']      = "Vous &ecirc;tes sur le point de cr&eacute;er un compte administrateur";
$lang['ins_tx_acc2']      = "Remplissez le formulaire suivant avec les informations du compte:";
$lang['ins_tx_done1']     = "La base de donn&eacute;e a bien &eacute;t&eacute; install&eacute;e!";
$lang['ins_tx_done2']     = "Le compte administrateur a correctement &eacute;t&eacute; cr&eacute;&eacute;!";
$lang['ins_tx_done3']     = "Il est conseill&eacute; de supprimer le dossier <i>install</i> si vous n'avez plus besoin de l'installateur!";

$lang['ins_form_server']  = "Serveur SQL";
$lang['ins_form_db']      = "Base de donn&eacute;e";
$lang['ins_form_prefix']  = "Pr&eacute;fix des tables";
$lang['ins_form_login']   = "Identifiant";
$lang['ins_form_pass']    = "Mot-de-passe";
$lang['ins_form_install'] = "Installer";

$lang['ins_acc_user']     = "Pseudo";
$lang['ins_acc_pass']     = "Mot-de-passe";
$lang['ins_acc_email']    = "Adresse e-Mail";
$lang['ins_acc_planet']   = "Plan&egrave;te m&egrave;re";
$lang['ins_acc_sex']      = "Sexe";
$lang['ins_acc_sex0']     = "-ind&eacute;fini-";
$lang['ins_acc_sex1']     = "Homme";
$lang['ins_acc_sex2']     = "Femme";

?>