<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Accès au panneau d'administration.
 *
 * Les niveaux étaient testés en clair dans chaque page (`$user['authlevel'] >= N`),
 * avec des seuils dispersés et deux listes de rôles contradictoires dans les
 * fichiers de langue. C'est désormais la seule référence : chaque page admin
 * annonce le niveau qu'elle exige, et `can()` décide.
 */
final class AdminAccess
{
    /** Niveau « joueur » : aucune page d'administration. */
    public const PLAYER = 0;

    /** Opérateur : consultation et modération courante. */
    public const OPERATOR = 1;

    /** Modérateur : gestion des comptes et des planètes. */
    public const MODERATOR = 2;

    /** Administrateur : réglages du jeu, outils, remises à zéro. */
    public const ADMIN = 3;

    /** Rôles, du plus bas au plus haut (mêmes libellés que le menu admin). */
    public const ROLES = array(
        self::PLAYER => 'Joueur',
        self::OPERATOR => 'Opérateur',
        self::MODERATOR => 'Modérateur',
        self::ADMIN => 'Administrateur',
    );

    /** Le niveau donné peut-il ouvrir une page qui exige `$required` ? */
    public static function can(int $authlevel, int $required): bool
    {
        return $authlevel >= $required && $authlevel >= self::OPERATOR;
    }

    /** Nom du rôle d'un niveau (pour l'affichage). */
    public static function role(int $authlevel): string
    {
        return self::ROLES[$authlevel] ?? self::ROLES[self::PLAYER];
    }
}
