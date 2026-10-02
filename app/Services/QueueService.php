<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Api\ApiException;
use App\Core\GameTables;
use App\Core\Language;

/**
 * Lecture et modification des files d'attente du jeu.
 *
 * Trois domaines aux mécaniques différentes :
 *   - buildings : `b_building_id` = "element,level,duration,endTime,mode;..."
 *                 (le premier élément est le chantier en cours, il ne se déplace pas) ;
 *   - hangar    : `b_hangar_id` = "element,count;..." avec `b_hangar` = secondes
 *                 de travail déjà accumulées (le premier élément est en fabrication) ;
 *   - research  : `b_tech` / `b_tech_id` sur une seule planète (pas de file).
 *
 * Les positions manipulées par l'API sont celles de l'affichage (1 = premier
 * élément). Comme dans le jeu, l'élément en cours (position 1) est figé : on ne
 * réordonne que les suivants, ce qui évite tout remboursement/débit de
 * ressources.
 */
final class QueueService
{
    public const DOMAIN_BUILDINGS = 'buildings';
    public const DOMAIN_HANGAR = 'hangar';
    public const DOMAIN_RESEARCH = 'research';

    /** Position du premier élément réellement déplaçable. */
    public const FIRST_MOVABLE_POSITION = 2;

    public static function domains(): array
    {
        return array(self::DOMAIN_BUILDINGS, self::DOMAIN_HANGAR, self::DOMAIN_RESEARCH);
    }

    // ------------------------------------------------------------------ lectures

    /**
     * File de construction brute.
     *
     * @return list<array{element: int, level: int, duration: int, end_time: int, mode: string}>
     */
    public function buildingEntries(array $planet): array
    {
        $entries = array();

        foreach ($this->split($planet['b_building_id'] ?? '') as $raw) {
            $parts = explode(',', $raw);
            $entries[] = array(
                'element' => (int) ($parts[0] ?? 0),
                'level' => (int) ($parts[1] ?? 0),
                'duration' => (int) ($parts[2] ?? 0),
                'end_time' => (int) ($parts[3] ?? 0),
                'mode' => (string) ($parts[4] ?? 'build'),
            );
        }

        return $entries;
    }

    /**
     * File de construction enrichie pour l'affichage.
     *
     * @return list<array<string, mixed>>
     */
    public function buildingItems(array $planet): array
    {
        return $this->decorate($this->buildingEntries($planet), 'level');
    }

    /**
     * File du hangar brute.
     *
     * @return list<array{element: int, count: int}>
     */
    /**
     * Répare les files du hangar dont une ligne dépasse le plafond autorisé.
     *
     * Une file ouverte avant la correction du plafond (`MAX_UNITS_PER_ROW`) reste
     * bloquée pour toujours : la page de réparation du panneau la vide. Règle
     * extraite de `admin/ElementQueueFixer.php`, avec les mêmes bornes.
     *
     * @return int nombre de files réinitialisées
     */
    public function repairHangarQueues(): int
    {
        $limit = \App\Core\GameConstants::maxUnitsPerRow();
        $repaired = 0;

        $planets = \App\Database\Connection::preparedFetchAll(
            "SELECT `id`, `b_hangar_id` FROM {{table}} WHERE `b_hangar_id` != '0' AND `b_hangar_id` != ''",
            array(),
            'planets'
        );

        foreach ($planets as $planet) {
            foreach ($this->hangarEntries($planet) as $entry) {
                if ($entry['count'] > $limit) {
                    \App\Database\Connection::preparedExecute(
                        "UPDATE {{table}} SET `b_hangar` = '0', `b_hangar_id` = '0' WHERE `id` = ?",
                        array((string) $planet['id']),
                        'planets'
                    );
                    $repaired++;
                    break;
                }
            }
        }

        return $repaired;
    }

    public function hangarEntries(array $planet): array
    {
        $entries = array();

        foreach ($this->split($planet['b_hangar_id'] ?? '') as $raw) {
            $parts = explode(',', $raw);
            $entries[] = array(
                'element' => (int) ($parts[0] ?? 0),
                'count' => (int) ($parts[1] ?? 0),
            );
        }

        return $entries;
    }

