<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FleetRepository;

/**
 * Vols en cours d'un joueur, pour le bandeau « flottes en vol » affiché sur
 * toutes les pages (ex. liste d'événements du bas de la vue générale).
 *
 * Deux familles d'entrées, celles que la vue générale affichait :
 *   - les flottes du joueur, avec l'échéance qui vient (arrivée, fin de
 *     stationnement, retour) ;
 *   - les flottes hostiles qui visent une de ses positions (leur arrivée, et la
 *     fin de leur stationnement pour la mission 5).
 *
 * Les attaques de missiles y sont comprises depuis qu'elles sont de vraies
 * missions de flotte (mission 11) : elles apparaissent comme les autres vols.
 *
 * Une entrée = un vol + SA prochaine échéance : c'est ce que le bandeau doit
 * décompter. Le calcul de cette échéance (`nextEvent`) et l'empreinte des vols
 * (`fingerprint`) sont des fonctions pures, testables sans base de données.
 */
final class FlyingFleetService
{
    /** Le vol arrive à destination. */
    public const EVENT_ARRIVAL = 'arrival';

    /** Fin du stationnement sur place (expédition, stationnement allié). */
    public const EVENT_STAY = 'stay';

    /** Le vol rentre à sa base. */
    public const EVENT_RETURN = 'return';

    /** Vol hostile en approche d'une position du joueur. */
    public const EVENT_INCOMING = 'incoming';

    /** Attaque de missiles en approche. */
    public const EVENT_MISSILE = 'missile';

    /** Flotte en orbite : aucune échéance avant son rappel. */
    public const EVENT_ORBIT = 'orbit';

    /** Missions du jeu dont le numéro change le calcul d'échéance. */
    public const MISSION_DEPLOY = 4;
    public const MISSION_HOLD = 5;
    public const MISSION_ORBIT = 10;
    public const MISSION_MISSILE = 11;

    public function __construct(
        private readonly FleetRepository $fleets = new FleetRepository(),
    ) {
    }

