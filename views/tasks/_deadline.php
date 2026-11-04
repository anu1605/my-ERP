<?php
$deadlineValue = '';
if (!empty($task['deadline_at'])) {
    $deadlineValue = (new DateTimeImmutable($task['deadline_at'], new DateTimeZone('UTC')))
        ->setTimezone(app_timezone())
        ->format('Y-m-d\TH:i');
}

$entries = $deadlineChanges;

// Tasks that already had a deadline before change tracking existed: show it as the initial entry.
if ($entries === [] && !empty($task['deadline_at'])) {
    $entries = [[
        'previous_deadline_at' => null,
        'new_deadline_at' => $task['deadline_at'],
        'changed_at' => $task['created_at'],
    ]];
}
?>
<div class="page-head">
    <div>
        <h1>Deadlines</h1>
        <div class="sub">Every deadline for this task and how many times it was extended.</div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card stat">
        <div class="value"><?= (int) $deadlineCount ?></div>
        <div class="label">Deadline<?= (int) $deadlineCount === 1 ? '' : 's' ?> extended</div>
    </div>
    <div class="card stat">
        <div class="value"><?= e($task['deadline_at'] ? to_local($task['deadline_at']) : '—') ?></div>
        <div class="label">Current deadline</div>
    </div>
</div>

<form method="post" action="<?= e(url('/tasks/' . $task['id'] . '/deadline')) ?>" class="card">
    <?= csrf_field() ?>
    <h2>Update deadline</h2>
    <div class="row">
        <label style="flex:1;min-width:220px;margin:0">
            <span>New deadline</span>
            <input type="datetime-local" name="deadline_at" value="<?= e($deadlineValue) ?>">
        </label>
        <button type="submit" class="btn btn-primary">Save deadline</button>
    </div>
    <p class="muted small" style="margin:8px 0 0">
        Each time you change the deadline after the first one, it is counted as an extension.
        Leave it empty and save to clear the deadline.
    </p>
</form>

<div class="card">
    <h2>All deadlines</h2>
    <?php if (empty($entries)): ?>
        <div class="empty">No deadline set yet.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Deadline</th>
                    <th>Previous</th>
                    <th>Changed</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $i => $change): ?>
                <?php
                $isInitial = $change['previous_deadline_at'] === null;
                $cleared = $change['new_deadline_at'] === null;
                $type = $isInitial ? 'Initial' : ($cleared ? 'Cleared' : 'Extended');
                $typeClass = $isInitial ? 'pending' : ($cleared ? 'cancelled' : 'urgent');
                ?>
                <tr>
                    <td class="muted"><?= (int) ($i + 1) ?></td>
                    <td class="nowrap"><strong><?= e($change['new_deadline_at'] ? to_local($change['new_deadline_at'], 'Y-m-d H:i') : '—') ?></strong></td>
                    <td class="nowrap muted"><?= e($change['previous_deadline_at'] ? to_local($change['previous_deadline_at'], 'Y-m-d H:i') : '—') ?></td>
                    <td class="nowrap small"><?= e(to_local($change['changed_at'], 'Y-m-d H:i')) ?></td>
                    <td><span class="badge badge-<?= e($typeClass) ?>"><?= e($type) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
