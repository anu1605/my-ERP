<div class="page-head">
    <div><h1><?= e($pageTitle) ?></h1></div>
    <a class="btn btn-ghost" href="<?= e(url('/backlog')) ?>">Cancel</a>
</div>

<form method="post" action="<?= e(url($action)) ?>" class="card">
    <?= csrf_field() ?>

    <label>
        <span>Title *</span>
        <input type="text" name="title" value="<?= e(old('title', $item['title'])) ?>" maxlength="255" required autofocus>
    </label>

    <label>
        <span>Details / notes</span>
        <textarea name="details" rows="6"><?= e(old('details', $item['details'] ?? '')) ?></textarea>
    </label>

    <div class="form-grid">
        <label>
            <span>Category / tag</span>
            <input type="text" name="category" value="<?= e(old('category', $item['category'] ?? '')) ?>" maxlength="100">
        </label>
        <label>
            <span>Priority</span>
            <?php $selectedPriority = old('priority', $item['priority'] ?? ''); ?>
            <select name="priority">
                <option value="">—</option>
                <?php foreach ($priorities as $priority): ?>
                    <option value="<?= e($priority) ?>" <?= $selectedPriority === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Status</span>
            <?php $selectedStatus = old('status', $item['status']); ?>
            <select name="status">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="row mt">
        <button type="submit" class="btn btn-primary">Save</button>
        <a class="btn btn-ghost" href="<?= e(url('/backlog')) ?>">Cancel</a>
    </div>
</form>
