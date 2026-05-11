<?php
require_once __DIR__ . '/core.php';

$user = require_login();
$userId = (int)$user['id'];
$notificationId = (int)($_GET['id'] ?? 0);

if ($notificationId <= 0) {
    set_flash('danger', 'Invalid notification.');
    header('Location: notifications.php');
    exit();
}

$notification = get_user_notification($userId, $notificationId);
if (!$notification) {
    set_flash('danger', 'Notification not found.');
    header('Location: notifications.php');
    exit();
}

mark_notification_read($userId, $notificationId);

$target = trim((string)($notification['action_url'] ?? ''));
if ($target === '') {
    $target = 'notifications.php';
}

header('Location: ' . $target);
exit();
