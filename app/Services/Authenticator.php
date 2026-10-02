<?php

namespace App\Services;

use App\Core\Flags;
use App\Core\Language;
use App\Repositories\AuthRepository;

/**
 * Authentification de session (session PHP + cookie "remember me").
 * Remplace ChekUser.php (CheckTheUser) et CheckCookies.php avec du SQL
 * préparé : le SQL legacy du cookie était injectable.
 *
 * C'est aussi le point de passage de **toutes** les pages : c'est donc ici que
 * l'historique des sessions note que le joueur vient de charger une page.
 */
final class Authenticator
{
    public function __construct(
        private readonly AuthRepository $auth = new AuthRepository(),
        private readonly SessionService $sessions = new SessionService(),
    ) {
    }

    /**
     * Vérifie la session/cookie et retourne l'enregistrement utilisateur.
     * Équivalent de CheckTheUser + CheckCookies combinés.
     */
    public function checkUser(bool $isUserChecked): array
    {
        $lang = Language::all();
        $userData = array();

        if (isset($_SESSION['user_id'])) {
            $userData = $this->auth->findById((int) $_SESSION['user_id']);
        } elseif (isset($_COOKIE['nova-cookie'])) {
            $cookieId = isset($_COOKIE['nova-cookie']['id']) ? (int) $_COOKIE['nova-cookie']['id'] : 0;
            $cookieKey = isset($_COOKIE['nova-cookie']['key']) ? (string) $_COOKIE['nova-cookie']['key'] : '';

            $userData = $this->auth->validateRememberMeCookie($cookieId, $cookieKey);

            if ($userData === false) {
                message($lang['cookies']['Error2'] ?? 'Erreur de cookie');
            }

            $_SESSION['user_id'] = (int) $userData['id'];

            $this->auth->touchSession(
                (int) $userData['id'],
                (string) ($_SERVER['REQUEST_URI'] ?? ''),
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );

            $isUserChecked = true;
        }

        // Historique des sessions : activité du joueur (une écriture par minute).
        if (is_array($userData) && isset($userData['id']) && (int) $userData['id'] > 0) {
            $_SESSION['xnova_session_id'] = $this->sessions->touch(
                (int) $userData['id'],
                isset($_SESSION['xnova_session_id']) ? (int) $_SESSION['xnova_session_id'] : null,
                (string) ($_SERVER['REMOTE_ADDR'] ?? '')
            );
        }

        // Joueur banni : page usr_banned (ex CheckTheUser).
        if ($userData !== array() && isset($userData['bana']) && $userData['bana'] == "1") {
            if (function_exists('includeLang')) {
                includeLang('system');
            }

            $lang = Language::all();
            $parse = $lang;
            $parse['banned_title'] = $lang['banned_title'] ?? 'Compte suspendu';
            $parse['banned_text'] = $lang['banned_text'] ?? 'Votre compte a &eacute;t&eacute; suspendu !';
            $parse['banned_contact'] = $lang['banned_contact'] ?? '';
            display(parsetemplate(gettemplate('usr_banned'), $parse), $parse['banned_title'], false, '', true);
        }

        // Compte supprimé (suppression logique) : même sortie que le bannissement,
        // avec son propre texte — les données sont toujours en base, l'accès non.
        // L'état est le drapeau partagé (`Flags::DELETED`).
        if ($userData !== array() && Flags::isDeleted((int) ($userData['flags'] ?? 0))) {
            if (function_exists('includeLang')) {
                includeLang('system');
            }

            $lang = Language::all();
            $parse = $lang;
            $parse['banned_title'] = $lang['deleted_title'] ?? 'Compte supprimé';
            $parse['banned_text'] = $lang['deleted_text'] ?? 'Ce compte a &eacute;t&eacute; supprim&eacute;.';
            $parse['banned_contact'] = '';
            display(parsetemplate(gettemplate('usr_banned'), $parse), $parse['banned_title'], false, '', true);
        }

        return array(
            'state' => $isUserChecked,
            'record' => $userData === false ? array() : $userData,
        );
    }
}
