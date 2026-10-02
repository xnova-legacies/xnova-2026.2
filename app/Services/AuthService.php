<?php

namespace App\Services;

use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function attempt(string $username, string $password): array|false
    {
        return $this->users->attemptLogin($username, $password);
    }



    /**
     * Lève le bannissement arrivé à échéance, avant la vérification du mot de
     * passe (comportement historique de la page de connexion).
     *
     * `attempt()` renvoie `false` quand le pseudo est inconnu : il n'y a alors
     * aucun compte — donc aucun bannissement — à traiter, on sort sans rien
     * faire. Le contrôleur appelle cette méthode sans savoir si le compte
     * existe : c'est donc ici que le cas se règle, pas chez l'appelant.
     */
    public function clearExpiredBan(array|false $login): void
    {
        if (!is_array($login) || !isset($login['banaday'])) {
            return;
        }

        if ($login['banaday'] <= time() & $login['banaday'] != '0') {
            $this->users->clearBan($login['username']);
        }
    }

    public function touchOnline(int $userId): void
    {
        $this->users->touchOnline($userId);
    }

    public function loginStats(): array
    {
        $count = $this->users->countPlayers();
        $lastPlayer = $this->users->findLastRegisteredUsername();
        $playersOnline = $this->users->countOnlinePlayers();

        return array(
            'last_user' => $lastPlayer['username'],
            'online_users' => $playersOnline['onlinenow'],
            'users_amount' => $count['players'],
        );
    }
}
