<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rapport d'espionnage : mise en forme uniquement.
 *
 * Le mode 0 décrit la planète espionnée (coordonnées, matières premières, énergie),
 * les modes 1 à 4 listent ce qui a été vu (flotte, défenses, bâtiments,
 * technologies). Les **règles** — quelle catégorie est révélée selon le niveau
 * d'espionnage — restent dans `FleetMissionService::spy()` ; ici on ne fait que
 * compter et ranger.
 *
 * Comme pour le rapport de combat, tout le balisage vit dans des gabarits
 * (`spy_report*.tpl`) : `SpyTarget()` ne calcule plus que les lignes et le total.
 */
final class SpyReport
{
    /**
     * Lignes d'une catégorie : une entrée par élément réellement présent, plus le
     * total des quantités vues (il sert au calcul du niveau d'information du
     * rapport, dans le service de mission). Fonction pure.
     *
     * @param array<string, mixed> $target planète ou utilisateur (colonnes du schéma)
     * @param list<array{0: int, 1: int}> $ranges plages d'identifiants à parcourir
     * @param array<int, string> $names libellés par identifiant (`$lang['tech']`)
     * @return array{rows: list<array{label: string, amount: string}>, count: int}
     */
    public static function rows(array $target, array $ranges, array $names): array
    {
        $resource = GameData::resource();
        $rows = array();
        $count = 0;

        foreach ($ranges as $range) {
            for ($id = (int) $range[0]; $id <= (int) $range[1]; $id++) {
                $column = $resource[$id] ?? null;

                if ($column === null) {
                    continue;
                }

                $amount = (int) ($target[$column] ?? 0);

                if ($amount <= 0) {
                    continue;
                }

                $rows[] = array(
                    'label' => (string) ($names[$id] ?? $column),
                    'amount' => Format::decimal($amount),
                );
                $count += $amount;
            }
        }

        return array('rows' => $rows, 'count' => $count);
    }

    /**
     * Tuile « matières premières sur la planète » : coordonnées et relevé.
     *
     * @param array<string, mixed> $planet
     * @param array<string, string> $labels libellés du jeu ($lang)
     */
    public static function resources(array $planet, string $title, array $labels, string $coordinates = ''): string
    {
        $rows = array();

        foreach (
            array('metal' => 'Metal', 'crystal' => 'Crystal', 'deuterium' => 'Deuterium',
                       'energy_max' => 'Energy') as $column => $label
        ) {
            $rows[] = array(
                'label' => (string) ($labels[$label] ?? $label),
                'amount' => Format::decimal($planet[$column] ?? 0),
            );
        }

        $heading = $title . ' ' . (string) ($planet['name'] ?? '');

        if ($coordinates !== '') {
            $heading .= ' ' . $coordinates;
        }

        return self::section($heading, $rows);
    }

    /**
     * Un bloc du rapport : un titre et un tableau « élément / quantité ».
     *
     * C'est le gabarit commun aux cinq catégories : les ressources de la planète,
     * puis ce que l'espionnage a réellement vu.
     *
     * @param list<array{label: string, amount: string}> $rows
     */
    public static function section(string $title, array $rows): string
    {
        $body = '';

        foreach ($rows as $row) {
            $body .= TemplateEngine::render('spy_report_row', array(
                'row_label' => $row['label'],
                'row_amount' => $row['amount'],
            ));
        }

        return TemplateEngine::render('spy_report_section', array(
            'section_title' => $title,
            'section_rows' => $body,
            // Un bloc sans élément garde son tiret, masqué quand il y a des lignes :
            // le balisage reste dans le gabarit, seule la classe change.
            'section_empty' => $rows === array() ? '' : 'd-none',
        ));
    }

    /**
     * Le rapport complet : en-tête (cible et coordonnées), blocs révélés, puis
     * l'issue de l'espionnage et le raccourci vers l'attaque.
     *
     * @param array<string, mixed> $data
     */
    public static function assemble(array $data): string
    {
        return TemplateEngine::render('spy_report', array(
            'spy_title' => (string) ($data['title'] ?? ''),
            'spy_target' => (string) ($data['target'] ?? ''),
            'spy_coordinates' => (string) ($data['coordinates'] ?? ''),
            'spy_date' => (string) ($data['date'] ?? ''),
            'spy_sections' => (string) ($data['sections'] ?? ''),
            'spy_hint' => (string) ($data['hint'] ?? ''),
            'spy_fate' => (string) ($data['fate'] ?? ''),
            'spy_fate_variant' => (string) ($data['fate_variant'] ?? 'secondary'),
            'spy_attack_url' => (string) ($data['attack_url'] ?? ''),
            'spy_attack_label' => (string) ($data['attack_label'] ?? ''),
        ));
    }
}
