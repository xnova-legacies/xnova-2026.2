<?php

namespace App\Repositories;

final class AuthRepository extends BaseRepository
{
    public function findById(int $userId): array|false
    {
        return $this->preparedFetchOne(
            "SELECT * FROM {{table}} WHERE id = ? LIMIT 1",
            array($userId),
            'users'
        );
    }

    /**
     * Vérifie un cookie "remember me" : SHA1(username . password . salt) où
     * salt = MID(key, 0, 4). Le legacy faisait ça en SQL avec des variables
     * utilisateur (injectable) : ici on vérifie en PHP avec des données
     * préparées.
     */
    public function validateRememberMeCookie(int $userId, string $key): array|false
    {
        if ($userId <= 0 || strlen($key) < 4) {
            return false;
        }

        $user = $this->findById($userId);
        if ($user === false) {
            return false;
        }

        $salt = substr($key, 0, 4);
        $expected = sha1($user['username'] . $user['password'] . $salt);

        if (!hash_equals($expected, substr($key, 4))) {
            return false;
        }

        return $user;
    }

    public function touchSession(int $userId, string $requestUri, string $remoteAddr, string $userAgent): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET
            onlinetime = UNIX_TIMESTAMP(NOW()),
            current_page = ?,
            user_lastip = ?,
            user_agent = ?
            WHERE id = ? LIMIT 1",
            array($requestUri, $remoteAddr, $userAgent, $userId),
            'users'
        );
    }

    public function touchOnline(int $userId): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET onlinetime = UNIX_TIMESTAMP(NOW()) WHERE id = ?",
            array($userId),
            'users'
        );
    }
}
