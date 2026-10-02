<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Api\ApiException;
use App\Core\GameConstants;
use App\Core\GameData;

/**
 * Actions sur la file de construction des bâtiments (page /game/buildings).
 *
 * Le service s'appuie volontairement sur les primitives legacy
 * (AddBuildingToQueue, SetNextQueueElementOnTop, CancelBuildingFromQueue,
 * RemoveBuildingFromQueue, BuildingSave*Record) : ce sont exactement celles
 * utilisées par la page HTML, donc les règles du jeu ne peuvent pas diverger
 * entre le POST classique et l'API JSON.
 *
 * Les garde-fous « purs » (éléments autorisés, taille de file, place
 * disponible) sont exposés séparément afin d'être testables sans base de données.
 *
 * Les erreurs métier sont signalées par ApiException, convertie en réponse JSON
 * par App\Core\Api\ApiController::handle().
 */
final class BuildingQueueService
{
    /** Bâtiments constructibles par type de planète (cf. BatimentBuildingPage). */
    private const ALLOWED_BY_PLANET_TYPE = array(
        1 => array(1, 2, 3, 4, 12, 14, 15, 16, 21, 22, 23, 24, 31, 33, 34, 44),
        3 => array(12, 14, 21, 22, 23, 24, 34, 41, 42, 43),
    );

    /**
     * @return list<int>
     */
    public function allowedElements(int $planetType): array
    {
        return self::ALLOWED_BY_PLANET_TYPE[$planetType] ?? array();
    }

    public function isAllowed(int $planetType, int $element): bool
    {
        return in_array($element, $this->allowedElements($planetType), true);
    }

    /** Nombre d'éléments en file ("0" signifie file vide). */
    public function queueLength(array $planet): int
    {
        return count($this->queueEntries($planet));
    }

    public function isQueueFull(array $planet): bool
    {
        return $this->queueLength($planet) >= GameConstants::maxBuildingQueueSize();
    }

    /** Reste-t-il un champ libre, une fois la file existante prise en compte ? */
    public function hasRoom(array $planet, ?int $queueLength = null): bool
    {
        $queueLength ??= $this->queueLength($planet);
        $maxFields = CalculateMaxPlanetFields($planet);

        return (int) ($planet['field_current'] ?? 0) < ($maxFields - $queueLength);
    }

    public function queueContains(array $planet, int $element): bool
    {
        foreach ($this->queueEntries($planet) as $entry) {
            if ((int) ($entry[0] ?? 0) === $element) {
                return true;
            }
        }

        return false;
    }

