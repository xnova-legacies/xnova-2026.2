<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ProductionService;
use PHPUnit\Framework\TestCase;

/**
 * La consommation du temps de hangar est une fonction pure : c'est la règle
 * « combien d'unités sortent de la file, et combien restent ».
 *
 * Elle a été isolée après un bug constaté en jeu : avec un temps de construction
 * nul (partie accélérée, nanites), la file ne sortait plus jamais rien et
 * `b_hangar` grossissait sans fin.
 */
final class ProductionServiceHangarTest extends TestCase
{
    public function testZeroBuildTimeProducesTheWholeBatch(): void
    {
        // Comportement du code d'origine : `b_hangar >= 0` était toujours vrai,
        // et le temps accumulé n'était pas consommé (`-= 0`).
        $result = ProductionService::consumeHangarWork(
            array(array('element' => 401, 'count' => 1000)),
            459,
            array(401 => 0)
        );

        self::assertSame(array(401 => 1000), $result['built']);
        self::assertSame(459, $result['hangar']);
        self::assertSame(array(), $result['remaining']);
    }

    public function testInstantEntryLeavesTheTimeForTheNextOne(): void
    {
        // Le lot instantané sort sans consommer le temps accumulé : celui-ci
        // reste disponible pour l'unité suivante (règle du prorata).
        $result = ProductionService::consumeHangarWork(
            array(
                array('element' => 401, 'count' => 4),
                array('element' => 402, 'count' => 2),
            ),
            25,
            array(401 => 0, 402 => 10)
        );

        self::assertSame(array(401 => 4, 402 => 2), $result['built']);
        self::assertSame(5, $result['hangar']);
        self::assertSame(array(), $result['remaining']);
    }

    public function testProductionIsProratedByTheAccumulatedTime(): void
    {
        $result = ProductionService::consumeHangarWork(
            array(array('element' => 401, 'count' => 5)),
            25,
            array(401 => 10)
        );

        self::assertSame(array(401 => 2), $result['built']);
        self::assertSame(5, $result['hangar']);
        self::assertSame(array(array('element' => 401, 'count' => 3)), $result['remaining']);
    }

    public function testEntriesAreConsumedInOrder(): void
    {
        $result = ProductionService::consumeHangarWork(
            array(
                array('element' => 401, 'count' => 2),
                array('element' => 402, 'count' => 3),
            ),
            30,
            array(401 => 10, 402 => 20)
        );

        // La première entrée prend 20 s sur les 30 accumulées ; la seconde ne
        // peut pas encore produire une unité de 20 s avec les 10 s restantes.
        self::assertSame(array(401 => 2), $result['built']);
        self::assertSame(10, $result['hangar']);
        self::assertSame(array(array('element' => 402, 'count' => 3)), $result['remaining']);
    }

    public function testLeftoverTimeIsKeptForTheNextEntry(): void
    {
        $result = ProductionService::consumeHangarWork(
            array(
                array('element' => 401, 'count' => 1),
                array('element' => 402, 'count' => 1),
            ),
            12,
            array(401 => 10, 402 => 5)
        );

        self::assertSame(array(401 => 1), $result['built']);
        self::assertSame(2, $result['hangar']);
        self::assertSame(array(array('element' => 402, 'count' => 1)), $result['remaining']);
    }

    public function testHangarNeverGoesNegative(): void
    {
        $result = ProductionService::consumeHangarWork(
            array(array('element' => 401, 'count' => 3)),
            0,
            array(401 => 10)
        );

        self::assertSame(array(), $result['built']);
        self::assertSame(0, $result['hangar']);
        self::assertSame(array(array('element' => 401, 'count' => 3)), $result['remaining']);
    }

    public function testUnknownBuildTimeIsTreatedAsInstant(): void
    {
        // Élément absent de la table fournie : aucun temps de construction connu,
        // donc rien à attendre (même règle que le temps nul).
        $result = ProductionService::consumeHangarWork(
            array(array('element' => 999, 'count' => 4)),
            0,
            array()
        );

        self::assertSame(array(999 => 4), $result['built']);
    }

    public function testEmptyEntriesProduceNothing(): void
    {
        $result = ProductionService::consumeHangarWork(array(), 120, array());

        self::assertSame(array(), $result['built']);
        self::assertSame(120, $result['hangar']);
        self::assertSame(array(), $result['remaining']);
    }

    public function testNonPositiveCountsAreIgnored(): void
    {
        $result = ProductionService::consumeHangarWork(
            array(
                array('element' => 401, 'count' => 0),
                array('element' => 402, 'count' => -3),
            ),
            60,
            array(401 => 10, 402 => 10)
        );

        self::assertSame(array(), $result['built']);
        self::assertSame(60, $result['hangar']);
        self::assertSame(array(), $result['remaining']);
    }

    public function testAUniqueElementIsProducedOnceAndTheRestLeavesTheQueue(): void
    {
        // File constatée en jeu : 2 000 grands boucliers, alors que la colonne est
        // un enum('0','1') — l'enregistrement de la production échouait (« Data
        // truncated for column 'big_protection_shield' »).
        $result = ProductionService::consumeHangarWork(
            array(
                array('element' => 408, 'count' => 1000),
                array('element' => 408, 'count' => 1000),
            ),
            1,
            array(408 => 0)
        );

        self::assertSame(array(408 => 2000), $result['built']);

        $capped = ProductionService::capUniqueProduction($result, array('big_protection_shield' => 0));

        self::assertSame(array(408 => 1), $capped['built']);
        self::assertSame(array(), $capped['remaining'], 'Le surplus quitte la file.');
    }

    public function testAnAlreadyBuiltUniqueElementIsNotProducedAgain(): void
    {
        $result = ProductionService::consumeHangarWork(
            array(array('element' => 407, 'count' => 3)),
            50,
            array(407 => 0)
        );

        $capped = ProductionService::capUniqueProduction($result, array('small_protection_shield' => 1));

        self::assertSame(array(), $capped['built']);
        self::assertSame(array(), $capped['remaining']);
    }

    public function testOrdinaryElementsAreLeftAlone(): void
    {
        $result = ProductionService::consumeHangarWork(
            array(array('element' => 401, 'count' => 5)),
            59,
            array(401 => 10)
        );

        self::assertSame($result, ProductionService::capUniqueProduction($result, array('misil_launcher' => 12)));
    }
}
