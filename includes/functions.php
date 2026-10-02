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

function check_vacation_mode($user)
{
	if ($user['vacation_mode'] == 1) {
		message("Vous êtes en mode vacances!", $title = $user['username'], $dest = "", $time = "3");
	}
}

function check_vacation_mode_time()
{
	global $user, $game_config;
	if ($game_config['vacation_mode_enforced'] == 1) {
		$limit                = 86400; //24x60x60= 24h
		$vacation_mode_time   = $user['vacation_mode_time'];
		$vacation_mode_until  = $vacation_mode_time + $limit;
		$now                  = time();
		if ($user['vacation_mode'] == 1 && $vacation_mode_until > $now) {
			$deadline_date = date("d.m.Y", $vacation_mode_until);
			$deadline_time = date("H:i:s", $vacation_mode_until);
message("Vous êtes en mode vacances!<br>Le mode vacance dure jusque $deadline_date $deadline_time<br>  Ce n'est qu'après cette période que vous pouvez changer vos options.", "Mode vacance");
		}
	}
}

// ----------------------------------------------------------------------------------------------------------------
//
// Routine Test de validité d'une adresse email
//
function is_email($email)
{
	return (preg_match("/^[-_.[:alnum:]]+@((([[:alnum:]]|[[:alnum:]][[:alnum:]-]*[[:alnum:]])\.)+(ad|ae|aero|af|ag|ai|al|am|an|ao|aq|ar|arpa|as|at|au|aw|az|ba|bb|bd|be|bf|bg|bh|bi|biz|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|com|coop|cr|cs|cu|cv|cx|cy|cz|de|dj|dk|dm|do|dz|ec|edu|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gh|gi|gl|gm|gn|gov|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|in|info|int|io|iq|ir|is|it|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|mg|mh|mil|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|museum|mv|mw|mx|my|mz|na|name|nc|ne|net|nf|ng|ni|nl|no|np|nr|nt|nu|nz|om|org|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|pro|ps|pt|pw|py|qa|re|ro|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw)$|(([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))$/i", $email));
}

// ----------------------------------------------------------------------------------------------------------------
//
// Routine Affichage d'un message administrateur avec saut vers une autre page si souhaité
//
function AdminMessage($mes, $title = 'Error', $dest = '', $time = '3', $color = 'red')
{
	echo renderMessage($mes, $title, $dest, $time, $color);

	die();
}

// ----------------------------------------------------------------------------------------------------------------
//
// Routine Affichage d'un message avec saut vers une autre page si souhaité
//
function message($mes, $title = 'Error', $dest = "", $time = "3", $color = 'orange')
{
	echo renderMessage($mes, $title, $dest, $time, $color);

	die();
}

// ----------------------------------------------------------------------------------------------------------------
//
// Helpers Bootstrap : les assets doivent etre references en absolu car les URLs
// MVC (/game/xxx, /front/xxx) resolvent les chemins relatifs depuis un sous-dossier.
//
function BootstrapStylesheetTag()
{
	return '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">' . "\n"
		. '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">' . "\n";
}

function BootstrapScriptTag()
{
	return '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>' . "\n"
		. '<script src="/scripts/theme.js" defer></script>' . "\n";
}

// Convertit une couleur legacy (red, orange, green, ...) en variante Bootstrap.
function BootstrapVariantFromColor($color)
{
	switch (strtolower(trim((string) $color))) {
		case 'red':
		case 'danger':
		case '#ff0000':
			return 'danger';
		case 'orange':
		case 'warning':
		case '#ffa500':
			return 'warning';
		case 'green':
		case 'lime':
		case 'success':
			return 'success';
		case 'blue':
		case 'info':
			return 'info';
		case 'dark':
		case 'black':
			return 'dark';
		default:
			return 'secondary';
	}
}

/**
 * Adaptateur legacy pour les messages d'erreur / succes.
 *
 * De nombreux appels historiques encapsulent encore le message dans
 * <font color="red"><b>..</b></font> : on retire le tableau, on en deduit
 * la variante Bootstrap et on remplace <b> par <strong>.
 */
function LegacyMessageText($message, &$color)
{
	$message = (string) $message;

	if (!preg_match('#^\s*<font\s+color=["\']?([a-z0-9\#]+)["\']?\s*>(.*)</font>\s*$#si', $message, $m)) {
		return $message;
	}

	$inner = $m[2];

	if ($color === '' || $color === null || strtolower(trim((string) $color)) === 'orange') {
		$color = $m[1];
	}

	if (preg_match('#^\s*<b>(.*)</b>\s*$#si', $inner, $b)) {
		$inner = '<strong>' . $b[1] . '</strong>';
	}

	return $inner;
}

/**
 * Traduit les anciennes destinations de redirection ("fleet.php") en routes MVC.
 */
