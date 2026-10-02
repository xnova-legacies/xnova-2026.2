<?php

namespace App\Core;

use App\Repositories\UserRepository;
use App\Services\ModuleService;

/**
 * Menu latéral de jeu.
 *
 * La logique de rendu vit ici : le tableau `ShowLeftMenu()`
 * (`app/Core/Legacy/LeftMenuFunctions.php`) ne fait que déléguer pour le code
 * legacy (`renderDisplay()`), et la page `/game/leftmenu` sert le même menu par
 * `LeftMenuController`.
 */
final class LeftMenu
{
    /**
     * Entrées du menu latéral apportées par un **module**, dans l'ordre du menu.
     *
     * Une entrée ne porte ni adresse ni libellé : l'adresse vient du manifeste du
     * module (`page`) et le libellé de sa langue (`label`, une clé `mod_<nom>`). Le
     * Coeur d'application n'écrit donc rien d'une page qu'il ne possède pas — et un module absent,
     * ou fermé au compte, n'apparaît pas du tout : l'entrée n'est pas écrite, elle
     * n'est pas cachée.
     *
     * La seconde valeur est la touche d'accès rapide du lien d'origine (`accesskey`).
     */
    private const GAME_MENU = array(
        'officier_link' => array('officier', 'o'),
        'marchand_link' => array('marchand', ''),
        'alliance_link' => array('alliance', 'a'),
        'records_link' => array('records', '3'),
        'chat_link' => array('chat', 'a'),
        'announce_link' => array('annonces', ''),
    );

    public static function render(int $level, string $template = 'left_menu'): string
    {
        $user = $GLOBALS['user'] ?? array();
        $gameConfig = GameConfig::load();
        $dpath = $GLOBALS['dpath'] ?? DEFAULT_SKINPATH;

        Language::include('leftmenu');

        $lang = Language::all();
        $menuTpl = TemplateEngine::load($template);
        $infoTpl = TemplateEngine::load('serv_infos');

        $parse = $lang;
        $parse['lm_tx_serv'] = $gameConfig['resource_multiplier'];
        $parse['lm_tx_game'] = GameSpeed::buildMultiplier($gameConfig);
        $parse['lm_tx_fleet'] = GameSpeed::fleetMultiplier($gameConfig);
        $parse['lm_tx_queue'] = GameConstants::maxUnitsPerRow();

        $subFrame = TemplateEngine::parse($infoTpl, $parse);
        $parse['server_info'] = $subFrame;
        $parse['XNovaRelease'] = Project::version();
        $parse['dpath'] = $dpath;
        $parse['forum_url'] = $gameConfig['forum_url'];
        $parse['mf'] = "_self";

        $statsRepository = new UserRepository();
        $rank = $statsRepository->findByRankSort((int) $user['id']);
        $parse['user_rank'] = $rank['total_rank'] ?? null;

        if ($level > 0) {
            $parse['ADMIN_LINK'] = '<a class="list-group-item list-group-item-action text-success" href="/back/overview" accesskey="a">' . $lang['user_level'][$level] . '</a>';
        } else {
            $parse['ADMIN_LINK'] = "";
        }

        // Lien supplémentaire déterminé dans le panel admin
        if ($gameConfig['link_enable'] == 1) {
            $parse['added_link'] = '<a class="list-group-item list-group-item-action" href="' . $gameConfig['link_url'] . '" target="_blank">' . stripslashes($gameConfig['link_name']) . '</a>';
        } else {
            $parse['added_link'] = "";
        }

        // Les pages d'un **module** vivent dans son module : le menu ne connaît donc
        // aucune de leurs adresses. Il demande au registre quelles entrées sont
        // ouvertes (`accessible()` : module déposé, allumé, dépendances tenues,
        // permission du rôle) et prend l'adresse au manifeste, le libellé à sa langue.
        $modules = new ModuleService();
        Language::includeModules();
        $moduleLang = Language::all();

        foreach (self::GAME_MENU as $marker => $entry) {
            list($name, $accesskey) = $entry;
            $module = Modules::all()[$name] ?? null;
            $page = (string) ($module['page'] ?? '');
            $label = (string) ($moduleLang[(string) ($module['label'] ?? '')] ?? $name);

            $parse[$marker] = ($page !== '' && $modules->accessible($name, $user))
                ? self::moduleLink($page, $label, $accesskey)
                : '';
        }

        $parse['servername'] = $gameConfig['game_name'];

        return TemplateEngine::parse($menuTpl, $parse);
    }

    /**
     * Entrée de menu d'un module : le balisage vit dans un gabarit, le libellé
     * vient du module (`modules/<nom>/language/`) et l'adresse du manifeste.
     *
     * La touche d'accès rapide est un **bloc d'attribut** (`''` ou ` accesskey="o"`) :
     * un gabarit ne doit jamais rendre un attribut vide.
     */
    private static function moduleLink(string $url, string $label, string $accesskey = ''): string
    {
        return TemplateEngine::render('left_menu_link', array(
            'menu_link_url' => $url,
            'menu_link_label' => $label,
            'menu_link_accesskey' => $accesskey === ''
                ? ''
                : ' accesskey="' . htmlspecialchars($accesskey, ENT_QUOTES) . '"',
        ));
    }
}
