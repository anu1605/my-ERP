<div class="page-head">
    <div>
        <h1>Timesheet</h1>
        <div class="sub">Every start and stop for this task, with the total time spent.</div>
    </div>
</div>

<form class="filters card" method="get" action="<?= e(url('/tasks/' . $task['id'])) ?>">
    <input type="hidden" name="tab" value="timesheet">
    <label>
        <span>From</span>
        <input type="date" name="from" value="<?= e((string) $timesheet['from']) ?>">
    </label>
    <label>
        <span>To</span>
        <input type="date" name="to" value="<?= e((string) $timesheet['to']) ?>">
    </label>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-ghost" href="<?= e(url('/tasks/' . $task['id'] . '?tab=timesheet')) ?>">Clear</a>
</form>

<div class="grid grid-4">
    <div class="card stat">
        <div class="value"><?= e(format_duration($timesheet['total'])) ?></div>
        <div class="label">Total time spent</div>
    </div>
    <div class="card stat">
        <div class="value"><?= count($timesheet['days']) ?></div>
        <div class="label">Days logged</div>
    </div>
    <div class="card stat">
        <div class="value"><?= count($timesheet['sessions']) ?></div>
        <div class="label">Sessions</div>
    </div>
    <div class="card stat">
        <div class="value"><?= $timesheet['from'] || $timesheet['to'] ? 'Filtered' : 'All time' ?></div>
        <div class="label">Range</div>
    </div>
</div>

<div class="card">
    <h2>Time spent each day</h2>
    <?php if (empty($timesheet['days'])): ?>
        <div class="empty">No time tracked for this range yet.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Date</th><th class="right">Time spent</th></tr></thead>
            <tbody>
            <?php foreach ($timesheet['days'] as $day): ?>
                <tr>
                    <td><?= e($day['date']) ?></td>
                    <td class="right nowrap"><strong><?= e(format_duration($day['seconds'])) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <th>Total</th>
                <th class="right"><?= e(format_duration($timesheet['total'])) ?></th>
            </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Activity log</h2>
    <?php if (empty($timesheet['sessions'])): ?>
        <div class="empty">No timer activity yet. Press Start on the task to begin tracking.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Started</th>
                    <th>Ended</th>
                    <th>Status</th>
                    <th class="right">Duration</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($timesheet['sessions'] as $session): ?>
                <tr>
                    <td class="nowrap"><?= e(to_local($session['started_at'], 'Y-m-d')) ?></td>
                    <td class="nowrap"><?= e(to_local($session['started_at'], 'H:i:s')) ?></td>
                    <td class="nowrap"><?= $session['ended_at'] !== null ? e(to_local($session['ended_at'], 'H:i:s')) : '<span class="badge badge-in_progress">running</span>' ?></td>
                    <td>
                        <span class="badge badge-<?= $session['status'] === 'stopped' ? 'completed' : 'in_progress' ?>"><?= e($session['status']) ?></span>
                    </td>
                    <td class="right nowrap"><?= e(format_duration($session['duration'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="4">Total time spent</th>
                <th class="right"><?= e(format_duration($timesheet['total'])) ?></th>
            </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>
