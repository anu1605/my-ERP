<?php

declare(strict_types=1);

use App\Support\Config;
use App\Support\Settings;

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
}

function storage_path(string $path = ''): string
{
    return STORAGE_PATH . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.url'), '/');
    $path = '/' . ltrim($path, '/');

    if ($path === '/') {
        return $base;
    }

    return $base . $path;
}

function redirect(string $path, int $status = 302): never
{
    $location = preg_match('#^https?://#i', $path) === 1 ? $path : url($path);
    header('Location: ' . $location, true, $status);
    exit;
}

function old(string $key, mixed $default = ''): string
{
    $old = $_SESSION['_old'][$key] ?? $default;
    return is_scalar($old) ? (string) $old : (string) $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function now_utc(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

function utc_now_string(): string
{
    return now_utc()->format('Y-m-d H:i:s');
}

function app_timezone(): DateTimeZone
{
    $tz = (string) setting('timezone', 'UTC');

    try {
        return new DateTimeZone($tz);
    } catch (\Throwable $e) {
        return new DateTimeZone('UTC');
    }
}

function local_today(): string
{
    return (new DateTimeImmutable('now', app_timezone()))->format('Y-m-d');
}

function to_local(?string $utc, string $format = 'Y-m-d H:i'): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }

    $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $dt->setTimezone(app_timezone())->format($format);
}

function local_to_utc(?string $local): ?string
{
    if ($local === null || $local === '') {
        return null;
    }

    $dt = new DateTimeImmutable($local, app_timezone());
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function days_until_deadline(?string $utc): ?int
{
    if ($utc === null || $utc === '') {
        return null;
    }

    $deadline = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(app_timezone())
        ->setTime(0, 0);
    $today = (new DateTimeImmutable('now', app_timezone()))->setTime(0, 0);

    return (int) $today->diff($deadline)->format('%r%a');
}

function deadline_date_passed(?string $utc): bool
{
    if ($utc === null || $utc === '') {
        return false;
    }

    return to_local($utc, 'Y-m-d') < local_today();
}

function format_duration(?int $seconds): string
{
    $seconds = max(0, (int) $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;

    if ($h > 0) {
        return sprintf('%dh %02dm %02ds', $h, $m, $s);
    }
    if ($m > 0) {
        return sprintf('%dm %02ds', $m, $s);
    }
    return sprintf('%ds', $s);
}

function format_overrun(?int $seconds): string
{
    $seconds = max(0, (int) $seconds);
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);

    if ($d > 0) {
        return sprintf('%dd %dh %dm', $d, $h, $m);
    }
    if ($h > 0) {
        return sprintf('%dh %dm', $h, $m);
    }
    if ($m > 0) {
        return sprintf('%dm', $m);
    }

    return sprintf('%ds', $seconds);
}

function human_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $value = (float) $bytes;

    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }

    return sprintf('%.2f %s', $value, $units[$i]);
}

function session_get(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function keep_old(array $data): void
{
    unset($data['_csrf']);
    unset($data['password']);
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return $errors;
}

function store_errors(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    $titles = [
        400 => 'Bad Request',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        419 => 'Page Expired',
        500 => 'Server Error',
    ];
    $title = $titles[$status] ?? 'Error';

    if (\App\Support\Env::bool('APP_DEBUG', false)) {
        echo "<h1>{$status} {$title}</h1><p>" . e($message) . '</p>';
    } else {
        echo "<h1>{$status} {$title}</h1>";
    }
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
