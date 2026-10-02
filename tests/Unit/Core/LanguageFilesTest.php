<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use ParseError;

/**
 * Les fichiers de langue sont du **PHP valide**.
 *
 * `language/` et `modules/<nom>/language/` sont des fichiers PHP inclus au vol : une
 * apostrophe non échappée dans une chaîne entre `'` casse le fichier **entier**, et le
 * jeu tombe en erreur fatale à la première page qui le charge (vécu sur `changelog.mo`,
 * où une entrée contenait `d'univers`). `php -l` le voit, `composer test` ne le voyait
 * pas : ce test comble le trou, et il balaie aussi les modules.
 */
final class LanguageFilesTest extends TestCase
{
    /** @return array<int, string> */
    private static function files(): array
    {
        $files = array();

        foreach (
            array(
                ROOT_PATH . 'language/*/*.mo',
                ROOT_PATH . 'language/*/*/*.mo',
                ROOT_PATH . 'modules/*/language/*/*.mo',
            ) as $pattern
        ) {
            foreach ((array) glob($pattern) as $file) {
                $files[] = (string) $file;
            }
        }

        sort($files);

        return $files;
    }

    public function testEveryLanguageFileIsValidPhp(): void
    {
        $files = self::files();

        self::assertNotSame(array(), $files, 'Les fichiers de langue du jeu doivent être trouvés.');

        $problems = array();

        foreach ($files as $file) {
            try {
                token_get_all((string) file_get_contents($file), TOKEN_PARSE);
            } catch (ParseError $error) {
                $problems[] = str_replace(array(ROOT_PATH, '\\'), array('', '/'), $file) . ' : ' . $error->getMessage();
            }
        }

        self::assertSame(
            array(),
            $problems,
            "Un fichier de langue doit rester du PHP valide (apostrophe échappée).\n" . implode("\n", $problems)
        );
    }
}
