<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use PHPUnit\Framework\TestCase;

/**
 * Verrou du traitement des flottes.
 *
 * `FlyingFleetHandler` verrouille les tables qu'il touche, et MySQL refuse alors
 * toute autre table — **même en lecture** (« Table … was not locked with LOCK
 * TABLES »), cela vaut aussi depuis le passage en InnoDB. La liste doit donc
 * couvrir ce que le code moderne lit au passage : une attaque a fini en erreur
 * fatale parce que `MissionCaseAttack` demande au registre des modules lequel
 * répond du moteur de combat (point de surcharge `ModuleService::resolve()`), et
 * que la table `modules` ne figurait pas dans le verrou.
 */
final class FleetLockTest extends TestCase
{
    /** Tables lues ou écrites pendant le traitement d'un vol : toutes dans le verrou. */
    private const TABLES = array(
        'lunas',
        'rw',
        'messages',
        'fleets',
        'planets',
        'galaxy',
        'users',
        'aks',      // suppression d'un groupe d'attaque
        'modules',  // point de surcharge du moteur : le registre répond lequel s'exécute
    );

    public function testTheFleetHandlerLocksEveryTableItTouches(): void
    {
        foreach ($this->lockStatements() as $lock) {
            foreach (self::TABLES as $table) {
                self::assertStringContainsString(
                    '{{table}}' . $table,
                    $lock,
                    'Le verrou du traitement des flottes doit couvrir la table ' . $table . '.'
                );
            }
        }
    }

    public function testTheFleetHandlerReleasesItsLock(): void
    {
        // `UNLOCK TABLES` contient « LOCK TABLE » : on compte les **commandes**,
        // pas les mots (un commentaire qui en parle ne compte pas non plus).
        $poses = substr_count($this->source(), 'doquery("LOCK TABLE');
        $relaches = substr_count($this->source(), 'doquery("UNLOCK TABLES');

        self::assertSame(1, $poses, 'Le traitement des flottes pose un verrou.');
        self::assertSame($poses, $relaches, 'Chaque verrou posé doit être relâché.');
    }

    /**
     * Toutes les commandes de verrou du fichier (un traitement peut en poser
     * plusieurs), commentaires exclus.
     *
     * @return list<string>
     */
    private function lockStatements(): array
    {
        preg_match_all('/doquery\("LOCK TABLE ([^"]+)"/', $this->source(), $matches);

        self::assertNotSame(array(), $matches[1], 'Le traitement des flottes doit poser un verrou.');

        return $matches[1];
    }

    private function source(): string
    {
        return (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/FleetFunctions.php');
    }
}
