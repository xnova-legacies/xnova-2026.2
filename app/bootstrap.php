<?php

define('APP_ROOT', dirname(__DIR__));

// Dépendances Composer (php-debugbar, workerman…). L'autoloader du jeu ne gère
// que le préfixe App\ : sans celui-ci, aucune classe de vendor/ n'est résolue.
// Le dossier est absent d'une installation sans `composer install` : on ne casse
// pas le jeu pour autant.
if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = APP_ROOT . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
        }

        return;
    }

    // Code livré par un module : `Modules\<Nom>\Core\…` vit dans son module, sous le
    // même découpage que `app/` (`Modules::classFile()` valide le chemin).
    if (!str_starts_with($class, \App\Core\Modules::NAMESPACE_PREFIX)) {
        return;
    }

    $file = \App\Core\Modules::classFile($class);

    if ($file !== null) {
        require_once $file;
    }
});
