<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Models\Comment;
use App\Models\Task;

final class CommentController extends Controller
{
    public function store(Request $request): void
    {
        $taskId = $request->routeInt('id');
        $task = Task::find($taskId);

        if ($task === null) {
            abort(404, 'Task not found.');
        }

        $body = $request->string('body');
        $kind = $request->string('kind', 'general');

        if ($body === '') {
            flash('error', 'A comment cannot be empty.');
            redirect('/tasks/' . $taskId);
        }

        if (mb_strlen($body) > 10000) {
            flash('error', 'The comment is too long (max 10000 characters).');
            redirect('/tasks/' . $taskId);
        }

        Comment::create($taskId, $body, $kind);
        flash('success', 'Comment added.');
        redirect('/tasks/' . $taskId);
    }

    public function destroy(Request $request): void
    {
        $commentId = $request->routeInt('id');
        $comment = Comment::find($commentId);

        if ($comment === null) {
            abort(404, 'Comment not found.');
        }

        Comment::delete($commentId);
        flash('success', 'Comment deleted.');
        redirect('/tasks/' . (int) $comment['task_id']);
    }
}
