<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Config/config.php';

use App\Core\App;

set_exception_handler(static function (\Throwable $e): never {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['status' => false, 'error' => ['code' => 500, 'message' => 'Internal server error']]);
    exit;
});

new App();
