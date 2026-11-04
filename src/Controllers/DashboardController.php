<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Models\Comment;
use App\Models\DailyReport;
use App\Models\Task;
use App\Models\TodoItem;
use App\Models\SlowPerformance;
use App\Services\ReportService;
use App\Services\SlowPerformanceService;
use App\Services\TimerService;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        SlowPerformanceService::syncAll();

        $today = local_today();
        $durations = ReportService::durationsForDay($today);

        $workedTasks = [];
        foreach ($durations['per_task'] as $taskId => $seconds) {
            $task = Task::find((int) $taskId);
            if ($task !== null) {
                $workedTasks[] = ['task' => $task, 'seconds' => $seconds];
            }
        }
        usort($workedTasks, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);

        $this->render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'activeTimer' => TimerService::active(),
            'counts' => Task::countsByStatus(),
            'dueTasks' => Task::dueTodayOrOverdue(),
            'todayTotal' => $durations['total'],
            'workedTasks' => $workedTasks,
            'recentComments' => Comment::recent(8),
            'todoCounts' => TodoItem::counts(),
            'today' => $today,
            'submittedToday' => DailyReport::isSubmitted($today),
            'todayReport' => DailyReport::findByDate($today),
            'report' => ReportService::build($today),
            'slowPerformanceCount' => SlowPerformance::countOpen(),
        ]);
    }
}
