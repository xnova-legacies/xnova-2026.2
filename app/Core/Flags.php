<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Drapeaux d'un enregistrement : un entier, un bit par état.
 *
 * Les tables `messages`, `planets`, `lunas`, `users` (migration 014) et `notes`
 * (migration 015) portent une colonne `flags` dont chaque bit décrit un état.
 * Ajouter un état ne change donc pas le schéma, et plusieurs états vivent côte à
 * côte sans se gêner.
 *
 *   DELETED  suppression **logique** : la ligne reste en base (rien n'est perdu)
 *            mais ne doit plus se montrer ni produire d'effet ;
 *   ENABLED  élément actif. Un enregistrement neuf vaut `Flags::DEFAULT`.
 *
 * Un enregistrement supprimé logiquement garde ses autres drapeaux : c'est
 * `DELETED` qui prime. Le test des traitements ordinaires est `usable()`
 * (actif **et** non supprimé), `isDeleted()` / `isEnabled()` servant à l'affichage
 * ou à un filtre précis.
 *
 * Dans une requête, le drapeau est un **paramètre** — jamais une chaîne concaténée
 * (les dépôts sont en requêtes préparées) :
 *
 *     // Les lignes non supprimées :
 *     WHERE (`flags` & ?) = 0      -- paramètre : Flags::DELETED
 *     // Les lignes actives :
 *     WHERE (`flags` & ?) = ?      -- paramètres : Flags::ENABLED, Flags::ENABLED
 *
 * À la lecture, la valeur sert de nouvelle valeur : `Flags::set($ligne['flags'], Flags::DELETED)`.
 *
 * La suppression d'un **compte** suit la même règle depuis la migration 016 :
 * `Flags::DELETED` en est le seul état, et `deleted_time` ne garde que la date
 * (0 = jamais supprimé). `Authenticator`, `UserRepository::softDelete()/restore()`,
 * `BotRepository::all()`, `StatsRepository::universeCounts()` et la fiche joueur
 * du panneau lisent tous le drapeau.
 */
final class Flags
{
    /** Suppression logique : la ligne existe encore, mais ne se montre plus. */
    public const DELETED = 1;

    /** Élément actif. */
    public const ENABLED = 2;

    /** Valeur d'un enregistrement neuf — celle de la colonne `flags` par défaut. */
    public const DEFAULT = self::ENABLED;

    /** Drapeaux connus, dans l'ordre de déclaration : nom => valeur. */
    public const NAMES = array(
        'DELETED' => self::DELETED,
        'ENABLED' => self::ENABLED,
    );

    /**
     * Valeur de drapeaux ramenée à un entier : une colonne entière arrive en texte
     * de la base, et une valeur illisible vaut « aucun drapeau ».
     */
    public static function value(mixed $flags): int
    {
        return is_numeric($flags) ? (int) $flags : 0;
    }

    /**
     * Le drapeau est-il posé ?
     *
     * Un `$flag` qui combine plusieurs bits exige qu'ils soient **tous** posés.
     */
    public static function has(mixed $flags, int $flag): bool
    {
        return (self::value($flags) & $flag) === $flag;
    }

    /** Pose (`$on` vrai) ou retire un drapeau, sans toucher aux autres. */
    public static function set(mixed $flags, int $flag, bool $on = true): int
    {
        $value = self::value($flags);

        return $on ? ($value | $flag) : ($value & ~$flag);
    }

    /** Inverse un drapeau. */
    public static function toggle(mixed $flags, int $flag): int
    {
        return self::value($flags) ^ $flag;
    }

    /** Enregistrement supprimé logiquement. */
    public static function isDeleted(mixed $flags): bool
    {
        return self::has($flags, self::DELETED);
    }

    /** Enregistrement dont le drapeau `ENABLED` est posé. */
    public static function isEnabled(mixed $flags): bool
    {
        return self::has($flags, self::ENABLED);
    }

    /** Enregistrement utilisable : actif et non supprimé logiquement. */
    public static function usable(mixed $flags): bool
    {
        return self::isEnabled($flags) && !self::isDeleted($flags);
    }

    /**
     * Noms des drapeaux posés, dans l'ordre de déclaration — pour un journal ou un
     * affichage. Les bits inconnus sont ignorés (une version plus récente peut en
     * avoir laissé).
     *
     * @return list<string>
     */
    public static function names(mixed $flags): array
    {
        $names = array();

        foreach (self::NAMES as $name => $value) {
            if (self::has($flags, $value)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
