<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class DailyReport
{
    public static function findByDate(string $date): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM daily_reports WHERE report_date = :d');
        $stmt->execute(['d' => $date]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM daily_reports WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function save(string $date, array $data): int
    {
        $existing = self::findByDate($date);
        $pdo = Database::connection();

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE daily_reports SET
                    generated_at = :generated_at,
                    planned_target = :planned_target,
                    target_hit_status = :target_hit_status,
                    accomplishments = :accomplishments,
                    blockers = :blockers,
                    next_steps = :next_steps,
                    report_markdown = :report_markdown
                 WHERE id = :id'
            );

            $stmt->execute([
                'id' => $existing['id'],
                'generated_at' => utc_now_string(),
                'planned_target' => $data['planned_target'] ?? null,
                'target_hit_status' => $data['target_hit_status'] ?? null,
                'accomplishments' => $data['accomplishments'] ?? null,
                'blockers' => $data['blockers'] ?? null,
                'next_steps' => $data['next_steps'] ?? null,
                'report_markdown' => $data['report_markdown'] ?? null,
            ]);

            return (int) $existing['id'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO daily_reports
                (report_date, generated_at, planned_target, target_hit_status, accomplishments, blockers, next_steps, report_markdown, created_at)
             VALUES
                (:report_date, :generated_at, :planned_target, :target_hit_status, :accomplishments, :blockers, :next_steps, :report_markdown, :created_at)'
        );

        $stmt->execute([
            'report_date' => $date,
            'generated_at' => utc_now_string(),
            'planned_target' => $data['planned_target'] ?? null,
            'target_hit_status' => $data['target_hit_status'] ?? null,
            'accomplishments' => $data['accomplishments'] ?? null,
            'blockers' => $data['blockers'] ?? null,
            'next_steps' => $data['next_steps'] ?? null,
            'report_markdown' => $data['report_markdown'] ?? null,
            'created_at' => utc_now_string(),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function isSubmitted(string $date): bool
    {
        $report = self::findByDate($date);

        return $report !== null && !empty($report['submitted_at']);
    }

    public static function markSubmitted(string $date): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE daily_reports SET submitted_at = :submitted_at WHERE report_date = :d'
        );
        $stmt->execute(['submitted_at' => utc_now_string(), 'd' => $date]);
    }

    public static function recent(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        return Database::connection()
            ->query('SELECT * FROM daily_reports ORDER BY report_date DESC LIMIT ' . $limit)
            ->fetchAll();
    }
}
