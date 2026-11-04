<?php
$zones = [
    'UTC', 'Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Asia/Tokyo',
    'Europe/London', 'Europe/Berlin', 'Europe/Paris', 'America/New_York',
    'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Australia/Sydney',
];
if (!in_array($settings['timezone'], $zones, true)) {
    array_unshift($zones, $settings['timezone']);
}
$uploadMb = (int) round($settings['upload_max_bytes'] / 1048576);
?>
<div class="page-head">
    <div>
        <h1>Settings</h1>
        <div class="sub">Stored in the database and applied immediately.</div>
    </div>
</div>

<form method="post" action="<?= e(url('/settings')) ?>" class="card">
    <?= csrf_field() ?>
    <h2>Application</h2>

    <div class="form-grid">
        <label>
            <span>Display timezone</span>
            <select name="timezone">
                <?php foreach ($zones as $zone): ?>
                    <option value="<?= e($zone) ?>" <?= $settings['timezone'] === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="muted small">Timestamps are stored in UTC and shown in this timezone.</div>
        </label>

        <label>
            <span>Max ZIP upload size (MB)</span>
            <input type="number" name="upload_max_mb" value="<?= (int) $uploadMb ?>" min="1" max="2048">
            <div class="muted small">Enforced server-side after upload.</div>
        </label>
    </div>

    <button type="submit" class="btn btn-primary">Save settings</button>
</form>

<div class="card">
    <h2>Server limits</h2>
    <p class="muted small">These come from php.ini and cap uploads independently of the app setting above.</p>
    <table>
        <tr><th>upload_max_filesize</th><td class="mono"><?= e((string) $phpLimits['upload_max_filesize']) ?></td></tr>
        <tr><th>post_max_size</th><td class="mono"><?= e((string) $phpLimits['post_max_size']) ?></td></tr>
        <tr><th>max_execution_time</th><td class="mono"><?= e((string) $phpLimits['max_execution_time']) ?>s</td></tr>
    </table>
</div>

<div class="card">
    <h2>Security notes</h2>
    <ul class="small muted">
        <li>Run locally and bind to <span class="mono">127.0.0.1</span>. Do not expose this app to the internet without authentication and TLS.</li>
        <li>Set <span class="mono">APP_PASSWORD</span> in <span class="mono">.env</span> to require a login (currently <?= \App\Support\Auth::enabled() ? 'enabled' : 'disabled' ?>).</li>
        <li>Uploaded ZIPs live under <span class="mono">storage/uploads</span>, outside the public web root.</li>
    </ul>
</div>
