<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Format;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SessionRepository;
use App\Services\BotService;
use App\Services\SessionService;

/**
 * Panneau d'administration : vue générale.
 *
 * C'est l'historique des connexions qui fait la vue générale (table `sessions`) :
 * une ligne par connexion, avec son début, sa dernière activité, sa durée et son
 * motif de fin (déconnexion, inactivité). L'ancienne liste « joueurs connectés
 * depuis moins de quinze minutes » a été fusionnée ici : elle ne disait que ce
 * que l'historique dit déjà, en mieux (les robots sont enregistrés comme les
 * joueurs — leur tour de jeu vaut connexion — et marqués comme robots).
 *
 * Deux filtres, combinables : la période (heure, jour, semaine, mois, année) et
 * le compte (un joueur précis, un robot, ou tous). Les courbes sont dessinées en
 * SVG par le serveur et les valeurs exactes restent dans les tableaux : la page
 * ne dépend d'aucun script.
 */
final class OverviewController extends AdminController
{
    /** Adresse des filtres : la page s'appelle elle-même, et l'ancienne adresse y renvoie. */
    public const PATH = '/back/overview';

    public function __construct(
        private readonly SessionService $sessions = new SessionService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.overview';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin');
        $this->includeLang('admin/sessions');

        $lang = $this->lang();

        // Consulter la page referme les connexions inactives : l'historique reste
        // net sans tâche de fond.
        $this->sessions->sweep();

        $range = SessionService::rangeKey($request->get('range'));
        $type = $this->typeFilter($request->get('type'));
        $player = SessionService::playerFilter($request->get('player'));
        $playerId = $this->sessions->playerId($player, $type);

        $series = $this->sessions->series($range, 0, $playerId, $type);
        $now = time();

        $accountList = $this->sessions->accounts($type);
        $botLabel = (string) ($lang['ses_bot_badge'] ?? '');
        $options = '';

        foreach ($accountList as $account) {
            $options .= $this->adminTemplate('datalist_option', $lang + array(
                'option_name' => Format::text((string) $account['username']),
                'option_label' => Format::text(SessionService::accountLabel($account, $botLabel)),
            ));
        }

        // Nom saisi : le nom du compte (quand il existe) part dans la saisie et
        // dans le rappel du filtre, marqué « bot » ou « inconnu » au besoin.
        $playerName = SessionService::typedName($player, $accountList);
        $playerValue = Format::text($playerName);
        $playerLabel = Format::text(SessionService::typedLabel(
            $player,
            $accountList,
            $botLabel,
            (string) ($lang['ses_player_unknown'] ?? '')
        ));

        $charts = '';

        foreach (array('sessions', 'seconds') as $metric) {
            $points = SessionService::chartPoints($series[$metric]);
            $charts .= $this->adminTemplate('sessions_chart', $lang + array(
                'ses_chart_title' => (string) ($lang['ses_chart_' . $metric] ?? $metric),
                'ses_chart_line' => $points['line'],
                'ses_chart_area' => $points['area'],
                'ses_chart_labels' => $this->labels($lang, $series['labels'], (int) $series['label_step']),
            ));
        }

        $rows = '';

        // La liste des connexions est paginée et triable : filtres, tri et taille de
        // page suivent dans les liens.
        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : null,
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            array_keys(SessionRepository::SORTS),
            'id'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $listQuery = array('range' => $range, 'type' => $type, 'player' => $player);
        $labels = $this->paginationLabels((string) ($lang['ses_recent_title'] ?? ''));
        $window = Paginator::window($this->sessions->countRecent($playerId, $type), $request->get('page'), $perPage);
        $head = '';

        foreach (
            array(
                array('login', 'ses_col_login'),
                array('user', 'ses_col_player'),
                array('ip', 'ses_col_ip'),
                array('activity', 'ses_col_last'),
                array('duration', 'ses_col_duration'),
                array('', 'ses_col_state'),
                array('end', 'ses_col_end'),
            ) as $column
        ) {
            $label = (string) ($lang[$column[1]] ?? $column[1]);

            $head .= $column[0] === ''
                ? $this->adminTemplate('table_head', array('th_label' => $label, 'th_class' => ''))
                : Paginator::header(self::PATH, $listQuery + array('per_page' => (string) $perPage), $sort, $column[0], $label, $labels);
        }

        foreach ($this->sessions->recent($window['per_page'], $playerId, $type, $window['offset'], $sort['field'], $sort['order']) as $row) {
            $rows .= $this->adminTemplate('session_row', $lang + $this->botMarkers($lang, $row) + array(
                'ses_login' => gmdate('d/m/Y H:i:s', (int) $row['login_time']),
                'ses_player' => Format::text((string) ($row['username'] ?? '')),
                'ses_ip' => Format::text((string) ($row['user_lastip'] ?? '')),
                'ses_last' => Format::prettyTime($now - (int) $row['last_activity']),
                'ses_duration' => Format::prettyTime(SessionService::duration($row, $now)),
                'ses_state' => (string) ($lang['ses_state_' . SessionService::state($row, $now)] ?? ''),
                'ses_end' => (string) ($lang['ses_end_' . ((string) $row['end_reason'] !== '' ? $row['end_reason'] : 'open')] ?? ''),
            ));
        }

        $totals = '';

