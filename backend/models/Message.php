<?php

declare(strict_types=1);

namespace Models;

use Lib\DB;

class Message
{
    public static function getBetween(int $userId, int $peerId, int $afterId = 0): array
    {
        $db = DB::getInstance();
        $sql = '
            SELECT id, sender_id, receiver_id, message, image, file, is_read, created_at
            FROM messages
            WHERE deleted_at IS NULL
              AND id > :after_id
              AND (
                  (sender_id = :u1 AND receiver_id = :p1)
                  OR
                  (sender_id = :p2 AND receiver_id = :u2)
              )
            ORDER BY id ASC
        ';
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':after_id' => $afterId,
            ':u1' => $userId,
            ':p1' => $peerId,
            ':p2' => $peerId,
            ':u2' => $userId,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function markAsRead(int $userId, int $peerId): void
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'UPDATE messages
             SET is_read = 1
             WHERE sender_id = :peer_id
               AND receiver_id = :user_id
               AND is_read = 0
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':peer_id' => $peerId,
            ':user_id' => $userId,
        ]);
    }

    public static function create(
        int $senderId,
        int $receiverId,
        ?string $message,
        ?string $image = null,
        ?string $file = null
    ): array {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO messages (sender_id, receiver_id, message, image, file)
             VALUES (:sender_id, :receiver_id, :message, :image, :file)'
        );
        $stmt->execute([
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId,
            ':message' => $message,
            ':image' => $image,
            ':file' => $file,
        ]);

        $id = (int) $db->lastInsertId();
        $fetch = $db->prepare(
            'SELECT id, sender_id, receiver_id, message, image, file, is_read, created_at
             FROM messages WHERE id = :id LIMIT 1'
        );
        $fetch->execute([':id' => $id]);

        return $fetch->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function softDelete(int $messageId, int $senderId): bool
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'UPDATE messages
             SET deleted_at = CURRENT_TIMESTAMP
             WHERE id = :id AND sender_id = :sender_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':id' => $messageId,
            ':sender_id' => $senderId,
        ]);

        return $stmt->rowCount() > 0;
    }
}