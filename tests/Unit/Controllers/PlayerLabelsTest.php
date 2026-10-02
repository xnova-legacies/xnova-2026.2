<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Une page d'administration ne doit pas afficher de clé de langue brute : un
 * marqueur de gabarit non rempli devient une chaîne vide (et son nom, en mode
 * `?DEBUG`), un libellé cherché par `$lang[...] ?? $clé` retombe sur la clé.
 *
 * Deux familles sont vérifiées ici pour la fiche joueur :
 *  - les marqueurs `{pal_*}` des gabarits qui ne sont pas des données du
 *    contrôleur doivent exister dans `language/fr/admin/player.mo` ;
 *  - les clés cherchées par indirection (`$lang[$label] ?? $label`, la liste des
 *    chiffres de l'univers) doivent y exister aussi.
 */
final class PlayerLabelsTest extends TestCase
{
    /** Gabarits de la fiche joueur (contrôleur PlayerController). */
    private const TEMPLATES = array(
        'player_body.tpl',
        'player_element_row.tpl',
        'player_elements.tpl',
        'player_extra.tpl',
        'player_fleet.tpl',
        'player_fleets.tpl',
        'player_info_row.tpl',
        'player_pick.tpl',
        'player_planet_row.tpl',
        'player_planets.tpl',
        'player_stat.tpl',
        'player_state.tpl',
    );

    public function testTemplateLabelsExistInLanguageFile(): void
    {
        $source = $this->controller();
        $missing = array();

        foreach (self::TEMPLATES as $template) {
            $markers = $this->markers((string) file_get_contents($this->templatePath($template)));

            foreach ($markers as $marker) {
                // Une donnée du contrôleur ('' ou ' d-none', un badge...) porte le
                // même nom que le marqueur : elle est fournie, pas traduite.
                if (str_contains($source, "'{$marker}'")) {
                    continue;
                }

                if (!$this->isTranslated($marker)) {
                    $missing[] = $template . ' : {' . $marker . '}';
                }
            }
        }

        sort($missing);

        self::assertSame(
            array(),
            $missing,
            'Ajouter la clé manquante dans language/fr/admin/player.mo.'
        );
    }

    public function testIndirectLabelsExistInLanguageFile(): void
    {
        $keys = $this->statKeys();
        $missing = array();

        foreach ($keys as $key) {
            if (!$this->isTranslated($key)) {
                $missing[] = $key;
            }
        }

        sort($missing);

        self::assertSame(
            array(),
            $missing,
            "Les chiffres de l'univers cherchent \$lang[\$cle] : chaque cle doit exister."
        );

        self::assertNotSame(
            array(),
            $keys,
            "La liste des chiffres de l'univers a disparu du contrôleur."
        );
    }

    /** Clés du tableau de bord cherchées par `$lang[$label] ?? $label`. */
    private function statKeys(): array
    {
        preg_match_all("/'(pal_stat_[a-z_]+)'\s*=>/", $this->controller(), $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @return list<string> */
    private function markers(string $template): array
    {
        preg_match_all('/\{(pal_[a-z_0-9]+)\}/', $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function isTranslated(string $key): bool
    {
        return preg_match(
            "/\\\$lang\['" . preg_quote($key, '/') . "'\]/",
            (string) file_get_contents($this->languagePath())
        ) === 1;
    }

    private function controller(): string
    {
        return (string) file_get_contents(ROOT_PATH . 'app/Controllers/Back/PlayerController.php');
    }

    private function templatePath(string $template): string
    {
        return ROOT_PATH . 'app/View/OpenGame/admin/' . $template;
    }

    private function languagePath(): string
    {
        return ROOT_PATH . 'language/fr/admin/player.mo';
    }
}
