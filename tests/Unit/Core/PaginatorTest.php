<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Paginator;
use PHPUnit\Framework\TestCase;

/**
 * Pagination des tableaux d'administration.
 *
 * Les fonctions sont pures (aucune base, aucun gabarit complexe) : on vérifie les
 * bornes, la fenêtre de pages et le rendu de la barre de liens.
 */
final class PaginatorTest extends TestCase
{
    public function testPageIsAlwaysAValidNumber(): void
    {
        $this->assertSame(3, Paginator::page('3'));
        $this->assertSame(1, Paginator::page('0'));
        $this->assertSame(1, Paginator::page('-4'));
        $this->assertSame(1, Paginator::page('abc'));
        $this->assertSame(1, Paginator::page(null));
    }

    public function testCountKeepsAtLeastOnePage(): void
    {
        $this->assertSame(1, Paginator::count(0, 50));
        $this->assertSame(1, Paginator::count(50, 50));
        $this->assertSame(2, Paginator::count(51, 50));
        // Un pas nul ne divise pas par zéro : il vaut une ligne par page.
        $this->assertSame(10, Paginator::count(10, 0));
    }

    public function testWindowReturnsTheOffsetAndTheDisplayedRange(): void
    {
        $window = Paginator::window(137, 2, 50);

        $this->assertSame(2, $window['page']);
        $this->assertSame(3, $window['pages']);
        $this->assertSame(50, $window['offset']);
        $this->assertSame(51, $window['first']);
        $this->assertSame(100, $window['last']);
        $this->assertSame(137, $window['total']);
    }

    public function testWindowBoundsTheRequestedPage(): void
    {
        $tooHigh = Paginator::window(120, 99, 50);
        $tooLow = Paginator::window(120, -3, 50);

        $this->assertSame(3, $tooHigh['page']);
        $this->assertSame(100, $tooHigh['offset']);
        $this->assertSame(120, $tooHigh['last']);
        $this->assertSame(1, $tooLow['page']);
        $this->assertSame(0, $tooLow['offset']);
    }

    public function testWindowOnAnEmptyList(): void
    {
        $window = Paginator::window(0, 4, 50);

        $this->assertSame(1, $window['page']);
        $this->assertSame(1, $window['pages']);
        $this->assertSame(0, $window['first']);
        $this->assertSame(0, $window['last']);
    }

    public function testLinksShowTheEdgesAndTheWindowAroundTheCurrentPage(): void
    {
        $this->assertSame(array(1), Paginator::links(1, 1));
        $this->assertSame(array(1, 2, 3, 4), Paginator::links(4, 2));
        $this->assertSame(array(1, 2, 3, 4, 5, 6, 10), Paginator::links(10, 4));
        $this->assertSame(array(1, 8, 9, 10), Paginator::links(10, 10));
    }

    public function testLinksAreSortedAndUnique(): void
    {
        $links = Paginator::links(3, 2);

        $this->assertSame(array(1, 2, 3), $links);
        $this->assertSame($links, array_values(array_unique($links)));
    }

    public function testSizeBarCarriesTheTotalAndThePageSize(): void
    {
        $html = Paginator::sizeBar('/back/actions', array('days' => '7'), Paginator::window(4, 1, 10), array(
            'label' => 'Journal',
            'summary' => '%d a %d sur %d',
            'size' => 'par page',
        ));

        // Le total et le choix de la taille vivent au-dessus du tableau, et
        // restent affichés même quand une seule page suffit.
        self::assertStringContainsString('par page', $html);
        self::assertStringContainsString('1 a 4 sur 4', $html);
        self::assertStringContainsString('per_page=50', $html);
        self::assertStringNotContainsString('d-none', $html);
    }

    public function testRenderHidesTheEmptyNavigation(): void
    {
        $html = Paginator::render('/back/actions', array('days' => '7'), Paginator::window(4, 1, 10), array(
            'label' => 'Journal',
            'first' => 'F',
            'previous' => 'P',
            'next' => 'N',
            'last' => 'L',
        ));

        // Une seule page : la navigation est masquée, et les boutons de taille
        // n'y sont plus (ils sont dans la barre du haut).
        self::assertStringContainsString('d-none', $html);
        self::assertStringNotContainsString('btn-outline-secondary', $html);
    }

    public function testRenderKeepsTheFiltersAndMarksTheCurrentPage(): void
    {
        $html = Paginator::render('/back/actions', array('days' => '30', 'kind' => 'page'), Paginator::window(300, 2, 50), array(
            'label' => 'Journal',
            'first' => 'F',
            'previous' => 'P',
            'next' => 'N',
            'last' => 'L',
        ));

        self::assertStringContainsString('href="/back/actions?days=30&amp;kind=page&amp;per_page=50&amp;page=3"', $html);
        self::assertStringContainsString('page-link active', $html);
    }

    public function testRenderFollowsTheSortAndThePageSize(): void
    {
        $html = Paginator::render(
            '/back/actions',
            array('days' => '30'),
            Paginator::window(300, 2, 25),
            array(),
            array('field' => 'user', 'order' => 'asc')
        );

        // Tourner la page garde le tri et la taille choisis.
        self::assertStringContainsString('days=30&amp;per_page=25&amp;sort=user&amp;order=asc&amp;page=3', $html);
    }

