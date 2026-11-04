<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Models\Comment;
use App\Models\Task;
use App\Models\TaskTodoItem;

final class TaskTodoController extends Controller
{
    public function store(Request $request): void
    {
        $task = $this->task($request);
        $taskId = (int) $task['id'];
        $lines = $this->lines($request->string('body'));

        if ($lines === []) {
            if ($request->wantsJson()) {
                json_response(['ok' => false, 'message' => 'Add at least one non-empty list item.'], 422);
            }

            flash('error', 'Add at least one non-empty list item.');
            redirect('/tasks/' . $taskId . '?tab=todo');
        }

        $items = [];
        foreach ($lines as $line) {
            $id = TaskTodoItem::create($taskId, $line);
            $items[] = [
                'id' => $id,
                'body' => $line,
            ];
        }

        if ($request->wantsJson()) {
            json_response(['ok' => true, 'items' => $items, 'count' => count($items)]);
        }

        $count = count($items);
        flash('success', $count === 1 ? 'List item added.' : $count . ' list items added.');
        redirect('/tasks/' . $taskId . '?tab=todo');
    }

    public function toggle(Request $request): void
    {
        $item = TaskTodoItem::find($request->routeInt('id'));

        if ($item === null) {
            abort(404, 'List item not found.');
        }

        $done = $request->post('done');
        $isDone = $done === null ? !(bool) $item['is_done'] : in_array((string) $done, ['1', 'true', 'on'], true);

        TaskTodoItem::setDone((int) $item['id'], $isDone);

        if ($request->wantsJson()) {
            json_response(['ok' => true, 'id' => (int) $item['id'], 'is_done' => $isDone]);
        }

        redirect('/tasks/' . (int) $item['task_id'] . '?tab=todo');
    }

    public function destroy(Request $request): void
    {
        $item = TaskTodoItem::find($request->routeInt('id'));

        if ($item === null) {
            abort(404, 'List item not found.');
        }

        TaskTodoItem::delete((int) $item['id']);
        flash('success', 'List item deleted.');
        redirect('/tasks/' . (int) $item['task_id'] . '?tab=todo');
    }

    public function reorder(Request $request): void
    {
        $task = $this->task($request);
        $order = $request->post('order');

        if (!is_array($order)) {
            $order = $order === null ? [] : explode(',', (string) $order);
        }

        TaskTodoItem::reorder((int) $task['id'], $order);

        if ($request->wantsJson()) {
            json_response(['ok' => true]);
        }

        redirect('/tasks/' . (int) $task['id'] . '?tab=todo');
    }

    public function addToComments(Request $request): void
    {
        $task = $this->task($request);
        $taskId = (int) $task['id'];
        $doneItems = TaskTodoItem::forTask($taskId, true);

        if ($doneItems === []) {
            flash('error', 'There are no marked-as-done items to add.');
            redirect('/tasks/' . $taskId . '?tab=todo');
        }

        $lines = array_map(
            static fn (array $item): string => '✔ ' . (string) $item['body'],
            $doneItems
        );
        $body = implode("\n", $lines);

        if (mb_strlen($body) > 10000) {
            $body = mb_substr($body, 0, 10000);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            Comment::create($taskId, $body, 'general');

            $delete = $pdo->prepare('DELETE FROM task_todo_items WHERE task_id = :task_id AND is_done = 1');
            $delete->execute(['task_id' => $taskId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $count = count($doneItems);
        flash('success', $count === 1
            ? 'Marked item added to comments.'
            : $count . ' marked items added to comments.');
        redirect('/tasks/' . $taskId . '?tab=todo');
    }

    private function task(Request $request): array
    {
        $task = Task::find($request->routeInt('id'));

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        return $task;
    }

    private function lines(string $value): array
    {
        $parts = preg_split('/\R/u', $value);

        if ($parts === false) {
            $parts = [$value];
        }

        $lines = [];
        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (mb_strlen($part) > TaskTodoItem::MAX_LENGTH) {
                $part = mb_substr($part, 0, TaskTodoItem::MAX_LENGTH);
            }

            $lines[] = $part;
        }

        return $lines;
    }
}
