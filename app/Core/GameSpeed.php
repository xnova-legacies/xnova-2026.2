<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Vitesses du jeu : conversion entre la valeur stockée et le multiplicateur.
 *
 * Les formules du moteur (durées de construction, de recherche, de trajet)
 * divisent par `game_speed` / `fleet_speed` : la valeur « normale » de ce
 * diviseur est 2500, ce qui correspond à ×1. Le panneau d'administration, lui,
 * demande le **multiplicateur** (1 = normal, 1000 = mille fois plus rapide) :
 * la conversion vit ici, et nulle part ailleurs.
 */
final class GameSpeed
{
    /** Diviseur historique d'une vitesse normale (×1). */
    public const NORMAL = 2500;

    /** Multiplicateur minimal accepté (0 donnerait une division par zéro). */
    public const MIN_MULTIPLIER = 0.01;

    /** Multiplicateur correspondant à une valeur stockée (`game_speed`, `fleet_speed`). */
    public static function multiplier($stored): float
    {
        if (!is_numeric($stored)) {
            return 1.0;
        }

        return max(self::MIN_MULTIPLIER, (float) $stored / self::NORMAL);
    }

    /** Valeur à stocker pour un multiplicateur saisi dans l'administration. */
    public static function stored($multiplier): string
    {
        $value = is_numeric($multiplier) ? (float) $multiplier : 1.0;
        $stored = max(self::MIN_MULTIPLIER, $value) * self::NORMAL;

        // La colonne est un texte : les valeurs rondes restent entières (2500, pas
        // 2500.0), les autres gardent leurs décimales (une vitesse peut valoir ×0.4).
        return (string) (round($stored) === $stored ? (int) $stored : $stored);
    }

    /** Multiplicateur de vitesse des constructions et des recherches. */
    public static function buildMultiplier(array $config): float
    {
        return self::multiplier($config['game_speed'] ?? self::NORMAL);
    }

    /** Multiplicateur de vitesse des flottes. */
    public static function fleetMultiplier(array $config): float
    {
        return self::multiplier($config['fleet_speed'] ?? self::NORMAL);
    }
}
