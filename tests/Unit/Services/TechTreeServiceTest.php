<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TechTreeService;
use PHPUnit\Framework\TestCase;

/**
 * Arbre des technologies : l'ordre des sections.
 *
 * L'arbre ne peut pas se contenter de parcourir `$lang['tech']` dans l'ordre du
 * tableau : le libellé d'une unité ajoutée par un module y est fusionné **avant**
 * ceux du jeu, si bien que l'unité s'affichait en tête de l'arbre, hors de sa
 * section — vécu avec l'extracteur (vaisseau 216), qui précédait la mine de métal
 * au lieu de figurer parmi les vaisseaux. Les sections viennent donc des
 * **catégories du jeu** (`$reslist`), que les modules complètent.
 */
final class TechTreeServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $sauvegarde = array();

    protected function setUp(): void
    {
        $this->sauvegarde = array(
            'lang' => $GLOBALS['lang'] ?? null,
            'resource' => $GLOBALS['resource'] ?? null,
            'reslist' => $GLOBALS['reslist'] ?? null,
            'requeriments' => $GLOBALS['requeriments'] ?? null,
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->sauvegarde as $cle => $valeur) {
            $GLOBALS[$cle] = $valeur;
        }
    }

    /** Une unité ajoutée par un module rejoint la section de sa catégorie. */
    public function testAUnitDeclaredFirstJoinsItsSection(): void
    {
        // Le libellé du module arrive en tête du tableau de langue, comme en vrai.
        $GLOBALS['lang'] = array(
            'tech' => array(216 => 'Extracteur', 0 => 'Bâtiments', 200 => 'Vaisseaux', 1 => 'Mine de métal', 21 => 'Chantier spatial', 202 => 'Petit transporteur'),
            'level' => 'Niveau',
            'treeinfo' => 'info',
            'Requirements' => 'Nécessite',
            'Tech' => 'Technologies',
        );
        // `21` est le chantier spatial : un prérequis se lit par la colonne que
        // `$resource` lui donne, sinon la lecture d'un niveau lève un avertissement.
        $GLOBALS['resource'] = array(1 => 'metal_mine', 21 => 'hangar', 202 => 'small_ship_cargo', 216 => 'extractor');
        $GLOBALS['reslist'] = array('build' => array(1), 'fleet' => array(202, 216));
        $GLOBALS['requeriments'] = array(202 => array(21 => 2), 216 => array(21 => 12));

        $html = (new TechTreeService())->buildPage(array(), array());

        $sections = strpos($html, 'Vaisseaux');
        $transporteur = strpos($html, 'Petit transporteur');
        $extracteur = strpos($html, 'Extracteur');
        $mine = strpos($html, 'Mine de métal');

        self::assertNotFalse($sections);
        self::assertNotFalse($extracteur);
        self::assertGreaterThan($sections, $transporteur, 'Le transporteur est dans sa section.');
        self::assertGreaterThan($transporteur, $extracteur, 'L\'extracteur suit les vaisseaux du jeu.');
        self::assertGreaterThan($mine, $extracteur, 'L\'extracteur n\'est plus en tête de l\'arbre.');
    }

    /** Un élément n'est rendu qu'une fois, même si deux catégories le listent. */
    public function testAnElementIsRenderedOnce(): void
    {
        $GLOBALS['lang'] = array(
            'tech' => array(0 => 'Bâtiments', 200 => 'Vaisseaux', 1 => 'Mine de métal'),
            'level' => 'Niveau',
            'treeinfo' => 'info',
            'Requirements' => 'Nécessite',
            'Tech' => 'Technologies',
        );
        $GLOBALS['resource'] = array(1 => 'metal_mine');
        // `prod` double `build` : la mine ne doit pas apparaître deux fois.
        $GLOBALS['reslist'] = array('build' => array(1), 'prod' => array(1));
        $GLOBALS['requeriments'] = array();

        $html = (new TechTreeService())->buildPage(array(), array());

        self::assertSame(1, substr_count($html, '>Mine de métal<'), 'Un élément, une ligne.');
    }

    /**
     * Le Coeur de l'application n'ajoute aucune section de lui-même.
     *
     * Une catégorie qui n'appartient pas au jeu (les officiers) vient d'un module : sa section
     * est rendue par `extraSections()`, vide ici. Le cas du module est tenu par
     * `modules/officier/tests/TechTreeServiceTest.php`.
     */
    public function testTheCoreAddsNoSectionOfItsOwn(): void
    {
        $method = new \ReflectionMethod(TechTreeService::class, 'extraSections');
        $method->setAccessible(true);

        self::assertSame(array(), $method->invoke(new TechTreeService()));
    }
}
