<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public static function html(string $content, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function download(string $body, string $filename, string $mime = 'application/octet-stream'): never
    {
        $safe = preg_replace('/[^\w.\-]+/', '_', $filename) ?: 'download';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Content-Length: ' . strlen($body));
        header('X-Content-Type-Options: nosniff');
        echo $body;
        exit;
    }
}
