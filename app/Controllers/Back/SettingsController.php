<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Api\CsrfToken;
use App\Core\GameConfig;
use App\Core\GameSpeed;
use App\Core\Request;
use App\Core\Response;

/**
 * Panneau d'administration : réglages du jeu.
 *
 * Reprend `admin/settings.php`. La page écrivait chaque réglage par un `UPDATE`
 * construit à la main, un par un, avec `addslashes()` : les écritures passent
 * maintenant par `GameConfig::set()` (une seule règle), et les champs sont
 * décrits par trois tables ci-dessous — texte, nombre, case à cocher — au lieu
 * d'être répétés dans le corps de la page.
 */
final class SettingsController extends AdminController
{
    /** Champs texte : une saisie vide ne remplace pas le réglage existant. */
    private const TEXT = array(
        'game_name' => 'game_name',
        'forum_url' => 'forum_url',
        'name_link_' => 'link_name',
        'url_link_' => 'link_url',
        'banner_source_post' => 'banner_source_post',
        'close_reason' => 'close_reason',
        'name_bot' => 'bot_name',
        'adress_bot' => 'bot_adress',
        'NewsText' => 'OverviewNewsText',
        'ExternChat' => 'OverviewExternChatCmd',
        'GoogleAds' => 'OverviewClickBanner',
    );

    /** Champs numériques : une saisie non numérique est ignorée. */
    private const NUMBERS = array(
        'resource_multiplier' => 'resource_multiplier',
        'stat_settings' => 'stat_settings',
        'initial_fields' => 'initial_fields',
        'metal_basic_income' => 'metal_basic_income',
        'crystal_basic_income' => 'crystal_basic_income',
        'deuterium_basic_income' => 'deuterium_basic_income',
        'energy_basic_income' => 'energy_basic_income',
        'enable_link_' => 'link_enable',
        'enable_announces_' => 'enable_announces',
        'enable_marchand_' => 'enable_marchand',
        'enable_notes_' => 'enable_notes',
        'duration_ban' => 'ban_duration',
        'bot_enable' => 'enable_bot',
        'bbcode_field' => 'enable_bbcode',
    );

    /**
     * Vitesses : saisies en **multiplicateur** (1 = normal, 1000 = mille fois
     * plus rapide), stockées dans l'unité du moteur (2500 = ×1). La conversion
     * vit dans `App\Core\GameSpeed` — jamais recopiée dans un formulaire.
     */
    private const SPEEDS = array(
        'game_speed' => 'game_speed',
        'fleet_speed' => 'fleet_speed',
    );

    /** Cases à cocher : « 1 » cochées, « 0 » sinon. */
    private const SWITCHES = array(
        'closed' => 'game_disable',
        'newsframe' => 'OverviewNewsFrame',
        'chatframe' => 'OverviewExternChat',
        'googlead' => 'OverviewBanner',
        'bannerframe' => 'ForumBannerFrame',
        'debug' => 'debug',
    );

    /** Texte qui accompagne une case : vidé quand elle est décochée. */
    private const COMPANIONS = array(
        'game_disable' => 'close_reason',
        'OverviewNewsFrame' => 'OverviewNewsText',
        'OverviewExternChat' => 'OverviewExternChatCmd',
        'OverviewBanner' => 'OverviewClickBanner',
    );

    protected function requiredPermission(): string
    {
        return 'admin.settings';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin/settings');
        // Les libellés des ressources (Métal, Cristal…) vivent dans `tech.mo`.
        $this->includeLang('tech');

        $lang = $this->lang();

        if (is_string($request->post('opt_save')) && $request->post('opt_save') !== '') {
            return $this->save($request);
        }

        $config = GameConfig::load();

        // Le formulaire demande le multiplicateur (1 = normal), le moteur lit son
        // diviseur : les deux champs de vitesse sont convertis pour l'affichage.
        $fields = $config;
        $fields['game_speed'] = (string) GameSpeed::buildMultiplier($config);
        $fields['fleet_speed'] = (string) GameSpeed::fleetMultiplier($config);

        $body = $this->adminPanel(
            'settings_form',
            $lang + $fields + array(
                'settings_action' => '/back/settings',
                'csrf_token' => CsrfToken::token(),
                'checked_game_disable' => $this->checked($config, 'game_disable'),
                'checked_news' => $this->checked($config, 'OverviewNewsFrame'),
                'checked_chat' => $this->checked($config, 'OverviewExternChat'),
                'checked_banner' => $this->checked($config, 'OverviewBanner'),
                'checked_forumbanner' => $this->checked($config, 'ForumBannerFrame'),
                'checked_debug' => $this->checked($config, 'debug'),
            ),
            (string) ($lang['adm_opt_title'] ?? ''),
            'bi-sliders',
            '',
            true
        );

        return $this->adminPage($body, (string) ($lang['adm_opt_title'] ?? 'Administration'));
    }

    /** Enregistre les réglages présents dans le formulaire. */
    private function save(Request $request): Response
    {
        $lang = $this->lang();

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['sys_noaccess'] ?? ''),
                (string) ($lang['sys_noalloaw'] ?? ''),
                '/back/settings',
                3,
                'red'
            );
        }

        $values = array();

        foreach (self::SWITCHES as $field => $name) {
            $on = $request->post($field) === 'on';
            $values[$name] = $on ? '1' : '0';

            // Le texte qui va avec la case est effacé quand on la décoche : c'est
            // ce que faisait la page historique, et il ne doit pas être rétabli
            // par le contenu resté dans la zone de texte.
            $companion = self::COMPANIONS[$name] ?? '';
            if (!$on && $companion !== '') {
                $values[$companion] = '';
            }
        }

        foreach (self::TEXT as $field => $name) {
            $value = trim((string) $request->post($field, ''));

            if ($value !== '' && !array_key_exists($name, $values)) {
                $values[$name] = $value;
            }
        }

        foreach (self::NUMBERS as $field => $name) {
            $value = $request->post($field);

            if (is_numeric($value)) {
                $values[$name] = (string) $value;
            }
        }

        // Vitesses : la saisie est un multiplicateur, on stocke le diviseur du moteur.
        foreach (self::SPEEDS as $field => $name) {
            $value = $request->post($field);

            if (is_numeric($value)) {
                $values[$name] = GameSpeed::stored($value);
            }
        }

        foreach ($values as $name => $value) {
            GameConfig::set($name, $value);
        }

        return $this->renderMessage(
            (string) ($lang['adm_opt_saved'] ?? ''),
            (string) ($lang['adm_opt_title'] ?? ''),
            '/back/settings',
            3,
            'lime'
        );
    }

    /** Marqueur `checked` d'une case à cocher. */
    private function checked(array $config, string $name): string
    {
        return (int) ($config[$name] ?? 0) === 1 ? ' checked' : '';
    }
}
