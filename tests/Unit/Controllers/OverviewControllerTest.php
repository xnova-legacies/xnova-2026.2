<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\Game\OverviewController;
use PHPUnit\Framework\TestCase;

/**
 * Sans module, la vue générale n'affiche aucun bloc de progression.
 *
 * La progression (niveaux de mineur et de raideur, expérience, alertes de montée) appartient au
 * module `officier` : sa page surcharge ce contrôleur (même nom court, même couche, rien à
 * déclarer) et remplit `progression()` — son cas est couvert par
 * `modules/officier/tests/OverviewControllerTest.php`. Ici, les deux emplacements du gabarit
 * (`{progression_alerts}`, `{progression_block}`) doivent rester vides, et la page est exactement
 * celle du jeu.
 *
 * Le contrôleur est construit **sans son constructeur** (qui interroge la base par ses dépôts) :
 * la méthode testée est pure.
 */
final class OverviewControllerTest extends TestCase
{
    public function testTheCoreRendersNoProgressionBlock(): void
    {
        $controller = (new \ReflectionClass(OverviewController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(OverviewController::class, 'progression');
        $method->setAccessible(true);

        self::assertSame(
            array('alerts' => '', 'block' => ''),
            $method->invoke($controller, array('lvl_minier' => 4, 'xpminier' => 999999), array())
        );
    }
}
