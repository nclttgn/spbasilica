<?php
require_once __DIR__ . '/layout.php';
$actor = require_roles(['admin', 'staff', 'priest'], 'Access denied. Admin/Staff/Priest only.');

$actorRole = (string)($actor['role'] ?? '');
$isPriest = $actorRole === 'priest';
$selectedKind = trim((string) ($_GET['kind'] ?? $_POST['event_kind'] ?? 'event'));
if ($selectedKind !== 'mass') {
    $selectedKind = 'event';
}
$redirectKind = $selectedKind;

function priest_is_available(mysqli $conn, int $priestId, string $eventDate, string $eventTime): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM event_schedules WHERE event_kind = "mass" AND priest_id = ? AND event_date = ? AND event_time = ? AND status IN ("pending_priest", "confirmed")');
    $stmt->bind_param('iss', $priestId, $eventDate, $eventTime);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)($row['total'] ?? 0)) === 0;
}

function schedule_admin_url(string $kind = 'event', int $requestId = 0): string
{
    $kind = $kind === 'mass' ? 'mass' : 'event';
    $params = ['kind' => $kind];
    if ($requestId > 0) {
        $params['request_id'] = $requestId;
    }
    if (!empty($_GET['sched_page'])) {
        $params['sched_page'] = max(1, (int)$_GET['sched_page']);
    }
    return 'event_schedule_admin.php?' . http_build_query($params);
}

function schedule_admin_edit_url(string $kind, int $scheduleId, string $filter = 'all'): string
{
    $kind = $kind === 'mass' ? 'mass' : 'event';
    $params = ['kind' => $kind, 'edit_id' => $scheduleId];
    if ($filter === 'pending') {
        $params['filter'] = 'pending';
    }
    if (!empty($_GET['sched_page'])) {
        $params['sched_page'] = max(1, (int)$_GET['sched_page']);
    }
    return 'event_schedule_admin.php?' . http_build_query($params);
}

function schedule_admin_form_url(string $kind, int $requestId = 0, int $scheduleId = 0): string
{
    if ($scheduleId > 0) {
        return schedule_admin_edit_url($kind, $scheduleId);
    }
    return schedule_admin_url($kind, $requestId);
}

function schedule_time_options(): array
{
    $options = [];
    $start = new DateTimeImmutable('05:00');
    $end = new DateTimeImmutable('21:00');
    for ($time = $start; $time <= $end; $time = $time->modify('+30 minutes')) {
        $value = $time->format('H:i');
        $options[$value] = $time->format('h:i A');
    }
    return $options;
}

$priests = [];
$priestStmt = $conn->prepare('SELECT id, full_name, email FROM users WHERE role = "priest" ORDER BY full_name ASC, email ASC');
$priestStmt->execute();
$priests = $priestStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$priestStmt->close();

$eventOptions = [
    'Baptism' => 'Baptism',
    'Wedding' => 'Wedding',
    'Meeting' => 'Ministry Meeting',
    'Retreat' => 'Retreat or Recollection',
    'Formation' => 'Faith Formation / Seminar',
    'Devotion' => 'Procession or Devotional',
    'Community' => 'Community / Outreach Event',
    'other' => 'Other (specify below)',
];

$massOptions = [
    'Mass' => 'Regular Mass',
    'Sunday Mass' => 'Sunday Mass',
    'Anticipated Mass' => 'Anticipated Mass',
    'Wedding Mass' => 'Wedding Mass',
    'Funeral Mass' => 'Funeral / Burial Mass',
    'Memorial Mass' => 'Memorial Mass',
    'Healing Mass' => 'Healing Mass',
    'Thanksgiving Mass' => 'Thanksgiving Mass',
    'Novena Mass' => 'Novena Mass',
    'Fiesta Mass' => 'Fiesta Mass',
    'Votive Mass' => 'Votive Mass',
    'other' => 'Other (specify below)',
];

$ministryOptions = [
    'Shrine Ministry',
    'Worship Ministry',
    'Extraordinary Ministers of Holy Communion',
    'Music Ministry',
    'Ministry of Lectors and Commentators',
    'Ministry of Altar Servers',
    'Greeters and Collectors',
    'Mother Butler Guild',
    'Bereavement Ministry',
    'Education and Formation Ministry',
    'Cathethical Ministry',
    'Vocation Ministry',
    'Synod Animator',
    'Mission Ministry',
    'Pastoral Care for LGBTQIA',
    'Social Services and Development Ministry',
    'Livelihood and Job Placement',
    'Public Affairs Ministry',
    'Elderly Ministry',
    'Ecology Ministry',
    'DRRM',
    'Health Ministry',
    'JPIC and Ubran Poor Ministry',
    'Restorative Justice Ministry',
    'Youth Ministry',
    'Social Communications Ministry',
    'Family and Life Ministry',
    'Migrants Ministry',
    'Temporalities Ministry',
    'Catholic Women League',
    'El Shaddai',
    'Holy Name Society',
    'Women for Christ',
    'Divine Mercy Apostolate',
];

