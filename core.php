<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/classes/SchemaService.php';
require_once __DIR__ . '/classes/SessionService.php';
require_once __DIR__ . '/classes/AuthService.php';
require_once __DIR__ . '/classes/AppSettingsService.php';
require_once __DIR__ . '/classes/AttendanceService.php';
require_once __DIR__ . '/classes/SystemService.php';
require_once __DIR__ . '/classes/ViewService.php';

date_default_timezone_set('Asia/Manila');
SchemaService::ensureSchema($conn);

function ensure_schema(mysqli $conn): void
{
    SchemaService::ensureSchema($conn);
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    return SchemaService::columnExists($conn, $table, $column);
}

function e(string $value): string
{
    return ViewService::escape($value);
}

function set_flash(string $type, string $message): void
{
    SessionService::setFlash($type, $message);
}

function get_flash(): ?array
{
    return SessionService::getFlash();
}

function login_user(array $user): void
{
    SessionService::loginUser($user);
}

function logout_user(): void
{
    SessionService::logoutUser();
}

function current_user(): ?array
{
    global $conn;
    return AuthService::currentUser($conn);
}

function actual_user(): ?array
{
    global $conn;
    return AuthService::actualUser($conn);
}

function require_login(): array
{
    global $conn;
    return AuthService::requireLogin($conn);
}

function is_admin_or_staff(?array $user = null): bool
{
    global $conn;
    return AuthService::isAdminOrStaff($conn, $user);
}

function require_admin_or_staff(): array
{
    global $conn;
    return AuthService::requireAdminOrStaff($conn);
}

function has_role(array $user, array $roles): bool
{
    return AuthService::hasRole($user, $roles);
}

function require_roles(array $roles, string $message = 'Access denied.'): array
{
    global $conn;
    return AuthService::requireRoles($conn, $roles, $message);
}

function require_admin_only(): array
{
    return require_roles(['admin'], 'Access denied. Admin only.');
}

function current_admin_actor(): ?array
{
    global $conn;
    return AuthService::currentAdminActor($conn);
}

function is_view_as_active(): bool
{
    return AuthService::isViewAsActive();
}

function current_view_as_role(): ?string
{
    return SessionService::currentViewAsRole();
}

function start_view_as_role(string $role): void
{
    SessionService::startViewAsRole($role);
}

function stop_view_as_role(): void
{
    SessionService::stopViewAsRole();
}

function require_priest_only(): array
{
    return require_roles(['priest'], 'Access denied. Priest only.');
}

function get_app_setting(string $key, ?string $default = null): ?string
{
    global $conn;
    return AppSettingsService::get($conn, $key, $default);
}

function set_app_setting(string $key, ?string $value): void
{
    global $conn;
    AppSettingsService::set($conn, $key, $value);
}

function clear_app_setting(string $key): void
{
    global $conn;
    AppSettingsService::clear($conn, $key);
}

function user_setting_key(int $userId, string $key): string
{
    return 'user_setting:' . $userId . ':' . $key;
}

function get_user_setting(int $userId, string $key, ?string $default = null): ?string
{
    return get_app_setting(user_setting_key($userId, $key), $default);
}

function set_user_setting(int $userId, string $key, ?string $value): void
{
    set_app_setting(user_setting_key($userId, $key), $value);
}

function clear_user_setting(int $userId, string $key): void
{
    clear_app_setting(user_setting_key($userId, $key));
}

