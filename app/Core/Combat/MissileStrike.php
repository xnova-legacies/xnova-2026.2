<?php

declare(strict_types=1);

namespace App\Core\Combat;

/**
 * Attaque de missiles interplanétaires : la **règle**, sans base de données.
 *
 * Reprend le calcul de l'ancienne fonction globale `raketenangriff()`
 * (`includes/raketenangriff.php`, supprimé) : la formule est conservée telle
 * quelle — y compris les index `$def[9]` (missiles d'interception du défenseur)
 * et `$def[10]` (missiles entrants de l'attaquant) — pour que les valeurs
 * restituées soient identiques au jeu existant.
 *
 * Les index des défenses sont ceux du jeu : 0 lanceur, 1 canon magnétique,
 * 2 batterie électromagnétique, 3 canon de Gauss, 4 lanceur ionique,
 * 5 lanceur de plasma, 6 petit bouclier, 7 grand bouclier, 8 missiles
 * interplanétaires du défenseur, 9 missiles d'interception.
 */
final class MissileStrike
{
    /** Défenses réellement touchées par une salve (les deux types de missiles à part). */
    public const DEFENCES = 9;

    /**
     * Résout une salve : quelles défenses tombent, et ce qu'elles coûtent.
     *
     * @param int          $defenderArmour  blindage du défenseur (techno défense)
     * @param int          $attackerWeapons techno militaire de l'attaquant
     * @param int          $missiles        missiles tirés
     * @param array<int, int> $defences     effectifs présents, indexés 0-9
     * @param int|string   $primary         cible prioritaire (`primaer` brut du tir)
     *
     * @return array{remaining: array<int, int>, destroyed: array<int, int>, lost_metal: array<int, int>, lost_crystal: array<int, int>, lost_deuterium: array<int, int>}
     */
    public static function resolve(int $defenderArmour, int $attackerWeapons, int $missiles, array $defences, int|string $primary = 0): array
    {
        $def = $defences;
        $def[10] = $missiles;

        $metal = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $crystal = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $deuterium = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $left = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

        for ($temp = 0; $temp < 11; $temp++) {
            $left[$temp] = $def[$temp];
        }

        $destroyed = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

        $hull = array();

        $hull[0] = 200 * (1 + $defenderArmour / 10);
        $hull[1] = $hull[0];
        $hull[2] = 800 * (1 + ($defenderArmour / 10));
        $hull[3] = 3500 * (1 + ($defenderArmour / 10));
        $hull[4] = $hull[2];
        $hull[5] = 10000 * (1 + ($defenderArmour / 10));
        $hull[6] = 2000 * (1 + ($defenderArmour / 10));
        $hull[7] = $hull[5];
        $hull[8] = 1500 * (1 + ($defenderArmour / 10));

        $metal_cost_table = array(2, 1.5, 6, 20, 2, 50, 10, 50, 12.5, 8);
        $crystal_cost_table = array(0, 0.5, 2, 15, 6, 50, 10, 50, 2.5, 0);
        $deuterium_cost_table = array(0, 0, 0, 2, 0, 30, 0, 0, 10.0, 2);

        $damage = (int) floor(($def[10] - $def[9]) * (12000 * (1 + ($attackerWeapons / 10))));

        if ($damage < 0) {
            $damage = 0;
        }

        // Ordre de tir : la cible prioritaire passe devant, puis le lanceur (0) et le
        // reste dans l'ordre du jeu. `8` (missiles interplanétaires du défenseur) et
        // toute valeur inconnue gardent l'ordre naturel, comme dans la règle d'origine.
        $primary = (int) $primary;
        $ordre = array(0, 1, 2, 3, 4, 5, 6, 7, 8);

        if ($primary >= 1 && $primary <= 7) {
            $ordre = array($primary, 0);

            for ($i = 1; $i <= 8; $i++) {
                if ($i !== $primary) {
                    $ordre[] = $i;
                }
            }
        }

        // Les missiles intercepteurs du défenseur abattent les missiles entrants.
        $left[10] = 0;
        $destroyed[10] += $def[10];
        $metal[10] += $destroyed[10] * $metal_cost_table[8];
        $crystal[10] += $destroyed[10] * $crystal_cost_table[8];
        $deuterium[10] += $destroyed[10] * $deuterium_cost_table[8];

        $left[9] = ($def[9] - $def[10]);

        if ($left[9] < 0) {
            $left[9] = 0;
        }

        $destroyed[11] = $def[9] - $left[9];
        $destroyed[9] += ($def[9] - $left[9]);
        $metal[9] += $destroyed[9] * $metal_cost_table[9];
        $crystal[9] += $destroyed[9] * $crystal_cost_table[9];
        $deuterium[9] += $destroyed[9] * $deuterium_cost_table[9];
        $metal[11] += $metal[9];
        $crystal[11] += $crystal[9];
        $deuterium[11] += $deuterium[9];

        // Puis les défenses tombent, dans l'ordre de tir.
        for ($temp = 0; $temp < 9; $temp++) {
            $cible = $ordre[$temp];

            if ($damage >= ($hull[$cible] * $def[$cible])) {
                $destroyed[$cible] += $def[$cible];
                $left[$cible] = 0;
                $damage -= ($hull[$cible] * $destroyed[$cible]);
            } else {
                $destroyed[$cible] += (int) floor($damage / $hull[$cible]);
                $damage -= $destroyed[$cible] * $hull[$cible];
                $left[$cible] = ($def[$cible] - $destroyed[$cible]);
            }

            $metal[$cible] += $destroyed[$cible] * $metal_cost_table[$cible];
            $crystal[$cible] += $destroyed[$cible] * $crystal_cost_table[$cible];
            $deuterium[$cible] += $destroyed[$cible] * $deuterium_cost_table[$cible];

            $left[11] += $left[$cible];
            $destroyed[11] += $destroyed[$cible];
            $metal[11] += $metal[$cible];
            $crystal[11] += $crystal[$cible];
            $deuterium[11] += $deuterium[$cible];
        }

        return array(
            'remaining' => $left,
            'destroyed' => $destroyed,
            'lost_metal' => $metal,
            'lost_crystal' => $crystal,
            'lost_deuterium' => $deuterium,
        );
    }
}
