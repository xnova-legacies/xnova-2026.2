<?php

namespace App\Core;

/**
 * Moteur de templates legacy ({placeholder}) + rendu de page complet.
 * Porte-méthode de functions.php (parsetemplate/getTemplate/display/renderDisplay)
 * et unlocalised.php (ReadFromFile/saveToFile).
 */
final class TemplateEngine
{
    public static function parse(string $template, array $array): string
    {
        return preg_replace_callback('#\{([a-z0-9\-_]*?)\}#Ssi', function ($M) use ($array) {
            if (!isset($array[$M[1]])) {
                $array[$M[1]] = (!isset($_GET['DEBUG'])) ? null : $M[1];
            }

            return $array[$M[1]];
        }, $template);
    }

    /**
     * Chemin d'un gabarit, une fois par nom et par requête.
     *
     * @var array<string, string>
     */
    private static array $resolved = array();

    public static function load(string $templateName): string
    {
        return (string) @file_get_contents(self::templateFile($templateName));
    }

    /**
     * Fichier d'un gabarit : celui du jeu, sinon celui d'un module.
     *
     * Un module apporte ses vues dans son module (`modules/<nom>/view/`), comme le
     * reste : ses pages se lisent avec les gabarits du jeu (`topnav`, `pagination`…)
     * et c'est le nom du gabarit qui décide, pas l'appelant.
     */
    public static function templateFile(string $templateName): string
    {
        if (isset(self::$resolved[$templateName])) {
            return self::$resolved[$templateName];
        }

        $file = self::templateDirectory() . $templateName . '.tpl';

        if (is_file($file)) {
            return self::$resolved[$templateName] = $file;
        }

        foreach ((array) glob(Modules::directory() . '*/view/' . $templateName . '.tpl') as $moduleFile) {
            return self::$resolved[$templateName] = (string) $moduleFile;
        }

        // Introuvable : on rend le chemin attendu, l'appelant reçoit une chaîne vide
        // (un gabarit absent ne doit pas arrêter la page).
        return self::$resolved[$templateName] = $file;
    }

    /**
     * Dossier des gabarits.
     *
     * Les constantes viennent de `common.php` sur une page ; un service peut aussi
     * rendre un gabarit hors de ce contexte (API, script en ligne de commande,
     * test) : on retombe alors sur `app/View/OpenGame/`, le dossier du jeu.
     */
    private static function templateDirectory(): string
    {
        if (defined('TEMPLATE_DIR') && defined('TEMPLATE_NAME')) {
            return rtrim((string) TEMPLATE_DIR, '/\\') . '/' . (string) TEMPLATE_NAME . '/';
        }

        $root = defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

        return rtrim($root, '/\\') . '/app/View/OpenGame/';
    }

    /**
     * Gabarit chargé puis rempli : le raccourci à utiliser partout.
     *
     * Un bloc de balisage vit dans un seul fichier `.tpl` ; s'il doit apparaître
     * plusieurs fois (une ligne de tableau), c'est le même gabarit qu'on remplit
     * en boucle — jamais une copie du balisage dans le PHP ni un second gabarit
     * identique.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $templateName, array $data = array()): string
    {
        return self::parse(self::load($templateName), $data);
    }

    public static function readFile(string $filename): string
    {
        return (string) @file_get_contents($filename);
    }

    public static function saveFile(string $filename, string $content): bool
    {
        return file_put_contents($filename, $content) !== false;
    }

    /**
     * Rendu complet d'une page (header + topnav + menu + footer).
     * Porte la logique de renderDisplay() de functions.php.
     */
    public static function renderDisplay($page, $title = '', $topnav = true, $metatags = '', $AdminPage = false): string
    {
        global $link, $game_config, $debug, $user, $planetrow;

        ob_start();

        if (!$AdminPage) {
            $DisplayPage = self::stdUserHeader($title, $metatags);
        } else {
            $DisplayPage = self::adminUserHeader($title, $metatags);
        }

        if ($topnav) {
            $DisplayPage .= ShowTopNavigationBar($user, $planetrow);
        }

        if (!$AdminPage && !defined('LOGIN') && !defined('POPUP') && !empty($user['id'])) {
            require_once ROOT_PATH . 'app/Core/Legacy/LeftMenuFunctions.' . PHPEXT;
            $menu = ShowLeftMenu($user['authlevel']);
            // Panneau latéral des files d'attente (App\Core\QueueBar) : une colonne
            // à droite, sur toutes les pages de jeu. Elle n'existe que si quelque
            // chose est en chantier — sinon la page reprend toute sa largeur.
            $QueueBar = QueueBar::column($user, $planetrow);
            $ContentCol = $QueueBar === '' ? 'col-12 col-lg-9 col-xxl-10' : 'col-12 col-lg-6 col-xxl-8';
            $DisplayPage .= '<div class="container-fluid xnova-shell">';
            $DisplayPage .= '<div class="row g-3 g-xl-4">';
            $DisplayPage .= '<aside class="col-12 col-lg-3 col-xxl-2 xnova-sidebar">' . $menu . '</aside>';
            $DisplayPage .= '<main class="' . $ContentCol . ' xnova-content">' . $page . '</main>';
            $DisplayPage .= $QueueBar;
            $DisplayPage .= '</div>';
            $DisplayPage .= '</div>';
        } else {
            $DisplayPage .= '<div class="xnova-page">' . $page . '</div>';
        }

        if (isset($user['authlevel']) && ($user['authlevel'] == 1 || $user['authlevel'] == 3)) {
            if (($game_config['debug'] ?? 0) == 1) {
                $debug->echo_log();
            }
        }

        $DisplayPage .= self::stdFooter();

        if (isset($link)) {
            mysql_close($link);
        }

        echo $DisplayPage;

        return ob_get_clean();
    }

