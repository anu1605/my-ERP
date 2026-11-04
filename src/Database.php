<?php

declare(strict_types=1);

namespace App;

use App\Support\Config;
use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = (string) Config::get('db.host');
        $port = (int) Config::get('db.port');
        $name = (string) Config::get('db.name');
        $user = (string) Config::get('db.user');
        $pass = (string) Config::get('db.pass');
        $charset = (string) Config::get('db.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$connection = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new PDOException(
                self::describe($e, $host, $port, $name, $user, $pass),
                (int) $e->getCode(),
                $e
            );
        }

        return self::$connection;
    }

    private static function describe(
        PDOException $e,
        string $host,
        int $port,
        string $name,
        string $user,
        string $pass
    ): string {
        $message = 'Database connection failed: ' . $e->getMessage();

        if ($e->getCode() === '1045' && $pass === '') {
            $message .= ' | The DB password is empty. Set DB_PASS in your .env file.';
        } elseif ($e->getCode() === '1049') {
            $message .= " | The database '{$name}' does not exist. Run: php database/migrate.php";
        } elseif ($e->getCode() === '2002') {
            $message .= " | Is MySQL running on {$host}:{$port}?";
        }

        return $message;
    }

    public static function serverConnection(): PDO
    {
        $host = (string) Config::get('db.host');
        $port = (int) Config::get('db.port');
        $charset = (string) Config::get('db.charset', 'utf8mb4');

        return new PDO(
            "mysql:host={$host};port={$port};charset={$charset}",
            (string) Config::get('db.user'),
            (string) Config::get('db.pass'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
