<?php

declare(strict_types=1);

namespace Controllers;

use Models\User;
use function Lib\responseJson;

class UserController
{
    public static function getAuthenticatedUserId(): ?int
    {
        if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        $headerUserId = $_SERVER['HTTP_X_USER_ID'] ?? null;
        if ($headerUserId !== null && is_numeric($headerUserId)) {
            return (int) $headerUserId;
        }

        return null;
    }

    public static function requireAuth(): array
    {
        $userId = self::getAuthenticatedUserId();
        if ($userId === null) {
            responseJson(statusCode: 401, message: 'Unauthorized. Silakan login terlebih dahulu.');
        }

        $user = User::findById($userId);
        if ($user === null) {
            unset($_SESSION['user_id']);
            responseJson(statusCode: 401, message: 'Sesi pengguna tidak valid.');
        }

        return $user;
    }

    public static function register(): void
    {
        $data = self::readJsonBody();

        $name = trim((string) ($data['name'] ?? ''));
        $phone = self::normalizePhone((string) ($data['phone'] ?? ''));
        $profilePicture = trim((string) ($data['profile_picture'] ?? ''));

        if ($name === '' || $phone === '') {
            responseJson(statusCode: 422, message: 'Nama dan nomor telepon wajib diisi.');
        }

        if (User::findByPhone($phone) !== null) {
            responseJson(statusCode: 409, message: 'Nomor telepon sudah terdaftar. Silakan login.');
        }

        if ($profilePicture === '') {
            $profilePicture = 'https://ui-avatars.com/api/?background=00a884&color=fff&name=' . urlencode($name);
        }

        $id = User::create([
            'name' => $name,
            'phone' => $phone,
            'profile_picture' => $profilePicture,
        ]);

        $_SESSION['user_id'] = $id;
        $user = User::findById($id);

        responseJson(
            data: ['user' => $user],
            statusCode: 201,
            message: 'Registrasi berhasil'
        );
    }

    public static function login(): void
    {
        $data = self::readJsonBody();
        $phone = self::normalizePhone((string) ($data['phone'] ?? ''));

        if ($phone === '') {
            responseJson(statusCode: 422, message: 'Nomor telepon wajib diisi.');
        }

        $user = User::findByPhone($phone);
        if ($user === null) {
            responseJson(statusCode: 404, message: 'Nomor telepon belum terdaftar.');
        }

        $_SESSION['user_id'] = (int) $user['id'];

        responseJson(
            data: ['user' => $user],
            statusCode: 200,
            message: 'Login berhasil'
        );
    }

    public static function me(): void
    {
        $user = self::requireAuth();
        responseJson(data: ['user' => $user], message: 'Authenticated');
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        responseJson(message: 'Logout berhasil');
    }

    public static function updateProfile(): void
    {
        $user = self::requireAuth();
        $userId = (int) $user['id'];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $name = $user['name'];
        $profilePicture = $user['profile_picture'];

        if (str_contains($contentType, 'multipart/form-data')) {
            if (isset($_POST['name']) && trim((string) $_POST['name']) !== '') {
                $name = trim((string) $_POST['name']);
            }

            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['avatar']['tmp_name']);

                if (!isset($allowedTypes[$mime])) {
                    responseJson(statusCode: 422, message: 'Format foto harus JPG, PNG, atau WEBP.');
                }

                if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    responseJson(statusCode: 422, message: 'Ukuran foto maksimal 2MB.');
                }

                $uploadDir = dirname(__DIR__) . '/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $filename = sprintf('avatar_%d_%d.%s', $userId, time(), $allowedTypes[$mime]);
                $destination = $uploadDir . '/' . $filename;

                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    responseJson(statusCode: 500, message: 'Gagal menyimpan file foto profil.');
                }

                $profilePicture = '../backend/uploads/' . $filename;
            } elseif (isset($_POST['profile_picture']) && trim((string) $_POST['profile_picture']) !== '') {
                $profilePicture = trim((string) $_POST['profile_picture']);
            }
        } else {
            $data = self::readJsonBody();
            if (isset($data['name']) && trim((string) $data['name']) !== '') {
                $name = trim((string) $data['name']);
            }
            if (isset($data['profile_picture']) && trim((string) $data['profile_picture']) !== '') {
                $profilePicture = trim((string) $data['profile_picture']);
            }
        }

        User::update($userId, [
            'name' => $name,
            'profile_picture' => $profilePicture,
        ]);

        $updatedUser = User::findById($userId);
        responseJson(data: ['user' => $updatedUser], message: 'Profil berhasil diperbarui');
    }

    public static function listDirectory(): void
    {
        $user = self::requireAuth();
        $users = User::allExcept((int) $user['id']);
        responseJson(data: ['users' => $users], message: 'Daftar pengguna berhasil dimuat');
    }

    private static function readJsonBody(): array
    {
        $input = file_get_contents('php://input');
        if ($input === false || trim($input) === '') {
            return [];
        }

        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            responseJson(statusCode: 400, message: 'Request body harus berupa JSON valid.');
        }

        return $data;
    }

    private static function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    }
}