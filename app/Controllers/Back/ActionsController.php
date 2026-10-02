<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Format;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ActionRepository;
use App\Services\ActionService;
use App\Services\SessionService;

/**
 * Panneau d'administration : journal des actions des joueurs.
 *
 * Une ligne par action demandée au serveur (page ouverte, écriture passée par
 * l'API JSON), avec la date, le compte, la méthode HTTP et l'adresse. Les robots
 * n'y figurent pas : leur activité se lit dans leurs tours de jeu.
 *
 * Trois filtres, combinables : la période (7, 30 ou 90 jours), le compte (saisi
 * par son nom, comme la vue générale) et la nature (pages ou appels API). Le
 * journal est borné à `ActionService::RETENTION_DAYS` jours, purgés à l'ouverture
 * de la page.
 */
final class ActionsController extends AdminController
{
    /** Adresse des filtres : la page s'appelle elle-même. */
    public const PATH = '/back/actions';

    public function __construct(
        private readonly ActionService $actions = new ActionService(),
        private readonly SessionService $sessions = new SessionService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.actions';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin');
        $this->includeLang('admin/actions');
        // Noms des bâtiments, recherches, vaisseaux et défenses : le journal les
        // affiche à côté des identifiants envoyés par le joueur.
        $this->includeLang('tech');

        $lang = $this->lang();

        $this->actions->prune();

        $days = ActionService::daysFilter($request->get('days'));
        $kind = ActionService::kindFilter($request->get('kind'));
        $player = SessionService::playerFilter($request->get('player'));
        $playerId = $this->sessions->playerId($player);

        // Comptes proposés à la saisie, et libellé du compte filtré : les mêmes
        // règles que la vue générale (une seule implémentation).
        $accountList = $this->sessions->accounts();
        $botLabel = (string) ($lang['ses_bot_badge'] ?? 'bot');
        $unknownLabel = (string) ($lang['ses_player_unknown'] ?? '');
        $playerName = SessionService::typedName($player, $accountList);
        $playerLabel = Format::text(SessionService::typedLabel($player, $accountList, $botLabel, $unknownLabel));

        $options = '';

        foreach ($accountList as $account) {
            $options .= $this->adminTemplate('datalist_option', $lang + array(
                'option_name' => Format::text((string) ($account['username'] ?? '')),
                'option_label' => Format::text(SessionService::accountLabel($account, $botLabel)),
            ));
        }

        $rows = '';

        // Le journal est paginé et triable : période, nature, compte, tri et taille
        // de page suivent dans les liens.
        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : null,
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            array_keys(ActionRepository::SORTS),
            'time'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $query = array('days' => (string) $days, 'kind' => $kind, 'player' => $player);
        $labels = $this->paginationLabels((string) ($lang['act_title'] ?? ''));

        $total = $this->actions->count($days, $playerId, $kind);
        $window = Paginator::window($total, $request->get('page'), $perPage);
        $pagination = Paginator::render(self::PATH, $query, $window, $labels, $sort);
        // Le choix de la taille vit au-dessus du tableau, la navigation en dessous.
        $pageBar = Paginator::sizeBar(self::PATH, $query, $window, $labels, $sort);
        $head = '';

        foreach (
            array(
            array('time', 'act_col_time', ''),
            array('user', 'act_col_player', ''),
            array('action', 'act_col_action', ''),
            array('method', 'act_col_method', ''),
            array('ip', 'act_col_ip', ''),
            ) as $column
        ) {
            $head .= Paginator::header(
                self::PATH,
                $query + array('per_page' => (string) $perPage),
                $sort,
                $column[0],
                (string) ($lang[$column[1]] ?? $column[0]),
                $labels,
                $column[2]
            );
        }

