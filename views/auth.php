<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Sign in') ?> · <?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1><?= e($appName) ?></h1>
    <?= $content ?? '' ?>
</div>
</body>
</html>