$eventMinistryRecommendations = [
    'Baptism' => [
        'Worship Ministry',
        'Extraordinary Ministers of Holy Communion',
        'Music Ministry',
        'Ministry of Lectors and Commentators',
        'Ministry of Altar Servers',
        'Greeters and Collectors',
    ],
    'Wedding' => [
        'Worship Ministry',
        'Music Ministry',
        'Ministry of Lectors and Commentators',
        'Ministry of Altar Servers',
        'Greeters and Collectors',
        'Family and Life Ministry',
    ],
    'Funeral' => [
        'Worship Ministry',
        'Music Ministry',
        'Ministry of Lectors and Commentators',
        'Ministry of Altar Servers',
        'Bereavement Ministry',
    ],
    'Meeting' => [
        'Education and Formation Ministry',
        'Public Affairs Ministry',
        'Social Communications Ministry',
    ],
    'Retreat' => [
        'Education and Formation Ministry',
        'Worship Ministry',
        'Music Ministry',
        'Youth Ministry',
        'Vocation Ministry',
    ],
    'Formation' => [
        'Education and Formation Ministry',
        'Cathethical Ministry',
        'Vocation Ministry',
        'Youth Ministry',
    ],
    'Devotion' => [
        'Worship Ministry',
        'Music Ministry',
        'Divine Mercy Apostolate',
        'Holy Name Society',
    ],
    'Community' => [
        'Mission Ministry',
        'Social Services and Development Ministry',
        'Livelihood and Job Placement',
        'Health Ministry',
        'JPIC and Ubran Poor Ministry',
        'Ecology Ministry',
    ],
];


$facilityOptions = [
    'Minor Basilica Main Hall',
    'St. Francis of Assisi Hall (2nd Floor)',
    'St. Peter of Alcantara (Peach Room)',
    'St. Margaret of Cortona (Green Room)',
    'St. Louis IX (Blue Room)',
    'Holy Cave',
    'Portiuncula Formation and Renewal Hall',
    'Brother Sun Sister Moon Garden',
    'San Damiano Garden',
    'Chamber Room',
];

$timeOptions = schedule_time_options();

$prefill = [
    'request_id' => null,
    'schedule_id' => null,
    'event_title_choice' => '',
    'event_title_other' => '',
    'event_ministry' => '',
    'event_ministries' => [],
    'event_date' => '',
    'event_time' => '',
    'priest_id' => '',
    'location_type' => 'inside',
    'location_inside' => '',
    'location_outside' => '',
    'description' => '',
    'event_kind' => $selectedKind,
];

if (isset($_GET['request_id'])) {
    $prefillId = (int)$_GET['request_id'];
    if ($prefillId > 0) {
        $reqStmt = $conn->prepare('SELECT id, user_id, title, form_type, details FROM service_requests WHERE id = ? LIMIT 1');
        $reqStmt->bind_param('i', $prefillId);
        $reqStmt->execute();
        $reqRow = $reqStmt->get_result()->fetch_assoc();
        $reqStmt->close();

        if ($reqRow && ($reqRow['form_type'] ?? '') === 'Event Creation Request') {
            $details = [];
            if (!empty($reqRow['details'])) {
                $decoded = json_decode((string)$reqRow['details'], true);
                if (is_array($decoded)) {
                    $details = $decoded;
                }
            }
            $title = trim((string)($details['event_name'] ?? $reqRow['title'] ?? ''));
            $requestKind = trim((string)($details['request_kind'] ?? ''));
            if ($requestKind === 'mass' || strcasecmp($title, 'Mass') === 0) {
                $prefill['event_kind'] = 'mass';
            }
            $availableTitleOptions = $prefill['event_kind'] === 'mass' ? $massOptions : $eventOptions;
            if ($title !== '' && array_key_exists($title, $availableTitleOptions)) {
                $prefill['event_title_choice'] = $title;
            } elseif ($title !== '') {
                $prefill['event_title_choice'] = 'other';
                $prefill['event_title_other'] = $title;
            }
            $prefill['event_ministry'] = trim((string)($details['event_ministry'] ?? ''));
            $prefillMinistries = $details['event_ministries'] ?? [];
            if (!is_array($prefillMinistries)) {
                $prefillMinistries = [];
            }
            if (!$prefillMinistries && $prefill['event_ministry'] !== '') {
                $prefillMinistries = array_map('trim', explode(',', $prefill['event_ministry']));
            }
            $prefill['event_ministries'] = array_values(array_intersect($ministryOptions, $prefillMinistries));
            $prefill['event_date'] = trim((string)($details['event_date'] ?? ''));
            $prefill['event_time'] = trim((string)($details['event_time'] ?? ''));
            $prefill['description'] = trim((string)($details['description'] ?? ''));
            $loc = trim((string)($details['location'] ?? ''));
            if ($loc !== '' && in_array($loc, $facilityOptions, true)) {
                $prefill['location_type'] = 'inside';
                $prefill['location_inside'] = $loc;
            } elseif ($loc !== '') {
                $prefill['location_type'] = 'outside';
                $prefill['location_outside'] = $loc;
            }
            $prefill['request_id'] = $prefillId;
        }
    }
}