        foreach ($this->actions->recent($days, $playerId, $kind, 0, $window['offset'], $window['per_page'], $sort['field'], $sort['order']) as $row) {
            $payload = (string) ($row['payload'] ?? '');
            $player = Format::text((string) ($row['username'] ?? ''));

            $rows .= $this->adminTemplate('action_row', $lang + array(
                'act_time' => gmdate('d/m/Y H:i:s', (int) $row['action_time']),
                // Une adresse inconnue est journalisée sans compte : l'IP reste.
                'act_player' => $player === '' ? (string) ($lang['act_guest'] ?? '') : $player,
                'act_label' => Format::text(ActionService::label($lang, (string) $row['action'], $payload)),
                'act_method' => (string) ($row['method'] ?? ''),
                'act_kind' => (string) ($lang['act_kind_' . (string) $row['kind']] ?? (string) $row['kind']),
                'act_detail' => Format::text((string) ($row['detail'] ?? '')),
                'act_summary' => Format::text(ActionService::describe($lang, $payload)),
                'act_summary_class' => $payload === '' ? ' d-none' : '',
                'act_payload' => Format::text($payload),
                'act_payload_class' => $payload === '' ? ' d-none' : '',
                'act_ip' => Format::text((string) ($row['user_lastip'] ?? '')),
            ));
        }

        $totals = '';

        foreach ($this->actions->totals($days, $playerId, $kind) as $total) {
            $totals .= $this->adminTemplate('action_total', $lang + array(
                'act_total_label' => Format::text(ActionService::label($lang, (string) $total['action'])),
                'act_total_action' => Format::text((string) $total['action']),
                'act_total_kind' => (string) ($lang['act_kind_' . (string) $total['kind']] ?? (string) $total['kind']),
                'act_total_nb' => (string) (int) $total['nb'],
                'act_total_last' => gmdate('d/m/Y H:i:s', (int) $total['last_time']),
            ));
        }

        $filtered = $player !== '' || $kind !== 'all';

        $body = $this->adminPanel('actions_body', $lang + array(
            'act_head' => $head,
            'act_days' => $this->buttons($lang, 'days', ActionService::DAYS, 'act_days_label_', $days, $days, $kind, $player),
            'act_kinds' => $this->buttons($lang, 'kind', ActionService::KINDS, 'act_kind_', $kind, $days, $kind, $player),
            'act_options' => $options,
            'act_player_value' => Format::text($playerName),
            'act_player_label' => $playerLabel,
            'act_player_label_class' => $playerName === '' ? ' d-none' : '',
            'act_days_value' => (string) $days,
            'act_kind_value' => $kind,
            'act_filter_href' => $this->link('player', '', $days, $kind, ''),
            'act_filter_clear_class' => $filtered ? '' : ' d-none',
            'act_rows' => $rows,
            'act_page_bar' => $pageBar,
            'act_pagination' => $pagination,
            'act_totals' => $totals,
            'act_rows_empty' => $rows === '' ? '' : ' d-none',
            'act_totals_empty' => $totals === '' ? '' : ' d-none',
            'act_days_label' => (string) ($lang['act_days_label_' . $days] ?? $days),
            'act_kind_label' => (string) ($lang['act_kind_' . $kind] ?? $kind),
            'act_filtered_class' => $filtered ? '' : ' d-none',
            'act_retention' => (string) ActionService::RETENTION_DAYS,
        ), (string) ($lang['act_title'] ?? ''), 'bi-journal-text', $total . ' ' . (string) ($lang['act_count'] ?? ''));

        return $this->adminPage($body, (string) ($lang['act_title'] ?? 'Administration'));
    }

    /** Boutons d'un filtre : le même gabarit, rempli en boucle. */
    private function buttons(
        array $lang,
        string $param,
        array $values,
        string $prefix,
        int|string $current,
        int $days,
        string $kind,
        string $player
    ): string {
        $html = '';

        foreach ($values as $value) {
            $html .= $this->adminTemplate('filter_button', $lang + array(
                'filter_label' => (string) ($lang[$prefix . $value] ?? $value),
                'filter_href' => $this->link($param, (string) $value, $days, $kind, $player),
                'filter_active' => (string) $value === (string) $current ? ' active' : '',
            ));
        }

        return $html;
    }

    /** Lien vers la même page avec un filtre changé. */
    private function link(string $param, string $value, int $days, string $kind, string $player): string
    {
        $params = array(
            'days' => (string) $days,
            'kind' => $kind,
            'player' => $player,
        );
        $params[$param] = $value;
        $params = array_filter($params, static fn (string $item): bool => $item !== '');

        return self::PATH . '?' . str_replace('&', '&amp;', http_build_query($params));
    }
}
