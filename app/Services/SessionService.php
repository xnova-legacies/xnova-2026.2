<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SessionRepository;

/**
 * Historique des sessions : une ligne par connexion, et les courbes de connexion.
 *
 * Trois seuils, dans l'ordre où un joueur s'éloigne :
 *   - `IDLE_AFTER` (5 min) sans rafraîchissement : la connexion est **inactive** ;
 *   - `CLOSE_AFTER` (15 min) : elle est **terminée** — même règle que « joueurs en
 *     ligne » du jeu (`users.onlinetime` sur un quart d'heure). Une connexion
 *     terminée par inactivité est datée au seuil, jamais au moment du balayage :
 *     la courbe ne compte pas le temps où le joueur n'était plus là.
 *   - `TOUCH_INTERVAL` (1 min) : le rafraîchissement n'est écrit qu'une fois par
 *     minute, quel que soit le nombre de pages ouvertes.
 *
 * Une connexion terminée puis reprise par le même joueur ouvre une **nouvelle**
 * ligne : c'est ce que veut dire « une ligne par connexion ».
 */
final class SessionService
{
    public const IDLE_AFTER = 300;

    public const CLOSE_AFTER = 900;

    public const TOUCH_INTERVAL = 60;

    public const RECENT_LIMIT = 50;

    public const TOTALS_LIMIT = 20;

    /** Périodes proposées par la page d'administration. */
    public const RANGES = array('hour', 'day', 'week', 'month', 'year', 'weekday');

    /** Nombre de semaines réunies par la statistique « jour de la semaine ». */
    public const WEEKDAY_WEEKS = 4;

    /** Nombre de comptes proposés par la saisie de nom (datalist) de la page. */
    public const ACCOUNT_SUGGESTIONS = 500;

    /** Types de compte enregistrés par l'historique. */
    public const TYPES = array('all', 'players', 'bots');

    private readonly SessionRepository $sessions;

    /**
     * Le dépôt est **résolu** (`ModuleService`) et non nommé en dur : le module qui
     * possède la colonne des robots dérive la classe du Coeur d'application, et c'est la sienne qui
     * répond — sans lui, la classe du jeu sert exactement comme avant.
     */
    public function __construct(?SessionRepository $sessions = null)
    {
        $this->sessions = $sessions ?? ModuleService::instance(SessionRepository::class);
    }

    // ------------------------------------------------------- règles pures

    /** Normalise la période demandée (`hour` par défaut). */
    public static function rangeKey(?string $range): string
    {
        $key = strtolower(trim((string) $range));

        return in_array($key, self::RANGES, true) ? $key : 'hour';
    }

    /** Normalise le type de compte demandé (`all` par défaut). */
    public static function typeFilter(?string $type): string
    {
        $key = strtolower(trim((string) $type));

        return in_array($key, self::TYPES, true) ? $key : 'all';
    }

    /**
     * Un compte est-il un robot ? (`users.bot`)
     *
     * Question **pure**, posée à la ligne : c'est l'affichage qui décide de montrer
     * la marque (`BotService::present()`), jamais cette lecture — la colonne reste en
     * base même quand le module des robots est éteint.
     */
    public static function isBot(array $row): bool
    {
        return (int) ($row['bot'] ?? 0) === 1;
    }

    /** État d'une connexion : `active`, `idle` (5 min) ou `closed` (15 min). */
    public static function state(array $row, int $now): string
    {
        if ((int) ($row['logout_time'] ?? 0) > 0) {
            return 'closed';
        }

        $idle = $now - (int) ($row['last_activity'] ?? 0);

        if ($idle >= self::CLOSE_AFTER) {
            return 'closed';
        }

        return $idle >= self::IDLE_AFTER ? 'idle' : 'active';
    }

    /** Fin retenue pour une connexion : réelle, sinon le seuil d'inactivité. */
    public static function endMoment(array $row, int $now): int
    {
        $logout = (int) ($row['logout_time'] ?? 0);

        if ($logout > 0) {
            return $logout;
        }

        return min($now, (int) ($row['last_activity'] ?? 0) + self::CLOSE_AFTER);
    }

