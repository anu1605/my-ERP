<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\DailyReport;
use App\Services\ReportService;
use App\Services\TimerService;

final class ReportController extends Controller
{
    public function index(Request $request): void
    {
        $date = $this->validDate((string) $request->query('date', '')) ?? local_today();
        $includeEarlier = (string) $request->query('include_earlier', '') === '1';

        $saved = DailyReport::findByDate($date);

        $options = [
            'include_earlier' => $includeEarlier,
            'planned_target' => $saved['planned_target'] ?? null,
            'target_hit_status' => $saved['target_hit_status'] ?? null,
            'accomplishments' => $saved['accomplishments'] ?? null,
            'blockers' => $saved['blockers'] ?? null,
            'next_steps' => $saved['next_steps'] ?? null,
        ];

        $report = ReportService::build($date, $options);
        $markdown = ReportService::toMarkdown($report);

        $this->render('reports/index', [
            'pageTitle' => 'Daily Report',
            'date' => $date,
            'includeEarlier' => $includeEarlier,
            'report' => $report,
            'markdown' => $markdown,
            'saved' => $saved,
            'submitted' => DailyReport::isSubmitted($date),
            'recentReports' => DailyReport::recent(15),
        ]);
    }

    public function save(Request $request): void
    {
        $date = $this->validDate($request->string('report_date')) ?? local_today();
        $includeEarlier = $request->string('include_earlier') === '1';

        if (DailyReport::isSubmitted($date)) {
            flash('error', 'The report for ' . $date . ' has been submitted and can no longer be edited.');
            redirect('/reports?date=' . $date);
        }

        $options = [
            'include_earlier' => $includeEarlier,
            'planned_target' => $this->nullable($request->string('planned_target')),
            'target_hit_status' => $this->nullable($request->string('target_hit_status')),
            'accomplishments' => $this->nullable($request->string('accomplishments')),
            'blockers' => $this->nullable($request->string('blockers')),
            'next_steps' => $this->nullable($request->string('next_steps')),
        ];

        $report = ReportService::build($date, $options);
        $markdown = ReportService::toMarkdown($report);

        DailyReport::save($date, array_merge($options, ['report_markdown' => $markdown]));

        flash('success', 'Report saved for ' . $date . '.');
        redirect('/reports?date=' . $date . ($includeEarlier ? '&include_earlier=1' : ''));
    }

    public function submit(Request $request): void
    {
        $date = $this->validDate($request->string('report_date')) ?? local_today();

        if (DailyReport::isSubmitted($date)) {
            flash('error', 'The report for ' . $date . ' has already been submitted.');
            redirect('/reports?date=' . $date);
        }

        if (TimerService::active() !== null) {
            flash('error', 'Stop the running timer before submitting the report.');
            redirect('/reports?date=' . $date);
        }

        $options = [
            'include_earlier' => $request->string('include_earlier') === '1',
            'planned_target' => $this->nullable($request->string('planned_target')),
            'target_hit_status' => $this->nullable($request->string('target_hit_status')),
            'accomplishments' => $this->nullable($request->string('accomplishments')),
            'blockers' => $this->nullable($request->string('blockers')),
            'next_steps' => $this->nullable($request->string('next_steps')),
        ];

        $saved = DailyReport::findByDate($date);
        foreach (['planned_target', 'target_hit_status', 'accomplishments', 'blockers', 'next_steps'] as $field) {
            if ($options[$field] === null && $saved !== null && !empty($saved[$field])) {
                $options[$field] = $saved[$field];
            }
        }

        $report = ReportService::build($date, $options);
        $markdown = ReportService::toMarkdown($report);

        DailyReport::save($date, array_merge($options, ['report_markdown' => $markdown]));
        DailyReport::markSubmitted($date);

        $message = 'Report submitted for ' . $date . '.';
        if ($date === local_today()) {
            $message .= ' Task timers are locked for the rest of the day.';
        }
        flash('success', $message);
        redirect('/reports?date=' . $date);
    }

    public function export(Request $request): void
    {
        $date = $this->validDate((string) $request->query('date', '')) ?? local_today();
        $format = (string) $request->query('format', 'md');
        $includeEarlier = (string) $request->query('include_earlier', '') === '1';

        $report = ReportService::build($date, ['include_earlier' => $includeEarlier]);

        if ($format === 'json') {
            Response::download(
                ReportService::toJson($report),
                "report-{$date}.json",
                'application/json'
            );
        }

        if ($format === 'txt') {
            Response::download(
                ReportService::toText($report),
                "report-{$date}.txt",
                'text/plain; charset=utf-8'
            );
        }

        Response::download(
            ReportService::toMarkdown($report),
            "report-{$date}.md",
            'text/markdown; charset=utf-8'
        );
    }

    private function validDate(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
