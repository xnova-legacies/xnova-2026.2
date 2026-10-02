<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SessionService;
use PHPUnit\Framework\TestCase;

/**
 * Historique des sessions : seuils d'inactivité, durées et découpage des courbes.
 * Tout est pur ici — aucune base de données n'est nécessaire.
 */
final class SessionServiceTest extends TestCase
{
    /** Repère fixe : mercredi 15 juin 2026, 12 h 00 UTC. */
    private function now(): int
    {
        return (int) mktime(12, 0, 0, 6, 15, 2026);
    }

    public function testStateFollowsTheTwoThresholds(): void
    {
        $now = $this->now();

        self::assertSame('active', SessionService::state(
            array('last_activity' => $now - 10, 'logout_time' => 0),
            $now
        ));

        // 5 minutes sans rafraîchissement : inactive.
        self::assertSame('idle', SessionService::state(
            array('last_activity' => $now - SessionService::IDLE_AFTER, 'logout_time' => 0),
            $now
        ));

        // 15 minutes : terminée, même si la ligne n'a pas encore été balayée.
        self::assertSame('closed', SessionService::state(
            array('last_activity' => $now - SessionService::CLOSE_AFTER, 'logout_time' => 0),
            $now
        ));

        self::assertSame('closed', SessionService::state(
            array('last_activity' => $now - 10, 'logout_time' => $now - 5),
            $now
        ));
    }

    public function testDurationStopsAtTheIdleThreshold(): void
    {
        $now = $this->now();

        // Connexion ouverte depuis 1 h mais vue il y a 20 min : la durée s'arrête
        // au seuil (dernière activité + 15 min), pas à maintenant.
        $row = array(
            'login_time' => $now - 3600,
            'last_activity' => $now - 1200,
            'logout_time' => 0,
        );

        self::assertSame(3600 - 1200 + SessionService::CLOSE_AFTER, SessionService::duration($row, $now));

        // Connexion fermée : la durée est celle de la ligne.
        self::assertSame(600, SessionService::duration(array(
            'login_time' => $now - 900,
            'last_activity' => $now - 500,
            'logout_time' => $now - 300,
        ), $now));
    }

    public function testDurationIsNeverNegative(): void
    {
        $now = $this->now();

        self::assertSame(0, SessionService::duration(array(
            'login_time' => $now,
            'last_activity' => $now,
            'logout_time' => $now - 500,
        ), $now));
    }

    public function testWindowBucketsPerRange(): void
    {
        $now = $this->now();

        self::assertCount(60, SessionService::window('hour', $now)['bounds']);
        self::assertCount(24, SessionService::window('day', $now)['bounds']);
        self::assertCount(7, SessionService::window('week', $now)['bounds']);
        self::assertCount(30, SessionService::window('month', $now)['bounds']);
        self::assertCount(12, SessionService::window('year', $now)['bounds']);

        $hour = SessionService::window('hour', $now);
        self::assertSame($now - 3600, $hour['from']);
        self::assertSame($now, $hour['to']);
        self::assertCount(60, $hour['labels']);

        // Une période inconnue retombe sur l'heure.
        self::assertSame('hour', SessionService::rangeKey('inconnue'));
        self::assertSame('month', SessionService::rangeKey('MONTH'));
    }

    public function testBucketiseSpreadsTimeAndCountsStarts(): void
    {
        $now = $this->now();
        $window = SessionService::window('hour', $now);

        // Une connexion de 30 min, vue il y a 10 min : elle couvre la moitié de la
        // fenêtre, répartie sur les seaux qu'elle traverse.
        $buckets = SessionService::bucketise(array(
            array('login_time' => $now - 1800, 'last_activity' => $now - 600, 'logout_time' => 0),
        ), $window, $now);

        self::assertSame(1, array_sum(array_column($buckets, 'sessions')));
        self::assertSame(1800, array_sum(array_column($buckets, 'seconds')));

        // Le compte tombe dans le seau du début de la connexion.
        $startIndex = (int) floor(($now - 1800 - $window['from']) / 60);
        self::assertSame(1, $buckets[$startIndex]['sessions']);
        self::assertSame(60, $buckets[$startIndex]['seconds']);
    }

