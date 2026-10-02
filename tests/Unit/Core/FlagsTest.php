<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Flags;
use PHPUnit\Framework\TestCase;

/**
 * Drapeaux d'un enregistrement : un bit par état, dans un même entier.
 *
 * Les fonctions sont pures — elles reçoivent et rendent la valeur de la colonne
 * `flags` (qui arrive en texte depuis la base) : aucun accès disque ni base.
 */
final class FlagsTest extends TestCase
{
    public function testEachFlagOwnsASingleBit(): void
    {
        foreach (Flags::NAMES as $name => $value) {
            self::assertGreaterThan(0, $value, $name . ' doit occuper un bit.');
            self::assertSame(0, $value & ($value - 1), $name . ' doit être une puissance de deux.');
        }

        self::assertSame(0, Flags::DELETED & Flags::ENABLED, 'Deux drapeaux ne partagent pas un bit.');
    }

    public function testAFreshRecordIsActive(): void
    {
        // La colonne `flags` vaut `Flags::DEFAULT` à la création : actif, non supprimé.
        self::assertTrue(Flags::isEnabled(Flags::DEFAULT));
        self::assertFalse(Flags::isDeleted(Flags::DEFAULT));
        self::assertTrue(Flags::usable(Flags::DEFAULT));
        self::assertSame(Flags::ENABLED, Flags::DEFAULT);
    }

    public function testHasReadsOneOrSeveralFlags(): void
    {
        self::assertTrue(Flags::has(Flags::DELETED, Flags::DELETED));
        self::assertFalse(Flags::has(Flags::ENABLED, Flags::DELETED));
        self::assertTrue(Flags::has(Flags::DELETED | Flags::ENABLED, Flags::DELETED));

        // Un masque exige que **tous** ses bits soient posés.
        self::assertTrue(Flags::has(Flags::DELETED | Flags::ENABLED, Flags::DELETED | Flags::ENABLED));
        self::assertFalse(Flags::has(Flags::DELETED, Flags::DELETED | Flags::ENABLED));

        // La valeur vient de la base : elle arrive en texte.
        self::assertTrue(Flags::has('1', Flags::DELETED));
        self::assertFalse(Flags::has('', Flags::DELETED));
        self::assertFalse(Flags::has(null, Flags::DELETED));
    }

    public function testSetPosesAndClearsWithoutTouchingTheOtherFlags(): void
    {
        $deleted = Flags::set(Flags::DEFAULT, Flags::DELETED);

        self::assertTrue(Flags::isDeleted($deleted));
        self::assertTrue(Flags::isEnabled($deleted), 'Le drapeau actif reste posé.');
        self::assertFalse(Flags::usable($deleted));

        $restored = Flags::set($deleted, Flags::DELETED, false);

        self::assertSame(Flags::DEFAULT, $restored);
        self::assertTrue(Flags::usable($restored));

        // Poser deux fois, retirer deux fois : la valeur ne bouge plus.
        self::assertSame($deleted, Flags::set($deleted, Flags::DELETED));
        self::assertSame($restored, Flags::set($restored, Flags::DELETED, false));
    }

    public function testSetAndToggleKeepUnknownBits(): void
    {
        $future = 8; // bit d'une version plus récente

        self::assertSame($future | Flags::DELETED, Flags::set($future, Flags::DELETED));
        self::assertSame($future, Flags::toggle(Flags::toggle($future, Flags::DELETED), Flags::DELETED));
    }

    public function testAnElementCanBeDisabled(): void
    {
        $disabled = Flags::set(Flags::DEFAULT, Flags::ENABLED, false);

        self::assertSame(0, $disabled);
        self::assertFalse(Flags::isEnabled($disabled));
        self::assertFalse(Flags::usable($disabled));
        self::assertFalse(Flags::isDeleted($disabled), 'Désactivé n\'est pas supprimé.');
    }

    public function testUsableNeedsBothActiveAndNotDeleted(): void
    {
        self::assertTrue(Flags::usable(Flags::ENABLED));
        self::assertFalse(Flags::usable(Flags::ENABLED | Flags::DELETED));
        self::assertFalse(Flags::usable(0));
    }

    public function testNamesListsThePosedFlags(): void
    {
        self::assertSame(array(), Flags::names(0));
        self::assertSame(array('DELETED'), Flags::names(Flags::DELETED));
        self::assertSame(array('DELETED', 'ENABLED'), Flags::names(Flags::DELETED | Flags::ENABLED));
        // Un bit inconnu n'a pas de nom, et n'empêche pas de lire les autres.
        self::assertSame(array(), Flags::names(8));
        self::assertSame(array('ENABLED'), Flags::names(Flags::ENABLED | 8));
    }
}
