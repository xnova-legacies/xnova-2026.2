<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Ce que le serveur doit avoir pour que l'installation aboutisse.
 *
 * Une installation Docker apporte tout cela (`Dockerfile`, `composer install`), mais rien
 * n'oblige à passer par Docker : l'installateur vérifie donc ce qu'il **utilise** avant de
 * demander quoi que ce soit d'autre, et s'arrête si un prérequis manque. Mieux vaut le dire
 * là que de laisser l'étape suivante échouer sur un `config.php` non inscriptible.
 *
 * Les clés rendues (`ins_chk_php`, `ins_chk_ext`, …) sont des **clés de langue** : la page
 * les traduit, la classe ne connaît aucun texte.
 */
final class Requirements
{
    /** Plancher PHP : celui déclaré dans `composer.json` (8.3). */
    public const MINIMUM_PHP = 80300;

    /**
     * Extensions sans lesquelles le jeu ne tourne pas.
     *
     * `pdo` et `pdo_mysql` portent toute la couche base, `mbstring` sert à valider l'UTF-8
     * des sources, et `phar` lit et écrit les archives tar de la mise à jour.
     */
    public const EXTENSIONS = array('pdo', 'pdo_mysql', 'mbstring', 'phar');

    /** Dossiers que l'installateur (ou la mise à jour) doit pouvoir écrire. */
    public const DIRECTORIES = array('configs', 'modules', 'backups');

    /**
     * État de chaque prérequis, dans l'ordre d'affichage.
     *
     * @return list<array{key: string, ok: bool, detail: string}>
     */
    public function all(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $requirements = array(
            array(
                'key' => 'ins_chk_php',
                'ok' => PHP_VERSION_ID >= self::MINIMUM_PHP,
                'detail' => PHP_VERSION,
            ),
            array(
                'key' => 'ins_chk_ext',
                'ok' => self::missingExtensions() === array(),
                'detail' => implode(', ', self::missingExtensions() === array() ? self::EXTENSIONS : self::missingExtensions()),
            ),
            array(
                'key' => 'ins_chk_vendor',
                'ok' => is_file($root . '/vendor/autoload.php'),
                'detail' => 'vendor/autoload.php',
            ),
        );

        foreach (self::DIRECTORIES as $directory) {
            $requirements[] = array(
                'key' => 'ins_chk_dir',
                'ok' => self::writable($root . '/' . $directory),
                'detail' => $directory . '/',
            );
        }

        return $requirements;
    }

    /** Extensions manquantes (liste vide si tout est là). */
    public static function missingExtensions(): array
    {
        $missing = array();

        foreach (self::EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        return $missing;
    }

    /**
     * Tous les prérequis sont-ils satisfaits ?
     *
     * @param list<array{key: string, ok: bool, detail: string}> $requirements
     */
    public static function satisfied(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            if (!$requirement['ok']) {
                return false;
            }
        }

        return true;
    }

    /** Un dossier inscriptible — créé s'il n'existe pas encore (`backups/`). */
    private static function writable(string $path): bool
    {
        if (!is_dir($path)) {
            return @mkdir($path, 0775, true) || is_dir($path);
        }

        return is_writable($path);
    }
}
