<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TimerSession;

final class TimesheetService
{
    /**
     * Build a task timesheet: session log, per-day totals (split across
     * midnight correctly) and the overall total for the selected range.
     */
    public static function forTask(int $taskId, ?string $fromLocal = null, ?string $toLocal = null): array
    {
        if ($fromLocal !== null && $toLocal !== null && $fromLocal > $toLocal) {
            [$fromLocal, $toLocal] = [$toLocal, $fromLocal];
        }

        $fromUtc = $fromLocal !== null ? ReportService::dayRangeUtc($fromLocal)['start'] : null;
        $toUtc = $toLocal !== null ? ReportService::dayRangeUtc($toLocal)['end'] : null;

        $sessions = TimerSession::forTaskBetween($taskId, $fromUtc, $toUtc);

        $rows = [];
        $days = [];
        $total = 0;
        $localZone = app_timezone();
        $utcZone = new \DateTimeZone('UTC');

        foreach ($sessions as $session) {
            $startedUtc = (string) $session['started_at'];
            $endedUtc = $session['ended_at'] !== null ? (string) $session['ended_at'] : null;
            $running = $session['status'] === 'running';

            $duration = $session['duration_seconds'] !== null
                ? (int) $session['duration_seconds']
                : max(0, time() - strtotime($startedUtc . ' UTC'));

            $rows[] = [
                'id' => (int) $session['id'],
                'started_at' => $startedUtc,
                'ended_at' => $endedUtc,
                'duration' => $duration,
                'status' => (string) $session['status'],
                'running' => $running,
            ];

            $endLocal = $endedUtc !== null
                ? (new \DateTimeImmutable($endedUtc, $utcZone))->setTimezone($localZone)
                : new \DateTimeImmutable('now', $localZone);

            $cursor = (new \DateTimeImmutable($startedUtc, $utcZone))->setTimezone($localZone);
            $lastDay = $endLocal->format('Y-m-d');
            $guard = 0;

            while ($guard < 400) {
                $day = $cursor->format('Y-m-d');

                if (($fromLocal === null || $day >= $fromLocal) && ($toLocal === null || $day <= $toLocal)) {
                    $seconds = ReportService::overlapSeconds($startedUtc, $endedUtc, ReportService::dayRangeUtc($day));
                    if ($seconds > 0) {
                        $days[$day] = ($days[$day] ?? 0) + $seconds;
                        $total += $seconds;
                    }
                }

                if ($day >= $lastDay) {
                    break;
                }

                $cursor = $cursor->modify('+1 day');
                $guard++;
            }
        }

        krsort($days);

        $dayRows = [];
        foreach ($days as $date => $seconds) {
            $dayRows[] = ['date' => $date, 'seconds' => $seconds];
        }

        return [
            'sessions' => $rows,
            'days' => $dayRows,
            'total' => $total,
            'from' => $fromLocal,
            'to' => $toLocal,
        ];
    }
}
