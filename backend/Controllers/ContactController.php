<?php

declare(strict_types=1);

namespace Controllers;

use Models\Contact;
use Models\User;
use function Lib\responseJson;

class ContactController
{
    public static function index(): void
    {
        $user = UserController::requireAuth();
        $conversations = Contact::getConversations((int) $user['id']);

        responseJson(
            data: ['contacts' => $conversations],
            message: 'Daftar kontak dan percakapan berhasil dimuat'
        );
    }

    public static function store(): void
    {
        $user = UserController::requireAuth();
        $userId = (int) $user['id'];

        $input = file_get_contents('php://input');
        $data = json_decode($input ?: '{}', true);
        if (!is_array($data)) {
            responseJson(statusCode: 400, message: 'JSON tidak valid.');
        }

        $phone = preg_replace('/[^0-9+]/', '', trim((string) ($data['phone'] ?? ''))) ?? '';
        $aliasName = trim((string) ($data['alias_name'] ?? ''));

        if ($phone === '') {
            responseJson(statusCode: 422, message: 'Nomor telepon kontak wajib diisi.');
        }

        $targetUser = User::findByPhone($phone);
        if ($targetUser === null) {
            responseJson(statusCode: 404, message: 'Pengguna dengan nomor telepon tersebut tidak ditemukan.');
        }

        $targetId = (int) $targetUser['id'];
        if ($targetId === $userId) {
            responseJson(statusCode: 422, message: 'Tidak dapat menambahkan nomor sendiri sebagai kontak.');
        }

        Contact::save($userId, $targetId, $aliasName !== '' ? $aliasName : null);

        responseJson(
            data: ['contact' => $targetUser],
            statusCode: 201,
            message: 'Kontak berhasil ditambahkan'
        );
    }

    public static function update(string $contactUserId): void
    {
        $user = UserController::requireAuth();
        $targetId = (int) $contactUserId;

        $input = file_get_contents('php://input');
        $data = json_decode($input ?: '{}', true);
        if (!is_array($data)) {
            responseJson(statusCode: 400, message: 'JSON tidak valid.');
        }

        $aliasName = trim((string) ($data['alias_name'] ?? ''));
        $updated = Contact::updateAlias((int) $user['id'], $targetId, $aliasName);

        if (!$updated) {
            Contact::save((int) $user['id'], $targetId, $aliasName);
        }

        responseJson(message: 'Nama kontak berhasil diperbarui');
    }

    public static function destroy(string $contactUserId): void
    {
        $user = UserController::requireAuth();
        $removed = Contact::remove((int) $user['id'], (int) $contactUserId);

        if (!$removed) {
            responseJson(statusCode: 404, message: 'Kontak tidak ditemukan.');
        }

        responseJson(message: 'Kontak berhasil dihapus');
    }
}