function log_activity_entry(int $userId, string $action, ?string $details = null): void
{
    global $conn;
    $action = trim($action);
    if ($userId <= 0 || $action === '') {
        return;
    }

    $stmt = $conn->prepare('INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iss', $userId, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function get_user_activity_logs(int $userId, int $limit = 20): array
{
    global $conn;
    $limit = max(1, min($limit, 50));
    $sql = "SELECT action, details, created_at
        FROM activity_logs
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT {$limit}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function notification_count(int $userId, bool $unreadOnly = false): int
{
    global $conn;
    $sql = 'SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?';
    if ($unreadOnly) {
        $sql .= ' AND is_read = 0';
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function get_user_notifications(int $userId, int $limit = 20, ?bool $unreadOnly = null): array
{
    global $conn;
    $limit = max(1, min($limit, 200));
    $sql = 'SELECT id, message, notification_type, action_url, action_label, is_read, created_at
        FROM notifications
        WHERE user_id = ?';
    if ($unreadOnly === true) {
        $sql .= ' AND is_read = 0';
    } elseif ($unreadOnly === false) {
        $sql .= ' AND is_read = 1';
    }
    $sql .= " ORDER BY created_at DESC, id DESC LIMIT {$limit}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function mark_all_notifications_read(int $userId): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function mark_notification_read(int $userId, int $notificationId): bool
{
    global $conn;
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();
    return $updated;
}

function get_user_notification(int $userId, int $notificationId): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT id, message, notification_type, action_url, action_label, is_read, created_at FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function app_now(): DateTimeImmutable
{
    global $conn;
    return SystemService::appNow($conn);
}

function current_datetime_validation_message(): string
{
    return 'Invalid input: The selected date and time has already passed.';
}

function password_strength_error(string $password, string $label = 'Password'): ?string
{
    if (strlen($password) < 8) {
        return $label . ' must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return $label . ' must include at least 1 uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return $label . ' must include at least 1 lowercase letter.';
    }

    if (!preg_match('/\d/', $password)) {
        return $label . ' must include at least 1 number.';
    }

    if (!preg_match('/[^A-Za-z\d]/', $password)) {
        return $label . ' must include at least 1 special character.';
    }

    return null;
}

function normalize_time_for_future_validation(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (str_contains($value, '-')) {
        [$value] = explode('-', $value, 2);
        $value = trim($value);
    }

    $timezone = new DateTimeZone(date_default_timezone_get());
    $formats = ['H:i:s', 'H:i', 'g:i A', 'g:iA', 'h:i A', 'h:iA'];
    foreach ($formats as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('H:i:s');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('H:i:s', $timestamp);
}

function validate_future_datetime_rule(array $rule): ?string
{
    $message = current_datetime_validation_message();
    $timezone = new DateTimeZone(date_default_timezone_get());
    $now = app_now();
    $nowMinute = $now->setTime((int)$now->format('H'), (int)$now->format('i'), 0);

    $datetimeLocal = trim((string)($rule['datetime_local'] ?? ''));
    if ($datetimeLocal !== '') {
        $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i'];
        $parsed = null;
        foreach ($formats as $format) {
            $candidate = DateTimeImmutable::createFromFormat($format, $datetimeLocal, $timezone);
            if ($candidate instanceof DateTimeImmutable) {
                $parsed = $candidate;
                break;
            }
        }
        if (!$parsed) {
            return 'Invalid date/time value.';
        }

        return $parsed < $nowMinute ? $message : null;
    }

    $dateValue = trim((string)($rule['date'] ?? ''));
    $timeValue = trim((string)($rule['time'] ?? ''));
    $allowBlank = array_key_exists('allow_blank', $rule) ? (bool)$rule['allow_blank'] : true;

    if ($dateValue === '' && $timeValue === '') {
        return $allowBlank ? null : $message;
    }

    if ($dateValue === '') {
        return null;
    }

    $dateParsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateValue, $timezone);
    if (!$dateParsed) {
        return 'Invalid date/time value.';
    }

    if ($timeValue === '') {
        return $dateParsed->format('Y-m-d') < $now->format('Y-m-d') ? $message : null;
    }

    $normalizedTime = normalize_time_for_future_validation($timeValue);
    if ($normalizedTime === null) {
        return 'Invalid date/time value.';
    }

    $scheduled = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateValue . ' ' . $normalizedTime, $timezone);
    if (!$scheduled) {
        return 'Invalid date/time value.';
    }

    return $scheduled < $nowMinute ? $message : null;
}

function validate_future_datetime_rules(array $rules): ?string
{
    foreach ($rules as $rule) {
        $error = validate_future_datetime_rule($rule);
        if ($error !== null) {
            return $error;
        }
    }

    return null;
}

function redirect_if_invalid_future_datetime_rules(array $rules, string $redirectUrl): void
{
    $error = validate_future_datetime_rules($rules);
    if ($error === null) {
        return;
    }

    set_flash('danger', $error);
    header('Location: ' . $redirectUrl);
    exit();
}

function purge_expired_announcements(): void
{
    global $conn;
    SystemService::purgeExpiredAnnouncements($conn);
}

function notify_user(
    int $userId,
    string $message,
    string $type = 'info',
    ?string $actionUrl = null,
    ?string $actionLabel = null
): void
{
    global $conn;
    SystemService::notifyUser($conn, $userId, $message, $type, $actionUrl, $actionLabel);
}

function generate_qr_token(int $length = 40): string
{
    return SystemService::generateQrToken($length);
}
?>
