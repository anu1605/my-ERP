<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class SlowPerformance
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            self::selectSql() . ' WHERE sp.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByTask(int $taskId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM slow_performance WHERE task_id = :task_id'
        );
        $stmt->execute(['task_id' => $taskId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function open(): array
    {
        return Database::connection()
            ->query(self::selectSql() . ' WHERE sp.resolved_at IS NULL ORDER BY sp.flagged_at DESC, sp.id DESC')
            ->fetchAll();
    }

    public static function resolved(): array
    {
        return Database::connection()
            ->query(self::selectSql() . ' WHERE sp.resolved_at IS NOT NULL ORDER BY sp.resolved_at DESC, sp.id DESC')
            ->fetchAll();
    }

    public static function all(): array
    {
        return Database::connection()
            ->query(
                self::selectSql()
                . ' ORDER BY sp.resolved_at IS NULL DESC, sp.resolved_at DESC, sp.flagged_at DESC, sp.id DESC'
            )
            ->fetchAll();
    }

    public static function countOpen(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM slow_performance WHERE resolved_at IS NULL')
            ->fetchColumn();
    }

    public static function countResolved(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM slow_performance WHERE resolved_at IS NOT NULL')
            ->fetchColumn();
    }

    public static function create(int $taskId, int $exceededCount, ?string $missedDeadlineAt, int $overrunSeconds): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO slow_performance
                (task_id, exceeded_count, deadline_at, overrun_seconds, flagged_at)
             VALUES (:task_id, :exceeded_count, :deadline_at, :overrun_seconds, :flagged_at)'
        );
        $stmt->execute([
            'task_id' => $taskId,
            'exceeded_count' => $exceededCount,
            'deadline_at' => $missedDeadlineAt,
            'overrun_seconds' => $overrunSeconds,
            'flagged_at' => utc_now_string(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updateMetrics(int $id, int $exceededCount, ?string $missedDeadlineAt, int $overrunSeconds): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE slow_performance
             SET exceeded_count = :exceeded_count,
                 deadline_at = :deadline_at,
                 overrun_seconds = :overrun_seconds
             WHERE id = :id AND resolved_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'exceeded_count' => $exceededCount,
            'deadline_at' => $missedDeadlineAt,
            'overrun_seconds' => $overrunSeconds,
        ]);
    }

    public static function resolve(int $id, string $reason): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE slow_performance
             SET resolved_at = :resolved_at, resolution_reason = :reason
             WHERE id = :id AND resolved_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'reason' => $reason,
            'resolved_at' => utc_now_string(),
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM slow_performance WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function deleteByTask(int $taskId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM slow_performance WHERE task_id = :task_id');
        $stmt->execute(['task_id' => $taskId]);
    }

    private static function selectSql(): string
    {
        return 'SELECT sp.*, '
            . 't.title AS task_title, t.description AS task_description, t.status AS task_status, '
            . 't.priority AS task_priority, t.category AS task_category, t.deadline_at AS task_deadline_at '
            . 'FROM slow_performance sp JOIN tasks t ON t.id = sp.task_id';
    }
}
