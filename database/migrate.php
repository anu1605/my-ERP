<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\Support\Config;

$dbName = (string) Config::get('db.name');

echo "Task Tracker migration runner\n";
echo "=============================\n\n";

try {
    $server = Database::serverConnection();
    $server->exec(
        "CREATE DATABASE IF NOT EXISTS `{$dbName}` "
        . 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    echo "Database `{$dbName}` ready.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Could not create/connect database: {$e->getMessage()}\n");
    exit(1);
}

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(255) NOT NULL,
        executed_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$executed = $pdo->query('SELECT migration FROM migrations')->fetchAll(\PDO::FETCH_COLUMN);
$executed = array_flip($executed);

$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files);

$ran = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (isset($executed[$name])) {
        echo "skip   {$name}\n";
        continue;
    }

    $sql = (string) file_get_contents($file);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    try {
        foreach ($statements as $statement) {
            // Strip whole-line comments. They are stripped per statement
            // instead of skipping the whole chunk, otherwise a comment in
            // front of a statement would silently discard it.
            $statement = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));

            if ($statement === '') {
                continue;
            }

            $pdo->exec($statement);
        }

        $stmt = $pdo->prepare('INSERT INTO migrations (migration, executed_at) VALUES (:m, :t)');
        $stmt->execute(['m' => $name, 't' => utc_now_string()]);

        echo "run    {$name}\n";
        $ran++;
    } catch (\Throwable $e) {
        fwrite(STDERR, "FAILED {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "\nDone. {$ran} migration(s) executed.\n";
