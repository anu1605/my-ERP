<div class="page-head">
    <div>
        <h1>Slow Performance</h1>
        <div class="sub">
            Tasks whose deadline has been exceeded <?= (int) \App\Services\SlowPerformanceService::THRESHOLD ?> or more times.
            Unresolved: <?= (int) $openCount ?> · Resolved: <?= (int) $resolvedCount ?>
        </div>
    </div>
    <div class="row">
        <a class="btn btn-primary" href="<?= e(url('/slow-performance/export')) ?>">Download report (.md)</a>
    </div>
</div>

<div class="tabs">
    <a class="tab<?= $view !== 'resolved' ? ' active' : '' ?>" href="<?= e(url('/slow-performance')) ?>">Unresolved (<?= (int) $openCount ?>)</a>
    <a class="tab<?= $view === 'resolved' ? ' active' : '' ?>" href="<?= e(url('/slow-performance?view=resolved')) ?>">Resolved (<?= (int) $resolvedCount ?>)</a>
</div>

<div class="card">
    <?php if (empty($entries)): ?>
        <div class="empty">
            <?= $view === 'resolved' ? 'No resolved slow performance entries yet.' : 'No unresolved slow performance entries.' ?>
        </div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Task</th>
                <th>Missed deadline</th>
                <th>Exceeded</th>
                <th>Overrun</th>
                <th>Flagged</th>
                <th>Status</th>
                <th class="right">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <?php $resolved = !empty($entry['resolved_at']); ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/tasks/' . (int) $entry['task_id'])) ?>"><?= e($entry['task_title']) ?></a>
                        <div class="muted small">#<?= (int) $entry['task_id'] ?></div>
                    </td>
                    <td class="nowrap">
                        <?= $entry['deadline_at'] !== null ? e(to_local($entry['deadline_at'], 'Y-m-d H:i')) : '—' ?>
                    </td>
                    <td><?= (int) $entry['exceeded_count'] ?> times</td>
                    <td class="nowrap"><?= e(format_overrun((int) $entry['overrun_seconds'])) ?></td>
                    <td class="muted small nowrap"><?= e(to_local($entry['flagged_at'], 'Y-m-d H:i')) ?></td>
                    <td>
                        <?php if ($resolved): ?>
                            <span class="badge badge-completed">resolved</span>
                            <div class="muted small"><?= e(to_local($entry['resolved_at'], 'Y-m-d H:i')) ?></div>
                        <?php else: ?>
                            <span class="badge badge-pending">unresolved</span>
                        <?php endif; ?>
                    </td>
                    <td class="right nowrap">
                        <?php if ($resolved): ?>
                            <a class="btn btn-sm" href="<?= e(url('/slow-performance/' . (int) $entry['id'] . '/resolve')) ?>">View</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('/slow-performance/' . (int) $entry['id'] . '/resolve')) ?>">Resolve</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
