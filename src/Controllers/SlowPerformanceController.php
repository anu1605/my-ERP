<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\SlowPerformance;
use App\Services\SlowPerformanceService;

final class SlowPerformanceController extends Controller
{
    public function index(Request $request): void
    {
        SlowPerformanceService::syncAll();

        $view = (string) $request->query('view', 'open') === 'resolved' ? 'resolved' : 'open';
        $entries = $view === 'resolved' ? SlowPerformance::resolved() : SlowPerformance::open();

        $this->render('slow_performance/index', [
            'pageTitle' => 'Slow Performance',
            'view' => $view,
            'entries' => $entries,
            'openCount' => SlowPerformance::countOpen(),
            'resolvedCount' => SlowPerformance::countResolved(),
        ]);
    }

    public function showResolve(Request $request): void
    {
        $entry = $this->entry($request);

        $this->render('slow_performance/resolve', [
            'pageTitle' => 'Resolve: ' . $entry['task_title'],
            'entry' => $entry,
        ]);
    }

    public function resolve(Request $request): void
    {
        $entry = $this->entry($request);
        $id = (int) $entry['id'];

        if (!empty($entry['resolved_at'])) {
            flash('success', 'This slow performance entry is already resolved.');
            redirect('/slow-performance');
        }

        $reason = trim($request->string('reason'));

        if ($reason === '') {
            store_errors(['reason' => 'A reason for the delay is required before resolving.']);
            keep_old($request->all());
            flash('error', 'Please provide a reason for the delay.');
            redirect('/slow-performance/' . $id . '/resolve');
        }

        if (mb_strlen($reason) > 10000) {
            store_errors(['reason' => 'The reason is too long (max 10000 characters).']);
            keep_old($request->all());
            flash('error', 'Please shorten the reason for the delay.');
            redirect('/slow-performance/' . $id . '/resolve');
        }

        SlowPerformance::resolve($id, $reason);
        flash('success', 'Slow performance issue resolved.');
        redirect('/slow-performance');
    }

    public function export(Request $request): void
    {
        SlowPerformanceService::syncAll();

        $markdown = SlowPerformanceService::toMarkdown(SlowPerformance::all());

        Response::download(
            $markdown,
            'slow-performance-' . local_today() . '.md',
            'text/markdown; charset=utf-8'
        );
    }

    private function entry(Request $request): array
    {
        $entry = SlowPerformance::find($request->routeInt('id'));

        if ($entry === null) {
            abort(404, 'Slow performance entry not found.');
        }

        return $entry;
    }
}
