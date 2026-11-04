<?php
use App\Services\TimerService;

$nav = [
    '/' => 'Dashboard',
    '/tasks' => 'Tasks',
    '/tasks/completed' => 'Completed',
    '/backlog' => 'Backlog',
    '/reports' => 'Reports',
    '/slow-performance' => 'Slow Performance',
    '/settings' => 'Settings',
];
$isActive = static function (string $path) use ($currentPath): bool {
    if ($path === '/') {
        return $currentPath === '/';
    }

    if ($path === '/tasks') {
        return $currentPath === '/tasks'
            || (str_starts_with($currentPath, '/tasks/') && !str_starts_with($currentPath, '/tasks/completed'));
    }

    if ($path === '/tasks/completed') {
        return $currentPath === '/tasks/completed' || str_starts_with($currentPath, '/tasks/completed/');
    }

    return $currentPath === $path || str_starts_with($currentPath, $path . '/');
};
$activeElapsed = isset($activeTimer) && $activeTimer ? TimerService::elapsed($activeTimer) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $appName) ?> · <?= e($appName) ?></title>
    <link rel="icon" href="<?= e(url('/assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="alternate icon" href="<?= e(url('/favicon.ico')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body data-csrf="<?= e(csrf_token()) ?>" data-base="<?= e(rtrim((string) config('app.url'), '/')) ?>">
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= e(url('/')) ?>"><?= e($appName) ?></a>
        <nav class="nav">
            <?php foreach ($nav as $path => $label): ?>
                <a class="nav-link<?= $isActive($path) ? ' active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="topbar-right">
            <?php if (!empty($activeTimer)): ?>
                <span class="timer-pill" id="header-timer"
                      data-active
                      data-session="<?= (int) $activeTimer['id'] ?>"
                      data-elapsed="<?= (int) $activeElapsed ?>">
                    <span class="dot"></span>
                    <a href="<?= e(url('/tasks/' . $activeTimer['task_id'])) ?>"><?= e($activeTimer['task_title']) ?></a>
                    <span class="timer-value" data-timer-value><?= e(format_duration($activeElapsed)) ?></span>
                </span>
            <?php endif; ?>
            <?php if (!empty($authEnabled)): ?>
                <form method="post" action="<?= e(url('/logout')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container">
    <?php foreach (($flashes ?? []) as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?= $content ?? '' ?>
</main>

<footer class="footer">
    <span><?= e($appName) ?></span>
    <span>Local-first task &amp; time tracker</span>
</footer>

<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
<div id="toast-container" class="toast-container"></div>
</body>
</html>
