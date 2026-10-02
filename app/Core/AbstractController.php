<?php

namespace App\Core;

use App\Services\BotService;
use App\Services\ModuleService;

abstract class AbstractController
{
    public static function legacyConstants(): array
    {
        return ['INSIDE' => true, 'INSTALL' => false];
    }

    protected function bootLegacy(array $constants = []): void
    {
        foreach ($constants as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    protected function user(): array
    {
        return $GLOBALS['user'] ?? [];
    }

    protected function planetRow(): array
    {
        return $GLOBALS['planetrow'] ?? [];
    }

    protected function galaxyRow(): array
    {
        return $GLOBALS['galaxyrow'] ?? [];
    }

    protected function gameConfig(): array
    {
        return $GLOBALS['game_config'] ?? [];
    }

    protected function lang(): array
    {
        return $GLOBALS['lang'] ?? [];
    }

    /**
     * Libellés de la pagination (App\Core\Paginator), communs au jeu et à
     * l'administration : les clés `pag_*` vivent dans `language/fr/system.mo`, le
     * seul fichier chargé des deux côtés.
     *
     * @param string $label titre de la liste, pour l'`aria-label` de la barre
     * @return array<string, string>
     */
    protected function paginationLabels(string $label = ''): array
    {
        $lang = $this->lang();

        return array(
            'label' => $label,
            'summary' => (string) ($lang['pag_summary'] ?? ''),
            'first' => (string) ($lang['pag_first'] ?? ''),
            'previous' => (string) ($lang['pag_previous'] ?? ''),
            'next' => (string) ($lang['pag_next'] ?? ''),
            'last' => (string) ($lang['pag_last'] ?? ''),
            'size' => (string) ($lang['pag_size'] ?? ''),
            'asc' => (string) ($lang['pag_asc'] ?? ''),
            'desc' => (string) ($lang['pag_desc'] ?? ''),
            'none' => (string) ($lang['pag_none'] ?? ''),
        );
    }

    /**
     * Case à cocher globale d'un tableau : elle coche ou décoche toutes les cases
     * du même groupe (`data-xnova-check`). Le balisage vit dans le gabarit partagé
     * `check_all.tpl` ; sans JavaScript la case ne fait rien et le formulaire
     * continue de fonctionner ligne par ligne.
     *
     * @param string $group valeur de `data-xnova-check` sur les cases de ligne
     * @param string $label libellé accessible (infobulle et `aria-label`)
     */
    protected function checkAll(string $group, string $label): string
    {
        return TemplateEngine::render('check_all', array(
            'check_group' => $group,
            'check_label' => $label,
        ));
    }

    protected function skinPath(): string
    {
        return $GLOBALS['dpath'] ?? '';
    }

    protected function resource(): array
    {
        return $GLOBALS['resource'] ?? [];
    }

    protected function resList(): array
    {
        return $GLOBALS['reslist'] ?? [];
    }

    protected function requirements(): array
    {
        return $GLOBALS['requeriments'] ?? [];
    }

    protected function setUser(mixed $user): void
    {
        $GLOBALS['user'] = $user;
    }

    protected function setPlanetRow(mixed $planetRow): void
    {
        $GLOBALS['planetrow'] = $planetRow;
    }

    protected function includeLang(string $name): void
    {
        includeLang($name);
    }

    protected function template(string $name): string
    {
        return gettemplate($name);
    }

    protected function parse(string $template, array $data): string
    {
        return parsetemplate($template, $data);
    }

    /**
     * Gabarit chargé puis rempli : le balisage reste dans `app/View/`, jamais
     * dans le contrôleur.
     *
     * @param array<string, mixed> $data
     */
    protected function partial(string $name, array $data = array()): string
    {
        return TemplateEngine::render($name, $data);
    }

    protected function renderPage(string $page, $title = '', $topnav = true, $metatags = '', $adminPage = false): Response
    {
        // Robots autonomes : un tour est joué à chaque affichage de page, borné par
        // BOTS_TICK_SECONDS et par le nombre d'actions par tour. Les routes API ne
        // passent pas par ici (leurs écritures sont trop rapprochées). Le service
        // résolu est celui du module « bot » quand il est utilisable, sinon la réponse
        // du Coeur d'application, qui ne fait rien : un incident côté robots ne casse jamais la page.
        try {
            ModuleService::instance(BotService::class)->tick();
        } catch (\Throwable) {
        }

        return Response::html(renderDisplay($page, $title, $topnav, $metatags, $adminPage));
    }

    protected function renderMessage(string $message, $title = 'Error', $dest = '', $time = '3', $color = 'orange'): Response
    {
        return Response::html(renderMessage($message, $title, $dest, $time, $color));
    }

    protected function captureLegacy(callable $callback): string
    {
        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    protected function redirect(string $location): Response
    {
        return Response::redirect($location);
    }
}
