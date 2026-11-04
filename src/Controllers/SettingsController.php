<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Support\Settings;

final class SettingsController extends Controller
{
    public function index(Request $request): void
    {
        $this->render('settings/index', [
            'pageTitle' => 'Settings',
            'settings' => Settings::all(),
            'phpLimits' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
        ]);
    }

    public function update(Request $request): void
    {
        $errors = [];

        $timezone = $request->string('timezone');
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable $e) {
            $errors['timezone'] = 'Unknown timezone.';
        }

        $uploadMb = max(1, min(2048, $request->int('upload_max_mb', 25)));
        $uploadBytes = $uploadMb * 1048576;

        if ($errors !== []) {
            store_errors($errors);
            flash('error', 'Please correct the settings.');
            redirect('/settings');
        }

        Settings::set('timezone', $timezone);
        Settings::set('upload_max_bytes', (string) $uploadBytes);

        flash('success', 'Settings saved.');
        redirect('/settings');
    }
}
