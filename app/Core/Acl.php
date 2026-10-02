<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contrôle d'accès (ACL) du panneau d'administration.
 *
 * Un **rôle** est un nom, un libellé et une liste de permissions ; un compte porte
 * un rôle (`users.role_id`). Une permission décrit **une page** (`admin.overview`,
 * `admin.settings`…) ou, plus tard, un module (`module.chat`) : le catalogue de ce
 * fichier est la référence, et chaque page du panneau déclare la sienne
 * (`AdminController::requiredPermission()`).
 *
 * Deux exceptions, voulues :
 *  - le compte d'installation (`SUPER_ADMIN_ID`) est super administrateur **par
 *    définition** : tous les droits, non supprimable, non bannissable, et son rôle
 *    ne se change pas ;
 *  - la permission `*` d'un rôle donne tous les droits, y compris ceux des pages
 *    qui n'existent pas encore — c'est ainsi qu'un rôle « Super administrateur »
 *    suit les évolutions du jeu.
 *
 * Les rôles sont **hiérarchisés** : chacun porte une place (`roles.position`, plus
 * grande = plus haut, migration 021) et ne gère que les rôles placés strictement
 * sous lui (`manages()`). Un rôle qui porte `*` gère tout le monde, lui-même
 * compris : il n'a plus rien à s'octroyer.
 *
 * Tout est pur ici (aucun accès disque/base) : `App\Services\AclService` lit et
 * écrit les rôles, ce catalogue dit ce qui existe et à quel niveau historique
 * (`AdminAccess`) chaque permission correspond — c'est ce niveau qui alimente les
 * rôles par défaut lors de l'installation.
 */
final class Acl
{
    /** Le compte d'installation : super administrateur, intouchable. */
    public const SUPER_ADMIN_ID = 1;

    /** Permission spéciale : tout, y compris les pages à venir. */
    public const ALL = '*';

    /** Préfixe des permissions du panneau d'administration. */
    public const ADMIN_PREFIX = 'admin.';

    /** Préfixe réservé aux modules (un module ajoute sa permission). */
    public const MODULE_PREFIX = 'module.';

    /**
     * Catalogue des permissions.
     *
     * `label` est une clé de langue (`leftmenu.mo` pour les entrées du menu
     * d'administration), `page` l'adresse de la page concernée, `level` le niveau
     * historique (`AdminAccess`) dont le rôle par défaut hérite la permission.
     *
     * Ajouter une page = ajouter sa ligne ici **et** la déclarer dans le
     * contrôleur : `tests/Unit/Core/AclTest` vérifie que les deux vont ensemble.
     */
    private const CATALOG = array(
        'admin.overview' => array(
            'label' => 'adm_over',
            'page' => '/back/overview',
            'level' => AdminAccess::OPERATOR,
        ),
        'admin.menu' => array(
            'label' => 'adm_menu',
            'page' => '/back/menu',
            'level' => AdminAccess::OPERATOR,
        ),
        'admin.actions' => array(
            'label' => 'adm_actions',
            'page' => '/back/actions',
            'level' => AdminAccess::OPERATOR,
        ),
        'admin.player' => array(
            'label' => 'adm_player',
            'page' => '/back/player',
            'level' => AdminAccess::OPERATOR,
        ),
        'admin.fleets' => array(
            'label' => 'adm_fleet',
            'page' => '/back/flying-fleets',
            'level' => AdminAccess::OPERATOR,
        ),
        'admin.queue' => array(
            'label' => 'adm_build',
            'page' => '/back/queue-fix',
            'level' => AdminAccess::OPERATOR,
        ),
        'admin.notes' => array(
            'label' => 'adm_notes',
            'page' => '/back/notes',
            'level' => AdminAccess::OPERATOR,
        ),
        // La page des robots est une page du **Core de l'application** dont le contenu
        // appartient à un module : sa permission vit donc ici, sinon elle n'existerait
        // qu'une fois le module déposé — et la porte ne répondrait à personne dans une
        // version de base.
        'admin.robots' => array(
            'label' => 'adm_robots',
            'page' => '/back/robots',
            'level' => AdminAccess::OPERATOR,
        ),
        'admin.message-all' => array(
            'label' => 'adm_msg_all',
            'page' => '/back/message-all',
            'level' => AdminAccess::OPERATOR,
        ),
        'admin.userlist' => array(
            'label' => 'adm_plrlst',
            'page' => '/back/userlist',
            'level' => AdminAccess::MODERATOR,
        ),
        'admin.multi' => array(
            'label' => 'adm_multi',
            'page' => '/back/multi',
            'level' => AdminAccess::MODERATOR,
        ),
        'admin.messages' => array(
            'label' => 'adm_msg',
            'page' => '/back/messagelist',
            'level' => AdminAccess::MODERATOR,
        ),
        'admin.settings' => array(
            'label' => 'adm_conf',
            'page' => '/back/settings',
            'level' => AdminAccess::ADMIN,
        ),
        'admin.reset' => array(
            'label' => 'adm_reset',
            'page' => '/back/reset',
            'level' => AdminAccess::ADMIN,
        ),
        'admin.credit' => array(
            'label' => 'adm_extcopy',
            'page' => '/back/credit',
            'level' => AdminAccess::ADMIN,
        ),
        'admin.chat' => array(
            'label' => 'adm_chat',
            'page' => '/back/chat',
            'level' => AdminAccess::ADMIN,
        ),
        'admin.roles' => array(
            'label' => 'adm_roles',
            'page' => '/back/roles',
            'level' => AdminAccess::ADMIN,
        ),
        'admin.modules' => array(
            'label' => 'adm_modules',
            'page' => '/back/modules',
            'level' => AdminAccess::ADMIN,
        ),
    );

