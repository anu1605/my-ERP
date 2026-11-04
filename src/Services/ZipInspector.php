<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config;

final class ZipValidationException extends \RuntimeException
{
}

final class ZipInspector
{
    private const SIGNATURES = ["PK\x03\x04", "PK\x05\x06", "PK\x06\x06", "PK\x07\x08"];

    public static function assertZipSignature(string $path): void
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new ZipValidationException('Could not read the uploaded file.');
        }

        $header = fread($handle, 4);
        fclose($handle);

        if ($header === false || strlen($header) < 4) {
            throw new ZipValidationException('The uploaded file is too small to be a valid ZIP archive.');
        }

        if (!in_array($header, self::SIGNATURES, true)) {
            throw new ZipValidationException('The file is not a valid ZIP archive (bad signature).');
        }
    }

    public static function inspect(string $path): array
    {
        $maxEntries = (int) Config::get('zip.max_entries', 5000);
        $maxTotal = (int) Config::get('zip.max_uncompressed_bytes', 262144000);
        $maxEntry = (int) Config::get('zip.max_entry_bytes', 52428800);
        $maxRatio = (int) Config::get('zip.max_ratio', 200);

        $zip = new \ZipArchive();
        $opened = $zip->open($path);

        if ($opened !== true) {
            throw new ZipValidationException('The archive is corrupted or in an unsupported format.');
        }

        try {
            $count = $zip->numFiles;

            if ($count === false) {
                throw new ZipValidationException('Could not read the archive contents.');
            }

            if ($count > $maxEntries) {
                throw new ZipValidationException(
                    "The archive contains too many entries ({$count}); the limit is {$maxEntries}."
                );
            }

            $totalUncompressed = 0;
            $totalCompressed = 0;
            $deadline = microtime(true) + 5.0;
            $entries = [];

            for ($i = 0; $i < $count; $i++) {
                if (microtime(true) > $deadline) {
                    throw new ZipValidationException('Archive inspection took too long; it was rejected.');
                }

                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new ZipValidationException('Could not read an archive entry (corrupted archive).');
                }

                $flags = (int) ($stat['flags'] ?? 0);
                $encryption = (int) ($stat['encryption_method'] ?? 0);

                if (($flags & 0x1) === 0x1 || $encryption !== 0) {
                    throw new ZipValidationException('Encrypted ZIP archives are not supported.');
                }

                $size = (int) ($stat['size'] ?? 0);
                $compSize = (int) ($stat['comp_size'] ?? 0);

                if ($size > $maxEntry) {
                    throw new ZipValidationException(
                        'An entry exceeds the maximum allowed size: ' . $stat['name']
                    );
                }

                $totalUncompressed += $size;
                $totalCompressed += $compSize;

                if ($totalUncompressed > $maxTotal) {
                    throw new ZipValidationException('The archive exceeds the maximum total uncompressed size.');
                }

                $entries[] = [
                    'name' => $stat['name'],
                    'size' => $size,
                    'compressed_size' => $compSize,
                ];
            }

            if ($maxRatio > 0 && $totalCompressed > 0) {
                $ratio = $totalUncompressed / max(1, $totalCompressed);
                if ($ratio > $maxRatio) {
                    throw new ZipValidationException(
                        sprintf('The archive compression ratio (%.1f:1) looks like a ZIP bomb.', $ratio)
                    );
                }
            }

            return [
                'entries' => $count,
                'total_uncompressed' => $totalUncompressed,
                'total_compressed' => $totalCompressed,
                'listed' => $entries,
            ];
        } finally {
            $zip->close();
        }
    }
}
