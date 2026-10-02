<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ModuleScan;
use App\Core\Modules;

/**
 * Mise à jour du jeu depuis les **étiquettes** du dépôt Git.
 *
 * La liste des versions disponibles, ce sont les étiquettes (`v2026.30`) : une
 * étiquette désigne le commit exact d'une version, donc elle se télécharge. Le
 * dépôt du projet est **privé** : la lecture de l'API GitHub demande un jeton,
 * posé dans `configs/.env.<env>` (`UPDATE_TOKEN`, jamais versionné) et lu ici par
 * `fromEnvironment()`.
 *
 * Le service ne décide de rien : il **lit** les versions, garde celles qui sont
 * plus récentes que la version courante, et rend l'adresse de l'archive d'une
 * version. Comparer deux versions est une règle du jeu — elle vit dans
 * `Modules::coreSatisfies()`, réutilisée telle quelle.
 *
 * Un seul point touche le réseau (`request()`) : tout le reste est **pur**, donc
 * testable sans connexion — `versions()` prend le texte de la réponse.
 */
final class UpdateService
{
    /** Dépôt lu par défaut : celui du projet. */
    public const DEFAULT_REPOSITORY = 'mandalorien/xnova-docker';

    /** Préfixe des étiquettes de version (`v2026.30`). */
    public const TAG_PREFIX = 'v';

    /** Délai maximal accordé à l'API (secondes). */
    public const TIMEOUT = 15;

    /**
     * Ce qu'une mise à jour ne remplace **jamais**.
     *
     * Une archive du dépôt ne contient pas ces chemins (ils ne sont pas versionnés),
     * mais rien ne garantit qu'un jour elle n'en portera pas un : la règle est écrite
     * ici, une fois, et testée. `configs/` porte la connexion à la base, `modules/`
     * les modules déposés — qui ne sont **pas** dans le dépôt —, `vendor/` les
     * dépendances installées, `backups/` les sauvegardes.
     */
    public const PROTECTED = array('configs', 'modules', 'vendor', 'backups', '.git', '.env');

    /**
     * Ce qu'une **sauvegarde** n'embarque pas.
     *
     * Trois raisons, une par entrée : `backups/` contiendrait l'archive en train de
     * s'écrire, `.git/` porte l'historique — que l'archive du dépôt ne touche jamais —,
     * et `vendor/` n'est pas dans l'archive (les dépendances se refont avec `composer
     * install`). Tout le reste est sauvé, `configs/` et `modules/` compris : ce sont eux
     * qu'on ne peut pas reconstruire.
     */
    public const NOT_BACKED_UP = array('backups', '.git', 'vendor');

    /**
     * Ce chemin relatif est-il protégé ? Fonction **pure** : c'est le premier segment
     * qui décide (`configs/config.php` est protégé, `app/Core/Project.php` ne l'est pas).
     */
    public static function isProtected(string $relative): bool
    {
        $first = explode('/', str_replace('\\', '/', trim($relative, '/')))[0];

        return $first !== '' && in_array($first, self::PROTECTED, true);
    }

    public function __construct(
        private readonly string $repository = '',
        private readonly string $token = '',
    ) {
    }

    /** Réglages de l'environnement : dépôt et jeton (`configs/.env.<env>`). */
    public static function fromEnvironment(): self
    {
        return new self(
            (string) xnova_env('UPDATE_REPOSITORY', self::DEFAULT_REPOSITORY),
            (string) xnova_env('UPDATE_TOKEN', '')
        );
    }

    public function repository(): string
    {
        $name = trim($this->repository);

        return $name !== '' ? $name : self::DEFAULT_REPOSITORY;
    }

    /** Un jeton est-il posé ? Sans lui, un dépôt privé ne répond rien. */
    public function hasToken(): bool
    {
        return trim($this->token) !== '';
    }

