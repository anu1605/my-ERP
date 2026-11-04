<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\DailyReport;
use App\Models\Task;
use App\Models\TimerSession;
use App\Services\TimerConflictException;
use App\Services\TimerService;

final class TimerController extends Controller
{
    public function start(Request $request): void
    {
        $taskId = $request->routeInt('id');
        $task = Task::find($taskId);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        if (DailyReport::isSubmitted(local_today())) {
            $this->timerResponse($request, false, 'error', 'Today\'s report has been submitted. Tasks are locked until tomorrow.', '/tasks/' . $taskId);
        }

        if ($task['status'] === 'completed') {
            $this->timerResponse($request, false, 'error', 'This task is marked as done. Reopen it before starting the timer.', '/tasks/' . $taskId);
        }

        if (!empty($task['deadline_at']) && deadline_date_passed($task['deadline_at'])) {
            $this->timerResponse($request, false, 'error', 'This task\'s deadline has passed. Update the deadline to a future date before starting.', '/tasks/' . $taskId);
        }

        try {
            $result = TimerService::switchTo($taskId, (string) $task['title']);
            $type = 'success';

            if (!empty($result['already_running'])) {
                $message = 'Timer already running.';
            } elseif (!empty($result['stopped_task_title'])) {
                $message = 'Switched from "' . $result['stopped_task_title'] . '".';
            } else {
                $message = 'Timer started.';
            }

            if (in_array($task['status'], ['pending', 'in_progress'], true) && $task['status'] !== 'in_progress') {
                Task::update($taskId, ['status' => 'in_progress']);
            }
        } catch (TimerConflictException $e) {
            $type = 'error';
            $message = 'A timer is already running. Stop it before starting another one.';
        }

        $extra = $type === 'success' ? ['notice' => 'New task started: ' . $task['title']] : [];
        $this->timerResponse($request, $type === 'success', $type, $message, '/tasks/' . $taskId, $extra);
    }

    public function stop(Request $request): void
    {
        $sessionId = $request->routeInt('id');
        $session = TimerSession::find($sessionId);

        if ($session === null) {
            abort(404, 'Timer session not found.');
        }

        if (!TimerService::stop($sessionId)) {
            $this->timerResponse($request, false, 'error', 'That timer was not running.', '/tasks/' . (int) $session['task_id'], [
                'task_id' => (int) $session['task_id'],
            ]);
        }

        $message = 'Timer stopped.';
        $this->timerResponse($request, true, 'success', $message, '/tasks/' . (int) $session['task_id'], [
            'task_id' => (int) $session['task_id'],
        ]);
    }

    private function timerResponse(Request $request, bool $ok, string $type, string $message, string $fallback, array $extra = []): never
    {
        if ($request->wantsJson()) {
            $target = (string) $request->post('return', '');
            $redirectTo = ($target !== '' && str_starts_with($target, '/')) ? $target : $fallback;
            Response::json(array_merge([
                'ok' => $ok,
                'message' => $message,
                'flashes' => [['type' => $type, 'message' => $message]],
                'active_timer' => TimerService::active(),
                'redirect' => $redirectTo,
            ], $extra), $ok ? 200 : 409);
        }

        flash($type, $message);
        redirect($fallback);
    }
}
