<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ModuleService;
use PHPUnit\Framework\TestCase;

/**
 * La liste des modules du panneau : filtre d'état, recherche libre et tri.
 *
 * Les trois sont des fonctions pures — elles ne lisent que la liste qu'on leur donne —
 * donc testables sans base : c'est `listing()`, qui interroge le registre, qui touche
 * au disque, et `all()` à la base.
 */
final class ModuleServiceTest extends TestCase
{
    /**
     * Deux modules de quoi éprouver la recherche et le tri.
     *
     * @return list<array<string, mixed>>
     */
    private static function modules(): array
    {
        return array(
            array(
                'name' => 'chat',
                'label' => 'mod_chat',
                'description' => 'Salon de discussion',
                'permission' => 'module.chat',
                'page' => '/game/chat',
                'author' => 'XNova',
            ),
            array(
                'name' => 'annonces',
                'label' => 'mod_annonces',
                'description' => 'Annonces du jeu',
                'permission' => 'module.annonces',
                'page' => '/game/annonce',
                'author' => 'XNova',
            ),
        );
    }

    /** @return list<string> */
    private static function names(array $modules): array
    {
        return array_values(array_map(static fn(array $module): string => (string) $module['name'], $modules));
    }

    public function testTheSearchLooksAtEveryColumnOfTheRow(): void
    {
        $modules = self::modules();

        // Nom technique, description, permission, adresse, auteur.
        self::assertSame(array('chat'), self::names(ModuleService::search($modules, 'CHAT')));
        self::assertSame(array('annonces'), self::names(ModuleService::search($modules, 'annonces du')));
        self::assertSame(array('chat'), self::names(ModuleService::search($modules, 'module.chat')));
        self::assertSame(array('annonces'), self::names(ModuleService::search($modules, '/game/annonce')));
        self::assertSame(array('chat', 'annonces'), self::names(ModuleService::search($modules, 'xnova')));
    }

    public function testTheSearchFindsNothingOnAnUnknownWord(): void
    {
        self::assertSame(array(), ModuleService::search(self::modules(), 'zzz'));
        // Une recherche vide ne filtre rien (elle n'est pas « aucun résultat »).
        self::assertSame(self::modules(), ModuleService::search(self::modules(), '   '));
    }

    public function testTheSearchLooksAtTheTranslatedLabel(): void
    {
        // Un manifeste ne porte qu'une **clé** de langue : la recherche doit aussi porter
        // sur ce qui s'affiche (`label_text` / `description_text`), sinon « tchat » ne
        // trouverait pas le tchat, dont le nom technique est `chat`.
        $modules = array(
            array(
                'name' => 'chat',
                'label' => 'mod_chat',
                'label_text' => 'Tchat du jeu',
                'description' => 'mod_chat_desc',
                'description_text' => 'Salon de discussion entre joueurs.',
            ),
        );

        self::assertSame(array('chat'), self::names(ModuleService::search($modules, 'tchat')));
        self::assertSame(array('chat'), self::names(ModuleService::search($modules, 'salon de discussion')));
    }

    public function testTheSortFollowsTheDeclaredColumns(): void
    {
        $modules = self::modules();

        self::assertSame(array('annonces', 'chat'), self::names(ModuleService::sort($modules, 'name', 'asc')));
        self::assertSame(array('chat', 'annonces'), self::names(ModuleService::sort($modules, 'name', 'desc')));
        // « Annonces du jeu » avant « Salon de discussion » : c'est la description qui trie.
        self::assertSame(array('annonces', 'chat'), self::names(ModuleService::sort($modules, 'description', 'asc')));
        self::assertSame(array('chat', 'annonces'), self::names(ModuleService::sort($modules, 'label', 'desc')));
    }

    public function testTheAddressColumnSortsOnThePageNotTheName(): void
    {
        $modules = array(
            array('name' => 'alfa', 'page' => '/game/zzz'),
            array('name' => 'bravo', 'page' => '/game/aaa'),
        );

        self::assertSame(array('bravo', 'alfa'), self::names(ModuleService::sort($modules, 'address', 'asc')));
    }

    public function testTheSortIgnoresAColumnOutsideTheList(): void
    {
        // Une clé d'URL inconnue retombe sur le nom : jamais sur une valeur venue de l'URL.
        self::assertSame(array('annonces', 'chat'), self::names(ModuleService::sort(self::modules(), 'inconnu', 'asc')));
    }

    public function testTheSortIsNaturalAndCaseInsensitive(): void
    {
        $modules = array(
            array('name' => 'module10'),
            array('name' => 'Module9'),
        );

        self::assertSame(array('Module9', 'module10'), self::names(ModuleService::sort($modules, 'name', 'asc')));
    }

    public function testTheFilterOnlyKeepsKnownStates(): void
    {
        self::assertSame('installed', ModuleService::listFilter(''));
        self::assertSame('installed', ModuleService::listFilter(null));
        self::assertSame('installed', ModuleService::listFilter('inconnu'));
        // Un tableau dans l'URL (`?state[]=...`) ne fait pas d'avertissement PHP.
        self::assertSame('installed', ModuleService::listFilter(array('archived')));
        self::assertSame('archived', ModuleService::listFilter(' Archived '));
        self::assertSame('all', ModuleService::listFilter('ALL'));
    }

    public function testEveryFilterAndSortIsDeclaredOnce(): void
    {
        // Les filtres et les colonnes triables sont des listes blanches : le filtre par
        // défaut en fait partie, et chaque colonne vise une clé de ligne.
        self::assertContains(ModuleService::FILTER_DEFAULT, ModuleService::FILTERS);
        self::assertSame(array('installed', 'archived', 'all'), ModuleService::FILTERS);

        $row = array(
            'name' => 'bot',
            'label' => 'mod_bot',
            'description' => 'Robots',
            'permission' => 'module.bot',
            'page' => '/back/robots',
        );

        foreach (ModuleService::SORTS as $column) {
            self::assertArrayHasKey($column, $row);
        }
    }
}