    /** Libellés des groupes de permissions (clés de langue). */
    private const GROUP_LABELS = array(
        'admin' => 'acl_group_admin',
        'module' => 'acl_group_module',
    );

    /**
     * Catalogue complet : permission => libellé de langue, adresse et niveau.
     *
     * Les permissions des **modules** (`module.chat`…) viennent du catalogue
     * `App\Core\Modules` : une seule liste de modules, donc une seule source pour
     * leurs libellés, leurs pages et leurs permissions.
     *
     * @return array<string, array{label: string, page: string, level: int, group: string}>
     */
    public static function all(): array
    {
        $catalog = array();

        foreach (array_keys(self::CATALOG) as $permission) {
            $catalog[$permission] = self::entry($permission);
        }

        foreach (Modules::names() as $name) {
            foreach (Modules::permissions($name) as $permission) {
                $catalog[$permission] = self::entry($permission);
            }
        }

        return $catalog;
    }

    /**
     * Une entrée du catalogue, page d'administration ou module.
     *
     * Un module est ouvert au **niveau le plus bas** : c'est le comportement
     * d'avant l'ACL (tout le monde y a accès), et c'est au rôle de le restreindre
     * en décochant sa permission. Le placer plus haut priverait d'un coup les
     * opérateurs et les modérateurs du tchat, des notes ou du marchand.
     *
     * @return array{label: string, page: string, level: int, group: string}
     */
    private static function entry(string $permission): array
    {
        if (isset(self::CATALOG[$permission])) {
            return array(
                'label' => self::CATALOG[$permission]['label'],
                'page' => self::CATALOG[$permission]['page'],
                'level' => self::CATALOG[$permission]['level'],
                'group' => self::groupOf($permission),
            );
        }

        $name = Modules::ofPermission($permission);

        if ($name === null) {
            return array('label' => $permission, 'page' => '', 'level' => AdminAccess::ADMIN, 'group' => 'module');
        }

        return array(
            'label' => Modules::label($name),
            'page' => Modules::page($name),
            'level' => AdminAccess::OPERATOR,
            'group' => 'module',
        );
    }

    /**
     * Catalogue groupé : groupe => [permission => libellé de langue].
     *
     * `$filter` restreint aux permissions qu'un rôle porte déjà (`*` compris) :
     * c'est ce que la page des rôles affiche pour un rôle donné.
     *
     * @param array<int, string> $filter
     * @return array<string, array<string, string>>
     */
    public static function grouped(array $filter = array()): array
    {
        $groups = array();

        foreach (self::all() as $permission => $entry) {
            if ($filter !== array() && !in_array($permission, $filter, true)) {
                continue;
            }

            $groups[$entry['group']][$permission] = $entry['label'];
        }

        return $groups;
    }

    /** Groupes connus : nom technique => clé de langue du libellé. */
    public static function groupLabels(): array
    {
        return self::GROUP_LABELS;
    }

