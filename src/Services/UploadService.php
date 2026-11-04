<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Models\Attachment;
use App\Support\Config;
use App\Support\Settings;

final class UploadValidationException extends \RuntimeException
{
}

final class UploadService
{
    public static function uploadDir(): string
    {
        $configured = (string) Config::get('upload.dir', 'storage/uploads');

        if (preg_match('#^([A-Za-z]:[\\\\/]|[\\\\/])#', $configured) === 1) {
            return rtrim($configured, '/\\');
        }

        return rtrim(base_path($configured), '/\\');
    }

    public static function maxBytes(): int
    {
        return Settings::int('upload_max_bytes', (int) Config::get('upload.max_bytes', 26214400));
    }

    public static function store(array $file, ?int $taskId, ?int $reportId = null): int
    {
        self::assertUploadOk($file);

        $maxBytes = self::maxBytes();
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            throw new UploadValidationException('The uploaded file is empty.');
        }

        if ($size > $maxBytes) {
            throw new UploadValidationException(
                'The ZIP exceeds the configured limit of ' . human_bytes($maxBytes) . '.'
            );
        }

        $originalName = (string) ($file['name'] ?? 'archive.zip');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, (array) Config::get('upload.allowed_ext', ['zip']), true)) {
            throw new UploadValidationException('Only .zip files are allowed.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new UploadValidationException('The upload could not be verified.');
        }

        $realSize = filesize($tmpName);
        if ($realSize === false || $realSize > $maxBytes) {
            throw new UploadValidationException(
                'The ZIP exceeds the configured limit of ' . human_bytes($maxBytes) . '.'
            );
        }

        $mime = self::detectMime($tmpName);
        $allowedMime = (array) Config::get('upload.allowed_mime', []);

        if (!in_array($mime, $allowedMime, true)) {
            throw new UploadValidationException('The file type (' . $mime . ') is not an accepted ZIP type.');
        }

        ZipInspector::assertZipSignature($tmpName);
        $info = ZipInspector::inspect($tmpName);

        $directory = self::uploadDir() . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new UploadValidationException('The upload directory could not be created.');
        }

        $storedName = bin2hex(random_bytes(16)) . '.zip';
        $destination = $directory . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new UploadValidationException('The uploaded file could not be saved.');
        }

        chmod($destination, 0640);

        $sha256 = hash_file('sha256', $destination);

        $relativePath = self::relativeStoragePath($destination);

        try {
            return Attachment::create([
                'task_id' => $taskId,
                'report_id' => $reportId,
                'original_filename' => $originalName,
                'stored_filename' => $storedName,
                'storage_path' => $relativePath,
                'mime_type' => $mime,
                'size_bytes' => (int) $realSize,
                'sha256' => (string) $sha256,
            ]);
        } catch (\Throwable $e) {
            @unlink($destination);
            throw $e;
        }
    }

    public static function delete(int $attachmentId): bool
    {
        $attachment = Attachment::find($attachmentId);
        if ($attachment === null) {
            return false;
        }

        $absolute = self::absolutePathFor($attachment['storage_path']);

        if (is_file($absolute)) {
            @unlink($absolute);
        }

        Attachment::delete($attachmentId);

        return true;
    }

    public static function absolutePathFor(string $storedPath): string
    {
        $storedPath = str_replace(['..', "\0"], '', $storedPath);
        $storedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedPath);

        return storage_path($storedPath);
    }

    private static function relativeStoragePath(string $absolute): string
    {
        $absolute = str_replace('\\', '/', $absolute);
        $storage = str_replace('\\', '/', STORAGE_PATH) . '/';

        if (str_starts_with($absolute, $storage)) {
            return substr($absolute, strlen($storage));
        }

        return ltrim($absolute, '/');
    }

    private static function assertUploadOk(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        $messages = [
            UPLOAD_ERR_INI_SIZE => 'The file exceeds the server upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the file to disk.',
            UPLOAD_ERR_EXTENSION => 'The upload was stopped by a server extension.',
        ];

        throw new UploadValidationException($messages[$error] ?? 'Unknown upload error.');
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }
}
