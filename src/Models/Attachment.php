<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Attachment
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function forTask(int $taskId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM attachments WHERE task_id = :task_id ORDER BY created_at DESC'
        );
        $stmt->execute(['task_id' => $taskId]);

        return $stmt->fetchAll();
    }

    public static function forTasks(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT * FROM attachments WHERE task_id IN ({$placeholders}) ORDER BY created_at DESC"
        );
        $stmt->execute(array_values($taskIds));

        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO attachments
                (task_id, report_id, original_filename, stored_filename, storage_path, mime_type, size_bytes, sha256, created_at)
             VALUES
                (:task_id, :report_id, :original_filename, :stored_filename, :storage_path, :mime_type, :size_bytes, :sha256, :created_at)'
        );

        $stmt->execute([
            'task_id' => $data['task_id'] ?? null,
            'report_id' => $data['report_id'] ?? null,
            'original_filename' => $data['original_filename'],
            'stored_filename' => $data['stored_filename'],
            'storage_path' => $data['storage_path'],
            'mime_type' => $data['mime_type'] ?? null,
            'size_bytes' => $data['size_bytes'],
            'sha256' => $data['sha256'],
            'created_at' => utc_now_string(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
