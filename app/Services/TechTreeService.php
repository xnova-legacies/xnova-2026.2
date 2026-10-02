<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Arbre des technologies (ex `ProfilController::techtreeAction`).
 *
 * Le contenu est rendu ici pour que l'onglet « Technologies » de la vue générale
 * et l'ancienne adresse `/game/profil` affichent exactement la même page.
 */
class TechTreeService
{
    /**
     * Page complète de l'arbre (liste des technologies et de leurs prérequis).
     *
     * Reprend tel quel le rendu historique : les éléments sans niveau disponible
     * ou dont les prérequis ne sont pas remplis restent affichés, en rouge.
     */
    /**
     * Sections de l'arbre, dans l'ordre : `[titre, catégorie, borne haute]`.
     *
     * Le titre est une clé de `$lang['tech']` qui ne désigne **aucun** élément, et les
     * bornes sont les siennes (0, 40, 100, 200, 400, 600) : une section porte les
     * éléments de sa catégorie dont l'identifiant va du titre à la borne suivante. Les
     * deux sections de bâtiments se partagent la catégorie `build` — les « spéciaux »
     * sont ceux qui viennent après 40.
     *
     * L'ordre ne peut pas venir de `$lang['tech']` : le libellé d'une unité ajoutée par
     * un module y est fusionné **avant** ceux du jeu, si bien que l'unité s'affichait en
     * tête de l'arbre, hors de sa section — vécu avec l'extracteur (vaisseau 216), qui
     * précédait la mine de métal au lieu de figurer parmi les vaisseaux. Les catégories
     * (`$reslist`), elles, portent les unités des modules : chacune rejoint sa section.
     *
     * Un module qui apporte une **catégorie à lui** — les officiers, par exemple — ajoute sa
     * section par `extraSections()` : ses éléments ne sont pas ceux du jeu.
     *
     * @var list<array{0: int, 1: string, 2: int}>
     */
    private const SECTIONS = array(
        array(0, 'build', 40),
        array(40, 'build', 100),
        array(100, 'tech', 200),
        array(200, 'fleet', 400),
        array(400, 'defense', 600),
    );

    /**
     * Sections ajoutées par un module, à la suite de celles du jeu.
     *
     * Le Coeur de l'application ne connaît que ses propres catégories : un module qui en apporte
     * une (les officiers, par exemple) rend ici sa section, et elle se place après — chaque
     * section ne rend que les éléments de sa catégorie, l'ordre n'a donc pas d'autre enjeu que la
     * lecture. Sans module, l'arbre est exactement celui du jeu.
     *
     * @return list<array{0: int, 1: string, 2: int}>
     */
    protected function extraSections(): array
    {
        return array();
    }

    public function buildPage(array $user, array $planetRow): string
    {
        global $lang, $resource;

        $requeriments = $GLOBALS['requeriments'] ?? array();
        $reslist = $GLOBALS['reslist'] ?? array();

        $headTpl = gettemplate('techtree_head');
        $rowTpl = gettemplate('techtree_row');

        $page = '';

        foreach (array_merge(self::SECTIONS, $this->extraSections()) as $section) {
            list($titre, $categorie, $borneHaute) = $section;

            $page .= parsetemplate($headTpl, array(
                'tt_name' => (string) ($lang['tech'][$titre] ?? ''),
                'Requirements' => $lang['Requirements'] ?? '',
            ));

            foreach ((array) ($reslist[$categorie] ?? array()) as $candidat) {
                $element = (int) $candidat;

                // Les catégories se chevauchent (`prod` double `build`) et les bornes
                // séparent les bâtiments des bâtiments spéciaux : une seule section
                // rend chaque élément.
                if ($element < $titre || $element >= $borneHaute || !isset($resource[$element])) {
                    continue;
                }

                $parse = array();
                $parse['tt_name'] = $lang['tech'][$element] ?? '';

                if (isset($requeriments[$element])) {
                    $parse['required_list'] = '';

                    foreach ($requeriments[$element] as $resClass => $level) {
                        if (isset($user[$resource[$resClass]]) && $user[$resource[$resClass]] >= $level) {
                            $parse['required_list'] .= '<span class="d-block text-success">';
                        } elseif (isset($planetRow[$resource[$resClass]]) && $planetRow[$resource[$resClass]] >= $level) {
                            $parse['required_list'] .= '<span class="d-block text-success">';
                        } else {
                            $parse['required_list'] .= '<span class="d-block text-danger">';
                        }

                        $parse['required_list'] .= $lang['tech'][$resClass] . ' (' . ($lang['level'] ?? '') . ' ' . $level . ')';
                        $parse['required_list'] .= '</span>';
                    }

                    $parse['tt_detail'] = '<a href="/game/profil/techdetails?techid=' . $element . '">' . ($lang['treeinfo'] ?? '') . '</a>';
                } else {
                    $parse['required_list'] = '';
                    $parse['tt_detail'] = '';
                }

                $parse['tt_info'] = $element;
                $page .= parsetemplate($rowTpl, $parse);
            }
        }

        $parse = array(
            'techtree_list' => $page,
            'Tech' => $lang['Tech'] ?? '',
        );

        return parsetemplate(gettemplate('techtree_body'), $parse);
    }
}
