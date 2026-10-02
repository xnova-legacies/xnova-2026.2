<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Règles de la phalange de capteur.
 *
 * Une phalange ne couvre que les systèmes voisins du sien : son niveau donne la
 * portée de part et d'autre (niveau 4 = 4 systèmes à gauche et à droite). Hors de
 * là, aucun scan n'est fait — et aucun deutérium n'est débité.
 *
 * Le coût du scan est ici, une seule fois : la page et l'entrepôt de la planète
 * s'y réfèrent.
 */
final class Phalanx
{
    /** Coût d'un scan, en deutérium. */
    public const SCAN_COST = 10000;

    /** Portée d'une phalange, en systèmes de part et d'autre du sien. */
    public static function range(int $level): int
    {
        return max(0, $level);
    }

    /** Premier système couvert (borné à 1). */
    public static function firstSystem(int $system, int $level): int
    {
        return max(1, $system - self::range($level));
    }

    /** Dernier système couvert. */
    public static function lastSystem(int $system, int $level, int $maxSystem): int
    {
        return min($maxSystem, $system + self::range($level));
    }

    /**
     * La cible est-elle à portée ?
     *
     * Il faut la même galaxie et un écart de systèmes au plus égal au niveau.
     */
    public static function inRange(int $galaxy, int $system, int $targetGalaxy, int $targetSystem, int $level): bool
    {
        if ($level <= 0 || $galaxy !== $targetGalaxy) {
            return false;
        }

        return abs($targetSystem - $system) <= self::range($level);
    }
}
