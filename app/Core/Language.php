<?php

namespace App\Core;

/**
 * Gestion de la localisation (ex includeLang dans unlocalised.php).
 * Les fichiers de langue définissent $lang[...] dans leur scope : on les
 * inclut dans une fonction puis on fusionne vers $GLOBALS['lang'].
 */
final class Language
{
    private static array $loaded = array();

    public static function include(string $filename, string $extension = '.mo'): void
    {

        if (isset(self::$loaded[$filename])) {
            return;
        }

        self::$loaded[$filename] = true;

        // Le jeu d'abord, puis les modules : les libellés d'un module voyagent avec
        // lui (`modules/<nom>/language/`), sans que le jeu ait à connaître son nom.
        foreach (self::languageRoots() as $root) {
            foreach (array(self::currentLang(), 'fr') as $lang) {
                foreach (array('.csv', $extension) as $suffix) {
                    $candidate = $root . '/' . $lang . '/' . $filename . $suffix;

                    if (is_readable($candidate)) {
                        self::includeLanguageFile($candidate);

                        // Les libellés des modules **repassent après** celui du jeu :
                        // un module peut nommer ce qu'il dépose (son vaisseau, sa
                        // mission) sans que `language/` du Coeur d'application soit modifié — et
                        // l'ordre d'inclusion ne peut pas les écraser.
                        self::applyModuleLabels();

                        return;
                    }
                }
            }
        }
    }

    /**
     * Dossiers de langue, dans l'ordre de recherche : le jeu, puis chaque module.
     *
     * @return array<int, string>
     */
    private static function languageRoots(): array
    {
        $roots = array(APP_ROOT . '/language');

        foreach (Modules::names() as $name) {
            $roots[] = Modules::directory() . $name . '/language';
        }

        return $roots;
    }

    private static function includeLanguageFile(string $file): void
    {
        include $file;


        if (isset($lang) && is_array($lang)) {
            // Fusion **profonde** : un module qui ajoute `$lang['tech'][216]` ne doit pas
            // effacer les autres noms de vaisseaux. `array_merge` est superficiel : il
            // remplaçait toute la clé `tech` par le seul tableau du module, et les
            // libellés du jeu disparaissaient (missions sans texte).
            $GLOBALS['lang'] = array_replace_recursive($GLOBALS['lang'] ?? array(), $lang);
        }
    }

    /**
     * Ré-applique les libellés des modules **par-dessus** ceux du jeu.
     *
     * Un fichier de langue du Coeur d'application écrase la clé qu'il définit (`$lang['tech']`
     * arrive en entier) : sans cette passe, le nom d'un vaisseau déposé par un
     * module disparaîtrait dès que `tech.mo` est chargé après lui. Les libellés d'un
     * module voyagent donc avec lui, et le Coeur d'application n'a aucune ligne à changer.
     */
    private static function applyModuleLabels(): void
    {
        foreach (self::moduleLanguageFiles() as $file) {
            self::includeLanguageFile($file);
        }
    }

    /**
     * Fichiers de langue des modules déposés (langue du compte, puis le français).
     *
     * Fonction pure : le registre lit les manifestes, jamais la base. Un module
     * éteint garde ses libellés — c'est l'interrupteur qui ferme ses pages, pas sa
     * langue.
     *
     * @return list<string>
     */
    private static function moduleLanguageFiles(): array
    {
        static $files = null;

        if ($files !== null) {
            return $files;
        }

        $found = array();

        foreach (Modules::names() as $name) {
            foreach (array(self::currentLang(), 'fr') as $lang) {
                foreach ((array) glob(Modules::directory() . $name . '/language/' . $lang . '/*.mo') as $file) {
                    $found[(string) $file] = (string) $file;
                }
            }
        }

        return $files = array_values($found);
    }

    /**
     * Libellés d'un **module** : tous les fichiers de son dossier `language/`.
     *
     * Un module apporte ses propres libellés (`modules/<nom>/language/<lang>/*.mo`),
     * comme le reste du jeu. Le panneau des modules et le message de refus d'un
     * module éteint les chargent à la demande ; la langue du compte d'abord, le
     * français ensuite, comme `include()`.
     */
    public static function includeModule(string $name): void
    {
        $key = 'module:' . $name;

        if (isset(self::$loaded[$key])) {
            return;
        }

        self::$loaded[$key] = true;

        $directories = array(
            Modules::directory() . $name . '/language/' . self::currentLang() . '/',
            Modules::directory() . $name . '/language/fr/',
        );

        foreach ($directories as $directory) {
            foreach ((array) glob($directory . '*.mo') as $file) {
                self::includeLanguageFile((string) $file);
            }
        }
    }

    /** Libellés de **tous** les modules déposés (page des modules, formulaire d'un rôle). */
    public static function includeModules(): void
    {
        foreach (Modules::names() as $name) {
            self::includeModule($name);
        }
    }

    /**
     * Libellés d'un module **archivé** (désinstallé du panneau).
     *
     * Ils voyagent avec son module, qui n'est plus découvert : le panneau doit pourtant
     * pouvoir le nommer pour le réinstaller.
     */
    public static function includeArchived(string $name): void
    {
        $key = 'archived:' . $name;

        if (isset(self::$loaded[$key])) {
            return;
        }

        self::$loaded[$key] = true;

        $directories = array(
            Modules::archiveDirectory($name) . 'language/' . self::currentLang() . '/',
            Modules::archiveDirectory($name) . 'language/fr/',
        );

        foreach ($directories as $directory) {
            foreach ((array) glob($directory . '*.mo') as $file) {
                self::includeLanguageFile((string) $file);
            }
        }
    }

    /** Langue du compte connecté, ou le français (les pages publiques n'en ont pas). */
    private static function currentLang(): string
    {
        $userLang = (string) ($GLOBALS['user']['lang'] ?? '');

        return $userLang !== '' ? $userLang : 'fr';
    }

    public static function all(): array
    {
        return $GLOBALS['lang'] ?? array();
    }

    public static function get(string $key, $default = null)
    {
        return $GLOBALS['lang'][$key] ?? $default;
    }
}
