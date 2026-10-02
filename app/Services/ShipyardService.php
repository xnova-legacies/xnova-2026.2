<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\GameConstants;
use App\Core\GameData;

/**
 * Hangar (vaisseaux et défenses).
 *
 * Le hangar utilise les mêmes primitives que le jeu : la file commune
 * `b_hangar_id` est écrite par FleetBuildingPage() / DefensesBuildingPage(),
 * qui appliquent les règles (technologies, ressources, plafond par ligne).
 * Ce service ne fait que préparer l'entrée au format historique
 * (tableau `amounts[element] = quantite`).
 */
final class ShipyardService
{
    /** Types de hangar acceptés par l'API. */
    public const KINDS = array('fleet', 'defense');

    /** Le type demandé, ou null s'il est inconnu. Fonction pure. */
    public static function kind(mixed $value): ?string
    {
        $kind = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($kind, self::KINDS, true) ? $kind : null;
    }

    /**
     * Ne garde que les éléments réellement demandés, plafonnés à la limite par
     * ligne (mêmes bornes que le formulaire historique). Fonction pure.
     *
     * @param array<string|int, mixed> $units
     * @return array<int, int>
     */
    public static function normalizeUnits(array $units, int $max = 0): array
    {
        // Une valeur par défaut ne peut pas appeler de méthode : on la résout ici.
        $max = $max > 0 ? $max : GameConstants::maxUnitsPerRow();
        $clean = array();

        foreach ($units as $elementId => $count) {
            $elementId = (int) $elementId;

            // Le client envoie un entier ; on refuse tout le reste plutôt que de
            // laisser un « -3 » se transformer en « 3 » comme le fait le
            // formulaire historique (qui se contente de retirer les non-chiffres).
            $count = is_numeric($count)
                ? (int) $count
                : (int) preg_replace('/[^0-9]/', '', (string) $count);

            if ($elementId <= 0 || $count <= 0) {
                continue;
            }

            $clean[$elementId] = min($count, $max);
        }

        return $clean;
    }

    /**
     * Entrée attendue par les primitives legacy ($_POST['amounts']).
     * Fonction pure.
     *
     * @param array<int, int> $units
     * @return array<int, int>
     */
    public static function amounts(array $units): array
    {
        return self::normalizeUnits($units);
    }

    /** Un élément qui n'existe qu'en un seul exemplaire (boucliers). Fonction pure. */
    public static function isUnique(int $element): bool
    {
        return in_array($element, GameData::uniqueUnits(), true);
    }

    /**
     * Combien d'exemplaires d'un élément unique il reste à construire : 0 s'il
     * est déjà construit ou déjà en file d'attente, 1 sinon. Fonction pure.
     *
     * Les boucliers sont stockés en `enum('0','1')` : laisser passer une quantité
     * supérieure fait échouer l'enregistrement de la production (MyISAM en mode
     * strict répond « Data truncated »). La règle vaut pour la commande comme pour
     * la consommation de la file, d'où cette fonction unique.
     */
    public static function uniqueAllowance(int $element, array $planet): int
    {
        if (!self::isUnique($element)) {
            return -1;
        }

        $column = GameData::resource()[$element] ?? null;

        if ($column !== null && (int) ($planet[$column] ?? 0) >= 1) {
            return 0;
        }

        $queue = (string) ($planet['b_hangar_id'] ?? '');

        return strpos($queue, $element . ',') === false ? 1 : 0;
    }
}