if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    if ($editId > 0) {
        $editStmt = $conn->prepare('SELECT id, title, description, event_date, event_time, location, event_ministry, event_kind, priest_id FROM event_schedules WHERE id = ? LIMIT 1');
        $editStmt->bind_param('i', $editId);
        $editStmt->execute();
        $editRow = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();

        if ($editRow) {
            $prefill['schedule_id'] = (int)$editRow['id'];
            $prefill['event_kind'] = (($editRow['event_kind'] ?? 'event') === 'mass') ? 'mass' : 'event';
            $title = trim((string)($editRow['title'] ?? ''));
            $availableTitleOptions = $prefill['event_kind'] === 'mass' ? $massOptions : $eventOptions;
            if ($title !== '' && array_key_exists($title, $availableTitleOptions)) {
                $prefill['event_title_choice'] = $title;
            } elseif ($title !== '') {
                $prefill['event_title_choice'] = 'other';
                $prefill['event_title_other'] = $title;
            }
            $prefill['event_ministry'] = trim((string)($editRow['event_ministry'] ?? ''));
            if ($prefill['event_ministry'] !== '') {
                $prefill['event_ministries'] = array_values(array_intersect(
                    $ministryOptions,
                    array_map('trim', explode(',', $prefill['event_ministry']))
                ));
            }
            $prefill['event_date'] = trim((string)($editRow['event_date'] ?? ''));
            $prefill['event_time'] = trim((string)($editRow['event_time'] ?? ''));
            $prefill['description'] = trim((string)($editRow['description'] ?? ''));
            $prefill['priest_id'] = (string)($editRow['priest_id'] ?? '');
            $loc = trim((string)($editRow['location'] ?? ''));
            if ($loc !== '' && in_array($loc, $facilityOptions, true)) {
                $prefill['location_type'] = 'inside';
                $prefill['location_inside'] = $loc;
            } elseif ($loc !== '') {
                $prefill['location_type'] = 'outside';
                $prefill['location_outside'] = $loc;
            }
        }
    }
}

$titleOptions = $prefill['event_kind'] === 'mass' ? $massOptions : $eventOptions;
$titleFieldLabel = $prefill['event_kind'] === 'mass' ? 'Mass Type' : 'Event Name';
$titlePlaceholder = $prefill['event_kind'] === 'mass' ? 'Select mass type' : 'Select event type';
$titleHelpText = $prefill['event_kind'] === 'mass'
    ? 'Choose the Mass type from the list or pick "Other" to describe a custom Mass.'
    : 'Choose from the list above or pick "Other" to describe a custom event.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'ajax_avail') {
        header('Content-Type: application/json');
        $eventDate = trim($_POST['event_date'] ?? '');
        $eventTime = trim($_POST['event_time'] ?? '');
        $priestId = (int)($_POST['priest_id'] ?? 0);
        $available = 0;
        if ($priestId > 0 && $eventDate && $eventTime && priest_is_available($conn, $priestId, $eventDate, $eventTime)) {
            $available = 1;
        }
        echo json_encode(['available' => $available]);
        exit();
    }

    if ($action === 'check_availability') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $eventDate = trim($_POST['event_date'] ?? '');
        $eventTime = trim($_POST['event_time'] ?? '');
        $eventKind = trim($_POST['event_kind'] ?? 'event');
        if ($eventKind !== 'mass') { $eventKind = 'event'; }
        $redirectKind = $eventKind;
        $priestId = (int)($_POST['priest_id'] ?? 0);
        if ($eventKind !== 'mass') {
            set_flash('info', 'Priest availability checks apply to Mass schedules only.');
            header('Location: ' . schedule_admin_form_url($redirectKind, $requestId, $scheduleId));
            exit();
        }
        if ($priestId <= 0 || $eventDate === '' || $eventTime === '') {
            set_flash('danger', 'Select priest, date, and time to check availability.');
            header('Location: ' . schedule_admin_form_url($redirectKind, $requestId, $scheduleId));
            exit();
        }
        redirect_if_invalid_future_datetime_rules([
            ['date' => $eventDate, 'time' => $eventTime, 'allow_blank' => false],
        ], schedule_admin_form_url($redirectKind, $requestId, $scheduleId));
        if (priest_is_available($conn, $priestId, $eventDate, $eventTime)) {
            set_flash('success', 'Priest is available for the selected Mass schedule.');
        } else {
            set_flash('danger', 'Priest is not available at the selected date/time.');
        }
        header('Location: ' . schedule_admin_form_url($redirectKind, $requestId, $scheduleId));
        exit();
    }
    if ($action === 'create' || $action === 'update') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $selectedEventName = trim($_POST['event_title_choice'] ?? '');
        $customEventName = trim($_POST['event_title_other'] ?? '');
        if ($selectedEventName === 'other') {
            $title = $customEventName;
        } elseif ($selectedEventName !== '') {
            $title = $selectedEventName;
        } else {
            $title = '';
        }
        $description = trim($_POST['description'] ?? '');
        $selectedMinistries = $_POST['event_ministry'] ?? [];
        if (!is_array($selectedMinistries)) {
            $selectedMinistries = [$selectedMinistries];
        }
        $selectedMinistries = array_values(array_intersect(
            $ministryOptions,
            array_map('trim', $selectedMinistries)
        ));
        $eventMinistry = implode(', ', $selectedMinistries);
        $eventDate = trim($_POST['event_date'] ?? '');
        $eventTime = trim($_POST['event_time'] ?? '');
        $locationType = trim($_POST['event_location_type'] ?? 'inside');
