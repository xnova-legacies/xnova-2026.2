<?php

namespace App\Controllers\Front;

use App\Core\AbstractController;
use App\Core\Project;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;

final class LostPasswordController extends AbstractController
{
    public static function legacyConstants(): array
    {
        return ['INSIDE' => true, 'INSTALL' => false, 'DISABLE_IDENTITY_CHECK' => true];
    }

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy(['DISABLE_IDENTITY_CHECK' => true]);

        $mailData = array(
            'recipient' => null,
            'sender' => 'no-reply',
            'subject' => 'XNova:Legacies - Changement de mot de passe'
        );

        $this->includeLang('lostpassword');
        $lang = $this->lang();

        $username = null;
        if (!empty($_POST)) {
            if (isset($_POST['pseudo']) && !empty($_POST['pseudo'])) {
                $username = mysql_real_escape_string($_POST['pseudo']);
                $result = $this->users->findEmailAndUsernameByName($username);
                if (!$result) {
                    return $this->renderMessage("Cet utilisateur n'existe pas", 'Erreur', '/front/lostpassword');
                }
                list($mailData['recipient'], $username) = $result;
            } else if (isset($_POST['email']) && !empty($_POST['email'])) {
                $email = mysql_real_escape_string($_POST['email']);
                $result = $this->users->findEmailAndUsernameByEmail($email);
                if (!$result) {
                    return $this->renderMessage("Cet email n'est utilisé par aucun joueur", 'Erreur', '/front/lostpassword');
                }
                list($mailData['recipient'], $username) = $result;
            } else {
                return $this->renderMessage('Veuillez entrer votre login ou votre email.', 'Erreur', '/front/lostpassword');
            }

            if (!is_null($mailData['recipient'])) {
                $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
                $randomPass = '';
                $size = rand(8, 10);
                for ($i = 0; $i < $size; $i++) {
                    $randomPass .= $characters[rand(0, strlen($characters) - 1)];
                }

                $message = <<<EOF
Votre mot de passe a été modifié, veuillez trouver ci-dessous vos informations de connexion :
login : $username
mot de passe : $randomPass

A bientôt sur XNova:Legacies
EOF;

                $version = Project::version();
                $headers = <<<EOF
From: {$mailData['sender']}
X-Sender: Legacies/{$version}

EOF;
                mail($mailData['recipient'], $mailData['subject'], $message, $headers);

                $this->users->updatePasswordForUsername($randomPass, $username);

                return $this->renderMessage('Mot de passe envoyé ! Veuillez regarder votre boite e-mail ou dans vos spams.', 'Nouveau mot de passe', '/front/login');
            }
        }

        $parse = $lang;
        $page = $this->parse($this->template('lostpassword'), $parse);

        return $this->renderPage($page, $lang['ResetPass']);
    }
}
