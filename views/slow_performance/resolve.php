<?php
$errs = errors();
$resolved = !empty($entry['resolved_at']);
?>
<div class="page-head">
    <div>
        <h1>Slow Performance: <?= e($entry['task_title']) ?></h1>
        <div class="sub">Task #<?= (int) $entry['task_id'] ?> · Flagged <?= e(to_local($entry['flagged_at'], 'Y-m-d H:i')) ?></div>
    </div>
    <div class="row">
        <a class="btn" href="<?= e(url('/slow-performance')) ?>">Back to list</a>
        <a class="btn" href="<?= e(url('/tasks/' . (int) $entry['task_id'])) ?>">Open task</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Task details</h2>
        <p class="row">
            <span class="badge badge-<?= e($entry['task_status']) ?>"><?= e($entry['task_status']) ?></span>
            <?php if (!empty($entry['task_priority'])): ?>
                <span class="badge badge-<?= e($entry['task_priority']) ?>"><?= e($entry['task_priority']) ?></span>
            <?php endif; ?>
            <?php if (!empty($entry['task_category'])): ?>
                <span class="badge"><?= e($entry['task_category']) ?></span>
            <?php endif; ?>
        </p>
        <?php if (!empty($entry['task_description'])): ?>
            <div class="comment-body"><?= e($entry['task_description']) ?></div>
        <?php else: ?>
            <p class="muted">No description.</p>
        <?php endif; ?>
        <table>
            <tbody>
            <tr>
                <th>Current deadline</th>
                <td><?= $entry['task_deadline_at'] !== null ? e(to_local($entry['task_deadline_at'], 'Y-m-d H:i')) : '—' ?></td>
            </tr>
            <tr>
                <th>Last missed deadline</th>
                <td><?= $entry['deadline_at'] !== null ? e(to_local($entry['deadline_at'], 'Y-m-d H:i')) : '—' ?></td>
            </tr>
            <tr>
                <th>Deadline exceeded</th>
                <td><?= (int) $entry['exceeded_count'] ?> times</td>
            </tr>
            <tr>
                <th>Overrun</th>
                <td><?= e(format_overrun((int) $entry['overrun_seconds'])) ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php if ($resolved): ?>
                        <span class="badge badge-completed">resolved</span>
                        <?= e(to_local($entry['resolved_at'], 'Y-m-d H:i')) ?>
                    <?php else: ?>
                        <span class="badge badge-pending">unresolved</span>
                    <?php endif; ?>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <?php if ($resolved): ?>
            <h2>Resolution</h2>
            <p class="muted small">Resolved on <?= e(to_local($entry['resolved_at'], 'Y-m-d H:i')) ?>.</p>
            <div class="comment-body"><?= e((string) $entry['resolution_reason']) ?></div>
        <?php else: ?>
            <h2>Resolve this entry</h2>
            <p class="muted small">
                Explain the reason for the delay. A non-empty reason is required; saving it marks this entry as resolved.
            </p>
            <form method="post" action="<?= e(url('/slow-performance/' . (int) $entry['id'] . '/resolve')) ?>">
                <?= csrf_field() ?>
                <label>
                    <span>Reason for delay</span>
                    <textarea name="reason" rows="6" maxlength="10000" required placeholder="What caused the delay?"><?= e((string) old('reason', '')) ?></textarea>
                    <?php if (isset($errs['reason'])): ?>
                        <span class="field-error"><?= e($errs['reason']) ?></span>
                    <?php endif; ?>
                </label>
                <div class="row">
                    <button type="submit" class="btn btn-primary">Save and resolve</button>
                    <a class="btn" href="<?= e(url('/slow-performance')) ?>">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
