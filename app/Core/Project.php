<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Manifeste du **projet** : `package.json` à la racine du dépôt.
 *
 * Le projet se décrit comme un module (`Modules`) : mêmes clés, même fichier —
 * `name`, `label`, `description`, `version`, `author` — mais il n'est pas un module.
 * Rien ne le découvre sous `modules/`, il **est** le Coeur d'application.
 *
 * La **version** vient de là, et de nulle part ailleurs : plus de
 * `define('VERSION', …)` à tenir à jour en double. Elle reste égale à la plus récente
 * entrée de `$lang['changelog']` (`language/fr/changelog.mo`), et `ProjectTest` le
 * vérifie — c'est exactement ce qui avait dérivé (la constante annonçait 2026.25
 * quand le journal était à 2026.27).
 *
 * `label` et `description` portent le **texte** affiché : le projet n'a qu'une langue,
 * là où un module donne des clés de langue parce qu'il voyage avec ses traductions.
 *
 * Tout est pur : la classe lit un fichier JSON, ne touche ni base, ni session, ni
 * constante du jeu. Le fichier est lu une fois par requête.
 */
final class Project
{
    /** Manifeste du projet : le même nom de fichier que celui d'un module. */
    public const MANIFEST = Modules::MANIFEST;

    /** @var array<string, mixed>|null */
    private static ?array $manifest = null;

    /**
     * Contenu du manifeste, ou tableau vide s'il manque ou n'est pas du JSON.
     *
     * Un manifeste illisible ne doit **pas** faire tomber une page : l'affichage de
     * la version reste vide, c'est tout.
     *
     * @return array<string, mixed>
     */
    public static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $file = APP_ROOT . '/' . self::MANIFEST;
        $raw = is_file($file) ? (string) file_get_contents($file) : '';
        $decoded = $raw === '' ? null : json_decode($raw, true);

        self::$manifest = is_array($decoded) ? $decoded : array();

        return self::$manifest;
    }

    /** Valeur d'une clé, en texte ('' si elle manque : un manifeste reste déclaratif). */
    public static function value(string $key): string
    {
        $value = self::manifest()[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function name(): string
    {
        return self::value('name');
    }

    /** Libellé affiché : une **clé de langue**, jamais du texte (convention des manifestes). */
    public static function label(): string
    {
        return self::value('label');
    }

    public static function description(): string
    {
        return self::value('description');
    }

    /** Version du Coeur d'application, comparée aux dépendances `core` d'un module. */
    public static function version(): string
    {
        return self::value('version');
    }

    public static function author(): string
    {
        return self::value('author');
    }
}
