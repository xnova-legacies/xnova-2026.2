<?php

declare(strict_types=1);

namespace App\Core\Combat;

/**
 * Répartition d'une force entre ses participants.
 *
 * Le moteur de combat ne connaît qu'une force par camp : pour une attaque groupée
 * (plusieurs attaquants) ou une défense groupée (le défenseur et les flottes en
 * poste chez lui), les forces sont réunies avant le combat, puis les survivants
 * sont répartis au prorata de ce que chacun a engagé.
 *
 * Aucun calcul du moteur n'est touché : ces deux fonctions pures ne font que
 * fusionner des compositions et redistribuer un total.
 */
final class ForceSplit
{
    /**
     * Fusionne plusieurs compositions de flotte.
     *
     * @param list<array<int, int>> $forces
     * @return array<int, int>
     */
    public static function merge(array $forces): array
    {
        $merged = array();

        foreach ($forces as $force) {
            foreach ($force as $shipId => $count) {
                $count = (int) $count;

                if ($count <= 0) {
                    continue;
                }

                $merged[(int) $shipId] = ($merged[(int) $shipId] ?? 0) + $count;
            }
        }

        return $merged;
    }

    /**
     * Répartit des survivants entre les participants, au prorata de leur apport.
     *
     * Chaque participant reçoit la part entière de son engagement ; le reliquat
     * d'arrondi revient au premier participant (le meneur du groupe, ou la planète
     * pour une défense groupée), pour ne jamais perdre un vaisseau en chemin.
     *
     * @param list<array<int, int>> $engaged  ce que chaque participant a engagé
     * @param array<int, int>       $survivors total des survivants, par type
     * @return list<array<int, int>> mêmes clés que `$engaged`
     */
    public static function survivors(array $engaged, array $survivors): array
    {
        $shares = array();

        foreach ($engaged as $index => $force) {
            $shares[$index] = array();
        }

        foreach ($survivors as $shipId => $total) {
            $shipId = (int) $shipId;
            $total = (int) $total;

            if ($total <= 0) {
                continue;
            }

            $pool = 0;

            foreach ($engaged as $force) {
                $pool += (int) ($force[$shipId] ?? 0);
            }

            if ($pool <= 0) {
                continue;
            }

            $given = 0;

            foreach ($engaged as $index => $force) {
                $engagedCount = (int) ($force[$shipId] ?? 0);

                if ($engagedCount <= 0) {
                    continue;
                }

                // Une flotte anéantie ne ressuscite pas : sa part est plafonnée à
                // ce qu'elle avait engagé.
                $share = min($engagedCount, (int) floor($total * $engagedCount / $pool));
                $shares[$index][$shipId] = $share;
                $given += $share;
            }

            // Le reliquat d'arrondi va au premier participant encore engagé.
            $rest = $total - $given;

            foreach ($engaged as $index => $force) {
                if ($rest <= 0) {
                    break;
                }

                $engagedCount = (int) ($force[$shipId] ?? 0);
                $already = (int) ($shares[$index][$shipId] ?? 0);
                $room = $engagedCount - $already;

                if ($room <= 0) {
                    continue;
                }

                $add = min($room, $rest);
                $shares[$index][$shipId] = $already + $add;
                $rest -= $add;
            }
        }

        // Une composition triée par identifiant reste lisible dans le rapport.
        foreach ($shares as $index => $force) {
            ksort($force);
            $shares[$index] = $force;
        }

        return $shares;
    }
}
