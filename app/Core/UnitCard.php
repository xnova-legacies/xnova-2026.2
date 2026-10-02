<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rendu d'une unité constructible du hangar (vaisseau ou défense).
 *
 * Le chantier spatial et la défense partagent la même présentation : une seule
 * implémentation, sinon les deux pages divergent — c'est ce qui était arrivé
 * (la défense n'avait ni zone de saisie large ni bouton « Nombre max »).
 *
 * Le nom et la description viennent de `$lang` (`tech` et `res.descriptions`) :
 * ils contiennent déjà des entités HTML et sont insérés tels quels, comme dans
 * le rendu historique.
 */
final class UnitCard
{
    /**
     * Rendus des unités accessibles d'une plage d'identifiants.
     *
     * L'ordre suit `$lang['tech']`, comme la boucle historique des deux pages.
     *
     * @param array<string, mixed> $user    joueur (prix et durée en dépendent)
     * @param array<string, mixed> $planet  planète active
     * @param callable(int): string|null $note remplace la saisie d'un élément
     *        quand elle est impossible (ex. « un seul » pour les boucliers)
     */
    public static function forRange(array $user, array $planet, int $from, int $to, ?callable $note = null): string
    {
        $html = '';
        $tabindex = 0;

        foreach (array_keys(Language::all()['tech'] ?? array()) as $element) {
            $element = (int) $element;

            if ($element < $from || $element > $to) {
                continue;
            }

            $html .= self::forElement(
                $user,
                $planet,
                $element,
                $tabindex,
                $note !== null ? (string) $note($element) : ''
            );
        }

        return $html;
    }

    /**
     * Rendu HTML d'une unité ('' si elle n'est pas accessible).
     *
     * `$tabindex` est incrémenté quand une saisie est réellement rendue : la
     * numérotation reste celle du rendu historique.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $planet
     */
    public static function forElement(
        array $user,
        array $planet,
        int $element,
        int &$tabindex,
        string $note = ''
    ): string {
        if (!IsTechnologieAccessible($user, $planet, $element)) {
            return '';
        }

        $lang = Language::all();
        $resource = GameData::resource();
        $name = (string) ($lang['tech'][$element] ?? '');
        $count = (int) ($planet[$resource[$element]] ?? 0);

        // Saisie : un tiret quand l'unité n'est pas abordable, la mention
        // fournie par la page quand la saisie n'a pas de sens (bouclier déjà
        // construit), sinon le champ de quantité. L'ordre reprend exactement
        // celui du rendu historique.
        $input = '<span class="text-body-secondary">-</span>';

        if (IsElementBuyable($user, $planet, $element, false)) {
            if ($note === '') {
                $tabindex++;
                $input = self::input($element, $name, $tabindex, self::maxElements($user, $planet, $element));
            } else {
                $input = self::note($note);
            }
        }

        $parse = array(
            'dpath' => $GLOBALS['dpath'] ?? '',
            'unit_id' => $element,
            'unit_name' => $name,
            'unit_count' => $count === 0 ? '' : ' (' . ($lang['dispo'] ?? '') . ': ' . pretty_number($count) . ')',
            'unit_descr' => (string) ($lang['res']['descriptions'][$element] ?? ''),
            'unit_price' => GetElementPrice($user, $planet, $element, false),
            'unit_time' => ShowBuildTime(GetBuildingTime($user, $planet, $element)),
            'unit_input' => $input,
        );

        return parsetemplate(gettemplate('buildings_unit_row'), $parse);
    }

    /**
     * Quantité proposée par « Nombre max ».
     *
     * Plafonnée par la place d'une ligne de file (`MAX_UNITS_PER_ROW`), et
     * ramenée à 1 quand la construction prend du temps — c'est la règle du
     * chantier spatial, elle vaut donc aussi pour les défenses.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $planet
     */
    public static function maxElements(array $user, array $planet, int $element): int
    {
        $max = (int) GetMaxConstructibleElements($element, $planet);
        $limit = GameConstants::maxUnitsPerRow();

        if ($max > $limit) {
            $max = $limit;
        }

        if (GetBuildingTime($user, $planet, $element) > 0) {
            $max = 1;
        }

        // Un élément unique (bouclier) ne se commande qu'en un exemplaire : le
        // bouton « Nombre max » proposait la place de la ligne, pas la règle.
        if (\App\Services\ShipyardService::isUnique($element)) {
            $max = min($max, 1);
        }

        return $max;
    }

    /**
     * Zone de saisie d'une unité : champ de quantité et bouton « Nombre max ».
     *
     * C'est le balisage commun aux deux pages du hangar ; `$max` = 0 affiche un
     * tiret (unité non abordable), comme le faisait le chantier spatial.
     */
    public static function input(int $element, string $name, int $tabindex, int $max): string
    {
        if ($max < 1) {
            return '<span class="text-body-secondary">-</span>';
        }

        $field = 'amounts[' . $element . ']';
        $label = htmlspecialchars(html_entity_decode($name, ENT_QUOTES), ENT_QUOTES);

        $html = '<div class="input-group input-group-sm">';
        $html .= '<input type="text" class="form-control" id="' . $field . '" name="' . $field . '"';
        $html .= ' value="0" tabindex="' . $tabindex . '" aria-label="' . $label . '">';
        $html .= '<button type="button" class="btn btn-outline-secondary"';
        $html .= ' onclick="document.getElementById(\'' . $field . '\').value=\'' . $max . '\';">';
        $html .= 'Nombre max (' . $max . ')</button>';
        $html .= '</div>';

        return $html;
    }

    /** Mention affichée à la place de la saisie (déjà localisée). */
    public static function note(string $text): string
    {
        return '<span class="text-danger small">' . $text . '</span>';
    }

    /**
     * File d'attente du hangar (vaisseaux et défenses partagent la même file).
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $planet
     */
    public static function queue(array $user, array $planet): string
    {
        if ((string) ($planet['b_hangar_id'] ?? '') === '') {
            return '';
        }

        return ElementBuildListBox($user, $planet);
    }
}
