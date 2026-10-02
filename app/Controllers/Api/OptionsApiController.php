<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;
use App\Services\ModuleService;
use App\Services\PlanetStateService;
use App\Services\UserSettingsService;

/**
 * Options du compte (formulaire /game/profil/options?mode=change) :
 *
 *   POST /game/api/options/save      { ...champs du formulaire... }
 *   POST /game/api/options/vacation  { exit_vacation }  (sortie de vacances)
 *
 * Le changement de pseudo ou de mot de passe invalide la session : la réponse
 * contient alors data.redirect = /front/login, comme le formulaire historique.
 */
final class OptionsApiController extends ApiController
{
    private const PASSWORD_MESSAGES = array(
        'wrong_password' => 'Mot de passe actuel incorrect.',
        'password_empty' => 'Merci de saisir le nouveau mot de passe.',
        'password_mismatch' => 'Les deux mots de passe ne correspondent pas.',
    );

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly PlanetStateService $state = new PlanetStateService(),
    ) {
    }

    public function saveAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $payload = $this->payload($request);
        $vacation = UserSettingsService::flag($payload, 'vacation_mode') === '1';

        if ($vacation) {
            $this->startVacation($user);
        }

        // Protection du joueur face aux attaques, réservée aux administrateurs.
        if ((int) $user['authlevel'] > 0) {
            $this->planets->setOwnerLevel(
                (int) $user['id'],
                UserSettingsService::flag($payload, 'adm_pl_prot') === '1' ? (int) $user['authlevel'] : 0
            );
        }

        // La classe est **résolue** : un module qui possède une colonne de réglage dans
        // `users` dérive le service et complète le tableau enregistré.
        $this->users->updateSettingsFull(
            ModuleService::resolve(UserSettingsService::class)::settings(
                $payload,
                $user,
                array('vacation_mode' => $vacation ? '1' : '0')
            ),
            (int) $user['id']
        );

        $messages = array(array('type' => 'success', 'text' => 'Options enregistrées.'));
        $data = array();

        if (UserSettingsService::wantsPasswordChange($payload)) {
            $problem = UserSettingsService::passwordProblem($payload, (string) $user['password']);

            if ($problem !== null) {
                throw ApiException::validation(
                    $problem,
                    self::PASSWORD_MESSAGES[$problem],
                    array('db_password' => self::PASSWORD_MESSAGES[$problem])
                );
            }

            $this->users->updatePassword((int) $user['id'], UserSettingsService::newPasswordHash($payload));
            $messages = array(array('type' => 'success', 'text' => 'Mot de passe modifié : reconnectez-vous.'));
            $data['redirect'] = '/front/login';
        } else {
            $username = UserSettingsService::username($payload, $user);

            if ($username !== (string) $user['username']) {
                if ($this->users->usernameTaken($username)) {
                    throw ApiException::validation(
                        'username_taken',
                        'Ce pseudo est déjà utilisé.',
                        array('db_character' => 'Ce pseudo est déjà utilisé.')
                    );
                }

                $this->users->updateUsername((int) $user['id'], $username);
                $messages = array(array('type' => 'success', 'text' => 'Pseudo modifié : reconnectez-vous.'));
                $data['redirect'] = '/front/login';
            }
        }

        return $this->success($data, $this->state->snapshot($user, $planet), $messages);
    }

    public function vacationAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $payload = $this->payload($request);

        if (UserSettingsService::flag($payload, 'exit_vacation') !== '1') {
            throw ApiException::validation(
                'vacation_missing',
                'Merci de cocher la case de confirmation.',
                array('exit_vacation' => 'Merci de cocher la case.')
            );
        }

        if ((int) ($user['vacation_until'] ?? 0) > time()) {
            throw ApiException::validation('vacation_running', 'Le mode vacances ne peut pas encore être interrompu.');
        }

        $this->users->clearVacation((int) $user['id']);

        foreach ($this->planets->findAllByOwner((int) $user['id']) as $row) {
            $this->planets->resetProductionPercent((int) $row['id']);
        }

        return $this->success(
            array(),
            $this->state->snapshot($user, $planet),
            array(array('type' => 'success', 'text' => 'Mode vacances désactivé.'))
        );
    }

    /**
     * Passage en mode vacances : refuse tant qu'une flotte, un chantier ou une
     * attaque est en cours (mêmes conditions que le formulaire historique).
     */
    private function startVacation(array $user): void
    {
        $fleet = $this->users->countFleetsByOwner((int) $user['id']);
        $build = $this->users->countBuildingByOwner((int) $user['id']);
        $tech = $this->users->countTechInProgress((int) $user['id']);
        $attack = $this->users->countIncomingFleets((int) $user['id']);

        if (
            (int) ($fleet['actcnt'] ?? 0) > 0
            || (int) ($build['building'] ?? 0) > 0
            || (int) ($tech['tech'] ?? 0) > 0
            || (int) ($attack['attack'] ?? 0) > 0
        ) {
            throw ApiException::validation(
                'vacation_unavailable',
                'Impossible de partir en vacances : flottes ou chantiers en cours.',
                array('vacation_mode' => 'Flottes ou chantiers en cours.')
            );
        }

        $this->users->setVacation((int) $user['id'], time() + UserSettingsService::VACATION_SECONDS);

        foreach ($this->planets->findAllByOwner((int) $user['id']) as $row) {
            $this->planets->zeroProductionPercent((int) $row['id'], $this->gameConfig());
        }
    }
}