$insideLocation = trim($_POST['event_location_inside'] ?? '');
$outsideLocation = trim($_POST['event_location_outside'] ?? '');
if ($locationType === 'outside') {
    $location = $outsideLocation;
} else {
    $location = $insideLocation;
}

        $eventKind = trim($_POST['event_kind'] ?? 'event');
        if ($eventKind !== 'mass') {
            $eventKind = 'event';
        }
        $redirectKind = $eventKind;
        $priestId = (int)($_POST['priest_id'] ?? 0);
        if ($requestId > 0 && $eventKind !== 'mass') {
            $priestId = null;
        }
        if ($eventKind === 'mass') {
            if ($isPriest) {
                $priestId = (int)$actor['id'];
            }
            if ($priestId <= 0) {
                set_flash('danger', 'Please select a priest for the Mass schedule.');
                header('Location: ' . schedule_admin_form_url($redirectKind, $requestId, $scheduleId));
                exit();
            }
        } else {
            $priestId = null;
        }

        if ($eventKind === 'mass' && !$isPriest) {
            $conflictStmt = $conn->prepare('SELECT id FROM event_schedules WHERE event_kind = "mass" AND priest_id = ? AND event_date = ? AND event_time = ? AND status IN ("pending_priest", "confirmed") AND id <> ? LIMIT 1');
            $conflictStmt->bind_param('issi', $priestId, $eventDate, $eventTime, $scheduleId);
            $conflictStmt->execute();
            $hasPriestConflict = $conflictStmt->get_result()->num_rows > 0;
            $conflictStmt->close();
            if ($hasPriestConflict) {
                set_flash('danger', 'Selected priest is not available at that date/time.');
                header('Location: ' . schedule_admin_form_url($redirectKind, $requestId, $scheduleId));
                exit();
            }
        }

        $status = ($eventKind === 'mass' && !$isPriest) ? 'pending_priest' : 'confirmed';
        $confirmedAt = ($status === 'confirmed') ? app_now()->format('Y-m-d H:i:s') : null;

        if ($title === '' || $eventDate === '' || $eventTime === '') {
            set_flash('danger', 'Title, date, and time are required.');
            header('Location: ' . schedule_admin_form_url($redirectKind, $requestId, $scheduleId));
            exit();
        }

        redirect_if_invalid_future_datetime_rules([
            ['date' => $eventDate, 'time' => $eventTime, 'allow_blank' => false],
        ], schedule_admin_form_url($redirectKind, $requestId, $scheduleId));

        $uid = (int)$actor['id'];
        if ($action === 'update' && $scheduleId > 0) {
            $stmt = $conn->prepare('UPDATE event_schedules SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?, event_ministry = ?, event_kind = ?, priest_id = ?, status = ?, priest_confirmed_at = ? WHERE id = ?');
            $stmt->bind_param('sssssssissi', $title, $description, $eventDate, $eventTime, $location, $eventMinistry, $eventKind, $priestId, $status, $confirmedAt, $scheduleId);
            $stmt->execute();
            $stmt->close();
        } else {
            $token = generate_qr_token(48);
            $stmt = $conn->prepare('INSERT INTO event_schedules (title, description, event_date, event_time, location, event_ministry, qr_token, created_by, event_kind, priest_id, status, priest_confirmed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssssisiss', $title, $description, $eventDate, $eventTime, $location, $eventMinistry, $token, $uid, $eventKind, $priestId, $status, $confirmedAt);
            $stmt->execute();
            $stmt->close();
        }

        if ($requestId > 0 && $action === 'create') {
            $reqStmt = $conn->prepare('SELECT user_id, form_type FROM service_requests WHERE id = ? LIMIT 1');
            $reqStmt->bind_param('i', $requestId);
            $reqStmt->execute();
            $reqRow = $reqStmt->get_result()->fetch_assoc();
            $reqStmt->close();

            if ($reqRow && ($reqRow['form_type'] ?? '') === 'Event Creation Request') {
                $updateReq = $conn->prepare('UPDATE service_requests SET status = "confirmed" WHERE id = ?');
                $updateReq->bind_param('i', $requestId);
                $updateReq->execute();
                $updateReq->close();

                $notifyMessage = 'Event request #' . $requestId . ' has been scheduled: ' . $title . ' on ' . $eventDate . ' ' . date('h:i A', strtotime($eventTime)) . '.';
                notify_user((int)$reqRow['user_id'], $notifyMessage, 'success', 'filled_forms.php', 'View Schedule');
            }
        }

        if ($status === 'pending_priest') {
            notify_user($priestId, 'Mass schedule pending your confirmation: ' . $title . ' on ' . $eventDate . ' ' . date('h:i A', strtotime($eventTime)) . '.', 'warning', 'priest_dashboard.php', 'Review Mass');
            set_flash('warning', $action === 'update' ? 'Mass schedule updated and pending priest confirmation.' : 'Mass schedule created and pending priest confirmation.');
        } else {
            set_flash('success', $action === 'update' ? 'Schedule updated. This event is connected to the Attendance module.' : 'Event schedule created and connected to the Attendance module.');
        }
        header('Location: ' . schedule_admin_url($redirectKind));
        exit();
    }
}

