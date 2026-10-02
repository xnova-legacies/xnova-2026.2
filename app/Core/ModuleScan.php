<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Analyse d'un module avant installation.
 *
 * Rien n'est jamais exécuté : on **lit** les fichiers d'un dossier et on rend un
 * rapport (`errors`, `warnings`). L'appelant décide — l'installation d'un module
 * reste une action explicite de l'administrateur, et cette analyse n'est qu'un
 * filtre : un module peut nuire sans employer une seule fonction de la liste.
 *
 * Règle du panneau : **une alerte n'empêche jamais l'installation**. Seuls les refus
 * « par construction » bloquent — un module que le Coeur d'application ne peut ni lire ni extraire
 * sans danger : manifeste absent ou JSON illisible, entrée qui sort du module
 * (zip-slip), lien symbolique, fichier que le serveur exécute ou lit tout seul
 * (`.phtml`, `.htaccess`), bornes de taille. Tout le reste — appel à `eval`, syntaxe
 * cassée, clé inconnue au manifeste, permission étrangère — est une **alerte** :
 * elle s'affiche, et l'administrateur décide en connaissance de cause (`ok` reste vrai).
 *
 * La classe ne connaît ni base de données, ni session, ni constante du jeu : elle
 * se teste seule et peut servir au panneau comme à une commande.
 */
final class ModuleScan
{
    /** Manifeste attendu à la racine du module. */
    public const MANIFEST = 'package.json';

    /** Clés admises au manifeste : toute autre est signalée (le Coeur d'application l'ignorerait). */
    public const MANIFEST_KEYS = array(
        'name', 'label', 'description', 'version', 'author', 'native', 'order',
        'page', 'routes', 'api', 'legacy', 'dependencies', 'permissions',
        'settings', 'tables', 'debris', 'missions',
    );

    /**
     * Fonctions qui exécutent du code ou une commande : un module qui les appelle
     * ne peut pas être installé « à l'aveugle ».
     */
    public const FORBIDDEN_FUNCTIONS = array(
        'eval', 'assert', 'create_function', 'exec', 'system', 'shell_exec',
        'passthru', 'proc_open', 'popen', 'pcntl_exec', 'dl',
    );

    /** Extensions exécutables ou de configuration serveur. */
    public const FORBIDDEN_EXTENSIONS = array(
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar',
    );

    /** Noms de fichiers que le serveur lit tout seul. */
    public const FORBIDDEN_NAMES = array('.htaccess', '.user.ini', 'php.ini', '.htpasswd');

    /** Bornes : un module de jeu n'a pas besoin de plus. */
    public const MAX_FILES = 2000;

    public const MAX_BYTES = 20 * 1024 * 1024;

    /** Au-delà, on ne compile pas le fichier (une analyse ne doit pas faire tomber le serveur). */
    public const MAX_PARSE_BYTES = 512 * 1024;

    /**
     * Une entrée d'archive est-elle sûre à extraire ?
     *
     * Fonction pure : pas de `..`, pas de chemin absolu, pas de `\` (un zip pose des
     * `/`), pas de nom vide. C'est la garde contre le « zip-slip », où une entrée
     * `../../app/Core/Kernel.php` écraserait le jeu lui-même.
     */
    public static function entryIsSafe(string $entry): bool
    {
        $entry = str_replace('\\', '/', trim($entry));

        if ($entry === '' || str_starts_with($entry, '/') || preg_match('#^[A-Za-z]:#', $entry) === 1) {
            return false;
        }

        foreach (explode('/', $entry) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Analyse un dossier de module.
     *
     * @return array{ok: bool, errors: array<int, array{file: string, line: int, code: string, message: string}>, warnings: array<int, array{file: string, line: int, code: string, message: string}>, manifest: array<string, mixed>|null, files: int, bytes: int}
     */
    public static function report(string $directory): array
    {
        $root = rtrim($directory, '/\\');
        $errors = array();
        $warnings = array();
        $files = 0;
        $bytes = 0;

        if (!is_dir($root)) {
            return self::result(array(self::finding('', 0, 'missing_directory', 'Dossier introuvable.')), array(), null, 0, 0);
        }

        $manifest = null;
        $manifestFile = $root . DIRECTORY_SEPARATOR . self::MANIFEST;

        if (!is_file($manifestFile)) {
            $errors[] = self::finding(self::MANIFEST, 0, 'missing_manifest', 'Le manifeste ' . self::MANIFEST . ' manque à la racine du module.');
        } else {
            [$manifest, $manifestFindings] = self::readManifest($manifestFile, basename($root));

            foreach ($manifestFindings as $manifestFinding) {
                // Un manifeste **illisible** bloque : le Coeur d'application ne saurait rien déclarer,
                // le module ne s'installerait pas. Le reste est une alerte — le nom, les
                // libellés, une clé inconnue se corrigent après coup, sans redemander
                // l'archive à l'auteur.
                if ($manifestFinding['code'] === 'invalid_json') {
                    $errors[] = $manifestFinding;
                } else {
                    $warnings[] = $manifestFinding;
                }
            }
        }

        foreach (self::walk($root) as $path) {
            $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
            $files++;

            if (is_link($path)) {
                $errors[] = self::finding($relative, 0, 'symlink', 'Lien symbolique : le contenu réel échappe à l\'analyse.');

                continue;
            }

            $bytes += (int) filesize($path);
            $name = basename($path);
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

            if (in_array($name, self::FORBIDDEN_NAMES, true)) {
                $errors[] = self::finding($relative, 0, 'server_config', 'Fichier de configuration serveur interdit dans un module.');
            }

            if ($name === self::MANIFEST && $relative !== self::MANIFEST) {
                $warnings[] = self::finding($relative, 0, 'nested_manifest', 'Second manifeste : le Coeur d\'application ne lira que celui de la racine.');
            }

            if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true) && $extension !== 'php') {
                $errors[] = self::finding($relative, 0, 'executable_extension', 'Extension exécutable « .' . $extension . ' » refusée (seul .php est admis, comme le reste du jeu).');
            }

            if ($extension !== 'php') {
                continue;
            }

            // **Alerte**, jamais refus : l'analyse ne juge pas à la place de
            // l'administrateur. Un module qui appelle `eval` peut être celui dont il a
            // besoin — il doit pouvoir l'installer en le sachant.
            foreach (self::scanPhp($path) as $finding) {
                $finding['file'] = $relative;
                $warnings[] = $finding;
            }
        }

        if ($files > self::MAX_FILES) {
            $errors[] = self::finding('', 0, 'too_many_files', 'Plus de ' . self::MAX_FILES . ' fichiers : refusé.');
        }

        if ($bytes > self::MAX_BYTES) {
            $errors[] = self::finding('', 0, 'too_large', 'Module de plus de ' . (int) (self::MAX_BYTES / 1048576) . ' Mo : refusé.');
        }

        return self::result($errors, $warnings, $manifest, $files, $bytes);
    }

