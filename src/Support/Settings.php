<?php

declare(strict_types=1);

namespace App\Support;

use App\Database;

final class Settings
{
    private static ?array $cache = null;

    private const DEFAULTS = [
        'timezone' => null,
        'upload_max_bytes' => null,
    ];

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];

        try {
            $pdo = Database::connection();
            $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable $e) {
            self::$cache = [];
        }

        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::load();

        if (array_key_exists($key, $values) && $values[$key] !== null && $values[$key] !== '') {
            return $values[$key];
        }

        $configDefault = match ($key) {
            'timezone' => Config::get('app.display_timezone'),
            'upload_max_bytes' => Config::get('upload.max_bytes'),
            default => null,
        };

        return $configDefault ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value === null ? $default : (int) $value;
    }

    public static function set(string $key, ?string $value): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute(['k' => $key, 'v' => $value]);

        self::$cache = null;
    }

    public static function all(): array
    {
        return [
            'timezone' => (string) self::get('timezone'),
            'upload_max_bytes' => self::int('upload_max_bytes'),
        ];
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
