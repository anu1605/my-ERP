<?php
$errs = errors();
$isEdit = $task !== null;
$deadlineValue = '';
if (!empty($task['deadline_at'])) {
    $deadlineValue = (new DateTimeImmutable($task['deadline_at'], new DateTimeZone('UTC')))
        ->setTimezone(app_timezone())->format('Y-m-d\TH:i');
}
if (old('deadline_at') !== '') {
    $deadlineValue = old('deadline_at');
}
?>
<div class="page-head">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <?php if (!empty($backlogItem)): ?>
            <div class="sub">From backlog item: <strong><?= e($backlogItem['title']) ?></strong> (will be marked converted)</div>
        <?php endif; ?>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('/tasks')) ?>">Cancel</a>
</div>

<form method="post" action="<?= e(url($action)) ?>" class="card">
    <?= csrf_field() ?>

    <label>
        <span>Title *</span>
        <input type="text" name="title" value="<?= e(old('title', $task['title'] ?? '')) ?>" maxlength="255" required autofocus>
        <?php if (isset($errs['title'])): ?><div class="field-error"><?= e($errs['title']) ?></div><?php endif; ?>
    </label>

    <label>
        <span>Description / target summary</span>
        <textarea name="description" rows="4"><?= e(old('description', $task['description'] ?? '')) ?></textarea>
    </label>

    <div class="form-grid">
        <label>
            <span>Status</span>
            <select name="status">
                <?php $selectedStatus = old('status', $task['status'] ?? 'pending'); ?>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Priority</span>
            <?php $selectedPriority = old('priority', $task['priority'] ?? ''); ?>
            <select name="priority">
                <option value="">—</option>
                <?php foreach ($priorities as $priority): ?>
                    <option value="<?= e($priority) ?>" <?= $selectedPriority === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Category / tag</span>
            <input type="text" name="category" value="<?= e(old('category', $task['category'] ?? '')) ?>" maxlength="100">
        </label>

        <label>
            <span>Deadline</span>
            <input type="datetime-local" name="deadline_at" value="<?= e($deadlineValue) ?>">
            <?php if (isset($errs['deadline_at'])): ?><div class="field-error"><?= e($errs['deadline_at']) ?></div><?php endif; ?>
        </label>
    </div>

    <div class="row mt">
        <button type="submit" class="btn btn-primary"><?= $isEdit || !empty($backlogItem) ? 'Save' : 'Create Task' ?></button>
        <a class="btn btn-ghost" href="<?= e(url('/tasks')) ?>">Cancel</a>
    </div>
</form>
