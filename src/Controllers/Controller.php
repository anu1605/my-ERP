<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Support\View;

abstract class Controller
{
    protected function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        echo View::render($view, $data, $layout);
    }

    protected function back(Request $request, string $default = '/'): never
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if ($referer !== '' && str_contains($referer, $host)) {
            redirect($referer);
        }

        redirect($default);
    }

    protected function requirePost(Request $request): void
    {
        if (!$request->isPost()) {
            abort(405, 'This action requires a POST request.');
        }
    }
}