    /**
     * File du hangar avec les heures de fin théoriques.
     *
     * `b_hangar` contient le travail déjà accumulé : le premier élément a donc
     * déjà consommé une partie de sa durée.
     *
     * @return list<array<string, mixed>>
     */
    public function hangarItems(array $planet, array $user): array
    {
        $entries = $this->hangarEntries($planet);
        $cursor = time() - (int) ($planet['b_hangar'] ?? 0);
        $items = array();

        foreach ($entries as $entry) {
            $unitTime = (int) GetBuildingTime($user, $planet, $entry['element']);
            $cursor += $unitTime * $entry['count'];
            $items[] = array(
                'element' => $entry['element'],
                'count' => $entry['count'],
                'duration' => $unitTime,
                'end_time' => $cursor,
                'mode' => 'build',
            );
        }

        return $this->decorate($items, 'count');
    }

    /**
     * Nature d'une unité fabriquée au hangar : `fleet` ou `defense`.
     *
     * La répartition vient de `GameTables::RES_LIST`, jamais d'une plage
     * d'identifiants recopiée : ajouter une unité ne demande qu'une ligne dans
     * la table. Fonction pure (aucune lecture en base).
     */
    public static function hangarKind(int $element): string
    {
        return in_array($element, GameTables::RES_LIST['defense'], true) ? 'defense' : 'fleet';
    }

    /**
     * Répartit une file de hangar entre vaisseaux et défenses.
     *
     * Les deux domaines partagent la MÊME file (`b_hangar_id`) : les entrées
     * gardent donc leur position réelle, et la première de la file reste la
     * seule marquée « en fabrication », même si elle n'est pas la première de sa
     * liste. Fonction pure.
     *
     * @param list<array<string, mixed>> $items entrées déjà enrichies (hangarItems)
     * @return array{fleet: list<array<string, mixed>>, defense: list<array<string, mixed>>}
     */
    public static function splitHangar(array $items): array
    {
        $groupes = array('fleet' => array(), 'defense' => array());

        foreach ($items as $item) {
            $groupes[self::hangarKind((int) ($item['element'] ?? 0))][] = $item;
        }

        return $groupes;
    }

    // ---------------------------------------------------------------- recherche

    /**
     * File de recherche : mêmes entrées que la file de construction.
     *
     * La file vit dans `users.b_tech_queue`. Tant qu'elle est vide, on retombe
     * sur l'emplacement historique (`b_tech` / `b_tech_id` de la planète du
     * laboratoire) : une recherche lancée avant la migration reste affichée.
     *
     * @return list<array{element: int, level: int, duration: int, end_time: int, mode: string}>
     */
    public function researchEntries(array $user, ?array $researchPlanet = null): array
    {
        $entries = array();

        foreach ($this->split($user['b_tech_queue'] ?? '') as $raw) {
            $parts = explode(',', $raw);
            $entries[] = array(
                'element' => (int) ($parts[0] ?? 0),
                'level' => (int) ($parts[1] ?? 0),
                'duration' => (int) ($parts[2] ?? 0),
                'end_time' => (int) ($parts[3] ?? 0),
                'mode' => (string) ($parts[4] ?? 'research'),
            );
        }

        if ($entries !== array()) {
            return $entries;
        }

        $element = (int) ($researchPlanet['b_tech_id'] ?? 0);

        if ($element === 0) {
            return array();
        }

        // Recherche heritee (lancee avant la file) : le niveau vise n'est pas
        // stocke, il n'est donc pas affiche.
        return array(array(
            'element' => $element,
            'level' => 0,
            'duration' => 0,
            'end_time' => (int) ($researchPlanet['b_tech'] ?? 0),
            'mode' => 'research',
        ));
    }

    /**
     * File de recherche enrichie pour l'affichage.
     *
     * @return list<array<string, mixed>>
     */
    public function researchItems(array $user, ?array $researchPlanet = null): array
    {
        return $this->decorate($this->researchEntries($user, $researchPlanet), 'level');
    }

    /**
     * Niveau que visera la prochaine recherche de cet élément : niveau courant du
     * joueur, augmenté d'une unité par recherche du même élément déjà en file.
     *
     * Implémentation unique : l'enregistrement (ResearchService) et l'affichage
     * (page du laboratoire) doivent annoncer le même niveau.
     */
    public function researchNextLevel(array $user, ?array $researchPlanet, int $element): int
    {
        $resource = \App\Core\GameData::resource();
        $level = (int) ($user[$resource[$element]] ?? 0);

        foreach ($this->researchEntries($user, $researchPlanet) as $entry) {
            if ((int) $entry['element'] === $element) {
                $level++;
            }
        }

        return $level + 1;
    }

