<?php

namespace App\Repositories;

final class ConfigRepository extends BaseRepository
{
    public function updateValue(string $name, string $value): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET config_value = ? WHERE config_name = ?",
            array($value, $name),
            'config'
        );
    }

    public function findValue(string $name): array|false
    {
        return $this->preparedFetchOne(
            "SELECT config_value FROM {{table}} WHERE config_name = ?",
            array($name),
            'config'
        );
    }
}
