<?php

if (!function_exists('xnova_load_env')) {
    /**
     * Lit `configs/.env` puis `configs/.env.<APP_ENV>` et renvoie le tableau des valeurs.
     * Le fichier peut etre inclus par plusieurs points d'entree (index.php via
     * la barre de debug, db/mysql.php, install/index.php) : les declarations sont
     * protegees par function_exists() pour rester idempotent.
     */
    function xnova_load_env($rootPath)
    {
        static $values;

        if ($values !== NULL) {
            return $values;
        }

        $values = array();
        $configPath = $rootPath . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR;
        $baseFilename = $configPath . '.env';

        $loadFile = function ($filename) use (&$values) {
            if (!is_readable($filename)) {
                return;
            }

            foreach (file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }

                $separator = strpos($line, '=');
                if ($separator === false) {
                    continue;
                }

                $key = trim(substr($line, 0, $separator));
                $value = trim(substr($line, $separator + 1));
                if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                    $value = trim($value, "\"'");
                }

                $values[$key] = $value;
            }
        };

        $loadFile($baseFilename);
        $environment = getenv('APP_ENV') ?: ($values['APP_ENV'] ?? 'local');
        if ($environment !== '') {
            $loadFile($configPath . '.env.' . $environment);
        }

        return $values;
    }
}

if (!function_exists('xnova_env')) {
    /** Valeur d'environnement, avec valeur par defaut. */
    function xnova_env($key, $default = '')
    {
        $values = xnova_load_env(dirname(__DIR__));

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }
}