    /**
     * Lecture et contrôle du manifeste : JSON valide, clés connues, nom cohérent,
     * permissions limitées au préfixe réservé `module.<nom>`.
     *
     * @return array{0: array<string, mixed>|null, 1: array<int, array{file: string, line: int, code: string, message: string}>}
     */
    private static function readManifest(string $file, string $folder): array
    {
        $raw = (string) @file_get_contents($file);
        $data = json_decode($raw, true);
        $errors = array();

        if (!is_array($data)) {
            // Le seul défaut de manifeste qui bloque : le Coeur d'application ne peut rien lire.
            return array(null, array(self::finding(self::MANIFEST, 0, 'invalid_json', 'Le manifeste n\'est pas un objet JSON valide.')));
        }

        foreach (array_keys($data) as $key) {
            if (!in_array((string) $key, self::MANIFEST_KEYS, true)) {
                $errors[] = self::finding(self::MANIFEST, 0, 'unknown_key', 'Clé inconnue « ' . $key . ' » : le Coeur d\'application l\'ignore, elle masque souvent une faute de frappe.');
            }
        }

        $name = (string) ($data['name'] ?? '');

        if (preg_match('/^[a-z][a-z0-9]*$/', $name) !== 1) {
            $errors[] = self::finding(self::MANIFEST, 0, 'invalid_name', 'Le nom doit être un identifiant en minuscules sans tiret (segment de namespace).');
        } elseif ($name !== $folder) {
            $errors[] = self::finding(self::MANIFEST, 0, 'name_mismatch', 'Le nom « ' . $name . ' » ne correspond pas au dossier « ' . $folder . ' ».');
        }

        foreach (array('label', 'description') as $key) {
            if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
                $errors[] = self::finding(self::MANIFEST, 0, 'missing_label', 'Clé « ' . $key . ' » manquante : un module voyage avec ses libellés.');
            }
        }

        foreach ((array) ($data['permissions'] ?? array()) as $permission) {
            if ($permission !== 'module.' . $name) {
                $errors[] = self::finding(self::MANIFEST, 0, 'invalid_permission', 'Permission « ' . $permission . ' » : un module ne réclame que « module.' . $name . ' ».');
            }
        }

        foreach (array('page', 'routes', 'api', 'legacy') as $key) {
            foreach ((array) ($data[$key] ?? array()) as $address => $target) {
                if (str_contains((string) $address, '..') || str_contains((string) $target, '..')) {
                    $errors[] = self::finding(self::MANIFEST, 0, 'path_traversal', 'Adresse « ' . $key . ' » contenant « .. » : refusé.');
                }
            }
        }

        foreach ((array) ($data['missions'] ?? array()) as $mission => $declared) {
            if ((int) $mission <= 0 || !is_array($declared)) {
                $errors[] = self::finding(self::MANIFEST, 0, 'invalid_mission', 'Mission « ' . $mission . ' » mal déclarée.');
            }
        }

