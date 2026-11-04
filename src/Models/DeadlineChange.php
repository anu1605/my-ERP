<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class DeadlineChange
{
    public static function forTask(int $taskId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM task_deadline_changes WHERE task_id = :task_id ORDER BY changed_at ASC, id ASC'
        );
        $stmt->execute(['task_id' => $taskId]);

        return $stmt->fetchAll();
    }

    public static function countExtensions(int $taskId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM task_deadline_changes
             WHERE task_id = :task_id AND previous_deadline_at IS NOT NULL'
        );
        $stmt->execute(['task_id' => $taskId]);

        return (int) $stmt->fetchColumn();
    }

    public static function missedCount(int $taskId): int
    {
        return count(self::missedChanges($taskId));
    }

    public static function lastMissedDeadline(int $taskId): ?string
    {
        $missed = self::missedChanges($taskId);

        return $missed === [] ? null : (string) end($missed);
    }

    private static function missedChanges(int $taskId): array
    {
        $missed = [];

        foreach (self::forTask($taskId) as $change) {
            if ($change['previous_deadline_at'] === null || $change['previous_deadline_at'] === '') {
                continue;
            }

            $previousDate = to_local($change['previous_deadline_at'], 'Y-m-d');
            $changedDate = to_local($change['changed_at'], 'Y-m-d');

            if ($previousDate < $changedDate) {
                $missed[] = (string) $change['previous_deadline_at'];
            }
        }

        return $missed;
    }

    public static function record(int $taskId, ?string $previousUtc, ?string $newUtc): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO task_deadline_changes (task_id, previous_deadline_at, new_deadline_at, changed_at)
             VALUES (:task_id, :previous, :new, :changed_at)'
        );
        $stmt->execute([
            'task_id' => $taskId,
            'previous' => $previousUtc,
            'new' => $newUtc,
            'changed_at' => utc_now_string(),
        ]);
    }
}
