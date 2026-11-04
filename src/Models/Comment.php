<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Comment
{
    public const KINDS = ['general', 'progress', 'blocker', 'decision'];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function forTask(int $taskId, string $order = 'desc'): array
    {
        $direction = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        $stmt = Database::connection()->prepare(
            "SELECT * FROM comments WHERE task_id = :task_id ORDER BY created_at {$direction}, id {$direction}"
        );
        $stmt->execute(['task_id' => $taskId]);

        return $stmt->fetchAll();
    }

    public static function create(int $taskId, string $body, string $kind = 'general'): int
    {
        if (!in_array($kind, self::KINDS, true)) {
            $kind = 'general';
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO comments (task_id, body, kind, created_at) VALUES (:task_id, :body, :kind, :created_at)'
        );
        $stmt->execute([
            'task_id' => $taskId,
            'body' => $body,
            'kind' => $kind,
            'created_at' => utc_now_string(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function recent(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT c.*, t.title AS task_title, t.id AS task_id
             FROM comments c JOIN tasks t ON t.id = c.task_id
             ORDER BY c.created_at DESC, c.id DESC LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function betweenUtc(string $startUtc, string $endUtc): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, t.title AS task_title
             FROM comments c JOIN tasks t ON t.id = c.task_id
             WHERE c.created_at >= :start AND c.created_at < :end
             ORDER BY c.created_at ASC'
        );
        $stmt->execute(['start' => $startUtc, 'end' => $endUtc]);

        return $stmt->fetchAll();
    }

    public static function forTasksBetween(array $taskIds, string $startUtc, string $endUtc): array
    {
        if ($taskIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT * FROM comments WHERE task_id IN ({$placeholders})
             AND created_at >= ? AND created_at < ? ORDER BY created_at ASC"
        );
        $stmt->execute([...array_values($taskIds), $startUtc, $endUtc]);

        return $stmt->fetchAll();
    }
}
