<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\QueueBar;
use PHPUnit\Framework\TestCase;

/**
 * Panneau latéral des files d'attente (colonne de droite, toutes les pages).
 *
 * On teste le rendu et les libellés : ce sont les parties pures. La collecte des
 * données (`QueueBar::groups`) lit les files du joueur en base, elle n'est pas
 * testée unitairement — comme les autres services qui écrivent ou lisent.
 */
final class QueueBarTest extends TestCase
{
    /** @return array<string, string> */
    private function labels(): array
    {
        return QueueBar::labels(array());
    }

    /**
     * Un jeu de groupes où seul celui demandé est garni.
     *
     * @param list<array<string, mixed>> $items
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupe(string $domaine, array $items): array
    {
        return array(
            'buildings' => array(),
            'research' => array(),
            'fleet' => array(),
            'defense' => array(),
            $domaine => $items,
        );
    }

    public function testLabelsFallBackToFrenchWording(): void
    {
        $labels = QueueBar::labels(array());

        self::assertSame("Files d'attente", $labels['title']);
        self::assertSame('Bâtiments', $labels['buildings']);
        self::assertSame('Recherche', $labels['research']);
        self::assertSame('Vaisseaux', $labels['fleet']);
        self::assertSame('Défenses', $labels['defense']);
    }

    public function testLabelsComeFromTheLanguageFileWhenPresent(): void
    {
        $labels = QueueBar::labels(array(
            'qb_title' => 'Files',
            'qb_buildings' => 'Chantiers',
            'qb_empty_fleet' => 'Rien à fabriquer',
        ));

        self::assertSame('Files', $labels['title']);
        self::assertSame('Chantiers', $labels['buildings']);
        self::assertSame('Rien à fabriquer', $labels['empty_fleet']);
        // Un libellé absent garde son repli : il ne devient jamais vide.
        self::assertSame('Défenses', $labels['defense']);
        self::assertSame('Aucune défense en fabrication', $labels['empty_defense']);
    }

    public function testTotalCountsEveryGroup(): void
    {
        self::assertSame(0, QueueBar::total($this->groupe('buildings', array())));

        self::assertSame(3, QueueBar::total(array(
            'buildings' => array(array('position' => 1), array('position' => 2)),
            'research' => array(),
            'fleet' => array(array('position' => 1)),
            'defense' => array(),
        )));
    }

    public function testTheColumnCarriesTheFourCollapsibleGroups(): void
    {
        $html = QueueBar::render(
            $this->groupe('buildings', array(
                array(
                    'position' => 1,
                    'element' => 3,
                    'name' => 'Mine de cristal',
                    'icon' => '/public/xnova/buildings/3.gif',
                    'level' => 4,
                    'end_time' => time() + 60,
                    'mode' => 'build',
                    'running' => true,
                ),
            )),
            $this->labels()
        );

        // L'identifiant est la réponse du client (scripts/xnova-queue.js).
        self::assertStringContainsString('id="' . QueueBar::MARKER . '"', $html);
        self::assertStringContainsString('xnova-queuebar', $html);
        self::assertStringContainsString('col-12 col-lg-3 col-xxl-2', $html);

        // Quatre listes repliables : un groupe garni s'ouvre d'office, les autres
        // restent repliés.
        self::assertSame(4, substr_count($html, '<details'));
        self::assertSame(1, substr_count($html, '<details class="xnova-queuebar-group" open>'));
        self::assertSame(3, substr_count($html, '<details class="xnova-queuebar-group">'));

        self::assertStringContainsString('Mine de cristal', $html);
        self::assertStringContainsString('data-end-time=', $html);
    }

    public function testTheGroupsCarryTheRealQueueDomains(): void
    {
        $html = QueueBar::render($this->groupe('fleet', array()), $this->labels());

        // `data-xnova-queue` est ce que le glisser-déposer envoie à l'API : les
        // vaisseaux et les défenses partagent donc le domaine du hangar, et les
        // quatre groupes en couvrent bien trois.
        self::assertStringContainsString('data-xnova-queue="buildings"', $html);
        self::assertStringContainsString('data-xnova-queue="research"', $html);
        self::assertSame(2, substr_count($html, 'data-xnova-queue="hangar"'));
    }

    public function testTheRowsCarryTheirActions(): void
    {
        $html = QueueBar::render(
            $this->groupe('buildings', array(
                array(
                    'position' => 1,
                    'element' => 3,
                    'name' => 'Mine de cristal',
                    'icon' => '',
                    'level' => 4,
                    'end_time' => time() + 60,
                    'mode' => 'build',
                    'running' => true,
                    'movable' => false,
                ),
                array(
                    'position' => 2,
                    'element' => 4,
                    'name' => 'Mine de deutérium',
                    'icon' => '',
                    'level' => 2,
                    'end_time' => time() + 120,
                    'mode' => 'build',
                    'running' => false,
                    'movable' => true,
                ),
            )),
            $this->labels()
        );

        // Le panneau réutilise le rendu des files : il hérite donc de ses liens
        // d'action et de sa poignée, sans une ligne de rendu de plus.
        self::assertStringContainsString('cmd=cancel', $html);
        self::assertStringContainsString('cmd=remove', $html);
        self::assertStringContainsString('data-position="2" data-movable="1" draggable="true"', $html);
    }

    public function testEmptyGroupsShowTheirOwnWording(): void
    {
        $html = QueueBar::render($this->groupe('fleet', array()), $this->labels());

        // Quatre messages distincts : « aucun chantier » ne doit pas servir pour
        // une file de vaisseaux.
        self::assertStringContainsString('Aucun chantier en cours', $html);
        self::assertStringContainsString('Aucune recherche en cours', $html);
        self::assertStringContainsString('Aucun vaisseau en fabrication', $html);
        self::assertStringContainsString('Aucune défense en fabrication', $html);
        self::assertSame(0, substr_count($html, ' open>'));
    }
}
