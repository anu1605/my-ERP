<form method="post" action="<?= e(url('/login')) ?>" class="stack">
    <?= csrf_field() ?>
    <label>
        <span>Password</span>
        <input type="password" name="password" autofocus required>
    </label>
    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
</form>
