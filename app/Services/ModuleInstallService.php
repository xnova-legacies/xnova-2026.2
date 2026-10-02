<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Modules;
use App\Core\ModuleScan;

/**
 * Installation d'un module téléversé.
 *
 * Trois temps, et l'ordre compte :
 *
 * 1. **Inspection** : l'archive est extraite dans une quarantaine
 *    (`modules/.archive/.upload/<nom>/`), jamais à sa place définitive. Chaque entrée
 *    est vérifiée **avant** écriture — un nom qui sort du dossier (zip-slip) ou un
 *    fichier que le serveur exécute ou lit tout seul (`.htaccess`, `.phtml`) est refusé
 *    sans être extrait. `modules/` n'est pas servi en HTTP, mais un `.htaccess` déposé
 *    là s'appliquerait quand même : on ne l'écrit donc jamais.
 * 2. **Analyse** : `ModuleScan` lit la quarantaine et rend son rapport — refus et
 *    alertes séparés.
 * 3. **Installation** : sur décision de l'administrateur. Un refus arrête tout ; les
 *    alertes n'empêchent pas (`$force` dit que l'administrateur les a lues), et
 *    l'empreinte SHA-256 de l'archive est alors conservée à côté du module, pour
 *    pouvoir dire plus tard si le dossier installé est bien celui qui a été analysé.
 *
 * Rien n'est jamais écrasé : un nom déjà pris est refusé, comme l'archivage refuse
 * d'écraser une archive existante.
 */
final class ModuleInstallService
{
    /** Quarantaine : sous-dossier de l'archive, il n'est jamais découvert comme module. */
    public const UPLOAD_DIRECTORY = '.upload';

