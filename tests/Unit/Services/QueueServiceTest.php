<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Api\ApiException;
use App\Services\QueueService;
use PHPUnit\Framework\TestCase;

/**
 * Le réordonnancement s'appuie sur deux fonctions pures (move, recomputeEndTimes)
 * et sur un garde-fou (assertMovable) : ce sont les seules parties testables sans
 * base de données (l'écriture passe par Connection).
 */
final class QueueServiceTest extends TestCase
{
    private QueueService $service;
    private array $langBackup = [];
    private array $dpathBackup = [];

    protected function setUp(): void
    {
        $this->service = new QueueService();
        $this->langBackup = $GLOBALS['lang'] ?? [];
        $this->dpathBackup = $GLOBALS['dpath'] ?? [];

        $GLOBALS['lang'] = ['tech' => [3 => 'Mine de deutérium', 202 => 'Petit transporteur']];
        $GLOBALS['dpath'] = '/public/xnova/';
    }

    protected function tearDown(): void
    {
        $GLOBALS['lang'] = $this->langBackup;
        $GLOBALS['dpath'] = $this->dpathBackup;
    }

    public function testBuildingEntriesParsesTheQueue(): void
    {
        $entries = $this->service->buildingEntries([
            'b_building_id' => '1,4,100,1000,build;31,2,50,1050,destroy',
        ]);

        self::assertCount(2, $entries);
        self::assertSame(
            ['element' => 1, 'level' => 4, 'duration' => 100, 'end_time' => 1000, 'mode' => 'build'],
            $entries[0]
        );
        self::assertSame('destroy', $entries[1]['mode']);
    }

    public function testParsingHandlesAnEmptyQueue(): void
    {
        self::assertSame([], $this->service->buildingEntries(['b_building_id' => '0']));
        self::assertSame([], $this->service->buildingEntries(['b_building_id' => '']));
        self::assertSame([], $this->service->buildingEntries([]));
        self::assertSame([], $this->service->hangarEntries(['b_hangar_id' => '0']));
    }

    public function testHangarEntriesParsesCounts(): void
    {
        $entries = $this->service->hangarEntries(['b_hangar_id' => '202,5;203,2;']);

        self::assertSame(
            [['element' => 202, 'count' => 5], ['element' => 203, 'count' => 2]],
            $entries
        );
    }

    public function testHangarEntriesAreSerializedBackInTheGameFormat(): void
    {
        $entries = [['element' => 202, 'count' => 5], ['element' => 203, 'count' => 2]];

        self::assertSame('202,5;203,2;', QueueService::serializeHangarEntries($entries));

        // Aller-retour : le calcul de production relit ce que la file a écrit.
        self::assertSame($entries, $this->service->hangarEntries(
            ['b_hangar_id' => QueueService::serializeHangarEntries($entries)]
        ));
    }

    public function testEmptyHangarQueueIsWrittenAsAnEmptyString(): void
    {
        // Une file vide ne doit pas laisser un « ; » : le rendu et le calcul de
        // production l'interprètent comme « rien à fabriquer ».
        self::assertSame('', QueueService::serializeHangarEntries([]));
        self::assertSame([], $this->service->hangarEntries(['b_hangar_id' => '']));
    }

    public function testBuildingItemsExposeNameIconAndMovability(): void
    {
        $items = $this->service->buildingItems([
            'b_building_id' => '3,5,100,1000,build;31,2,50,1050,build',
        ]);

        self::assertCount(2, $items);

        self::assertSame('Mine de deutérium', $items[0]['name']);
        self::assertSame('/public/xnova/buildings/3.gif', $items[0]['icon']);
        self::assertSame(1, $items[0]['position']);
        self::assertTrue($items[0]['running']);
        self::assertFalse($items[0]['movable']);

        self::assertSame(2, $items[1]['position']);
        self::assertFalse($items[1]['running']);
        self::assertTrue($items[1]['movable']);
    }

    public function testASingleItemCannotBeMoved(): void
    {
        $items = $this->service->buildingItems(['b_building_id' => '3,5,100,1000,build']);

        self::assertFalse($items[0]['movable']);
    }

