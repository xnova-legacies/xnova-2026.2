<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use PHPUnit\Framework\TestCase;

/**
 * Le moteur de combat doit être réellement **appelé** par les deux missions qui combattent.
 *
 * `MissionCaseDestruction()` (attaque de lune) a perdu son appel lors du renommage
 * `implementation()` → `resolve()` : la classe du moteur était bien résolue, mais jamais invoquée.
 * `$battle` restait alors indéfini — la mission n'échangeait aucun coup, le rapport sortait sans
 * issue, et une lune ne pouvait plus être détruite. Ni `php -l`, ni phpcs, ni le crawl ne le
 * voyaient : les 42 adresses du test de fumée ne déclenchent aucune attaque.
 *
 * Le fichier vit dans `app/Core/Legacy/`, sans espace de noms : il se lit par son texte.
 */
final class CombatEngineCallTest extends TestCase
{
    /** Les deux missions qui font combattre une flotte. */
    private const MISSIONS = array('MissionCaseAttack', 'MissionCaseDestruction');

    public function testTheBattleEngineIsCalledByBothCombatMissions(): void
    {
        $source = (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/CombatFunctions.php');

        foreach (self::MISSIONS as $mission) {
            $body = $this->functionSource($source, $mission);

            self::assertStringContainsString(
                'ModuleService::resolve(\App\Core\Combat\BattleEngine::class)',
                $body,
                $mission . '() doit résoudre le moteur de combat (point de surcharge des modules).'
            );

            self::assertStringContainsString(
                '= $Engine::resolve($CurrentSet, $TargetSet, $CurrentTechno, $TargetTechno);',
                $body,
                $mission . '() doit appeler le moteur : sans cet appel, `$battle` reste indéfini.'
            );
        }
    }

    public function testCombatFunctionsCarriesNoMoreSql(): void
    {
        $source = (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/CombatFunctions.php');

        foreach (array('doquery(', 'mysql_query(', 'mysql_fetch_array', 'Connection::') as $gesture) {
            self::assertStringNotContainsString(
                $gesture,
                $source,
                'CombatFunctions.php passe par les dépôts : plus de ' . $gesture . '.'
            );
        }
    }

    /** Le corps d'une fonction, de son `function Nom(` à l'accolade fermante en colonne 1. */
    private function functionSource(string $source, string $function): string
    {
        $start = strpos($source, 'function ' . $function . '(');

        self::assertNotFalse($start, 'La fonction ' . $function . '() doit exister.');

        $end = strpos($source, "\n}\n", (int) $start);

        self::assertNotFalse($end, 'La fonction ' . $function . '() doit se terminer.');

        return substr($source, (int) $start, (int) $end - (int) $start);
    }
}
