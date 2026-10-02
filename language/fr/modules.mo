<?php
/**
 * Libellés **communs** aux modules du jeu (registre de la page /back/modules).
 *
 * Les libellés d'un module appartiennent à son module : `modules/<nom>/language/`,
 * chargés par `App\Core\Language::includeModule()`. Ce fichier ne porte que ce qui
 * ne dépend d'aucun module — le refus d'une fonctionnalité éteinte — pour que le
 * message existe même si le module a disparu du disque.
 *
 * Il ne doit pas y avoir d'accolade dans ces textes : `parsetemplate` les lirait
 * comme des marqueurs de gabarit.
 */

$lang['mod_disabled_title']    = "Module d&eacute;sactiv&eacute;";
$lang['mod_disabled_message']  = "Le module %s est d&eacute;sactiv&eacute; par l'administration : son adresse n'est plus accessible.";

// Page du panneau dont le module n'est plus l&agrave; (d&eacute;sinstall&eacute;, ou jamais d&eacute;pos&eacute;) :
// le Coeur d'application ne charge aucune classe d'un module absent, il le dit &agrave; la place.
$lang['mod_missing_message']   = "Le module %s n'est pas disponible sur cette installation : cette page n'a rien &agrave; montrer.";

// D&eacute;pendances d'un module : les mots sont ici parce que le refus d'une page les
// affiche aussi (panneau des modules et jeu partagent la m&ecirc;me phrase).
$lang['mod_dep_core']    = "Coeur d'application";
$lang['mod_dep_module']  = "module";
$lang['mod_dep_missing'] = "absent :";
$lang['mod_dep_off']     = "&eacute;teint :";
$lang['mod_dep_blocked'] = "Le module %module% est suspendu : il attend %depends%.";
