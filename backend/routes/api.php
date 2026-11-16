<?php

declare(strict_types=1);

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
};