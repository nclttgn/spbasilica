<?php
require_once __DIR__ . '/layout.php';

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    set_flash('danger', 'Invalid attendance QR link.');
    header('Location: attendance.php');
    exit();
}

$stmt = $conn->prepare('SELECT * FROM event_schedules WHERE qr_token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    set_flash('danger', 'Event schedule not found for this QR code.');
    header('Location: attendance.php');
    exit();
}

$user = current_user();
$roleKey = strtolower(trim((string)($user['role'] ?? '')));
$canDeleteAttendance = in_array($roleKey, ['admin', 'staff', 'minister', 'priest'], true);
$canScanAttendance = in_array($roleKey, ['admin', 'staff', 'minister', 'priest'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attendance_id'])) {
    if (!$canDeleteAttendance) {
        set_flash('danger', 'Only admin/staff/minister/priest can delete attendance records.');
        header('Location: attendance_scan.php?token=' . urlencode($token));
        exit();
    }

    $deleteId = (int)($_POST['delete_attendance_id'] ?? 0);
    if ($deleteId <= 0) {
        set_flash('danger', 'Invalid attendance record.');
        header('Location: attendance_scan.php?token=' . urlencode($token));
        exit();
    }

    $scheduleId = (int)$event['id'];
    $del = $conn->prepare('DELETE FROM attendance_logs WHERE id = ? AND schedule_id = ? LIMIT 1');
    $del->bind_param('ii', $deleteId, $scheduleId);
    $del->execute();
    $deleted = $del->affected_rows > 0;
    $del->close();

    if ($deleted) {
        set_flash('success', 'Attendance record deleted.');
    } else {
        set_flash('warning', 'Attendance record was not found.');
    }
    header('Location: attendance_scan.php?token=' . urlencode($token));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['scan_qr_text'])) {
        if (!$canScanAttendance) {
            set_flash('danger', 'Only admin/staff/minister/priest can scan attendance QR codes.');
            header('Location: attendance_scan.php?token=' . urlencode($token));
            exit();
        }

        $qrText = trim((string)($_POST['scan_qr_text'] ?? ''));
        if ($qrText === '') {
            set_flash('danger', 'QR code scan data is required.');
            header('Location: attendance_scan.php?token=' . urlencode($token));
            exit();
        }

        $verified = AttendanceService::verifyQrToken($conn, $qrText);
        if (!$verified['ok']) {
            set_flash('danger', $verified['message'] ?? 'QR validation failed.');
            header('Location: attendance_scan.php?token=' . urlencode($token));
            exit();
        }

        $verifiedUser = $verified['user'] ?? [];
        $participantName = trim((string)($verifiedUser['full_name'] ?? ''));
        $participantEmail = trim((string)($verifiedUser['email'] ?? ''));
        $userId = (int)($verifiedUser['id'] ?? 0);
        $scheduleId = (int)$event['id'];

        if ($participantName === '' || $participantEmail === '' || $userId <= 0) {
            set_flash('danger', 'Scanned QR data is incomplete.');
            header('Location: attendance_scan.php?token=' . urlencode($token));
            exit();
        }

        $check = $conn->prepare('SELECT id FROM attendance_logs WHERE schedule_id = ? AND participant_email = ? LIMIT 1');
        $check->bind_param('is', $scheduleId, $participantEmail);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            set_flash('warning', 'Attendance already recorded for this participant.');
            header('Location: attendance_scan.php?token=' . urlencode($token));
            exit();
        }

        $stmt = $conn->prepare('INSERT INTO attendance_logs (schedule_id, user_id, participant_name, participant_email, source) VALUES (?, ?, ?, ?, "qr")');
        $stmt->bind_param('iiss', $scheduleId, $userId, $participantName, $participantEmail);
        $stmt->execute();
        $stmt->close();

        set_flash('success', $participantName . ' checked in successfully.');
        header('Location: attendance_scan.php?token=' . urlencode($token));
        exit();
    }

    $participantName = trim($_POST['participant_name'] ?? '');
    $participantEmail = trim($_POST['participant_email'] ?? '');
    $userId = null;

    if ($user) {
        $participantName = $user['full_name'] ?: $user['email'];
        $participantEmail = $user['email'];
        $userId = (int)$user['id'];
    }

    if ($participantName === '') {
        set_flash('danger', 'Participant name is required.');
        header('Location: attendance_scan.php?token=' . urlencode($token));
        exit();
    }

    if ($participantEmail === '') {
        $participantEmail = strtolower(str_replace(' ', '', $participantName)) . '@guest.local';
    }

    $check = $conn->prepare('SELECT id FROM attendance_logs WHERE schedule_id = ? AND participant_email = ? LIMIT 1');
    $scheduleId = (int)$event['id'];
    $check->bind_param('is', $scheduleId, $participantEmail);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        set_flash('warning', 'Attendance already recorded for this participant.');
        header('Location: attendance_scan.php?token=' . urlencode($token));
        exit();
    }

    if ($userId !== null) {
        $stmt = $conn->prepare('INSERT INTO attendance_logs (schedule_id, user_id, participant_name, participant_email, source) VALUES (?, ?, ?, ?, "qr")');
        $stmt->bind_param('iiss', $scheduleId, $userId, $participantName, $participantEmail);
    } else {
        $stmt = $conn->prepare('INSERT INTO attendance_logs (schedule_id, participant_name, participant_email, source) VALUES (?, ?, ?, "qr")');
        $stmt->bind_param('iss', $scheduleId, $participantName, $participantEmail);
    }
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Attendance check-in successful.');
    header('Location: attendance_scan.php?token=' . urlencode($token));
    exit();
}