    /** Durée retenue d'une connexion, en secondes (jamais négative). */
    public static function duration(array $row, int $now): int
    {
        return max(0, self::endMoment($row, $now) - (int) ($row['login_time'] ?? 0));
    }

    /**
     * Fenêtre d'une période : bornes, seaux et libellés.
     *
     * Les jours sont des tranches de 24 h qui se terminent maintenant (et non des
     * journées civiles) : la courbe montre les dernières 24 h, pas depuis minuit.
     * Les mois, eux, sont les mois civils : douze seaux du 1er au 1er.
     *
     * La période `weekday` n'est pas un axe de temps : ses sept seaux sont les
     * jours de la semaine, cumulés sur les quatre dernières semaines
     * (`weekdays()`), d'où les bornes vides et les libellés numérotés de 1
     * (lundi) à 7 (dimanche), traduits par la page.
     *
     * @return array{from: int, to: int, bounds: list<array{0: int, 1: int}>, labels: list<string>}
     */
    public static function window(string $range, int $now): array
    {
        $range = self::rangeKey($range);
        $bounds = array();
        $labels = array();

        if ($range === 'weekday') {
            return array(
                'from' => $now - (self::WEEKDAY_WEEKS * 7 * 86400),
                'to' => $now,
                'bounds' => array(),
                'labels' => array('1', '2', '3', '4', '5', '6', '7'),
            );
        }

        if ($range === 'hour') {
            $to = $now;
            $from = $to - 3600;

            for ($index = 0; $index < 60; $index++) {
                $start = $from + $index * 60;
                $bounds[] = array($start, $start + 60);
                $labels[] = gmdate('H:i', $start);
            }

            return array('from' => $from, 'to' => $to, 'bounds' => $bounds, 'labels' => $labels);
        }

        if ($range === 'day') {
            $to = $now;
            $from = $to - 86400;

            for ($index = 0; $index < 24; $index++) {
                $start = $from + $index * 3600;
                $bounds[] = array($start, $start + 3600);
                $labels[] = gmdate('H:i', $start);
            }

            return array('from' => $from, 'to' => $to, 'bounds' => $bounds, 'labels' => $labels);
        }

        if ($range === 'week' || $range === 'month') {
            $days = $range === 'week' ? 7 : 30;
            $to = $now;
            $from = $to - $days * 86400;

            for ($index = 0; $index < $days; $index++) {
                $start = $from + $index * 86400;
                $bounds[] = array($start, $start + 86400);
                $labels[] = gmdate('d/m', $start);
            }

            return array('from' => $from, 'to' => $to, 'bounds' => $bounds, 'labels' => $labels);
        }

        // Année : douze mois civils, du premier jour du mois -11 à maintenant.
        $first = mktime(0, 0, 0, (int) gmdate('n', $now) - 11, 1, (int) gmdate('Y', $now));

        for ($index = 0; $index < 12; $index++) {
            $start = (int) strtotime('+' . $index . ' month', (int) $first);
            $end = (int) strtotime('+' . ($index + 1) . ' month', (int) $first);
            $bounds[] = array($start, $end);
            $labels[] = gmdate('m/Y', $start);
        }

        return array('from' => $bounds[0][0], 'to' => $now, 'bounds' => $bounds, 'labels' => $labels);
    }

    /**
     * Statistique par jour de la semaine (lundi → dimanche).
     *
     * Les quatre dernières semaines sont réunies : chaque jour de la semaine
     * cumule ce qui s'y est passé. Une connexion est comptée le jour de son
     * **début** et lui porte tout son temps — un axe « jour de la semaine » ne se
     * découpe pas en tranches horaires comme les autres périodes.
     *
     * @param list<array<string, mixed>> $rows
     * @param array{from: int, to: int} $window
     * @return list<array{sessions: int, seconds: int}> sept seaux, lundi en tête
     */
    public static function weekdays(array $rows, array $window, int $now): array
    {
        $buckets = array();

        for ($day = 0; $day < 7; $day++) {
            $buckets[] = array('sessions' => 0, 'seconds' => 0);
        }

        foreach ($rows as $row) {
            $start = max($window['from'], (int) ($row['login_time'] ?? 0));
            $end = min($window['to'], self::endMoment($row, $now));

            if ($end <= $start) {
                continue;
            }

            // `N` : 1 = lundi … 7 = dimanche.
            $day = (int) gmdate('N', $start) - 1;
            $buckets[$day]['sessions']++;
            $buckets[$day]['seconds'] += $end - $start;
        }

        return $buckets;
    }

