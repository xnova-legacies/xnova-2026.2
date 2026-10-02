<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PlayerAdminService;
use PHPUnit\Framework\TestCase;

/**
 * Gestion d'un joueur : les règles pures (bornes de saisie, retrait de
 * vaisseaux, éléments modifiables). Aucun accès à MySQL.
 */
final class PlayerAdminServiceTest extends TestCase
{
    public function testQuantityIsBoundedOnBothSides(): void
    {
        self::assertSame(5, PlayerAdminService::quantity('5'));
        self::assertSame(0, PlayerAdminService::quantity('-3'));
        self::assertSame(0, PlayerAdminService::quantity('abc'));
        self::assertSame(0, PlayerAdminService::quantity(''));
        self::assertSame(7, PlayerAdminService::quantity(' 7 '));
        self::assertSame(10, PlayerAdminService::quantity('999', 10), 'Le plafond passé borne la saisie.');
    }

    public function testAmountKeepsItsSign(): void
    {
        self::assertSame(1000, PlayerAdminService::amount('1000'));
        self::assertSame(-500, PlayerAdminService::amount('-500'));
        self::assertSame(0, PlayerAdminService::amount('pas un nombre'));
    }

    public function testBoundedNeverRemovesMoreThanTheStock(): void
    {
        // On ne retire jamais plus qu'il n'y a : les colonnes sont non signées.
        self::assertSame(-100, PlayerAdminService::bounded(-100, 250.5));
        self::assertSame(-250, PlayerAdminService::bounded(-1000, 250.5));
        self::assertSame(0, PlayerAdminService::bounded(-100, 0.0));
        self::assertSame(100, PlayerAdminService::bounded(100, 0.0), 'Un ajout passe toujours.');
    }

    public function testDiameterIsOnlyBoundedUpwards(): void
    {
        // Zéro (ou une saisie illisible) veut dire « on ne touche pas ».
        self::assertSame(0, PlayerAdminService::diameter('0'));
        self::assertSame(0, PlayerAdminService::diameter('-20'));
        self::assertSame(0, PlayerAdminService::diameter('abc'));
        self::assertSame(0, PlayerAdminService::diameter(null));

        // Un diamètre du jeu (dix à vingt mille) passe tel quel : seule une
        // saisie absurde est ramenée au plafond.
        self::assertSame(22950, PlayerAdminService::diameter('22950'));
        self::assertSame(PlayerAdminService::MAX_DIAMETER, PlayerAdminService::diameter('999999999'));
    }

    public function testRemoveUnitsDropsEmptyUnitsAndTotalsTheRest(): void
    {
        $after = PlayerAdminService::removeUnits(array(202 => 10, 204 => 1), array(202 => 3));

        self::assertSame('202,7;204,1;', $after['array']);
        self::assertSame(8, $after['amount']);
        self::assertFalse($after['empty']);

        // Retirer plus que la flotte n'emporte vide l'unité, pas la flotte.
        $partial = PlayerAdminService::removeUnits(array(202 => 2, 204 => 1), array(202 => 5));

        self::assertSame('204,1;', $partial['array']);
        self::assertSame(1, $partial['amount']);
        self::assertFalse($partial['empty']);

        // Une flotte vidée de toutes ses unités disparaît.
        $empty = PlayerAdminService::removeUnits(array(202 => 2), array(202 => 2));

        self::assertSame('', $empty['array']);
        self::assertSame(0, $empty['amount']);
        self::assertTrue($empty['empty']);

        // Une unité absente est ignorée.
        self::assertSame('202,2;', PlayerAdminService::removeUnits(array(202 => 2), array(204 => 9))['array']);
    }

    public function testCoordinateReadsOnlyPositiveNumbers(): void
    {
        self::assertSame(7, PlayerAdminService::coordinate('7'));
        self::assertSame(12, PlayerAdminService::coordinate(' 12 '));
        self::assertSame(0, PlayerAdminService::coordinate('0'));
        self::assertSame(0, PlayerAdminService::coordinate('-4'));
        self::assertSame(0, PlayerAdminService::coordinate('abc'));
        self::assertSame(0, PlayerAdminService::coordinate(null));
    }

