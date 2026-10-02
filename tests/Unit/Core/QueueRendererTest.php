<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\QueueRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Rendu serveur des files d'attente.
 *
 * Le balisage doit rester celui que le client produisait (mêmes classes, mêmes
 * attributs `data-*`) : c'est la réponse du décompte (`data-end-time`) et du
 * glisser-déposer (`data-position` / `data-movable`).
 */
final class QueueRendererTest extends TestCase
{
    /** @return array<string, string> */
    private function labels(string $domain = QueueRenderer::DOMAIN_BUILDINGS): array
    {
        return QueueRenderer::labels(array(), $domain);
    }

    public function testCountdownFollowsTheClientFormat(): void
    {
        self::assertSame('0:00:00', QueueRenderer::countdown(0));
        self::assertSame('0:00:00', QueueRenderer::countdown(-12));
        self::assertSame('0:00:59', QueueRenderer::countdown(59));
        self::assertSame('1:01:01', QueueRenderer::countdown(3661));
        self::assertSame('10:00:00', QueueRenderer::countdown(36000));
    }

    public function testEmptyListShowsTheEmptyLabel(): void
    {
        $html = QueueRenderer::renderList(QueueRenderer::DOMAIN_BUILDINGS, array(), $this->labels());

        self::assertStringContainsString('Aucun chantier en cours', $html);
        self::assertStringNotContainsString('xnova-queue-item', $html);
    }

    public function testRunningAndMovableRowsExposeTheClientContract(): void
    {
        $items = array(
            array(
                'position' => 1,
                'element' => 1,
                'name' => 'Mine de m&eacute;tal',
                'icon' => '/public/xnova/buildings/1.gif',
                'level' => 5,
                'end_time' => time() + 3661,
                'mode' => 'build',
                'running' => true,
                'movable' => false,
            ),
            array(
                'position' => 2,
                'element' => 2,
                'name' => 'Mine de cristal',
                'icon' => '/public/xnova/buildings/2.gif',
                'level' => 3,
                'end_time' => time() + 60,
                'mode' => 'destroy',
                'running' => false,
                'movable' => true,
            ),
        );

        $html = QueueRenderer::renderList(QueueRenderer::DOMAIN_BUILDINGS, $items, $this->labels());

        // Premier element : en cours, non deplacable, annulation possible.
        self::assertStringContainsString('data-position="1" data-movable="0"', $html);
        self::assertStringContainsString('badge text-bg-primary">en cours', $html);
        self::assertStringContainsString('href="/game/buildings?cmd=cancel&amp;listid=1"', $html);

        // Second element : deplacable, demolition, retrait possible.
        self::assertStringContainsString('data-position="2" data-movable="1" draggable="true"', $html);
        self::assertStringContainsString('href="/game/buildings?listid=2&amp;cmd=remove"', $html);
        self::assertStringContainsString('text-danger">démolition', $html);

        // Le nom garde ses entites (réponse historique de $lang['tech']).
        self::assertStringContainsString('Mine de m&eacute;tal', $html);

        // Le compteur porte l'horodatage de fin : le client s'en sert pour decompter.
        self::assertStringContainsString('data-end-time="' . (time() + 3661) . '"', $html);
        self::assertStringContainsString('1:01:0', $html);

        // Une ligne = une seule ligne : aucun <br> dans le rendu.
        self::assertStringNotContainsString('<br', $html);
    }

    public function testResearchRowCancelsWithTheTechnologyIdentifier(): void
    {
        $html = QueueRenderer::renderItem(
            QueueRenderer::DOMAIN_RESEARCH,
            array(
                'position' => 1,
                'element' => 124,
                'name' => 'Espionnage',
                'icon' => '/public/xnova/buildings/124.gif',
                'level' => 0,
                'end_time' => time() + 30,
                'mode' => 'research',
                'running' => true,
                'movable' => false,
            ),
            $this->labels(QueueRenderer::DOMAIN_RESEARCH)
        );

        self::assertStringContainsString('href="/game/buildings?mode=research&amp;cmd=cancel&amp;tech=124"', $html);
        self::assertStringContainsString('Aucune recherche en cours', QueueRenderer::renderList(
            QueueRenderer::DOMAIN_RESEARCH,
            array(),
            $this->labels(QueueRenderer::DOMAIN_RESEARCH)
        ));
    }

    public function testQueuedResearchRowOffersRemoval(): void
    {
        $html = QueueRenderer::renderItem(
            QueueRenderer::DOMAIN_RESEARCH,
            array(
                'position' => 3,
                'element' => 106,
                'name' => 'Technologie Espionnage',
                'icon' => '/public/xnova/buildings/106.gif',
                'level' => 4,
                'end_time' => time() + 300,
                'mode' => 'research',
                'running' => false,
                'movable' => true,
            ),
            $this->labels(QueueRenderer::DOMAIN_RESEARCH)
        );

        self::assertStringContainsString(
            'href="/game/buildings?mode=research&amp;cmd=remove&amp;listid=3"',
            $html
        );
        self::assertStringContainsString('niveau 4', $html);
        self::assertStringContainsString('data-position="3" data-movable="1" draggable="true"', $html);
    }

    public function testHangarRowsCountUnitsAndHaveNoRemoveButton(): void
    {
        $labels = $this->labels(QueueRenderer::DOMAIN_HANGAR);
        self::assertSame('en fabrication', $labels['running']);

        $html = QueueRenderer::renderItem(
            QueueRenderer::DOMAIN_HANGAR,
            array(
                'position' => 2,
                'element' => 202,
                'name' => 'Chasseur l&eacute;ger',
                'icon' => '/public/xnova/buildings/202.gif',
                'count' => 7,
                'end_time' => time() + 120,
                'mode' => 'build',
                'running' => false,
                'movable' => true,
            ),
            $labels
        );

        self::assertStringContainsString('×7', $html);
        self::assertStringNotContainsString('cmd=remove', $html);
    }

    public function testRunningHangarRowHasNoCancelLink(): void
    {
        // Le lien « Interrompre » d'une unite en fabrication visait la file des
        // batiments (domaine deduit de la page) : il annulait un chantier de la
        // planete. Aucune action n'est donc rendue pour le hangar.
        $html = QueueRenderer::renderItem(
            QueueRenderer::DOMAIN_HANGAR,
            array(
                'position' => 1,
                'element' => 203,
                'name' => 'Chasseur lourd',
                'icon' => '/public/xnova/buildings/203.gif',
                'count' => 3,
                'end_time' => time() + 90,
                'mode' => 'build',
                'running' => true,
                'movable' => false,
            ),
            $this->labels(QueueRenderer::DOMAIN_HANGAR)
        );

        self::assertStringContainsString('badge text-bg-primary">en fabrication', $html);
        self::assertStringNotContainsString('cmd=cancel', $html);
        self::assertStringNotContainsString('cmd=remove', $html);
    }
}
