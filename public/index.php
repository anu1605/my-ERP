<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Http\Request;
use App\Router;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\View;
use App\Services\TimerService;

Session::start();

$request = new Request();

Csrf::check($request);

$path = $request->path();

if (Auth::enabled() && !Auth::check() && !in_array($path, ['/login'], true)) {
    redirect('/login');
}

View::share('appName', (string) config('app.name'));
View::share('currentPath', $path);
View::share('flashes', take_flashes());
View::share('activeTimer', TimerService::active());
View::share('authEnabled', Auth::enabled());

$router = new Router();

$router->get('/', [\App\Controllers\DashboardController::class, 'index']);

$router->get('/tasks', [\App\Controllers\TaskController::class, 'index']);
$router->get('/tasks/create', [\App\Controllers\TaskController::class, 'create']);
$router->get('/tasks/completed', [\App\Controllers\TaskController::class, 'completed']);
$router->post('/tasks', [\App\Controllers\TaskController::class, 'store']);
$router->get('/tasks/{id}/edit', [\App\Controllers\TaskController::class, 'edit']);
$router->get('/tasks/{id}', [\App\Controllers\TaskController::class, 'show']);
$router->post('/tasks/{id}', [\App\Controllers\TaskController::class, 'update']);
$router->post('/tasks/{id}/complete', [\App\Controllers\TaskController::class, 'complete']);
$router->post('/tasks/{id}/deadline', [\App\Controllers\TaskController::class, 'updateDeadline']);
$router->post('/tasks/{id}/delete', [\App\Controllers\TaskController::class, 'destroy']);
$router->post('/tasks/{id}/comments', [\App\Controllers\CommentController::class, 'store']);
$router->post('/tasks/{id}/todo', [\App\Controllers\TaskTodoController::class, 'store']);
$router->post('/tasks/{id}/todo/reorder', [\App\Controllers\TaskTodoController::class, 'reorder']);
$router->post('/tasks/{id}/todo/add-to-comments', [\App\Controllers\TaskTodoController::class, 'addToComments']);
$router->post('/tasks/{id}/attachments', [\App\Controllers\AttachmentController::class, 'store']);
$router->post('/todo/{id}/toggle', [\App\Controllers\TaskTodoController::class, 'toggle']);
$router->post('/todo/{id}/delete', [\App\Controllers\TaskTodoController::class, 'destroy']);
$router->post('/tasks/{id}/timer/start', [\App\Controllers\TimerController::class, 'start']);

$router->post('/comments/{id}/delete', [\App\Controllers\CommentController::class, 'destroy']);

$router->post('/timers/{id}/stop', [\App\Controllers\TimerController::class, 'stop']);

$router->get('/attachments/{id}/download', [\App\Controllers\AttachmentController::class, 'download']);
$router->post('/attachments/{id}/delete', [\App\Controllers\AttachmentController::class, 'destroy']);

$router->get('/backlog', [\App\Controllers\TodoController::class, 'index']);
$router->post('/backlog', [\App\Controllers\TodoController::class, 'store']);
$router->get('/backlog/{id}/edit', [\App\Controllers\TodoController::class, 'edit']);
$router->get('/backlog/{id}/convert', [\App\Controllers\TodoController::class, 'convertForm']);
$router->post('/backlog/{id}/convert', [\App\Controllers\TodoController::class, 'convert']);
$router->post('/backlog/{id}/archive', [\App\Controllers\TodoController::class, 'archive']);
$router->post('/backlog/{id}/delete', [\App\Controllers\TodoController::class, 'destroy']);
$router->post('/backlog/{id}', [\App\Controllers\TodoController::class, 'update']);

$router->get('/reports', [\App\Controllers\ReportController::class, 'index']);
$router->post('/reports', [\App\Controllers\ReportController::class, 'save']);
$router->post('/reports/submit', [\App\Controllers\ReportController::class, 'submit']);
$router->get('/reports/export', [\App\Controllers\ReportController::class, 'export']);

$router->get('/slow-performance', [\App\Controllers\SlowPerformanceController::class, 'index']);
$router->get('/slow-performance/export', [\App\Controllers\SlowPerformanceController::class, 'export']);
$router->get('/slow-performance/{id}/resolve', [\App\Controllers\SlowPerformanceController::class, 'showResolve']);
$router->post('/slow-performance/{id}/resolve', [\App\Controllers\SlowPerformanceController::class, 'resolve']);

$router->get('/settings', [\App\Controllers\SettingsController::class, 'index']);
$router->post('/settings', [\App\Controllers\SettingsController::class, 'update']);

$router->get('/login', [\App\Controllers\LoginController::class, 'show']);
$router->post('/login', [\App\Controllers\LoginController::class, 'attempt']);
$router->post('/logout', [\App\Controllers\LoginController::class, 'logout']);

$router->dispatch($request);