    /** Rendu d'un message d'erreur/success (ex renderMessage). */
    public static function renderMessage($mes, $title = 'Error', $dest = "", $time = "3", $color = 'orange'): string
    {
        $mes = LegacyMessageText($mes, $color);
        $dest = LegacyMessageDestination($dest);

        $parse['color'] = $color;
        $parse['variant'] = BootstrapVariantFromColor($color);
        $parse['title'] = $title;
        $parse['mes'] = $mes;

        $page = self::parse(self::load('admin/message_body'), $parse);

        // Même remarque que dans includes/functions.php : la page de message est
        // autonome, on y charge le Coeur d'application AJAX pour la notification et le retour.
        $noticeScript = '<script src="/scripts/xnova-ajax.js?v=' . (int) @filemtime(ROOT_PATH . 'scripts/xnova-ajax.js') . '" defer></script>' . "\n";

        return self::renderDisplay($page, $title, false, $noticeScript . (($dest != "") ? "<meta http-equiv=\"refresh\" content=\"$time;url=$dest\">" : ""), true);
    }

    private static function stdUserHeader($title = '', $metatags = ''): string
    {
        global $user;

        $parse = is_array($GLOBALS['langInfos'] ?? null) ? $GLOBALS['langInfos'] : array();
        $parse['title'] = $title;
        $parse['lang'] = (!empty($user['lang'])) ? $user['lang'] : 'fr';
        $parse['theme'] = 'dark';
        $parse['dpath'] = (!empty($GLOBALS['dpath'])) ? $GLOBALS['dpath'] : DEFAULT_SKINPATH;

        $styles = BootstrapStylesheetTag();

        if (defined('LOGIN')) {
            $styles .= '<link rel="stylesheet" href="/css/styles.css">' . "\n";
            $styles .= '<link rel="stylesheet" href="/css/about.css">' . "\n";
            $styles .= '<link rel="stylesheet" href="/css/bootstrap-compat.css">' . "\n";
        } else {
            // Feuilles legacy conservees en repli (pages non migrees).
            $styles .= '<link rel="stylesheet" href="' . $parse['dpath'] . 'default.css">' . "\n";
            $styles .= '<link rel="stylesheet" href="' . $parse['dpath'] . 'formate.css">' . "\n";
            $styles .= '<link rel="stylesheet" href="/css/styles.css">' . "\n";
            $styles .= '<link rel="stylesheet" href="/css/bootstrap-compat.css">' . "\n";
        }

        $parse['-style-'] = $styles;
        $parse['-meta-'] = ($metatags) ? $metatags : "";
        $parse['-body-'] = '<body class="xnova-body">';
        // Assets du module qui sert la page (vide pour une page du jeu).
        $parse['-script-'] = BootstrapScriptTag() . self::checkAllScript() . Modules::assetTags();

        return self::parse(self::load('simple_header'), $parse);
    }

    private static function adminUserHeader($title = '', $metatags = ''): string
    {
        global $dpath, $user;

        $parse = is_array($GLOBALS['langInfos'] ?? null) ? $GLOBALS['langInfos'] : array();
        $parse['dpath'] = ($dpath) ? $dpath : DEFAULT_SKINPATH;
        $parse['title'] = $title;
        $parse['lang'] = (!empty($user['lang'])) ? $user['lang'] : 'fr';
        $parse['theme'] = 'dark';

        $styles = BootstrapStylesheetTag();
        $styles .= '<link rel="stylesheet" href="' . $parse['dpath'] . 'formate.css">' . "\n";
        $styles .= '<link rel="stylesheet" href="/css/bootstrap-compat.css">' . "\n";

        $parse['-style-'] = $styles;
        $parse['-meta-'] = ($metatags) ? $metatags : "";
        $parse['-body-'] = '<body class="xnova-body">';
        $parse['-script-'] = BootstrapScriptTag() . self::checkAllScript();

        return self::parse(self::load('admin/simple_header'), $parse);
    }

    /**
     * Case à cocher globale des tableaux : le script vit à part du Coeur d'application AJAX, que
     * l'administration ne charge pas. La date de modification sert de version.
     */
    private static function checkAllScript(): string
    {
        $file = ROOT_PATH . 'scripts/xnova-checkall.js';

        return '<script src="/scripts/xnova-checkall.js?v=' . (int) @filemtime($file) . '" defer></script>' . "\n";
    }

    private static function stdFooter(): string
    {
        global $game_config;

        $parse['copyright'] = $game_config['copyright'] ?? 'XNova Support Team';
        $parse['TranslationBy'] = Language::get('TranslationBy', '');

        return self::parse(self::load('overall_footer'), $parse);
    }
}
