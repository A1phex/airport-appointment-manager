<?php
require_once __DIR__ . '/../includes/session.php';
include("../connection.php");
include("../includes/calendar.php");
include("../includes/ical.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("location: ../login.php");
    exit;
}

// Snapshot a rejected/cancelled request so the employee still sees what happened.
function insert_request_notice(mysqli $database, array $slot, string $kind): void
{
    $stmt = $database->prepare(
        "INSERT INTO request_notice (employee_id, kind, title, slot_date, slot_time, topic, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("isssss", $slot['requested_by'], $kind, $slot['title'], $slot['slot_date'], $slot['slot_time'], $slot['topic']);
    $stmt->execute();
}

// Slots belong to a manager's calendar; the admin picks whose when creating one.
$managers = [];
$res = $database->query("SELECT id, name FROM manager ORDER BY name");
while ($m = $res->fetch_assoc()) {
    $managers[(int)$m['id']] = $m['name'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $action = $_POST['action'] ?? '';

    $duration = (int)($_POST['duration_minutes'] ?? 30);
    if ($duration < 5 || $duration > 480) {
        $duration = 30;
    }

    if ($action == 'create_slot') {
        $managerId = (int)($_POST['manager_id'] ?? 0);
        if (!isset($managers[$managerId])) {
            $managerId = (int)array_key_first($managers);
        }
        $stmt = $database->prepare("INSERT INTO slot (title, slot_date, slot_time, duration_minutes, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $_POST['title'], $_POST['slot_date'], $_POST['slot_time'], $duration, $managerId);
        $stmt->execute();
        $_SESSION['flash'] = "Slot created.";

    } elseif ($action == 'update_slot') {
        // Only open slots may be edited; changing the time under a pending or
        // approved request would invalidate what the employee agreed to.
        $stmt = $database->prepare("UPDATE slot SET title = ?, slot_date = ?, slot_time = ?, duration_minutes = ? WHERE id = ? AND status = 'open'");
        $stmt->bind_param("sssii", $_POST['title'], $_POST['slot_date'], $_POST['slot_time'], $duration, $_POST['id']);
        $stmt->execute();
        $_SESSION['flash'] = $stmt->affected_rows > 0
            ? "Slot updated."
            : "That slot was requested in the meantime and can no longer be edited.";

    } elseif ($action == 'delete_slot') {
        $database->begin_transaction();
        $stmt = $database->prepare("SELECT * FROM slot WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();
        $slot = $stmt->get_result()->fetch_assoc();
        if ($slot) {
            $stmt = $database->prepare("DELETE FROM slot WHERE id = ?");
            $stmt->bind_param("i", $_POST['id']);
            $stmt->execute();
            if ($slot['requested_by'] && $slot['status'] != 'open') {
                insert_request_notice($database, $slot, 'cancelled');
            }
        }
        $database->commit();
        $_SESSION['flash'] = "Slot deleted.";

    } elseif ($action == 'approve_request') {
        $stmt = $database->prepare("UPDATE slot SET status = 'approved', approved_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();
        $_SESSION['flash'] = $stmt->affected_rows > 0
            ? "Appointment approved."
            : "That request was withdrawn in the meantime.";

    } elseif ($action == 'reject_request') {
        $stmt = $database->prepare("SELECT * FROM slot WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();
        $slot = $stmt->get_result()->fetch_assoc();

        if ($slot && $slot['requested_by']) {
            // Guard on the requester too: only write the notice if THIS request
            // was still live when we cleared it (not withdrawn and re-requested).
            $stmt = $database->prepare(
                "UPDATE slot SET status = 'open', requested_by = NULL, topic = NULL, requested_at = NULL
                 WHERE id = ? AND status = 'pending' AND requested_by = ?"
            );
            $stmt->bind_param("ii", $_POST['id'], $slot['requested_by']);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                insert_request_notice($database, $slot, 'rejected');
                $_SESSION['flash'] = "Request declined. The slot is open again.";
            } else {
                $_SESSION['flash'] = "That request is no longer pending.";
            }
        } else {
            $_SESSION['flash'] = "That request is no longer pending.";
        }

    } elseif ($action == 'release_slot') {
        $stmt = $database->prepare("SELECT * FROM slot WHERE id = ? AND status = 'approved'");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();
        $slot = $stmt->get_result()->fetch_assoc();

        if ($slot && $slot['requested_by']) {
            $stmt = $database->prepare(
                "UPDATE slot SET status = 'open', requested_by = NULL, topic = NULL, requested_at = NULL, approved_at = NULL
                 WHERE id = ? AND status = 'approved' AND requested_by = ?"
            );
            $stmt->bind_param("ii", $_POST['id'], $slot['requested_by']);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                insert_request_notice($database, $slot, 'cancelled');
                $_SESSION['flash'] = "Appointment cancelled. The slot is open again.";
            } else {
                $_SESSION['flash'] = "That appointment already changed.";
            }
        } else {
            $_SESSION['flash'] = "That appointment already changed.";
        }
    }

    // Re-validate week/view before echoing them into the redirect URL.
    $backWeek = cal_week_start($_POST['week'] ?? null);
    $backView = cal_view($_POST['view'] ?? null);
    header("location: index.php?" . cal_qs($backWeek, $backView));
    exit;
}

$view = cal_view($_GET['view'] ?? null);
$weekStart = cal_week_start($_GET['week'] ?? null);
$weekEnd = $weekStart->modify('+6 days');
$today = new DateTimeImmutable('today');
$qs = cal_qs($weekStart, $view);

$editSlot = null;
if (isset($_GET['edit'])) {
    $stmt = $database->prepare("SELECT * FROM slot WHERE id = ? AND status = 'open'");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editSlot = $stmt->get_result()->fetch_assoc();
}

// Optional ?date=YYYY-MM-DD prefill for the create form (from a day's "+" link).
$prefillDate = '';
if (!$editSlot && isset($_GET['date'])) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['date']);
    if ($parsed && $parsed->format('Y-m-d') === $_GET['date']) {
        $prefillDate = $_GET['date'];
    }
}

if ($view == 'week') {
    $stmt = $database->prepare(
        "SELECT slot.*, employee.name AS requested_by_name, manager.name AS manager_name
         FROM slot
         LEFT JOIN employee ON slot.requested_by = employee.id
         JOIN manager ON slot.created_by = manager.id
         WHERE slot_date BETWEEN ? AND ?
         ORDER BY slot_date, slot_time"
    );
    $from = $weekStart->format('Y-m-d');
    $to = $weekEnd->format('Y-m-d');
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $slotsByDay = cal_group_by_day($stmt->get_result());
} else {
    $slots = $database->query(
        "SELECT slot.*, employee.name AS requested_by_name, manager.name AS manager_name
         FROM slot
         LEFT JOIN employee ON slot.requested_by = employee.id
         JOIN manager ON slot.created_by = manager.id
         ORDER BY slot_date, slot_time"
    );
}

$pageTitle = "Appointments";
$activeNav = "appointments";
include("../includes/header.php");

// Shared markup for the per-slot action forms.
function slot_action_form(string $action, int $id, DateTimeImmutable $weekStart, string $view, string $label, string $btnClass, string $confirm = ''): void
{
    $onclick = $confirm !== '' ? ' onclick="return confirm(\'' . htmlspecialchars($confirm, ENT_QUOTES) . '\');"' : '';
    echo '<form action="index.php" method="POST">'
        . '<input type="hidden" name="action" value="' . $action . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="week" value="' . $weekStart->format('Y-m-d') . '">'
        . '<input type="hidden" name="view" value="' . $view . '">'
        . '<input type="submit" value="' . $label . '" class="btn ' . $btnClass . ' btn-sm"' . $onclick . '>'
        . '</form>';
}

function slot_delete_confirm(array $row): string
{
    if ($row['status'] == 'pending') {
        return 'This slot has a pending request. Deleting will decline it. Continue?';
    }
    if ($row['status'] == 'approved') {
        return 'This slot has an approved appointment. Deleting will cancel it. Continue?';
    }
    return 'Delete this slot?';
}
?>

<div class="card">
    <p class="card-title"><?php echo $editSlot ? "Edit Slot" : "Add an Available Time"; ?></p>
    <form action="index.php" method="POST">
        <input type="hidden" name="action" value="<?php echo $editSlot ? "update_slot" : "create_slot"; ?>">
        <input type="hidden" name="week" value="<?php echo $weekStart->format('Y-m-d'); ?>">
        <input type="hidden" name="view" value="<?php echo $view; ?>">
        <?php if ($editSlot): ?>
            <input type="hidden" name="id" value="<?php echo (int)$editSlot['id']; ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div>
                <label class="form-label">Title</label>
                <input type="text" name="title" class="input-text" placeholder="e.g. 1:1 Meeting Window" required
                    value="<?php echo $editSlot ? htmlspecialchars($editSlot['title']) : ''; ?>">
            </div>
            <div>
                <label class="form-label">Date</label>
                <input type="date" name="slot_date" class="input-text" required
                    value="<?php echo $editSlot ? htmlspecialchars($editSlot['slot_date']) : $prefillDate; ?>">
            </div>
            <div>
                <label class="form-label">Time</label>
                <input type="time" name="slot_time" class="input-text" required
                    value="<?php echo $editSlot ? htmlspecialchars($editSlot['slot_time']) : ''; ?>">
            </div>
            <div>
                <label class="form-label">Duration (min)</label>
                <input type="number" name="duration_minutes" class="input-text" min="5" max="480" step="5"
                    value="<?php echo $editSlot ? (int)$editSlot['duration_minutes'] : 30; ?>">
            </div>
            <?php if (!$editSlot): ?>
            <div>
                <label class="form-label">For Manager</label>
                <select name="manager_id" class="input-text">
                    <?php foreach ($managers as $mid => $mname): ?>
                        <option value="<?php echo $mid; ?>"><?php echo htmlspecialchars($mname ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="table-actions">
                <input type="submit" value="<?php echo $editSlot ? "Save" : "Add Slot"; ?>" class="btn btn-primary">
                <?php if ($editSlot): ?>
                    <a href="index.php?<?php echo $qs; ?>" class="btn btn-primary-gray">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="week-nav">
    <div class="week-nav-links">
        <a href="index.php?<?php echo cal_qs($weekStart->modify('-7 days'), $view); ?>" class="btn btn-primary-gray btn-sm">&lsaquo; Prev</a>
        <a href="index.php?<?php echo cal_qs(cal_week_start(null), $view); ?>" class="btn btn-primary-gray btn-sm">Today</a>
        <a href="index.php?<?php echo cal_qs($weekStart->modify('+7 days'), $view); ?>" class="btn btn-primary-gray btn-sm">Next &rsaquo;</a>
    </div>
    <p class="week-nav-label">Week of <?php echo $weekStart->format('j M Y'); ?></p>
    <div class="week-nav-links">
        <a href="index.php?<?php echo cal_qs($weekStart, 'week'); ?>" class="btn btn-sm <?php echo $view == 'week' ? 'btn-primary' : 'btn-primary-gray'; ?>">Week</a>
        <a href="index.php?<?php echo cal_qs($weekStart, 'list'); ?>" class="btn btn-sm <?php echo $view == 'list' ? 'btn-primary' : 'btn-primary-gray'; ?>">List</a>
    </div>
</div>

<?php if ($view == 'week'): ?>

<div class="week-grid">
    <?php foreach (cal_week_days($weekStart) as $day):
        $key = $day->format('Y-m-d');
        $daySlots = $slotsByDay[$key] ?? [];
        $isToday = $key == $today->format('Y-m-d');
    ?>
    <div class="day-col<?php echo $isToday ? ' day-today' : ''; ?><?php echo empty($daySlots) ? ' day-empty' : ''; ?>">
        <div class="day-head">
            <span class="day-name"><?php echo $day->format('D'); ?></span>
            <span class="day-date"><?php echo $day->format('j M'); ?></span>
            <a class="day-add" title="Add an available time on this day"
               href="index.php?<?php echo cal_qs($weekStart, $view, ['date' => $key]); ?>">+</a>
        </div>
        <?php if (empty($daySlots)): ?>
            <p class="day-none">&mdash;</p>
        <?php endif; ?>
        <?php foreach ($daySlots as $row):
            $cardClass = $row['status'] == 'pending' ? 'slot-pending' : ($row['status'] == 'approved' ? 'slot-booked' : 'slot-open');
        ?>
        <div class="slot-card <?php echo $cardClass; ?>">
            <p class="slot-time"><?php echo htmlspecialchars(substr($row['slot_time'], 0, 5)); ?> &middot; <?php echo (int)$row['duration_minutes']; ?> min</p>
            <p class="slot-title"><?php echo htmlspecialchars($row['title']); ?></p>
            <?php if ($row['status'] == 'pending'): ?>
                <span class="badge badge-pending">Request &middot; <?php echo htmlspecialchars($row['requested_by_name'] ?? ''); ?></span>
                <p class="slot-topic"><?php echo htmlspecialchars($row['topic'] ?? ''); ?></p>
                <div class="slot-actions">
                    <?php slot_action_form('approve_request', (int)$row['id'], $weekStart, $view, 'Approve', 'btn-primary'); ?>
                    <?php slot_action_form('reject_request', (int)$row['id'], $weekStart, $view, 'Reject', 'btn-danger-soft', 'Decline this request? The slot becomes open again.'); ?>
                </div>
            <?php elseif ($row['status'] == 'approved'): ?>
                <span class="badge badge-booked">Approved &middot; <?php echo htmlspecialchars($row['requested_by_name'] ?? ''); ?></span>
                <p class="slot-topic"><?php echo htmlspecialchars($row['topic'] ?? ''); ?></p>
                <div class="slot-actions">
                    <a href="<?php echo htmlspecialchars(gcal_url($row['title'], slot_start_dt($row), (int)$row['duration_minutes'], $row['topic'] ?? '')); ?>"
                       target="_blank" rel="noopener" class="btn btn-primary-soft btn-sm">Google</a>
                    <a href="../ics.php?slot=<?php echo (int)$row['id']; ?>" class="btn btn-primary-soft btn-sm">.ics</a>
                    <?php slot_action_form('release_slot', (int)$row['id'], $weekStart, $view, 'Cancel', 'btn-danger-soft', 'Cancel this appointment? The employee will be notified on their dashboard.'); ?>
                </div>
            <?php else: ?>
                <span class="badge badge-open">Open</span>
                <div class="slot-actions">
                    <a href="index.php?<?php echo cal_qs($weekStart, $view, ['edit' => (int)$row['id']]); ?>" class="btn btn-primary-soft btn-sm">Edit</a>
                    <?php slot_action_form('delete_slot', (int)$row['id'], $weekStart, $view, 'Delete', 'btn-danger-soft', slot_delete_confirm($row)); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Manager</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Topic</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($slots->num_rows == 0): ?>
                <tr><td colspan="7" class="table-empty">No slots yet &mdash; add an available time above.</td></tr>
                <?php endif; ?>
                <?php while ($row = $slots->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['manager_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($row['slot_date']); ?></td>
                    <td><?php echo htmlspecialchars(substr($row['slot_time'], 0, 5)); ?> &middot; <?php echo (int)$row['duration_minutes']; ?> min</td>
                    <td>
                        <?php if ($row['status'] == 'pending'): ?>
                            <span class="badge badge-pending">Request &middot; <?php echo htmlspecialchars($row['requested_by_name'] ?? ''); ?></span>
                        <?php elseif ($row['status'] == 'approved'): ?>
                            <span class="badge badge-booked">Approved &middot; <?php echo htmlspecialchars($row['requested_by_name'] ?? ''); ?></span>
                        <?php else: ?>
                            <span class="badge badge-open">Open</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['topic'] ?? ''); ?></td>
                    <td>
                        <div class="table-actions">
                            <?php if ($row['status'] == 'pending'): ?>
                                <?php slot_action_form('approve_request', (int)$row['id'], $weekStart, $view, 'Approve', 'btn-primary'); ?>
                                <?php slot_action_form('reject_request', (int)$row['id'], $weekStart, $view, 'Reject', 'btn-danger-soft', 'Decline this request? The slot becomes open again.'); ?>
                            <?php elseif ($row['status'] == 'approved'): ?>
                                <a href="<?php echo htmlspecialchars(gcal_url($row['title'], slot_start_dt($row), (int)$row['duration_minutes'], $row['topic'] ?? '')); ?>"
                                   target="_blank" rel="noopener" class="btn btn-primary-soft btn-sm">Google</a>
                                <a href="../ics.php?slot=<?php echo (int)$row['id']; ?>" class="btn btn-primary-soft btn-sm">.ics</a>
                                <?php slot_action_form('release_slot', (int)$row['id'], $weekStart, $view, 'Cancel', 'btn-danger-soft', 'Cancel this appointment? The employee will be notified on their dashboard.'); ?>
                            <?php else: ?>
                                <a href="index.php?<?php echo cal_qs($weekStart, $view, ['edit' => (int)$row['id']]); ?>" class="btn btn-primary-soft btn-sm">Edit</a>
                            <?php endif; ?>
                            <?php slot_action_form('delete_slot', (int)$row['id'], $weekStart, $view, 'Delete', 'btn-danger-soft', slot_delete_confirm($row)); ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include("../includes/footer.php"); ?>
