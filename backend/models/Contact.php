<?php

declare(strict_types=1);

namespace Models;

use Lib\DB;

class Contact
{
    public static function getConversations(int $userId): array
    {
        $db = DB::getInstance();
        $sql = '
            SELECT
                u.id AS user_id,
                u.name,
                u.phone,
                u.profile_picture,
                c.alias_name,
                CASE WHEN c.id IS NOT NULL AND c.deleted_at IS NULL THEN 1 ELSE 0 END AS is_saved,
                lm.message AS last_message,
                lm.image AS last_image,
                lm.file AS last_file,
                lm.sender_id AS last_sender_id,
                lm.created_at AS last_message_at,
                (
                    SELECT COUNT(*)
                    FROM messages m_unread
                    WHERE m_unread.sender_id = u.id
                      AND m_unread.receiver_id = :uid_unread
                      AND m_unread.is_read = 0
                      AND m_unread.deleted_at IS NULL
                ) AS unread_count
            FROM users u
            LEFT JOIN contacts c
                ON c.contact_user_id = u.id
               AND c.user_id = :uid_contact
               AND c.deleted_at IS NULL
            LEFT JOIN messages lm
                ON lm.id = (
                    SELECT m2.id
                    FROM messages m2
                    WHERE m2.deleted_at IS NULL
                      AND (
                          (m2.sender_id = :uid_m1 AND m2.receiver_id = u.id)
                          OR
                          (m2.sender_id = u.id AND m2.receiver_id = :uid_m2)
                      )
                    ORDER BY m2.id DESC
                    LIMIT 1
                )
            WHERE u.id != :uid_self
              AND u.deleted_at IS NULL
              AND (c.id IS NOT NULL OR lm.id IS NOT NULL)
            ORDER BY COALESCE(lm.created_at, c.created_at) DESC, u.name ASC
        ';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':uid_unread' => $userId,
            ':uid_contact' => $userId,
            ':uid_m1' => $userId,
            ':uid_m2' => $userId,
            ':uid_self' => $userId,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function save(int $userId, int $contactUserId, ?string $aliasName = null): void
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO contacts (user_id, contact_user_id, alias_name, deleted_at)
             VALUES (:user_id, :contact_user_id, :alias_name, NULL)
             ON DUPLICATE KEY UPDATE
                alias_name = VALUES(alias_name),
                deleted_at = NULL'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':contact_user_id' => $contactUserId,
            ':alias_name' => $aliasName !== '' ? $aliasName : null,
        ]);
    }

    public static function updateAlias(int $userId, int $contactUserId, ?string $aliasName): bool
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'UPDATE contacts
             SET alias_name = :alias_name
             WHERE user_id = :user_id AND contact_user_id = :contact_user_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':alias_name' => $aliasName !== '' ? $aliasName : null,
            ':user_id' => $userId,
            ':contact_user_id' => $contactUserId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function remove(int $userId, int $contactUserId): bool
    {
        $db = DB::getInstance();
        $stmt = $db->prepare(
            'UPDATE contacts
             SET deleted_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND contact_user_id = :contact_user_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':contact_user_id' => $contactUserId,
        ]);

        return $stmt->rowCount() > 0;
    }
}