    /**
     * Entrées d'affichage, triées par échéance croissante.
     *
     * @return list<array<string, mixed>>
     */
    public function entries(int $userId, ?int $now = null): array
    {
        $now ??= time();
        $entries = array();

        foreach ($this->fleets->findByOwner($userId) as $row) {
            $entry = $this->buildEntry($row, $now, false);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        foreach ($this->fleets->findByTargetOwner($userId) as $row) {
            // Une expédition ou un recyclage n'a pas de destinataire : seules les
            // flottes qui visent réellement une position du joueur l'intéressent.
            if ((int) $row['fleet_owner'] === $userId) {
                continue;
            }

            $entry = $this->buildEntry($row, $now, true);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        usort($entries, static fn (array $a, array $b): int => $a['time'] <=> $b['time']);

        return $entries;
    }

    /**
     * Prochaine échéance d'un vol, ou null si elle ne concerne pas le joueur
     * (vol hostile déjà arrivé, transfert déjà terminé…).
     *
     * Reprend les conditions de BuildFleetEventTable() : un vol hostile n'est
     * annoncé que jusqu'à son arrivée (et la fin de son stationnement), un
     * transfert (mission 4) n'a pas de retour.
     *
     * @param array<string, mixed> $row
     * @return array{kind: string, time: int}|null
     */
    public static function nextEvent(array $row, int $now, bool $incoming): ?array
    {
        $start = (int) ($row['fleet_start_time'] ?? 0);
        $stay = (int) ($row['fleet_end_stay'] ?? 0);
        $end = (int) ($row['fleet_end_time'] ?? 0);
        $mission = (int) ($row['fleet_mission'] ?? 0);
        $returning = (int) ($row['fleet_mess'] ?? 0) === 1;

        // Attaque de missiles (mission 11) : la salve ne rentre pas, son échéance
        // est l'impact — pour le tireur comme pour celui qui la reçoit.
        if ($mission === self::MISSION_MISSILE) {
            return $end > $now ? array('kind' => self::EVENT_MISSILE, 'time' => $end) : null;
        }

        if ($incoming) {
            if ($start > $now) {
                return array('kind' => self::EVENT_INCOMING, 'time' => $start);
            }

            if ($mission === self::MISSION_HOLD && $stay > $now) {
                return array('kind' => self::EVENT_STAY, 'time' => $stay);
            }

            return null;
        }

        if ($returning) {
            return $end > $now ? array('kind' => self::EVENT_RETURN, 'time' => $end) : null;
        }

        if ($start > $now) {
            return array('kind' => self::EVENT_ARRIVAL, 'time' => $start);
        }

        // Transfert (mission 4) : la flotte est absorbée à l'arrivée, sans retour.
        if ($mission === self::MISSION_DEPLOY) {
            return null;
        }

        // Mise en orbite : la flotte reste sur place jusqu'à son rappel. Il n'y a
        // donc aucune échéance à décompter, mais la ligne reste affichée (elle ne
        // disparaît pas comme un transfert) : l'échéance est repoussée à l'infini
        // pour passer en dernier, sans décompte.
        if ($mission === self::MISSION_ORBIT) {
            return array('kind' => self::EVENT_ORBIT, 'time' => PHP_INT_MAX);
        }

        if ($stay > $now) {
            return array('kind' => self::EVENT_STAY, 'time' => $stay);
        }

        return array('kind' => self::EVENT_RETURN, 'time' => $end);
    }

    /**
     * Empreinte des vols : elle ne dépend pas de l'heure courante, mais change
     * dès qu'un vol apparaît, disparaît ou change d'échéance. Le client s'en sert
     * pour ne redemander le bandeau que lorsque c'est utile.
     *
     * @param list<array<string, mixed>> $entries
     */
    public static function fingerprint(array $entries): string
    {
        $parts = array();

        foreach ($entries as $entry) {
            $parts[] = implode(':', array(
                $entry['kind'],
                $entry['fleet_id'],
                $entry['mission'],
                $entry['time'],
                !empty($entry['incoming']) ? 1 : 0,
                $entry['ships'],
                // La composition compte aussi : un vol dont le total ne change
                // pas mais dont les vaisseaux changent doit rafraîchir le détail.
                is_array($entry['units'] ?? null) ? (string) json_encode($entry['units']) : '',
            ));
        }

        return md5(implode('|', $parts));
    }

    /**
     * Décompte par type de vaisseau, à partir de `fleet_array` du vol.
     *
     * Fonction pure : le champ est une suite de couples « id,nombre; ». Les
     * couples incomplets ou de quantité nulle sont ignorés, l'ordre du champ est
     * conservé (c'est celui de la vue générale).
     *
     * @return array<int, int>
     */
    public static function parseUnits(?string $fleetArray): array
    {
        $units = array();

        foreach (explode(';', (string) $fleetArray) as $couple) {
            if ($couple === '') {
                continue;
            }

            $parts = explode(',', $couple);

            if (count($parts) < 2) {
                continue;
            }

            $shipId = (int) $parts[0];
            $count = (int) $parts[1];

            if ($shipId <= 0 || $count <= 0) {
                continue;
            }

            $units[$shipId] = ($units[$shipId] ?? 0) + $count;
        }

        return $units;
    }

    /**
     * Libellé d'une flotte : « 3 Chasseur léger, 2 Croiseur ».
     *
     * @param array<int, int>          $units
     * @param array<int|string, mixed> $names
     */
    /**
     * L'inverse de `parseUnits()` : la composition d'un vol telle qu'elle se range en base
     * (`202,5;203,3;`). Le format n'existe donc qu'ici, pour la lecture comme pour l'écriture.
     *
     * @param array<int|string, int|float|string> $units vaisseau => quantité
     */
    public static function unitsArray(array $units): string
    {
        $fleetArray = '';

        foreach ($units as $ship => $count) {
            $fleetArray .= (int) $ship . ',' . (int) $count . ';';
        }

        return $fleetArray;
    }

    public static function unitsLabel(array $units, array $names): string
    {
        $parts = array();

        foreach ($units as $shipId => $count) {
            $parts[] = (int) $count . ' ' . (string) ($names[$shipId] ?? ('#' . $shipId));
        }

        return implode(', ', $parts);
    }

    /**
     * Entrée d'affichage d'un vol, ou null s'il n'a plus d'échéance à annoncer.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function buildEntry(array $row, int $now, bool $incoming): ?array
    {
        $next = self::nextEvent($row, $now, $incoming);

        if ($next === null) {
            return null;
        }

        return array(
            'kind' => $next['kind'],
            'time' => $next['time'],
            'fleet_id' => (int) $row['fleet_id'],
            'mission' => (int) $row['fleet_mission'],
            'incoming' => $incoming,
            'returning' => (int) $row['fleet_mess'] === 1,
            // Propriétaire du vol : le bandeau n'affiche le bouton « Retour » que
            // sur les flottes du joueur (les vols hostiles ne se rappellent pas).
            'owner' => (int) $row['fleet_owner'],
            'ships' => (int) $row['fleet_amount'],
            'units' => self::parseUnits(isset($row['fleet_array']) ? (string) $row['fleet_array'] : null),
            'from' => self::position($row, 'fleet_start_galaxy', 'fleet_start_system', 'fleet_start_planet', 'fleet_start_type'),
            'to' => self::position($row, 'fleet_end_galaxy', 'fleet_end_system', 'fleet_end_planet', 'fleet_end_type'),
        );
    }

    /**
     * Coordonnées d'une extrémité de vol.
     *
     * @param array<string, mixed> $row
     * @return array{galaxy: int, system: int, planet: int, type: int}
     */
    private static function position(array $row, string $galaxyKey, string $systemKey, string $planetKey, string $typeKey): array
    {
        $type = $row[$typeKey] ?? 1;

        return array(
            'galaxy' => (int) ($row[$galaxyKey] ?? 0),
            'system' => (int) ($row[$systemKey] ?? 0),
            'planet' => (int) ($row[$planetKey] ?? 0),
            'type' => is_numeric($type) ? (int) $type : 1,
        );
    }
}