    /**
     * Le joueur/la planète peut-il payer le prochain niveau de cet élément ?
     *
     * Équivalent local de IsElementBuyable() sans requête : l'affichage temps
     * réel recalcule cette information à chaque synchronisation, une requête par
     * élément serait trop coûteuse. Les règles restent celles du jeu (prix de
     * base x facteur^niveau, mode vacances exclu).
     */
    public function isAffordable(array $user, array $planet, int $element): bool
    {
        if (($user['vacation_mode'] ?? 0) == 1) {
            return false;
        }

        $resource = GameData::resource();
        $pricelist = GameData::priceList();
        $field = $resource[$element] ?? null;

        if ($field === null || !isset($pricelist[$element])) {
            return false;
        }

        $level = (int) ($planet[$field] ?? 0) ?: (int) ($user[$field] ?? 0);
        $factor = (float) ($pricelist[$element]['factor'] ?? 1);

        foreach (array('metal', 'crystal', 'deuterium') as $type) {
            $base = (float) ($pricelist[$element][$type] ?? 0);

            if ($base == 0.0) {
                continue;
            }

            if (floor($base * pow($factor, $level)) > (float) ($planet[$type] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ajoute un bâtiment (ou lance sa destruction) puis démarre l'élément de
     * tête si aucun chantier n'est en cours.
     *
     * @return array{dropped: bool} dropped = l'élément a été retiré faute de ressources
     */
    public function add(array $user, array &$planet, int $element, bool $destroy = false): array
    {
        $this->refreshQueue($planet);

        $planetType = (int) ($planet['planet_type'] ?? 1);

        if (!$this->isAllowed($planetType, $element)) {
            throw ApiException::validation(
                'element_not_allowed',
                'Ce bâtiment n\'est pas constructible sur ce type de planète.'
            );
        }

        $queueLength = $this->queueLength($planet);

        if ($queueLength >= GameConstants::maxBuildingQueueSize()) {
            throw ApiException::validation('queue_full', 'La file de construction est pleine.');
        }

        if (!$this->hasRoom($planet, $queueLength)) {
            throw ApiException::validation('no_room', 'Plus assez de champs disponibles sur la planète.');
        }

        if (!$destroy && !IsTechnologieAccessible($user, $planet, $element)) {
            throw ApiException::validation(
                'requirements_not_met',
                'Les prérequis de ce bâtiment ne sont pas satisfaits.'
            );
        }

        // Legacy : seul le premier élément de la file doit être payable ; les
        // suivants ne sont vérifiés qu'en arrivant en tête (SetNextQueueElementOnTop).
        if ($queueLength === 0 && !IsElementBuyable($user, $planet, $element, true, $destroy)) {
            throw ApiException::validation(
                'not_enough_resources',
                'Ressources insuffisantes pour lancer ce chantier.'
            );
        }

        AddBuildingToQueue($planet, $user, $element, !$destroy);
        SetNextQueueElementOnTop($planet, $user);
        $this->persist($planet, $user);

        // L'élément a disparu de la file sans chantier démarré : la tête n'était
        // pas payable, le legacy l'a retiré (il prévient normalement par message).
        $dropped = (int) ($planet['b_building'] ?? 0) === 0
            && !$this->queueContains($planet, $element);

        return array('dropped' => $dropped);
    }

    /** Interrompt le chantier en cours (premier de la file) et rembourse. */
    public function cancel(array $user, array &$planet): bool
    {
        $this->refreshQueue($planet);

        if ($this->queueLength($planet) === 0) {
            throw ApiException::validation('queue_empty', 'Aucun chantier à interrompre.');
        }

        $cancelled = CancelBuildingFromQueue($planet, $user);
        $this->persist($planet, $user);

        return (bool) $cancelled;
    }

    /** Retire un élément de la file (le premier s'interrompt, il ne se supprime pas). */
    public function remove(array $user, array &$planet, int $position): bool
    {
        $this->refreshQueue($planet);

        $length = $this->queueLength($planet);

        if ($position <= 1 || $position > $length) {
            throw ApiException::validation(
                'invalid_position',
                'Position invalide : le premier chantier s\'interrompt, il ne se supprime pas.'
            );
        }

        RemoveBuildingFromQueue($planet, $user, $position);
        $this->persist($planet, $user);

        return true;
    }

    /**
     * Liste les entrées de la file : "element,level,buildTime,endTime,mode".
     *
     * @return list<array<int, string>>
     */
    private function queueEntries(array $planet): array
    {
        $queue = (string) ($planet['b_building_id'] ?? '0');
        $entries = array();

        foreach (explode(';', $queue) as $entry) {
            if ($entry === '' || $entry === '0') {
                continue;
            }

            $entries[] = explode(',', $entry);
        }

        return $entries;
    }

    private function persist(array &$planet, array &$user): void
    {
        // Seule la planete change ici (file, ressources) : ce qui touche au compte —
        // l'experience des chantiers — s'ecrit quand un chantier **se termine**
        // (`BuildingService::constructionRewards()`), jamais a l'ajout ou au retrait.
        BuildingSavePlanetRecord($planet);
    }

    /**
     * Relit la file de construction avant de la modifier.
     *
     * L'action du joueur part d'une copie de la planète chargée en début de
     * requête : la synchronisation temps réel peut avoir terminé un chantier
     * entre-temps (les durées de ce serveur de test tombent sous la seconde), et
     * l'écriture qui suivait écrasait alors ce changement : l'élément ajouté
     * disparaissait de la file, l'interruption semblait sans effet.
     */
    private function refreshQueue(array &$planet): void
    {
        $state = (new \App\Repositories\BuildingQueueRepository())->findQueueState((int) ($planet['id'] ?? 0));

        if ($state === null) {
            return;
        }

        $planet['b_building'] = $state['b_building'];
        $planet['b_building_id'] = $state['b_building_id'];
    }
}
