<?php
$todayLocked = \App\Models\DailyReport::isSubmitted(local_today());
$completedMode = $completedMode ?? false;
$qs = http_build_query($filters);
$returnPath = $currentPath . ($qs !== '' ? '?' . $qs : '');
?>
<div class="page-head">
    <div>
        <h1><?= $completedMode ? 'Completed Tasks' : 'Tasks' ?></h1>
        <div class="sub"><?= count($tasks) ?> task(s)</div>
    </div>
    <div class="row">
        <?php if ($completedMode): ?>
            <a class="btn" href="<?= e(url('/tasks')) ?>">← Active tasks</a>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('/tasks/create')) ?>">+ New Task</a>
    </div>
</div>

<form class="filters card" method="get" action="<?= e(url($completedMode ? '/tasks/completed' : '/tasks')) ?>">
    <label>
        <span>Search title</span>
        <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search…">
    </label>
    <label>
        <span>Status</span>
        <select name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span>Priority</span>
        <select name="priority">
            <option value="">All</option>
            <?php foreach ($priorities as $priority): ?>
                <option value="<?= e($priority) ?>" <?= $filters['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span>Category</span>
        <select name="category">
            <option value="">All</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span>Due</span>
        <select name="due">
            <option value="">Any</option>
            <option value="today" <?= $filters['due'] === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="overdue" <?= $filters['due'] === 'overdue' ? 'selected' : '' ?>>Overdue</option>
        </select>
    </label>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-ghost" href="<?= e(url($completedMode ? '/tasks/completed' : '/tasks')) ?>">Reset</a>
</form>

<div class="card">
    <?php if (empty($tasks)): ?>
        <div class="empty">
            <?php if ($completedMode): ?>
                No completed tasks match. <a href="<?= e(url('/tasks')) ?>">Back to active tasks</a>.
            <?php else: ?>
                No tasks match. <a href="<?= e(url('/tasks/create')) ?>">Create one</a>.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Task</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Category</th>
                <th>Deadline</th>
                <th class="right">Tracked today</th>
                <th class="right">Timer</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $task): ?>
                <?php
                $isRunning = !empty($activeTimer) && (int) $activeTimer['task_id'] === (int) $task['id'];
                $deadlinePassed = deadline_date_passed($task['deadline_at'] ?? null);
                $taskSeconds = (int) ($todaySeconds[(int) $task['id']] ?? 0);
                $sessionToday = $isRunning
                    ? \App\Services\ReportService::overlapSeconds(
                        (string) $activeTimer['started_at'],
                        null,
                        \App\Services\ReportService::dayRangeUtc(local_today())
                    )
                    : 0;
                $baseToday = max(0, $taskSeconds - $sessionToday);
                ?>
                <tr class="task-row<?= $isRunning ? ' task-row-running' : '' ?>" data-task-row="<?= (int) $task['id'] ?>" data-start-blocked="<?= $deadlinePassed ? 'deadline' : ($todayLocked ? 'locked' : '') ?>">
                    <td>
                        <a href="<?= e(url('/tasks/' . $task['id'])) ?>"><?= e($task['title']) ?></a>
                        <?php if (!empty($task['description'])): ?>
                            <div class="muted small"><?= e(mb_strimwidth($task['description'], 0, 90, '…')) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= e($task['status']) ?>"><?= e($task['status']) ?></span></td>
                    <td><?php if (!empty($task['priority'])): ?><span class="badge badge-<?= e($task['priority']) ?>"><?= e($task['priority']) ?></span><?php endif; ?></td>
                    <td class="muted small"><?= e($task['category'] ?? '') ?></td>
                    <td class="nowrap">
                        <?php if ($task['deadline_at']): ?>
                            <?= e(to_local($task['deadline_at'])) ?>
                            <?php $daysLeft = days_until_deadline($task['deadline_at']); ?>
                            <?php if ($daysLeft !== null): ?>
                                <?php if ($daysLeft < 0): ?>
                                    <span class="badge badge-urgent"><?= e((string) abs($daysLeft)) ?> day(s) overdue</span>
                                <?php elseif ($daysLeft === 0): ?>
                                    <span class="badge badge-urgent">due today</span>
                                <?php else: ?>
                                    <span class="muted small"><?= e((string) $daysLeft) ?> day(s) left</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="right nowrap">
                        <?php if ($isRunning): ?>
                            <span class="tracked-value" data-total="<?= (int) $baseToday ?>"
                                  data-rendered="<?= e(format_duration($taskSeconds)) ?>"
                                  data-elapsed="<?= (int) $sessionToday ?>">
                                <span class="timer-active-value" data-timer-value><?= e(format_duration($taskSeconds)) ?></span>
                            </span>
                        <?php else: ?>
                            <span class="tracked-value" data-total="<?= (int) $taskSeconds ?>"
                                  data-rendered="<?= e(format_duration($taskSeconds)) ?>"><?= e(format_duration($taskSeconds)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="right nowrap">
                        <?php if ($isRunning): ?>
                            <form method="post" action="<?= e(url('/timers/' . $activeTimer['id'] . '/stop')) ?>" data-timer-action="stop">
                                <?= csrf_field() ?>
                                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($returnPath) ?>">
                                <button type="submit" class="btn btn-stop btn-sm">■ Stop</button>
                            </form>
                        <?php elseif ($task['status'] === 'completed'): ?>
                            <span class="muted small">done</span>
                        <?php elseif ($todayLocked): ?>
                            <span class="muted small">locked</span>
                        <?php elseif ($deadlinePassed): ?>
                            <span class="muted small" title="Update the deadline to a future date to start">deadline passed</span>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/tasks/' . $task['id'] . '/timer/start')) ?>" data-timer-action="start">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return" value="<?= e($returnPath) ?>">
                                <button type="submit" class="btn btn-start btn-sm">▶ Start</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
