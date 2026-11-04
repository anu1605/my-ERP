<div class="modal" id="submit-report-modal" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel card" role="dialog" aria-modal="true" aria-labelledby="submit-report-title">
        <div class="page-head">
            <div>
                <h2 id="submit-report-title">Submit report — <?= e($submitDate) ?></h2>
                <div class="sub">Review the work below. A .txt copy downloads when you submit.</div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-modal-close>Close</button>
        </div>

        <div class="modal-body">
            <?php if (empty($report['tasks'])): ?>
                <div class="empty">No tasks were worked on or commented on for this date.</div>
            <?php else: ?>
                <?php foreach ($report['tasks'] as $task): ?>
                    <div class="report-task">
                        <div class="report-task-head">
                            <strong><?= e($task['title']) ?></strong>
                            <span class="badge badge-in_progress"><?= e($task['time']) ?></span>
                        </div>
                        <?php if (!empty($task['comments'])): ?>
                            <?php foreach ($task['comments'] as $comment): ?>
                                <div class="comment-body small"><?= e((string) $comment['body']) ?></div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="muted small">No comments.</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('/reports/submit')) ?>" data-report-submit data-export-url="<?= e(url('/reports/export')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="report_date" value="<?= e($submitDate) ?>">
            <div class="row" style="justify-content:flex-end;margin-top:14px">
                <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Submit report &amp; download .txt</button>
            </div>
        </form>
    </div>
</div>
