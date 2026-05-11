<?php
require_once __DIR__ . '/layout.php';
require_login();

$now = app_now();
$monthParam = trim((string)($_GET['month'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = $now->format('Y-m');
}

$selectedDateParam = trim((string)($_GET['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateParam)) {
    $selectedDateParam = $now->format('Y-m-d');
}

$calendarStart = DateTimeImmutable::createFromFormat('Y-m-d', $monthParam . '-01');
if (!$calendarStart) {
    $calendarStart = new DateTimeImmutable($now->format('Y-m-01'));
}

$selectedDate = DateTimeImmutable::createFromFormat('Y-m-d', $selectedDateParam);
if (!$selectedDate) {
    $selectedDate = new DateTimeImmutable($now->format('Y-m-d'));
}
$selectedDateParam = $selectedDate->format('Y-m-d');

$calendarPrev = $calendarStart->modify('-1 month')->format('Y-m');
$calendarNext = $calendarStart->modify('+1 month')->format('Y-m');
$monthStartDate = $calendarStart->format('Y-m-01');
$monthEndDate = $calendarStart->format('Y-m-t');
$daysInMonth = (int)$calendarStart->format('t');
$startWeekday = (int)$calendarStart->format('w');

$calendarEvents = [];
$eventsByDay = [];

$eventStmt = $conn->prepare('SELECT title AS item_title, event_date AS item_date, event_time AS item_time, CASE WHEN event_kind = "mass" THEN "Mass" ELSE "Admin Event" END AS item_type, "admin" AS source
    FROM event_schedules
    WHERE event_date BETWEEN ? AND ?');
$eventStmt->bind_param('ss', $monthStartDate, $monthEndDate);
$eventStmt->execute();
$eventRows = $eventStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$eventStmt->close();

$scheduleStmt = $conn->prepare('SELECT s.event_title AS item_title, s.event_date AS item_date, s.event_time AS item_time, r.form_type AS item_type, "reservation" AS source
    FROM schedules s
    JOIN service_requests r ON r.id = s.request_id
    WHERE s.event_date BETWEEN ? AND ?');
$scheduleStmt->bind_param('ss', $monthStartDate, $monthEndDate);
$scheduleStmt->execute();
$scheduleRows = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scheduleStmt->close();

$calendarEvents = array_merge($eventRows, $scheduleRows);
usort($calendarEvents, static function (array $a, array $b): int {
    return strcmp(($a['item_date'] . ' ' . $a['item_time']), ($b['item_date'] . ' ' . $b['item_time']));
});

foreach ($calendarEvents as $item) {
    $day = (int)date('j', strtotime((string)$item['item_date']));
    $eventsByDay[$day] ??= [];
    $eventsByDay[$day][] = $item;
}

$calendarCells = array_fill(0, $startWeekday, null);
for ($day = 1; $day <= $daysInMonth; $day++) {
    $calendarCells[] = $day;
}
while (count($calendarCells) % 7 !== 0) {
    $calendarCells[] = null;
}

render_header('Events and Schedules', 'events');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h2 class="mb-1">Schedules Calendar</h2>
        <p class="text-secondary mb-0"><?php echo e($calendarStart->format('F Y')); ?> - all upcoming events and approved schedules.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-light px-3" href="events.php?month=<?php echo e($calendarPrev); ?>&date=<?php echo e($selectedDateParam); ?>">&laquo; Previous</a>
        <div class="px-3 py-2 rounded border border-warning-subtle bg-dark-subtle text-warning fw-semibold">
            <?php echo e($calendarStart->format('F Y')); ?>
        </div>
        <a class="btn btn-outline-light px-3" href="events.php?month=<?php echo e($calendarNext); ?>&date=<?php echo e($selectedDateParam); ?>">Next &raquo;</a>
    </div>
</div>

<div class="card bg-dark border-warning-subtle mb-4">
    <div class="card-body p-2 p-md-3">
        <div class="table-responsive">
            <table class="table table-dark table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Sun</th>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_chunk($calendarCells, 7) as $week): ?>
                        <tr>
                            <?php foreach ($week as $day): ?>
                                <td style="vertical-align: top; min-width: 140px; height: 130px;">
                                    <?php if ($day === null): ?>
                                        &nbsp;
                                    <?php else: ?>
                                        <?php $cellDate = $calendarStart->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT); ?>
                                        <?php $isSelectedDate = $cellDate === $selectedDateParam; ?>
                                        <?php $dayItems = $eventsByDay[$day] ?? []; ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong>
                                                <button
                                                    class="btn btn-link p-0 border-0 fw-bold <?php echo $isSelectedDate ? 'text-warning text-decoration-underline' : 'text-light text-decoration-none'; ?>"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#dayScheduleModal"
                                                    data-date="<?php echo e($cellDate); ?>"
                                                    data-label="<?php echo e(date('F j, Y', strtotime($cellDate))); ?>"
                                                    data-items="<?php echo e(json_encode($dayItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)); ?>"
                                                >
                                                    <?php echo $day; ?>
                                                </button>
                                            </strong>
                                            <?php $count = count($dayItems); ?>
                                            <?php if ($count > 0): ?>
                                                <span class="badge text-bg-warning"><?php echo $count; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($dayItems)): ?>
                                            <?php foreach (array_slice($dayItems, 0, 2) as $entry): ?>
                                                <div class="small mb-1">
                                                    <div class="text-info"><?php echo e(date('h:i A', strtotime($entry['item_time']))); ?></div>
                                                    <div><?php echo e($entry['item_title']); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                            <button
                                                class="btn btn-sm btn-outline-light mt-1"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#dayScheduleModal"
                                                data-date="<?php echo e($cellDate); ?>"
                                                data-label="<?php echo e(date('F j, Y', strtotime($cellDate))); ?>"
                                                data-items="<?php echo e(json_encode($dayItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)); ?>"
                                            >
                                                View schedules
                                            </button>
                                            <?php if (count($dayItems) > 2): ?>
                                                <small class="text-secondary d-block mt-1">+<?php echo count($dayItems) - 2; ?> more</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button
                                                class="btn btn-sm btn-outline-secondary mt-1"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#dayScheduleModal"
                                                data-date="<?php echo e($cellDate); ?>"
                                                data-label="<?php echo e(date('F j, Y', strtotime($cellDate))); ?>"
                                                data-items="[]"
                                            >
                                                View schedules
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php render_footer(); ?>
