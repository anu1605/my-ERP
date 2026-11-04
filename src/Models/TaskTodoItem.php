<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class TaskTodoItem
{
    public const MAX_LENGTH = 500;

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM task_todo_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function forTask(int $taskId, bool $done = false): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM task_todo_items
             WHERE task_id = :task_id AND is_done = :is_done
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([
            'task_id' => $taskId,
            'is_done' => $done ? 1 : 0,
        ]);

        return $stmt->fetchAll();
    }

    public static function create(int $taskId, string $body): int
    {
        $body = trim($body);
        $now = utc_now_string();

        $orderStmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM task_todo_items WHERE task_id = :task_id'
        );
        $orderStmt->execute(['task_id' => $taskId]);
        $sortOrder = (int) $orderStmt->fetchColumn();

        $stmt = Database::connection()->prepare(
            'INSERT INTO task_todo_items (task_id, body, is_done, sort_order, created_at, updated_at)
             VALUES (:task_id, :body, 0, :sort_order, :created_at, :updated_at)'
        );
        $stmt->execute([
            'task_id' => $taskId,
            'body' => $body,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function setDone(int $id, bool $done): void
    {
        $now = utc_now_string();
        $params = [
            'id' => $id,
            'is_done' => $done ? 1 : 0,
            'done_at' => $done ? $now : null,
            'updated_at' => $now,
        ];
        $sql = 'UPDATE task_todo_items
                SET is_done = :is_done, done_at = :done_at, updated_at = :updated_at';

        if (!$done) {
            $item = self::find($id);

            if ($item !== null) {
                $orderStmt = Database::connection()->prepare(
                    'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM task_todo_items
                     WHERE task_id = :task_id AND is_done = 0'
                );
                $orderStmt->execute(['task_id' => (int) $item['task_id']]);

                $sql .= ', sort_order = :sort_order';
                $params['sort_order'] = (int) $orderStmt->fetchColumn();
            }
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function reorder(int $taskId, array $ids): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE task_todo_items
             SET sort_order = :sort_order, updated_at = :updated_at
             WHERE id = :id AND task_id = :task_id'
        );

        $pdo->beginTransaction();

        try {
            foreach (array_values($ids) as $index => $id) {
                if (!is_numeric($id)) {
                    continue;
                }

                $stmt->execute([
                    'sort_order' => $index,
                    'updated_at' => utc_now_string(),
                    'id' => (int) $id,
                    'task_id' => $taskId,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM task_todo_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