    /**
     * Relit les files d'une planète juste avant de les modifier.
     *
     * La page affichée, la synchronisation temps réel et l'action du joueur
     * peuvent être traitées en parallèle : sans cette relecture, une écriture
     * faite entre le chargement de la page et l'action était écrasée par la
     * copie en mémoire (l'élément ajouté disparaissait de la file, un
     * réordonnancement était annulé).
     */
    public function refreshPlanetQueues(array &$planet): void
    {
        $row = \App\Database\Connection::preparedFetchOne(
            "SELECT b_building_id, b_building, b_hangar_id, b_hangar FROM {{table}} WHERE id = ?",
            array((int) ($planet['id'] ?? 0)),
            'planets'
        );

        if (!is_array($row)) {
            return;
        }

        foreach (array('b_building_id', 'b_building', 'b_hangar_id', 'b_hangar') as $field) {
            if (array_key_exists($field, $row)) {
                $planet[$field] = $row[$field];
            }
        }
    }

    /** Relit la file de recherche juste avant de la modifier (voir ci-dessus). */
    public function refreshResearchQueue(array &$user): void
    {
        $row = \App\Database\Connection::preparedFetchOne(
            "SELECT b_tech_queue, b_tech_planet FROM {{table}} WHERE id = ?",
            array((int) ($user['id'] ?? 0)),
            'users'
        );

        if (!is_array($row)) {
            return;
        }

        foreach (array('b_tech_queue', 'b_tech_planet') as $field) {
            if (array_key_exists($field, $row)) {
                $user[$field] = $row[$field];
            }
        }
    }

    /** Identifiant de la planète sur laquelle tourne la recherche (0 = aucune). */
    public function researchPlanetId(array $user): int
    {
        return (int) ($user['b_tech_planet'] ?? 0);
    }

