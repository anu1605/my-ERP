<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Task;
use App\Models\TimerSession;

final class ReportService
{
    public static function dayRangeUtc(string $localDate): array
    {
        $tz = app_timezone();
        $start = new \DateTimeImmutable($localDate . ' 00:00:00', $tz);
        $end = $start->modify('+1 day');
        $utc = new \DateTimeZone('UTC');

        return [
            'start' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
            'start_ts' => $start->getTimestamp(),
            'end_ts' => $end->getTimestamp(),
        ];
    }

    public static function overlapSeconds(string $sessionStartUtc, ?string $sessionEndUtc, array $range): int
    {
        $start = strtotime($sessionStartUtc . ' UTC');
        $end = $sessionEndUtc !== null ? strtotime($sessionEndUtc . ' UTC') : time();

        $from = max($start, $range['start_ts']);
        $to = min($end, $range['end_ts']);

        return max(0, $to - $from);
    }

    public static function durationsForDay(string $localDate): array
    {
        $range = self::dayRangeUtc($localDate);
        $nowUtc = utc_now_string();
        $sessions = TimerSession::inRange($range['start'], $range['end'], $nowUtc);

        $perTask = [];
        $total = 0;

        foreach ($sessions as $session) {
            $seconds = self::overlapSeconds(
                (string) $session['started_at'],
                $session['ended_at'] !== null ? (string) $session['ended_at'] : null,
                $range
            );

            if ($seconds <= 0) {
                continue;
            }

            $taskId = (int) $session['task_id'];
            $perTask[$taskId] = ($perTask[$taskId] ?? 0) + $seconds;
            $total += $seconds;
        }

        return ['per_task' => $perTask, 'total' => $total];
    }

    public static function build(string $localDate, array $options = []): array
    {
        $range = self::dayRangeUtc($localDate);
        $nowUtc = utc_now_string();
        $includeEarlier = (bool) ($options['include_earlier'] ?? false);

        $durations = self::durationsForDay($localDate);
        $perTask = $durations['per_task'];

        $sessions = TimerSession::inRange($range['start'], $range['end'], $nowUtc);
        $commentsToday = Comment::betweenUtc($range['start'], $range['end']);

        $taskIds = [];

        foreach ($perTask as $taskId => $seconds) {
            $taskIds[$taskId] = true;
        }
        foreach ($commentsToday as $comment) {
            $taskIds[(int) $comment['task_id']] = true;
        }

        $taskIds = array_keys($taskIds);

        $commentsByTask = [];
        if ($includeEarlier && $taskIds !== []) {
            $all = Comment::forTasksBetween($taskIds, '1970-01-01 00:00:00', $nowUtc);
            foreach ($all as $comment) {
                $commentsByTask[(int) $comment['task_id']][] = $comment;
            }
        } else {
            foreach ($commentsToday as $comment) {
                $commentsByTask[(int) $comment['task_id']][] = $comment;
            }
        }

        $attachmentsByTask = [];
        foreach (Attachment::forTasks($taskIds) as $attachment) {
            $attachmentsByTask[(int) $attachment['task_id']][] = $attachment;
        }

        $tasks = [];

        foreach ($taskIds as $taskId) {
            $task = Task::find((int) $taskId);
            if ($task === null) {
                continue;
            }

            $dayComments = $commentsByTask[$taskId] ?? [];

            if ($includeEarlier) {
                $earlier = array_filter(
                    $dayComments,
                    fn (array $c): bool => $c['created_at'] < $range['start']
                );
                $today = array_filter(
                    $dayComments,
                    fn (array $c): bool => $c['created_at'] >= $range['start']
                );
            } else {
                $earlier = [];
                $today = $dayComments;
            }

            $tasks[] = [
                'id' => (int) $task['id'],
                'title' => $task['title'],
                'description' => $task['description'],
                'status' => $task['status'],
                'priority' => $task['priority'],
                'category' => $task['category'],
                'deadline_at' => $task['deadline_at'],
                'link' => url('/tasks/' . $task['id']),
                'seconds' => $perTask[$taskId] ?? 0,
                'time' => format_duration($perTask[$taskId] ?? 0),
                'comments' => array_values($today),
                'earlier_comments' => array_values($earlier),
                'attachments' => $attachmentsByTask[$taskId] ?? [],
            ];
        }

        usort($tasks, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);

        return [
            'report_date' => $localDate,
            'generated_at' => to_local(utc_now_string(), 'Y-m-d H:i:s'),
            'timezone' => (string) setting('timezone'),
            'tasks' => $tasks,
            'total_seconds' => $durations['total'],
            'total_time' => format_duration($durations['total']),
            'comment_count' => count($commentsToday),
            'planned_target' => $options['planned_target'] ?? null,
            'target_hit_status' => $options['target_hit_status'] ?? null,
            'accomplishments' => $options['accomplishments'] ?? null,
            'blockers' => $options['blockers'] ?? null,
            'next_steps' => $options['next_steps'] ?? null,
        ];
    }

