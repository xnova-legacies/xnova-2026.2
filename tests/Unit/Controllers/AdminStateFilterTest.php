<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\Back\AdminController;
use App\Controllers\Back\MessageListController;
use App\Controllers\Back\NotesController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Filtre d'état partagé par les listes d'administration.
 *
 * L'effacement est **logique** (`App\Core\Flags::DELETED`) : chaque liste affiche
 * soit les enregistrements existants (défaut), soit les supprimés — c'est ce que
 * décide ce filtre, qui vient d'une valeur d'URL. Toute valeur inconnue revient à
 * « existants », sinon une faute de frappe montrerait les supprimés.
 */
final class AdminStateFilterTest extends TestCase
{
    public function testTheDeletedStateIsOnlyTheKnownKey(): void
    {
        self::assertTrue(AdminController::stateFilter(AdminController::STATE_DELETED));
        self::assertTrue(AdminController::stateFilter('DELETED'));
        self::assertTrue(AdminController::stateFilter(' deleted '));
    }

    public function testAnythingElseShowsTheLiveRecords(): void
    {
        self::assertFalse(AdminController::stateFilter(AdminController::STATE_LIVE));
        self::assertFalse(AdminController::stateFilter(null));
        self::assertFalse(AdminController::stateFilter(''));
        self::assertFalse(AdminController::stateFilter('supprimes'));
        // Une URL bricolée (`?state[]=deleted`) livre un tableau : il est refusé
        // sans le moindre avertissement PHP.
        self::assertFalse(AdminController::stateFilter(array('deleted')));
    }

    public function testTheStateKeyIsTheOneWrittenInTheUrl(): void
    {
        self::assertSame('live', AdminController::stateKey(false));
        self::assertSame('deleted', AdminController::stateKey(true));
    }

    /**
     * Une règle = une implémentation : les pages de liste héritent du filtre du
     * Coeur d'application d'administration, elles ne le redéfinissent pas.
     */
    public function testTheListPagesShareTheSameFilter(): void
    {
        foreach (array(MessageListController::class, NotesController::class) as $class) {
            $method = new ReflectionMethod($class, 'stateFilter');

            self::assertSame(
                AdminController::class,
                $method->getDeclaringClass()->getName(),
                $class . ' doit utiliser le filtre du Coeur d\'application.'
            );
        }
    }

    /**
     * La sélection multiple (`sele[<id>]`) se lit au même endroit : les pages de
     * liste du panneau héritent de la lecture du Coeur d'application, elles ne la recopient pas.
     */
    public function testTheListPagesShareTheSameSelection(): void
    {
        foreach (array(MessageListController::class, NotesController::class) as $class) {
            $method = new ReflectionMethod($class, 'selection');

            self::assertSame(
                AdminController::class,
                $method->getDeclaringClass()->getName(),
                $class . ' doit utiliser la lecture de sélection du Coeur d\'application.'
            );
        }
    }
}
