<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class TodoItem
{
    public const STATUSES = ['inbox', 'planned', 'converted', 'archived'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM todo_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function all(array $filters = [], bool $includeConverted = true): array
    {
        $sql = 'SELECT * FROM todo_items WHERE 1=1';
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        } elseif (!$includeConverted) {
            $sql .= ' AND status <> "archived"';
        }

        if (!empty($filters['category'])) {
            $sql .= ' AND category = :category';
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['priority']) && in_array($filters['priority'], self::PRIORITIES, true)) {
            $sql .= ' AND priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['q'])) {
            $sql .= ' AND (title LIKE :q OR details LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql .= ' ORDER BY '
            . 'CASE status WHEN "inbox" THEN 0 WHEN "planned" THEN 1 WHEN "converted" THEN 2 ELSE 3 END, '
            . 'CASE priority WHEN "urgent" THEN 0 WHEN "high" THEN 1 WHEN "medium" THEN 2 WHEN "low" THEN 3 ELSE 4 END, '
            . 'created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $now = utc_now_string();
        $stmt = Database::connection()->prepare(
            'INSERT INTO todo_items (title, details, category, priority, status, created_at, updated_at)
             VALUES (:title, :details, :category, :priority, :status, :created_at, :updated_at)'
        );

        $stmt->execute([
            'title' => $data['title'],
            'details' => $data['details'] ?? null,
            'category' => $data['category'] ?? null,
            'priority' => $data['priority'] ?? null,
            'status' => $data['status'] ?? 'inbox',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = ['id' => $id, 'updated_at' => utc_now_string()];
        $allowed = ['title', 'details', 'category', 'priority', 'status', 'converted_task_id'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return;
        }

        $fields[] = 'updated_at = :updated_at';
        $stmt = Database::connection()->prepare('UPDATE todo_items SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public static function markConverted(int $id, int $taskId): void
    {
        self::update($id, ['status' => 'converted', 'converted_task_id' => $taskId]);
    }

    public static function archive(int $id): void
    {
        self::update($id, ['status' => 'archived']);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM todo_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function categories(): array
    {
        $rows = Database::connection()
            ->query('SELECT DISTINCT category FROM todo_items WHERE category IS NOT NULL AND category <> "" ORDER BY category')
            ->fetchAll(\PDO::FETCH_COLUMN);

        return $rows ?: [];
    }

    public static function counts(): array
    {
        $rows = Database::connection()
            ->query('SELECT status, COUNT(*) AS c FROM todo_items GROUP BY status')
            ->fetchAll();

        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['c'];
        }

        return $counts;
    }
}
