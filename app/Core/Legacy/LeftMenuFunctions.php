<?php

/**
 * Tableau legacy du menu latéral de jeu.
 *
 * `ShowLeftMenu()` vivait dans `leftmenu.php`, à la racine : ce fichier n'était
 * qu'un wrapper, et sa place est ici, avec les autres tableaux de fonctions de
 * page (`app/Core/Legacy/`). Le rendu, lui, reste dans `App\Core\LeftMenu`, seule
 * implémentation.
 *
 * Chargé par `includes/todofleetcontrol.php` et, à la demande, par les deux
 * `renderDisplay()` (`App\Core\TemplateEngine` et `includes/functions.php`).
 */

function ShowLeftMenu ( $Level , $Template = 'left_menu') {
    return \App\Core\LeftMenu::render($Level, $Template);
}
