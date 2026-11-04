<?php
$activeCount = count($todoItems);
$doneCount = count($doneTodoItems);
$dragIcon = '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" x="0" y="0" fill="#000" viewBox="0 0 20 20"><circle cx="7.5" cy="5.5" r="1.5"/><circle cx="12.5" cy="5.5" r="1.5"/><circle cx="7.5" cy="10.5" r="1.5"/><circle cx="12.5" cy="10.5" r="1.5"/><circle cx="7.5" cy="15.5" r="1.5"/><circle cx="12.5" cy="15.5" r="1.5"/></svg>';
?>
<div class="keep-todo">
    <div class="page-head">
        <div>
            <h2>To-do for today</h2>
            <div class="sub">Check off topics as you finish them. Marked items move below so you can add them to the task comments.</div>
        </div>
        <span class="badge"><?= $activeCount ?> open · <?= $doneCount ?> done</span>
    </div>

    <div class="keep-card">
        <ul class="todo-list" data-todo-list data-reorder-url="<?= e(url('/tasks/' . (int) $task['id'] . '/todo/reorder')) ?>">
            <?php foreach ($todoItems as $item): ?>
                <li class="todo-row" data-todo-id="<?= (int) $item['id'] ?>">
                    <span class="todo-drag" draggable="true" title="Drag to reorder" aria-label="Drag to reorder"><?= $dragIcon ?></span>

                    <form method="post" action="<?= e(url('/todo/' . (int) $item['id'] . '/toggle')) ?>" class="todo-toggle-form" data-todo-toggle-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="done" value="1">
                        <button type="submit" class="todo-check-btn" aria-label="Mark as done" title="Mark as done"></button>
                    </form>

                    <div class="todo-content">
                        <span class="todo-text"><?= e($item['body']) ?></span>

                        <form method="post" action="<?= e(url('/todo/' . (int) $item['id'] . '/delete')) ?>" class="todo-delete-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="todo-delete" aria-label="Delete item" title="Delete item">×</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="todo-empty"<?= $activeCount > 0 ? ' hidden' : '' ?>>Nothing here yet. Add a topic below or paste a list.</div>

        <form method="post" action="<?= e(url('/tasks/' . (int) $task['id'] . '/todo')) ?>" class="todo-add-form" data-todo-add>
            <?= csrf_field() ?>
            <span class="todo-plus">+</span>
            <textarea name="body" rows="1" class="todo-add-input" placeholder="List item" maxlength="10000" aria-label="Add list item"></textarea>
            <button type="submit" class="btn btn-sm btn-primary todo-add-btn">Add</button>
        </form>
        <div class="muted small todo-hint">Tip: paste a multi-line list and each non-empty line becomes its own checklist item.</div>
    </div>

    <?php if ($doneCount > 0): ?>
        <div class="keep-card keep-done-card">
            <div class="page-head">
                <div>
                    <h2>Marked as done</h2>
                    <div class="sub">These items are hidden from the active list until you add them to comments.</div>
                </div>
                <span class="badge badge-completed"><?= $doneCount ?></span>
            </div>

            <ul class="todo-list todo-done-list">
                <?php foreach ($doneTodoItems as $item): ?>
                    <li class="todo-row todo-done" data-todo-id="<?= (int) $item['id'] ?>">
                        <span class="todo-drag todo-drag-disabled" aria-hidden="true"><?= $dragIcon ?></span>

                        <form method="post" action="<?= e(url('/todo/' . (int) $item['id'] . '/toggle')) ?>" class="todo-toggle-form" data-todo-toggle-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="done" value="0">
                            <button type="submit" class="todo-check-btn checked" aria-label="Mark as not done" title="Mark as not done">✓</button>
                        </form>

                        <div class="todo-content">
                            <span class="todo-text"><?= e($item['body']) ?></span>

                            <form method="post" action="<?= e(url('/todo/' . (int) $item['id'] . '/delete')) ?>" class="todo-delete-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="todo-delete" aria-label="Delete item" title="Delete item">×</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="post" action="<?= e(url('/tasks/' . (int) $task['id'] . '/todo/add-to-comments')) ?>" class="todo-comment-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Add to comment</button>
                <span class="muted small">Adds every marked item to the task comments and clears this list.</span>
            </form>
        </div>
    <?php endif; ?>
</div>
