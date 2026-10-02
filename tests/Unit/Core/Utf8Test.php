<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Database\Connection;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Tout le jeu parle UTF-8, et la connexion l'annonce.
 *
 * La connexion historique (`db/mysql.php`) ne fixait aucun jeu de caractères :
 * MySQL la tenait pour du latin1 et **réencodait chaque écriture**, et comme la
 * connexion moderne réutilisait le même handle, c'est tout le jeu qui écrivait du
 * mojibake — « planète mère » arrivait en base sous la forme « planÃ¨te mÃ¨re ».
 *
 * Il n'y a plus qu'**un seul** créateur de connexion (`Connection::open()`, passé à
 * PDO) : le jeu de caractères part dans la source (DSN) et le shim historique
 * n'ouvre plus rien lui-même.
 */
final class Utf8Test extends TestCase
{
    public function testTheTwoConnectionsAnnounceTheSameCharset(): void
    {
        $legacy = (string) file_get_contents(ROOT_PATH . 'db/mysql.php');
        $modern = (string) file_get_contents(ROOT_PATH . 'app/Database/Connection.php');

        self::assertSame('utf8', Connection::CHARSET);

        self::assertStringContainsString(
            "';charset=' . self::CHARSET",
            $modern,
            'La connexion annonce son jeu de caractères dans la source (DSN) : sans cela, '
            . 'MySQL lit les octets UTF-8 comme du latin1 et les réencode.'
        );

        self::assertStringContainsString(
            'Connection::open(',
            $legacy,
            'Le shim historique passe par la connexion du Coeur d\'application : deux '
            . 'créateurs de connexion, c\'est ce qui avait produit le double encodage.'
        );

        self::assertStringNotContainsString(
            'mysqli_',
            $legacy,
            'La couche base est passée à PDO : plus aucun appel à mysqli.'
        );
    }

    public function testNoTemplateAnnouncesAnotherCharset(): void
    {
        $offenders = array();

        foreach (self::files(array('tpl')) as $relative => $path) {
            $source = (string) file_get_contents($path);

            // La valeur est **capturée puis comparée** : une citation optionnelle dans
            // l'expression laisserait passer `charset="utf-8"` lui-même.
            if (preg_match('/charset\s*=\s*["\']?([\w-]+)/i', $source, $matches) !== 1) {
                continue;
            }

            if (strtolower($matches[1]) !== 'utf-8') {
                $offenders[] = $relative;
            }
        }

        self::assertSame(
            array(),
            $offenders,
            'Les gabarits annoncent l\'UTF-8 : un autre jeu de caractères affiche du mojibake.'
        );
    }

    public function testTheSourcesAreUtf8WithoutBom(): void
    {
        $offenders = array();

        foreach (self::files(array('php', 'mo', 'tpl')) as $relative => $path) {
            $source = (string) file_get_contents($path);

            if (str_starts_with($source, "\xEF\xBB\xBF")) {
                $offenders[] = $relative . ' (BOM)';
                continue;
            }

            if (!mb_check_encoding($source, 'UTF-8')) {
                $offenders[] = $relative . ' (pas UTF-8)';
            }
        }

        self::assertSame(
            array(),
            $offenders,
            'Les sources sont en UTF-8 sans BOM : un fichier latin1 s\'affiche en mojibake.'
        );
    }

    /**
     * Fichiers du jeu, par extension, indexés par leur chemin relatif.
     *
     * @param list<string> $extensions
     *
     * @return array<string, string>
     */
    private static function files(array $extensions): array
    {
        $directories = array('app', 'db', 'includes', 'language', 'tests', 'tools', 'ws');
        $found = array();

        foreach ($directories as $directory) {
            $root = ROOT_PATH . $directory;

            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
                    continue;
                }

                $path = $file->getPathname();
                $found[str_replace('\\', '/', substr($path, strlen(ROOT_PATH)))] = $path;
            }
        }

        return $found;
    }
}