    /** Groupe d'une permission (`admin` ou `module`), d'après son préfixe. */
    public static function groupOf(string $permission): string
    {
        if (str_starts_with($permission, self::MODULE_PREFIX)) {
            return 'module';
        }

        return 'admin';
    }

    public static function exists(string $permission): bool
    {
        return $permission === self::ALL
            || isset(self::CATALOG[$permission])
            || Modules::ofPermission($permission) !== null;
    }

    /** Clé de langue du libellé d'une permission. */
    public static function label(string $permission): string
    {
        return self::entry($permission)['label'];
    }

    /** Adresse de la page que la permission ouvre. */
    public static function page(string $permission): string
    {
        return self::entry($permission)['page'];
    }

    /** Niveau historique (`AdminAccess`) associé à une permission. */
    public static function level(string $permission): int
    {
        return self::entry($permission)['level'];
    }

    /**
     * Permissions d'un rôle par défaut, pour un niveau donné : tout ce que le
     * niveau historique ouvrait, et rien de plus. Un administrateur reçoit tout
     * le catalogue (comme `authlevel = 3` aujourd'hui).
     *
     * @return array<int, string>
     */
    public static function defaultsFor(int $level): array
    {
        if ($level >= AdminAccess::ADMIN) {
            return array_keys(self::all());
        }

        $permissions = array();

        foreach (self::all() as $permission => $entry) {
            if ($entry['level'] <= $level) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * Le compte est-il le super administrateur ? Le compte d'installation l'est
     * par définition (son `id`), et un rôle qui porte `*` l'est aussi.
     */
    public static function isSuperAdmin(array $user): bool
    {
        return (int) ($user['id'] ?? 0) === self::SUPER_ADMIN_ID;
    }

    /**
     * L'acteur peut-il **gérer** ce rôle (renommer, cocher ses permissions, le
     * supprimer, le déplacer) ?
     *
     * Les rôles forment une hiérarchie (`roles.position`, plus grand = plus haut)
     * et un rôle ne gère que ceux placés **strictement en dessous de lui** : son
     * propre rôle ne se modifie pas — sinon un opérateur s'ajouterait « Rôles »
     * puis s'octroierait tout —, et les rôles au-dessus non plus. Un rôle qui porte
     * `*` (tous les droits, y compris les pages à venir) fait exception : rien ne
     * peut lui être refusé, donc il gère tout le monde, y compris lui-même.
     *
     * Le compte d'installation (`isSuperAdmin()`) est traité avant d'arriver ici.
     * Fonction pure : `AclService` lui passe les positions déjà lues.
     */
    public static function manages(int $actorPosition, int $targetPosition, bool $actorAll = false): bool
    {
        return $actorAll || $actorPosition > $targetPosition;
    }

    /**
     * Le rôle décrit par sa liste de permissions (`*` ou `a,b,c`) ouvre-t-il la
     * permission demandée ? Fonction pure : c'est la règle que `AclService`
     * applique après avoir lu le rôle du compte.
     */
    public static function granted(?string $permissions, string $permission): bool
    {
        $list = self::parse($permissions);

        return in_array(self::ALL, $list, true) || in_array($permission, $list, true);
    }

    /**
     * Liste de permissions stockée -> tableau nettoyé (une entrée par permission,
     * dans l'ordre du catalogue pour rester lisible).
     *
     * @return array<int, string>
     */
    public static function parse(?string $list): array
    {
        if ($list === null || trim($list) === '') {
            return array();
        }

        $parts = array_map('trim', explode(',', $list));
        $parts = array_values(array_unique(array_filter($parts, static fn (string $part): bool => $part !== '')));

        $known = array_keys(self::all());
        $ordered = array();

        foreach ($known as $permission) {
            if (in_array($permission, $parts, true)) {
                $ordered[] = $permission;
            }
        }

        // `*` (et toute permission inconnue, gardée pour ne rien perdre) en tête.
        foreach ($parts as $part) {
            if (!in_array($part, $ordered, true)) {
                array_unshift($ordered, $part);
            }
        }

        return $ordered;
    }

    /**
     * Tableau de permissions -> valeur stockée.
     *
     * @param array<int, string> $permissions
     */
    public static function serialize(array $permissions): string
    {
        $cleaned = array();

        foreach ($permissions as $permission) {
            $permission = trim((string) $permission);

            if ($permission !== '' && !in_array($permission, $cleaned, true)) {
                $cleaned[] = $permission;
            }
        }

        return implode(',', $cleaned);
    }
}
