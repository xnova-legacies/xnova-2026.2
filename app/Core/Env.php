<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Variables d'environnement lues à l'exécution (`configs/.env.<APP_ENV>`).
 *
 * Une seule règle, un seul endroit : `xnova_env()` lit les fichiers, et il vit dans
 * `includes/env.php` — un fichier que tous les points d'entrée ne chargent pas (une route
 * d'API, un test, un script de `db/`). Charger ce fichier au besoin était recopié chez
 * chaque lecteur (`ConfigDefaults`, les modules qui lisent leurs réglages) : c'est
 * désormais ici, et nulle part ailleurs. `tools/audit-doublons.php` veille.
 */
final class Env
{
    /** Valeur d'une variable, ou `$default` quand elle n'est pas définie. */
    public static function get(string $key, string $default = ''): string
    {
        if (!function_exists('xnova_env')) {
            $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);

            include_once $root . '/includes/env.php';
        }

        return (string) xnova_env($key, $default);
    }
}
