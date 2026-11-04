<div class="page-head">
    <div>
        <h1>Topic Backlog</h1>
        <div class="sub">Capture things to learn or build. Backlog items don't accrue time until converted to a task.</div>
    </div>
    <div class="row">
        <span class="badge badge-inbox">Inbox <?= (int) $counts['inbox'] ?></span>
        <span class="badge badge-planned">Planned <?= (int) $counts['planned'] ?></span>
        <span class="badge badge-converted">Converted <?= (int) $counts['converted'] ?></span>
    </div>
</div>

<div class="card">
    <h2>Quick add</h2>
    <form method="post" action="<?= e(url('/backlog')) ?>">
        <?= csrf_field() ?>
        <div class="quick-add">
            <input type="text" name="title" placeholder="What do you want to learn or build?" required maxlength="255">
            <button type="submit" class="btn btn-primary">Add</button>
        </div>
        <div class="form-grid mt">
            <label>
                <span>Details (optional)</span>
                <textarea name="details" rows="2" placeholder="Notes, scope, links…"></textarea>
            </label>
            <div>
                <label>
                    <span>Category / tag</span>
                    <input type="text" name="category" maxlength="100" list="todo-cats" placeholder="PHP interview, DSA, Laravel…">
                    <datalist id="todo-cats">
                        <?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?php endforeach; ?>
                    </datalist>
                </label>
                <label>
                    <span>Priority</span>
                    <select name="priority">
                        <option value="">—</option>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?= e($priority) ?>"><?= e($priority) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>
    </form>
</div>

<form class="filters card" method="get" action="<?= e(url('/backlog')) ?>">
    <label>
        <span>Search</span>
        <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Title or details…">
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
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-ghost" href="<?= e(url('/backlog')) ?>">Reset</a>
</form>

<div class="card">
    <?php if (empty($items)): ?>
        <div class="empty">No backlog items match.</div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Topic</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Created</th>
                <th class="right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?= e($item['title']) ?></strong>
                        <?php if (!empty($item['details'])): ?>
                            <div class="muted small"><?= e(mb_strimwidth($item['details'], 0, 120, '…')) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['converted_task_id'])): ?>
                            <div class="small">→ linked task
                                <a href="<?= e(url('/tasks/' . $item['converted_task_id'])) ?>">#<?= (int) $item['converted_task_id'] ?></a>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="muted small"><?= e($item['category'] ?? '') ?></td>
                    <td><?php if (!empty($item['priority'])): ?><span class="badge badge-<?= e($item['priority']) ?>"><?= e($item['priority']) ?></span><?php endif; ?></td>
                    <td><span class="badge badge-<?= e($item['status']) ?>"><?= e($item['status']) ?></span></td>
                    <td class="muted small nowrap"><?= e(to_local($item['created_at'], 'Y-m-d')) ?></td>
                    <td class="right nowrap">
                        <a class="btn btn-sm" href="<?= e(url('/backlog/' . $item['id'] . '/edit')) ?>">Edit</a>
                        <?php if ($item['status'] !== 'converted'): ?>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('/backlog/' . $item['id'] . '/convert')) ?>">Create task</a>
                        <?php else: ?>
                            <a class="btn btn-sm" href="<?= e(url('/backlog/' . $item['id'] . '/convert')) ?>">Create another task</a>
                        <?php endif; ?>
                        <?php if ($item['status'] !== 'archived'): ?>
                            <form class="inline" method="post" action="<?= e(url('/backlog/' . $item['id'] . '/archive')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-ghost" type="submit">Archive</button>
                            </form>
                        <?php endif; ?>
                        <form class="inline" method="post" action="<?= e(url('/backlog/' . $item['id'] . '/delete')) ?>" data-confirm="Delete this backlog item permanently?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
