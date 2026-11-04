<?php
use App\Services\TimerService;

$totalSeconds = 0;
foreach ($sessions as $s) {
    if ($s['status'] === 'stopped') {
        $totalSeconds += (int) $s['duration_seconds'];
    }
}
$isRunningHere = !empty($activeTimer) && (int) $activeTimer['task_id'] === (int) $task['id'];
$todayLocked = \App\Models\DailyReport::isSubmitted(local_today());
$deadlinePassed = deadline_date_passed($task['deadline_at'] ?? null);
$maxUpload = \App\Services\UploadService::maxBytes();
?>
<div class="page-head">
    <div>
        <h1><?= e($task['title']) ?></h1>
        <div class="sub row">
            <span class="badge badge-<?= e($task['status']) ?>"><?= e($task['status']) ?></span>
            <?php if (!empty($task['priority'])): ?><span class="badge badge-<?= e($task['priority']) ?>"><?= e($task['priority']) ?></span><?php endif; ?>
            <?php if (!empty($task['category'])): ?><span class="badge"><?= e($task['category']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="row">
        <?php if ($task['status'] === 'completed'): ?>
            <span class="badge badge-completed">Done</span>
        <?php else: ?>
            <form method="post" action="<?= e(url('/tasks/' . $task['id'] . '/complete')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-start">✓ Mark as done</button>
            </form>
        <?php endif; ?>
        <a class="btn" href="<?= e(url('/tasks/' . $task['id'] . '/edit')) ?>">Edit</a>
        <form method="post" action="<?= e(url('/tasks/' . $task['id'] . '/delete')) ?>" data-confirm="Delete this task and all its comments, sessions, and attachments? This cannot be undone.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<?php if (!empty($slowPerformance) && empty($slowPerformance['resolved_at'])): ?>
    <div class="alert alert-warning">
        <strong>Slow performance:</strong> this task has exceeded its deadline
        <?= (int) $slowPerformance['exceeded_count'] ?> times
        (last missed deadline <?= e(to_local($slowPerformance['deadline_at'], 'Y-m-d H:i')) ?>,
        overrun <?= e(format_overrun((int) $slowPerformance['overrun_seconds'])) ?>).
        <a href="<?= e(url('/slow-performance/' . (int) $slowPerformance['id'] . '/resolve')) ?>">Resolve this issue</a>
    </div>
<?php endif; ?>

<?php
$tabQuery = static function (string $target) use ($task, $timesheet): string {
    $query = ['tab' => $target];
    if ($target === 'timesheet') {
        if (!empty($timesheet['from'])) {
            $query['from'] = $timesheet['from'];
        }
        if (!empty($timesheet['to'])) {
            $query['to'] = $timesheet['to'];
        }
    }

    return url('/tasks/' . $task['id'] . '?' . http_build_query($query));
};
?>
<div class="tabs">
    <a class="tab<?= $tab === 'overview' ? ' active' : '' ?>" href="<?= e($tabQuery('overview')) ?>">Overview</a>
    <a class="tab<?= $tab === 'timesheet' ? ' active' : '' ?>" href="<?= e($tabQuery('timesheet')) ?>">Timesheet</a>
    <a class="tab<?= $tab === 'deadline' ? ' active' : '' ?>" href="<?= e($tabQuery('deadline')) ?>"><?= (int) $deadlineCount > 0 ? (int) $deadlineCount . ' deadlines' : 'deadlines' ?></a>
    <a class="tab<?= $tab === 'todo' ? ' active' : '' ?>" href="<?= e($tabQuery('todo')) ?>">To-do<?= !empty($todoItems) ? ' (' . count($todoItems) . ')' : '' ?></a>
</div>

<?php if ($tab === 'timesheet'): ?>
    <?php require __DIR__ . '/_timesheet.php'; ?>
<?php elseif ($tab === 'deadline'): ?>
    <?php require __DIR__ . '/_deadline.php'; ?>
<?php elseif ($tab === 'todo'): ?>
    <?php require __DIR__ . '/_todo.php'; ?>
<?php else: ?>

    <div class="card">
    <h2>Timer</h2>

    <div class="timer-panel"<?= $isRunningHere ? ' data-elapsed="' . (int) TimerService::elapsed($activeTimer) . '"' : '' ?>>

        <div>
            <div class="timer-clock" data-timer-value><?= e(format_duration($isRunningHere ? TimerService::elapsed($activeTimer) : 0)) ?></div>
            <div class="muted small">Today: <strong><?= e(format_duration($todaySeconds)) ?></strong> · All time: <strong><?= e(format_duration($totalSeconds)) ?></strong></div>
        </div>
        <div class="row">
            <?php if ($isRunningHere): ?>
                <form method="post" action="<?= e(url('/timers/' . $activeTimer['id'] . '/stop')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-stop">■ Stop</button>
                </form>
            <?php elseif (!empty($activeTimer)): ?>
                <span class="muted small">Another task is being timed:</span>
                <a class="btn btn-sm" href="<?= e(url('/tasks/' . $activeTimer['task_id'])) ?>"><?= e($activeTimer['task_title']) ?></a>
            <?php elseif ($task['status'] === 'completed'): ?>
                <span class="muted small">This task is marked as done. Reopen it from the edit form to start the timer.</span>
            <?php elseif ($todayLocked): ?>
                <span class="muted small">Today's report has been submitted. Tasks are locked until tomorrow.</span>
            <?php elseif ($deadlinePassed): ?>
                <span class="muted small">The deadline has passed. Update it to a future date to start the timer.</span>
            <?php else: ?>
                <form method="post" action="<?= e(url('/tasks/' . $task['id'] . '/timer/start')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-start">▶ Start</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Details</h2>
        <table>
            <tr><th>ID</th><td class="mono">#<?= (int) $task['id'] ?></td></tr>
            <tr><th>Created</th><td><?= e(to_local($task['created_at'], 'Y-m-d H:i:s')) ?></td></tr>
            <tr><th>Updated</th><td><?= e(to_local($task['updated_at'], 'Y-m-d H:i:s')) ?></td></tr>
            <tr><th>Deadline</th><td><?= e($task['deadline_at'] ? to_local($task['deadline_at'], 'Y-m-d H:i:s') : '—') ?></td></tr>
            <tr><th>Link</th><td class="mono small"><?= e(url('/tasks/' . $task['id'])) ?></td></tr>
        </table>
        <?php if (!empty($task['description'])): ?>
            <h3 class="mt">Target / description</h3>
            <div class="comment-body"><?= e($task['description']) ?></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Recent timer sessions</h2>
        <?php if (empty($sessions)): ?>
            <div class="empty">No sessions yet.</div>
        <?php else: ?>
            <table>
                <thead><tr><th>Start</th><th>Status</th><th class="right">Duration</th></tr></thead>
                <tbody>
                <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td class="small"><?= e(to_local($s['started_at'])) ?></td>
                        <td>
                            <span class="badge badge-<?= $s['status'] === 'stopped' ? 'completed' : 'in_progress' ?>"><?= e($s['status']) ?></span>
                        </td>
                        <td class="right nowrap"><?= e($s['duration_seconds'] !== null ? format_duration((int) $s['duration_seconds']) : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="page-head">
        <h2>Comments (<?= count($comments) ?>)</h2>
        <div class="row small">
            <span class="muted">Order:</span>
            <a class="btn btn-sm <?= $commentOrder !== 'asc' ? 'btn-primary' : '' ?>" href="<?= e(url('/tasks/' . $task['id'] . '?order=desc')) ?>">Newest</a>
            <a class="btn btn-sm <?= $commentOrder === 'asc' ? 'btn-primary' : '' ?>" href="<?= e(url('/tasks/' . $task['id'] . '?order=asc')) ?>">Oldest</a>
        </div>
    </div>

    <form method="post" action="<?= e(url('/tasks/' . $task['id'] . '/comments')) ?>" class="mb">
        <?= csrf_field() ?>
        <label>
            <span>Add a note (progress, blocker, decision…)</span>
            <textarea name="body" rows="3" maxlength="10000" required placeholder="What did you do / what is blocking you?"></textarea>
        </label>
        <button type="submit" class="btn btn-primary">Add comment</button>
    </form>

    <?php if (empty($comments)): ?>
        <div class="empty">No comments yet.</div>
    <?php else: ?>
        <?php $previousDay = null; ?>
        <?php foreach ($comments as $comment): ?>
            <?php
            $localCreated = to_local($comment['created_at'], 'Y-m-d H:i:s');
            $commentDay = substr($localCreated, 0, 10);
            ?>
            <?php if ($commentDay !== $previousDay): ?>
                <div class="comment-day-separator"><span><?= e(to_local($comment['created_at'], 'Y-m-d H:i')) ?></span></div>
                <?php $previousDay = $commentDay; ?>
            <?php endif; ?>
            <div class="comment">
                <div class="comment-head">
                    <span class="badge badge-<?= e($comment['kind']) ?>"><?= e($comment['kind']) ?></span>
                    <span class="muted small"><?= e($localCreated) ?></span>
                    <form method="post" action="<?= e(url('/comments/' . $comment['id'] . '/delete')) ?>" data-confirm="Delete this comment?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost btn-sm">Delete</button>
                    </form>
                </div>
                <div class="comment-body"><?= e($comment['body']) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>ZIP attachments</h2>
    <p class="muted small">
        Only <strong>.zip</strong> files up to <strong><?= e(human_bytes($maxUpload)) ?></strong> are accepted.
        Uploads are validated server-side (signature, size, entry count, compression ratio) and stored outside the web root.
        PHP limit: upload_max_filesize=<?= e((string) ini_get('upload_max_filesize')) ?>, post_max_size=<?= e((string) ini_get('post_max_size')) ?>.
    </p>

    <form method="post" action="<?= e(url('/tasks/' . $task['id'] . '/attachments')) ?>" enctype="multipart/form-data" class="row mb">
        <?= csrf_field() ?>
        <input type="file" name="zip" accept=".zip,application/zip" required style="flex:1">
        <button type="submit" class="btn btn-primary">Upload ZIP</button>
    </form>

    <?php if (empty($attachments)): ?>
        <div class="empty">No attachments.</div>
    <?php else: ?>
        <ul class="attach-list">
            <?php foreach ($attachments as $attachment): ?>
                <li>
                    <div>
                        <a href="<?= e(url('/attachments/' . $attachment['id'] . '/download')) ?>"><?= e($attachment['original_filename']) ?></a>
                        <div class="muted small">
                            <?= e(human_bytes((int) $attachment['size_bytes'])) ?> ·
                            uploaded <?= e(to_local($attachment['created_at'], 'Y-m-d H:i')) ?> ·
                            sha256 <span class="mono"><?= e(substr((string) $attachment['sha256'], 0, 16)) ?>…</span>
                        </div>
                    </div>
                    <form method="post" action="<?= e(url('/attachments/' . $attachment['id'] . '/delete')) ?>" data-confirm="Delete this attachment?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php endif; ?>
