<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ModuleScan;
use PHPUnit\Framework\TestCase;

/**
 * Analyse d'un module avant installation.
 *
 * Ces tests n'ont besoin ni de MySQL, ni d'un serveur, ni du jeu démarré : on écrit
 * un faux module dans un dossier temporaire et on lit le rapport. C'est le point de
 * la classe — l'analyse est **pure** et vérifiable seule.
 */
final class ModuleScanTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Le nom du dossier du module compte : le manifeste doit s'appeler comme lui.
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xnova_scan_' . uniqid('', true) . DIRECTORY_SEPARATOR . 'demo';
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree(dirname($this->root));

        parent::tearDown();
    }

    public function testAnEntryCannotEscapeThePackage(): void
    {
        // Le « zip-slip » : une entrée d'archive ne doit jamais remonter ni viser
        // un chemin absolu, sinon elle écrase le jeu lui-même.
        $this->assertFalse(ModuleScan::entryIsSafe('../app/Core/Kernel.php'));
        $this->assertFalse(ModuleScan::entryIsSafe('view/../../Kernel.php'));
        $this->assertFalse(ModuleScan::entryIsSafe('/etc/passwd'));
        $this->assertFalse(ModuleScan::entryIsSafe('C:/Windows/system32/x.php'));
        $this->assertFalse(ModuleScan::entryIsSafe('view\\..\\..\\Kernel.php'));
        $this->assertFalse(ModuleScan::entryIsSafe(''));

        $this->assertTrue(ModuleScan::entryIsSafe('package.json'));
        $this->assertTrue(ModuleScan::entryIsSafe('view/page.tpl'));
        $this->assertTrue(ModuleScan::entryIsSafe('db/migrations/001_x.php'));
    }

    public function testAMinimalPackagePasses(): void
    {
        $this->writeManifest();
        $this->write('services/DemoService.php', "<?php\n\nnamespace Modules\\Demo\\Services;\n\nfinal class DemoService\n{\n}\n");
        $this->write('view/page.tpl', "<div>{titre}</div>\n");

        $report = ModuleScan::report($this->root);

        $this->assertTrue($report['ok'], 'Un module minimal doit passer : ' . json_encode($report['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(array(), $report['errors']);
        $this->assertSame(3, $report['files']);
        $this->assertSame('demo', $report['manifest']['name'] ?? null);
    }

    public function testAMissingManifestIsRefused(): void
    {
        $this->write('services/DemoService.php', "<?php\n");

        $report = ModuleScan::report($this->root);

        $this->assertFalse($report['ok']);
        $this->assertSame('missing_manifest', $report['errors'][0]['code']);
    }

    public function testTheFolderNameMustMatchTheManifest(): void
    {
        $this->writeManifest('autre');

        $report = ModuleScan::report($this->root);
        $codes = array_column($report['warnings'], 'code');

        $this->assertContains('name_mismatch', $codes);
        $this->assertTrue($report['ok'], 'Un nom mal choisi est une alerte, pas un refus.');
    }

    public function testAFunctionThatRunsCodeIsReported(): void
    {
        $this->writeManifest();
        $this->write('services/Bad.php', "<?php\n\n// une ligne de commentaire\n\n\$x = 1;\n\neval(\$x);\n");

        $report = ModuleScan::report($this->root);
        $codes = array_column($report['warnings'], 'code');

        $this->assertContains('forbidden_function', $codes);

        $finding = $report['warnings'][array_search('forbidden_function', $codes, true)];

        $this->assertSame('services/Bad.php', $finding['file']);
        $this->assertSame(7, $finding['line'], 'La ligne rapportée doit être celle de l\'appel.');
    }

    public function testTheSameWordInACommentIsNotAFinding(): void
    {
        // Un rapport qui crie au loup ne sert à rien : le mot cité dans un commentaire
        // ou une chaîne n'est pas un appel.
        $this->writeManifest();
        $this->write('services/Doc.php', "<?php\n\n// eval() n'est jamais appelé ici, c'est de la documentation\n\n\$texte = 'system(';\n");

        $report = ModuleScan::report($this->root);

        $this->assertTrue($report['ok'], 'Un mot cité n\'est pas un appel : ' . json_encode($report['errors'], JSON_UNESCAPED_UNICODE));
    }

    public function testAnExecutableExtensionIsRefused(): void
    {
        $this->writeManifest();
        $this->write('view/shell.phtml', "<?php echo 1;\n");

        $codes = array_column(ModuleScan::report($this->root)['errors'], 'code');

        $this->assertContains('executable_extension', $codes);
    }

    public function testAServerConfigurationFileIsRefused(): void
    {
        $this->writeManifest();
        $this->write('.htaccess', "php_value auto_prepend_file /tmp/x.php\n");

        $codes = array_column(ModuleScan::report($this->root)['errors'], 'code');

        $this->assertContains('server_config', $codes);
    }

    public function testARemoteIncludeIsReported(): void
    {
        $this->writeManifest();
        $this->write('services/Remote.php', "<?php\n\ninclude 'http://exemple.test/charge.php';\n");

        $codes = array_column(ModuleScan::report($this->root)['warnings'], 'code');

        $this->assertContains('remote_include', $codes);
    }

    public function testBrokenSyntaxIsReportedWithItsLine(): void
    {
        $this->writeManifest();
        $this->write('services/Casse.php', "<?php\n\n\$x = 1;\n\nfunction (\n");

        $errors = ModuleScan::report($this->root)['warnings'];
        $codes = array_column($errors, 'code');
        $index = array_search('php_syntax', $codes, true);

        $this->assertNotFalse($index, 'Un fichier PHP cassé doit être signalé.');
        $this->assertGreaterThan(0, $errors[$index]['line']);
    }

    public function testAlertsDoNotPreventInstallation(): void
    {
        // La règle du panneau, tenue ici : une alerte éclaire, elle ne bloque pas. Ce
        // module cumule un appel à `eval`, une clé inconnue au manifeste, un nom qui ne
        // correspond pas à son dossier et une permission étrangère — et il reste
        // installable : c'est l'administrateur qui décide, en le sachant.
        $this->writeManifest('autre', array('routess' => array(), 'permissions' => array('module.tiers')));
        $this->write('services/Bad.php', "<?php\n\neval('1');\n");

        $report = ModuleScan::report($this->root);
        $codes = array_column($report['warnings'], 'code');

        $this->assertTrue($report['ok'], 'Aucune alerte ne doit empêcher l\'installation.');
        $this->assertSame(array(), $report['errors']);
        $this->assertContains('forbidden_function', $codes);
        $this->assertContains('unknown_key', $codes);
        $this->assertContains('name_mismatch', $codes);
        $this->assertContains('invalid_permission', $codes);
    }

    public function testAnUnknownManifestKeyIsSignalled(): void
    {
        $this->writeManifest('demo', array('routess' => array('game/demo' => 'DemoController@indexAction')));

        $codes = array_column(ModuleScan::report($this->root)['warnings'], 'code');

        $this->assertContains('unknown_key', $codes);
    }

    public function testAPermissionOutsideItsOwnModuleIsSignalled(): void
    {
        $this->writeManifest('demo', array('permissions' => array('module.autre')));

        $codes = array_column(ModuleScan::report($this->root)['warnings'], 'code');

        $this->assertContains('invalid_permission', $codes);
    }

    /** Manifeste valide, éventuellement modifié. */
    private function writeManifest(string $name = 'demo', array $extra = array()): void
    {
        $manifest = array(
            'name' => $name,
            'label' => 'mod_demo',
            'description' => 'mod_demo_desc',
            'version' => '1.0.0',
            'author' => 'XNova',
            'permissions' => array('module.' . $name),
        );

        // `array_merge` et non `+` : avec l'opérateur d'union, la clé de gauche
        // gagne — et le test « permission étrangère » passerait pour de mauvaises
        // raisons (c'est le piège documenté du tableau de langue, le même en PHP).
        $this->write(ModuleScan::MANIFEST, json_encode(array_merge($manifest, $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    private function write(string $relative, string $content): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $content);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }

            return;
        }

        foreach (scandir($path) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }

        @rmdir($path);
    }
}
