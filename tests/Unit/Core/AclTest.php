<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Acl;
use App\Core\AdminAccess;
use App\Core\Modules;
use App\Services\AclService;
use PHPUnit\Framework\TestCase;

/**
 * Catalogue des permissions et règles pures de l'ACL.
 *
 * `AclService` lit les rôles en base (il n'est donc pas testé ici) ; ce qui décide
 * vraiment — quelles permissions existent, ce qu'un rôle accorde, ce que le compte
 * d'installation peut — est pur et vit dans `App\Core\Acl`.
 */
final class AclTest extends TestCase
{
    public function testTheCatalogDescribesPagesWithTheirHistoricalLevel(): void
    {
        $catalog = Acl::all();

        self::assertNotSame(array(), $catalog);

        foreach ($catalog as $permission => $entry) {
            self::assertNotSame('', $entry['label'], $permission . ' doit nommer une clé de libellé.');
            self::assertContains($entry['level'], array(
                AdminAccess::OPERATOR,
                AdminAccess::MODERATOR,
                AdminAccess::ADMIN,
            ), $permission . ' hérite d\'un niveau historique connu.');

            if ($entry['group'] === 'module') {
                // Une permission de module vient d'un manifeste : elle porte le
                // préfixe réservé. Quand elle mène à une page, c'est celle du **jeu**
                // (`/game/...`) ou celle du **panneau** (`/back/...`), selon ce que le
                // module apporte — mais un module peut n'ouvrir **aucune** page : celui
                // qui n'ajoute qu'un vaisseau et une mission (Extracteurs) n'annonce
                // qu'une permission, et elle sert à l'éteindre.
                self::assertStringStartsWith('module.', $permission);
                self::assertTrue(
                    $entry['page'] === '' || (bool) preg_match('#^/(game|back)/#', $entry['page']),
                    $permission . ' annonce une page absolue, ou aucune.'
                );

                continue;
            }

            self::assertStringStartsWith('admin.', $permission);
            self::assertStringStartsWith('/back/', $entry['page'], $permission . ' doit annoncer sa page.');
            self::assertSame('admin', $entry['group']);
        }
    }

    public function testTheCatalogCoversEveryPageOfThePanel(): void
    {
        // Les pages du panneau, une par contrôleur : si l'une d'elles ajoute une
        // permission sans la déclarer ici, elle ne sera cochable nulle part.
        foreach (
            array(
                'admin.overview',
                'admin.menu',
                'admin.actions',
                'admin.player',
                'admin.fleets',
                'admin.queue',
                'admin.notes',
                'admin.message-all',
                'admin.userlist',
                'admin.multi',
                'admin.messages',
                'admin.settings',
                'admin.reset',
                'admin.credit',
                'admin.chat',
                'admin.roles',
            ) as $permission
        ) {
            self::assertTrue(Acl::exists($permission), $permission . ' doit exister au catalogue.');
        }
    }

    /**
     * Le groupe « Modules » existe même sans module déposé : c'est le **manifeste** qui le
     * peuple, module par module. La version livrée n'en dépose aucun, donc le catalogue ne
     * porte que les pages du panneau — et la boucle se réarme dès qu'un module revient.
     */
    public function testTheModuleGroupComesFromTheManifests(): void
    {
        self::assertSame('module', Acl::groupOf('module.chat'));
        self::assertSame('admin', Acl::groupOf('admin.overview'));
        self::assertArrayHasKey('module', Acl::groupLabels());

        // Le groupe se peuple à partir des **manifestes** : avec des modules déposés, chaque
        // permission `module.<nom>` est au catalogue ; sans module, la boucle est vide et le
        // groupe existe quand même (c'est sa page qui manque).
        foreach (Modules::names() as $name) {
            $permission = Modules::permission($name);

            self::assertTrue(Acl::exists($permission), $permission . ' doit être au catalogue.');
            self::assertSame('module', Acl::groupOf($permission));
            self::assertSame('module', Acl::all()[$permission]['group']);
            self::assertSame(Modules::page($name), Acl::page($permission));
            self::assertSame(Modules::label($name), Acl::label($permission));
        }
    }

    public function testTheSuperAdminIsTheInstallationAccount(): void
    {
        self::assertTrue(Acl::isSuperAdmin(array('id' => Acl::SUPER_ADMIN_ID)));
        self::assertFalse(Acl::isSuperAdmin(array('id' => 2)));
        self::assertFalse(Acl::isSuperAdmin(array()));
    }

