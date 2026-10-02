<?php

namespace App\Repositories;

final class SearchRepository extends BaseRepository
{
    public function searchUsers(string $term): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE username LIKE ? LIMIT 30",
            array('%' . $term . '%'),
            'users'
        );
    }

    public function searchPlanets(string $term): array
    {
        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE name LIKE ? LIMIT 30",
            array('%' . $term . '%'),
            'planets'
        );
    }

    public function findPlanetNameById(int $id): array|false
    {
        return $this->preparedFetchOne(
            "SELECT name FROM {{table}} WHERE id = ?",
            array($id),
            'planets'
        );
    }

    public function findAllyNameById(int $id): array|false
    {
        return $this->preparedFetchOne(
            "SELECT ally_name FROM {{table}} WHERE id = ?",
            array($id),
            'alliance'
        );
    }
}