$filter = trim($_GET['filter'] ?? 'all');
$schedulesPerPage = 10;
$schedulePage = max(1, (int)($_GET['sched_page'] ?? 1));
$where = '1=1';
if ($filter === 'pending') {
    $where = 'e.status = \"pending_priest\" AND e.event_kind = \"mass\"';
}
$stmt = $conn->prepare('SELECT e.*, u.full_name, p.full_name AS priest_name, p.email AS priest_email,
    (SELECT COUNT(*) FROM attendance_logs a WHERE a.schedule_id = e.id) AS attendance_count
    FROM event_schedules e
    LEFT JOIN users u ON u.id = e.created_by
    LEFT JOIN users p ON p.id = e.priest_id
    WHERE ' . $where . '
    ORDER BY
        CASE WHEN e.status = "pending_priest" THEN 0 ELSE 1 END,
        e.event_date ASC,
        e.event_time ASC,
        e.id ASC');
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$scheduleTotal = count($events);
$scheduleTotalPages = max(1, (int)ceil($scheduleTotal / $schedulesPerPage));
$schedulePage = min($schedulePage, $scheduleTotalPages);
$scheduleOffset = ($schedulePage - 1) * $schedulesPerPage;
$visibleEvents = array_slice($events, $scheduleOffset, $schedulesPerPage);
$scheduleBaseParams = ['kind' => $selectedKind, 'filter' => $filter];
$schedulePrevParams = $scheduleBaseParams;
$schedulePrevParams['sched_page'] = max(1, $schedulePage - 1);
$scheduleNextParams = $scheduleBaseParams;
$scheduleNextParams['sched_page'] = min($scheduleTotalPages, $schedulePage + 1);
$schedulePrevUrl = 'event_schedule_admin.php?' . http_build_query($schedulePrevParams);
$scheduleNextUrl = 'event_schedule_admin.php?' . http_build_query($scheduleNextParams);
$scheduleModeLabel = $prefill['event_kind'] === 'mass' ? 'Mass' : 'Event';
$isEditMode = !empty($prefill['schedule_id']);
render_header('Create Schedule', $prefill['event_kind'] === 'mass' ? 'create_schedule_mass' : 'create_schedule_event');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0"><?php echo e($scheduleModeLabel); ?> Schedule</h2>
</div>

<div class="card bg-dark border-warning-subtle mb-4">
    <div class="card-body">
        <h5 class="text-warning"><?php echo $isEditMode ? 'Edit' : 'Create'; ?> <?php echo e($scheduleModeLabel); ?> Schedule</h5>
        <form method="POST" class="row g-3">
            <?php if (!empty($prefill['request_id'])): ?>
                <input type="hidden" name="request_id" value="<?php echo (int)$prefill['request_id']; ?>">
                <div class="alert alert-info w-100 mb-0">Loaded request #<?php echo (int)$prefill['request_id']; ?> from a minister. Review and create the schedule to confirm it.</div>
            <?php endif; ?>
            <?php if ($isEditMode): ?>
                <input type="hidden" name="schedule_id" value="<?php echo (int)$prefill['schedule_id']; ?>">
            <?php endif; ?>
            <input type="hidden" name="sched_page" value="<?php echo $schedulePage; ?>">
            <input type="hidden" name="event_kind" id="event_kind" value="<?php echo e($prefill['event_kind']); ?>">
            
            <div class="col-md-6">
                <label class="form-label"><?php echo e($titleFieldLabel); ?></label>
                <select class="form-select" name="event_title_choice" id="event_title_choice" required>
                    <option value=""><?php echo e($titlePlaceholder); ?></option>
                    <?php foreach ($titleOptions as $value => $label): ?>
                        <option value="<?php echo e($value); ?>" <?php echo ($prefill['event_title_choice'] === $value) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text text-secondary"><?php echo e($titleHelpText); ?></div>
            </div>
            <div class="col-md-6" id="event_title_other_group" style="display:none;">
                <label class="form-label">Other <?php echo e($titleFieldLabel); ?></label>
                <input class="form-control" type="text" name="event_title_other" id="event_title_other" placeholder="Describe the schedule" value="<?php echo e($prefill['event_title_other']); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Hosting Ministry</label>
                <div class="dropdown">
                    <button class="form-select text-start d-flex align-items-center justify-content-between" type="button" id="eventMinistryDropdownButton" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span id="eventMinistryDropdownLabel">Select ministry</span>
                    </button>
                    <div class="dropdown-menu w-100 p-2" aria-labelledby="eventMinistryDropdownButton" style="max-height: 22rem; overflow-y: auto;">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button class="btn btn-outline-warning btn-sm" type="button" id="recommend_ministries_btn">Recommend</button>
                            <button class="btn btn-outline-light btn-sm" type="button" id="select_all_ministries_btn">Select All</button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="clear_ministries_btn">Clear</button>
                        </div>
                        <div class="small text-secondary mb-2">Choose one or more ministries. Recommendation uses the selected event type.</div>
                        <?php foreach ($ministryOptions as $index => $ministry): ?>
                            <label class="dropdown-item-text d-flex gap-2 align-items-start py-1">
                                <input class="form-check-input mt-1 ministry-checkbox" type="checkbox" name="event_ministry[]" value="<?php echo e($ministry); ?>" id="admin_event_ministry_<?php echo $index; ?>" <?php echo in_array($ministry, $prefill['event_ministries'], true) ? 'checked' : ''; ?>>
                                <span><?php echo e($ministry); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4" id="priest_group">
                <label class="form-label">Priest <span id="avail-badge" class="badge text-bg-secondary ms-1" style="display:none;"></span></label>
                <select class="form-select" name="priest_id" id="mass_priest">
                    <option value="">Select priest</option>
                    <?php foreach ($priests as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo ((string)(int)$p['id'] === (string)$prefill['priest_id']) ? 'selected' : ''; ?>><?php echo e($p['full_name'] ?: $p['email']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text text-secondary">Required for Mass schedules.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input class="form-control" type="date" name="event_date" id="event_date" data-datetime-future="true" data-datetime-pair="admin-event-schedule" data-datetime-role="date" value="<?php echo e($prefill['event_date']); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Time</label>
                <select class="form-select" name="event_time" id="event_time" data-datetime-future="true" data-datetime-pair="admin-event-schedule" data-datetime-role="time" required>
                    <option value="">Select time</option>
                    <?php foreach ($timeOptions as $value => $label): ?>
                        <option value="<?php echo e($value); ?>" <?php echo ($prefill['event_time'] === $value) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Location Type</label>
                <div class="d-flex flex-wrap gap-3">
                    <label class="form-check">
                        <input class="form-check-input" type="radio" name="event_location_type" value="inside" <?php echo $prefill['location_type'] === 'inside' ? 'checked' : ''; ?>>
                        <span class="form-check-label">Inside church facility</span>
                    </label>
                    <label class="form-check">
                        <input class="form-check-input" type="radio" name="event_location_type" value="outside" <?php echo $prefill['location_type'] === 'outside' ? 'checked' : ''; ?>>
                        <span class="form-check-label">Outside church</span>
                    </label>
                </div>
            </div>
            <div class="col-md-6" id="location_inside_group">
                <label class="form-label">Facility</label>
                <select class="form-select" name="event_location_inside" id="event_location_inside" required>
                    <option value="">Select facility</option>
                    <option value="Minor Basilica Main Hall" <?php echo ($prefill['location_inside'] === 'Minor Basilica Main Hall') ? 'selected' : ''; ?>>Minor Basilica Main Hall</option>
                    <option value="St. Francis of Assisi Hall (2nd Floor)" <?php echo ($prefill['location_inside'] === 'St. Francis of Assisi Hall (2nd Floor)') ? 'selected' : ''; ?>>St. Francis of Assisi Hall (2nd Floor)</option>
                    <option value="St. Peter of Alcantara (Peach Room)" <?php echo ($prefill['location_inside'] === 'St. Peter of Alcantara (Peach Room)') ? 'selected' : ''; ?>>St. Peter of Alcantara (Peach Room)</option>
                    <option value="St. Margaret of Cortona (Green Room)" <?php echo ($prefill['location_inside'] === 'St. Margaret of Cortona (Green Room)') ? 'selected' : ''; ?>>St. Margaret of Cortona (Green Room)</option>
                    <option value="St. Louis IX (Blue Room)" <?php echo ($prefill['location_inside'] === 'St. Louis IX (Blue Room)') ? 'selected' : ''; ?>>St. Louis IX (Blue Room)</option>
                    <option value="Holy Cave" <?php echo ($prefill['location_inside'] === 'Holy Cave') ? 'selected' : ''; ?>>Holy Cave</option>
                    <option value="Portiuncula Formation and Renewal Hall" <?php echo ($prefill['location_inside'] === 'Portiuncula Formation and Renewal Hall') ? 'selected' : ''; ?>>Portiuncula Formation and Renewal Hall</option>
                    <option value="Brother Sun Sister Moon Garden" <?php echo ($prefill['location_inside'] === 'Brother Sun Sister Moon Garden') ? 'selected' : ''; ?>>Brother Sun Sister Moon Garden</option>
                    <option value="San Damiano Garden" <?php echo ($prefill['location_inside'] === 'San Damiano Garden') ? 'selected' : ''; ?>>San Damiano Garden</option>
                    <option value="Chamber Room" <?php echo ($prefill['location_inside'] === 'Chamber Room') ? 'selected' : ''; ?>>Chamber Room</option>
                </select>
            </div>
            <div class="col-md-6" id="location_outside_group" style="display:none;">
                <label class="form-label">Outside Location</label>
                <input class="form-control" type="text" name="event_location_outside" id="event_location_outside" placeholder="Enter outside location" value="<?php echo e($prefill['location_outside']); ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"><?php echo e($prefill['description']); ?></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-outline-light" type="submit" name="action" value="check_availability">Check Priest Availability</button>
                <button class="btn btn-warning" type="submit" name="action" value="<?php echo $isEditMode ? 'update' : 'create'; ?>"><?php echo $isEditMode ? 'Update Schedule' : 'Create Schedule'; ?></button>
            </div>
        </form>
        <script>
            (function () {
                var kind = document.getElementById('event_kind');
                var priest = document.getElementById('mass_priest');
                var date = document.getElementById('event_date');
                var time = document.getElementById('event_time');
                var badge = document.getElementById('avail-badge');
                var priestGroup = document.getElementById('priest_group');
                var eventChoice = document.getElementById('event_title_choice');
                var eventOtherGroup = document.getElementById('event_title_other_group');
                var eventOther = document.getElementById('event_title_other');
                var ministryCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.ministry-checkbox'));
                var ministryLabel = document.getElementById('eventMinistryDropdownLabel');
                var recommendBtn = document.getElementById('recommend_ministries_btn');
                var selectAllBtn = document.getElementById('select_all_ministries_btn');
                var clearBtn = document.getElementById('clear_ministries_btn');
                var recommendationMap = <?php echo json_encode($eventMinistryRecommendations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                if (!kind || !priest || !date || !time || !badge || !priestGroup || !eventChoice || !eventOtherGroup || !eventOther) return;
                
                var checkTimeout;
                var isMassMode = kind.value === 'mass';
                
                function toggleOtherEventName() {
                    var showCustom = eventChoice.value === 'other';
                    eventOtherGroup.style.display = showCustom ? 'block' : 'none';
                    eventOther.required = showCustom;
                    if (!showCustom) {
                        eventOther.value = '';
                    }
                }

                function togglePriest() {
                    priestGroup.style.display = isMassMode ? 'block' : 'none';
                    priest.disabled = !isMassMode;
                    priest.required = isMassMode;
                    if (!isMassMode) { 
                        priest.value = ''; 
                        badge.style.display = 'none';
                    } else {
                        checkAvailability();
                    }
                }

                function selectedMinistryValues() {
                    return ministryCheckboxes.filter(function (checkbox) {
                        return checkbox.checked;
                    }).map(function (checkbox) {
                        return checkbox.value;
                    });
                }

                function updateMinistryLabel() {
                    if (!ministryLabel) {
                        return;
                    }
                    var selected = selectedMinistryValues();
                    if (!selected.length) {
                        ministryLabel.textContent = 'Select ministry';
                    } else if (selected.length === 1) {
                        ministryLabel.textContent = selected[0];
                    } else {
                        ministryLabel.textContent = selected.length + ' ministries selected';
                    }
                }

                function setMinistrySelection(values) {
                    ministryCheckboxes.forEach(function (checkbox) {
                        checkbox.checked = values.indexOf(checkbox.value) !== -1;
                    });
                    updateMinistryLabel();
                }

                function applyRecommendedMinistries() {
                    var recommended = recommendationMap[eventChoice.value] || [];
                    if (!recommended.length && eventChoice.value && eventChoice.value !== 'other') {
                        recommended = ['Public Affairs Ministry'];
                    }
                    setMinistrySelection(recommended);
                }
                
                function checkAvailability() {
                    var pId = priest.value;
                    var d = date.value;
                    var t = time.value;
                    if (pId && d && t && isMassMode) {
                        clearTimeout(checkTimeout);
                        checkTimeout = setTimeout(function() {
                            fetch(window.location.href, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'action=ajax_avail&priest_id=' + encodeURIComponent(pId) + '&event_date=' + encodeURIComponent(d) + '&event_time=' + encodeURIComponent(t)
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.available) {
                                    badge.textContent = 'Available';
                                    badge.className = 'badge text-bg-success ms-1';
                                } else {
                                    badge.textContent = 'Unavailable';
                                    badge.className = 'badge text-bg-danger ms-1';
                                }
                                badge.style.display = 'inline';
                            })
                            .catch(() => {
                                badge.textContent = 'Error';
                                badge.className = 'badge text-bg-secondary ms-1';
                                badge.style.display = 'inline';
                            });
                        }, 500);
                    } else {
                        badge.style.display = 'none';
                    }
                }
                
                priest.addEventListener('change', checkAvailability);
                date.addEventListener('change', checkAvailability);
                time.addEventListener('change', checkAvailability);
                eventChoice.addEventListener('change', toggleOtherEventName);
                eventChoice.addEventListener('change', updateMinistryLabel);
                ministryCheckboxes.forEach(function (checkbox) {
                    checkbox.addEventListener('change', updateMinistryLabel);
                });
                if (recommendBtn) {
                    recommendBtn.addEventListener('click', applyRecommendedMinistries);
                }
                if (selectAllBtn) {
                    selectAllBtn.addEventListener('click', function () {
                        setMinistrySelection(ministryCheckboxes.map(function (checkbox) {
                            return checkbox.value;
                        }));
                    });
                }
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        setMinistrySelection([]);
                    });
                }
                toggleOtherEventName();
                togglePriest();
                updateMinistryLabel();
                function toggleLocationType() {
                    var type = document.querySelector('input[name="event_location_type"]:checked');
                    var isOutside = type && type.value === 'outside';
                    var insideGroup = document.getElementById('location_inside_group');
                    var outsideGroup = document.getElementById('location_outside_group');
                    var insideField = document.getElementById('event_location_inside');
                    var outsideField = document.getElementById('event_location_outside');
                    if (!insideGroup || !outsideGroup || !insideField || !outsideField) return;
                    insideGroup.style.display = isOutside ? 'none' : 'block';
                    outsideGroup.style.display = isOutside ? 'block' : 'none';
                    insideField.required = !isOutside;
                    outsideField.required = isOutside;
                    if (!isOutside) {
                        outsideField.value = '';
                    } else {
                        insideField.value = '';
                    }
                }

                var locationRadios = document.querySelectorAll('input[name="event_location_type"]');
                locationRadios.forEach(function(radio) {
                    radio.addEventListener('change', toggleLocationType);
                });
                toggleLocationType();
            })();
        </script>
    </div>
