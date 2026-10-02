<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\AdminAccess;
use PHPUnit\Framework\TestCase;

/**
 * Les niveaux d'accès de l'administration étaient testés en clair dans chaque
 * page, avec des seuils dispersés et deux listes de rôles contradictoires.
 * `AdminAccess` est désormais la seule référence.
 */
final class AdminAccessTest extends TestCase
{
    public function testTheLevelsFollowTheAdminMenu(): void
    {
        self::assertSame('Joueur', AdminAccess::role(0));
        self::assertSame('Opérateur', AdminAccess::role(1));
        self::assertSame('Modérateur', AdminAccess::role(2));
        self::assertSame('Administrateur', AdminAccess::role(3));
        // Un niveau inconnu retombe sur « Joueur » (aucun droit).
        self::assertSame('Joueur', AdminAccess::role(9));
    }

    public function testTheRequiredLevelGatesThePage(): void
    {
        // Page d'opérateur : ouverte à partir du niveau 1.
        self::assertTrue(AdminAccess::can(AdminAccess::OPERATOR, AdminAccess::OPERATOR));
        self::assertTrue(AdminAccess::can(AdminAccess::MODERATOR, AdminAccess::OPERATOR));
        self::assertTrue(AdminAccess::can(AdminAccess::ADMIN, AdminAccess::OPERATOR));
        self::assertFalse(AdminAccess::can(AdminAccess::PLAYER, AdminAccess::OPERATOR));

        // Page d'administrateur : réservée au niveau 3.
        self::assertTrue(AdminAccess::can(AdminAccess::ADMIN, AdminAccess::ADMIN));
        self::assertFalse(AdminAccess::can(AdminAccess::MODERATOR, AdminAccess::ADMIN));
    }

    public function testAPlayerNeverOpensAnAdminPage(): void
    {
        // Même une page qui n'exigerait rien reste fermée à un joueur.
        self::assertFalse(AdminAccess::can(AdminAccess::PLAYER, AdminAccess::PLAYER));
    }
}
