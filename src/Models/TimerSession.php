<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class TimerSession
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM timer_sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function active(): ?array
    {
        $stmt = Database::connection()->query(
            'SELECT s.*, t.title AS task_title
             FROM timer_sessions s
             JOIN tasks t ON t.id = s.task_id
             WHERE s.status = "running"
             ORDER BY s.id DESC
             LIMIT 1'
        );
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function forTask(int $taskId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT * FROM timer_sessions WHERE task_id = :task_id ORDER BY started_at DESC LIMIT ' . $limit
        );
        $stmt->execute(['task_id' => $taskId]);

        return $stmt->fetchAll();
    }

    public static function forTaskBetween(int $taskId, ?string $fromUtc = null, ?string $toUtc = null): array
    {
        $sql = 'SELECT * FROM timer_sessions WHERE task_id = :task_id';
        $params = ['task_id' => $taskId];

        if ($toUtc !== null) {
            $sql .= ' AND started_at < :to';
            $params['to'] = $toUtc;
        }
        if ($fromUtc !== null) {
            $sql .= ' AND COALESCE(ended_at, :now) > :from';
            $params['from'] = $fromUtc;
            $params['now'] = utc_now_string();
        }

        $sql .= ' ORDER BY started_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(int $taskId, string $startedAt): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO timer_sessions (task_id, started_at, status, created_at)
             VALUES (:task_id, :started_at, "running", :created_at)'
        );

        $now = utc_now_string();
        $stmt->execute([
            'task_id' => $taskId,
            'started_at' => $startedAt,
            'created_at' => $now,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function finalize(int $id, string $endUtc, string $status): int
    {
        $session = self::find($id);
        if ($session === null) {
            return 0;
        }

        $start = strtotime((string) $session['started_at'] . ' UTC');
        $end = strtotime($endUtc . ' UTC');
        $duration = max(0, $end - $start);

        $stmt = Database::connection()->prepare(
            'UPDATE timer_sessions
             SET ended_at = :ended_at, duration_seconds = :duration, status = :status
             WHERE id = :id'
        );

        $stmt->execute([
            'ended_at' => $endUtc,
            'duration' => $duration,
            'status' => $status,
            'id' => $id,
        ]);

        return $duration;
    }

    public static function inRange(string $rangeStartUtc, string $rangeEndUtc, string $nowUtc): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.*, t.title AS task_title, t.id AS task_id
             FROM timer_sessions s
             JOIN tasks t ON t.id = s.task_id
             WHERE s.status IN ("stopped","running")
               AND s.started_at < :range_end
               AND COALESCE(s.ended_at, :now) > :range_start
             ORDER BY s.started_at ASC'
        );
        $stmt->execute([
            'range_start' => $rangeStartUtc,
            'range_end' => $rangeEndUtc,
            'now' => $nowUtc,
        ]);

        return $stmt->fetchAll();
    }
}
