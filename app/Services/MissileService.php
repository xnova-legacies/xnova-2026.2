<?php

namespace App\Services;

use App\Core\Combat\MissileStrike;
use App\Core\GameData;
use App\Entities\Fleet;
use App\Entities\Message;
use App\Repositories\FleetRepository;
use App\Repositories\MessageRepository;
use App\Repositories\MissionRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;

/**
 * Attaque de missiles interplanétaires : le **tir** et l'**impact**.
 *
 * Depuis la migration 019, une salve est une vraie **mission de flotte** (mission
 * 11, table `fleets`) : le tir l'enregistre, le moteur de missions l'apporte ici à
 * l'échéance (`FleetMissionService::missileStrike()`), et le calcul des dégâts vit
 * dans `App\Core\Combat\MissileStrike`. Il n'y a donc plus ni table dédiée, ni
 * fichier inclus à chaque requête pour les résoudre.
 */
final class MissileService
{
    /** Silo de missiles exigé pour tirer (règle historique). */
    public const SILO_LEVEL = 4;

    /**
     * Correspondance index du calcul => id ressource, reprise de l'ancienne table
     * `$ids` du traitement. Elle est **volontairement conservée telle quelle**,
     * inversion 502/503 comprise : le calcul des missiles lit ses défenses dans
     * l'ordre historique (voir `$def` dans `applyStrike()`) et le décompte doit
     * suivre le même ordre pour rester fidèle au résultat d'origine.
     */
    private const DEFENCE_IDS = array(401, 402, 403, 404, 405, 406, 407, 408, 502, 503);