    /**
     * Versions lisibles dans une réponse de l'API GitHub (`GET /repos/…/tags`).
     *
     * Seules les étiquettes bien formées comptent (`v` suivi de nombres) : le dépôt
     * porte aussi des branches de travail, et une étiquette qui n'est pas une version
     * n'a rien à faire dans la liste. Le tri n'est **pas** fait ici.
     *
     * @return list<array{version: string, tag: string, sha: string}>
     */
    public function versions(string $payload): array
    {
        $decoded = json_decode($payload, true);
        $versions = array();

        foreach (is_array($decoded) ? $decoded : array() as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $tag = (string) ($entry['name'] ?? '');

            if (!str_starts_with($tag, self::TAG_PREFIX)) {
                continue;
            }

            $version = substr($tag, strlen(self::TAG_PREFIX));

            if (preg_match('/^[0-9]+(\.[0-9]+)*$/', $version) !== 1) {
                continue;
            }

            $commit = $entry['commit'] ?? array();

            $versions[] = array(
                'version' => $version,
                'tag' => $tag,
                'sha' => is_array($commit) ? (string) ($commit['sha'] ?? '') : '',
            );
        }

        return $versions;
    }

    /**
     * Celles qui sont **plus récentes** que la version courante, la plus récente d'abord.
     *
     * @param list<array{version: string, tag: string, sha: string}> $versions
     * @return list<array{version: string, tag: string, sha: string}>
     */
    public function upgradable(array $versions, string $current): array
    {
        $newer = array();

        foreach ($versions as $entry) {
            if (Modules::coreSatisfies($entry['version'], '>' . $current)) {
                $newer[] = $entry;
            }
        }

        usort($newer, static function (array $left, array $right): int {
            return Modules::coreSatisfies($left['version'], '>' . $right['version']) ? -1 : 1;
        });

        return $newer;
    }

    /** Adresse de l'archive d'une étiquette — l'API suit vers `codeload`. */
    public function archiveUrl(string $tag): string
    {
        return 'https://api.github.com/repos/' . $this->repository() . '/tarball/' . $tag;
    }

    /** Adresse de la liste des étiquettes (100 par page, la plus récente d'abord). */
    public function tagsUrl(): string
    {
        return 'https://api.github.com/repos/' . $this->repository() . '/tags?per_page=100';
    }

    /**
     * Versions lues sur le dépôt, ou tableau vide si l'API ne répond rien.
     *
     * @return list<array{version: string, tag: string, sha: string}>
     */
    public function fetchVersions(): array
    {
        return $this->versions($this->request($this->tagsUrl()));
    }

    /** Télécharge l'archive d'une étiquette dans un fichier (`.tar.gz`). */
    public function download(string $tag, string $target): bool
    {
        $body = $this->request($this->archiveUrl($tag));

        if ($body === '') {
            return false;
        }

        return file_put_contents($target, $body) !== false;
    }

    /**
     * Applique une version : sauvegarde **l'arbre entier** en tar, télécharge l'archive de
     * l'étiquette, l'extrait en quarantaine, puis recopie le code à la racine.
     *
     * L'ordre compte : la sauvegarde est faite **avant** de toucher au disque, l'archive
     * est extraite **ailleurs** avant d'être recopiée, et chaque entrée passe par
     * `isProtected()` **et** par la garde de chemin de `ModuleScan` (pas de `..`, pas de
     * chemin absolu) : une archive détournée ne peut donc pas écrire dans `configs/`.
     *
     * Le service ne rejoue **pas** les migrations : c'est une étape distincte, visible
     * dans le rapport rendu à l'appelant (outil en ligne de commande, puis page).
     *
     * @return array{ok: bool, tag: string, files: int, skipped: int, backup: string, dump: string, error: string}
     */
    public function apply(string $tag, string $root, string $work): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $work = rtrim(str_replace('\\', '/', $work), '/');
        $report = array('ok' => false, 'tag' => $tag, 'files' => 0, 'skipped' => 0, 'backup' => '', 'dump' => '', 'error' => '');

        if (!is_dir($work) && !mkdir($work, 0775, true) && !is_dir($work)) {
            $report['error'] = 'staging';

            return $report;
        }

        $report['backup'] = $this->backup($root);

        // Le vidage de la base vient avec la sauvegarde de l'arbre, et **avant** tout
        // telechargement : un echec ici arrete la mise a jour, sans avoir rien ecrit.
        try {
            $report['dump'] = (new DumpService())->dump($root, \App\Core\Project::version());
        } catch (\Throwable $exception) {
            $report['error'] = 'dump';

            return $report;
        }

        $archive = $work . '/update.tar.gz';

        if (!$this->download($tag, $archive)) {
            $report['error'] = 'download';

            return $report;
        }

