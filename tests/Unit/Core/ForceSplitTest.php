<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Combat\ForceSplit;
use PHPUnit\Framework\TestCase;

/**
 * Le moteur ne combat qu'une force par camp : les forces sont réunies avant le
 * combat, puis les survivants sont répartis entre les participants au prorata.
 */
final class ForceSplitTest extends TestCase
{
    public function testForcesAreMerged(): void
    {
        self::assertSame(
            array(204 => 300, 206 => 30, 211 => 10),
            ForceSplit::merge(array(
                array(204 => 200, 206 => 20, 211 => 10),
                array(204 => 100, 206 => 10),
            ))
        );

        self::assertSame(array(), ForceSplit::merge(array(array(204 => 0), array())));
    }

    public function testSurvivorsAreSplitProportionally(): void
    {
        // Deux attaquants (200 et 100 chasseurs) : 90 survivants sur 300 → 60 et 30.
        $shares = ForceSplit::survivors(
            array(array(204 => 200), array(204 => 100)),
            array(204 => 90)
        );

        self::assertSame(array(204 => 60), $shares[0]);
        self::assertSame(array(204 => 30), $shares[1]);
    }

    public function testTheRoundingRemainderNeverLosesAShip(): void
    {
        // 100 survivants pour 3 flottes égales de 100 : 33/33/33 + le reliquat (1) au premier.
        $shares = ForceSplit::survivors(
            array(array(204 => 100), array(204 => 100), array(204 => 100)),
            array(204 => 100)
        );

        self::assertSame(100, $shares[0][204] + $shares[1][204] + $shares[2][204]);
        self::assertSame(array(204 => 34), $shares[0]);
        self::assertSame(array(204 => 33), $shares[1]);
    }

    public function testAForceNeverGetsMoreSurvivorsThanItEngaged(): void
    {
        // Le total annoncé dépasse ce qui a été engagé : on plafonne.
        $shares = ForceSplit::survivors(
            array(array(204 => 5), array(204 => 5)),
            array(204 => 20)
        );

        self::assertSame(array(204 => 5), $shares[0]);
        self::assertSame(array(204 => 5), $shares[1]);
    }

    public function testAWipedForceGetsNothing(): void
    {
        $shares = ForceSplit::survivors(
            array(array(204 => 200), array(204 => 0)),
            array(204 => 0)
        );

        self::assertSame(array(), $shares[0]);
        self::assertSame(array(), $shares[1]);
    }

    public function testEveryParticipantKeepsItsOwnIndex(): void
    {
        $shares = ForceSplit::survivors(
            array(array(204 => 100, 206 => 10), array(202 => 50)),
            array(204 => 50, 202 => 20)
        );

        self::assertSame(array(204 => 50), $shares[0], 'Le deuxième type non engagé ne crée pas de clé.');
        self::assertSame(array(202 => 20), $shares[1]);
    }
}