function LegacyMessageDestination($dest)
{
	$map = array(
		'fleet.php' => '/game/fleet',
		'alliance.php' => '/game/alliance',
		'galaxy.php' => '/game/galaxy',
		'overview.php' => '/game/overview',
		'buildings.php' => '/game/buildings',
		'resources.php' => '/game/resources',
		'stat.php' => '/game/stat',
		'records.php' => '/game/records',
		'search.php' => '/game/search',
		'chat.php' => '/game/chat',
		'annie.php' => '/game/annonce',
	);

	$dest = (string) $dest;

	return isset($map[$dest]) ? $map[$dest] : $dest;
}

function renderMessage($mes, $title = 'Error', $dest = "", $time = "3", $color = 'orange')
{
	$mes  = LegacyMessageText($mes, $color);
	$dest = LegacyMessageDestination($dest);

	$parse['color']   = $color;
	$parse['variant'] = BootstrapVariantFromColor($color);
	$parse['title']   = $title;
	$parse['mes']     = $mes;

	$page = parsetemplate(gettemplate('admin/message_body'), $parse);

	// La page reste autonome (en-tete admin) : on y charge le Coeur d'application AJAX pour la
	// notification flottante, le bouton de retour et la redirection annulable.
	$NoticeScript = '<script src="/scripts/xnova-ajax.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-ajax.js') . '" defer></script>' . "\n";

	return renderDisplay($page, $title, false, $NoticeScript . (($dest != "") ? "<meta http-equiv=\"refresh\" content=\"$time;url=$dest\">" : ""), true);
}

// ----------------------------------------------------------------------------------------------------------------
//
// Routine d'affichage d'une page dans un cadre donné
//
// $page      -> la page
// $title     -> le titre de la page
// $topnav    -> Affichage des ressources ? oui ou non ??
// $metatags  -> S'il y a quelques actions particulieres a faire ...
// $AdminPage -> Si on est dans la section admin ... faut le dire ...
function display($page, $title = '', $topnav = true, $metatags = '', $AdminPage = false)
{
	echo renderDisplay($page, $title, $topnav, $metatags, $AdminPage);

	die();
}

function renderDisplay($page, $title = '', $topnav = true, $metatags = '', $AdminPage = false)
{
	global $link, $game_config, $debug, $user, $planetrow;

	ob_start();

	if (!$AdminPage) {
		$DisplayPage  = StdUserHeader($title, $metatags);
	} else {
		$DisplayPage  = AdminUserHeader($title, $metatags);
	}

	if ($topnav) {
		$DisplayPage .= ShowTopNavigationBar($user, $planetrow);
	}

	if (!$AdminPage && !defined('LOGIN') && !defined('POPUP') && !empty($user['id'])) {
		require_once ROOT_PATH . 'app/Core/Legacy/LeftMenuFunctions.' . PHPEXT;
		$menu = ShowLeftMenu($user['authlevel']);
		// Panneau latéral des files d'attente (App\Core\QueueBar) : une colonne à
		// droite, sur toutes les pages de jeu. Elle n'existe que si quelque chose est
		// en chantier — sinon la page reprend toute sa largeur.
		$QueueBar   = \App\Core\QueueBar::column($user, $planetrow);
		$ContentCol = $QueueBar === '' ? 'col-12 col-lg-9 col-xxl-10' : 'col-12 col-lg-6 col-xxl-8';
		$DisplayPage .= '<div class="container-fluid xnova-shell">';
		$DisplayPage .= '<div class="row g-3 g-xl-4">';
		$DisplayPage .= '<aside class="col-12 col-lg-3 col-xxl-2 xnova-sidebar">' . $menu . '</aside>';
		$DisplayPage .= '<main class="' . $ContentCol . ' xnova-content">' . $page . '</main>';
		$DisplayPage .= $QueueBar;
		$DisplayPage .= '</div>';
		$DisplayPage .= '</div>';
	} else {
		$DisplayPage .= '<div class="xnova-page">' . $page . '</div>';
	}
	// Affichage du Debug si necessaire
	//
	// Le journal legacy (`$debug->echo_log()`) terminait par `die()` : dès que le
	// réglage `debug` était activé, **toutes** les pages se réduisaient à ce
	// journal. La barre de debug moderne (`DEBUG_BAR=1`, injectée par index.php)
	// le remplace.
	$DisplayPage .= StdFooter();
	if (isset($link)) {
		mysql_close($link);
	}

	echo $DisplayPage;

	return ob_get_clean();
}

