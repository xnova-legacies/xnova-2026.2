<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Combat;

use App\Core\Combat\MissileStrike;
use PHPUnit\Framework\TestCase;

/**
 * Tests de App\Core\Combat\MissileStrike, la règle d'attaque de missiles
 * interplanétaires utilisée par App\Services\MissileService.
 *
 * Index des défenses du défenseur :
 *   0..7 => 401..408, 8 => 503 (missiles interplanétaires), 9 => 502 (intercepteurs).
 * L'index 10 est réservé aux missiles entrants et l'index 11 au total.
 */
final class MissileStrikeTest extends TestCase
{
    private function defences(array $counts): array
    {
        return array_replace(array_fill(0, 10, 0), $counts);
    }

    public function testAMissileDestroysRocketLaunchers(): void
    {
        // 1 missile = 12000 dégâts, lanceur de missiles = 200 points de coque.
        $result = MissileStrike::resolve(0, 0, 1, $this->defences([0 => 10]));

        self::assertSame(0, (int) $result['remaining'][0]);
        self::assertSame(10, (int) $result['destroyed'][0]);
        self::assertSame(10, (int) $result['destroyed'][11]);
    }

    public function testDamageIsLimitedByAvailableTargetsAndSpillsOver(): void
    {
        // 3 lanceurs (600 PV) puis 5 canons de Gauss (3500 PV chacun).
        $result = MissileStrike::resolve(0, 0, 1, $this->defences([0 => 3, 3 => 5]));

        self::assertSame(0, (int) $result['remaining'][0]);
        self::assertSame(2, (int) $result['remaining'][3]);
        self::assertSame(3, (int) $result['destroyed'][3]);
    }

    public function testNoDamageWhenTheTargetHasNoDefences(): void
    {
        $result = MissileStrike::resolve(0, 0, 5, $this->defences([]));

        self::assertSame(0, (int) $result['destroyed'][11]);
        self::assertSame(0, (int) $result['remaining'][11]);
    }

    public function testInterceptorsDestroyIncomingMissiles(): void
    {
        // 5 intercepteurs (index 9) annulent 5 missiles entrants.
        $result = MissileStrike::resolve(0, 0, 5, $this->defences([9 => 5]));

        self::assertSame(0, (int) $result['remaining'][9]);
        self::assertSame(5, (int) $result['destroyed'][9]);
        self::assertSame(5, (int) $result['destroyed'][10]);
    }

    public function testAttackingMissilesAreCountedAsLosses(): void
    {
        // Missiles interplanétaires : 12.5k métal / 2.5k cristal / 10k deutérium l'unité.
        $result = MissileStrike::resolve(0, 0, 4, $this->defences([]));

        self::assertSame(4, (int) $result['destroyed'][10]);
        self::assertSame(50.0, $result['lost_metal'][10]);
        self::assertSame(10.0, $result['lost_crystal'][10]);
        self::assertSame(40.0, $result['lost_deuterium'][10]);
    }

    public function testWeaponsTechnologyIncreasesDamage(): void
    {
        // Canon de Gauss : 3500 points de coque. 1 missile = 12000 dégâts,
        // portés à 24000 avec la technologie armes niveau 10.
        $withoutTech = MissileStrike::resolve(0, 0, 1, $this->defences([3 => 10]));
        $withTech = MissileStrike::resolve(0, 10, 1, $this->defences([3 => 10]));

        self::assertSame(3, (int) $withoutTech['destroyed'][3]);
        self::assertSame(7, (int) $withoutTech['remaining'][3]);
        self::assertSame(6, (int) $withTech['destroyed'][3]);
        self::assertSame(4, (int) $withTech['remaining'][3]);
    }

    public function testArmourTechnologyReducesDamage(): void
    {
        // Tech blindage 10 => coque doublée : 400 PV par lanceur, 30 sont détruits.
        $result = MissileStrike::resolve(10, 0, 1, $this->defences([0 => 100]));

        self::assertSame(30, (int) $result['destroyed'][0]);
        self::assertSame(70, (int) $result['remaining'][0]);
    }

    /**
     * `primaer` vaut 9 pour les missiles interplanétaires, donc hors de la plage
     * 0-8 du switch historique : l'ordre de tir restait indéfini et la
     * destruction devenait incohérente (aucune défense touchée, warnings PHP).
     */
    public function testAnOutOfRangePrimaryTargetFallsBackToTheDefaultOrder(): void
    {
        $defences = $this->defences([0 => 3, 3 => 5]);

        $default = MissileStrike::resolve(0, 0, 1, $defences, 0);
        $primaryNine = MissileStrike::resolve(0, 0, 1, $defences, 9);
        $empty = MissileStrike::resolve(0, 0, 1, $defences, '');

        self::assertSame($default['destroyed'], $primaryNine['destroyed']);
        self::assertSame($default['remaining'], $primaryNine['remaining']);
        self::assertSame($default['destroyed'], $empty['destroyed']);

        // Le missile a bien détruit du matériel : le repli n'est pas un no-op.
        self::assertSame(3, (int) $primaryNine['destroyed'][3]);
        self::assertSame(0, (int) $primaryNine['remaining'][0]);
    }

    /**
     * La cible prioritaire passe devant : les canons de Gauss tombent avant les
     * lanceurs, comme le faisait la table de tirs d'origine.
     */
    public function testThePrimaryTargetIsHitFirst(): void
    {
        $defences = $this->defences([0 => 10, 3 => 5]);

        $natural = MissileStrike::resolve(0, 0, 1, $defences, 0);
        $gaussFirst = MissileStrike::resolve(0, 0, 1, $defences, 3);

        self::assertSame(10, (int) $natural['destroyed'][0]);
        self::assertSame(2, (int) $natural['destroyed'][3]);

        self::assertSame(7, (int) $gaussFirst['destroyed'][0]);
        self::assertSame(3, (int) $gaussFirst['destroyed'][3]);
    }
}
