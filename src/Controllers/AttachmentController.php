<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Models\Attachment;
use App\Models\Task;
use App\Services\UploadService;
use App\Services\UploadValidationException;

final class AttachmentController extends Controller
{
    public function store(Request $request): void
    {
        $taskId = $request->routeInt('id');
        $task = Task::find($taskId);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        $file = $request->file('zip');

        if ($file === null) {
            $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            $postMax = self::iniBytes((string) ini_get('post_max_size'));

            if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax) {
                flash('error', 'The upload exceeded the server post_max_size limit (' . ini_get('post_max_size') . ').');
            } else {
                flash('error', 'Please choose a ZIP file to upload.');
            }

            redirect('/tasks/' . $taskId);
        }

        try {
            UploadService::store($file, $taskId);
            flash('success', 'ZIP uploaded and validated.');
        } catch (UploadValidationException $e) {
            flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Upload failed: ' . $e->getMessage());
            flash('error', 'The upload could not be processed.');
        }

        redirect('/tasks/' . $taskId);
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function download(Request $request): void
    {
        $id = $request->routeInt('id');
        $attachment = Attachment::find($id);

        if ($attachment === null) {
            abort(404, 'Attachment not found.');
        }

        $absolute = UploadService::absolutePathFor((string) $attachment['storage_path']);
        $real = realpath($absolute);
        $storageReal = realpath(STORAGE_PATH);

        if ($real === false || $storageReal === false || !str_starts_with($real, $storageReal)) {
            abort(404, 'Attachment file is missing.');
        }

        $mime = $attachment['mime_type'] ?: 'application/zip';
        $filename = (string) $attachment['original_filename'];

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w.\-]+/', '_', $filename) . '"');
        header('Content-Length: ' . filesize($real));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($real);
        exit;
    }

    public function destroy(Request $request): void
    {
        $id = $request->routeInt('id');
        $attachment = Attachment::find($id);

        if ($attachment === null) {
            abort(404, 'Attachment not found.');
        }

        $taskId = (int) $attachment['task_id'];
        UploadService::delete($id);
        flash('success', 'Attachment deleted.');
        redirect('/tasks/' . $taskId);
    }
}
