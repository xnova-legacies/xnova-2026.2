<?php

namespace App\Controllers\Front;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\SessionService;

final class LoginController extends AbstractController
{
    public static function legacyConstants(): array
    {
        return ['INSIDE' => true, 'INSTALL' => false, 'LOGIN' => true, 'DISABLE_IDENTITY_CHECK' => true];
    }

    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly SessionService $sessions = new SessionService(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy(['DISABLE_IDENTITY_CHECK' => true, 'LOGIN' => true]);

        $this->includeLang('login');

        $lang = $this->lang();
        $gameConfig = $this->gameConfig();

        if (!empty($_POST)) {
            $userData = array(
                'username' => mysql_real_escape_string($_POST['username']),
                'password' => mysql_real_escape_string($_POST['password']),
            );

            $login = $this->auth->attempt($userData['username'], $userData['password']);

            $this->auth->clearExpiredBan($login);

            if ($login) {
                if (intval($login['login_success'])) {
                    if (isset($_POST['rememberme'])) {
                        setcookie('nova-cookie', array('id' => $login['id'], 'key' => $login['login_rememberme']), time() + 2592000);
                    }

                    $this->auth->touchOnline((int) $login['id']);

                    $_SESSION['user_id'] = $login['id'];

                    // Historique des sessions : une ligne par connexion.
                    $_SESSION['xnova_session_id'] = $this->sessions->start(
                        (int) $login['id'],
                        (string) ($_SERVER['REMOTE_ADDR'] ?? '')
                    );

                    return $this->redirect('/game/overview');
                }

                return $this->renderMessage($lang['Login_FailPassword'], $lang['Login_Error']);
            }

            return $this->renderMessage($lang['Login_FailUser'], $lang['Login_Error']);
        }

        $parse = $lang;
        $stats = $this->auth->loginStats();
        $parse['last_user'] = $stats['last_user'];
        $parse['online_users'] = $stats['online_users'];
        $parse['users_amount'] = $stats['users_amount'];
        $parse['servername'] = $gameConfig['game_name'];
        $parse['forum_url'] = $gameConfig['forum_url'];
        $parse['PasswordLost'] = $lang['PasswordLost'];

        $page = $this->parse($this->template('login_body'), $parse);

        if (isset($_GET['ucount']) && $_GET['ucount'] == 1) {
            return Response::raw($stats['online_users'] . "/" . $stats['users_amount']);
        }

        return $this->renderPage($page, $lang['Login']);
    }
}