    /**
     * Ligne d'une planète par identifiant : sert à retrouver la colonie sur
     * laquelle une recherche tourne quand la file est gérée depuis une autre.
     *
     * @return array<string, mixed>|null
     */
    public function findPlanet(int $planetId): ?array
    {
        if ($planetId <= 0) {
            return null;
        }

        $row = \App\Database\Connection::preparedFetchOne(
            "SELECT * FROM {{table}} WHERE id = ?",
            array($planetId),
            'planets'
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Ajoute une recherche en fin de file. $entry porte element, level et
     * duration ; l'heure de fin est enchainée à la dernière entrée.
     *
     * @param array{element: int, level: int, duration: int} $entry
     */
    public function appendResearch(array &$user, array &$planet, array $entry): void
    {
        $this->refreshResearchQueue($user);

        $entries = $this->researchEntries($user, $planet);
        $base = $entries === array() ? time() : max((int) $entries[count($entries) - 1]['end_time'], time());

        $entries[] = array(
            'element' => (int) $entry['element'],
            'level' => (int) $entry['level'],
            'duration' => (int) $entry['duration'],
            'end_time' => $base + (int) $entry['duration'],
            'mode' => 'research',
        );

        $this->storeResearchQueue($user, $planet, $entries);
    }

    /**
     * Retire une recherche en attente (position >= 2, la première est en cours).
     *
     * Comme pour la file de construction, le retrait ne rembourse pas : les
     * ressources ont été engagées au lancement (seule l'interruption de la
     * recherche en cours est remboursée, cf. ResearchService::cancel()).
     */
    public function removeResearch(array &$user, array &$planet, int $position): void
    {
        $this->refreshResearchQueue($user);

        $entries = $this->researchEntries($user, $planet);
        $count = count($entries);

        if ($position < self::FIRST_MOVABLE_POSITION || $position > $count) {
            throw ApiException::validation(
                'invalid_position',
                'Cette recherche ne peut pas être retirée de la file.'
            );
        }

        $removed = $entries[$position - 1];
        array_splice($entries, $position - 1, 1);

        // Meme regle que pour les batiments (RemoveBuildingFromQueue) : les
        // recherches du meme element qui suivent redescendent d'un niveau, et
        // leur duree est recalculee pour ce niveau.
        foreach ($entries as $index => $entry) {
            if ($index < $position - 1 || (int) $entry['element'] !== (int) $removed['element']) {
                continue;
            }

            $entries[$index]['level'] = max(1, (int) $entry['level'] - 1);
            $entries[$index]['duration'] = (int) GetBuildingTimeLevel(
                $user,
                $planet,
                (int) $entry['element'],
                (int) $entries[$index]['level']
            );
        }

        // Les suivantes repartent de la fin de celle qui precede le retrait :
        // sans ce recalcul, la duree de la recherche retiree restait reservee.
        if ($entries !== array()) {
            $entries = self::rechainAfter($entries, $position - 1);
        }

        $this->storeResearchQueue($user, $planet, $entries);
    }

    /** Déplace une recherche en attente (positions 1-based). */
    public function reorderResearch(array &$user, array &$planet, int $from, int $to): void
    {
        $this->refreshResearchQueue($user);

        $entries = $this->researchEntries($user, $planet);
        $this->assertMovable($entries, $from, $to);

        $entries = self::move($entries, $from, $to);
        $base = max((int) $entries[0]['end_time'], time());
        $entries = self::recomputeEndTimes($entries, $base);

        $this->storeResearchQueue($user, $planet, $entries);
    }

    /**
     * Écrit la file de recherche et son miroir historique.
     *
     * `b_tech` / `b_tech_id` de la planète qui fait tourner la recherche restent
     * la copie de la première entrée : le code non migré (HandleTechnologieBuild,
     * affichage legacy) continue de fonctionner.
     *
     * Deux règles importantes :
     *   - la file appartient au joueur (`users.b_tech_queue`) : elle est toujours
     *     enregistrée, même quand elle est modifiée depuis une autre colonie que
     *     celle où la recherche tourne (sinon l'ajout était perdu alors que ses
     *     ressources avaient été débitées) ;
     *   - le miroir est écrit sur la planète qui fait tourner la recherche, pas
     *     sur la planète active : c'est elle que lit HandleTechnologieBuild().
     *
     * @param list<array<string, mixed>> $entries
     */
    private function storeResearchQueue(array &$user, array &$planet, array $entries): void
    {
        $entries = array_values($entries);
        $user['b_tech_queue'] = implode(';', array_map(
            static fn (array $entry): string => implode(',', array(
                $entry['element'],
                $entry['level'],
                $entry['duration'],
                $entry['end_time'],
                $entry['mode'],
            )),
            $entries
        ));

        $thisPlanet = (int) ($planet['id'] ?? 0);
        $runningPlanet = (int) ($user['b_tech_planet'] ?? 0);
        $mirrorElement = 0;
        $mirrorEndTime = 0;

        if ($entries === array()) {
            // File vide : plus rien ne tourne, la planète du laboratoire est libérée.
            $mirrorPlanet = $runningPlanet !== 0 ? $runningPlanet : $thisPlanet;
            $user['b_tech_planet'] = 0;
        } elseif ($runningPlanet === 0) {
            // Démarrage : la recherche tourne sur la planète passée en paramètre.
            $mirrorPlanet = $thisPlanet;
            $user['b_tech_planet'] = $thisPlanet;
            $mirrorElement = (int) $entries[0]['element'];
            $mirrorEndTime = (int) $entries[0]['end_time'];
        } else {
            // Enchaînement, ou file modifiée depuis une autre colonie : la
            // recherche reste là où elle tourne déjà (son miroir seul est réécrit).
            $mirrorPlanet = $runningPlanet;
            $mirrorElement = (int) $entries[0]['element'];
            $mirrorEndTime = (int) $entries[0]['end_time'];
        }

        \App\Database\Connection::preparedExecute(
            "UPDATE {{table}} SET b_tech_id = ?, b_tech = ? WHERE id = ?",
            array($mirrorElement, $mirrorEndTime, $mirrorPlanet),
            'planets'
        );

        \App\Database\Connection::preparedExecute(
            "UPDATE {{table}} SET b_tech_queue = ?, b_tech_planet = ? WHERE id = ?",
            array($user['b_tech_queue'], (int) $user['b_tech_planet'], (int) $user['id']),
            'users'
        );

        // La copie en mémoire de la planète active reste cohérente pour la suite
        // du rendu quand c'est elle qui porte le miroir.
        if ($thisPlanet === $mirrorPlanet) {
            $planet['b_tech_id'] = $mirrorElement;
            $planet['b_tech'] = $mirrorEndTime;
        }
    }

    /**
     * Retire le pointeur de recherche d'un compte quand aucune recherche ne tourne.
     *
     * Le miroir historique (`users.b_tech_planet`) ne s'écrit qu'ici : un pointeur orphelin —
     * le panneau d'administration ou une page legacy l'ont posé sans la file, ou une recherche
     * a été retirée — fait croire au jeu qu'une recherche tourne sur une colonie, et
     * `HandleTechnologieBuild()` cherche alors une planète qui n'a rien à annoncer.
     */
    public function clearResearchPlanet(array &$user): void
    {
        if ((int) ($user['b_tech_planet'] ?? 0) === 0) {
            return;
        }

        $user['b_tech_planet'] = 0;

        \App\Database\Connection::preparedExecute(
            'UPDATE {{table}} SET b_tech_planet = 0 WHERE id = ?',
            array((int) $user['id']),
            'users'
        );
    }

    /**
     * Termine la recherche en cours : la retire de la file.
     *
     * En fin normale, les heures de fin des suivantes ont été enchainées au
     * lancement : la nouvelle première porte donc déjà la bonne échéance. Quand
     * la recherche est interrompue avant son terme ($restart), la suite repart de
     * maintenant : sans ce recalcul, le joueur attendrait la fin de la recherche
     * qu'il vient d'annuler.
     *
     * @return bool vrai s'il reste une recherche à faire
     */
    public function advanceResearch(array &$user, array &$planet, bool $restart = false): bool
    {
        $this->refreshResearchQueue($user);

        $entries = $this->researchEntries($user, $planet);
        array_shift($entries);

        if ($restart && $entries !== array()) {
            $entries = self::chainFrom($entries, time());
        }

        $this->storeResearchQueue($user, $planet, $entries);

        return $entries !== array();
    }

    // ------------------------------------------------------------ réordonnancement

    /**
     * Déplace un élément de la file de construction.
     *
     * @throws ApiException si la position est invalide ou concerne le chantier en cours
     */
    public function reorderBuildings(array $user, array &$planet, int $from, int $to): void
    {
        $this->refreshPlanetQueues($planet);

        $entries = $this->buildingEntries($planet);
        $this->assertMovable($entries, $from, $to);

        $entries = self::move($entries, $from, $to);
        // Les heures de fin repartent de la fin du chantier en cours.
        $base = max((int) $entries[0]['end_time'], time());
        $entries = self::recomputeEndTimes($entries, $base);

        $planet['b_building_id'] = implode(';', array_map(
            static fn (array $entry): string => implode(',', array(
                $entry['element'],
                $entry['level'],
                $entry['duration'],
                $entry['end_time'],
                $entry['mode'],
            )),
            $entries
        ));

        $this->persistQueue($planet, 'b_building_id');
    }

    /**
     * Réécrit la file du hangar au format du jeu (`element,count;`).
     *
     * Une file vide s'écrit en chaîne vide : c'est ce que le rendu et le calcul
     * de production attendent pour « rien à fabriquer ».
     *
     * @param list<array{element: int, count: int}> $entries
     */
    public static function serializeHangarEntries(array $entries): string
    {
        if ($entries === array()) {
            return '';
        }

        return implode(';', array_map(
            static fn (array $entry): string => $entry['element'] . ',' . $entry['count'],
            $entries
        )) . ';';
    }

    /**
     * Déplace un élément de la file du hangar.
     *
     * @throws ApiException si la position est invalide ou concerne l'élément en fabrication
     */
    public function reorderHangar(array &$planet, int $from, int $to): void
    {
        $this->refreshPlanetQueues($planet);

        $entries = $this->hangarEntries($planet);
        $this->assertMovable($entries, $from, $to);

        $entries = self::move($entries, $from, $to);

        $planet['b_hangar_id'] = self::serializeHangarEntries($entries);

        $this->persistQueue($planet, 'b_hangar_id');
    }

    /**
     * Déplace un élément d'une liste (positions 1-based). Fonction pure.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function move(array $items, int $from, int $to): array
    {
        if ($from === $to) {
            return array_values($items);
        }

        $moved = array_splice($items, $from - 1, 1);

        if ($moved === array()) {
            return array_values($items);
        }

        array_splice($items, $to - 1, 0, $moved);

        return array_values($items);
    }

    /**
     * Réenchaine les heures de fin : le premier élément conserve la sienne,
     * chaque suivant démarre à la fin du précédent. Fonction pure, utilisée pour
     * recalculer la file après un déplacement.
     *
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public static function recomputeEndTimes(array $entries, int $baseTime): array
    {
        $cursor = $baseTime;

        foreach ($entries as $index => $entry) {
            if ($index === 0) {
                // Le chantier en cours garde sa date de fin (ou la référence donnée).
                $entries[$index]['end_time'] = $cursor;
                continue;
            }

            $cursor += (int) $entry['duration'];
            $entries[$index]['end_time'] = $cursor;
        }

        return array_values($entries);
    }

    /**
     * Réenchaine les heures de fin à partir d'un démarrage : chaque élément, le
     * premier compris, se termine après sa propre durée.
     *
     * À utiliser quand le premier élément de la file vient d'être retiré (ou
     * interrompu) : le suivant démarre maintenant, il n'hérite pas de la fin de
     * l'élément annulé.
     *
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public static function chainFrom(array $entries, int $startTime): array
    {
        $entries = array_values($entries);
        $cursor = $startTime;

        foreach ($entries as $index => $entry) {
            $cursor += (int) $entry['duration'];
            $entries[$index]['end_time'] = $cursor;
        }

        return $entries;
    }

    /**
     * Réenchaine les heures de fin des éléments à partir d'une position : les
     * précédents (dont celui en cours, position 1) gardent leur échéance, la suite
     * repart de la fin du précédent.
     *
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public static function rechainAfter(array $entries, int $fromIndex): array
    {
        $entries = array_values($entries);
        $cursor = 0;

        foreach ($entries as $index => $entry) {
            if ($index < $fromIndex) {
                // Élément antérieur : il conserve sa date de fin, qui sert de base.
                $cursor = (int) $entry['end_time'];

                continue;
            }

            $cursor += (int) $entry['duration'];
            $entries[$index]['end_time'] = $cursor;
        }

        return $entries;
    }

    // ------------------------------------------------------------------- interne

    /**
     * @param list<array{element: int, count?: int, level?: int, duration?: int, end_time?: int, mode?: string}> $entries
     * @return list<array<string, mixed>>
     */
    private function decorate(array $entries, string $quantityKey): array
    {
        $lang = Language::all();
        $tech = is_array($lang['tech'] ?? null) ? $lang['tech'] : array();
        $dpath = $GLOBALS['dpath'] ?? (defined('DEFAULT_SKINPATH') ? DEFAULT_SKINPATH : '/public/xnova/');
        $total = count($entries);
        $items = array();

        foreach ($entries as $index => $entry) {
            $element = (int) $entry['element'];
            $position = $index + 1;
            $item = array(
                'position' => $position,
                'element' => $element,
                'name' => (string) ($tech[$element] ?? ('#' . $element)),
                'icon' => $dpath . 'buildings/' . $element . '.gif',
                'duration' => (int) ($entry['duration'] ?? 0),
                'end_time' => (int) ($entry['end_time'] ?? 0),
                'mode' => (string) ($entry['mode'] ?? 'build'),
                'running' => $position === 1,
                'movable' => $total > 1 && $position >= self::FIRST_MOVABLE_POSITION,
            );

            $item[$quantityKey] = (int) ($entry[$quantityKey] ?? 0);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Découpe une file "a;b;c" en ignorant les entrées vides et la valeur "0".
     *
     * @return list<string>
     */
    private function split(mixed $queue): array
    {
        $queue = (string) $queue;

        if ($queue === '' || $queue === '0') {
            return array();
        }

        return array_values(array_filter(
            explode(';', $queue),
            static fn (string $entry): bool => $entry !== '' && $entry !== '0'
        ));
    }

    /**
     * Vérifie qu'un déplacement est possible : au moins deux éléments, et
     * aucune position sur l'élément en cours (position 1).
     *
     * @param list<array<string, mixed>> $entries
     * @throws ApiException
     */
    public function assertMovable(array $entries, int $from, int $to): void
    {
        $count = count($entries);

        if ($count < 2) {
            throw ApiException::validation(
                'nothing_to_reorder',
                'Il n\'y a rien à déplacer dans cette file.'
            );
        }

        if ($from < self::FIRST_MOVABLE_POSITION || $to < self::FIRST_MOVABLE_POSITION) {
            throw ApiException::validation(
                'running_element_fixed',
                'L\'élément en cours ne peut pas être déplacé : interromps-le d\'abord.'
            );
        }

        if ($from > $count || $to > $count) {
            throw ApiException::validation('invalid_position', 'Position invalide dans la file.');
        }
    }

    private function persistQueue(array &$planet, string $field): void
    {
        \App\Database\Connection::preparedExecute(
            "UPDATE {{table}} SET `" . $field . "` = ? WHERE id = ?",
            array($planet[$field], $planet['id']),
            'planets'
        );
    }
}
