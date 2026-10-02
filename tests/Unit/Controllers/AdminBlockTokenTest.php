<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Un bloc rendu **à part** porte son propre jeton de sécurité.
 *
 * `TemplateEngine::parse()` remplace un marqueur inconnu par une **chaîne vide** (ou par
 * son nom quand `?DEBUG` est là). Un gabarit rendu séparément est donc rempli une fois
 * pour toutes : le `csrf_token` passé au corps de page ne redescend **jamais** dans un
 * bloc déjà rendu. Le bloc de téléversement des modules est tombé dans le piège — ses
 * deux formulaires (envoi du module, installation) envoyaient un `_token` vide, et la
 * page répondait « Jeton de sécurité manquant » à chaque fois.
 *
 * Le contrôle est **approximatif** par construction : il lit l'appel dans la source, sans
 * évaluer le PHP. Il regarde donc si le jeton apparaît dans les données qui suivent
 * l'appel ; il peut être indulgent (deux appels voisins), jamais l'inverse — un bloc
 * oublié est signalé.
 */
final class AdminBlockTokenTest extends TestCase
{
    /** Assez large pour couvrir les marqueurs d'un bloc, sans atteindre le suivant. */
    private const WINDOW = 2000;

    public function testEveryAdminBlockWithAFormGetsItsOwnToken(): void
    {
        $offenders = array();

        foreach ((array) glob(ROOT_PATH . 'app/Controllers/Back/*.php') as $file) {
            $source = (string) file_get_contents($file);

            preg_match_all('/adminTemplate\(\s*\'([a-z_0-9]+)\'/', $source, $matches);

            foreach (array_unique($matches[1] ?? array()) as $template) {
                $tpl = ROOT_PATH . 'app/View/OpenGame/admin/' . $template . '.tpl';

                if (!is_file($tpl) || !str_contains((string) file_get_contents($tpl), '{csrf_token}')) {
                    continue;
                }

                $start = strpos($source, "adminTemplate('" . $template . "'");

                if ($start === false || !str_contains(substr($source, $start, self::WINDOW), 'csrf_token')) {
                    $offenders[] = basename($file) . ' → ' . $template . '.tpl';
                }
            }
        }

        self::assertSame(
            array(),
            $offenders,
            'Un bloc qui contient un formulaire doit recevoir son csrf_token dans SES données.'
        );
    }

    /**
     * Le piège est celui du moteur : un marqueur inconnu disparaît.
     *
     * Ce test tient la raison d'être du précédent — si le moteur se met un jour à laisser
     * le marqueur en place (ou à le remplir depuis un contexte global), la règle change et
     * ce commentaire devient faux avant le code.
     */
    public function testAnUnknownMarkerIsReplacedByNothing(): void
    {
        $html = \App\Core\TemplateEngine::parse('avant {marqueur_absent} apres', array());

        self::assertSame('avant  apres', $html);
    }
}