    /**
     * Répartit les connexions sur les seaux d'une fenêtre.
     *
     * Une connexion compte, pour le nombre de connexions, dans le seau de son
     * **début** ; son temps est découpé sur tous les seaux qu'elle traverse (une
     * session de 3 h pèse donc sur trois seaux horaires, pas sur un seul).
     *
     * @param list<array<string, mixed>> $rows
     * @param array{from: int, to: int, bounds: list<array{0: int, 1: int}>, labels: list<string>} $window
     * @return list<array{sessions: int, seconds: int}>
     */
    public static function bucketise(array $rows, array $window, int $now): array
    {
        $buckets = array();

        foreach ($window['bounds'] as $bound) {
            $buckets[] = array('sessions' => 0, 'seconds' => 0);
        }

        foreach ($rows as $row) {
            $login = (int) ($row['login_time'] ?? 0);
            $start = max($window['from'], $login);
            $end = min($window['to'], self::endMoment($row, $now));

            if ($end <= $start) {
                continue;
            }

            foreach ($window['bounds'] as $index => $bound) {
                if ($start >= $bound[0] && $start < $bound[1]) {
                    $buckets[$index]['sessions']++;
                    break;
                }
            }

            $cursor = $start;

            while ($cursor < $end) {
                foreach ($window['bounds'] as $index => $bound) {
                    if ($cursor < $bound[0] || $cursor >= $bound[1]) {
                        continue;
                    }

                    $slice = min($end, $bound[1]) - $cursor;
                    $buckets[$index]['seconds'] += $slice;
                    $cursor += $slice;
                    continue 2;
                }

                // Hors bornes (fin de fenêtre) : on arrête.
                break;
            }
        }

        return $buckets;
    }

    /**
     * Polyligne SVG d'une série : la valeur la plus haute occupe le haut du
     * cadre, une série plate se cale sur la ligne de base.
     *
     * @param list<int> $values
     * @return array{line: string, area: string}
     */
    public static function chartPoints(array $values, int $width = 600, int $height = 150): array
    {
        $values = array_values($values);
        $count = count($values);

        if ($count === 0) {
            return array('line' => '', 'area' => '');
        }

        $max = max(1, max($values));
        $step = $count > 1 ? $width / ($count - 1) : 0.0;
        $points = array();

        foreach ($values as $index => $value) {
            $x = round($index * $step, 2);
            $y = round($height - min($height, max(0, (int) $value) / $max * $height), 2);
            $points[] = $x . ',' . $y;
        }

        $line = implode(' ', $points);

        return array(
            'line' => $line,
            'area' => '0,' . $height . ' ' . $line . ' ' . $width . ',' . $height,
        );
    }

    /** Une graduation tous les `$max` seaux au plus (les libellés ne se chevauchent pas). */
    public static function labelStep(int $count, int $max = 6): int
    {
        return $count <= $max ? 1 : (int) ceil($count / $max);
    }

    /**
     * Nom de compte saisi dans le formulaire ('' = tous).
     *
     * La page propose les noms des comptes (datalist) et non des identifiants :
     * avec un millier de joueurs, une liste déroulante serait inutilisable.
     * L'identifiant est résolu à part (`playerId()`), et un nom inconnu ne doit
     * pas faire échouer l'affichage.
     */
    public static function playerFilter(?string $player): string
    {
        return trim(mb_substr((string) $player, 0, 64));
    }