    public function testTheAllPermissionGrantsEverythingIncludingPagesToCome(): void
    {
        self::assertTrue(Acl::granted(Acl::ALL, 'admin.settings'));
        self::assertTrue(Acl::granted('admin.overview,admin.settings', 'admin.settings'));
        self::assertFalse(Acl::granted('admin.overview', 'admin.settings'));
        self::assertFalse(Acl::granted('', 'admin.overview'));
        self::assertFalse(Acl::granted(null, 'admin.overview'));

        // Une page qui n'existe pas encore : seul `*` l'ouvre.
        self::assertTrue(Acl::granted(Acl::ALL, 'module.chat'));
        self::assertFalse(Acl::granted('admin.overview', 'module.chat'));
    }

    public function testTheStoredListIsCleanedAndOrdered(): void
    {
        self::assertSame(
            array('admin.overview', 'admin.settings'),
            Acl::parse(' admin.settings , admin.overview , admin.overview ')
        );
        self::assertSame(array(), Acl::parse('   '));
        self::assertSame(array(), Acl::parse(null));

        // Une valeur inconnue est gardée (rien ne se perd) mais rangée après.
        self::assertSame(array('inconnue', 'admin.overview'), Acl::parse('inconnue,admin.overview'));

        self::assertSame('admin.overview,admin.settings', Acl::serialize(array('admin.overview', 'admin.settings', 'admin.overview')));
        self::assertSame('', Acl::serialize(array('', ' ')));
    }

    public function testTheDefaultRolesFollowTheHistoricalLevels(): void
    {
        $operator = Acl::defaultsFor(AdminAccess::OPERATOR);
        $moderator = Acl::defaultsFor(AdminAccess::MODERATOR);
        $admin = Acl::defaultsFor(AdminAccess::ADMIN);

        // Chaque rôle reçoit ce que son niveau ouvrait : un opérateur ne voit pas
        // les réglages du jeu, un modérateur non plus, et l'administrateur reçoit
        // tout le catalogue (comme `authlevel = 3` avant l'ACL).
        self::assertContains('admin.overview', $operator);
        self::assertNotContains('admin.settings', $operator);
        self::assertNotContains('admin.userlist', $operator);

        self::assertContains('admin.userlist', $moderator);
        self::assertNotContains('admin.settings', $moderator);

        self::assertSame(array_keys(Acl::all()), $admin);
        self::assertContains('admin.roles', $admin);
    }

    public function testTheGroupedCatalogKeepsTheLabels(): void
    {
        $groups = Acl::grouped();
        $admin = $groups['admin'] ?? array();

        self::assertSame(Acl::label('admin.overview'), $admin['admin.overview']);
        self::assertStringStartsWith('adm_', Acl::label('admin.overview'));
        // Une permission inconnue garde son nom pour libellé (jamais de vide).
        self::assertSame('inconnue', Acl::label('inconnue'));
        self::assertSame('', Acl::page('inconnue'));
    }

    /**
     * Un rôle ne gère que ceux placés **strictement** sous lui : ni le sien (il
     * s'octroierait des droits), ni ceux au-dessus.
     */
    public function testARoleManagesOnlyTheRolesBelowIt(): void
    {
        self::assertTrue(Acl::manages(30, 20), 'Un administrateur gère un modérateur.');
        self::assertFalse(Acl::manages(20, 30), 'Un modérateur ne gère pas un administrateur.');
        self::assertFalse(Acl::manages(20, 20), 'Le même rôle ne se modifie pas.');
        self::assertTrue(Acl::manages(0, -1), 'Un rôle composé en bas reste gérable par le premier rôle venu.');
        self::assertFalse(Acl::manages(0, 0), 'Sans rôle, on ne gère rien.');
    }

    /**
     * Un rôle qui porte « tous les droits » gère tout le monde, lui-même compris :
     * il n'a plus rien à s'octroyer, donc la règle anti-escalade ne le concerne pas.
     */
    public function testTheAllPermissionsRoleManagesEveryone(): void
    {
        self::assertTrue(Acl::manages(10, 40, true));
        self::assertTrue(Acl::manages(10, 10, true));
        self::assertTrue(Acl::manages(0, 40, true));
    }
}