        foreach ($this->sessions->totalsByUser($range, SessionService::TOTALS_LIMIT, 0, $playerId, $type) as $total) {
            $totals .= $this->adminTemplate('session_total', $lang + $this->botMarkers($lang, $total) + array(
                'ses_row_player' => Format::text((string) ($total['username'] ?? '')),
                'ses_row_sessions' => (string) (int) $total['nb_sessions'],
                'ses_row_time' => Format::prettyTime((int) $total['seconds']),
            ));
        }

        $filtered = $player !== '' || $type !== 'all';

        $body = $this->adminPanel('sessions_body', $lang + array(
            'ses_periods' => $this->buttons($lang, 'range', SessionService::RANGES, 'ses_range_', $range, $range, $type, $player),
            'ses_types' => $this->buttons($lang, 'type', $this->types(), 'ses_type_', $type, $range, $type, $player),
            'ses_options' => $options,
            'ses_player_value' => $playerValue,
            'ses_player_label_class' => $playerName === '' ? ' d-none' : '',
            'ses_range_value' => $range,
            'ses_type_value' => $type,
            'ses_filter_href' => $this->link('player', '', $range, $type, ''),
            'ses_filter_clear_class' => $filtered ? '' : ' d-none',
            'ses_charts' => $charts,
            'ses_rows' => $rows,
            'ses_head' => $head,
            'ses_page_bar' => Paginator::sizeBar(self::PATH, $listQuery, $window, $labels, $sort),
            'ses_pagination' => Paginator::render(self::PATH, $listQuery, $window, $labels, $sort),
            'ses_totals' => $totals,
            'ses_rows_empty' => $rows === '' ? '' : ' d-none',
            'ses_totals_empty' => $totals === '' ? '' : ' d-none',
            'ses_summary_sessions' => (string) $series['total_sessions'] . ' ' . (string) ($lang['ses_count'] ?? ''),
            'ses_summary_time' => Format::prettyTime($series['total_seconds']),
            'ses_range_label' => (string) ($lang['ses_range_' . $range] ?? $range),
            'ses_type_label' => (string) ($lang['ses_type_' . $type] ?? $type),
            'ses_player_label' => $playerLabel,
            'ses_filtered_class' => $filtered ? '' : ' d-none',
        ), (string) ($lang['ses_title'] ?? ''), 'bi-activity', $window['total'] . ' ' . (string) ($lang['ses_count'] ?? ''));

        return $this->adminPage($body, (string) ($lang['sys_overview'] ?? 'Administration'));
    }

    /**
     * Marqueurs « bot » d'un compte : la classe cache le badge pour les joueurs, et
     * pour tout le monde quand le module des robots n'est pas utilisable.
     *
     * @return array{ses_bot_class: string, ses_bot_label: string}
     */
    private function botMarkers(array $lang, array $row): array
    {
        return array(
            'ses_bot_class' => BotService::present() && SessionService::isBot($row) ? '' : ' d-none',
            'ses_bot_label' => (string) ($lang['ses_bot_badge'] ?? 'bot'),
        );
    }

    /**
     * Types de compte du filtre : sans le module des robots, la question ne se pose
     * plus — aucun compte n'est un robot — et le bouton disparaît avec eux.
     *
     * @return array<int, string>
     */
    private function types(): array
    {
        return BotService::present()
            ? SessionService::TYPES
            : array_values(array_diff(SessionService::TYPES, array('bots')));
    }

    /**
     * Filtre de type demandé par l'adresse : un filtre « robots » sans module retombe
     * sur tous les comptes, au lieu de vider la page.
     */
    private function typeFilter(?string $value): string
    {
        $type = SessionService::typeFilter($value);

        return $type === 'bots' && !BotService::present() ? 'all' : $type;
    }

    /** Boutons d'un filtre : le même gabarit, rempli en boucle. */
    private function buttons(
        array $lang,
        string $param,
        array $values,
        string $prefix,
        string $current,
        string $range,
        string $type,
        string $player
    ): string {
        $html = '';

        foreach ($values as $value) {
            $html .= $this->adminTemplate('filter_button', $lang + array(
                'filter_label' => (string) ($lang[$prefix . $value] ?? $value),
                'filter_href' => $this->link($param, $value, $range, $type, $player),
                'filter_active' => $value === $current ? ' active' : '',
            ));
        }

        return $html;
    }

    /** Lien vers la même page avec un filtre changé. */
    private function link(string $param, string $value, string $range, string $type, string $player): string
    {
        $params = array(
            'range' => $range,
            'type' => $type,
            'player' => $player,
        );
        $params[$param] = $value;
        $params = array_filter($params, static fn(string $item): bool => $item !== '');

        return self::PATH . '?' . str_replace('&', '&amp;', http_build_query($params));
    }

    /**
     * Libellés de l'axe des abscisses, un sur `label_step` pour rester lisible.
     *
     * Les périodes horaires donnent une date (`14:32`, `03/09`) ; l'axe des jours
     * de la semaine donne un numéro de jour (1 = lundi), traduit ici.
     *
     * @param list<string> $labels
     */
    private function labels(array $lang, array $labels, int $step): string
    {
        $html = '';

        foreach ($labels as $index => $label) {
            if ($index % max(1, $step) !== 0) {
                continue;
            }

            $html .= $this->adminTemplate('sessions_label', $lang + array(
                'ses_label' => (string) ($lang['ses_weekday_' . $label] ?? $label),
            ));
        }

        return $html;
    }
}