        $source = $this->extract($archive, $work . '/source');

        if ($source === '') {
            $report['error'] = 'extract';

            return $report;
        }

        $copied = $this->copyTree($source, $root);
        $report['files'] = $copied['files'];
        $report['skipped'] = $copied['skipped'];
        $report['ok'] = $copied['files'] > 0;
        $report['error'] = $report['ok'] ? '' : 'copy';

        return $report;
    }

    /**
     * Ce chemin est-il écarté d'une sauvegarde ? Même règle que `isProtected()` : le
     * **premier segment** décide. Fonction pure.
     */
    public static function isNotBackedUp(string $relative): bool
    {
        $first = explode('/', str_replace('\\', '/', trim($relative, '/')))[0];

        return $first !== '' && in_array($first, self::NOT_BACKED_UP, true);
    }

    /**
     * Sauvegarde **l'arbre entier** dans une archive tar et rend le chemin écrit ('' sinon).
     *
     * C'est le garde-fou qui rend une mise à jour réversible : une archive du dépôt
     * remplace du code, et sans cette copie on ne peut pas revenir en arrière.
     *
     * Le tar s'écrit **sans** l'extension `zip`, absente de l'image : `PharData` suffit,
     * et il n'est pas soumis à `phar.readonly`, qui ne garde que les archives exécutables.
     */
    public function backup(string $root): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $directory = $root . '/backups';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return '';
        }

        $target = $directory . '/xnova-' . date('Ymd-His') . '.tar';
        $prefix = strlen($root) + 1;
        $files = 0;

        try {
            $archive = new \PharData($target);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($item->getPathname(), $prefix));

                if (self::isNotBackedUp($relative)) {
                    continue;
                }

                $archive->addFile($item->getPathname(), $relative);
                $files++;
            }
        } catch (\Throwable $exception) {
            return '';
        }

        return $files > 0 ? $target : '';
    }

    /**
     * Extrait l'archive en quarantaine et rend le dossier du dessus ('' si échec).
     *
     * L'archive publiée par GitHub a toujours un dossier racine unique
     * (`<dépôt>-<commit>/`) : on ne recopie donc jamais ce dossier, seulement son contenu.
     */
    private function extract(string $archive, string $target): string
    {
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
            return '';
        }

        try {
            $phar = new \PharData($archive);
            $phar->extractTo($target, null, true);
        } catch (\Throwable $exception) {
            return '';
        }

        $entries = array_values(array_filter((array) scandir($target), static function (string $entry): bool {
            return $entry !== '.' && $entry !== '..';
        }));

        return count($entries) === 1 && is_dir($target . '/' . $entries[0]) ? $target . '/' . $entries[0] : '';
    }

    /**
     * Recopie un arbre de code à la racine, en **laissant** ce que `isProtected()` protège.
     *
     * @return array{files: int, skipped: int}
     */
    private function copyTree(string $source, string $root): array
    {
        $files = 0;
        $skipped = 0;
        $prefix = strlen($source) + 1;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), $prefix));

            if (!ModuleScan::entryIsSafe($relative) || self::isProtected($relative)) {
                $skipped++;

                continue;
            }

            $destination = $root . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($destination)) {
                    mkdir($destination, 0775, true);
                }

                continue;
            }

            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0775, true);
            }

            if (@copy($item->getPathname(), $destination)) {
                $files++;
            } else {
                $skipped++;
            }
        }

        return array('files' => $files, 'skipped' => $skipped);
    }

    /**
     * **Le seul point qui touche le réseau.**
     *
     * `ignore_errors` est volontaire : une réponse 404 a un corps qu'on préfère lire
     * (le parseur rend alors une liste vide) plutôt que lever une exception dans la page.
     * Aucune exception ne remonte donc d'ici : au pire, une chaîne vide.
     */
    private function request(string $url): string
    {
        $headers = 'User-Agent: xnova-update' . "\r\n";

        if ($this->hasToken()) {
            $headers .= 'Authorization: Bearer ' . trim($this->token) . "\r\n";
            $headers .= 'Accept: application/vnd.github+json' . "\r\n";
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => $headers,
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
            ),
        ));

        $body = @file_get_contents($url, false, $context);

        return $body === false ? '' : $body;
    }
}