$attendeesStmt = $conn->prepare('SELECT id, participant_name, participant_email, scanned_at FROM attendance_logs WHERE schedule_id = ? ORDER BY scanned_at DESC LIMIT 30');
$sid = (int)$event['id'];
$attendeesStmt->bind_param('i', $sid);
$attendeesStmt->execute();
$attendees = $attendeesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$attendeesStmt->close();

render_header('Attendance Check-In', 'attendance');
?>
<div class="card bg-dark border-warning-subtle">
    <div class="card-body">
        <h3 class="text-warning mb-2">Attendance Check-In</h3>
        <p class="mb-1"><strong>Event:</strong> <?php echo e($event['title']); ?></p>
        <p class="mb-1"><strong>Date/Time:</strong> <?php echo e($event['event_date']); ?> <?php echo e(date('h:i A', strtotime($event['event_time']))); ?></p>
        <p class="mb-4"><strong>Location:</strong> <?php echo e($event['location'] ?: 'TBA'); ?></p>

        <?php if ($canScanAttendance): ?>
            <div class="card bg-black border-secondary mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="text-warning mb-1">Scan Attendance QR</h5>
                            <p class="text-secondary mb-0">Use the camera to scan staff and minister attendance QR codes for this event.</p>
                        </div>
                    </div>
                    <div id="event-attendance-reader" class="attendance-reader mb-3"></div>
                    <div id="event-attendance-scan-status" class="alert alert-secondary mb-3">Preparing camera access for QR scanning.</div>
                    <form method="POST" id="eventAttendanceScanForm">
                        <input type="hidden" name="scan_qr_text" id="scan_qr_text">
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="row g-3 mb-4">
            <?php if ($user): ?>
                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        Logged in as <strong><?php echo e($user['full_name'] ?: $user['email']); ?></strong>. Click to confirm check-in.
                    </div>
                </div>
            <?php else: ?>
                <div class="col-md-6">
                    <label class="form-label">Participant Name</label>
                    <input class="form-control" type="text" name="participant_name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Participant Email</label>
                    <input class="form-control" type="email" name="participant_email" placeholder="optional@email.com">
                </div>
            <?php endif; ?>
            <div class="col-12">
                <button class="btn btn-warning" type="submit">Confirm Attendance</button>
                <a class="btn btn-outline-light ms-2" href="attendance.php">Back</a>
            </div>
        </form>

        <h5 class="text-warning">Recent Attendees</h5>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Scanned At</th>
                        <?php if ($canDeleteAttendance): ?>
                            <th class="text-end">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$attendees): ?>
                        <tr><td colspan="<?php echo $canDeleteAttendance ? '4' : '3'; ?>" class="text-center">No attendance records yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendees as $a): ?>
                            <tr>
                                <td><?php echo e($a['participant_name']); ?></td>
                                <td><?php echo e($a['participant_email']); ?></td>
                                <td><?php echo e($a['scanned_at']); ?></td>
                                <?php if ($canDeleteAttendance): ?>
                                    <td class="text-end">
                                        <form method="POST">
                                            <input type="hidden" name="delete_attendance_id" value="<?php echo (int)$a['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($canScanAttendance): ?>
    <script src="https://unpkg.com/html5-qrcode" defer></script>
    <script>
        (function () {
            var readerId = 'event-attendance-reader';
            var statusEl = document.getElementById('event-attendance-scan-status');
            var qrInput = document.getElementById('scan_qr_text');
            var form = document.getElementById('eventAttendanceScanForm');
            var lastScan = '';
            var submitting = false;

            function setStatus(message, level) {
                if (!statusEl) {
                    return;
                }
                statusEl.className = 'alert mb-3';
                statusEl.classList.add(level === 'danger' ? 'alert-danger' : level === 'warning' ? 'alert-warning' : level === 'success' ? 'alert-success' : 'alert-secondary');
                statusEl.textContent = message;
            }

            function startScanner() {
                if (!window.Html5Qrcode || !qrInput || !form) {
                    setStatus('QR scanner library could not be loaded.', 'danger');
                    return;
                }

                var scanner = new window.Html5Qrcode(readerId);
                window.Html5Qrcode.getCameras().then(function (devices) {
                    if (!devices || !devices.length) {
                        setStatus('No camera was detected for event attendance scanning.', 'danger');
                        return;
                    }

                    scanner.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: 240 },
                        function (decodedText) {
                            if (submitting || !decodedText || decodedText === lastScan) {
                                return;
                            }
                            lastScan = decodedText;
                            submitting = true;
                            setStatus('QR code detected. Recording attendance...', 'success');
                            qrInput.value = decodedText;
                            form.submit();
                        },
                        function () {}
                    ).then(function () {
                        setStatus('Scanner ready. Point the camera at an attendance QR code.', 'secondary');
                    }).catch(function () {
                        setStatus('Unable to start the QR camera scanner.', 'danger');
                    });
                }).catch(function () {
                    setStatus('Unable to access camera devices for scanning.', 'danger');
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startScanner);
            } else {
                startScanner();
            }
        })();
    </script>
<?php endif; ?>
<?php render_footer(); ?>
