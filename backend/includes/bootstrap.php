<?php
/**
 * VEYRA — Bootstrap
 * Load config, connect DB, start session, set headers.
 */

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';

date_default_timezone_set($config['timezone']);

if ($config['app_debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Auth.php';

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $config['cors']['allowed_origins'] ?? [];
if ($origin && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: ' . implode(', ', $config['cors']['allowed_methods']));
    header('Access-Control-Allow-Headers: ' . implode(', ', $config['cors']['allowed_headers']));
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = Database::connect($config['db']);
} catch (Throwable $e) {
    if ($config['app_debug']) {
        Response::error('Database connection failed: ' . $e->getMessage(), 500);
    }
    Response::error('Service temporarily unavailable.', 503);
}

$auth = new Auth($pdo, $config);
$auth->startSession();

/**
 * Read JSON body or form POST into an array.
 */
function request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    return array_merge($_GET, $_POST);
}

/**
 * Require HTTP method.
 */
function require_method(string ...$methods): void
{
    $current = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $allowed = array_map('strtoupper', $methods);
    if (!in_array($current, $allowed, true)) {
        Response::methodNotAllowed($allowed);
    }
}
