<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Acl;
use App\Core\Flags;
use App\Core\Format;
use App\Core\GameConstants;
use App\Core\GameData;
use App\Repositories\FleetRepository;
use App\Repositories\GalaxyRepository;
use App\Repositories\MissionRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;

/**
 * Gestion d'un joueur depuis le panneau d'administration.
 *
 * Réunit ce qui était dispersé : l'état du compte (bannir, débannir, supprimer,
 * rétablir), ses planètes et lunes (ressources, champs, débris, éléments, lune) et
 * ses flottes (rappel, retrait de vaisseaux, suppression).
 *
 * Les règles de bannissement restent dans `BanService`, le rappel d'une flotte
 * dans `FleetService::recall()`, la création d'une lune dans la primitive legacy
 * `CreateOneMoonRecord()` : ici, on ne fait que les appeler, avec les bornes de
 * saisie et les garde-fous propres à l'administration.
 */
final class PlayerAdminService
{
    /** Catégories d'éléments modifiables depuis la fiche (le laboratoire est à part). */
    public const ELEMENT_CATEGORIES = array('build', 'fleet', 'defense');

    /** Onglets de la gestion des éléments : les bâtiments, les recherches, puis les unités. */
    public const ELEMENT_TABS = array('build', 'tech', 'fleet', 'defense');

    /** Catégorie des recherches (les niveaux vivent sur le compte, pas sur la planète). */
    public const TECH_CATEGORY = 'tech';

    /** Plafond d'un niveau de recherche modifié depuis le panneau. */
    public const MAX_TECH_LEVEL = 60;

    /** Plafond d'une quantité saisie (vaisseaux, défenses, ressources). */
    public const MAX_QUANTITY = 1000000000;

    /** Plafond de champs d'une planète. */
    public const MAX_FIELDS = 5000;

    /** Plafond d'un niveau de bâtiment modifié depuis le panneau. */
    public const MAX_LEVEL = 60;

    /** Longueur du motif d'un bannissement (colonne `banned.theme`). */
    public const MAX_REASON = 200;

    /** Longueur du nom d'une lune (colonne `lunas.name` : `varchar(11)`). */
    public const MAX_MOON_NAME = 11;

    /** Une lune posée depuis le panneau est toujours créée. */
    public const MOON_CHANCE = 100;

    /** Plafond du diamètre d'une planète (le jeu en distribue quelques centaines). */
    /**
     * Plafond du diamètre d'une planète. Le jeu en attribue de l'ordre de dix à
     * vingt mille : la borne ne doit pas écraser une valeur légitime, elle
     * empêche seulement une saisie absurde.
     */
    public const MAX_DIAMETER = 1000000;

    /** Missions qui attaquent une position : attaque, groupée, destruction de lune. */
    public const ATTACK_MISSIONS = array(1, 2, 9);

    /** Une flotte posée sur la position y est arrivée : `fleet_mess` vaut 2. */
    public const PARKED_MESS = 2;

