<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

/**
 * Variables d'environnement : la règle unique du dépôt.
 *
 * `Env::get()` charge `includes/env.php` au besoin puis interroge `xnova_env()`. Le test
 * vérifie la réponse visible de l'extérieur — la valeur par défaut quand la variable
 * n'existe pas — sans dépendre de ce que porte la machine qui l'exécute.
 */
final class EnvTest extends TestCase
{
    public function testFallsBackToTheDefaultWhenTheVariableIsMissing(): void
    {
        self::assertSame('defaut', Env::get('XNOVA_VARIABLE_ABSENTE_DU_TEST', 'defaut'));
        self::assertSame('', Env::get('XNOVA_VARIABLE_ABSENTE_DU_TEST'));
    }

    /** Le fichier `includes/env.php` doit être chargé sans que l'appelant s'en occupe. */
    public function testTheLegacyReaderIsAvailableAfterTheCall(): void
    {
        Env::get('XNOVA_VARIABLE_ABSENTE_DU_TEST');

        self::assertTrue(function_exists('xnova_env'));
    }
}
