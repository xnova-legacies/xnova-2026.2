<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ConfigRepository;
use App\Repositories\GalaxyRepository;
use App\Repositories\UserRepository;
use App\Services\RegistrationService;

final class RegController extends AbstractController
{
    public static function legacyConstants(): array
    {
        return ['INSIDE' => true, 'INSTALL' => false, 'DISABLE_IDENTITY_CHECK' => true];
    }

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly RegistrationService $registration = new RegistrationService(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        session_start();

        $this->includeLang('reg');

        $lang = $this->lang();
        $gameConfig = $this->gameConfig();

        if ($_POST) {
            $errors = 0;
            $errorlist = "";

            if (($gameConfig['secu'] ?? null) == 1) {
                if (!($_POST['secu'] ?? '') || $_POST['secu'] != ($_SESSION['secu'] ?? null)) {
                    $errorlist .= $lang['error_secu'];
                    $errors++;
                }
            }

            $_POST['email'] = strip_tags((string) ($_POST['email'] ?? ''));
            if (!is_email($_POST['email'])) {
                // Le message est renvoye dans la page : on l'echappe (XSS reflechi).
                $errorlist .= "\"" . htmlspecialchars($_POST['email'], ENT_QUOTES) . "\" " . $lang['error_mail'];
                $errors++;
            }

            if (!($_POST['planet'] ?? '')) {
                $errorlist .= $lang['error_planet'];
                $errors++;
            }

            if (preg_match("/[^A-z0-9_\-]/", (string) ($_POST['planet'] ?? '')) == 1) {
                $errorlist .= $lang['error_planetnum'];
                $errors++;
            }

            if (!($_POST['character'] ?? '')) {
                $errorlist .= $lang['error_character'];
                $errors++;
            }

            if (strlen((string) ($_POST['passwrd'] ?? '')) < 4) {
                $errorlist .= $lang['error_password'];
                $errors++;
            }

            if (preg_match("/[^A-z0-9_\-]/", (string) ($_POST['character'] ?? '')) == 1) {
                $errorlist .= $lang['error_charalpha'];
                $errors++;
            }

            if (($_POST['rgt'] ?? '') != 'on') {
                $errorlist .= $lang['error_rgt'];
                $errors++;
            }

            $ExistUser = $this->users->usernameExists((string) ($_POST['character'] ?? ''));
            if ($ExistUser) {
                $errorlist .= $lang['error_userexist'];
                $errors++;
            }

            $ExistMail = $this->users->emailExists($_POST['email']);
            if ($ExistMail) {
                $errorlist .= $lang['error_emailexist'];
                $errors++;
            }

            if (($_POST['sex'] ?? '') != '' && $_POST['sex'] != 'F' && $_POST['sex'] != 'M') {
                $errorlist .= $lang['error_sex'];
                $errors++;
            }

            if ($errors != 0) {
                return $this->renderMessage($errorlist, $lang['Register']);
            }

            $newpass = (string) ($_POST['passwrd'] ?? '');
            $UserName = CheckInputStrings((string) ($_POST['character'] ?? ''));
            $UserEmail = CheckInputStrings((string) ($_POST['email'] ?? ''));
            $UserPlanet = CheckInputStrings(addslashes((string) ($_POST['planet'] ?? '')));

            $userId = $this->registration->register($UserName, $UserEmail, (string) ($_POST['sex'] ?? ''), $_SERVER["REMOTE_ADDR"], $newpass, $UserPlanet);

            $Message = $lang['thanksforregistry'] . " (" . htmlentities($UserEmail) . ")";

            if (!$this->sendPassEmail($UserEmail, "$newpass")) {
                $Message .= str_replace('%s', (string) $newpass, $lang['reg_mail_failed']);
            }

            return $this->renderMessage($Message, $lang['reg_welldone']);
        } elseif (($gameConfig['secu'] ?? null) == 1) {
            $parse = $lang;
            $_SESSION['nombre1'] = rand(0, 50);
            $_SESSION['nombre2'] = rand(0, 50);
            $_SESSION['secu'] = $_SESSION['nombre1'] + $_SESSION['nombre2'];

            // Le bloc de sécurité vit dans registry_form.tpl (classe vide ici,
            // `d-none` quand la sécurité est désactivée).
            $parse['secu_hidden'] = '';
            $parse['secu_nombre1'] = $_SESSION['nombre1'];
            $parse['secu_nombre2'] = $_SESSION['nombre2'];
            $parse['code_secu'] = $lang['code_secu'] ?? 'Sécurité';
            $page = $this->parse($this->template('registry_form'), $parse);
        } else {
            $parse = $lang;
            $parse['code_secu'] = '';
            $parse['affiche'] = '';
            $parse['secu_hidden'] = ' d-none';
            $parse['secu_nombre1'] = '';
            $parse['secu_nombre2'] = '';
            $page = $this->parse($this->template('registry_form'), $parse);
        }

        return $this->renderPage($page, $lang['registry'], false);
    }

    private function sendPassEmail(string $emailAddress, string $password): bool
    {
        $lang = $this->lang();

        $parse['gameurl'] = GAMEURL;
        $parse['password'] = $password;
        $email = $this->parse($lang['mail_welcome'], $parse);

        return (bool) $this->myMail($emailAddress, $lang['mail_title'], $email);
    }

    private function myMail(string $to, string $title, string $body, string $from = ''): bool
    {
        $from = trim($from);

        if (!$from) {
            $from = ADMINEMAIL;
        }

        $rp = ADMINEMAIL;

        $head = '';
        $head .= "Content-Type: text/plain \r\n";
        $head .= "Date: " . date('r') . " \r\n";
        $head .= "Return-Path: $rp \r\n";
        $head .= "From: $from \r\n";
        $head .= "Sender: $from \r\n";
        $head .= "Reply-To: $from \r\n";
        $head .= "Organization: " . ($org ?? '') . " \r\n";
        $head .= "X-Sender: $from \r\n";
        $head .= "X-Priority: 3 \r\n";
        $body = str_replace("\r\n", "\n", $body);
        $body = str_replace("\n", "\r\n", $body);

        return (bool) mail($to, $title, $body, $head);
    }
}
