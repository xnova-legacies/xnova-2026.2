<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ActionRepository;
use App\Repositories\BuddyRepository;
use App\Repositories\BuildingQueueRepository;
use App\Repositories\FleetRepository;
use App\Repositories\GalaxyRepository;
use App\Repositories\MessageRepository;
use App\Repositories\MissileRepository;
use App\Repositories\MissionRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\SearchRepository;
use App\Repositories\StatsRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fou anti-redondance : chaque règle d'accès aux données n'a qu'une seule
 * implémentation. Échoue si une copie revient (audit des doublons du chantier).
 *
 * Aucun accès base : on ne teste que la surface des classes.
 */
final class RepositoryConsolidationTest extends TestCase
{
    /** Un seul INSERT de message : MessageRepository. */
    public function testInsertMessageHasASingleHome(): void
    {
        self::assertTrue(method_exists(MessageRepository::class, 'insertMessage'));
        self::assertFalse(method_exists(MissionRepository::class, 'insertMessage'));
        self::assertFalse(method_exists(MissileRepository::class, 'insertMessage'));
    }

    /** Compteurs de messages : les deux règles vivent dans UserRepository. */
    public function testUnreadCountersHaveASingleHome(): void
    {
        self::assertTrue(method_exists(UserRepository::class, 'incrementUnread'));
        self::assertTrue(method_exists(UserRepository::class, 'incrementNewMessage'));
        self::assertFalse(method_exists(MissionRepository::class, 'incrementUnread'));
        self::assertFalse(method_exists(MissileRepository::class, 'incrementUnread'));
    }

    /** Écritures de colonnes de planète : PlanetRepository. */
    public function testPlanetColumnWritesHaveASingleHome(): void
    {
        self::assertTrue(method_exists(PlanetRepository::class, 'decrementField'));
        self::assertTrue(method_exists(PlanetRepository::class, 'setField'));
        self::assertFalse(method_exists(PlanetRepository::class, 'decrementShips'));
        self::assertFalse(method_exists(MissileRepository::class, 'decrementDefence'));
        self::assertFalse(method_exists(MissileRepository::class, 'zeroDefence'));
    }

    /** File de construction et récompenses de fin de chantier : BuildingQueueRepository. */
    public function testBuildingQueueWritesHaveASingleHome(): void
    {
        self::assertTrue(method_exists(BuildingQueueRepository::class, 'saveBuildingQueueState'));
        self::assertTrue(method_exists(BuildingQueueRepository::class, 'saveUserFields'));
        self::assertFalse(method_exists(BuildingQueueRepository::class, 'saveUserXp'));
        self::assertFalse(method_exists(BuildingQueueRepository::class, 'savePlanetRecord'));
        self::assertFalse(method_exists(BuildingQueueRepository::class, 'saveUserRecordXp'));

        // La colonne `xpminier` appartient au module : le dépôt du Coeur de l'application écrit
        // ce que l'appelant lui donne, il ne la nomme pas.
        self::assertStringNotContainsString(
            'xpminier',
            (string) file_get_contents(ROOT_PATH . 'app/Repositories/BuildingQueueRepository.php')
        );
    }

    /**
     * Le filtre d'état des tables à drapeau n'a qu'une implémentation : celle du
     * Coeur d'application des dépôts. Les quatre dépôts l'avaient recopiée (messages, notes, vols,
     * rôles) et les copies avaient fini par diverger.
     */
    public function testTheFlagsStateFilterHasASingleHome(): void
    {
        $declarent = array();

        // Les dépôts du Coeur d'application **et** ceux des modules : aucune copie, où qu'elle soit.
        $fichiers = array_merge(
            glob(ROOT_PATH . 'app/Repositories/*.php') ?: array(),
            glob(ROOT_PATH . 'modules/*/repositories/*.php') ?: array()
        );

        foreach ($fichiers as $fichier) {
            $nom = basename($fichier);

            if ($nom === 'BaseRepository.php') {
                continue;
            }

            if (str_contains((string) file_get_contents($fichier), 'function stateFilter')) {
                $declarent[] = $nom;
            }
        }

        self::assertSame(array(), $declarent, 'Le filtre d\'état ne vit que dans BaseRepository.');
        self::assertStringContainsString(
            'function stateFilter',
            (string) file_get_contents(ROOT_PATH . 'app/Repositories/BaseRepository.php'),
            'BaseRepository porte le filtre d\'état partagé.'
        );
    }