    /** Taille maximale acceptée pour l'archive téléversée (10 Mo). */
    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly ModuleService $modules = new ModuleService(),
    ) {
    }

    /**
     * Extrait l'archive en quarantaine et l'analyse.
     *
     * @return array{ok: bool, name: string, staging: string, sha256: string, report: array<string, mixed>}
     */
    public function inspect(string $archive): array
    {
        $empty = array('ok' => false, 'errors' => array(), 'warnings' => array(), 'manifest' => null, 'files' => 0, 'bytes' => 0);

        // L'extension `zip` n'est **pas** installée dans l'image : c'est `PharData` qui lit
        // l'archive (formats zip, tar et tar.gz). Il donne aussi la **liste** des entrées,
        // ce qui permet de refuser avant d'écrire quoi que ce soit.
        if (!is_file($archive) || !class_exists(\PharData::class)) {
            return array('ok' => false, 'name' => '', 'staging' => '', 'sha256' => '', 'report' => $empty + array('errors' => array($this->error('missing_archive', 'Archive illisible (lecture d\'archive indisponible sur le serveur).'))));
        }

        if ((int) filesize($archive) > self::MAX_UPLOAD_BYTES) {
            return array('ok' => false, 'name' => '', 'staging' => '', 'sha256' => '', 'report' => $empty + array('errors' => array($this->error('too_large', 'Archive de plus de ' . (int) (self::MAX_UPLOAD_BYTES / 1048576) . ' Mo.'))));
        }

        $entries = $this->entries($archive);

        if ($entries === array()) {
            return array('ok' => false, 'name' => '', 'staging' => '', 'sha256' => '', 'report' => $empty + array('errors' => array($this->error('bad_archive', 'Archive vide ou illisible.'))));
        }

        // Le nom du module vient du manifeste : à la racine de l'archive, ou dans le
        // dossier commun (`demo/package.json`).
        $name = $this->packageName($entries);

        if ($name === '') {
            return array('ok' => false, 'name' => '', 'staging' => '', 'sha256' => '', 'report' => $empty + array('errors' => array($this->error('missing_manifest', 'Aucun package.json lisible dans l\'archive : rien à installer.'))));
        }

        $staging = $this->stagingDirectory() . DIRECTORY_SEPARATOR . $name;
        $this->removeTree($staging);

        if (!is_dir($staging) && !mkdir($staging, 0777, true) && !is_dir($staging)) {
            return array('ok' => false, 'name' => $name, 'staging' => '', 'sha256' => '', 'report' => $empty + array('errors' => array($this->error('staging_failed', 'Quarantaine impossible à créer.'))));
        }

        $refused = array();

        foreach ($entries as $relative => $source) {
            $base = basename($relative);
            $extension = strtolower((string) pathinfo($relative, PATHINFO_EXTENSION));

            // Deux gardes **avant** toute écriture : le chemin (zip-slip) et ce que le
            // serveur exécute ou lit tout seul. Le reste est analysé après extraction.
            if (!ModuleScan::entryIsSafe($relative)) {
                $refused[] = $this->error('zip_slip', 'Entrée refusée (chemin hors du module) : ' . $relative);

                continue;
            }

            if (in_array($base, ModuleScan::FORBIDDEN_NAMES, true) || (in_array($extension, ModuleScan::FORBIDDEN_EXTENSIONS, true) && $extension !== 'php')) {
                $refused[] = $this->error('executable_extension', 'Entrée refusée (fichier que le serveur exécute ou lit seul) : ' . $relative);

                continue;
            }

            $content = @file_get_contents($source);

            if ($content === false) {
                $refused[] = $this->error('unreadable_entry', 'Entrée illisible : ' . $relative);

                continue;
            }

            $target = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }

            file_put_contents($target, $content);
        }

        $report = ModuleScan::report($staging);

        if ($refused !== array()) {
            $report['errors'] = array_merge($refused, $report['errors']);
            $report['ok'] = false;
        }

        $sha256 = (string) hash_file('sha256', $archive);

        // L'empreinte accompagne le module préparé : l'installation la relit de là, et
        // l'écrit à côté du module installé — jamais depuis la demande du client, qui
        // ne fournit qu'un **nom**.
        @file_put_contents($staging . DIRECTORY_SEPARATOR . '.sha256', $sha256 . "\n");

        return array(
            'ok' => $report['ok'],
            'name' => $name,
            'staging' => $staging,
            'sha256' => $sha256,
            'report' => $report,
        );
    }

    /**
     * Installe le module préparé en quarantaine.
     *
     * @return array{installed: bool, refusal: string}
     */
    public function install(string $staging, string $name, string $sha256, bool $force = false): array
    {
        if ($staging === '' || !is_dir($staging)) {
            return array('installed' => false, 'refusal' => 'mod_install_missing');
        }

        if (preg_match('/^[a-z][a-z0-9]*$/', $name) !== 1) {
            return array('installed' => false, 'refusal' => 'mod_install_bad_name');
        }

        $target = Modules::directory() . $name;

        // On n'écrase jamais : un nom déjà pris se refuse, comme l'archivage refuse
        // d'écraser une archive.
        if (is_dir($target) || Modules::exists($name)) {
            return array('installed' => false, 'refusal' => 'mod_install_exists');
        }

        $report = ModuleScan::report($staging);

        // Les **alertes** n'empêchent pas, à condition que l'administrateur les ait
        // lues (`$force`). Les refus, eux, arrêtent tout.
        if (!$report['ok']) {
            return array('installed' => false, 'refusal' => 'mod_install_refused');
        }

        if ($report['warnings'] !== array() && !$force) {
            return array('installed' => false, 'refusal' => 'mod_install_confirm');
        }

        if (!@rename($staging, $target)) {
            return array('installed' => false, 'refusal' => 'mod_install_move');
        }

        // L'empreinte est écrite à côté du module : elle dit quel fichier a été analysé.
        if ($sha256 !== '') {
            @file_put_contents($target . DIRECTORY_SEPARATOR . '.sha256', $sha256 . "\n");
        }

        // Le catalogue des modules le découvre au prochain chargement : le semis
        // s'occupe de le déclarer, il n'y a rien à écrire en base ici.
        $this->modules->seedDefaults();

        return array('installed' => true, 'refusal' => '');
    }

    /** Quarantaine : `modules/.archive/.upload`, jamais découverte comme module. */
    private function stagingDirectory(): string
    {
        $path = Modules::directory() . '.archive' . DIRECTORY_SEPARATOR . self::UPLOAD_DIRECTORY;

        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }

        return $path;
    }

    /**
     * Dossier de quarantaine d'un module **par son nom**.
     *
     * Le chemin n'est jamais reçu du client : il est reconstruit ici, à partir d'un nom
     * validé — sinon une adresse fabriquée ferait installer n'importe quel dossier du
     * serveur. Le nom est de toute façon revalidé par `install()`, qui **relance**
     * l'analyse avant de déplacer quoi que ce soit.
     */
    public function stagingPath(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $name) !== 1) {
            return '';
        }

        return $this->stagingDirectory() . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Entrées de l'archive : chemin relatif => ressource lisible (`phar://…`).
     *
     * Un dossier commun est retiré (`demo/…` devient `…`) : c'est le **contenu** du
     * dossier qui doit devenir `modules/demo/`, pas le dossier lui-même.
     *
     * @return array<string, string>
     */
    private function entries(string $archive): array
    {
        try {
            $data = new \PharData($archive);
        } catch (\Throwable $error) {
            return array();
        }

        $prefix = 'phar://' . str_replace('\\', '/', $archive) . '/';
        $found = array();

        try {
            foreach (new \RecursiveIteratorIterator($data, \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
                $path = str_replace('\\', '/', (string) $file->getPathname());
                $relative = ltrim(substr($path, strlen($prefix)), '/');

                if ($relative === '' || str_starts_with($relative, '__MACOSX')) {
                    continue;
                }

                $found[$relative] = $path;
            }
        } catch (\Throwable $error) {
            return array();
        }

        return $this->stripCommonPrefix($found);
    }

    /**
     * Retire le premier segment quand toutes les entrées le partagent.
     *
     * @param array<string, string> $entries
     * @return array<string, string>
     */
    private function stripCommonPrefix(array $entries): array
    {
        $prefix = null;

        foreach (array_keys($entries) as $relative) {
            $first = explode('/', (string) $relative)[0] . '/';

            if ($prefix === null) {
                $prefix = $first;

                continue;
            }

            if ($prefix !== $first) {
                return $entries;
            }
        }

        if ($prefix === null || !array_key_exists($prefix . ModuleScan::MANIFEST, $entries)) {
            return $entries;
        }

        $stripped = array();

        foreach ($entries as $relative => $source) {
            $stripped[substr($relative, strlen($prefix))] = $source;
        }

        return $stripped;
    }

    /**
     * Nom du module, lu dans le manifeste.
     *
     * @param array<string, string> $entries
     */
    private function packageName(array $entries): string
    {
        $source = $entries[ModuleScan::MANIFEST] ?? '';

        if ($source === '') {
            return '';
        }

        $data = json_decode((string) @file_get_contents($source), true);
        $name = is_array($data) ? (string) ($data['name'] ?? '') : '';

        return preg_match('/^[a-z][a-z0-9]*$/', $name) === 1 ? $name : '';
    }

    /** @return array{file: string, line: int, code: string, message: string} */
    private function error(string $code, string $message): array
    {
        return array('file' => '', 'line' => 0, 'code' => $code, 'message' => $message);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
