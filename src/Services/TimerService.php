<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

final class TimerConflictException extends \RuntimeException
{
    public function __construct(public readonly array $session)
    {
        parent::__construct('Another timer session is already active.');
    }
}

final class TimerService
{
    private const LOCK_NAME = 'task_tracker_timer';

    public static function active(): ?array
    {
        return \App\Models\TimerSession::active();
    }

    public static function elapsed(array $session): int
    {
        $start = strtotime((string) $session['started_at'] . ' UTC');
        $end = !empty($session['ended_at'])
            ? strtotime((string) $session['ended_at'] . ' UTC')
            : (!empty($session['duration_seconds']) ? 0 : time());

        if (!empty($session['duration_seconds'])) {
            return (int) $session['duration_seconds'];
        }

        return max(0, $end - $start);
    }

    public static function stop(int $sessionId): bool
    {
        $pdo = Database::connection();
        self::acquireLock($pdo);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM timer_sessions WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $sessionId]);
            $session = $stmt->fetch();

            if (!$session || $session['status'] !== 'running') {
                $pdo->rollBack();
                return false;
            }

            \App\Models\TimerSession::finalize($sessionId, utc_now_string(), 'stopped');
            $pdo->commit();

            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            self::releaseLock($pdo);
        }
    }

    /**
     * Stop any running session and start a new one for the given task atomically.
     * Returns ['started' => int, 'stopped_task_id' => ?int, 'stopped_task_title' => ?string].
     */
    public static function switchTo(int $taskId, ?string $taskTitle = null): array
    {
        $pdo = Database::connection();
        self::acquireLock($pdo);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->query(
                'SELECT s.*, t.title AS task_title FROM timer_sessions s
                 JOIN tasks t ON t.id = s.task_id
                 WHERE s.status = "running" ORDER BY s.id DESC LIMIT 1 FOR UPDATE'
            );
            $existing = $stmt->fetch();

            $stoppedTaskId = null;
            $stoppedTitle = null;
            $alreadyRunningHere = false;

            if ($existing) {
                if ((int) $existing['task_id'] === $taskId) {
                    $alreadyRunningHere = true;
                } else {
                    \App\Models\TimerSession::finalize((int) $existing['id'], utc_now_string(), 'stopped');
                    $stoppedTaskId = (int) $existing['task_id'];
                    $stoppedTitle = (string) $existing['task_title'];
                }
            }

            if (!$alreadyRunningHere) {
                $newId = \App\Models\TimerSession::create($taskId, utc_now_string());
            } else {
                $newId = (int) $existing['id'];
            }

            $pdo->commit();

            return [
                'started' => $newId,
                'stopped_task_id' => $stoppedTaskId,
                'stopped_task_title' => $stoppedTitle,
                'already_running' => $alreadyRunningHere,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            self::releaseLock($pdo);
        }
    }

    private static function acquireLock(\PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT GET_LOCK(:name, 5)');
        $stmt->execute(['name' => self::LOCK_NAME]);
        $result = $stmt->fetchColumn();

        if ((int) $result !== 1) {
            throw new \RuntimeException('Could not acquire timer lock. Please retry.');
        }
    }

    private static function releaseLock(\PDO $pdo): void
    {
        try {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $stmt->execute(['name' => self::LOCK_NAME]);
        } catch (\Throwable $e) {
            error_log('Timer lock release failed: ' . $e->getMessage());
        }
    }
}