    /** Motifs de refus d'un déplacement ('' = le déplacement est possible). */
    public const MOVE_OK = '';
    public const MOVE_SAME = 'same';
    public const MOVE_BOUNDS = 'bounds';
    public const MOVE_OCCUPIED = 'occupied';
    public const MOVE_ATTACK = 'attack';
    public const MOVE_PARKED = 'parked';

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly GalaxyRepository $galaxy = new GalaxyRepository(),
        private readonly MissionRepository $missions = new MissionRepository(),
        private readonly SessionRepository $sessions = new SessionRepository(),
        private readonly BanService $bans = new BanService(),
        private readonly FleetRepository $fleets = new FleetRepository(),
    ) {
    }

    // ------------------------------------------------------------ règles pures

    /** Quantité saisie, bornée (jamais négative, jamais au-delà du plafond). */
    public static function quantity(?string $value, int $max = self::MAX_QUANTITY): int
    {
        $text = trim((string) $value);
        $number = is_numeric($text) ? (int) $text : 0;

        return max(0, min($number, $max));
    }

    /** Déplacement de ressource : le signe compte, la valeur est bornée. */
    public static function amount(?string $value): int
    {
        $text = trim((string) $value);
        $number = is_numeric($text) ? (int) $text : 0;

        return max(-self::MAX_QUANTITY, min($number, self::MAX_QUANTITY));
    }

    /**
     * Montant réellement appliqué : on ne retire pas plus qu'il n'y a.
     *
     * Les colonnes de ressources sont non signées : un retrait supérieur au stock
     * ferait échouer l'écriture au lieu de mettre la planète à zéro.
     */
    public static function bounded(int $amount, float $available): int
    {
        if ($amount >= 0) {
            return $amount;
        }

        return -(int) min((float) abs($amount), max(0.0, $available));
    }

    /**
     * Diamètre saisi, borné (0 = champ laissé vide, donc rien à changer).
     */
    public static function diameter(?string $value): int
    {
        $text = trim((string) $value);
        $number = is_numeric($text) ? (int) $text : 0;

        return $number <= 0 ? 0 : min($number, self::MAX_DIAMETER);
    }

    /** Coordonnée saisie (0 = case vide ou saisie illisible). */
    public static function coordinate(?string $value): int
    {
        $text = trim((string) $value);
        $number = is_numeric($text) ? (int) $text : 0;

        return max(0, $number);
    }

    /** La position demandée appartient-elle à l'univers ? */
    public static function inWorld(
        int $galaxy,
        int $system,
        int $position,
        int $maxGalaxy,
        int $maxSystem,
        int $maxPosition
    ): bool {
        return $galaxy >= 1 && $galaxy <= $maxGalaxy
            && $system >= 1 && $system <= $maxSystem
            && $position >= 1 && $position <= $maxPosition;
    }

    /**
     * Une flotte occupe-t-elle la position ?
     *
     * Mission de stationnement ou de transfert arrivée (`fleet_mess = 2`) : les
     * vaisseaux sont posés là, déplacer la position les laisserait au-dessus du
     * vide.
     *
     * @param list<array<string, mixed>> $fleets flottes qui visent la position
     */
    public static function parkedFleet(array $fleets): bool
    {
        foreach ($fleets as $fleet) {
            if ((int) ($fleet['fleet_mess'] ?? 0) === self::PARKED_MESS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Une attaque vise-t-elle la position ?
     *
     * Flotte d'un autre compte, en route (`fleet_mess = 0`) et portant une mission
     * d'attaque, ou missiles encore en vol. Une flotte de retour, un transport ou
     * une expédition d'un autre compte ne bloquent pas le déplacement : leurs
     * coordonnées suivent la position.
     *
     * @param list<array<string, mixed>> $fleets   flottes qui visent la position
     * @param list<array<string, mixed>> $missiles missiles encore en vol
     */
    public static function incomingAttack(array $fleets, array $missiles, int $ownerId): bool
    {
        if ($missiles !== array()) {
            return true;
        }

        foreach ($fleets as $fleet) {
            if ((int) ($fleet['fleet_owner'] ?? 0) === $ownerId) {
                continue;
            }

            if ((int) ($fleet['fleet_mess'] ?? 0) !== 0) {
                continue;
            }

            if (in_array((int) ($fleet['fleet_mission'] ?? 0), self::ATTACK_MISSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Décision pure d'un déplacement de position.
     *
     * L'ordre des refus est celui du bon sens : une position identique ou hors de
     * l'univers se refuse avant même de regarder ce qui s'y trouve.
     *
     * @param array{same: bool, in_world: bool, occupied: bool, owner: int,
     *              fleets: list<array<string, mixed>>, missiles: list<array<string, mixed>>} $context
     *
     * @return string '' si le déplacement est possible, sinon la clé du refus
     */
    public static function moveRefusal(array $context): string
    {
        $fleets = (array) ($context['fleets'] ?? array());

        if ((bool) ($context['same'] ?? false)) {
            return self::MOVE_SAME;
        }

        if (!(bool) ($context['in_world'] ?? false)) {
            return self::MOVE_BOUNDS;
        }

        if ((bool) ($context['occupied'] ?? false)) {
            return self::MOVE_OCCUPIED;
        }

        if (self::incomingAttack($fleets, (array) ($context['missiles'] ?? array()), (int) ($context['owner'] ?? 0))) {
            return self::MOVE_ATTACK;
        }

        if (self::parkedFleet($fleets)) {
            return self::MOVE_PARKED;
        }

        return self::MOVE_OK;
    }

    /**
     * Éléments d'une catégorie, avec leur valeur actuelle, pour un tableau.
     *
     * Seuls les éléments dont la colonne existe réellement dans la ligne proposée
     * sont listés : une recherche se lit sur le compte, un bâtiment sur la planète,
     * et le panneau n'invente jamais une colonne.
     *
     * @param array<int|string, mixed> $resList
     * @param array<int|string, mixed> $resource
     * @param array<int|string, mixed> $tech
     * @param array<string, mixed>     $values  ligne qui porte les colonnes (planète ou compte)
     * @return list<array{id: int, label: string, value: int}>
     */
    public static function elementRows(array $resList, array $resource, array $tech, string $category, array $values): array
    {
        $items = array();

        foreach ((array) ($resList[$category] ?? array()) as $id) {
            $id = (int) $id;
            $column = (string) ($resource[$id] ?? '');

            if ($column === '' || !isset($tech[$id]) || !array_key_exists($column, $values)) {
                continue;
            }

            $items[] = array(
                'id' => $id,
                'label' => (string) $tech[$id] . ' (' . $id . ')',
                'value' => (int) ($values[$column] ?? 0),
            );
        }

        return $items;
    }

    /**
     * Variation saisie pour un élément : le signe porte le sens.
     *
     * Négatif = on retire, zéro = on ne touche à rien, positif = on ajoute.
     */
    public static function delta(?string $value): int
    {
        $text = trim((string) $value);
        $number = is_numeric($text) ? (int) $text : 0;

        return max(-self::MAX_QUANTITY, min($number, self::MAX_QUANTITY));
    }

    /**
     * Variations saisies dans un formulaire (`delta[401] = -5`).
     *
     * Les cases vides et les zéros sont écartés : ne rien saisir veut dire « ne
     * rien changer », pas « mettre à zéro ».
     *
     * @return array<int, int> identifiant d'élément => variation
     */
    public static function deltas(mixed $values): array
    {
        $deltas = array();

        foreach ((array) $values as $elementId => $value) {
            $delta = self::delta(is_string($value) ? $value : null);

            if ($delta !== 0) {
                $deltas[(int) $elementId] = $delta;
            }
        }

        return $deltas;
    }

    /**
     * Valeur retenue après variation : jamais négative, jamais au-dessus du
     * plafond, et jamais deux fois une unité unique (boucliers).
     */
    public static function elementValue(int $current, int $delta, int $max, bool $unique = false): int
    {
        $value = max(0, min($current + $delta, $max));

        return $unique ? min($value, 1) : $value;
    }

    /**
     * Applique une série de variations à une planète ou à un compte.
     *
     * Une seule écriture par élément réellement changé ; la méthode rend le nombre
     * de valeurs modifiées, ce qui permet à la page de dire ce qu'elle a fait.
     *
     * @param array<string, mixed> $row    ligne visée (planète, lune ou compte)
     * @param array<int, int>      $deltas identifiant d'élément => variation
     */
    public function changeElements(array $row, string $category, array $deltas): int
    {
        $resource = GameData::resource();
        $resList = GameData::resList();
        $ids = array_map('intval', (array) ($resList[$category] ?? array()));
        $isTech = $category === self::TECH_CATEGORY;
        $max = $isTech ? self::MAX_TECH_LEVEL : (in_array($category, array('build'), true) ? self::MAX_LEVEL : self::MAX_QUANTITY);
        $changed = 0;

        foreach ($deltas as $elementId => $delta) {
            $elementId = (int) $elementId;
            $column = (string) ($resource[$elementId] ?? '');

            if ($delta === 0 || !in_array($elementId, $ids, true) || $column === '' || !array_key_exists($column, $row)) {
                continue;
            }

            $current = (int) ($row[$column] ?? 0);
            $value = self::elementValue(
                $current,
                $delta,
                $max,
                in_array($elementId, GameData::uniqueUnits(), true)
            );

            if ($value === $current) {
                continue;
            }

            if ($isTech) {
                $this->users->setColumn((int) $row['id'], $column, $value);
            } else {
                $this->planets->setField((int) $row['id'], $column, $value);
            }

            $changed++;
        }

        return $changed;
    }

    /**
     * Nouveau contenu d'une flotte après retrait d'unités.
     *
     * Une unité absente de la flotte est ignorée et le retrait ne descend pas
     * sous zéro : c'est l'état final qui compte, pas la saisie.
     *
     * @param array<int, int> $units contenu actuel (id => quantité)
     * @param array<int, int> $wanted unités à retirer (id => quantité)
     * @return array{array: string, amount: int, empty: bool}
     */
    public static function removeUnits(array $units, array $wanted): array
    {
        $kept = array();
        $amount = 0;
        $parts = '';

        foreach ($units as $unitId => $quantity) {
            $left = max(0, (int) $quantity - max(0, (int) ($wanted[(int) $unitId] ?? 0)));

            if ($left <= 0) {
                continue;
            }

            $kept[(int) $unitId] = $left;
            $amount += $left;
            $parts .= (int) $unitId . ',' . $left . ';';
        }

        return array('array' => $parts, 'amount' => $amount, 'empty' => $kept === array());
    }

    // ------------------------------------------------------------- état du compte

    /**
     * Bannit un compte et le prévient par un message.
     *
     * Le motif et l'échéance sont annoncés au joueur : une sanction sans
     * explication n'apprend rien à personne.
     *
     * @param array<string, mixed> $target
     * @param array<string, mixed> $author
     * @param array<string, mixed> $lang
     */
    public function ban(array $target, string $reason, int $seconds, array $author, array $lang): int
    {
        // Le compte d'installation ne se bannit pas : c'est la porte de secours du
        // panneau (déjà super administrateur par définition).
        if (self::isProtected((int) $target['id'])) {
            return 0;
        }

        $until = $this->bans->ban($target, $reason, $seconds, $author);

        SendSimpleMessage(
            (int) $target['id'],
            '',
            time(),
            5,
            (string) ($lang['sys_mess_tower'] ?? ''),
            (string) ($lang['pal_msg_ban_subject'] ?? ''),
            sprintf(
                (string) ($lang['pal_msg_ban_text'] ?? ''),
                Format::escape($reason),
                gmdate('d/m/Y H:i', $until)
            )
        );

        return $until;
    }

    /** Lève un bannissement (le drapeau du compte et la ligne du pilori). */
    public function unban(string $username): void
    {
        $this->users->clearBan($username);
    }

    /**
     * Suppression logique : le compte reste en base, mais ne se connecte plus.
     *
     * Les connexions ouvertes sont refermées dans la foulée : sans cela, la vue
     * générale continuerait de l'afficher « en ligne » alors qu'il est écarté.
     */
    public function softDelete(int $userId): void
    {
        if (self::isProtected($userId)) {
            return;
        }

        $now = time();

        $this->users->softDelete($userId, $now);
        $this->sessions->closeOpenFor($userId, $now, 'deleted');
    }

    /**
     * Un compte protégé ? Le compte d'installation (`Acl::SUPER_ADMIN_ID`) ne se
     * bannit pas, ne se supprime pas et ne change pas de rôle : c'est la porte de
     * secours du panneau.
     */
    public static function isProtected(int $userId): bool
    {
        return $userId === Acl::SUPER_ADMIN_ID;
    }

    /** Rétablit un compte supprimé : ses données n'ont jamais bougé. */
    public function restore(int $userId): void
    {
        $this->users->restore($userId);
    }

    // -------------------------------------------------------- planètes et lunes

    /**
     * Vide les trois ressources d'une planète.
     *
     * @param array<string, mixed> $planet
     */
    public function clearResources(array $planet): bool
    {
        if ((float) ($planet['metal'] ?? 0) <= 0.0 && (float) ($planet['crystal'] ?? 0) <= 0.0 && (float) ($planet['deuterium'] ?? 0) <= 0.0) {
            return false;
        }

        $this->planets->updateResources((int) $planet['id'], 0, 0, 0);

        return true;
    }

    /**
     * Change le diamètre d'une planète (il décide du nombre de champs).
     *
     * @param array<string, mixed> $planet
     */
    public function setDiameter(array $planet, int $diameter): bool
    {
        if ($diameter <= 0 || $diameter === (int) ($planet['diameter'] ?? 0)) {
            return false;
        }

        $this->planets->setField((int) $planet['id'], 'diameter', $diameter);

        return true;
    }

    /**
     * Renomme une planète ou une lune.
     *
     * Le nom passe par la règle du jeu (`PlanetService::cleanName()`) : un nom
     * saisi dans le panneau doit donner le même résultat qu'un nom saisi par le
     * joueur, sans quoi le panneau deviendrait un moyen de poser des caractères
     * que le jeu refuse.
     *
     * @param array<string, mixed> $planet
     */
    public function rename(array $planet, string $name): bool
    {
        $clean = PlanetService::cleanName($name);

        if ($clean === '' || $clean === (string) ($planet['name'] ?? '')) {
            return false;
        }

        $service = new PlanetService();
        $service->rename($planet, $clean);

        return true;
    }

    /**
     * Applique un déplacement de ressources, borné à ce que la planète possède.
     *
     * @param array<string, mixed> $planet
     */
    public function changeResources(array $planet, int $metal, int $crystal, int $deuterium): bool
    {
        $metal = self::bounded($metal, (float) ($planet['metal'] ?? 0));
        $crystal = self::bounded($crystal, (float) ($planet['crystal'] ?? 0));
        $deuterium = self::bounded($deuterium, (float) ($planet['deuterium'] ?? 0));

        if ($metal === 0 && $crystal === 0 && $deuterium === 0) {
            return false;
        }

        $this->planets->addResources((int) $planet['id'], $metal, $crystal, $deuterium);

        return true;
    }

    /**
     * Fixe le nombre de champs d'une planète, jamais sous les champs occupés.
     *
     * @param array<string, mixed> $planet
     */
    public function setFields(array $planet, int $max): bool
    {
        $used = (int) ($planet['field_current'] ?? 0);
        $value = max($used, min($max, self::MAX_FIELDS));

        if ($value === (int) ($planet['field_max'] ?? 0)) {
            return false;
        }

        $this->planets->setField((int) $planet['id'], 'field_max', $value);

        return true;
    }

    /**
     * Vide le champ de débris de la position.
     *
     * Les colonnes du champ de débris sont celles de la table `galaxy`
     * (`metal`/`crystal`), celles-là mêmes qu'écrit le moteur quand une flotte
     * recycle ou qu'un combat laisse des restes.
     *
     * @param array<string, mixed> $planet
     */
    public function clearDebris(array $planet): bool
    {
        $row = $this->galaxy->findDebrisAt(
            (int) $planet['galaxy'],
            (int) $planet['system'],
            (int) $planet['planet']
        );

        if ($row === false) {
            return false;
        }

        $metal = (int) ($row['metal'] ?? 0);
        $crystal = (int) ($row['crystal'] ?? 0);

        if ($metal <= 0 && $crystal <= 0) {
            return false;
        }

        $this->missions->decrementGalaxyDebris(
            (int) $planet['galaxy'],
            (int) $planet['system'],
            (int) $planet['planet'],
            $metal,
            $crystal
        );

        return true;
    }

    /**
     * Déplace une planète (et sa lune) vers une autre position.
     *
     * Ce qui suit la position : la lune (ligne `planets` de type 3 et son entrée
     * au registre `lunas`), le champ de débris (`galaxy`) et **toutes les flottes
     * en vol** qui partent de la position ou qui la visent — sans quoi elles
     * rentreraient à une adresse vide. Aucun autre fichier ne référence une
     * position : le reste du jeu lit les coordonnées de la planète.
     *
     * Le déplacement est refusé si une attaque est en cours ou si une flotte est
     * posée sur la position (`moveRefusal()`), et si la position visée n'est pas
     * libre : on ne pose pas une planète sur une autre.
     *
     * @param array<string, mixed> $planet
     *
     * @return string motif de refus ('' si la position a été déplacée)
     */
    public function movePlanet(array $planet, int $galaxy, int $system, int $position): string
    {
        $from = array((int) $planet['galaxy'], (int) $planet['system'], (int) $planet['planet']);
        $targeting = $this->fleets->findTargetingPosition($from[0], $from[1], $from[2]);

        $refusal = self::moveRefusal(array(
            'same' => array($galaxy, $system, $position) === $from,
            'in_world' => self::inWorld(
                $galaxy,
                $system,
                $position,
                GameConstants::maxGalaxyInWorld(),
                GameConstants::maxSystemInGalaxy(),
                GameConstants::maxPlanetInSystem()
            ),
            'occupied' => $this->planets->findListByCoordsNoType($galaxy, $system, $position) !== array()
                || $this->galaxy->findPosition($galaxy, $system, $position) !== false,
            'owner' => (int) ($planet['id_owner'] ?? 0),
            'fleets' => $targeting,
            'missiles' => $this->fleets->findMissileStrikesToPosition($galaxy, $system, $position),
        ));

        if ($refusal !== self::MOVE_OK) {
            return $refusal;
        }

        $this->planets->movePosition((int) $planet['id'], $galaxy, $system, $position);
        $this->planets->moveMoonAt($from[0], $from[1], $from[2], $galaxy, $system, $position);
        $this->planets->moveMoonRegistry($from[0], $from[1], $from[2], $galaxy, $system, $position);
        $this->galaxy->movePosition($from[0], $from[1], $from[2], $galaxy, $system, $position);
        $this->fleets->movePositionReferences($from[0], $from[1], $from[2], $galaxy, $system, $position);

        return self::MOVE_OK;
    }

    /**
     * Pose une lune autour d'une planète (une seule par position).
     *
     * @param array<string, mixed> $planet
     * @param array<string, mixed> $lang
     */
    public function addMoon(array $planet, string $name, array $lang): bool
    {
        // La lune se lit sur la table des planètes (ligne de type 3) : le registre
        // `lunas` peut être incomplet, la position, non.
        if ($this->planets->findMoonPlanet($planet) !== false) {
            return false;
        }

        $name = mb_substr(trim($name), 0, self::MAX_MOON_NAME);

        CreateOneMoonRecord(
            (int) $planet['galaxy'],
            (int) $planet['system'],
            (int) $planet['planet'],
            (int) $planet['id_owner'],
            time(),
            $name !== '' ? $name : (string) ($lang['pal_moon_default'] ?? 'Lune'),
            self::MOON_CHANCE
        );

        return true;
    }

    /**
     * Détruit la lune d'une planète — **logiquement** : la ligne reste en base.
     *
     * Le geste est celui du jeu (la lune n'existe plus : plus de vue, plus de
     * ciblage, sa ligne ne se trouve plus par les lectures de lunes) mais rien
     * n'est perdu : le panneau peut la rétablir. Ce qui suit la destruction est
     * écrit dans le même geste : le registre `lunas` et le lien de la galaxie, qui
     * ne doivent plus annoncer de lune à cette position.
     *
     * @param array<string, mixed> $planet
     */
    public function deleteMoon(array $planet): bool
    {
        // Même règle que pour la création : c'est la ligne `planets` de type 3 qui
        // dit qu'une lune existe, pas le registre.
        if ($this->planets->findMoonPlanet($planet) === false) {
            return false;
        }

        $this->planets->markMoonDeletedAt(
            (int) $planet['galaxy'],
            (int) $planet['system'],
            (int) $planet['planet'],
            (int) $planet['id_owner']
        );
        $this->planets->markMoonRegistryDestroyed(
            (int) $planet['galaxy'],
            (int) $planet['system'],
            (int) $planet['planet']
        );
        $this->galaxy->clearMoonLink(
            (int) $planet['galaxy'],
            (int) $planet['system'],
            (int) $planet['planet']
        );

        // Le compte peut avoir sa vue posée sur la lune : sans cela, il continuerait
        // de jouer sur une planète qui n'existe plus.
        $this->users->relocateFromPlanet((int) $planet['id']);

        return true;
    }

    /**
     * Rétablit une colonie abandonnée logiquement.
     *
     * Refusé si une planète **existant** occupe la position (une colonie reposée depuis) :
     * sinon la ligne et son contenu reviennent simplement dans le jeu, chez son
     * ancien propriétaire (conservé par `markPlanetAbandoned()`).
     *
     * La méthode renvoie la **raison** du refus, sous la forme du suffixe de la clé de
     * langue à afficher (`''` = rétablie), comme `restoreMoon()`.
     *
     * @param array<string, mixed> $planet
     *
     * @return string 'no' (position occupée) ou '' (rétablie)
     */
    public function restorePlanet(array $planet): string
    {
        if (!Flags::isDeleted((int) ($planet['flags'] ?? 0))) {
            return 'no';
        }

        if ($this->planets->countLivePlanetsAt((int) $planet['galaxy'], (int) $planet['system'], (int) $planet['planet']) > 0) {
            return 'no';
        }

        $this->planets->restorePlanetAt(
            (int) $planet['galaxy'],
            (int) $planet['system'],
            (int) $planet['planet'],
            (int) $planet['id_owner']
        );
        $this->galaxy->linkPlanet(
            (int) $planet['galaxy'],
            (int) $planet['system'],
            (int) $planet['planet'],
            (int) $planet['id']
        );

        return '';
    }

    /**
     * Rétablit une lune détruite logiquement.
     *
     * Refusé si une nouvelle lune occupe la position : deux lunes au même endroit
     * casseraient la règle du jeu (une seule par position).
     *
     * La méthode renvoie la **raison** du refus, sous la forme du suffixe de la clé de
     * langue à afficher (`''` = rétablie) : une seule cause ne suffisait pas, puisque
     * l'absence de planète et la présence d'une lune exigent deux textes différents.
     *
     * @param array<string, mixed> $moon
     *
     * @return string 'no' (position occupée), 'planet' (planète absente) ou '' (rétablie)
     */
    public function restoreMoon(array $moon): string
    {
        if (!Flags::isDeleted((int) ($moon['flags'] ?? 0))) {
            return 'no';
        }

        if ($this->planets->countMoonsAt((int) $moon['galaxy'], (int) $moon['system'], (int) $moon['planet']) > 0) {
            return 'no';
        }

        // Une lune n'existe pas sans sa planète (abandonnée avec sa colonie) : il
        // faut d'abord rétablir la planète.
        if ($this->planets->countLivePlanetsAt((int) $moon['galaxy'], (int) $moon['system'], (int) $moon['planet']) === 0) {
            return 'planet';
        }

        // Le lien de la galaxie annonce l'identifiant du **registre**, comme la
        // création d'une lune. Le registre est purgé quelque temps après une
        // destruction : s'il n'y est plus, on le recrée, sinon la lune resterait
        // définitivement hors de portée.
        $registry = $this->planets->findMoonRegistryAt(
            (int) $moon['galaxy'],
            (int) $moon['system'],
            (int) $moon['planet']
        );

        if ($registry === false) {
            $registryId = $this->planets->insertMoonRegistry($moon);
        } else {
            $registryId = (int) ($registry['id'] ?? 0);
            $this->planets->restoreMoonRegistry(
                (int) $moon['galaxy'],
                (int) $moon['system'],
                (int) $moon['planet']
            );
        }

        $this->planets->restoreMoonAt(
            (int) $moon['galaxy'],
            (int) $moon['system'],
            (int) $moon['planet'],
            (int) $moon['id_owner']
        );
        $this->galaxy->linkMoon(
            (int) $moon['galaxy'],
            (int) $moon['system'],
            (int) $moon['planet'],
            $registryId
        );

        return '';
    }

    // ------------------------------------------------------------------ flottes

    /**
     * Rappelle une flotte (même règle que le bouton « Retour » du joueur).
     *
     * @param array<string, mixed> $fleet
     */
    public function recall(array $fleet, int $ownerId): bool
    {
        return (new FleetService())->recall((int) $fleet['fleet_id'], $ownerId);
    }

    /**
     * Retire des vaisseaux d'une flotte ; la flotte disparaît si elle se vide.
     *
     * @param array<string, mixed> $fleet
     * @param array<int, int> $wanted
     */
    public function removeFleetUnits(array $fleet, array $wanted): bool
    {
        $units = FlyingFleetService::parseUnits((string) $fleet['fleet_array']);
        $after = self::removeUnits($units, $wanted);

        if ($after['amount'] === array_sum($units)) {
            return false;
        }

        if ($after['empty']) {
            $this->missions->deleteFleet((int) $fleet['fleet_id']);

            return true;
        }

        $this->missions->setFleetUnits((int) $fleet['fleet_id'], $after['array'], $after['amount']);

        return true;
    }

    /**
     * Supprime un vol demandé par le panneau : la ligne reste en base.
     *
     * La suppression est **logique** (`Flags::DELETED`) : le vol n'arrivera plus
     * — le moteur filtre le drapeau — mais l'administrateur peut revenir sur son
     * geste. La consommation d'un vol par le moteur reste, elle, un vrai
     * `DELETE` : c'est la fin de la mission, pas un retrait.
     *
     * @param array<string, mixed> $fleet
     */
    public function deleteFleet(array $fleet): void
    {
        $this->missions->markDeleted((int) $fleet['fleet_id']);
    }

    /**
     * Rétablit un vol supprimé logiquement : il repart en mission.
     *
     * @param array<string, mixed> $fleet
     */
    public function restoreFleet(array $fleet): void
    {
        $this->missions->restore((int) $fleet['fleet_id']);
    }
}
