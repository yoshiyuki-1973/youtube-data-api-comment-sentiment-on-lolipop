<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$rootDir = dirname(__DIR__);
$srcDir = $rootDir . '/src';
$checks = [
    'php_version' => PHP_VERSION,
    'extensions' => [
        'curl' => extension_loaded('curl'),
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'mbstring' => extension_loaded('mbstring'),
        'json' => extension_loaded('json'),
    ],
    'files' => [
        'config.php' => file_exists($rootDir . '/config.php'),
        'src/Config.php' => file_exists($srcDir . '/Config.php'),
        'src/Database.php' => file_exists($srcDir . '/Database.php'),
        'src/GeminiClient.php' => file_exists($srcDir . '/GeminiClient.php'),
        'src/Exceptions.php' => file_exists($srcDir . '/Exceptions.php'),
    ],
    'config_keys' => [],
    'database' => [
        'connect' => false,
        'tables' => [],
    ],
];

try {
    require_once $srcDir . '/bootstrap.php';

    foreach (['YOUTUBE_API_KEY', 'GEMINI_API_KEY', 'GEMINI_MODEL', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
        $checks['config_keys'][$key] = Config::get($key) !== null && Config::get($key) !== '';
    }

    $pdo = Database::getInstance();
    $checks['database']['connect'] = true;

    foreach (['videos', 'analysis_results', 'comments'] as $table) {
        try {
            $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            $checks['database']['tables'][$table] = true;
        } catch (Throwable) {
            $checks['database']['tables'][$table] = false;
        }
    }
} catch (Throwable $e) {
    $checks['error'] = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
    ];
}

$checks['ok'] =
    !in_array(false, $checks['extensions'], true)
    && !in_array(false, $checks['files'], true)
    && !in_array(false, $checks['config_keys'], true)
    && $checks['database']['connect'] === true
    && !in_array(false, $checks['database']['tables'], true);

http_response_code($checks['ok'] ? 200 : 500);
echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
