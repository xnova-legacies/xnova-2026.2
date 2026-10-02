<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Api\ApiException;
use App\Core\GameConstants;
use App\Core\GameData;
use App\Database\Connection;

/**
 * File de recherche du laboratoire (page /game/buildings?mode=research et API).
 *
 * Reprend la logique de ResearchBuildingPage() (cmd=search / cmd=cancel), mais
 * avec une file comme pour les bâtiments : les coûts sont débités à l'ajout dans
 * la file, remboursés à l'interruption, et l'enchaînement (b_tech / b_tech_id sur
 * la planète, b_tech_planet / b_tech_queue sur l'utilisateur) est tenu par
 * QueueService.
 */
final class ResearchService
{
    /** Champ de la table planets portant le niveau du laboratoire. */
    private const LAB_FIELD = 'laboratory';

    private readonly QueueService $queues;

    public function __construct()
    {
        $this->queues = new QueueService();
    }

    /**
     * La file peut-elle encore accueillir une recherche ? (fonction pure)
     *
     * La longueur vient de `QueueService::researchEntries()` : la règle est ici,
     * à côté de celle du lancement, et testable sans base.
     */
    public static function queueHasRoom(int $queueLength): bool
    {
        return $queueLength < GameConstants::maxTechnologyQueueSize();
    }

    /**
     * Ajoute une recherche à la file. Les ressources sont débitées tout de suite,
     * la première recherche de la file démarre immédiatement.
     */
    public function start(array &$user, array &$planet, int $element): void
    {
        if (!in_array($element, GameData::resList()['tech'], true)) {
            throw ApiException::validation('invalid_element', 'Technologie inconnue.');
        }

        if ((int) ($planet[self::LAB_FIELD] ?? 0) === 0) {
            throw ApiException::validation(
                'no_laboratory',
                'Aucun laboratoire sur cette planète : la recherche y est impossible.'
            );
        }

        if (!CheckLabSettingsInQueue($planet)) {
            throw ApiException::validation(
                'laboratory_upgrading',
                'Le laboratoire est en cours d\'amélioration : la recherche est bloquée.'
            );
        }

        // La file est portée par le compte (une recherche travaille, les autres
        // attendent) : elle se relit avant de débiter la moindre ressource.
        $this->queues->refreshResearchQueue($user);

        if (!self::queueHasRoom(count($this->queues->researchEntries($user, $planet)))) {
            throw ApiException::validation('queue_full', 'La file de recherche est pleine.');
        }

        if (!IsTechnologieAccessible($user, $planet, $element)) {
            throw ApiException::validation(
                'requirements_not_met',
                'Les prérequis de cette technologie ne sont pas satisfaits.'
            );
        }

        if (!IsElementBuyable($user, $planet, $element)) {
            throw ApiException::validation(
                'not_enough_resources',
                'Ressources insuffisantes pour lancer cette recherche.'
            );
        }

        $costs = GetBuildingPrice($user, $planet, $element);

        $planet['metal'] = (float) $planet['metal'] - $costs['metal'];
        $planet['crystal'] = (float) $planet['crystal'] - $costs['crystal'];
        $planet['deuterium'] = (float) $planet['deuterium'] - $costs['deuterium'];

        // Le niveau visé dépend de la file : on la relit avant de décider, sinon
        // deux ajouts rapprochés du même élément visaient le même niveau.
        $this->queues->refreshResearchQueue($user);

        $level = $this->queues->researchNextLevel($user, $planet, $element);

        // La durée est celle du niveau qui sera atteint : une recherche du même
        // élément déjà dans la file démarre au niveau suivant.
        $this->queues->appendResearch($user, $planet, array(
            'element' => $element,
            'level' => $level,
            'duration' => GetBuildingTimeLevel($user, $planet, $element, $level),
            'mode' => 'research',
        ));

        $this->persistResources($planet);
    }

    /**
     * Interrompt la recherche en cours : les ressources du niveau engagé sont
     * remboursées sur la planète qui la faisait tourner, puis la recherche
     * suivante démarre — et elle démarre maintenant, pas à la fin de celle qui
     * vient d'être annulée (d'où le recalcul de la file).
     */
    public function cancel(array &$user, array &$planet, int $element): void
    {
        $this->queues->refreshResearchQueue($user);

        // Le contrôle porte sur la file du joueur (elle est globale), pas sur le
        // miroir de la planète active : une recherche lancée sur une colonie doit
        // pouvoir être annulée depuis n'importe quelle planète.
        $entries = $this->queues->researchEntries($user, $planet);

        if ($entries === array()) {
            throw ApiException::validation('nothing_to_cancel', 'Aucune recherche en cours.');
        }

        if ((int) $entries[0]['element'] !== $element) {
            throw ApiException::validation(
                'unknown_research',
                'Cette technologie n\'est pas celle en cours de recherche.'
            );
        }

        // Le remboursement va sur la planète qui a payé le niveau engagé.
        $target = $planet;
        $runningPlanet = $this->queues->researchPlanetId($user);

        if ($runningPlanet !== 0 && $runningPlanet !== (int) ($planet['id'] ?? 0)) {
            $target = $this->queues->findPlanet($runningPlanet) ?? $planet;
        }

        $costs = GetBuildingPrice($user, $target, $element);

        $target['metal'] = (float) $target['metal'] + $costs['metal'];
        $target['crystal'] = (float) $target['crystal'] + $costs['crystal'];
        $target['deuterium'] = (float) $target['deuterium'] + $costs['deuterium'];

        // La file réécrit b_tech / b_tech_id / b_tech_planet et démarre la suivante.
        $this->queues->advanceResearch($user, $target, true);

        $this->persistResources($target);
    }

    private function persistResources(array $planet): void
    {
        Connection::preparedExecute(
            "UPDATE {{table}} SET metal = ?, crystal = ?, deuterium = ? WHERE id = ?",
            array(
                (float) $planet['metal'],
                (float) $planet['crystal'],
                (float) $planet['deuterium'],
                (int) $planet['id'],
            ),
            'planets'
        );
    }
}