    public function testMoveReordersItems(): void
    {
        $items = ['a', 'b', 'c', 'd'];

        self::assertSame(['a', 'c', 'b', 'd'], QueueService::move($items, 2, 3));
        self::assertSame(['a', 'd', 'b', 'c'], QueueService::move($items, 4, 2));
    }

    public function testMoveKeepsTheListUnchangedWhenPositionsAreEqual(): void
    {
        self::assertSame(['a', 'b', 'c'], QueueService::move(['a', 'b', 'c'], 2, 2));
    }

    public function testRecomputeEndTimesChainsDurations(): void
    {
        $entries = [
            ['duration' => 100, 'end_time' => 1000],
            ['duration' => 30, 'end_time' => 0],
            ['duration' => 50, 'end_time' => 0],
        ];

        $result = QueueService::recomputeEndTimes($entries, 1000);

        self::assertSame(1000, $result[0]['end_time']);
        self::assertSame(1030, $result[1]['end_time']);
        self::assertSame(1080, $result[2]['end_time']);
    }

    public function testChainFromStartsTheFirstElementAfterItsOwnDuration(): void
    {
        // Apres une interruption, le nouvel element de tete demarre maintenant :
        // sa fin doit inclure sa propre duree (contrairement a recomputeEndTimes,
        // qui conserve l'echeance du chantier en cours).
        $entries = [
            ['duration' => 100, 'end_time' => 9000],
            ['duration' => 30, 'end_time' => 0],
        ];

        $result = QueueService::chainFrom($entries, 1000);

        self::assertSame(1100, $result[0]['end_time']);
        self::assertSame(1130, $result[1]['end_time']);
    }

    public function testChainFromKeepsEmptyListsEmpty(): void
    {
        self::assertSame([], QueueService::chainFrom([], 1000));
    }

    public function testRechainAfterKeepsPreviousEntriesAndRechainsTheRest(): void
    {
        // Retrait d'un element au milieu de la file : ce qui precede (dont le
        // chantier en cours) garde son echeance, la suite repart de la fin du
        // precedent, sans conserver le creneau de l'element retire.
        $entries = [
            ['duration' => 100, 'end_time' => 500],
            ['duration' => 30, 'end_time' => 9000],
            ['duration' => 50, 'end_time' => 9000],
        ];

        $result = QueueService::rechainAfter($entries, 1);

        self::assertSame(500, $result[0]['end_time']);
        self::assertSame(530, $result[1]['end_time']);
        self::assertSame(580, $result[2]['end_time']);
    }

    public function testRechainAfterIgnoresAnIndexBeyondTheList(): void
    {
        $entries = [['duration' => 10, 'end_time' => 700]];

        $result = QueueService::rechainAfter($entries, 1);

        self::assertSame(700, $result[0]['end_time']);
    }

    public function testChainFromToleratesAZeroDuration(): void
    {
        // Les parties accelerees donnent des durees nulles : l'echeance ne doit
        // pas reculer, et les suivantes restent enchainees.
        $result = QueueService::chainFrom(
            [['duration' => 0, 'end_time' => 500], ['duration' => 5, 'end_time' => 500]],
            1000
        );

        self::assertSame(1000, $result[0]['end_time']);
        self::assertSame(1005, $result[1]['end_time']);
    }

    public function testAssertMovableAcceptsWaitingPositions(): void
    {
        $this->service->assertMovable([['x'], ['y'], ['z']], 2, 3);

        self::assertTrue(true); // aucun exception => déplacement autorisé
    }

    public function testAssertMovableRejectsTheRunningElement(): void
    {
        $this->expectException(ApiException::class);

        $this->service->assertMovable([['x'], ['y']], 1, 2);
    }

    public function testAssertMovableRejectsOutOfRangePositions(): void
    {
        $this->expectException(ApiException::class);

        $this->service->assertMovable([['x'], ['y']], 2, 5);
    }

    public function testAssertMovableRejectsAQueueWithOneElement(): void
    {
        $this->expectException(ApiException::class);

        $this->service->assertMovable([['x']], 2, 2);
    }

    public function testResearchEntriesParsesTheQueue(): void
    {
        $entries = $this->service->researchEntries([
            'b_tech_queue' => '106,3,100,1000,research;108,1,50,1050,research',
        ]);

        self::assertCount(2, $entries);
        self::assertSame(
            ['element' => 106, 'level' => 3, 'duration' => 100, 'end_time' => 1000, 'mode' => 'research'],
            $entries[0]
        );
        self::assertSame(108, $entries[1]['element']);
        self::assertSame(1050, $entries[1]['end_time']);
    }

