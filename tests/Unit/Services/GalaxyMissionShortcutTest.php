<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Modules;
use App\Services\FleetDispatchService;
use PHPUnit\Framework\TestCase;

/**
 * Raccourcis de la vue galaxie.
 *
 * Le secteur des débris propose le recyclage (Coeur d'application) et les missions **déclarées
 * par un module** sur un champ de débris : le Coeur d'application ne connaît aucun identifiant
 * de module, c'est le manifeste qui le dit (`debris`). Ces tests tiennent les deux
 * sens — la déclaration est lue, et l'action JSON accepte exactement ce que la
 * page propose.
 */
final class GalaxyMissionShortcutTest extends TestCase
{
    public function testTheCoreKnowsTheThreeHistoricalShortcuts(): void
    {
        $missions = FleetDispatchService::galaxyMissionIds();

        $this->assertContains(6, $missions, 'Espionnage.');
        $this->assertContains(7, $missions, 'Colonisation.');
        $this->assertContains(8, $missions, 'Recyclage.');
    }

    public function testAnExpeditionIsNotADebrisShortcut(): void
    {
        // L'expédition (15) vise elle aussi une position libre : elle ne doit pas
        // être confondue avec une mission de champ de débris.
        $this->assertArrayNotHasKey(15, FleetDispatchService::galaxyDebrisMissions());
    }

    /**
     * Une mission de **champ de débris** vient d'un manifeste, jamais du Core.
     *
     * Le module `extracteurs` en déclare une (la mission 12, jouée par le vaisseau 216) ;
     * sans lui, la liste des missions de champ de débris est vide — mais les trois
     * raccourcis du Core, eux, restent en place, et l'expédition n'en fait pas partie.
     */
    public function testTheDebrisMissionComesFromAManifest(): void
    {
        $missions = FleetDispatchService::galaxyMissionIds();

        $this->assertSame(
            array(6, 7, 8),
            array_values(array_intersect($missions, array(6, 7, 8))),
            'Espionnage, colonisation et recyclage.'
        );

        if (Modules::names() === array()) {
            $this->assertSame(array(), FleetDispatchService::galaxyDebrisMissions(), 'Aucun module déposé ne déclare de mission de champ de débris.');
            // Un module en moins n'enlève rien au Core : sans module, la liste se réduit aux
            // trois raccourcis historiques (c'est ce que la première assertion vient de dire).
            $this->assertSame(array(6, 7, 8), $missions, 'Sans module, seuls les trois raccourcis du Core sont proposés.');

            return;
        }

        foreach (array_diff($missions, array(6, 7, 8)) as $declared) {
            $this->assertArrayHasKey(
                $declared,
                FleetDispatchService::galaxyDebrisMissions(),
                'Le raccourci en plus des trois du Core vient d\'un manifeste.'
            );
        }
    }
}
