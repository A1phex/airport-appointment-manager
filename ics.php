<?php
// Downloadable .ics file for an approved appointment: ics.php?slot=ID
// Accessible to the manager and to the employee the appointment belongs to.

require_once __DIR__ . '/includes/session.php';
include(__DIR__ . '/connection.php');
include(__DIR__ . '/includes/ical.php');

if (!isset($_SESSION['role'])) {
    http_response_code(403);
    exit('Forbidden');
}

$slotId = (int)($_GET['slot'] ?? 0);

$stmt = $database->prepare(
    "SELECT slot.*, employee.name AS employee_name, manager.name AS manager_name
     FROM slot
     LEFT JOIN employee ON slot.requested_by = employee.id
     JOIN manager ON slot.created_by = manager.id
     WHERE slot.id = ? AND slot.status = 'approved'"
);
$stmt->bind_param("i", $slotId);
$stmt->execute();
$slot = $stmt->get_result()->fetch_assoc();

$allowed = $slot && (
    $_SESSION['role'] === 'manager'
    || $_SESSION['role'] === 'admin'
    || ($_SESSION['role'] === 'employee' && (int)$slot['requested_by'] === (int)$_SESSION['user_id'])
);

if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$utc = new DateTimeZone('UTC');
$start = slot_start_dt($slot);
$end = $start->modify('+' . (int)$slot['duration_minutes'] . ' minutes');

$approvedTs = (new DateTimeImmutable($slot['approved_at'], new DateTimeZone('Europe/Berlin')))->getTimestamp();
$description = ($slot['topic'] ?? '') . "\nWith: " . $slot['manager_name'] . " / " . ($slot['employee_name'] ?? '');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//DHS Scheduler//EN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:slot-' . (int)$slot['id'] . '-' . $approvedTs . '@dhs-scheduler',
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z'),
    'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z'),
    ics_fold('SUMMARY:' . ics_escape($slot['title'])),
    ics_fold('DESCRIPTION:' . ics_escape($description)),
    'END:VEVENT',
    'END:VCALENDAR',
];

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="appointment-' . (int)$slot['id'] . '.ics"');
echo implode("\r\n", $lines) . "\r\n";
