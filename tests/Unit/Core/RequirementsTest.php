<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Requirements;
use PHPUnit\Framework\TestCase;

/**
 * Les prérequis de l'installation : la liste, et ce qui est vraiment vérifié.
 *
 * Les tests de la machine de développement échoueraient s'ils exigeaient un prérequis
 * **absent** : on vérifie donc la **forme** et les règles pures, plus les deux cas que
 * l'on maîtrise (un dossier inscriptible, un dossier absent).
 */
final class RequirementsTest extends TestCase
{
    public function testEveryRequirementNamesAKeyAndADetail(): void
    {
        $requirements = (new Requirements())->all(sys_get_temp_dir() . '/xnova-requirements-' . getmypid());

        self::assertNotSame(array(), $requirements);

        foreach ($requirements as $requirement) {
            self::assertArrayHasKey('key', $requirement);
            self::assertArrayHasKey('ok', $requirement);
            self::assertArrayHasKey('detail', $requirement);
            self::assertStringStartsWith('ins_chk_', $requirement['key']);
            self::assertIsBool($requirement['ok']);
        }

        // Les clés annoncées sont celles du catalogue, sans doublon inattendu.
        self::assertContains('ins_chk_php', array_column($requirements, 'key'));
        self::assertContains('ins_chk_ext', array_column($requirements, 'key'));
        self::assertContains('ins_chk_vendor', array_column($requirements, 'key'));
    }

    public function testPhpFloorFollowsTheDeclaredMinimum(): void
    {
        // Le plancher est 8.3 : la machine qui fait tourner la suite doit le satisfaire,
        // et une version plus basse ne le satisferait pas.
        self::assertSame(80300, Requirements::MINIMUM_PHP);
        self::assertTrue(PHP_VERSION_ID >= Requirements::MINIMUM_PHP, 'La suite tourne sur le plancher déclaré.');

        $php = (new Requirements())->all(sys_get_temp_dir());
        $checked = array_values(array_filter($php, static function (array $entry): bool {
            return $entry['key'] === 'ins_chk_php';
        }));

        self::assertCount(1, $checked);
        self::assertTrue($checked[0]['ok']);
    }

    public function testTheLayersThisProjectNeedsAreDeclared(): void
    {
        // La couche base repose sur PDO, l'UTF-8 des sources sur mbstring, les archives
        // de la mise à jour sur phar : les retirer d'ici les rendrait invisibles.
        foreach (array('pdo', 'pdo_mysql', 'mbstring', 'phar') as $extension) {
            self::assertContains($extension, Requirements::EXTENSIONS);
        }
    }

    public function testSatisfiedIsFalseAsSoonAsOneRequirementFails(): void
    {
        self::assertTrue(Requirements::satisfied(array(
            array('key' => 'ins_chk_php', 'ok' => true, 'detail' => ''),
        )));

        self::assertFalse(Requirements::satisfied(array(
            array('key' => 'ins_chk_php', 'ok' => true, 'detail' => ''),
            array('key' => 'ins_chk_dir', 'ok' => false, 'detail' => 'configs/'),
        )));
    }

    public function testAWFolderTheProjectOwnsIsWritable(): void
    {
        $root = sys_get_temp_dir() . '/xnova-requirements-' . getmypid();
        @mkdir($root . '/configs', 0775, true);

        $requirements = (new Requirements())->all($root);
        $directories = array_values(array_filter($requirements, static function (array $entry): bool {
            return $entry['key'] === 'ins_chk_dir';
        }));

        self::assertCount(count(Requirements::DIRECTORIES), $directories);

        // `configs/` existe : il doit être vu comme inscriptible. Les deux autres sont
        // créés par la vérification elle-même.
        self::assertTrue($directories[0]['ok']);
        self::assertTrue(Requirements::satisfied($directories));
    }
}