    /**
     * Compte portant ce nom, dans la liste proposée à la saisie.
     *
     * Le rapprochement ignore la casse : la saisie est libre, l'utilisateur ne
     * doit pas avoir à respecter la capitale du pseudo.
     *
     * @param list<array<string, mixed>> $accounts comptes proposés par la saisie
     * @return array<string, mixed>|null
     */
    public static function typedAccount(array $accounts, string $player): ?array
    {
        if ($player === '') {
            return null;
        }

        foreach ($accounts as $account) {
            if (mb_strtolower((string) ($account['username'] ?? '')) === mb_strtolower($player)) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Nom canonique du compte saisi (la saisie elle-même si aucun ne correspond).
     *
     * C'est cette valeur qui repart dans le champ de saisie : elle doit rester un
     * nom de compte, sans marque d'affichage, pour que la page se recharge sans
     * perdre le filtre.
     *
     * @param list<array<string, mixed>> $accounts comptes proposés par la saisie
     */
    public static function typedName(string $player, array $accounts): string
    {
        if ($player === '') {
            return '';
        }

        $account = self::typedAccount($accounts, $player);

        return $account === null ? $player : (string) ($account['username'] ?? '');
    }

    /**
     * Libellé d'un compte dans la liste de noms : les robots sont annoncés.
     *
     * @param array<string, mixed> $account
     */
    public static function accountLabel(array $account, string $botLabel = 'bot'): string
    {
        return (string) ($account['username'] ?? '') . self::botSuffix($account, $botLabel);
    }

    /**
     * Libellé de la saisie de compte : le nom du compte (marqué « bot » s'il
     * l'est), sinon la saisie suivie de la mention d'un nom inconnu.
     *
     * @param list<array<string, mixed>> $accounts comptes proposés par la saisie
     */
    public static function typedLabel(
        string $player,
        array $accounts,
        string $botLabel = 'bot',
        string $unknownLabel = ''
    ): string {
        if ($player === '') {
            return '';
        }

        $account = self::typedAccount($accounts, $player);

        if ($account !== null) {
            return self::accountLabel($account, $botLabel);
        }

        return $player . ($unknownLabel === '' ? '' : ' (' . $unknownLabel . ')');
    }

    /**
     * Mention ajoutée au libellé d'un compte robot (rien pour un joueur, ni sans le
     * module des robots : éteint, ses comptes redeviennent des joueurs comme les
     * autres).
     */
    private static function botSuffix(array $account, string $botLabel): string
    {
        return BotService::present() && self::isBot($account) && $botLabel !== ''
            ? ' (' . $botLabel . ')'
            : '';
    }

    // --------------------------------------------------- lecture / écriture

    /** Ouvre une connexion (appelée à la connexion du joueur). */
    public function start(int $userId, string $ip = ''): int
    {
        $this->sweep();

        return $this->sessions->open($userId, time(), $ip);
    }

    /**
     * Le joueur vient de charger une page.
     *
     * Reprend la connexion en cours (celle de la session PHP, sinon la dernière
     * ouverte) et en ouvre une nouvelle si elle est terminée depuis plus de
     * `CLOSE_AFTER`.
     *
     * @return int identifiant de la connexion à mémoriser
     */
    public function touch(int $userId, ?int $rowId = null, string $ip = ''): int
    {
        $now = time();
        $row = $rowId !== null ? $this->sessions->find($rowId) : false;

        if ($row === false || (int) $row['id_owner'] !== $userId || (int) $row['logout_time'] > 0) {
            $row = $this->sessions->findOpen($userId);
        }

        if ($row === false || $now - (int) $row['last_activity'] >= self::CLOSE_AFTER) {
            return $this->start($userId, $ip);
        }

        if ($now - (int) $row['last_activity'] >= self::TOUCH_INTERVAL) {
            $this->sessions->touch((int) $row['id'], $now);
        }

        return (int) $row['id'];
    }

    /** Déconnexion explicite. */
    public function close(?int $rowId, string $reason = 'logout'): void
    {
        if ($rowId === null || $rowId <= 0) {
            return;
        }

        $this->sessions->close($rowId, time(), $reason);
    }

    /** Ferme les connexions inactives (appelée à la connexion et à chaque page). */
    public function sweep(): int
    {
        return $this->sessions->closeIdle(self::CLOSE_AFTER);
    }

    /** @return list<array<string, mixed>> */
    public function recent(
        int $limit = self::RECENT_LIMIT,
        int $playerId = 0,
        string $type = 'all',
        int $offset = 0,
        string $sort = 'id',
        string $order = 'desc'
    ): array {
        return $this->sessions->findRecent($limit, $playerId, self::typeFilter($type), $offset, $sort, $order);
    }

    /** Nombre de connexions d'un filtre : le total de la liste paginée. */
    public function countRecent(int $playerId = 0, string $type = 'all'): int
    {
        return $this->sessions->countRecent($playerId, self::typeFilter($type));
    }

    /**
     * Comptes proposés à la saisie (joueurs, robots, ou les deux), par ordre de nom.
     *
     * @return list<array<string, mixed>>
     */
    public function accounts(string $type = 'all', int $limit = self::ACCOUNT_SUGGESTIONS): array
    {
        return $this->sessions->findAccounts(self::typeFilter($type), $limit);
    }

    /**
     * Identifiant du compte dont le nom a été saisi.
     *
     * 0 = tous les comptes ; -1 = nom inconnu. Un identifiant impossible vide
     * les tableaux au lieu de tout montrer : la page ne doit pas répondre autre
     * chose que ce qui a été demandé.
     */
    public function playerId(string $player, string $type = 'all'): int
    {
        if ($player === '') {
            return 0;
        }

        $id = $this->sessions->findIdByName($player, self::typeFilter($type));

        return $id > 0 ? $id : -1;
    }

    /**
     * Activité d'un compte robot.
     *
     * Un robot ne se connecte pas : c'est son tour de jeu qui vaut connexion, et
     * les quinze minutes d'inactivité la referment comme pour un joueur (le robot
     * est vu à chaque tour, `BOTS_TICK_SECONDS`).
     */
    public function touchBot(int $botId): int
    {
        return $this->touch($botId);
    }

    /**
     * Courbe d'une période : nombre de connexions et temps de connexion par seau.
     *
     * `label_step` est le pas d'affichage des libellés de l'axe : la page le lit
     * au lieu de le recalculer (l'axe des jours de la semaine doit tous les
     * montrer).
     *
     * @return array{range: string, labels: list<string>, sessions: list<int>, seconds: list<int>, total_sessions: int, total_seconds: int, label_step: int}
     */
    public function series(string $range, int $now = 0, int $playerId = 0, string $type = 'all'): array
    {
        $now = $now > 0 ? $now : time();
        $key = self::rangeKey($range);
        $window = self::window($key, $now);
        $rows = $this->sessions->findOverlapping($window['from'], $window['to'], $playerId, self::typeFilter($type));
        $buckets = $key === 'weekday'
            ? self::weekdays($rows, $window, $now)
            : self::bucketise($rows, $window, $now);
        $sessions = array();
        $seconds = array();

        foreach ($buckets as $bucket) {
            $sessions[] = $bucket['sessions'];
            $seconds[] = $bucket['seconds'];
        }

        $labels = count($window['labels']);

        return array(
            'range' => $key,
            'labels' => $window['labels'],
            'sessions' => $sessions,
            'seconds' => $seconds,
            'total_sessions' => array_sum($sessions),
            'total_seconds' => array_sum($seconds),
            'label_step' => self::labelStep($labels, $key === 'weekday' ? $labels : 6),
        );
    }

    /** Temps de connexion par joueur sur une période. */
    public function totalsByUser(
        string $range,
        int $limit = self::TOTALS_LIMIT,
        int $now = 0,
        int $playerId = 0,
        string $type = 'all'
    ): array {
        $now = $now > 0 ? $now : time();
        $window = self::window($range, $now);

        return $this->sessions->findTotalsByUser(
            $window['from'],
            $window['to'],
            $limit,
            $playerId,
            self::typeFilter($type)
        );
    }
}