// ----------------------------------------------------------------------------------------------------------------
//
// Entete de page
//
function StdUserHeader($title = '', $metatags = '')
{
	global $user, $langInfos;

	$parse             = is_array($langInfos) ? $langInfos : array();
	$parse['title']    = $title;
	$parse['lang']     = (!empty($user['lang'])) ? $user['lang'] : 'fr';
	$parse['theme']    = 'dark';
	$parse['dpath']    = (!empty($GLOBALS['dpath'])) ? $GLOBALS['dpath'] : DEFAULT_SKINPATH;

	$Styles            = BootstrapStylesheetTag();

	if (defined('LOGIN')) {
		$Styles           .= '<link rel="stylesheet" href="/css/styles.css">' . "\n";
		$Styles           .= '<link rel="stylesheet" href="/css/about.css">' . "\n";
		$Styles           .= '<link rel="stylesheet" href="/css/bootstrap-compat.css">' . "\n";
	} else {
		// Feuilles legacy conservees en repli (les pages non migrees en dependent encore).
		$Styles           .= '<link rel="stylesheet" href="' . $parse['dpath'] . 'default.css">' . "\n";
		$Styles           .= '<link rel="stylesheet" href="' . $parse['dpath'] . 'formate.css">' . "\n";
		$Styles           .= '<link rel="stylesheet" href="/css/styles.css">' . "\n";
		$Styles           .= '<link rel="stylesheet" href="/css/bootstrap-compat.css">' . "\n";
	}

	// Jeton CSRF pour les appels AJAX (les pages de connexion n'en ont pas besoin).
	$CsrfMeta          = '';
	$ApiScripts        = '';
	if (!defined('LOGIN')) {
		$CsrfMeta   = '<meta name="csrf-token" content="' . htmlspecialchars(App\Core\Api\CsrfToken::token(), ENT_QUOTES) . '">' . "\n";
		// Canal temps réel, activable par WS_ENABLED dans le .env : sans lui, le
		// navigateur se sert de l'API JSON et de son polling (repli).
		$ApiScripts = '';

		if (App\Core\Ws\Settings::enabled()) {
			$WSUrl       = htmlspecialchars(App\Core\Ws\Settings::publicUrl(), ENT_QUOTES);
			$CsrfMeta   .= '<meta name="ws-enabled" content="1">' . "\n";
			$CsrfMeta   .= '<meta name="ws-url" content="' . $WSUrl . '">' . "\n";
			$ApiScripts .= '<script src="/scripts/xnova-ws.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-ws.js') . '" defer></script>' . "\n";
		} else {
			$CsrfMeta .= '<meta name="ws-enabled" content="0">' . "\n";
		}

		// La date de modification sert de version : le navigateur recharge le
		// script des qu'il change, sans purge de cache manuelle.
		$ApiScripts .= '<script src="/scripts/xnova-ajax.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-ajax.js') . '" defer></script>' . "\n"
			. '<script src="/scripts/xnova-live.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-live.js') . '" defer></script>' . "\n"
			. '<script src="/scripts/xnova-queue.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-queue.js') . '" defer></script>' . "\n"
			. '<script src="/scripts/xnova-fleets.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-fleets.js') . '" defer></script>' . "\n"
			// Les scripts d'un module (tchat, marché) vivent dans son module : ils sont
			// découverts et ajoutés ici, et seulement sur les pages du module.
			. App\Core\Modules::assetTags();
	}

	$parse['-style-']  = $Styles;
	$parse['-meta-']   = $CsrfMeta . (($metatags) ? $metatags : "");
	$parse['-body-']   = '<body class="xnova-body">';
	$parse['-script-'] = BootstrapScriptTag() . $ApiScripts
		. '<script src="/scripts/xnova-checkall.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-checkall.js') . '" defer></script>' . "\n";
	return parsetemplate(gettemplate('simple_header'), $parse);
}

// ----------------------------------------------------------------------------------------------------------------
//
// Entete de page administration
//
function AdminUserHeader($title = '', $metatags = '')
{
	global $user, $dpath, $langInfos;

	$parse           = is_array($langInfos) ? $langInfos : array();
	$parse['dpath']  = ($dpath) ? $dpath : DEFAULT_SKINPATH;
	$parse['title']  = $title;
	$parse['lang']   = (!empty($user['lang'])) ? $user['lang'] : 'fr';
	$parse['theme']  = 'dark';

	$Styles          = BootstrapStylesheetTag();
	$Styles         .= '<link rel="stylesheet" href="' . $parse['dpath'] . 'formate.css">' . "\n";
	$Styles         .= '<link rel="stylesheet" href="/css/bootstrap-compat.css">' . "\n";

	$parse['-style-']  = $Styles;
	$parse['-meta-']   = ($metatags) ? $metatags : "";
	$parse['-body-']   = '<body class="xnova-body">';
	$parse['-script-'] = BootstrapScriptTag()
		. '<script src="/scripts/xnova-checkall.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-checkall.js') . '" defer></script>' . "\n";
	return parsetemplate(gettemplate('admin/simple_header'), $parse);
}

// ----------------------------------------------------------------------------------------------------------------
//
// Pied de page
//
function StdFooter()
{
	global $game_config, $lang;
	$parse['copyright']     = isset($game_config['copyright']) ? $game_config['copyright'] : 'XNova Support Team';
	$parse['TranslationBy'] = isset($lang['TranslationBy']) ? $lang['TranslationBy'] : '';
	return parsetemplate(gettemplate('overall_footer'), $parse);
}

// ----------------------------------------------------------------------------------------------------------------
//
// Calcul de la place disponible sur une planete
//
function CalculateMaxPlanetFields(&$planet)
{
	global $resource;

	return $planet["field_max"] + ($planet[$resource[33]] * 5);
}