    public function __construct(
        private readonly FleetRepository $fleets = new FleetRepository(),
        private readonly MissionRepository $missions = new MissionRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly MessageRepository $messages = new MessageRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /** Portée du silo : (moteur à impulsion × 2) − 1 systèmes. Fonction pure. */
    public static function range(int $impulseTech): int
    {
        return ($impulseTech * 2) - 1;
    }

    /**
     * Le tir est-il autorisé depuis cette planète vers ce système ? Fonction pure :
     * silo de niveau suffisant, moteur à impulsion, même galaxie, portée respectée.
     *
     * @param array<string, mixed> $planet
     * @param array<string, mixed> $user
     */
    public static function launchAllowed(array $planet, array $user, int $galaxy, int $system): bool
    {
        if ((int) ($planet['silo'] ?? 0) < self::SILO_LEVEL) {
            return false;
        }

        $range = self::range((int) ($user['impulse_motor_tech'] ?? 0));

        if ($range < 1 || $galaxy !== (int) ($planet['galaxy'] ?? 0)) {
            return false;
        }

        return abs($system - (int) ($planet['system'] ?? 0)) <= $range;
    }

    /** Durée de vol d'une salve, en secondes (formule historique). Fonction pure. */
    public static function flightTime(int $fromSystem, int $toSystem, int $gameSpeed): int
    {
        // Une salve reste toujours en vol au moins une seconde : à vitesse ×1000 la
        // formule historique tombe sous la seconde et la salve partirait et
        // frapperait dans le même affichage de page.
        return max(1, (int) round(((30 + (60 * abs($toSystem - $fromSystem))) * 2500) / max(1, $gameSpeed)));
    }

    /**
     * Motif de refus d'un tir, ou null si la demande est acceptable. Fonction pure :
     * silo, portée, cible existante (ligne `planets` déjà lue, `false` si absente) et
     * stock disponible. C'est la **seule** validation du tir — les deux pages qui
     * tirent (galaxie et /game/mipattack) s'en servent, elles ne font que choisir
     * leur rendu.
     *
     * @param array<string, mixed>       $user
     * @param array<string, mixed>       $planet planète d'où part la salve
     * @param array<string, mixed>|false $target planète visé, `false` s'il n'existe plus
     */
    public static function refusal(
        array $user,
        array $planet,
        array|false $target,
        int $galaxy,
        int $system,
        int $missiles
    ): ?string {
        if ((int) ($planet['silo'] ?? 0) < self::SILO_LEVEL) {
            return 'Le silo de missiles doit &ecirc;tre au niveau ' . self::SILO_LEVEL . '.';
        }

        if (!self::launchAllowed($planet, $user, $galaxy, $system)) {
            return 'Ces coordonn&eacute;es sont hors de port&eacute;e : il faut un moteur &agrave; impulsion plus avanc&eacute;, et la m&ecirc;me galaxie.';
        }

        if ($target === false) {
            return 'Aucune planète existante &agrave; ces coordonn&eacute;es.';
        }

        $available = (int) ($planet['interplanetary_misil'] ?? 0);

        if ($missiles < 1 || $missiles > $available) {
            return 'Vous ne poss&eacute;dez pas assez de missiles interplan&eacute;taires (' . $available . ' en silo).';
        }

        return null;
    }

    /**
     * Enregistre le tir : une salve de `$missiles` vers la position visée, et le
     * débit du stock. Les vérifications sont faites **avant** par `refusal()` :
     * aucun missile ne part si le tir est refusé.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $planet planète d'où partent les missiles
     * @param array<string, mixed> $target ligne `planets` visée (planète existant)
     */
    public function launch(array $user, array $planet, array $target, int $missiles, int $primary, int $gameSpeed): void
    {
        $departure = time();

        $this->fleets->insertMissileStrike(array(
            'owner' => (int) $user['id'],
            'from' => array((int) $planet['galaxy'], (int) $planet['system'], (int) $planet['planet']),
            'to' => array((int) $target['galaxy'], (int) $target['system'], (int) $target['planet']),
            'targetOwner' => (int) $target['id_owner'],
            'targetType' => (int) ($target['planet_type'] ?? 1),
            'missiles' => $missiles,
            'departure' => $departure,
            'impact' => $departure + self::flightTime((int) $planet['system'], (int) $target['system'], $gameSpeed),
            'primary' => $primary,
        ));

        // Le stock part avec la salve : un tir ne se rejoue pas.
        $this->planets->decrementField((int) $planet['id'], GameData::resource()[503], $missiles);
    }

    /**
     * Résout une salve arrivée : dégâts aux défenses, rapport au défenseur, puis
     * fin de la mission (la ligne disparaît — un tir ne rentre pas).
     *
     * @param array<string, mixed> $fleetRow ligne `fleets` de la mission 11
     */
    public function strike(array $fleetRow): void
    {
        $fleet = Fleet::fromRow($fleetRow);

        // Le handler sélectionne aussi un vol dont le **départ** est passé : une
        // salve encore en route n'est pas frappée, son échéance est son impact.
        if ($fleet->arrivalTime() > time()) {
            return;
        }

        if ($fleet->amount() < 1) {
            $this->missions->deleteFleet($fleet->id());

            return;
        }

        $this->applyStrike($fleetRow);
    }

    /**
     * Applique une salve : interception, dégâts aux défenses, rapport au défenseur.
     *
     * Reprend le corps de l'ancien traitement (table `missiles`) à l'identique :
     * seules les lectures changent de source — la ligne de vol remplace la ligne
     * de tir, et les planètes passent par les dépôts habituels.
     *
     * @param array<string, mixed> $fleetRow
     */
    private function applyStrike(array $fleetRow): void
    {
        $resource = GameData::resource();
        $fleet = Fleet::fromRow($fleetRow);

        $planet = $this->planets->findByCoords($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet(), $fleet->endType());
        $defender = $this->users->findFullById($fleet->targetOwner());
        $attacker = $this->users->findFullById($fleet->ownerId());

        // Planète disparu entre le tir et l'impact : la salve se perd.
        if ($planet === false || $defender === false || $attacker === false) {
            $this->missions->deleteFleet($fleet->id());

            return;
        }

        $amount = $fleet->amount();

        // Défenses de la cible, dans l'ordre historique du calcul : 401 à 408, puis
        // les missiles interplanétaires (503), puis les intercepteurs (502). C'est
        // l'ordre attendu par `MissileStrike`, et `DEFENCE_IDS` relit son résultat
        // avec les mêmes index.
        $def = array(
            0 => $planet[$resource[401]],
            1 => $planet[$resource[402]],
            2 => $planet[$resource[403]],
            3 => $planet[$resource[404]],
            4 => $planet[$resource[405]],
            5 => $planet[$resource[406]],
            6 => $planet[$resource[407]],
            7 => $planet[$resource[408]],
            8 => $planet[$resource[503]],
            9 => $planet[$resource[502]],
        );

        // Libellés du rapport, repris tels quels : l'inversion 502/503 est celle de
        // l'ancien traitement, le joueur voyait déjà ces deux lignes ainsi.
        $lang = array(
            0 => "Lanceur Missile",
            1 => "Canon Magn&eacute;tique",
            2 => "Batterie Electromagn&eacute;tique",
            3 => "Canon de Gauss",
            4 => "Lanceur Ionique",
            5 => "Lanceur de plasma",
            6 => "Petit bouclier",
            7 => "Grand bouclier",
            8 => "Missiles Intercepteur",
            9 => "Missiles Interplanetaire",
            10 => "Missiles Intercepteur"
        );

        // `primaer` de la salve : l'index attendu par la règle des missiles.
        $result = MissileStrike::resolve(
            (int) $defender['defence_tech'],
            (int) $attacker['military_tech'],
            $amount,
            $def,
            (int) ($fleetRow['fleet_primary'] ?? 0)
        );

        $message = '';

        if ($planet[$resource[502]] >= $amount) {
            $message = 'Les Missiles Intercepteur adverses ont d&eacute;truit vos missiles Interplanetaire<br>';
            $this->planets->decrementField((int) $planet['id'], $resource[502], $amount);
        } else {
            if ($planet[$resource[502]] > 0) {
                $message = $planet[$resource[502]] . " missiles Interplanetaire ont &eacute;t&eacute; intercept&eacute;s par vos missiles.<br>";
                $this->planets->setField((int) $planet['id'], $resource[502], 0);
            }

            foreach ($result['destroyed'] as $id => $destroyed) {
                if (!empty($destroyed) && $id < 10) {
                    if ($id != 9) {
                        $message .= $lang[$id] . " (- " . $destroyed . ")<br>";
                    }

                    $this->planets->decrementField((int) $planet['id'], $resource[self::DEFENCE_IDS[$id]], (int) $destroyed);
                }
            }
        }

        $name = $fleet->startCoordinates();
        $nameDefender = (string) $planet['name'];

        $template = 'Une attaque de missiles (' . $amount . ') de ' . $name
            . ' <a href="galaxy.php?mode=3&galaxy=' . $fleet->startGalaxy() . '&system=' . $fleet->startSystem() . '&planet=' . $fleet->startPlanet() . '">[' . $fleet->startCoordinates() . ']</a>';
        $template .= 'de la planete ' . $nameDefender
            . ' <a href="galaxy.php?mode=3&galaxy=' . $fleet->endGalaxy() . '&system=' . $fleet->endSystem() . '&planet=' . $fleet->endPlanet() . '">[' . $fleet->endCoordinates() . ']</a><br><br>';

        if (empty($message)) {
            $message = "L ennemis ne possedait pas de d&eacute;fenses, rien n a &eacute;t&eacute; d&eacute;truit !";
        }

        $this->messages->insertMessage(Message::new(
            $fleet->targetOwner(),
            0,
            0,
            'QG',
            'Attaque de MIP',
            $template . $message
        ));
        $this->users->incrementNewMessage($fleet->targetOwner());

        // Un tir ne rentre pas : la mission se termine avec l'impact.
        $this->missions->deleteFleet($fleet->id());
    }
}
