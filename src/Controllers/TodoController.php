<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Models\Task;
use App\Models\TodoItem;

final class TodoController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'category' => (string) $request->query('category', ''),
            'priority' => (string) $request->query('priority', ''),
            'q' => (string) $request->query('q', ''),
        ];

        $this->render('todo/index', [
            'pageTitle' => 'Topic Backlog',
            'items' => TodoItem::all($filters),
            'filters' => $filters,
            'categories' => TodoItem::categories(),
            'statuses' => TodoItem::STATUSES,
            'priorities' => TodoItem::PRIORITIES,
            'counts' => TodoItem::counts(),
        ]);
    }

    public function store(Request $request): void
    {
        $title = $request->string('title');

        if ($title === '') {
            flash('error', 'A topic title is required.');
            redirect('/backlog');
        }

        TodoItem::create([
            'title' => $title,
            'details' => $this->nullable($request->string('details')),
            'category' => $this->nullable($request->string('category')),
            'priority' => $this->validPriority($request->string('priority')),
            'status' => in_array($request->string('status'), TodoItem::STATUSES, true)
                ? $request->string('status')
                : 'inbox',
        ]);

        flash('success', 'Backlog item added.');
        redirect('/backlog');
    }

    public function edit(Request $request): void
    {
        $item = TodoItem::find($request->routeInt('id'));

        if ($item === null) {
            abort(404, 'Backlog item not found.');
        }

        $this->render('todo/form', [
            'pageTitle' => 'Edit Backlog Item',
            'item' => $item,
            'statuses' => TodoItem::STATUSES,
            'priorities' => TodoItem::PRIORITIES,
            'action' => '/backlog/' . $item['id'],
        ]);
    }

    public function update(Request $request): void
    {
        $id = $request->routeInt('id');
        $item = TodoItem::find($id);

        if ($item === null) {
            abort(404, 'Backlog item not found.');
        }

        $title = $request->string('title');

        if ($title === '') {
            flash('error', 'A topic title is required.');
            redirect('/backlog/' . $id . '/edit');
        }

        $status = $request->string('status');
        if (!in_array($status, TodoItem::STATUSES, true)) {
            $status = $item['status'];
        }

        TodoItem::update($id, [
            'title' => $title,
            'details' => $this->nullable($request->string('details')),
            'category' => $this->nullable($request->string('category')),
            'priority' => $this->validPriority($request->string('priority')),
            'status' => $status,
        ]);

        flash('success', 'Backlog item updated.');
        redirect('/backlog');
    }

    public function archive(Request $request): void
    {
        $id = $request->routeInt('id');
        TodoItem::archive($id);
        flash('success', 'Backlog item archived.');
        redirect('/backlog');
    }

    public function destroy(Request $request): void
    {
        $id = $request->routeInt('id');
        TodoItem::delete($id);
        flash('success', 'Backlog item deleted.');
        redirect('/backlog');
    }

    public function convertForm(Request $request): void
    {
        $item = TodoItem::find($request->routeInt('id'));

        if ($item === null) {
            abort(404, 'Backlog item not found.');
        }

        $this->render('tasks/form', [
            'pageTitle' => 'Create Task from Backlog Item',
            'task' => [
                'title' => $item['title'],
                'description' => $item['details'],
                'category' => $item['category'],
                'priority' => $item['priority'],
                'status' => 'pending',
                'deadline_at' => null,
            ],
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
            'action' => '/backlog/' . $item['id'] . '/convert',
            'backlogItem' => $item,
        ]);
    }

    public function convert(Request $request): void
    {
        $item = TodoItem::find($request->routeInt('id'));

        if ($item === null) {
            abort(404, 'Backlog item not found.');
        }

        $title = $request->string('title');
        if ($title === '') {
            flash('error', 'A title is required to create the task.');
            redirect('/backlog/' . $item['id'] . '/convert');
        }

        $status = $request->string('status', 'pending');
        if (!in_array($status, Task::STATUSES, true)) {
            $status = 'pending';
        }

        $deadlineUtc = null;
        $deadlineLocal = str_replace('T', ' ', $request->string('deadline_at'));
        if ($deadlineLocal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $deadlineLocal) === 1) {
            if (strlen($deadlineLocal) === 10) {
                $deadlineLocal .= ' 00:00';
            }
            $deadlineUtc = local_to_utc($deadlineLocal);
        }

        $taskId = Task::create([
            'title' => $title,
            'description' => $this->nullable($request->string('description')),
            'status' => $status,
            'priority' => $this->validPriority($request->string('priority')),
            'category' => $this->nullable($request->string('category')),
            'deadline_at' => $deadlineUtc,
        ]);

        TodoItem::markConverted((int) $item['id'], $taskId);

        flash('success', 'Task created from backlog item. The backlog entry is preserved and marked as converted.');
        redirect('/tasks/' . $taskId);
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function validPriority(string $priority): ?string
    {
        return in_array($priority, TodoItem::PRIORITIES, true) ? $priority : null;
    }
}
