<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
define('VIEW_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'views');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'helpers.php';

use App\Support\Config;
use App\Support\Env;

Env::load(BASE_PATH . DIRECTORY_SEPARATOR . '.env');
Config::load(require BASE_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');

date_default_timezone_set('UTC');

$debug = Env::bool('APP_DEBUG', false);

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

$logDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs';
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'app.log');
}

set_exception_handler(function (\Throwable $e) use ($debug): void {
    error_log(sprintf(
        '[%s] %s: %s in %s:%d',
        utc_now_string(),
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    http_response_code(500);

    if ($debug) {
        echo '<h1>500 Server Error</h1>';
        echo '<pre>' . e($e->getMessage()) . "\n\n" . e($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>500 Server Error</h1><p>An unexpected error occurred. Please check the logs.</p>';
    }
    exit;
});
