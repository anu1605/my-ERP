<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = [], ?string $layout = 'layout'): string
    {
        $data = array_merge(self::$shared, $data);
        $content = self::capture($view, $data);

        if ($layout === null) {
            return $content;
        }

        $data['content'] = $content;
        return self::capture($layout, $data);
    }

    public static function capture(string $view, array $data = []): string
    {
        $file = VIEW_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