    public function testInWorldKeepsTheBoundsOfTheUniverse(): void
    {
        // 9 galaxies, 499 systèmes, 15 positions.
        self::assertTrue(PlayerAdminService::inWorld(1, 1, 1, 9, 499, 15));
        self::assertTrue(PlayerAdminService::inWorld(9, 499, 15, 9, 499, 15));
        self::assertFalse(PlayerAdminService::inWorld(10, 1, 1, 9, 499, 15));
        self::assertFalse(PlayerAdminService::inWorld(1, 500, 1, 9, 499, 15));
        self::assertFalse(PlayerAdminService::inWorld(1, 1, 16, 9, 499, 15));
        self::assertFalse(PlayerAdminService::inWorld(0, 0, 0, 9, 499, 15), 'Une saisie vide ne vise aucune position.');
    }

    public function testParkedFleetOnlyCountsAFleetThatIsThere(): void
    {
        // Mission 5 arrivée (stationnement chez un allié) : les vaisseaux sont posés là.
        self::assertTrue(PlayerAdminService::parkedFleet(array(array('fleet_mess' => 2, 'fleet_mission' => 5))));
        // Mission 4 arrivée (transfert) : idem.
        self::assertTrue(PlayerAdminService::parkedFleet(array(array('fleet_mess' => 2, 'fleet_mission' => 4))));

        // En vol, stationnement ou non : la flotte n'est pas sur place.
        self::assertFalse(PlayerAdminService::parkedFleet(array(array('fleet_mess' => 0, 'fleet_mission' => 5))));
        self::assertFalse(PlayerAdminService::parkedFleet(array(array('fleet_mess' => 1, 'fleet_mission' => 4))));
        // Une flotte en orbite (mission 10) n'occupe pas la position : elle suit la planète.
        self::assertFalse(PlayerAdminService::parkedFleet(array(array('fleet_mess' => 0, 'fleet_mission' => 10))));
        self::assertFalse(PlayerAdminService::parkedFleet(array()));
    }

    public function testIncomingAttackCountsOnlyHostileAttackRuns(): void
    {
        $attack = array('fleet_owner' => 9, 'fleet_mission' => 1, 'fleet_mess' => 0);
        $acs = array('fleet_owner' => 9, 'fleet_mission' => 2, 'fleet_mess' => 0);
        $destroy = array('fleet_owner' => 9, 'fleet_mission' => 9, 'fleet_mess' => 0);

        self::assertTrue(PlayerAdminService::incomingAttack(array($attack), array(), 4));
        self::assertTrue(PlayerAdminService::incomingAttack(array($acs), array(), 4));
        self::assertTrue(PlayerAdminService::incomingAttack(array($destroy), array(), 4));
        self::assertTrue(PlayerAdminService::incomingAttack(array(), array(array('time' => 1)), 4), 'Un missile en vol est une attaque.');

        // Sa propre flotte ne s'attaque pas.
        self::assertFalse(PlayerAdminService::incomingAttack(array($attack), array(), 9));
        // Une flotte déjà arrivée (retour ou stationnement) n'est plus une attaque.
        self::assertFalse(PlayerAdminService::incomingAttack(array(array('fleet_owner' => 9, 'fleet_mission' => 1, 'fleet_mess' => 2)), array(), 4));
        // Le transport d'un voisin, son espionnage ou sa colonisation ne bloquent pas.
        self::assertFalse(PlayerAdminService::incomingAttack(array(array('fleet_owner' => 9, 'fleet_mission' => 3, 'fleet_mess' => 0)), array(), 4));
        self::assertFalse(PlayerAdminService::incomingAttack(array(array('fleet_owner' => 9, 'fleet_mission' => 6, 'fleet_mess' => 0)), array(), 4));
        self::assertFalse(PlayerAdminService::incomingAttack(array(), array(), 4));
    }

    public function testMoveRefusalFollowsTheOrderOfReasons(): void
    {
        $ground = array(
            'same' => false,
            'in_world' => true,
            'occupied' => false,
            'owner' => 4,
            'fleets' => array(),
            'missiles' => array(),
        );

        self::assertSame(PlayerAdminService::MOVE_OK, PlayerAdminService::moveRefusal($ground));
        self::assertSame('same', PlayerAdminService::moveRefusal(array('same' => true) + $ground));
        self::assertSame('bounds', PlayerAdminService::moveRefusal(array('in_world' => false) + $ground));
        self::assertSame('occupied', PlayerAdminService::moveRefusal(array('occupied' => true) + $ground));
        self::assertSame(
            'attack',
            PlayerAdminService::moveRefusal(array('fleets' => array(array('fleet_owner' => 9, 'fleet_mission' => 1, 'fleet_mess' => 0))) + $ground)
        );
        self::assertSame(
            'parked',
            PlayerAdminService::moveRefusal(array('fleets' => array(array('fleet_owner' => 4, 'fleet_mission' => 5, 'fleet_mess' => 2))) + $ground)
        );

        // L'ordre compte : une position hors de l'univers se refuse avant d'aller
        // regarder ce qui s'y trouve, et l'attaque passe avant la flotte posée.
        self::assertSame(
            'bounds',
            PlayerAdminService::moveRefusal(array('in_world' => false, 'occupied' => true) + $ground)
        );
        self::assertSame(
            'attack',
            PlayerAdminService::moveRefusal(array(
                'fleets' => array(
                    array('fleet_owner' => 4, 'fleet_mission' => 5, 'fleet_mess' => 2),
                    array('fleet_owner' => 9, 'fleet_mission' => 1, 'fleet_mess' => 0),
                ),
            ) + $ground)
        );
    }

