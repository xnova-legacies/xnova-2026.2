<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BannedRepository;
use App\Repositories\UserRepository;

/**
 * Bannissements (ex `admin/banned.php`, `admin/unbanned.php`, `admin/autounban.php`).
 *
 * Deux règles y vivent, une seule fois : poser un bannissement (la ligne du
 * pilori **et** le drapeau du compte, qui doivent rester d'accord) et lever les
 * bannissements arrivés à échéance. Le débannissement manuel, lui, est déjà
 * écrit dans `UserRepository::clearBan()` et n'est pas dupliqué ici.
 */
final class BanService
{
    /** Une journée, en secondes : le pas de la remise à niveau automatique. */
    public const DAY = 86400;

    public function __construct(
        private readonly BannedRepository $banned = new BannedRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /**
     * Durée saisie à l'écran, en secondes.
     *
     * Les quatre champs sont bornés : une saisie non numérique vaut zéro, une
     * durée négative n'a pas de sens, et `$max` évite qu'un zéro en trop ne
     * transforme une semaine en bannissement à vie.
     */
    public static function duration(int $days, int $hours, int $minutes, int $seconds, int $max = 3650): int
    {
        $total = max(0, $days) * self::DAY
            + max(0, $hours) * 3600
            + max(0, $minutes) * 60
            + max(0, $seconds);

        return min($total, $max * self::DAY);
    }

    /**
     * Pose un bannissement : le compte et sa ligne de pilori sont écrits
     * ensemble, à partir de la même échéance.
     *
     * @param array<string, mixed> $target compte visé (lu par le dépôt)
     *
     * @return int échéance appliquée (horodatage)
     */
    public function ban(array $target, string $reason, int $seconds, array $author): int
    {
        $now = time();
        $until = $now + $seconds;
        $username = (string) $target['username'];

        $this->banned->insert(
            $username,
            $reason,
            $now,
            $until,
            (string) ($author['username'] ?? ''),
            (string) ($author['email'] ?? '')
        );

        $this->users->ban($username, $until);

        return $until;
    }

    /**
     * Remise à niveau : lève les sanctions arrivées à échéance.
     *
     * La page historique décomptait en plus une seconde de `banaday` — avec une
     * espace dans le nom de la colonne, donc la requête était refusée et rien ne
     * se passait — puis levait les sanctions dont la date était passée. Le
     * décompte n'est pas repris : `banaday` est une échéance absolue, la
     * raccourcir à chaque passage transformerait la tâche en amnistie au lieu
     * d'un simple nettoyage.
     *
     * @return int nombre de bannissements levés
     */
    public function expire(): int
    {
        $lifted = 0;

        foreach ($this->users->findExpiredBans() as $account) {
            // Le débannissement est déjà écrit une fois pour toutes dans
            // `clearBan()` (drapeau du compte + ligne de pilori).
            $this->users->clearBan((string) $account['username']);
            $lifted++;
        }

        return $lifted;
    }
}
