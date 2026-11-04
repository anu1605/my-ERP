<div class="page-head">
    <div>
        <h1>Daily Report</h1>
        <div class="sub">Time is calculated from timer sessions only, and split across midnight correctly.</div>
    </div>
    <div class="row">
        <a class="btn" href="<?= e(url('/reports/export?date=' . $date . ($includeEarlier ? '&include_earlier=1' : '') . '&format=md')) ?>">Download .md</a>
        <a class="btn" href="<?= e(url('/reports/export?date=' . $date . ($includeEarlier ? '&include_earlier=1' : '') . '&format=json')) ?>">Download .json</a>
    </div>
</div>

<form class="filters card" method="get" action="<?= e(url('/reports')) ?>">
    <label>
        <span>Report date</span>
        <input type="date" name="date" value="<?= e($date) ?>">
    </label>
    <label style="align-self:center">
        <span class="small">
            <input type="checkbox" name="include_earlier" value="1" <?= $includeEarlier ? 'checked' : '' ?>>
            Include earlier comments
        </span>
    </label>
    <button class="btn btn-primary" type="submit">View report</button>
</form>

<div class="grid grid-4">
    <div class="card stat">
        <div class="value"><?= e($report['total_time']) ?></div>
        <div class="label">Total tracked</div>
    </div>
    <div class="card stat">
        <div class="value"><?= count($report['tasks']) ?></div>
        <div class="label">Tasks worked on</div>
    </div>
    <div class="card stat">
        <div class="value"><?= (int) $report['comment_count'] ?></div>
        <div class="label">Comments that day</div>
    </div>
    <div class="card stat">
        <div class="value"><?= $submitted ? 'Submitted' : ($saved ? 'Saved' : 'Draft') ?></div>
        <div class="label">Status</div>
    </div>
</div>

<?php if ($submitted): ?>
    <div class="alert alert-success">
        This report was submitted<?= !empty($saved['submitted_at']) ? ' on ' . e(to_local($saved['submitted_at'], 'Y-m-d H:i')) : '' ?>
        and is locked. Task timers are disabled for that day and unlock the next day.
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/reports')) ?>" class="card" data-report-notes>
    <?= csrf_field() ?>
    <input type="hidden" name="report_date" value="<?= e($date) ?>">
    <input type="hidden" name="include_earlier" value="<?= $includeEarlier ? '1' : '0' ?>">
    <h2>Report notes (optional)</h2>
    <div class="form-grid">
        <label>
            <span>Planned target</span>
            <textarea name="planned_target" rows="2"><?= e((string) ($saved['planned_target'] ?? '')) ?></textarea>
        </label>
        <label>
            <span>Target hit status</span>
            <input type="text" name="target_hit_status" value="<?= e((string) ($saved['target_hit_status'] ?? '')) ?>" placeholder="hit / partial / missed">
        </label>
        <label>
            <span>Accomplishments</span>
            <textarea name="accomplishments" rows="2"><?= e((string) ($saved['accomplishments'] ?? '')) ?></textarea>
        </label>
        <label>
            <span>Blockers</span>
            <textarea name="blockers" rows="2"><?= e((string) ($saved['blockers'] ?? '')) ?></textarea>
        </label>
        <label>
            <span>Next steps</span>
            <textarea name="next_steps" rows="2"><?= e((string) ($saved['next_steps'] ?? '')) ?></textarea>
        </label>
    </div>
    <?php if ($submitted): ?>
        <button type="submit" class="btn" disabled>Save report (locked)</button>
    <?php else: ?>
        <div class="row">
            <button type="submit" class="btn">Save draft</button>
            <button type="button" class="btn btn-primary" data-modal-open="#submit-report-modal">Submit report</button>
        </div>
    <?php endif; ?>
</form>

<div class="card">
    <div class="page-head">
        <h2>Markdown preview</h2>
        <button type="button" class="btn btn-sm" data-copy-target="#report-md">Copy Markdown</button>
    </div>
    <textarea id="report-md" class="report-preview" readonly rows="24"><?= e($markdown) ?></textarea>
</div>

<div class="card">
    <h2>Recent saved reports</h2>
    <?php if (empty($recentReports)): ?>
        <div class="empty">No saved reports yet.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Date</th><th>Generated</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($recentReports as $reportRow): ?>
                <tr>
                    <td><?= e($reportRow['report_date']) ?></td>
                    <td class="muted small"><?= e(to_local($reportRow['generated_at'], 'Y-m-d H:i')) ?></td>
                    <td class="right nowrap">
                        <a class="btn btn-sm" href="<?= e(url('/reports?date=' . $reportRow['report_date'])) ?>">Open</a>
                        <a class="btn btn-sm" href="<?= e(url('/reports/export?date=' . $reportRow['report_date'] . '&format=md')) ?>">.md</a>
                        <a class="btn btn-sm" href="<?= e(url('/reports/export?date=' . $reportRow['report_date'] . '&format=txt')) ?>">.txt</a>
                        <a class="btn btn-sm" href="<?= e(url('/reports/export?date=' . $reportRow['report_date'] . '&format=json')) ?>">.json</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!$submitted): ?>
    <?php $submitDate = $date; ?>
    <?php require __DIR__ . '/../partials/submit_report_modal.php'; ?>
<?php endif; ?>
