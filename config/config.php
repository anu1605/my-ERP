<?php

declare(strict_types=1);

use App\Support\Env;

$appUrl = rtrim((string) Env::get('APP_URL', 'http://127.0.0.1:8000'), '/');
$basePath = trim((string) parse_url($appUrl, PHP_URL_PATH), '/');

/**
 * Reads a setting from the environment, falling back to $default when the
 * value is missing or blank. Without this an empty line in .env (e.g.
 * "DB_NAME=") would silently override the default with an empty string.
 */
$setting = static function (string $key, string $default): string {
    $value = trim((string) Env::get($key, ''));

    return $value === '' ? $default : $value;
};

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Task Tracker'),
        'env' => Env::get('APP_ENV', 'local'),
        'debug' => Env::bool('APP_DEBUG', false),
        'url' => $appUrl,
        'base_path' => $basePath === '' ? '' : '/' . $basePath,
        'display_timezone' => Env::get('APP_TIMEZONE', 'UTC'),
        'storage_timezone' => 'UTC',
    ],

    'db' => [
        'host' => $setting('DB_HOST', '127.0.0.1'),
        'port' => (int) $setting('DB_PORT', '3306'),
        'name' => $setting('DB_NAME', 'task_tracker'),
        'user' => $setting('DB_USER', 'root'),
        'pass' => trim((string) Env::get('DB_PASS', '')),
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name' => Env::get('SESSION_NAME', 'task_tracker_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 1209600),
    ],

    'upload' => [
        'max_bytes' => (int) Env::get('UPLOAD_MAX_BYTES', 26214400),
        'dir' => Env::get('UPLOAD_DIR', 'storage/uploads'),
        'allowed_ext' => ['zip'],
        'allowed_mime' => [
            'application/zip',
            'application/x-zip-compressed',
            'application/x-zip',
            'multipart/x-zip',
            'application/octet-stream',
        ],
    ],

    'zip' => [
        'max_entries' => (int) Env::get('ZIP_MAX_ENTRIES', 5000),
        'max_uncompressed_bytes' => (int) Env::get('ZIP_MAX_UNCOMPRESSED_BYTES', 262144000),
        'max_entry_bytes' => (int) Env::get('ZIP_MAX_ENTRY_BYTES', 52428800),
        'max_ratio' => (int) Env::get('ZIP_MAX_RATIO', 200),
    ],
];