        return array($data, $errors);
    }

    /**
     * Analyse d'un fichier PHP : syntaxe, fonctions d'exécution, code dynamique.
     *
     * Compiler sans exécuter (`TOKEN_PARSE`) attrape les fichiers cassés, et les
     * jetons évitent de confondre un appel réel avec le mot cité dans un
     * commentaire — un rapport qui crie au loup ne sert à rien.
     *
     * @return array<int, array{file: string, line: int, code: string, message: string}>
     */
    private static function scanPhp(string $path): array
    {
        $code = (string) @file_get_contents($path);

        if (strlen($code) > self::MAX_PARSE_BYTES) {
            return array(self::finding('', 0, 'file_too_large', 'Fichier PHP trop volumineux pour être analysé.'));
        }

        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (\ParseError $error) {
            return array(self::finding('', max(1, (int) $error->getLine()), 'php_syntax', 'Syntaxe PHP invalide : ' . $error->getMessage()));
        } catch (\Throwable $error) {
            return array(self::finding('', 0, 'php_unreadable', 'Fichier PHP illisible par l\'analyse.'));
        }

        $findings = array();
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '`') {
                $findings[] = self::finding('', 0, 'backtick', 'Exécution de commande par accents graves.');
            }

            if (!is_array($token)) {
                continue;
            }

            // Le nom du jeton ne suffit pas : `eval` est un mot réservé (T_EVAL), pas un
            // T_STRING — chercher parmi les T_STRING seuls laissait passer l'appel le
            // plus recherché. On regarde donc le texte de chaque jeton porteur d'un nom.
            $function = strtolower($token[1]);

            if (!in_array($function, self::FORBIDDEN_FUNCTIONS, true)) {
                continue;
            }

            // Un appel, et rien d'autre : ni une méthode (`->system(`), ni un nom
            // qualifié (`Mon\system(`), ni une déclaration (`function system(`).
            $previous = null;

            for ($j = $i - 1; $j >= 0; $j--) {
                if (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                    continue;
                }

                $previous = $tokens[$j];

                break;
            }

            if (is_array($previous) && in_array($previous[0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NS_SEPARATOR), true)) {
                continue;
            }

            if ($previous === '\\') {
                continue;
            }

            // Un appel : le nom est suivi d'une parenthèse (une constante du même nom
            // ne compte pas).
            $next = null;

            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                    continue;
                }

                $next = $tokens[$j];

                break;
            }

            if ($next === '(') {
                $findings[] = self::finding('', (int) $token[2], 'forbidden_function', 'Appel à ' . $function . '() : exécution de code ou de commande.');
            }
        }

        foreach (self::DYNAMIC_PATTERNS as $codeName => $pattern) {
            if (preg_match($pattern['regex'], $code, $match, PREG_OFFSET_CAPTURE) === 1) {
                $findings[] = self::finding('', substr_count(substr($code, 0, (int) $match[0][1]), "\n") + 1, $codeName, $pattern['message']);
            }
        }

        return $findings;
    }

    /**
     * Motifs qui n'ont pas de jeton dédié mais disent la même chose qu'une fonction
     * interdite : inclusion distante, `preg_replace` avec l'option `e` (exécution
     * du remplacement), déchiffrement suivi d'exécution.
     */
    private const DYNAMIC_PATTERNS = array(
        'remote_include' => array(
            'regex' => '/(include|require)(_once)?\s*\(?\s*[\'"](https?|ftp|php):\/\//i',
            'message' => 'Inclusion d\'une adresse distante : le module n\'est plus celui qu\'on a analysé.',
        ),
        'preg_replace_e' => array(
            'regex' => '/preg_replace\s*\([^)]*[\'"][^\'"]*e[^\'"]*[\'"]\s*,/i',
            'message' => 'preg_replace avec l\'option « e » : le remplacement est exécuté comme du code.',
        ),
        'decoded_execution' => array(
            'regex' => '/base64_decode\s*\([^)]*\)\s*(;|\))?\s*(eval|assert|create_function)\s*\(/is',
            'message' => 'Code déchiffré puis exécuté : obfuscation typique d\'une charge malveillante.',
        ),
    );

    /**
     * Tous les fichiers du dossier, récursivement, dans un ordre stable.
     *
     * @return array<int, string>
     */
    private static function walk(string $root): array
    {
        $paths = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            $paths[] = $file->getPathname();
        }

        sort($paths);

        return $paths;
    }

    /**
     * @param array<int, array{file: string, line: int, code: string, message: string}> $errors
     * @param array<int, array{file: string, line: int, code: string, message: string}> $warnings
     * @return array{ok: bool, errors: array<int, array{file: string, line: int, code: string, message: string}>, warnings: array<int, array{file: string, line: int, code: string, message: string}>, manifest: array<string, mixed>|null, files: int, bytes: int}
     */
    private static function result(array $errors, array $warnings, ?array $manifest, int $files, int $bytes): array
    {
        return array(
            'ok' => $errors === array(),
            'errors' => $errors,
            'warnings' => $warnings,
            'manifest' => $manifest,
            'files' => $files,
            'bytes' => $bytes,
        );
    }

    /** @return array{file: string, line: int, code: string, message: string} */
    private static function finding(string $file, int $line, string $code, string $message): array
    {
        return array('file' => $file, 'line' => $line, 'code' => $code, 'message' => $message);
    }
}
