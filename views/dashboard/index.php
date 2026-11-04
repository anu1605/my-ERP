<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="sub"><?= e($today) ?> · <?= e((string) setting('timezone')) ?></div>
    </div>
    <div class="row">
        <a class="btn btn-primary" href="<?= e(url('/tasks/create')) ?>">+ New Task</a>
        <a class="btn" href="<?= e(url('/reports?date=' . $today)) ?>">Generate today's report</a>
        <?php if (!empty($submittedToday)): ?>
            <span class="badge badge-completed">Report submitted · tasks locked until tomorrow</span>
        <?php else: ?>
            <button type="button" class="btn btn-primary" data-modal-open="#submit-report-modal">Submit report</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($submittedToday)): ?>
    <div class="alert alert-success">
        Today's report was submitted
        <?php if (!empty($todayReport['submitted_at'])): ?>
            on <?= e(to_local($todayReport['submitted_at'], 'Y-m-d H:i')) ?>
        <?php endif; ?>.
        Task timers are locked for the rest of the day and will unlock tomorrow.
    </div>
<?php endif; ?>

<?php if (!empty($slowPerformanceCount)): ?>
    <div class="alert alert-warning">
        There are performance issues which need to be resolved.
        <a href="<?= e(url('/slow-performance')) ?>">View slow performance</a>
    </div>
<?php endif; ?>

<div class="grid grid-4">
    <div class="card stat">
        <div class="value"><?= e(format_duration($todayTotal)) ?></div>
        <div class="label">Tracked today</div>
    </div>
    <div class="card stat">
        <div class="value"><?= (int) $counts['in_progress'] ?></div>
        <div class="label">In progress</div>
    </div>
    <div class="card stat">
        <div class="value"><?= (int) $counts['pending'] ?></div>
        <div class="label">Pending</div>
    </div>
    <div class="card stat">
        <div class="value"><?= (int) $counts['completed'] ?></div>
        <div class="label">Completed</div>
    </div>
</div>

<div class="card">
    <h2>Current timer</h2>
    <?php if (!empty($activeTimer)): ?>
        <div class="timer-panel"
             data-elapsed="<?= (int) \App\Services\TimerService::elapsed($activeTimer) ?>">
            <div>
                <div class="timer-clock" data-timer-value><?= e(format_duration(\App\Services\TimerService::elapsed($activeTimer))) ?></div>
                <div class="muted small">Running on
                    <a href="<?= e(url('/tasks/' . $activeTimer['task_id'])) ?>"><?= e($activeTimer['task_title']) ?></a>
                </div>
            </div>
            <form method="post" action="<?= e(url('/timers/' . $activeTimer['id'] . '/stop')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-stop">Stop</button>
            </form>
        </div>
    <?php else: ?>
        <p class="muted">No timer is running. Open a task and press <span class="badge badge-completed">Start</span> to begin tracking.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Due today &amp; overdue</h2>
    <?php if (empty($dueTasks)): ?>
        <div class="empty">Nothing due today. You're clear.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Task</th><th>Status</th><th>Deadline</th></tr></thead>
            <tbody>
            <?php foreach ($dueTasks as $task): ?>
                <?php $overdue = $task['deadline_at'] < utc_now_string(); ?>
                <tr>
                    <td><a href="<?= e(url('/tasks/' . $task['id'])) ?>"><?= e($task['title']) ?></a></td>
                    <td><span class="badge badge-<?= e($task['status']) ?>"><?= e($task['status']) ?></span></td>
                    <td class="<?= $overdue ? 'muted' : '' ?>">
                        <?= e(to_local($task['deadline_at'])) ?>
                        <?php if ($overdue): ?><span class="badge badge-urgent">overdue</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Today's time by task</h2>
        <?php if (empty($workedTasks)): ?>
            <div class="empty">No time tracked today yet.</div>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($workedTasks as $row): ?>
                    <tr>
                        <td><a href="<?= e(url('/tasks/' . $row['task']['id'])) ?>"><?= e($row['task']['title']) ?></a></td>
                        <td class="right nowrap"><?= e(format_duration($row['seconds'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="right"><?= e(format_duration($todayTotal)) ?></th>
                </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Recent comments</h2>
        <?php if (empty($recentComments)): ?>
            <div class="empty">No comments yet.</div>
        <?php else: ?>
            <?php foreach ($recentComments as $comment): ?>
                <div class="comment">
                    <div class="comment-head">
                        <a href="<?= e(url('/tasks/' . $comment['task_id'])) ?>"><?= e($comment['task_title']) ?></a>
                        <span class="badge badge-<?= e($comment['kind']) ?>"><?= e($comment['kind']) ?></span>
                        <span class="muted small"><?= e(to_local($comment['created_at'])) ?></span>
                    </div>
                    <div class="comment-body small"><?= e(mb_strimwidth($comment['body'], 0, 160, '…')) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Backlog</h2>
    <div class="row">
        <span class="badge badge-inbox">Inbox: <?= (int) $todoCounts['inbox'] ?></span>
        <span class="badge badge-planned">Planned: <?= (int) $todoCounts['planned'] ?></span>
        <span class="badge badge-converted">Converted: <?= (int) $todoCounts['converted'] ?></span>
        <span class="badge badge-archived">Archived: <?= (int) $todoCounts['archived'] ?></span>
        <a class="btn btn-sm" href="<?= e(url('/backlog')) ?>">Open backlog</a>
    </div>
</div>

<?php if (empty($submittedToday)): ?>
    <?php $submitDate = $today; ?>
    <?php require __DIR__ . '/../partials/submit_report_modal.php'; ?>
<?php endif; ?>
