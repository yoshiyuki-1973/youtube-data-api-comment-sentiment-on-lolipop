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
        'zend_opcache' => extension_loaded('Zend OPcache'),
    ],
    'opcache' => [
        'enable' => filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN),
        'enable_cli' => filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN),
        'validate_timestamps' => filter_var(ini_get('opcache.validate_timestamps'), FILTER_VALIDATE_BOOLEAN),
        'revalidate_freq' => (int)ini_get('opcache.revalidate_freq'),
    ],
    'files' => [
        'config.php' => file_exists($rootDir . '/config.php'),
        'src/Config.php' => file_exists($srcDir . '/Config.php'),
        'src/Database.php' => file_exists($srcDir . '/Database.php'),
        'src/GeminiClient.php' => file_exists($srcDir . '/GeminiClient.php'),
        'src/Exceptions.php' => file_exists($srcDir . '/Exceptions.php'),
    ],
    'exceptions_file' => [
        'path' => realpath($srcDir . '/Exceptions.php') ?: $srcDir . '/Exceptions.php',
        'size' => file_exists($srcDir . '/Exceptions.php') ? filesize($srcDir . '/Exceptions.php') : null,
        'mtime' => file_exists($srcDir . '/Exceptions.php') ? date('c', filemtime($srcDir . '/Exceptions.php')) : null,
        'sha1' => file_exists($srcDir . '/Exceptions.php') ? sha1_file($srcDir . '/Exceptions.php') : null,
        'contains_gemini_exception' => file_exists($srcDir . '/Exceptions.php')
            && str_contains((string)file_get_contents($srcDir . '/Exceptions.php'), 'GeminiApiException'),
    ],
    'config_keys' => [],
    'database' => [
        'connect' => false,
        'tables' => [],
        'cache_query' => false,
    ],
];

try {
    require_once $srcDir . '/bootstrap.php';

    $checks['classes'] = [
        'YouTubeApiException' => class_exists('YouTubeApiException', false),
        'GeminiApiException' => class_exists('GeminiApiException', false),
        'GeminiClient' => class_exists('GeminiClient', false),
    ];

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

    try {
        $repository = new VideoRepository($pdo);
        $repository->findCachedResult('dQw4w9WgXcQ', 10);
        $checks['database']['cache_query'] = true;
    } catch (Throwable $e) {
        $checks['database']['cache_query_error'] = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
        ];
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
    && !in_array(false, $checks['database']['tables'], true)
    && $checks['database']['cache_query'] === true;

http_response_code($checks['ok'] ? 200 : 500);
echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
