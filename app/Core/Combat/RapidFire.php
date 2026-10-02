<?php

declare(strict_types=1);

namespace App\Core\Combat;

/**
 * RapidFire (rapid fire) d'un combat.
 *
 * Le moteur enchaîne plusieurs tirs quand l'unité visée figure dans la table
 * `$CombatCaps[$tireur]['sd']` avec une valeur supérieure à 1 (0/1 = pas de feu
 * rapide). Cette classe ne relit que la table de combat et la composition des
 * deux camps au premier tour : elle décrit ce qui a pu se passer dans CE combat
 * (uniquement les unités réellement engagées en face), sans recalculer le
 * moindre tir — les calculs du moteur restent la seule référence.
 */
final class RapidFire
{
    /**
     * Lignes « unité / cible / tirs » du RapidFire d'un camp.
     *
     * @param array<int|string, mixed> $side       unités du camp (clés = ids, `count`)
     * @param array<int|string, mixed> $enemy      unités d'en face
     * @param array<int|string, mixed> $combatCaps table des capacités de combat
     * @param array<int|string, mixed> $names      noms des unités (langue du rapport)
     * @return list<array{unit: string, target: string, shots: int}>
     */
    public static function rows(array $side, array $enemy, array $combatCaps, array $names): array
    {
        $engaged = static function (array $units): array {
            $ids = array();

            foreach ($units as $id => $data) {
                if (!is_numeric($id) || !is_array($data)) {
                    continue;
                }

                if ((int) ($data['count'] ?? 0) > 0) {
                    $ids[] = (int) $id;
                }
            }

            return $ids;
        };

        $mine = $engaged($side);
        $theirs = $engaged($enemy);
        $rows = array();

        foreach ($mine as $unit) {
            $shots = $combatCaps[$unit]['sd'] ?? array();

            if (!is_array($shots)) {
                continue;
            }

            foreach ($theirs as $target) {
                $count = (int) ($shots[$target] ?? 0);

                if ($count <= 1) {
                    continue;
                }

                $rows[] = array(
                    'unit' => (string) ($names[$unit] ?? $unit),
                    'target' => (string) ($names[$target] ?? $target),
                    'shots' => $count,
                );
            }
        }

        return $rows;
    }
}