    /**
     * « La planète existant à ces coordonnées » n'a qu'une implémentation :
     * `PlanetRepository::findByCoords()`. Une copie oublie facilement le drapeau, et
     * une planète abandonné ou une lune détruite redevient alors une cible.
     */
    public function testTheLiveWorldAtSomeCoordinatesHasASingleHome(): void
    {
        self::assertTrue(method_exists(PlanetRepository::class, 'findByCoords'));

        foreach (array('findByCoordsType', 'findByCoordsTypeRaw', 'findListByCoords', 'findUpdatedSince') as $copie) {
            self::assertFalse(
                method_exists(PlanetRepository::class, $copie),
                'PlanetRepository::' . $copie . '() doublonnait findByCoords().'
            );
        }

        foreach (array('findPlanetAt', 'findPlanetAtNoType') as $copie) {
            self::assertFalse(
                method_exists(MissionRepository::class, $copie),
                'MissionRepository::' . $copie . '() a rejoint findByCoords().'
            );
        }

        self::assertFalse(method_exists(FleetRepository::class, 'findTargetRow'));

        // Les lunes existantes d'un compte se lisent dans le dépôt des planètes : la
        // copie du dépôt de la galaxie n'avait aucun appelant.
        self::assertTrue(method_exists(PlanetRepository::class, 'findMoonsByOwner'));
        self::assertFalse(method_exists(GalaxyRepository::class, 'findMoonsByOwner'));
    }

    /**
     * Une planète par son identifiant : une seule lecture.
     *
     * Le legacy en portait trois copies — `findCurrentByIdSemicolon()` et
     * `findCurrentByIdWord()` ne différaient de `findCurrentById()` que par la ponctuation de
     * leur SQL — appelées par sept pages. Elles ont rejoint la première : c'est elle qui
     * porte la lecture, et elle seule.
     */
    public function testAWorldByIdHasASingleHome(): void
    {
        self::assertTrue(method_exists(PlanetRepository::class, 'findCurrentById'));

        foreach (array('findCurrentByIdSemicolon', 'findCurrentByIdWord') as $copie) {
            self::assertFalse(
                method_exists(PlanetRepository::class, $copie),
                'PlanetRepository::' . $copie . '() doublonnait findCurrentById().'
            );
        }
    }

    /**
     * Un compte par son identifiant : une seule lecture.
     *
     * `findUserById()` existait dans trois dépôts (amis, missions, recherche) avec le **même**
     * corps. Seule reste celle du dépôt des comptes ; la porte de la galaxie
     * (`GalaxyRepository::findUserById()`) reste parce qu'elle passe par son **préchargement**
     * (une requête pour tout un système) : c'est un cache, pas une seconde règle.
     */
    public function testTheUserByIdReadHasASingleHome(): void
    {
        self::assertTrue(method_exists(UserRepository::class, 'findFullById'), 'Le dépôt des comptes porte la lecture.');
        self::assertFalse(method_exists(UserRepository::class, 'findByRawId'), 'findByRawId() doublonnait findFullById().');
        self::assertTrue(method_exists(GalaxyRepository::class, 'findUserById'), 'La galaxie garde sa porte préchargée.');

        foreach (array(BuddyRepository::class, MissionRepository::class, SearchRepository::class) as $depot) {
            self::assertFalse(
                method_exists($depot, 'findUserById'),
                $depot . '::findUserById() doublonnait UserRepository::findFullById().'
            );
        }
    }

