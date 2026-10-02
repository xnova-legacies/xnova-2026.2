<?php

namespace App\Services;

use App\Entities\ConfigEntry;
use App\Repositories\ConfigRepository;
use App\Repositories\GalaxyRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;

final class RegistrationService
{
    private const MAX_GALAXY = 9;
    private const MAX_SYSTEM = 499;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly ConfigRepository $config = new ConfigRepository(),
        private readonly GalaxyRepository $galaxies = new GalaxyRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly MessageService $messages = new MessageService(),
    ) {
    }

    public function usernameAvailable(string $username): bool
    {
        return $this->users->usernameExists($username) === false;
    }

    public function emailAvailable(string $email): bool
    {
        return $this->users->emailExists($email) === false;
    }

    public function register(string $username, string $email, string $sex, string $ip, string $password, string $planetName): int
    {
        $this->users->insertRegistration($username, $email, $sex, $ip, md5($password));

        $userId = (int) $this->users->findIdByUsername($username)['id'];

        list($galaxy, $system, $planet) = $this->findFreePosition();

        if ($this->galaxies->findPosition($galaxy, $system, $planet) === false) {
            CreateOnePlanetRecord($galaxy, $system, $planet, $userId, $planetName, true);
        } else {
            CreateOnePlanetRecord($galaxy, $system, $planet, $userId, $planetName, false);
        }

        $planetRow = $this->users->findPlanetIdByOwner($userId);
        $planetId = (int) $planetRow['id'];

        $this->users->updateHomePlanet($userId, $planetId, $galaxy, $system, $planet);
        $this->users->incrementUsersAmount();

        $lang = $GLOBALS['lang'] ?? array();

        $this->messages->sendAdminSimple(
            $userId,
            $lang['sender_message_ig'] ?? 'Admin',
            $lang['subject_message_ig'] ?? 'Bienvenue',
            $lang['text_message_ig'] ?? 'Bienvenue sur XNova !'
        );

        return $userId;
    }

    private function findFreePosition(): array
    {
        $galaxy = $this->configInt('LastSettedGalaxyPos');
        $system = $this->configInt('LastSettedSystemPos');
        $planetPos = $this->configInt('LastSettedPlanetPos');

        for ($g = $galaxy; $g <= self::MAX_GALAXY; $g++) {
            for ($s = $system; $s <= self::MAX_SYSTEM; $s++) {
                for ($p = $planetPos; $p <= 4; $p++) {
                    $planetSlot = random_int(4, 12);

                    $row = $this->galaxies->findPosition($g, $s, $planetSlot);

                    if ($row === false || (int) ($row['id_planet'] ?? 0) === 0) {
                        return array($g, $s, $planetSlot);
                    }
                }
            }
        }

        return array(1, 1, random_int(4, 12));
    }

    /**
     * Valeur entière d'une clé de configuration du jeu.
     *
     * `ConfigRepository::findValue()` ne sélectionne que `config_value` :
     * l'entité accepte donc une ligne partielle.
     */
    private function configInt(string $name, int $default = 1): int
    {
        $row = $this->config->findValue($name);

        return $row === false ? $default : ConfigEntry::fromRow($row)->valueAsInt();
    }
}
