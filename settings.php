<?php
require_once __DIR__ . '/layout.php';
$user = require_login();
$userId = (int)$user['id'];
$roleLabel = (($user['role'] ?? '') === 'user') ? 'parishioner' : ($user['role'] ?? 'parishioner');

$seasonThemeOptions = [
    'auto' => 'Automatic by Church Season',
    'ordinary' => 'Ordinary Time',
    'lent' => 'Lent',
    'easter' => 'Easter',
    'advent' => 'Advent',
    'christmas' => 'Christmas',
];

if (isset($_POST['change_password'])) {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($currentPassword, (string)($row['password'] ?? ''))) {
        set_flash('danger', 'Current password is incorrect.');
        header('Location: settings.php');
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        set_flash('danger', 'New password and confirmation do not match.');
        header('Location: settings.php');
        exit();
    }

    $passwordError = password_strength_error($newPassword, 'New password');
    if ($passwordError !== null) {
        set_flash('danger', $passwordError);
        header('Location: settings.php');
        exit();
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $update->bind_param('si', $passwordHash, $userId);
    $update->execute();
    $update->close();

    log_activity_entry($userId, 'Changed password', 'Password updated from Settings.');
    set_flash('success', 'Password updated successfully.');
    header('Location: settings.php');
    exit();
}

if (isset($_POST['save_season_theme'])) {
    $seasonTheme = strtolower(trim((string)($_POST['season_theme'] ?? 'auto')));
    if (!array_key_exists($seasonTheme, $seasonThemeOptions)) {
        $seasonTheme = 'auto';
    }

    set_user_setting($userId, 'season_theme', $seasonTheme);
    log_activity_entry($userId, 'Updated seasonal theme', 'Season theme: ' . $seasonTheme . '.');

    set_flash('success', 'Seasonal theme updated.');
    header('Location: settings.php');
    exit();
}

if (isset($_POST['update_app_datetime'])) {
    if (($user['role'] ?? '') !== 'admin') {
        set_flash('danger', 'Only admin can update system date/time.');
        header('Location: settings.php');
        exit();
    }

    $input = trim((string)($_POST['app_datetime'] ?? ''));
    if ($input === '') {
        clear_app_setting('app_datetime_override');
        log_activity_entry($userId, 'Reset system date/time', 'Returned app time to server time.');
        set_flash('success', 'System date/time reset to server current time.');
        header('Location: settings.php');
        exit();
    }

    redirect_if_invalid_future_datetime_rules([
        ['datetime_local' => $input, 'allow_blank' => false],
    ], 'settings.php');

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $input);
    if (!$parsed) {
        set_flash('danger', 'Invalid date/time value.');
        header('Location: settings.php');
        exit();
    }

    set_app_setting('app_datetime_override', $parsed->format('Y-m-d H:i:s'));
    log_activity_entry($userId, 'Updated system date/time', 'New app time: ' . $parsed->format('Y-m-d H:i:s') . '.');
    set_flash('success', 'System date/time updated.');
    header('Location: settings.php');
    exit();
}

$appDateTimeInput = app_now()->format('Y-m-d\TH:i');
$appOverrideRaw = get_app_setting('app_datetime_override', null);
$seasonTheme = get_user_setting($userId, 'season_theme', 'auto') ?: 'auto';
$currentSeasonKey = $seasonTheme === 'auto' ? SystemService::liturgicalSeason(app_now()) : $seasonTheme;
$currentSeasonLabel = $seasonThemeOptions[$currentSeasonKey] ?? ucfirst($currentSeasonKey);
$activityLogs = get_user_activity_logs($userId, 20);
$activityLogsPreview = array_slice($activityLogs, 0, 10);

render_header('Settings', 'settings');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <h4 class="mb-3 text-warning">Session</h4>
                <p class="mb-2"><?php echo e($user['full_name'] ?: $user['email']); ?></p>
                <small class="text-uppercase d-block mb-3"><?php echo e($roleLabel); ?></small>
                <a class="btn btn-outline-light" href="logout.php">Logout</a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card bg-dark border-warning-subtle">
            <div class="card-body">
                <h4 class="mb-3 text-warning">Seasonal Theme</h4>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="save_season_theme" value="1">
                    <div class="col-12">
                        <label class="form-label">Theme Based on Liturgical Season</label>
                        <select class="form-select theme-select" name="season_theme" required>
                            <?php foreach ($seasonThemeOptions as $value => $label): ?>
                                <option value="<?php echo e($value); ?>" <?php echo $seasonTheme === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-secondary mb-0">
                            Current theme season: <strong><?php echo e($currentSeasonLabel); ?></strong>.
                            Automatic mode follows the church calendar seasons already styled in the app.
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-warning" type="submit">Save Seasonal Theme</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <h4 class="mb-3 text-warning">Change Password</h4>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="change_password" value="1">
                    <div class="col-12">
                        <label class="form-label">Current Password</label>
                        <input class="form-control" type="password" name="current_password" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">New Password</label>
                        <input class="form-control" type="password" name="new_password" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm New Password</label>
                        <input class="form-control" type="password" name="confirm_password" required>
                    </div>
                    <div class="col-12">
                        <small class="text-secondary d-block mb-2">Use at least 8 characters with 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.</small>
                        <button class="btn btn-warning" type="submit">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (($user['role'] ?? '') === 'admin'): ?>
        <div class="col-lg-6">
            <div class="card bg-dark border-warning-subtle h-100">
                <div class="card-body">
                    <h4 class="mb-3 text-warning">System Date and Time</h4>
                    <form method="POST" class="row g-3 align-items-end">
                        <input type="hidden" name="update_app_datetime" value="1">
                        <div class="col-12">
                            <label class="form-label">Date/Time</label>
                            <input class="form-control" type="datetime-local" name="app_datetime" data-datetime-future="true" data-datetime-role="datetime-local" value="<?php echo e($appDateTimeInput); ?>">
                        </div>
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-warning" type="submit">Save Date/Time</button>
                                <button class="btn btn-outline-light" type="submit" name="app_datetime" value="" data-datetime-submit-ignore>Reset to Server Time</button>
                            </div>
                            <small class="text-secondary d-block mt-2">
                                <?php echo $appOverrideRaw ? 'Custom app time is active.' : 'Using current server time.'; ?>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="col-12">
        <div class="card bg-dark border-warning-subtle">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h4 class="mb-0 text-warning">Activity Log</h4>
                    <?php if (count($activityLogs) > 10): ?>
                        <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="modal" data-bs-target="#activityLogModal">View All</button>
                    <?php endif; ?>
                </div>
                <?php if (!$activityLogsPreview): ?>
                    <div class="alert alert-info mb-0">No activity records yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activityLogsPreview as $log): ?>
                                    <tr>
                                        <td><?php echo e($log['created_at']); ?></td>
                                        <td><?php echo e($log['action']); ?></td>
                                        <td><?php echo e($log['details'] ?: '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (count($activityLogs) > 10): ?>
<div class="modal fade" id="activityLogModal" tabindex="-1" aria-labelledby="activityLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-warning-subtle text-light">
            <div class="modal-header border-warning-subtle">
                <h5 class="modal-title text-warning" id="activityLogModalLabel">All Activity Log Entries</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-dark table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activityLogs as $log): ?>
                                <tr>
                                    <td><?php echo e($log['created_at']); ?></td>
                                    <td><?php echo e($log['action']); ?></td>
                                    <td><?php echo e($log['details'] ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
