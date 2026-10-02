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

if ( defined('INSIDE') ) {
	// Les valeurs de jeu viennent d'une source unique (App\Core\GameConstants) :
	// chaque constante est surchargeable par l'environnement (.env).
	foreach (App\Core\GameConstants::all() as $ConstantName => $ConstantValue) {
		if (!defined($ConstantName)) {
			define($ConstantName, $ConstantValue);
		}
	}

	define('GAMEURL'                  , "http://".($_SERVER['HTTP_HOST'] ?? 'localhost')."/");

	// Debug Level (piloté par CONFIG_DEBUG dans `configs/.env.<env>`)
	define('DEBUG', (int) (App\Core\ConfigDefaults::envValue('debug') === '1'));
	// Mot qui sont interdit a la saisie !
	$ListCensure = App\Core\GameConstants::censoredWords();
} else {
	die("Hacking attempt");
}



?>