<?php

declare(strict_types=1);

namespace Models;

use Lib\DB;

class User
{
    public static function create(array $attributes): int
    {
        $db = DB::getInstance();
        $statement = $db->prepare(
            'INSERT INTO users (name, phone, profile_picture)
             VALUES (:name, :phone, :profile_picture)'
        );
        $statement->execute([
            ':name' => $attributes['name'],
            ':phone' => $attributes['phone'],
            ':profile_picture' => $attributes['profile_picture'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'SELECT id, name, phone, profile_picture, created_at, updated_at
             FROM users
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function findByPhone(string $phone): ?array
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'SELECT id, name, phone, profile_picture, created_at, updated_at
             FROM users
             WHERE phone = :phone AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':phone' => $phone]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function update(int $id, array $attributes): bool
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'UPDATE users
             SET name = :name, profile_picture = :profile_picture
             WHERE id = :id AND deleted_at IS NULL'
        );

        return $stmt->execute([
            ':id' => $id,
            ':name' => $attributes['name'],
            ':profile_picture' => $attributes['profile_picture'],
        ]);
    }

    public static function allExcept(int $excludeUserId): array
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'SELECT id, name, phone, profile_picture
             FROM users
             WHERE id != :id AND deleted_at IS NULL
             ORDER BY name ASC'
        );
        $stmt->execute([':id' => $excludeUserId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}