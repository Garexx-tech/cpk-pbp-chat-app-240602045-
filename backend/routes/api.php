<?php

declare(strict_types=1);

use Controllers\ChatController;
use Controllers\ContactController;
use Controllers\UserController;
use Lib\Router;
use function Lib\responseJson;
use Lib\DB;

return static function (Router $router): void {
    $router->get('/api/health', static function (): void {
        try {
            $db = DB::getInstance();
            $db->query('SELECT 1');
        } catch (\PDOException $e) {
            responseJson(
                data: ['ok' => false, 'database' => false],
                message: 'Database connection failed: ' . $e->getMessage(),
                statusCode: 500
            );
            return;
        }
        responseJson(data: ['ok' => true, 'database' => true], message: 'API is running');
    });

    // Kriteria 2: Autentikasi & Manajemen Pengguna
    $router->post('/api/users', [UserController::class, 'register']);
    $router->post('/api/auth/register', [UserController::class, 'register']);
    $router->post('/api/auth/login', [UserController::class, 'login']);
    $router->get('/api/auth/me', [UserController::class, 'me']);
    $router->post('/api/auth/logout', [UserController::class, 'logout']);
    $router->post('/api/users/profile', [UserController::class, 'updateProfile']);
    $router->put('/api/users/profile', [UserController::class, 'updateProfile']);
    $router->get('/api/users/directory', [UserController::class, 'listDirectory']);

    // Kriteria 3: Manajemen Kontak & Daftar Percakapan
    $router->get('/api/contacts', [ContactController::class, 'index']);
    $router->post('/api/contacts', [ContactController::class, 'store']);
    $router->put('/api/contacts/{contactUserId}', [ContactController::class, 'update']);
    $router->delete('/api/contacts/{contactUserId}', [ContactController::class, 'destroy']);

    // Kriteria 4: Chat (Pesan Teks, Gambar, File) & Route GET Image/File
    $router->get('/api/messages/{peerId}', [ChatController::class, 'getMessages']);
    $router->post('/api/messages/{peerId}', [ChatController::class, 'sendMessage']);
    $router->delete('/api/messages/{messageId}', [ChatController::class, 'deleteMessage']);
    $router->get('/api/images/{filename}', [ChatController::class, 'getImage']);
    $router->get('/api/files/{filename}', [ChatController::class, 'getFile']);
};