</div>

<div class="card bg-dark border-warning-subtle">
    <div class="card-body">
        <h5 class="text-warning mb-3">Created Schedules<?php if ($filter === 'pending'): ?> (Pending Priest Confirmations)<?php endif; ?>
            <a href="?kind=<?php echo e($selectedKind); ?>&filter=all" class="badge text-bg-light ms-2<?php echo ($filter !== 'pending') ? ' text-bg-primary' : ''; ?>">All</a>
            <a href="?kind=<?php echo e($selectedKind); ?>&filter=pending" class="badge text-bg-light ms-1<?php echo ($filter === 'pending') ? ' text-bg-warning' : ''; ?>">Pending</a>
            <?php if ($scheduleTotal > 0): ?>
                <small class="text-secondary ms-2">Page <?php echo $schedulePage; ?> of <?php echo $scheduleTotalPages; ?></small>
            <?php endif; ?>
        </h5>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Ministry</th>
                        <th>Priest</th>
                        <th>Status</th>
                        <th>Date/Time</th>
                        <th>Location</th>
                        <th>Attendance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$events): ?>
                        <tr><td colspan="10" class="text-center">No schedules yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($visibleEvents as $index => $e): ?>
                            <tr>
                                <td><?php echo $scheduleOffset + $index + 1; ?></td>
                                <td>
                                    <strong><?php echo e($e['title']); ?></strong><br>
                                    <small class="text-secondary"><?php echo e($e['description'] ?: '-'); ?></small>
                                </td>
                                <td><?php echo e(((($e['event_kind'] ?? 'event') === 'mass') ? 'Mass' : 'Event')); ?></td>
                                <td><?php echo e($e['event_ministry'] ?: '-'); ?></td>
                                <td><?php echo e(((($e['event_kind'] ?? '') === 'mass') ? ($e['priest_name'] ?: $e['priest_email'] ?: '-') : '-')); ?></td>
                                <td>
                                    <?php
                                    $status = $e['status'] ?? 'confirmed';
                                    $statusLabel = match ($status) {
                                        'pending_priest' => 'Pending Priest',
                                        'confirmed' => 'Confirmed',
                                        default => ucfirst($status)
                                    };
                                    $statusBadge = $status === 'confirmed' ? 'success' : ($status === 'pending_priest' ? 'warning' : 'secondary');
                                    ?>
                                    <span class="badge text-bg-<?php echo $statusBadge; ?>"><?php echo e($statusLabel); ?></span>
                                </td>
                                <td><?php echo e($e['event_date']); ?> <?php echo e(date('h:i A', strtotime($e['event_time']))); ?></td>
                                <td><?php echo e($e['location'] ?: '-'); ?></td>
                                <td><span class="badge text-bg-info"><?php echo (int)$e['attendance_count']; ?></span></td>
                                <td>
                                    <a class="btn btn-sm btn-warning" href="<?php echo e(schedule_admin_edit_url((string)($e['event_kind'] ?? 'event'), (int)$e['id'], $filter)); ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($scheduleTotalPages > 1): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <a class="btn btn-outline-light btn-sm<?php echo $schedulePage <= 1 ? ' disabled' : ''; ?>" href="<?php echo e($schedulePrevUrl); ?>"<?php echo $schedulePage <= 1 ? ' aria-disabled="true"' : ''; ?>>Previous</a>
                <small class="text-secondary">Showing <?php echo $scheduleOffset + 1; ?>-<?php echo $scheduleOffset + count($visibleEvents); ?> of <?php echo $scheduleTotal; ?></small>
                <a class="btn btn-outline-light btn-sm<?php echo $schedulePage >= $scheduleTotalPages ? ' disabled' : ''; ?>" href="<?php echo e($scheduleNextUrl); ?>"<?php echo $schedulePage >= $scheduleTotalPages ? ' aria-disabled="true"' : ''; ?>>Next</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
