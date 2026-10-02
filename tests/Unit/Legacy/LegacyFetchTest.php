<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use PHPUnit\Framework\TestCase;

/**
 * Le legacy lit par les dépôts : il ne touche plus aux fonctions `mysql_*`.
 *
 * Un dépôt rend un **tableau** de lignes, là où une requête rendait une
 * **ressource** : garder un `mysql_fetch_array()` sur le premier lève une erreur
 * fatale dès que la branche est empruntée. Vécu sur `GetBuildingTime()` — le
 * laboratoire intergalactique construit, la page des bâtiments tombait sur
 * `mysqli_fetch_array(): Argument #1 ($result) must be of type mysqli_result, array given`.
 */
final class LegacyFetchTest extends TestCase
{
    public function testTheLegacyNeverFetchesFromAQuery(): void
    {
        $offenders = array();

        foreach (glob(ROOT_PATH . 'app/Core/Legacy/*.php') ?: array() as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match('/mysql_(fetch_array|fetch_assoc|fetch_object|num_rows|result|query)\s*\(/', $source) === 1) {
                $offenders[] = basename($file);
            }
        }

        self::assertSame(
            array(),
            $offenders,
            'Le legacy passe par les dépôts : les fonctions `mysql_*` ne s\'y emploient plus '
            . '(un dépôt rend un tableau, pas une ressource de requête).'
        );
    }
}
