<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DeadlineChange;
use App\Models\SlowPerformance;
use App\Models\Task;
use App\Models\TaskTodoItem;
use App\Models\TimerSession;
use App\Http\Request;
use App\Services\ReportService;
use App\Services\SlowPerformanceService;
use App\Services\TimesheetService;
use App\Services\TimerService;

final class TaskController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'category' => (string) $request->query('category', ''),
            'priority' => (string) $request->query('priority', ''),
            'q' => (string) $request->query('q', ''),
            'due' => (string) $request->query('due', ''),
        ];

        $this->render('tasks/index', [
            'pageTitle' => 'Tasks',
            'tasks' => Task::all($filters),
            'filters' => $filters,
            'categories' => Task::categories(),
            'statuses' => array_values(array_diff(Task::STATUSES, ['completed'])),
            'priorities' => Task::PRIORITIES,
            'completedMode' => false,
            'todaySeconds' => ReportService::durationsForDay(local_today())['per_task'],
        ]);
    }

    public function completed(Request $request): void
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'category' => (string) $request->query('category', ''),
            'priority' => (string) $request->query('priority', ''),
            'q' => (string) $request->query('q', ''),
            'due' => (string) $request->query('due', ''),
        ];

        $this->render('tasks/index', [
            'pageTitle' => 'Completed Tasks',
            'tasks' => Task::all($filters, true),
            'filters' => $filters,
            'categories' => Task::categories(),
            'statuses' => ['completed'],
            'priorities' => Task::PRIORITIES,
            'completedMode' => true,
            'todaySeconds' => ReportService::durationsForDay(local_today())['per_task'],
        ]);
    }

    public function create(Request $request): void
    {
        $this->render('tasks/form', [
            'pageTitle' => 'New Task',
            'task' => null,
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
            'action' => '/tasks',
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);

        if ($data === null) {
            keep_old($request->all());
            $this->back($request, '/tasks/create');
        }

        $taskId = Task::create($data);
        SlowPerformanceService::evaluateTask($taskId);
        clear_old();
        flash('success', 'Task created.');
        redirect('/tasks/' . $taskId);
    }

    public function show(Request $request): void
    {
        $id = $request->routeInt('id');
        $task = Task::find($id);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        $today = local_today();
        $durations = ReportService::durationsForDay($today);
        SlowPerformanceService::evaluateTask($id);

        $tab = in_array($request->query('tab'), ['timesheet', 'deadline', 'todo'], true)
            ? (string) $request->query('tab')
            : 'overview';
        $from = $this->validDate((string) $request->query('from', ''));
        $to = $this->validDate((string) $request->query('to', ''));

        $this->render('tasks/show', [
            'pageTitle' => $task['title'],
            'task' => $task,
            'tab' => $tab,
            'comments' => Comment::forTask($id, (string) $request->query('order', 'desc')),
            'commentOrder' => (string) $request->query('order', 'desc'),
            'attachments' => Attachment::forTask($id),
            'sessions' => TimerSession::forTask($id, 5),
            'todoItems' => TaskTodoItem::forTask($id, false),
            'doneTodoItems' => TaskTodoItem::forTask($id, true),
            'timesheet' => TimesheetService::forTask($id, $from, $to),
            'deadlineChanges' => DeadlineChange::forTask($id),
            'deadlineCount' => DeadlineChange::countExtensions($id),
            'todaySeconds' => $durations['per_task'][$id] ?? 0,
            'activeTimer' => TimerService::active(),
            'slowPerformance' => SlowPerformance::findByTask($id),
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
        ]);
    }

    public function edit(Request $request): void
    {
        $id = $request->routeInt('id');
        $task = Task::find($id);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        $this->render('tasks/form', [
            'pageTitle' => 'Edit Task',
            'task' => $task,
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
            'action' => '/tasks/' . $id,
        ]);
    }

    public function update(Request $request): void
    {
        $id = $request->routeInt('id');
        $task = Task::find($id);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        $data = $this->validated($request);

        if ($data === null) {
            keep_old($request->all());
            redirect('/tasks/' . $id . '/edit');
        }

        Task::update($id, $data);

        if ($data['status'] === 'completed') {
            $this->stopTimerForTask($id);
        }

        SlowPerformanceService::evaluateTask($id);
        clear_old();
        flash('success', 'Task updated.');
        redirect('/tasks/' . $id);
    }

    public function complete(Request $request): void
    {
        $id = $request->routeInt('id');
        $task = Task::find($id);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        if ($task['status'] === 'completed') {
            flash('success', 'This task is already marked as done.');
            redirect('/tasks/completed');
        }

        $this->stopTimerForTask($id);
        Task::update($id, ['status' => 'completed']);
        SlowPerformanceService::evaluateTask($id);

        flash('success', 'Task marked as done.');
        redirect('/tasks/completed');
    }

    public function updateDeadline(Request $request): void
    {
        $id = $request->routeInt('id');
        $task = Task::find($id);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        $deadlineUtc = $this->deadlineToUtc($request->string('deadline_at'));

        if ($deadlineUtc === false) {
            flash('error', 'Please provide a valid deadline date/time.');
            redirect('/tasks/' . $id . '?tab=deadline');
        }

        Task::update($id, ['deadline_at' => $deadlineUtc]);
        SlowPerformanceService::evaluateTask($id);

        if ($deadlineUtc === null) {
            flash('success', 'Deadline cleared.');
        } else {
            flash('success', 'Deadline updated to ' . to_local($deadlineUtc, 'Y-m-d H:i') . '.');
        }

        redirect('/tasks/' . $id . '?tab=deadline');
    }

    public function destroy(Request $request): void
    {
        $id = $request->routeInt('id');
        $task = Task::find($id);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        foreach (Attachment::forTask($id) as $attachment) {
            \App\Services\UploadService::delete((int) $attachment['id']);
        }

        Task::delete($id);
        flash('success', 'Task and its comments, sessions, and attachments were deleted.');
        redirect('/tasks');
    }

    private function stopTimerForTask(int $taskId): void
    {
        $active = TimerService::active();

        if ($active !== null && (int) $active['task_id'] === $taskId) {
            TimerService::stop((int) $active['id']);
        }
    }

    private function validDate(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /**
     * @return string|null|false UTC value for a valid input, null when empty, false when invalid.
     */
    private function deadlineToUtc(string $value): string|null|false
    {
        if (trim($value) === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', trim($value));

        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $normalized) !== 1) {
            return false;
        }

        if (strlen($normalized) === 10) {
            $normalized .= ' 00:00';
        }

        return local_to_utc($normalized);
    }

    private function validated(Request $request): ?array
    {
        $errors = [];

        $title = $request->string('title');
        if ($title === '') {
            $errors['title'] = 'A title is required.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'The title may not exceed 255 characters.';
        }

        $status = $request->string('status', 'pending');
        if (!in_array($status, Task::STATUSES, true)) {
            $status = 'pending';
        }

        $priority = $request->string('priority');
        if ($priority !== '' && !in_array($priority, Task::PRIORITIES, true)) {
            $priority = '';
        }

        $deadlineUtc = $this->deadlineToUtc($request->string('deadline_at'));

        if ($deadlineUtc === false) {
            $errors['deadline_at'] = 'Please provide a valid deadline date/time.';
            $deadlineUtc = null;
        }

        if ($errors !== []) {
            store_errors($errors);
            flash('error', 'Please correct the highlighted fields.');
            return null;
        }

        return [
            'title' => $title,
            'description' => $request->string('description') !== '' ? $request->string('description') : null,
            'status' => $status,
            'priority' => $priority !== '' ? $priority : null,
            'category' => $request->string('category') !== '' ? $request->string('category') : null,
            'deadline_at' => $deadlineUtc,
        ];
    }
}