    public function testResearchEntriesFallsBackToTheLegacySlot(): void
    {
        // Recherche lancee avant la migration : elle n'est pas dans b_tech_queue,
        // c'est le couple b_tech_id / b_tech de la planete qui la decrit (niveau
        // inconnu, donc 0 : il n'est pas affiche).
        $entries = $this->service->researchEntries(
            ['b_tech_queue' => ''],
            ['b_tech_id' => 115, 'b_tech' => 5000]
        );

        self::assertCount(1, $entries);
        self::assertSame(115, $entries[0]['element']);
        self::assertSame(0, $entries[0]['level']);
        self::assertSame(5000, $entries[0]['end_time']);
    }

    public function testResearchEntriesIgnoresAnEmptyQueue(): void
    {
        self::assertSame([], $this->service->researchEntries(['b_tech_queue' => '']));
        self::assertSame([], $this->service->researchEntries([], ['b_tech_id' => 0, 'b_tech' => 0]));
    }

    public function testResearchNextLevelCountsQueuedEntriesOfTheSameElement(): void
    {
        // Niveau courant 3 (spy_tech) + une recherche deja en file => la prochaine
        // recherche ajoutee visera le niveau 5.
        $user = array(
            'b_tech_queue' => '106,4,100,1000,research;108,1,50,1050,research',
            'spy_tech' => 3,
        );

        self::assertSame(5, $this->service->researchNextLevel($user, null, 106));
        // Niveau 0 (computer_tech) + une recherche deja en file => niveau 2.
        self::assertSame(2, $this->service->researchNextLevel($user, null, 108));
    }

    public function testResearchItemsExposeNameAndPositions(): void
    {
        $GLOBALS['lang']['tech'][106] = 'Technologie Espionnage';

        $items = $this->service->researchItems([
            'b_tech_queue' => '106,3,100,1000,research;106,4,50,1050,research',
        ]);

        self::assertCount(2, $items);
        self::assertSame('Technologie Espionnage', $items[0]['name']);
        self::assertSame(1, $items[0]['position']);
        self::assertTrue($items[0]['running']);
        self::assertFalse($items[0]['movable']);
        self::assertSame(2, $items[1]['position']);
        self::assertTrue($items[1]['movable']);
    }

    public function testHangarKindSeparatesFleetsFromDefenses(): void
    {
        // La repartition vient de GameTables::RES_LIST, jamais d'une plage recopiee.
        self::assertSame('fleet', QueueService::hangarKind(202));
        self::assertSame('fleet', QueueService::hangarKind(214));
        self::assertSame('defense', QueueService::hangarKind(401));
        self::assertSame('defense', QueueService::hangarKind(503));
        // Une unite inconnue retombe du cote des vaisseaux plutot que d'etre perdue.
        self::assertSame('fleet', QueueService::hangarKind(999));
    }

    public function testSplitHangarKeepsTheRealQueuePositions(): void
    {
        $groupes = QueueService::splitHangar(array(
            array('position' => 1, 'element' => 202, 'count' => 3, 'running' => true),
            array('position' => 2, 'element' => 401, 'count' => 5, 'running' => false),
            array('position' => 3, 'element' => 204, 'count' => 1, 'running' => false),
        ));

        self::assertCount(2, $groupes['fleet']);
        self::assertCount(1, $groupes['defense']);

        // La position est celle de la file REELLE, pas celle de la liste : le
        // glisser-déposer du panneau envoie bien un déplacement valide pour le jeu.
        self::assertSame(3, $groupes['fleet'][1]['position']);
        self::assertSame(2, $groupes['defense'][0]['position']);

        // Seule la premiere entree de la file est « en fabrication ».
        self::assertTrue($groupes['fleet'][0]['running']);
        self::assertFalse($groupes['fleet'][1]['running']);
        self::assertFalse($groupes['defense'][0]['running']);
    }

    public function testSplitHangarWithNothingQueued(): void
    {
        $groupes = QueueService::splitHangar(array());

        self::assertSame(array(), $groupes['fleet']);
        self::assertSame(array(), $groupes['defense']);
    }
}
