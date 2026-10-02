<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Modules;
use App\Core\Project;
use PHPUnit\Framework\TestCase;

/**
 * Le projet se décrit dans son manifeste, et la version n'est écrite qu'une fois.
 *
 * La version vivait dans un `define()` de `common.php`, en double avec la plus récente
 * entrée du journal des modifications — et les deux avaient dérivé (la constante
 * annonçait 2026.25 quand le journal était à 2026.27). Elle est maintenant lue dans le
 * manifeste, et ce test tient la règle : les deux avancent ensemble.
 */
final class ProjectTest extends TestCase
{
    public function testTheProjectManifestDeclaresItsIdentity(): void
    {
        self::assertFileExists(APP_ROOT . '/' . Modules::MANIFEST, 'Le projet a son manifeste à la racine.');

        foreach (array('name', 'label', 'description', 'version', 'author') as $key) {
            self::assertNotSame(
                '',
                Project::value($key),
                'Le manifeste du projet déclare « ' . $key . ' ».'
            );
        }

        self::assertSame(Project::value('name'), Project::name());
        self::assertSame(Project::value('label'), Project::label());
        self::assertSame(Project::value('description'), Project::description());
        self::assertSame(Project::value('author'), Project::author());
        self::assertSame(Project::value('version'), Project::version());
    }

    public function testTheVersionIsNoLongerAConstant(): void
    {
        $common = (string) file_get_contents(APP_ROOT . '/common.php');

        self::assertStringNotContainsString(
            "define('VERSION'",
            $common,
            'La version est lue dans le manifeste du projet : plus de constante à tenir à jour en double.'
        );

        self::assertNotSame('', Project::version(), 'Le manifeste déclare la version livrée.');
    }

    public function testTheVersionMatchesTheLatestChangelogEntry(): void
    {
        $changelog = (string) file_get_contents(APP_ROOT . '/language/fr/changelog.mo');

        // La plus récente entrée est la première du tableau : elle porte le numéro.
        self::assertSame(
            1,
            preg_match('/text-success">([0-9][0-9.]*)</', $changelog, $matches),
            'Le journal des modifications porte au moins une entrée de version.'
        );

        self::assertSame(
            $matches[1],
            Project::version(),
            'Le manifeste du projet et la plus récente entrée du journal portent le même numéro.'
        );
    }
}
