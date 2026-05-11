<?php
require_once __DIR__ . '/layout.php';
$user = require_login();
$isAdmin = is_admin_or_staff($user);
$formsPerPage = 5;

if ($isAdmin) {
    $stmt = $conn->prepare('SELECT r.id, r.form_type, r.title, r.status, r.requested_date, r.requested_time, r.created_at, u.full_name, u.email
        FROM service_requests r
        JOIN users u ON u.id = r.user_id
        ORDER BY r.created_at DESC');
} else {
    $uid = (int)$user['id'];
    $stmt = $conn->prepare('SELECT r.id, r.form_type, r.title, r.status, r.requested_date, r.requested_time, r.created_at, u.full_name, u.email
        FROM service_requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC');
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$formPage = max(1, (int)($_GET['page'] ?? 1));
$formTotal = count($rows);
$formTotalPages = max(1, (int)ceil($formTotal / $formsPerPage));
$formPage = min($formPage, $formTotalPages);
$formOffset = ($formPage - 1) * $formsPerPage;
$visibleRows = array_slice($rows, $formOffset, $formsPerPage);
$formPrevUrl = 'filled_forms.php?page=' . max(1, $formPage - 1);
$formNextUrl = 'filled_forms.php?page=' . min($formTotalPages, $formPage + 1);

render_header('Filled Forms', 'services');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Filled Forms</h2>
    <a class="btn btn-warning" href="services.php">Back to Services</a>
</div>
<p class="text-secondary mb-4">View all submitted forms and open the complete form details.</p>

<div class="card bg-dark border-warning-subtle">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="text-warning mb-0">Submitted Forms</h5>
            <?php if ($formTotal > 0): ?>
                <small class="text-secondary">Page <?php echo $formPage; ?> of <?php echo $formTotalPages; ?></small>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle request-table">
                <thead>
                    <tr>
                        <th class="request-table-user">Requester</th>
                        <th class="request-table-request">Form</th>
                        <th class="request-table-datetime">Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$visibleRows): ?>
                        <tr><td colspan="5" class="text-center">No submitted forms found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($visibleRows as $index => $r): ?>
                            <tr>
                                <td class="request-table-user">
                                    <div class="request-primary"><?php echo e($r['full_name'] ?: '-'); ?></div>
                                    <small class="request-secondary"><?php echo e($r['email']); ?></small>
                                </td>
                                <td>
                                    <div class="request-primary"><?php echo e($r['title']); ?></div>
                                    <small class="request-secondary"><?php echo e($r['form_type']); ?></small>
                                </td>
                                <td class="request-table-datetime">
                                    <div class="request-primary"><?php echo e($r['created_at']); ?></div>
                                    <small class="request-secondary">
                                        <?php echo e($r['requested_date'] ?: 'N/A'); ?> / <?php echo e($r['requested_time'] ? date('h:i A', strtotime($r['requested_time'])) : 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $badge = match ($r['status']) {
                                        'confirmed' => 'success',
                                        'rejected' => 'danger',
                                        'conflict' => 'warning',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge text-bg-<?php echo $badge; ?>"><?php echo e($r['status']); ?></span>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-outline-info" href="filled_form_view.php?id=<?php echo (int)$r['id']; ?>">Full View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($formTotalPages > 1): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <a class="btn btn-outline-light btn-sm<?php echo $formPage <= 1 ? ' disabled' : ''; ?>" href="<?php echo e($formPrevUrl); ?>"<?php echo $formPage <= 1 ? ' aria-disabled="true"' : ''; ?>>Previous</a>
                <small class="text-secondary">Showing <?php echo $formOffset + 1; ?>-<?php echo $formOffset + count($visibleRows); ?> of <?php echo $formTotal; ?></small>
                <a class="btn btn-outline-light btn-sm<?php echo $formPage >= $formTotalPages ? ' disabled' : ''; ?>" href="<?php echo e($formNextUrl); ?>"<?php echo $formPage >= $formTotalPages ? ' aria-disabled="true"' : ''; ?>>Next</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