    public function testElementRowsKeepTheColumnsThatExistOnTheRow(): void
    {
        $resList = array('build' => array(1, 2), 'fleet' => array(202), 'defense' => array(401), 'tech' => array(106));
        $resource = array(1 => 'metal_mine', 2 => 'crystal_mine', 202 => 'small_ship_cargo', 401 => 'misil_launcher', 106 => 'spy_tech');
        $tech = array(1 => 'Mine de métal', 2 => 'Mine de cristal', 202 => 'Petit transporteur', 401 => 'Lanceur de missiles', 106 => 'Technologie espionnage');
        $planet = array('id' => 5, 'metal_mine' => 12, 'small_ship_cargo' => 3);

        $rows = PlayerAdminService::elementRows($resList, $resource, $tech, 'build', $planet);

        // Seul le bâtiment dont la colonne existe est listé, avec sa valeur.
        self::assertSame(array('id' => 1, 'label' => 'Mine de métal (1)', 'value' => 12), $rows[0]);
        self::assertCount(1, $rows);

        $fleets = PlayerAdminService::elementRows($resList, $resource, $tech, 'fleet', $planet);

        self::assertSame(3, $fleets[0]['value']);
    }

    public function testElementRowsReadTheAccountForResearch(): void
    {
        $resList = array('tech' => array(106, 107));
        $resource = array(106 => 'spy_tech', 107 => 'computer_tech');
        $tech = array(106 => 'Technologie espionnage', 107 => 'Technologie ordinateur');
        $user = array('id' => 1, 'spy_tech' => 4);

        $rows = PlayerAdminService::elementRows($resList, $resource, $tech, 'tech', $user);

        self::assertSame(array('id' => 106, 'label' => 'Technologie espionnage (106)', 'value' => 4), $rows[0]);
        self::assertCount(1, $rows, 'Une recherche dont la colonne n\'existe pas sur le compte est écartée.');
    }

    public function testDeltaCarriesTheMeaningInItsSign(): void
    {
        self::assertSame(5, PlayerAdminService::delta('5'), 'Positif : on ajoute.');
        self::assertSame(-5, PlayerAdminService::delta('-5'), 'Négatif : on retire.');
        self::assertSame(0, PlayerAdminService::delta('0'), 'Zéro : on ne touche à rien.');
        self::assertSame(0, PlayerAdminService::delta(''), 'Vide : on ne touche à rien.');
        self::assertSame(0, PlayerAdminService::delta(null));
        self::assertSame(0, PlayerAdminService::delta('abc'));
    }

    public function testDeltasDropEmptyFieldsAndZeroes(): void
    {
        $deltas = PlayerAdminService::deltas(array('1' => '3', '2' => '0', '401' => '', '202' => '-2', '204' => 'abc'));

        self::assertSame(array(1 => 3, 202 => -2), $deltas);
        self::assertSame(array(), PlayerAdminService::deltas(null));
        self::assertSame(array(), PlayerAdminService::deltas(array()), 'Un formulaire vide ne change rien.');
    }

    public function testElementValueNeverGoesBelowZeroOrAboveTheCeiling(): void
    {
        self::assertSame(15, PlayerAdminService::elementValue(10, 5, 60));
        self::assertSame(5, PlayerAdminService::elementValue(10, -5, 60));
        self::assertSame(0, PlayerAdminService::elementValue(10, -50, 60), 'Un retrait plus grand que le niveau met à zéro.');
        self::assertSame(60, PlayerAdminService::elementValue(10, 500, 60));
        self::assertSame(10, PlayerAdminService::elementValue(10, 0, 60), 'Zéro ne change rien.');
        self::assertSame(1, PlayerAdminService::elementValue(0, 10, 60, true), 'Une unité unique reste unique.');
        self::assertSame(0, PlayerAdminService::elementValue(1, -1, 60, true));
    }
}
