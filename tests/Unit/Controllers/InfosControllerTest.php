<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\Game\InfosController;
use PHPUnit\Framework\TestCase;

/**
 * Sans module, la fiche d'information n'applique aucun bonus d'officier.
 *
 * La fiche est un point de surcharge : le module `officier` dérive ce contrôleur (même nom
 * court, même couche, rien à déclarer) et remplit `productionFactor()` — son cas est couvert
 * par `modules/officier/tests/InfosControllerTest.php`. Ici, le Coeur de l'application doit
 * rendre la production brute de la formule, exactement comme avant les officiers.
 *
 * Le contrôleur est construit **sans son constructeur** (qui interroge la base par son
 * dépôt) : la méthode testée est pure et ne lit que le tableau du compte.
 */
final class InfosControllerTest extends TestCase
{
    /** @param array<string, mixed> $user */
    private static function factor(array $user, string $resource): float
    {
        $controller = (new \ReflectionClass(InfosController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(InfosController::class, 'productionFactor');
        $method->setAccessible(true);

        return (float) $method->invoke($controller, $user, $resource);
    }

    public function testTheCoreAppliesNoOfficerBonusToTheProductionPreview(): void
    {
        $user = array('rpg_geologue' => 5, 'rpg_ingenieur' => 5);

        self::assertSame(1.0, self::factor($user, 'metal'));
        self::assertSame(1.0, self::factor($user, 'crystal'));
        self::assertSame(1.0, self::factor($user, 'deuterium'));
        self::assertSame(1.0, self::factor($user, 'energy'));
    }
}