    public function testBucketiseIgnoresSessionsOutsideTheWindow(): void
    {
        $now = $this->now();
        $window = SessionService::window('hour', $now);

        $buckets = SessionService::bucketise(array(
            array('login_time' => $now - 7200, 'last_activity' => $now - 7000, 'logout_time' => $now - 6900),
        ), $window, $now);

        self::assertSame(0, array_sum(array_column($buckets, 'sessions')));
        self::assertSame(0, array_sum(array_column($buckets, 'seconds')));
    }

    public function testChartPointsScaleOnTheHighestValue(): void
    {
        $points = SessionService::chartPoints(array(0, 5, 10), 100, 100);

        self::assertSame('0,100 50,50 100,0', $points['line']);
        self::assertStringStartsWith('0,100 0,100', $points['area']);
        self::assertStringEndsWith('100,100', $points['area']);

        // Série plate : elle se cale sur la ligne de base (pas de division par 0).
        self::assertSame('0,100 100,100', SessionService::chartPoints(array(0, 0), 100, 100)['line']);
        self::assertSame('', SessionService::chartPoints(array())['line']);
    }

    public function testLabelStepThinsLongSeries(): void
    {
        self::assertSame(1, SessionService::labelStep(5));
        self::assertSame(10, SessionService::labelStep(60));
        self::assertSame(5, SessionService::labelStep(30));
        // L'axe des jours de la semaine montre ses sept libellés.
        self::assertSame(1, SessionService::labelStep(7, 7));
    }

    public function testTheWeekdayWindowSpansFourWeeksAndHasSevenBuckets(): void
    {
        $now = 1_800_000_000;
        $window = SessionService::window('weekday', $now);

        self::assertSame($now - (SessionService::WEEKDAY_WEEKS * 7 * 86400), $window['from']);
        self::assertSame($now, $window['to']);
        self::assertSame(array('1', '2', '3', '4', '5', '6', '7'), $window['labels']);
        self::assertSame(array(), $window['bounds'], 'L\'axe est le jour de la semaine, pas le temps.');
    }

    public function testWeekdaysGroupSessionsByDayOfWeek(): void
    {
        // 1er septembre 2025 = lundi, 3 septembre 2025 = mercredi.
        $monday = (int) strtotime('2025-09-01 08:00:00 UTC');
        $wednesday = (int) strtotime('2025-09-03 10:00:00 UTC');
        $now = $wednesday + 4000;
        $window = array('from' => $monday, 'to' => $now);

        $rows = array(
            array('login_time' => $monday, 'logout_time' => $monday + 600, 'last_activity' => $monday + 600),
            array('login_time' => $wednesday, 'logout_time' => $wednesday + 3000, 'last_activity' => $wednesday + 3000),
        );

        $buckets = SessionService::weekdays($rows, $window, $now);

        self::assertCount(7, $buckets);
        self::assertSame(1, $buckets[0]['sessions'], 'Lundi reçoit la connexion du lundi.');
        self::assertSame(600, $buckets[0]['seconds']);
        self::assertSame(1, $buckets[2]['sessions'], 'Mercredi reçoit la connexion du mercredi.');
        self::assertSame(3000, $buckets[2]['seconds']);
        self::assertSame(0, $buckets[6]['sessions'], 'Dimanche reste vide.');
    }

    public function testFiltersFromTheFormAreNormalized(): void
    {
        // Compte saisi : un nom de joueur, borné en longueur (une saisie libre
        // remplace la liste déroulante, inutilisable avec mille joueurs).
        self::assertSame('', SessionService::playerFilter(''));
        self::assertSame('', SessionService::playerFilter('   '));
        self::assertSame('Cygnus 3', SessionService::playerFilter('  Cygnus 3 '));
        self::assertSame(64, mb_strlen(SessionService::playerFilter(str_repeat('a', 80))));

        // Type : joueurs, robots, ou les deux.
        self::assertSame('all', SessionService::typeFilter('inconnu'));
        self::assertSame('bots', SessionService::typeFilter('BOTS'));
        self::assertSame('players', SessionService::typeFilter('players'));

        // Un compte robot est reconnu par `users.bot`.
        self::assertTrue(SessionService::isBot(array('bot' => 1)));
        self::assertFalse(SessionService::isBot(array('bot' => 0)));
        self::assertFalse(SessionService::isBot(array()));
    }
}