    public static function toMarkdown(array $report): string
    {
        $lines = [];
        $lines[] = '# Daily Progress Report — ' . $report['report_date'];
        $lines[] = '';
        $lines[] = '- Generated: ' . $report['generated_at'] . ' (' . $report['timezone'] . ')';
        $lines[] = '- Total tracked time: **' . $report['total_time'] . '**';
        $lines[] = '- Tasks worked on: ' . count($report['tasks']);
        $lines[] = '';

        if (!empty($report['planned_target'])) {
            $lines[] = '## Planned Target';
            $lines[] = '';
            $lines[] = (string) $report['planned_target'];
            $lines[] = '';
        }

        if (!empty($report['target_hit_status'])) {
            $lines[] = '- Target hit status: **' . $report['target_hit_status'] . '**';
            $lines[] = '';
        }

        foreach ($report['tasks'] as $task) {
            $lines[] = '## [' . $task['title'] . '](' . $task['link'] . ')';
            $lines[] = '';
            $lines[] = '- Task ID: `' . $task['id'] . '`';
            $lines[] = '- Status: ' . $task['status'];
            $lines[] = '- Time today: **' . $task['time'] . '**';

            if (!empty($task['category'])) {
                $lines[] = '- Category: ' . $task['category'];
            }
            if (!empty($task['priority'])) {
                $lines[] = '- Priority: ' . $task['priority'];
            }
            if (!empty($task['deadline_at'])) {
                $lines[] = '- Deadline: ' . to_local($task['deadline_at']);
            }

            $lines[] = '- Link: ' . $task['link'];
            $lines[] = '';

            if (!empty($task['description'])) {
                $lines[] = '**Target / summary:**';
                $lines[] = '';
                $lines[] = (string) $task['description'];
                $lines[] = '';
            }

            if (!empty($task['comments'])) {
                $lines[] = '**Comments (' . count($task['comments']) . '):**';
                $lines[] = '';
                foreach ($task['comments'] as $comment) {
                    $lines[] = '- `' . to_local($comment['created_at']) . '` [' . $comment['kind'] . '] '
                        . str_replace("\n", ' ', (string) $comment['body']);
                }
                $lines[] = '';
            } else {
                $lines[] = '_No comments added today._';
                $lines[] = '';
            }

            if (!empty($task['earlier_comments'])) {
                $lines[] = '**Earlier comments:**';
                $lines[] = '';
                foreach ($task['earlier_comments'] as $comment) {
                    $lines[] = '- `' . to_local($comment['created_at']) . '` [' . $comment['kind'] . '] '
                        . str_replace("\n", ' ', (string) $comment['body']);
                }
                $lines[] = '';
            }

            if (!empty($task['attachments'])) {
                $lines[] = '**Attachments:**';
                $lines[] = '';
                foreach ($task['attachments'] as $attachment) {
                    $lines[] = '- ' . $attachment['original_filename']
                        . ' (' . human_bytes((int) $attachment['size_bytes']) . ')'
                        . ' — sha256 `' . $attachment['sha256'] . '`'
                        . ' — ' . url('/attachments/' . $attachment['id'] . '/download');
                }
                $lines[] = '';
            }
        }

        if (!empty($report['accomplishments'])) {
            $lines[] = '## Accomplishments';
            $lines[] = '';
            $lines[] = (string) $report['accomplishments'];
            $lines[] = '';
        }
        if (!empty($report['blockers'])) {
            $lines[] = '## Blockers';
            $lines[] = '';
            $lines[] = (string) $report['blockers'];
            $lines[] = '';
        }
        if (!empty($report['next_steps'])) {
            $lines[] = '## Next Steps';
            $lines[] = '';
            $lines[] = (string) $report['next_steps'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public static function toText(array $report): string
    {
        $lines = [];

        foreach ($report['tasks'] as $task) {
            $lines[] = $task['title'] . ' - ' . $task['time'];

            foreach ($task['comments'] as $comment) {
                $lines[] = (string) $comment['body'];
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    public static function toJson(array $report): string
    {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
