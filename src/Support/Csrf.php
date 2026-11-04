<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Request;

final class Csrf
{
    public static function check(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = (string) $request->input('_csrf', '');
        $session = (string) ($_SESSION['_csrf'] ?? '');

        if ($token === '' || $session === '' || !hash_equals($session, $token)) {
            if ($request->wantsJson()) {
                \App\Http\Response::json(['error' => 'CSRF token mismatch. Please refresh the page.'], 419);
            }

            abort(419, 'Your session expired. Please go back, refresh, and try again.');
        }
    }
}
