<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Task
{
    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function all(array $filters = [], bool $completedOnly = false): array
    {
        $sql = 'SELECT t.* FROM tasks t WHERE 1=1';
        $params = [];

        if ($completedOnly) {
            $sql .= ' AND t.status = "completed"';
        } else {
            $sql .= ' AND t.status <> "completed"';
        }

        if (!$completedOnly && !empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND t.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $sql .= ' AND t.category = :category';
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['priority']) && in_array($filters['priority'], self::PRIORITIES, true)) {
            $sql .= ' AND t.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['q'])) {
            $sql .= ' AND t.title LIKE :q';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['due'])) {
            $todayUtc = self::localDayToUtcRange(local_today());
            if ($filters['due'] === 'today') {
                $sql .= ' AND t.deadline_at >= :dstart AND t.deadline_at < :dend';
                $params['dstart'] = $todayUtc[0];
                $params['dend'] = $todayUtc[1];
            } elseif ($filters['due'] === 'overdue') {
                $sql .= ' AND t.deadline_at IS NOT NULL AND t.deadline_at < :now'
                    . ' AND t.status NOT IN ("completed","cancelled")';
                $params['now'] = utc_now_string();
            }
        }

        $sql .= ' ORDER BY '
            . 'CASE t.status WHEN "in_progress" THEN 0 WHEN "pending" THEN 1 WHEN "completed" THEN 2 ELSE 3 END, '
            . 't.deadline_at IS NULL, t.deadline_at ASC, t.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $now = utc_now_string();
        $stmt = Database::connection()->prepare(
            'INSERT INTO tasks (title, description, status, priority, category, deadline_at, created_at, updated_at)
             VALUES (:title, :description, :status, :priority, :category, :deadline_at, :created_at, :updated_at)'
        );

        $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? null,
            'category' => $data['category'] ?? null,
            'deadline_at' => $data['deadline_at'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) Database::connection()->lastInsertId();

        if (!empty($data['deadline_at'])) {
            DeadlineChange::record($id, null, (string) $data['deadline_at']);
        }

        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $current = self::find($id);

        $fields = [];
        $params = ['id' => $id, 'updated_at' => utc_now_string()];
        $allowed = ['title', 'description', 'status', 'priority', 'category', 'deadline_at'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        $fields[] = 'updated_at = :updated_at';

        $stmt = Database::connection()->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);

        if (array_key_exists('deadline_at', $data) && $current !== null) {
            $previous = $current['deadline_at'] !== null ? (string) $current['deadline_at'] : null;
            $new = $data['deadline_at'] !== null ? (string) $data['deadline_at'] : null;

            if ($previous !== $new) {
                DeadlineChange::record($id, $previous, $new);
            }
        }
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function countsByStatus(): array
    {
        $rows = Database::connection()
            ->query('SELECT status, COUNT(*) AS c FROM tasks GROUP BY status')
            ->fetchAll();

        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['c'];
        }

        return $counts;
    }

    public static function dueTodayOrOverdue(): array
    {
        [$start, $end] = self::localDayToUtcRange(local_today());

        $sql = 'SELECT * FROM tasks
                WHERE deadline_at IS NOT NULL
                  AND deadline_at < :end
                  AND status NOT IN ("completed","cancelled")
                ORDER BY deadline_at ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['end' => $end]);

        return $stmt->fetchAll();
    }

    public static function categories(): array
    {
        $rows = Database::connection()
            ->query('SELECT DISTINCT category FROM tasks WHERE category IS NOT NULL AND category <> "" ORDER BY category')
            ->fetchAll(\PDO::FETCH_COLUMN);

        return $rows ?: [];
    }

    public static function localDayToUtcRange(string $localDate): array
    {
        $tz = app_timezone();
        $start = new \DateTimeImmutable($localDate . ' 00:00:00', $tz);
        $end = $start->modify('+1 day');

        $utc = new \DateTimeZone('UTC');

        return [
            $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            $end->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }
}
