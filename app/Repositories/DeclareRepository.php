<?php

namespace App\Repositories;

final class DeclareRepository extends BaseRepository
{
    /**
     * Colonnes triables de la liste (clé d'URL → colonne).
     *
     * La table `declared` n'a ni clé primaire ni colonne `id` : pas d'identifiant
     * pour départager deux valeurs égales.
     */
    public const SORTS = array(
        'declarator_name' => 'declarator_name',
        'declarator' => 'declarator',
        'declared_1' => 'declared_1',
        'declared_2' => 'declared_2',
        'declared_3' => 'declared_3',
        'reason' => 'reason',
    );

    /**
     * Enregistre une déclaration d'IP collective.
     *
     * Les six valeurs viennent du formulaire du joueur : elles partent en paramètres.
     */
    public function insert(string $declarator, string $declaratorName, string $decl1, string $decl2, string $decl3, string $reason): void
    {
        $this->preparedExecute(
            "INSERT INTO {{table}} (declarator, declarator_name, declared_1, declared_2, declared_3, reason) VALUES (?, ?, ?, ?, ?, ?)",
            array($declarator, $declaratorName, $decl1, $decl2, $decl3, $reason),
            'declared'
        );
    }

    /**
     * Marque le compte comme ayant validé sa déclaration.
     *
     * Le pseudo vient du formulaire : il part en paramètre.
     */
    public function validateUser(string $username): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET multi_validated = '1' WHERE username = ?",
            array($username),
            'users'
        );
    }

    /**
     * Toutes les déclarations d'IP collective (page admin).
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(string $sort = 'declarator_name', string $order = 'desc'): array
    {
        $column = self::SORTS[$sort] ?? self::SORTS['declarator_name'];
        $direction = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        return $this->fetchAll(
            'SELECT * FROM {{table}} ORDER BY `' . $column . '` ' . $direction,
            'declared'
        );
    }
}