    /**
     * Le pseudo d'un compte : une seule lecture.
     *
     * Il en existait deux : `ActionRepository::username()` (le journal, pour dire d'où venait
     * un renommage) et le SQL bâti à la main de `BuildHostileFleetPlayerLink()` (le lien vers
     * une flotte hostile). Le dépôt des comptes porte désormais la lecture.
     */
    public function testTheUsernameReadHasASingleHome(): void
    {
        self::assertTrue(method_exists(UserRepository::class, 'username'), 'Le dépôt des comptes porte la lecture.');
        self::assertFalse(
            method_exists(ActionRepository::class, 'username'),
            'ActionRepository::username() doublonnait UserRepository::username().'
        );
    }

    /**
     * Les tableaux legacy ne bâtissent plus leurs requêtes.
     *
     * `PageFunctions`, `MiscFunctions` et `GalaxyFunctions` délèguent aux dépôts (une planète par
     * son identifiant, le pseudo d'un compte, le retour d'une flotte sur sa planète) : une
     * requête qui revient dans l'une d'elles serait une seconde vérité sur une table du jeu.
     */
    public function testTheLegacyWrappersNoLongerBuildTheirOwnQueries(): void
    {
        foreach (array('PageFunctions', 'MiscFunctions', 'GalaxyFunctions') as $enveloppe) {
            $source = (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/' . $enveloppe . '.php');

            self::assertStringNotContainsString(
                'doquery(',
                $source,
                $enveloppe . '.php doit déléguer aux dépôts, jamais bâtir sa requête.'
            );

            self::assertStringNotContainsString(
                'Connection::',
                $source,
                $enveloppe . '.php ne parle plus au serveur de base : la requête vit dans le dépôt de sa table.'
            );
        }
    }

    /**
     * Le miroir de la recherche n'a qu'un écrivain : la file.
     *
     * `planets.b_tech` / `b_tech_id` / `users.b_tech_planet` sont écrits par `QueueService`
     * seul (`storeResearchQueue()`, `clearResearchPlanet()`). `HandleTechnologieBuild()` les
     * lisait et les réécrivait à sa façon : une seconde vérité sur la même file.
     */
    public function testTheResearchMirrorHasASingleWriter(): void
    {
        $handle = $this->methodSource(
            (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/BuildFunctions.php'),
            'HandleTechnologieBuild'
        );

        self::assertStringNotContainsString(
            'UPDATE',
            $handle,
            'HandleTechnologieBuild() ne réécrit plus le miroir : la file s en charge.'
        );
        self::assertStringContainsString('advanceResearch', $handle, 'La file dit ce qui suit.');
    }

    /**
     * Le nettoyage anti-triche passe par les dépôts (sauf les tables de modules).
     *
     * `DeleteSelectedUser()` efface **physiquement** — c'est l'exception documentée du
     * chantier, choisie pour l'anti-triche — mais il ne nomme plus aucune table du Coeur d'application :
     * chacune a sa méthode dans son dépôt (`purgeAccount`, `purgeWorldsOfOwner`,
     * `purgeByOwner`, `purgeStatpoints`). Les tables des modules ne sont pas nommées non plus :
     * c'est `ModuleService::purgeAccountData()` qui demande aux modules d'effacer les leurs.
     * Seule l'alliance reste nommée (le Coeur d'application lui retire un membre).
     */
    public function testTheAntiCheatWipeGoesThroughTheRepositories(): void
    {
        $wipe = $this->methodSource(
            (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/UserFunctions.php'),
            'DeleteSelectedUser'
        );

        $tables = array(
            'users', 'planets', 'galaxy', 'lunas', 'statpoints', 'messages', 'fleets', 'rw', 'buddy',
            'notes', 'annonce',
        );

        foreach ($tables as $table) {
            self::assertStringNotContainsString(
                "'" . $table . "'",
                $wipe,
                'DeleteSelectedUser() ne doit plus nommer la table ' . $table . '.'
            );
        }

        self::assertStringContainsString('purgeAccountData', $wipe, 'Les modules effacent leurs tables.');
    }

    /** Rangs de statistiques : une méthode paramétrée par stat_type. */
    public function testStatRanksHaveASingleImplementation(): void
    {
        self::assertTrue(method_exists(StatsRepository::class, 'updateRank'));
        self::assertTrue(method_exists(StatsRepository::class, 'updateRankOnly'));

        foreach (array('updateAllyRank', 'updateUserRank', 'updateAllyRankOnly', 'updateUserRankOnly') as $gone) {
            self::assertFalse(
                method_exists(StatsRepository::class, $gone),
                $gone . '() est remplacée par updateRank().'
            );
        }
    }

    /**
     * Niveaux RPG : le Core de l'application n'en garde aucune trace.
     *
     * Les niveaux (mineur, raideur) sont écrits par le module `officier`, dans **son**
     * dépôt, qui dérive celui du Core : le Core ne connaît donc ni ces méthodes, ni le
     * décompte qui les fait monter. Le module une fois déposé, sa copie dérive la classe du
     * Core — c'est le point de surcharge, jamais une seconde vérité.
     */
    public function testLevelUpBelongsToTheOfficerModule(): void
    {
        foreach (array('levelUp', 'levelUpMinier', 'levelUpRaid') as $method) {
            self::assertFalse(
                method_exists(UserRepository::class, $method),
                'UserRepository::' . $method . '() appartient au module officier.'
            );
        }
    }

    /**
     * Supprimer un compte : la règle vit dans `UserRepository`, sur le drapeau
     * partagé (`Flags::DELETED`).
     *
     * La colonne `users.deleted` a été retirée par la migration 016 : un code qui
     * la lirait encore travaillerait sur une autre vérité que le drapeau (et
     * échouerait en base, la colonne n'existant plus). Ce test refuse son retour.
     */
    public function testAccountDeletionUsesTheSharedFlag(): void
    {
        self::assertTrue(method_exists(UserRepository::class, 'softDelete'));
        self::assertTrue(method_exists(UserRepository::class, 'restore'));

        // `['deleted']`, `["deleted"]`, `` `deleted` `` en SQL, ou `deleted = 1`.
        // La variable PHP `$deleted` (un compteur de lignes) n'est pas visée.
        $pattern = '/(\[["\']deleted["\']\]|`deleted`|(?<!\$)deleted\s*=\s*\'?[01]\'?)/';
        $found = array();

        foreach ($this->phpFiles(ROOT_PATH . 'app') as $file) {
            if (preg_match($pattern, (string) file_get_contents($file)) === 1) {
                $found[] = str_replace('\\', '/', str_replace(ROOT_PATH, '', $file));
            }
        }

        sort($found);

        self::assertSame(
            array(),
            $found,
            'La colonne `users.deleted` n\'existe plus : l\'état d\'un compte supprimé est Flags::DELETED.'
        );
    }

    /**
     * Une colonne qu'un **module** a posée dans une table du Coeur d'application n'est nommée nulle
     * part dans le Coeur d'application : ni dans une requête, ni dans un `SET`.
     *
     * `users.bot` (module `bot`) est lue par l'historique des connexions et le journal
     * des actions, `users.settings_bots` est écrit par la page Options : les trois
     * passent par un **point de surcharge** (`botColumn()` des dépôts, `extraSettings()`
     * du service des réglages, l'écriture pilotée par les données de `UserRepository`).
     * Le Coeur d'application tourne donc sur un jeu **sans** le module — ce test refuse le retour
     * d'une colonne nommée en dur.
     */
    public function testTheSocleNeverNamesAModuleColumn(): void
    {
        // Formes SQL uniquement : `u.bot` (l'alias de la jointure), `'settings_bots'`
        // (une clé d'écriture) et la comparaison nue `bot = 1` / `bot IS NULL`. Le nom du
        // module entre accents graves et la variable PHP `$bot` sont de la prose.
        $pattern = '/(u\.bot\b|\'settings_bots\'|(?<!\$)\bbot\b\s*(?:=|IS\b))/';
        $found = array();

        foreach ($this->phpFiles(ROOT_PATH . 'app') as $file) {
            if (preg_match($pattern, (string) file_get_contents($file)) === 1) {
                $found[] = str_replace('\\', '/', str_replace(ROOT_PATH, '', $file));
            }
        }

        sort($found);

        self::assertSame(
            array(),
            $found,
            'Une colonne de module se lit par surcharge de classe, jamais en nommant la colonne dans le Coeur d\'application.'
        );
    }

    /**
     * Supprimer un vol : le panneau marque la ligne, le moteur la consomme.
     *
     * Deux gestes différents, donc deux entrées différentes dans `MissionRepository` :
     * `markDeleted()`/`restore()` pour le panneau (le vol reste en base et peut
     * revenir), `deleteFleet()` pour la fin de mission (la ligne disparaît pour de
     * bon). Le moteur doit en outre **ignorer** un vol marqué : sans ce filtre, sa
     * mission partirait quand même à l'échéance.
     */
    public function testFleetDeletionKeepsTheEngineAndThePanelApart(): void
    {
        foreach (array('markDeleted', 'restore', 'deleteFleet') as $method) {
            self::assertTrue(
                method_exists(MissionRepository::class, $method),
                'MissionRepository::' . $method . '() manque.'
            );
        }

        $source = (string) file_get_contents(ROOT_PATH . 'app/Repositories/MissionRepository.php');

        // Le marquage passe par le drapeau partagé, dans les deux sens.
        self::assertStringContainsString('`flags` = `flags` | ?', $source);
        self::assertStringContainsString('`flags` = `flags` & ~?', $source);

        // Les deux lectures du moteur (traitement d'une position, attaque groupée)
        // doivent filtrer le drapeau.
        foreach (array('findFleetsToProcess', 'findGroupFleets') as $method) {
            self::assertStringContainsString(
                '`flags`',
                $this->methodSource($source, $method),
                $method . '() doit ignorer les vols supprimés.'
            );
        }
    }

    /**
     * Détruire une lune : un seul geste, et il est **logique**.
     *
     * La ligne `planets` de type 3 reste en base (drapeau `DELETED`) ; les lectures
     * de lunes filtrent ce drapeau — la lune n'existe plus pour le jeu, mais le
     * panneau peut la rétablir. Les trois chemins de destruction (attaque, abandon
     * d'une colonie, panneau) passent par la même méthode du dépôt.
     */
    public function testMoonDestructionIsLogicalEverywhere(): void
    {
        foreach (array('markMoonDeletedAt', 'markMoonDeletedById', 'restoreMoonAt', 'markMoonRegistryDestroyed') as $method) {
            self::assertTrue(
                method_exists(PlanetRepository::class, $method),
                'PlanetRepository::' . $method . '() manque.'
            );
        }

        // Les lectures de lunes ignorent une lune détruite : soit elles portent la
        // condition, soit elles passent par le filtre commun.
        $planets = (string) file_get_contents(ROOT_PATH . 'app/Repositories/PlanetRepository.php');

        self::assertStringContainsString(
            'flags',
            $this->methodSource($planets, 'liveFilter'),
            'Le filtre des planètes doit porter le drapeau DELETED.'
        );

        foreach (array('findMoonPlanet', 'findByCoords', 'findMoonsByOwner', 'countMoonsAt') as $method) {
            self::assertMatchesRegularExpression(
                '/liveFilter|flags/',
                $this->methodSource($planets, $method),
                $method . '() doit ignorer les lunes détruites.'
            );
        }

        // Plus de suppression définitive d'une lune dans le dépôt ni dans le panneau.
        $service = (string) file_get_contents(ROOT_PATH . 'app/Services/PlayerAdminService.php');

        foreach (array('deleteMoonAt', 'deleteMoonRegistry') as $gone) {
            self::assertStringNotContainsString($gone, $planets . $service, $gone . '() a été remplacée par le drapeau.');
        }

        // Les deux chemins du jeu marquent la lune au lieu de la supprimer.
        $legacy = (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/CombatFunctions.php')
            . (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/FleetFunctions.php');

        self::assertStringContainsString('markMoonDeletedById', $legacy);
        self::assertStringContainsString('markMoonDeletedAt', $legacy);
        self::assertStringNotContainsString('$QryDeleteMoon', $legacy);

        // Le panneau propose le retour en arrière.
        self::assertStringContainsString(
            'moon_restore',
            (string) file_get_contents(ROOT_PATH . 'app/Controllers/Back/PlayerController.php')
        );
    }

    /**
     * Abandonner une colonie : un seul geste, et il est **logique**.
     *
     * La ligne `planets` reste en base (drapeau `DELETED`, `id_owner` conservé), la
     * position est détachée de la galaxie, et le panneau peut rétablir la colonie.
     * L'ancien marquage (`destruyed` posé dans le passé par une version antérieure)
     * est **converti** par la purge de la galaxie, jamais supprimé.
     */
    public function testPlanetAbandonmentIsLogicalEverywhere(): void
    {
        foreach (array('markPlanetAbandoned', 'restorePlanetAt', 'findAbandonedPlanetsByOwner', 'countLivePlanetsAt', 'isPositionReserved') as $method) {
            self::assertTrue(
                method_exists(PlanetRepository::class, $method),
                'PlanetRepository::' . $method . '() manque.'
            );
        }

        // Les lectures de planètes existants ignorent la colonie abandonnée.
        $planets = (string) file_get_contents(ROOT_PATH . 'app/Repositories/PlanetRepository.php');

        foreach (array('findAllByOwner', 'findProtectionLevel', 'findByCoords', 'findMoonPlanet', 'countLivePlanetsAt') as $method) {
            self::assertMatchesRegularExpression(
                '/liveFilter|flags/',
                $this->methodSource($planets, $method),
                $method . '() doit ignorer les planètes abandonnés.'
            );
        }

        // Le rétablissement repart de maintenant : sinon la colonie rétablie
        // encaisserait la production de tout son abandon.
        foreach (array('restorePlanetAt', 'restoreMoonAt') as $method) {
            self::assertStringContainsString(
                'last_update',
                $this->methodSource($planets, $method),
                $method . '() doit remettre `last_update` à maintenant.'
            );
        }

        // L'abandon du jeu marque la ligne et détache la position.
        $abandon = $this->methodSource(
            (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/FleetFunctions.php'),
            'AbandonColony'
        );

        self::assertStringContainsString('markPlanetAbandoned', $abandon);
        self::assertStringContainsString('clearPlanetLink', $abandon);

        // La purge héritée convertit l'ancien marquage, avec le garde qui évite de
        // balayer toutes les planètes existantes (elles valent `destruyed = 0`).
        $check = $this->methodSource(
            (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/GalaxyFunctions.php'),
            'CheckAbandonPlanetState'
        );

        self::assertStringContainsString('markPlanetAbandoned', $check);
        self::assertStringContainsString('clearPlanetLink', $check);
        self::assertMatchesRegularExpression(
            '/\(int\) \$planet\[.destruyed.\]\s*!==\s*0/',
            $check,
            'CheckAbandonPlanetState() doit exiger un `destruyed` posé avant de convertir.'
        );

        // Une colonie abandonnée ne bloque plus la colonisation de sa position : la
        // fonction ne lit plus la table, elle interroge le dépôt, dont les lectures
        // **existantes** (`countLivePlanetsAt()`, `findByCoords()`) filtrent le drapeau.
        $create = $this->methodSource(
            (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/BuildFunctions.php'),
            'CreateOnePlanetRecord'
        );

        self::assertStringContainsString(
            'countLivePlanetsAt',
            $create,
            'CreateOnePlanetRecord() doit passer par la lecture existante du dépôt des planètes.'
        );
        self::assertStringNotContainsString(
            'SELECT',
            $create,
            'CreateOnePlanetRecord() ne bâtit plus aucune requête : le dépôt les porte.'
        );

        // Les coordonnées d'une colonie abandonnée restent réservées le temps du
        // délai de jeu : la garde sert aussi bien la colonisation que la création
        // d'une planète (inscription, robots, remise à zéro).
        self::assertStringContainsString('isPositionReserved', $create);

        $colonise = $this->methodSource(
            (string) file_get_contents(ROOT_PATH . 'app/Services/FleetMissionService.php'),
            'colonise'
        );

        self::assertStringContainsString('isPositionReserved', $colonise);
        self::assertStringContainsString('sys_colo_reserved', $colonise);

        // Le retour en arrière annonce de nouveau la position : la ligne `galaxy`
        // est recréée si une base ancienne l'avait supprimée avec sa planète.
        $galaxy = (string) file_get_contents(ROOT_PATH . 'app/Repositories/GalaxyRepository.php');

        self::assertStringContainsString('INSERT', $this->methodSource($galaxy, 'linkPlanet'));

        // Piège corrigé une fois : l'abandon **détache** le lien sans supprimer la ligne
        // `galaxy`, donc l'annonce ne doit pas être accrochée à l'absence de ligne.
        self::assertMatchesRegularExpression(
            '/!\.?\$GalaxyRow \|\| \(int\) \$GalaxyRow\[.id_planet.\] === 0/',
            $this->methodSource((string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/GalaxyFunctions.php'), 'ShowGalaxyRows'),
            'ShowGalaxyRows() doit annoncer la position dès qu\'elle est libre, ligne présente ou non.'
        );

        // Le panneau propose le retour en arrière.
        self::assertStringContainsString(
            'planet_restore',
            (string) file_get_contents(ROOT_PATH . 'app/Controllers/Back/PlayerController.php')
        );

        // Les lectures qui décident d'un **effet de jeu** filtrent le drapeau :
        // cibles de missiles, cible de phalange et laboratoires intergalactiques.
        $planets2 = (string) file_get_contents(ROOT_PATH . 'app/Repositories/PlanetRepository.php');

        foreach (array('findByCoords', 'findListByCoordsNoType') as $method) {
            self::assertStringContainsString(
                'liveFilter',
                $this->methodSource($planets2, $method),
                $method . '() doit ignorer une colonie abandonnée.'
            );
        }

        $build = (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/BuildFunctions.php');

        self::assertSame(
            2,
            substr_count($build, 'Seuls les laboratoires des planètes'),
            'Les deux sommes de laboratoires intergalactiques doivent ignorer une colonie abandonnée.'
        );

        self::assertStringContainsString(
            'phl_no_target',
            (string) file_get_contents(ROOT_PATH . 'app/Controllers/Game/PhalanxController.php'),
            'La phalange doit refuser une planète disparu avant de débiter le deutérium.'
        );

        // Les deux pages de tir passent par les lectures filtrées du dépôt : une
        // colonie abandonnée n'est plus une cible, et la salve devient une mission.
        foreach (array('MissileLaunchController', 'MipAttackController') as $controller) {
            $source = (string) file_get_contents(ROOT_PATH . 'app/Controllers/Game/' . $controller . '.php');

            self::assertStringContainsString('findByCoords', $source, $controller . ' doit refuser une planète disparu.');
            self::assertStringContainsString('missiles->launch', $source, $controller . ' doit confier la salve au service des missiles.');
        }

        self::assertStringContainsString(
            'insertMissileStrike',
            (string) file_get_contents(ROOT_PATH . 'app/Services/MissileService.php'),
            'Un tir de missiles doit être enregistré comme une mission de flotte.'
        );
    }

    /** Code source d'une méthode, de sa signature à la suivante. */
    private function methodSource(string $source, string $method): string
    {
        $start = strpos($source, 'function ' . $method . '(');

        if ($start === false) {
            return '';
        }

        $next = strpos($source, 'function ', $start + 1);

        return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $found = array();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $found[] = (string) $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }
}
