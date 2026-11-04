<?php

declare(strict_types=1);

namespace App\Support;

final class Auth
{
    public static function enabled(): bool
    {
        return (string) Env::get('APP_PASSWORD', '') !== '';
    }

    public static function check(): bool
    {
        return !self::enabled() || (($_SESSION['authed'] ?? false) === true);
    }

    public static function attempt(string $password): bool
    {
        $expected = (string) Env::get('APP_PASSWORD', '');

        if ($expected === '' || !hash_equals($expected, $password)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['authed'] = true;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['authed']);
        session_regenerate_id(true);
    }
}
