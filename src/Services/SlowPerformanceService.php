<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Models\DeadlineChange;
use App\Models\SlowPerformance;
use App\Models\Task;

final class SlowPerformanceService
{
    public const THRESHOLD = 2;

    public static function evaluateTask(int $taskId): void
    {
        $task = Task::find($taskId);

        if ($task === null) {
            return;
        }

        if (in_array($task['status'], ['completed', 'cancelled'], true)) {
            SlowPerformance::deleteByTask($taskId);
            return;
        }

        $missed = DeadlineChange::missedCount($taskId);
        $lastMissed = DeadlineChange::lastMissedDeadline($taskId);
        $overdue = deadline_date_passed($task['deadline_at'] ?? null);

        if ($overdue) {
            $missed++;
            if ($lastMissed === null || $task['deadline_at'] > $lastMissed) {
                $lastMissed = (string) $task['deadline_at'];
            }
        }

        $existing = SlowPerformance::findByTask($taskId);

        if ($missed < self::THRESHOLD) {
            if ($existing !== null) {
                SlowPerformance::delete((int) $existing['id']);
            }

            return;
        }

        $overrun = $lastMissed === null ? 0 : max(0, now_utc()->getTimestamp() - self::toTimestamp($lastMissed));

        if ($existing === null) {
            SlowPerformance::create($taskId, $missed, $lastMissed, $overrun);
            return;
        }

        if ($existing['resolved_at'] === null) {
            SlowPerformance::updateMetrics((int) $existing['id'], $missed, $lastMissed, $overrun);
        }
    }

    public static function syncAll(): void
    {
        $ids = Database::connection()
            ->query(
                'SELECT id FROM tasks
                 WHERE deadline_at IS NOT NULL AND status NOT IN ("completed","cancelled")'
            )
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($ids as $taskId) {
            self::evaluateTask((int) $taskId);
        }
    }

    public static function toMarkdown(array $entries): string
    {
        $openCount = count(array_filter($entries, static fn (array $entry): bool => empty($entry['resolved_at'])));

        $lines = [];
        $lines[] = '# Slow Performance Report';
        $lines[] = '';
        $lines[] = '- Generated: ' . to_local(utc_now_string(), 'Y-m-d H:i');
        $lines[] = '- Total flagged tasks: ' . count($entries);
        $lines[] = '- Unresolved: ' . $openCount;
        $lines[] = '- Resolved: ' . (count($entries) - $openCount);
        $lines[] = '';

        if ($entries === []) {
            $lines[] = '_No slow performance entries._';
        }

        foreach ($entries as $entry) {
            $resolved = !empty($entry['resolved_at']);

            $lines[] = '## ' . $entry['task_title'] . ' (task #' . (int) $entry['task_id'] . ')';
            $lines[] = '';
            $lines[] = '- Task link: ' . url('/tasks/' . (int) $entry['task_id']);
            $lines[] = '- Task status: ' . $entry['task_status'];
            $lines[] = '- Deadline exceeded: ' . (int) $entry['exceeded_count'] . ' times';
            $lines[] = '- Last missed deadline: '
                . ($entry['deadline_at'] !== null ? to_local($entry['deadline_at'], 'Y-m-d H:i') : 'n/a');
            $lines[] = '- Deadline overrun: ' . format_overrun((int) $entry['overrun_seconds']);
            $lines[] = '- Flagged at: ' . to_local($entry['flagged_at'], 'Y-m-d H:i');
            $lines[] = '- Resolution status: '
                . ($resolved ? 'Resolved on ' . to_local($entry['resolved_at'], 'Y-m-d H:i') : 'Unresolved');
            $lines[] = '- Reason for delay: '
                . ($resolved
                    ? str_replace("\n", ' ', (string) $entry['resolution_reason'])
                    : '_Not provided yet._');
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function toTimestamp(string $utc): int
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->getTimestamp();
    }
}
