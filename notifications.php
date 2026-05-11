<?php
require_once __DIR__ . '/layout.php';
$user = require_login();
$userId = (int)$user['id'];

function notification_badge_class(?string $type, bool $isRead): string
{
    $value = strtolower(trim((string)$type));
    return match ($value) {
        'success' => 'success',
        'warning' => 'warning',
        'error' => 'danger',
        'info' => 'info',
        default => $isRead ? 'secondary' : 'warning',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    mark_all_notifications_read($userId);
    set_flash('success', 'All notifications marked as read.');
    header('Location: notifications.php');
    exit();
}

$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}

$unreadCount = notification_count($userId, true);
$totalCount = notification_count($userId, false);
$notifications = get_user_notifications(
    $userId,
    200,
    $filter === 'unread' ? true : ($filter === 'read' ? false : null)
);
$notificationsPreview = array_slice($notifications, 0, 10);

render_header('Notifications', 'notifications');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="mb-1">Notifications <?php if ($unreadCount > 0): ?><span class="module-link-badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span><?php endif; ?></h2>
        <p class="text-secondary mb-0">Track updates for your request forms, service requests, and account activity.</p>
    </div>
    <form method="POST" class="m-0">
        <input type="hidden" name="mark_all_read" value="1">
        <button class="btn btn-outline-light" type="submit">Mark All Read</button>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <h6 class="text-warning mb-2">Total</h6>
                <div class="display-6"><?php echo $totalCount; ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <h6 class="text-warning mb-2">Unread</h6>
                <div class="display-6"><?php echo $unreadCount; ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-12 col-lg-6">
        <div class="card bg-dark border-warning-subtle h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn <?php echo $filter === 'all' ? 'btn-warning' : 'btn-outline-light'; ?>" href="notifications.php">All</a>
                    <a class="btn <?php echo $filter === 'unread' ? 'btn-warning' : 'btn-outline-light'; ?>" href="notifications.php?filter=unread">Unread</a>
                    <a class="btn <?php echo $filter === 'read' ? 'btn-warning' : 'btn-outline-light'; ?>" href="notifications.php?filter=read">Read</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card bg-dark border-warning-subtle">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="text-warning mb-0">All Notifications</h5>
            <?php if (count($notifications) > 10): ?>
                <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="modal" data-bs-target="#notificationsModal">View All</button>
            <?php endif; ?>
        </div>
        <?php if (!$notificationsPreview): ?>
            <div class="alert alert-info mb-0">No notifications found.</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notificationsPreview as $notification): ?>
                    <?php $notificationLink = 'notification_open.php?id=' . (int)$notification['id']; ?>
                    <?php $notificationBadgeClass = notification_badge_class($notification['notification_type'] ?? null, (int)$notification['is_read'] === 1); ?>
                    <a href="<?php echo e($notificationLink); ?>" class="list-group-item list-group-item-action bg-transparent border-secondary-subtle px-0">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                            <div class="small <?php echo (int)$notification['is_read'] === 1 ? 'text-secondary' : 'text-info'; ?>">
                                <?php echo e(date('M d, Y h:i A', strtotime((string)$notification['created_at']))); ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge text-bg-<?php echo e($notificationBadgeClass); ?>">
                                    <?php echo e($notification['notification_type'] ?: ((int)$notification['is_read'] === 1 ? 'read' : 'new')); ?>
                                </span>
                                <span class="small text-info"><?php echo e($notification['action_label'] ?: 'Open'); ?></span>
                            </div>
                        </div>
                        <div><?php echo e($notification['message']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (count($notifications) > 10): ?>
<div class="modal fade" id="notificationsModal" tabindex="-1" aria-labelledby="notificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-warning-subtle text-light">
            <div class="modal-header border-warning-subtle">
                <h5 class="modal-title text-warning" id="notificationsModalLabel">All Notifications</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <?php $notificationLink = 'notification_open.php?id=' . (int)$notification['id']; ?>
                        <?php $notificationBadgeClass = notification_badge_class($notification['notification_type'] ?? null, (int)$notification['is_read'] === 1); ?>
                        <a href="<?php echo e($notificationLink); ?>" class="list-group-item list-group-item-action bg-transparent border-secondary-subtle px-0">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                                <div class="small <?php echo (int)$notification['is_read'] === 1 ? 'text-secondary' : 'text-info'; ?>">
                                    <?php echo e(date('M d, Y h:i A', strtotime((string)$notification['created_at']))); ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge text-bg-<?php echo e($notificationBadgeClass); ?>">
                                        <?php echo e($notification['notification_type'] ?: ((int)$notification['is_read'] === 1 ? 'read' : 'new')); ?>
                                    </span>
                                    <span class="small text-info"><?php echo e($notification['action_label'] ?: 'Open'); ?></span>
                                </div>
                            </div>
                            <div><?php echo e($notification['message']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php render_footer(); ?>
