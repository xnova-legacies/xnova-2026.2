<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Acl;
use App\Core\AdminAccess;
use App\Core\Flags;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;

/**
 * Rôles et permissions (ACL du panneau d'administration).
 *
 * `App\Core\Acl` dit ce qui existe (le catalogue des permissions et la règle
 * pure) ; ce service lit et écrit les rôles en base, et décide de l'accès d'un
 * compte (`can()`).
 *
 * Deux invariants :
 *  - le compte d'installation (`Acl::SUPER_ADMIN_ID`) est super administrateur par
 *    définition : tous les droits, non supprimable, non bannissable, et son rôle ne
 *    se change pas — c'est `assign()` qui le refuse ;
 *  - un rôle **par défaut** (`DEFAULT_ROLES`) se modifie mais ne se supprime pas :
 *    sans cela, une installation pourrait se retrouver sans « Administrateur ».
 *
 * Les autres rôles se suppriment **logiquement** (`Flags::DELETED`, comme les
 * messages, les notes, les vols, les lunes et les colonies) : ils quittent la
 * liste et ne donnent plus aucun droit, et la page les rétablit.
 */
final class AclService
{
    /**
     * Rôles créés à l'installation (et repris par `db/acl.php` sur une base
     * existante) : nom technique => libellé, description et niveau historique.
     *
     * Le niveau (`AdminAccess`) alimente les permissions initiales : chaque compte
     * reçoit exactement les accès que son `authlevel` lui ouvrait avant l'ACL.
     *
     * `position` est la place dans la hiérarchie (plus grand = plus haut) : opérateur,
     * modérateur, administrateur, super administrateur, du bas vers le haut. C'est ici
     * qu'elle est écrite, en un seul endroit : `seedDefaults()` la pose à
     * l'installation, et le semis ne réécrit jamais un rôle ajusté depuis.
     */
    public const DEFAULT_ROLES = array(
        'operator' => array(
            'label' => 'Opérateur',
            'description' => 'Consultation, notes, messages et file de fabrication',
            'level' => AdminAccess::OPERATOR,
            'position' => 10,
            'all' => false,
        ),
        'moderator' => array(
            'label' => 'Modérateur',
            'description' => 'Gestion des comptes, des messages et des multi-comptes',
            'level' => AdminAccess::MODERATOR,
            'position' => 20,
            'all' => false,
        ),
        'admin' => array(
            'label' => 'Administrateur',
            'description' => 'Réglages du jeu, outils et remises à zéro',
            'level' => AdminAccess::ADMIN,
            'position' => 30,
            'all' => false,
        ),
        'super_admin' => array(
            'label' => 'Super administrateur',
            'description' => 'Tous les droits, y compris les pages à venir',
            'level' => AdminAccess::ADMIN,
            'position' => 40,
            'all' => true,
        ),
    );

    /** Liste de permissions d'un rôle, par identifiant (une lecture par rôle et par page). */
    private static array $permissionsCache = array();

