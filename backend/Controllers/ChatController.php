<?php

declare(strict_types=1);

namespace Controllers;

use Models\Message;
use Models\User;
use function Lib\responseJson;

class ChatController
{
    public static function getMessages(string $peerId): void
    {
        $user = UserController::requireAuth();
        $userId = (int) $user['id'];
        $targetId = (int) $peerId;

        $peer = User::findById($targetId);
        if ($peer === null) {
            responseJson(statusCode: 404, message: 'Pengguna tujuan tidak ditemukan.');
        }

        $afterId = isset($_GET['after_id']) && is_numeric($_GET['after_id'])
            ? (int) $_GET['after_id']
            : 0;

        Message::markAsRead($userId, $targetId);
        $messages = Message::getBetween($userId, $targetId, $afterId);

        responseJson(
            data: [
                'peer' => $peer,
                'messages' => $messages,
            ],
            message: 'Pesan berhasil dimuat'
        );
    }

    public static function sendMessage(string $peerId): void
    {
        $user = UserController::requireAuth();
        $userId = (int) $user['id'];
        $targetId = (int) $peerId;

        if ($userId === $targetId) {
            responseJson(statusCode: 422, message: 'Tidak dapat mengirim pesan ke diri sendiri.');
        }

        $peer = User::findById($targetId);
        if ($peer === null) {
            responseJson(statusCode: 404, message: 'Penerima tidak ditemukan.');
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $text = '';
        $imageFilename = null;
        $fileFilename = null;

        if (str_contains($contentType, 'multipart/form-data')) {
            $text = trim((string) ($_POST['message'] ?? ''));

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageFilename = self::handleUpload(
                    $_FILES['image'],
                    'img_' . $userId,
                    [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif',
                    ],
                    5 * 1024 * 1024
                );
            }

            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $fileFilename = self::handleUpload(
                    $_FILES['file'],
                    'doc_' . $userId,
                    [
                        'application/pdf' => 'pdf',
                        'application/zip' => 'zip',
                        'application/x-zip-compressed' => 'zip',
                        'text/plain' => 'txt',
                        'application/msword' => 'doc',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    ],
                    10 * 1024 * 1024
                );
            }
        } else {
            $input = file_get_contents('php://input');
            $data = json_decode($input ?: '{}', true);
            if (!is_array($data)) {
                responseJson(statusCode: 400, message: 'JSON tidak valid.');
            }
            $text = trim((string) ($data['message'] ?? ''));
        }

        if ($text === '' && $imageFilename === null && $fileFilename === null) {
            responseJson(statusCode: 422, message: 'Pesan teks, gambar, atau file wajib diisi.');
        }

        $created = Message::create(
            $userId,
            $targetId,
            $text !== '' ? $text : null,
            $imageFilename,
            $fileFilename
        );

        responseJson(
            data: ['message' => $created],
            statusCode: 201,
            message: 'Pesan terkirim'
        );
    }

    public static function deleteMessage(string $messageId): void
    {
        $user = UserController::requireAuth();
        $deleted = Message::softDelete((int) $messageId, (int) $user['id']);

        if (!$deleted) {
            responseJson(statusCode: 404, message: 'Pesan tidak ditemukan atau bukan milik Anda.');
        }

        responseJson(message: 'Pesan berhasil dihapus');
    }

    public static function getImage(string $filename): void
    {
        $safeName = basename($filename);
        $filePath = dirname(__DIR__) . '/uploads/' . $safeName;

        if ($safeName === '' || !is_file($filePath)) {
            responseJson(statusCode: 404, message: 'Gambar tidak ditemukan.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath) ?: '';

        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedImageMimes, true)) {
            responseJson(statusCode: 403, message: 'File bukan format gambar yang valid.');
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $safeName . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }

    public static function getFile(string $filename): void
    {
        $safeName = basename($filename);
        $filePath = dirname(__DIR__) . '/uploads/' . $safeName;

        if ($safeName === '' || !is_file($filePath)) {
            responseJson(statusCode: 404, message: 'File lampiran tidak ditemukan.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        readfile($filePath);
        exit;
    }

    private static function handleUpload(array $file, string $prefix, array $allowedMimes, int $maxSize): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!isset($allowedMimes[$mime])) {
            responseJson(statusCode: 422, message: 'Format file tidak didukung.');
        }

        if ($file['size'] > $maxSize) {
            responseJson(statusCode: 422, message: 'Ukuran file melebihi batas maksimal.');
        }

        $uploadDir = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $filename = sprintf('%s_%d_%s.%s', $prefix, time(), bin2hex(random_bytes(3)), $allowedMimes[$mime]);
        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            responseJson(statusCode: 500, message: 'Gagal menyimpan file unggahan.');
        }

        return $filename;
    }
}