    public function testSizeBarFollowsTheSortAndRestartsAtTheFirstPage(): void
    {
        $html = Paginator::sizeBar(
            '/back/actions',
            array('days' => '30'),
            Paginator::window(300, 2, 25),
            array('summary' => '%d a %d sur %d', 'size' => 'par page'),
            array('field' => 'user', 'order' => 'asc')
        );

        // Les liens de taille gardent le tri…
        self::assertStringContainsString('days=30&amp;per_page=100&amp;sort=user&amp;order=asc"', $html);
        self::assertStringContainsString('btn btn-sm btn-outline-secondary active', $html);
        // …et repartent de la première page : le décalage n'a plus de sens.
        self::assertStringNotContainsString('&amp;page=', $html);
        self::assertStringNotContainsString('?page=', $html);
        self::assertStringContainsString('26 a 50 sur 300', $html);
    }

    public function testRenderOmitsTheImpossibleLinksOnTheEdges(): void
    {
        $labels = array(
            'label' => 'Journal',
            'summary' => '%d a %d sur %d',
            'first' => 'F',
            'previous' => 'P',
            'next' => 'N',
            'last' => 'L',
        );

        $firstPage = Paginator::render('/back/actions', array(), Paginator::window(300, 1, 50), $labels);
        $lastPage = Paginator::render('/back/actions', array(), Paginator::window(300, 6, 50), $labels);

        // Au début, ni « premier » ni « précédent » ; à la fin, ni « suivant » ni « dernier ».
        $this->assertStringNotContainsString('>F<', $firstPage);
        $this->assertStringNotContainsString('>P<', $firstPage);
        $this->assertStringContainsString('>N<', $firstPage);
        $this->assertStringContainsString('>L<', $firstPage);
        $this->assertStringContainsString('>F<', $lastPage);
        $this->assertStringContainsString('>P<', $lastPage);
        $this->assertStringNotContainsString('>N<', $lastPage);
        $this->assertStringNotContainsString('>L<', $lastPage);
    }

    public function testSortKeepsOnlyKnownKeysAndMeaningfulOrders(): void
    {
        $allowed = array('time', 'user');

        self::assertSame(array('field' => 'user', 'order' => 'desc'), Paginator::sort('user', 'desc', $allowed, 'time'));
        self::assertSame(array('field' => 'time', 'order' => 'asc'), Paginator::sort('inconnu', 'asc', $allowed, 'time'));
        self::assertSame(array('field' => 'time', 'order' => 'asc'), Paginator::sort(null, null, $allowed, 'time'));
        // Un sens illisible ne devient jamais autre chose qu'ascendant.
        self::assertSame('asc', Paginator::sort('user', 'n\'importe quoi', $allowed, 'time')['order']);
        // Le sens, lui, accepte les majuscules ; la clé se compare à l'identique
        // (c'est le nom d'une colonne, pas une saisie libre).
        self::assertSame('desc', Paginator::sort('user', 'DESC', $allowed, 'time')['order']);
        self::assertSame('time', Paginator::sort('USER', 'desc', $allowed, 'time')['field']);
    }

    public function testNextOrderSwitchesOnlyTheSortedColumn(): void
    {
        $sort = array('field' => 'user', 'order' => 'asc');

        self::assertSame('desc', Paginator::nextOrder($sort, 'user'), 'Même colonne : le sens bascule.');
        self::assertSame('asc', Paginator::nextOrder($sort, 'time'), 'Autre colonne : on repart en ascendant.');
        self::assertSame('asc', Paginator::nextOrder(array('field' => 'user', 'order' => 'desc'), 'user'));
    }

    public function testPerPageAcceptsOnlyTheProposedSizes(): void
    {
        self::assertSame(10, Paginator::PER_PAGE, 'Dix lignes par page par défaut.');
        self::assertSame(25, Paginator::perPage('25'));
        self::assertSame(100, Paginator::perPage(100));
        self::assertSame(Paginator::PER_PAGE, Paginator::perPage('33'), 'Une taille non proposée retombe sur la valeur par défaut.');
        self::assertSame(Paginator::PER_PAGE, Paginator::perPage('abc'));
        self::assertSame(Paginator::PER_PAGE, Paginator::perPage(null));
    }

    public function testHeaderMarksTheSortedColumnAndSwitchesItsOrder(): void
    {
        $labels = array('asc' => 'HAUT', 'desc' => 'BAS', 'none' => 'RIEN');
        $sort = array('field' => 'user', 'order' => 'asc');

        $here = Paginator::header('/back/actions', array('days' => '7'), $sort, 'user', 'Compte', $labels);
        $there = Paginator::header('/back/actions', array('days' => '7'), $sort, 'time', 'Date', $labels, 'end');

        self::assertStringContainsString('HAUT', $here);
        self::assertStringContainsString('active', $here);
        self::assertStringContainsString('sort=user&amp;order=desc', $here, 'Cliquer sur la colonne triée inverse le sens.');
        self::assertStringNotContainsString('page=', $here, 'Changer de tri ramène à la première page.');

        self::assertStringContainsString('RIEN', $there);
        self::assertStringContainsString('text-end', $there);
        self::assertStringContainsString('sort=time&amp;order=asc', $there);
    }
}