    public function __construct(
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /**
     * Le compte peut-il ouvrir cette permission ? C'est la seule décision du
     * dispositif : les pages ne comparent plus de niveaux.
     *
     * @param array<string, mixed> $user compte tel que le charge `Authenticator`
     */
    public function can(array $user, string $permission): bool
    {
        if (Acl::isSuperAdmin($user)) {
            return true;
        }

        $roleId = (int) ($user['role_id'] ?? 0);

        if ($roleId <= 0) {
            return false;
        }

        return Acl::granted($this->permissionsRaw($roleId), $permission);
    }

    /**
     * Permissions effectives d'un compte (celles de son rôle).
     *
     * @param array<string, mixed> $user
     * @return array<int, string>
     */
    public function permissions(array $user): array
    {
        $roleId = (int) ($user['role_id'] ?? 0);

        if ($roleId <= 0) {
            return array();
        }

        return Acl::parse($this->permissionsRaw($roleId));
    }

    /**
     * Rôle d'un compte, ou `false` s'il n'en a pas (ou si le rôle n'existe plus).
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>|false
     */
    public function role(array $user): array|false
    {
        $roleId = (int) ($user['role_id'] ?? 0);

        return $roleId > 0 ? $this->roles->findById($roleId) : false;
    }

    /**
     * Libellé du rôle d'un compte, pour l'affichage : « Super administrateur »
     * pour le compte d'installation, sinon le libellé du rôle, sinon ''.
     *
     * @param array<string, mixed> $user
     */
    public function roleLabel(array $user): string
    {
        if (Acl::isSuperAdmin($user)) {
            return self::DEFAULT_ROLES['super_admin']['label'];
        }

        $role = $this->role($user);

        return $role === false ? '' : (string) $role['label'];
    }

    /**
     * Rôles existants, avec le nombre de comptes qui les portent (la page les
     * classe par libellé).
     *
     * @return array<int, array<string, mixed>>
     */
    public function roles(bool $deleted = false): array
    {
        return $this->roles->findAllWithCounts($deleted);
    }

    /** @return array<string, mixed>|false */
    public function find(int $id): array|false
    {
        return $this->roles->findById($id);
    }

    /**
     * Nom technique d'un rôle par défaut ? Ces rôles se modifient mais ne se
     * suppriment pas (l'installation doit toujours garder un administrateur).
     */
    public static function isDefault(string $name): bool
    {
        return isset(self::DEFAULT_ROLES[$name]);
    }

    /**
     * Crée un rôle. Renvoie '' si c'est fait, sinon la clé du motif de refus.
     *
     * Le rôle naît **en bas** de la hiérarchie (une place sous la plus basse) : il
     * faut le remonter pour qu'il puisse gérer quelqu'un.
     *
     * @param array<int, string> $permissions
     */
    public function create(string $label, string $description, array $permissions): string
    {
        $label = trim($label);

        if (mb_strlen($label) < 3 || mb_strlen($label) > 100) {
            return 'acl_label_length';
        }

        $name = self::slug($label);

        if ($name === '' || $this->roles->findByName($name) !== false) {
            return 'acl_name_taken';
        }

        $this->roles->insert(
            $name,
            $label,
            trim($description),
            Acl::serialize($this->filter($permissions)),
            $this->roles->lowestPosition() - 1,
            time()
        );

        return '';
    }

    /**
     * Le compte peut-il **gérer** ce rôle (le renommer, cocher ses permissions, le
     * supprimer, le déplacer) ? C'est `Acl::manages()` appliqué aux données : le
     * compte d'installation gère tout, un rôle qui porte `*` aussi, et les autres
     * seulement ce qui est **strictement sous eux**.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $role
     */
    public function canManage(array $actor, array $role): bool
    {
        if (Acl::isSuperAdmin($actor)) {
            return true;
        }

        return Acl::manages($this->actorPosition($actor), (int) ($role['position'] ?? 0), $this->actorHasAll($actor));
    }

    /**
     * Modifie un rôle : libellé, description et permissions.
     *
     * @param array<string, mixed> $actor
     * @param array<int, string>   $permissions
     */
    public function save(array $actor, int $id, string $label, string $description, array $permissions): string
    {
        $role = $this->roles->findById($id);

        if ($role === false) {
            return 'acl_role_notfound';
        }

        if (!$this->canManage($actor, $role)) {
            return 'acl_role_above';
        }

        $label = trim($label);

        if (mb_strlen($label) < 3 || mb_strlen($label) > 100) {
            return 'acl_label_length';
        }

        $this->roles->update($id, $label, trim($description));

        // Un rôle « tous les droits » (`*`) garde sa portée : la page ne propose
        // pas de cases à cocher pour lui, et le formulaire ne peut donc pas la
        // réduire par inadvertance.
        if (trim((string) $role['permissions']) !== Acl::ALL) {
            $this->roles->setPermissions($id, Acl::serialize($this->filter($permissions)));
            self::$permissionsCache[$id] = null;
        }

        return '';
    }

    /**
     * Supprime **logiquement** un rôle (il se rétablit ensuite, comme un message,
     * une note ou un vol). Renvoie '' si c'est fait, sinon le motif de refus.
     *
     * @param array<string, mixed> $actor
     */
    public function delete(array $actor, int $id): string
    {
        $role = $this->roles->findById($id);

        if ($role === false) {
            return 'acl_role_notfound';
        }

        if (!$this->canManage($actor, $role)) {
            return 'acl_role_above';
        }

        if (self::isDefault((string) $role['name'])) {
            return 'acl_role_default';
        }

        if ($this->roles->countUsers($id) > 0) {
            return 'acl_role_used';
        }

        $this->roles->setFlag($id, Flags::DELETED);
        self::$permissionsCache[$id] = null;

        return '';
    }

    /**
     * Rétablit un rôle supprimé logiquement.
     *
     * @param array<string, mixed> $actor
     */
    public function restore(array $actor, int $id): string
    {
        $role = $this->roles->findByIdAny($id);

        if ($role === false) {
            return 'acl_role_notfound';
        }

        if (!$this->canManage($actor, $role)) {
            return 'acl_role_above';
        }

        $this->roles->setFlag($id, Flags::DELETED, false);
        self::$permissionsCache[$id] = null;

        return '';
    }

    /**
     * Déplace un rôle d'une place vers le haut ou vers le bas (`$up`) : sa position
     * est échangée avec celle de son voisin immédiat. Renvoie '' si c'est fait,
     * sinon la clé du refus — rôle inconnu, rôle (ou son voisin) au-dessus du sien,
     * ou rôle déjà en bout d'échelle.
     *
     * @param array<string, mixed> $actor
     */
    public function move(array $actor, int $id, bool $up): string
    {
        $role = $this->roles->findById($id);

        if ($role === false) {
            return 'acl_role_notfound';
        }

        if (!$this->canManage($actor, $role)) {
            return 'acl_role_above';
        }

        $neighbour = $this->roles->findNeighbour((int) $role['position'], $up);

        if ($neighbour === false) {
            return 'acl_role_edge';
        }

        // Monter un rôle à la place de son propre rôle ferait descendre celui-ci :
        // le voisin doit être gérable lui aussi (c'est le cas dès qu'il est sous
        // l'acteur, donc le blocage ne joue que contre son propre rôle).
        if (!$this->canManage($actor, $neighbour)) {
            return 'acl_role_above';
        }

        $this->roles->setPosition($id, (int) $neighbour['position']);
        $this->roles->setPosition((int) $neighbour['id'], (int) $role['position']);

        return '';
    }

    /**
     * Attribue un rôle à un compte. Renvoie '' si c'est fait, sinon le motif de
     * refus : le compte d'installation est super administrateur par définition,
     * son rôle ne se change pas.
     */
    public function assign(int $userId, int $roleId): string
    {
        if ($userId <= 0) {
            return 'acl_user_notfound';
        }

        if ($userId === Acl::SUPER_ADMIN_ID) {
            return 'acl_super_admin_role';
        }

        if ($roleId > 0 && $this->roles->findById($roleId) === false) {
            return 'acl_role_notfound';
        }

        $this->users->setColumn($userId, 'role_id', $roleId);

        return '';
    }

    /**
     * Installe les rôles par défaut et attribue à chaque compte celui de son
     * `authlevel`. **Idempotent** : un rôle déjà présent garde son libellé, sa
     * description et ses permissions (l'administrateur a pu les ajuster), seules
     * les permissions d'un rôle encore vide sont complétées ; et un compte qui a
     * déjà un rôle n'est pas retouché.
     *
     * @return array<string, int> compte rendu : rôle créé -> identifiant, plus
     *                             'assignes' (comptes rattachés) et 'roles' (nombre).
     */
    public function seed(): array
    {
        $report = array();
        $ids = array();

        foreach (self::DEFAULT_ROLES as $name => $definition) {
            $role = $this->roles->findByName($name);

            if ($role === false) {
                $permissions = $definition['all'] ? Acl::ALL : Acl::serialize(Acl::defaultsFor($definition['level']));
                $ids[$name] = $this->roles->insert(
                    $name,
                    $definition['label'],
                    $definition['description'],
                    $permissions,
                    (int) $definition['position'],
                    time()
                );
                $report[$name] = $ids[$name];

                continue;
            }

            $ids[$name] = (int) $role['id'];

            // Rôle déjà présent : on ne complète que s'il est vide (jamais de
            // réécriture des choix de l'administrateur).
            if (trim((string) $role['permissions']) === '') {
                $permissions = $definition['all'] ? Acl::ALL : Acl::serialize(Acl::defaultsFor($definition['level']));
                $this->roles->setPermissions($ids[$name], $permissions);
                self::$permissionsCache[$ids[$name]] = null;
            }
        }

        $report['assignes'] = $this->assignLevels($ids);
        $report['roles'] = count($ids);

        return $report;
    }

    /**
     * Semis depuis un point d'entrée qui n'a pas de service sous la main
     * (l'installateur, `db/acl.php`) : c'est le même travail que `seed()`.
     *
     * @return array<string, int>
     */
    public static function seedDefaults(): array
    {
        return (new self())->seed();
    }

    /**
     * Rattache les comptes sans rôle au rôle de leur `authlevel` : opérateur (1),
     * modérateur (2), administrateur (3). Le compte d'installation est laissé tel
     * quel (il est super administrateur par définition).
     *
     * @param array<string, int> $ids nom technique => identifiant
     */
    private function assignLevels(array $ids): int
    {
        $map = array(
            AdminAccess::OPERATOR => 'operator',
            AdminAccess::MODERATOR => 'moderator',
            AdminAccess::ADMIN => 'admin',
        );

        $assigned = 0;

        foreach ($map as $level => $name) {
            if (!isset($ids[$name])) {
                continue;
            }

            $assigned += $this->users->assignRoleToAuthlevel($level, $ids[$name], Acl::SUPER_ADMIN_ID);
        }

        return $assigned;
    }

    /**
     * Permissions envoyées par la page : seules celles du catalogue sont retenues
     * (une valeur bricolée dans le formulaire ne peut pas ouvrir une page).
     *
     * @param array<int, string> $permissions
     * @return array<int, string>
     */
    private function filter(array $permissions): array
    {
        $known = array();

        foreach ($permissions as $permission) {
            $permission = trim((string) $permission);

            if (Acl::exists($permission) && $permission !== Acl::ALL) {
                $known[] = $permission;
            }
        }

        return $known;
    }

    /** Permissions brutes d'un rôle, en cache pour la durée de la page. */
    private function permissionsRaw(int $roleId): string
    {
        if (!array_key_exists($roleId, self::$permissionsCache)) {
            $role = $this->roles->findById($roleId);
            self::$permissionsCache[$roleId] = $role === false ? '' : (string) $role['permissions'];
        }

        return (string) self::$permissionsCache[$roleId];
    }

    /** Place du rôle du compte dans la hiérarchie (0 s'il n'a pas de rôle). */
    private function actorPosition(array $actor): int
    {
        $role = $this->role($actor);

        return $role === false ? 0 : (int) $role['position'];
    }

    /** Le rôle du compte porte-t-il « tous les droits » (`*`) ? */
    private function actorHasAll(array $actor): bool
    {
        $role = $this->role($actor);

        return $role !== false && trim((string) $role['permissions']) === Acl::ALL;
    }

    /** Nom technique déduit d'un libellé (« Chef de section » -> `chef_de_section`). */
    public static function slug(string $label): string
    {
        $slug = strtolower(trim($label));
        $slug = strtr($slug, array(
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'ñ' => 'n',
        ));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');

        // La colonne `name` est un varchar(50) : un libellé long ne doit pas
        // tronquer silencieusement (MySQL strict refuserait l'écriture).
        return substr($slug, 0, 50);
    }

    /** Vide le cache des permissions (après une écriture, ou pour un test). */
    public static function clearCache(): void
    {
        self::$permissionsCache = array();
    }
}
