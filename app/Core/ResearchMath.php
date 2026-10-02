<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Durée des recherches (règles qui ne dépendent pas de la base).
 *
 * L'usine de nanites divise par deux la durée de construction des bâtiments,
 * des vaisseaux et des défenses (`pow(0.5, niveau)` dans `GetBuildingTime()`).
 * L'accélérateur de particules fait la même chose pour les **recherches** :
 * une seule implémentation du facteur, utilisée par les tableaux legacy
 * (`GetBuildingTime()`, `GetBuildingTimeLevel()`) et par
 * `BuildingService::buildingTime()`.
 */
final class ResearchMath
{
    /**
     * Facteur appliqué à la durée d'une recherche.
     *
     * Un niveau divise la durée par deux (1 → 0,5 → 0,25 …). Fonction pure.
     */
    public static function accelerationFactor(int $acceleratorLevel): float
    {
        return $acceleratorLevel > 0 ? pow(0.5, $acceleratorLevel) : 1.0;
    }
}
