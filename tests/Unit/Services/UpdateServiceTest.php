<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UpdateService;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des versions disponibles dans les étiquettes du dépôt.
 *
 * Le service prend le **texte** de la réponse de l'API : la seule partie qui touche
 * le réseau n'est donc pas testée ici (les tests unitaires ne sortent jamais de la
 * machine), et tout le reste l'est — le dépôt étant privé, c'est d'autant plus
 * important que la lecture d'un corps inattendu ne casse rien.
 */
final class UpdateServiceTest extends TestCase
{
    public function testVersionsAreReadFromTheGitHubPayload(): void
    {
        $service = new UpdateService('exemple/depot', '');
        $payload = json_encode(array(
            array('name' => 'v2026.30', 'commit' => array('sha' => 'abc123')),
            array('name' => 'v2026.9', 'commit' => array('sha' => 'def456')),
            array('name' => 'build/dossiers-modules-suivis', 'commit' => array('sha' => 'xyz')),
            array('name' => 'vpasunnombre', 'commit' => array('sha' => 'xyz')),
            'bruit',
        ));

        $versions = $service->versions((string) $payload);

        self::assertSame(array('2026.30', '2026.9'), array_column($versions, 'version'));
        self::assertSame(array('v2026.30', 'v2026.9'), array_column($versions, 'tag'));
        self::assertSame('abc123', $versions[0]['sha']);
    }

    public function testSomethingOtherThanAListYieldsNoVersion(): void
    {
        $service = new UpdateService('exemple/depot', '');

        self::assertSame(array(), $service->versions(''));
        self::assertSame(array(), $service->versions('{"message":"Not Found"}'));
        self::assertSame(array(), $service->versions('pas du json du tout'));
    }

    public function testOnlyNewerVersionsAreOfferedNewestFirst(): void
    {
        $service = new UpdateService('exemple/depot', '');
        $versions = array(
            array('version' => '2026.28', 'tag' => 'v2026.28', 'sha' => 'a'),
            array('version' => '2026.30', 'tag' => 'v2026.30', 'sha' => 'b'),
            array('version' => '2026.29', 'tag' => 'v2026.29', 'sha' => 'c'),
            array('version' => '2026.10', 'tag' => 'v2026.10', 'sha' => 'd'),
        );

        // 2026.10 est plus récente que 2026.9, mais **plus ancienne** que 2026.28 :
        // la comparaison est numérique, jamais alphabétique.
        self::assertSame(
            array('2026.30', '2026.29'),
            array_column($service->upgradable($versions, '2026.28'), 'version')
        );

        self::assertSame(array(), $service->upgradable($versions, '2026.30'));
        self::assertSame(
            array('2026.30', '2026.29', '2026.28', '2026.10'),
            array_column($service->upgradable($versions, '2026.9'), 'version')
        );
    }

    public function testTheRepositoryAndTheArchiveComeFromTheSettings(): void
    {
        $service = new UpdateService('exemple/depot', 'jeton');

        self::assertSame('exemple/depot', $service->repository());
        self::assertTrue($service->hasToken());
        self::assertSame(
            'https://api.github.com/repos/exemple/depot/tarball/v2026.30',
            $service->archiveUrl('v2026.30')
        );
        self::assertSame(
            'https://api.github.com/repos/exemple/depot/tags?per_page=100',
            $service->tagsUrl()
        );
    }

    public function testProtectedPathsAreNeverReplaced(): void
    {
        // Le premier segment décide : la connexion, les modules déposés, les dépendances
        // installées et les sauvegardes survivent à une mise à jour.
        foreach (
            array('configs', 'configs/config.php', '/configs/.env.prod', 'modules/officier/package.json',
                'vendor/autoload.php', 'backups/x.sql', '.git/config', '.env') as $protected
        ) {
            self::assertTrue(UpdateService::isProtected($protected), $protected . ' est protégé.');
        }

        foreach (array('app/Core/Project.php', 'package.json', 'common.php', 'index.php', '') as $free) {
            self::assertFalse(UpdateService::isProtected($free), $free . ' n\'est pas protégé.');
        }
    }

    public function testTheBackupKeepsTheCodeAndLeavesTheHeavyDirectories(): void
    {
        $root = sys_get_temp_dir() . '/xnova-backup-' . getmypid();
        @mkdir($root . '/app/Core', 0775, true);
        @mkdir($root . '/vendor', 0775, true);
        @mkdir($root . '/.git', 0775, true);
        file_put_contents($root . '/package.json', '{"version":"2026.32"}');
        file_put_contents($root . '/app/Core/Project.php', '<?php');
        file_put_contents($root . '/vendor/autoload.php', '<?php');
        file_put_contents($root . '/.git/config', 'gitdir');

        $archive = (new UpdateService('exemple/depot', ''))->backup($root);

        self::assertNotSame('', $archive, 'La sauvegarde est écrite.');
        self::assertFileExists($archive);

        $names = array();

        foreach (new \PharData($archive) as $entry) {
            $names[] = $entry->getFilename();
        }

        // Le code est sauvé — c'est lui que l'archive du dépôt remplace.
        self::assertContains('package.json', $names);
        self::assertContains('app', $names);

        // Et l'archive ne se sauvegarde pas elle-même, ni l'historique, ni les dépendances.
        self::assertNotContains('backups', $names);
        self::assertNotContains('.git', $names);
        self::assertNotContains('vendor', $names);
    }

    public function testWithoutSettingsTheServiceReadsTheProjectRepository(): void
    {
        $service = new UpdateService();

        self::assertSame(UpdateService::DEFAULT_REPOSITORY, $service->repository());
        self::assertFalse($service->hasToken());
    }
}
