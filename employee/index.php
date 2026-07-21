<?php
require_once __DIR__ . '/../includes/session.php';
include("../connection.php");
include("../includes/calendar.php");
include("../includes/ical.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employee') {
    header("location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action == 'request_slot') {
        $topic = trim($_POST['topic'] ?? '');
        if ($topic === '' || !mb_check_encoding($topic, 'UTF-8')) {
            $_SESSION['flash'] = "Please describe the topic of the meeting before requesting.";
        } elseif (mb_strlen($topic) > 500) {
            $_SESSION['flash'] = "The topic description is too long (500 characters max).";
        } else {
            $stmt = $database->prepare(
                "UPDATE slot SET status = 'pending', requested_by = ?, topic = ?, requested_at = NOW()
                 WHERE id = ? AND status = 'open'"
            );
            $stmt->bind_param("isi", $_SESSION['user_id'], $topic, $_POST['id']);
            $stmt->execute();
            $_SESSION['flash'] = $stmt->affected_rows > 0
                ? "Appointment requested. You'll see it as approved once the manager confirms."
                : "Sorry, that slot was just requested by someone else.";
        }

    } elseif ($action == 'withdraw_request') {
        $stmt = $database->prepare(
            "UPDATE slot SET status = 'open', requested_by = NULL, topic = NULL, requested_at = NULL
             WHERE id = ? AND requested_by = ? AND status = 'pending'"
        );
        $stmt->bind_param("ii", $_POST['id'], $_SESSION['user_id']);
        $stmt->execute();
        $_SESSION['flash'] = $stmt->affected_rows > 0
            ? "Request withdrawn."
            : "That request was already handled by the manager.";

    } elseif ($action == 'cancel_appointment') {
        $stmt = $database->prepare(
            "UPDATE slot SET status = 'open', requested_by = NULL, topic = NULL, requested_at = NULL, approved_at = NULL
             WHERE id = ? AND requested_by = ? AND status = 'approved'"
        );
        $stmt->bind_param("ii", $_POST['id'], $_SESSION['user_id']);
        $stmt->execute();
        $_SESSION['flash'] = $stmt->affected_rows > 0
            ? "Appointment cancelled. Remember to remove it from your calendar if you added it."
            : "That appointment already changed.";
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

// Rejections / cancellations by the manager from the last 30 days.
$stmt = $database->prepare(
    "SELECT * FROM request_notice
     WHERE employee_id = ? AND created_at > (NOW() - INTERVAL 30 DAY)
     ORDER BY created_at DESC LIMIT 10"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$notices = $stmt->get_result();

if ($view == 'week') {
    // Other employees' topics never leave the database.
    $stmt = $database->prepare(
        "SELECT id, title, slot_date, slot_time, duration_minutes, status, requested_by,
                CASE WHEN requested_by = ? THEN topic END AS topic
         FROM slot
         WHERE slot_date BETWEEN ? AND ?
         ORDER BY slot_date, slot_time"
    );
    $from = $weekStart->format('Y-m-d');
    $to = $weekEnd->format('Y-m-d');
    $stmt->bind_param("iss", $_SESSION['user_id'], $from, $to);
    $stmt->execute();
    $slotsByDay = cal_group_by_day($stmt->get_result());
} else {
    $openSlots = $database->query("SELECT * FROM slot WHERE status = 'open' ORDER BY slot_date, slot_time");

    $stmt = $database->prepare(
        "SELECT * FROM slot WHERE requested_by = ? AND status IN ('pending','approved')
         ORDER BY slot_date, slot_time"
    );
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $myRequests = $stmt->get_result();
}

$pageTitle = "Dashboard";
$activeNav = "dashboard";
include("../includes/header.php");
?>

<?php if ($notices->num_rows > 0): ?>
<div class="notice-list">
    <?php while ($n = $notices->fetch_assoc()): ?>
    <p class="notice notice-<?php echo $n['kind']; ?>">
        <?php if ($n['kind'] == 'rejected'): ?>
            Your request for <strong><?php echo htmlspecialchars($n['title']); ?></strong>
            on <?php echo htmlspecialchars($n['slot_date']); ?> at <?php echo htmlspecialchars(substr($n['slot_time'], 0, 5)); ?>
            (&ldquo;<?php echo htmlspecialchars($n['topic'] ?? ''); ?>&rdquo;) was <strong>declined</strong> by the manager.
        <?php else: ?>
            Your appointment <strong><?php echo htmlspecialchars($n['title']); ?></strong>
            on <?php echo htmlspecialchars($n['slot_date']); ?> at <?php echo htmlspecialchars(substr($n['slot_time'], 0, 5)); ?>
            (&ldquo;<?php echo htmlspecialchars($n['topic'] ?? ''); ?>&rdquo;) was <strong>cancelled</strong> by the manager.
            Remove it from your calendar if you had added it.
        <?php endif; ?>
    </p>
    <?php endwhile; ?>
</div>
<?php endif; ?>

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
        </div>
        <?php if (empty($daySlots)): ?>
            <p class="day-none">&mdash;</p>
        <?php endif; ?>
        <?php foreach ($daySlots as $row):
            $isMine = $row['requested_by'] == $_SESSION['user_id'];
            $isOpen = $row['status'] === 'open';
        ?>
        <?php if ($isOpen): ?>
        <div class="slot-card slot-open">
            <p class="slot-time"><?php echo htmlspecialchars(substr($row['slot_time'], 0, 5)); ?> &middot; <?php echo (int)$row['duration_minutes']; ?> min</p>
            <p class="slot-title"><?php echo htmlspecialchars($row['title']); ?></p>
            <span class="badge badge-open">Open</span>
            <form action="index.php" method="POST" class="request-form">
                <input type="hidden" name="action" value="request_slot">
                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                <input type="hidden" name="week" value="<?php echo $weekStart->format('Y-m-d'); ?>">
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                <input type="text" name="topic" class="input-text input-topic" placeholder="Topic of the meeting" maxlength="500" required>
                <input type="submit" value="Request" class="btn btn-primary btn-sm">
            </form>
        </div>
        <?php elseif ($isMine && $row['status'] === 'pending'): ?>
        <div class="slot-card slot-pending">
            <p class="slot-time"><?php echo htmlspecialchars(substr($row['slot_time'], 0, 5)); ?> &middot; <?php echo (int)$row['duration_minutes']; ?> min</p>
            <p class="slot-title"><?php echo htmlspecialchars($row['title']); ?></p>
            <p class="slot-topic"><?php echo htmlspecialchars($row['topic'] ?? ''); ?></p>
            <span class="badge badge-pending">Pending approval</span>
            <div class="slot-actions">
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="withdraw_request">
                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                    <input type="hidden" name="week" value="<?php echo $weekStart->format('Y-m-d'); ?>">
                    <input type="hidden" name="view" value="<?php echo $view; ?>">
                    <input type="submit" value="Withdraw" class="btn btn-primary-gray btn-sm" onclick="return confirm('Withdraw this request?');">
                </form>
            </div>
        </div>
        <?php elseif ($isMine && $row['status'] === 'approved'): ?>
        <div class="slot-card slot-mine">
            <p class="slot-time"><?php echo htmlspecialchars(substr($row['slot_time'], 0, 5)); ?> &middot; <?php echo (int)$row['duration_minutes']; ?> min</p>
            <p class="slot-title"><?php echo htmlspecialchars($row['title']); ?></p>
            <p class="slot-topic"><?php echo htmlspecialchars($row['topic'] ?? ''); ?></p>
            <span class="badge badge-mine">Approved</span>
            <div class="slot-actions">
                <a href="<?php echo htmlspecialchars(gcal_url($row['title'], slot_start_dt($row), (int)$row['duration_minutes'], $row['topic'] ?? '')); ?>"
                   target="_blank" rel="noopener" class="btn btn-primary-soft btn-sm">Google</a>
                <a href="../ics.php?slot=<?php echo (int)$row['id']; ?>" class="btn btn-primary-soft btn-sm">.ics</a>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="cancel_appointment">
                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                    <input type="hidden" name="week" value="<?php echo $weekStart->format('Y-m-d'); ?>">
                    <input type="hidden" name="view" value="<?php echo $view; ?>">
                    <input type="submit" value="Cancel" class="btn btn-danger-soft btn-sm" onclick="return confirm('Cancel this appointment?');">
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="slot-card slot-taken">
            <p class="slot-time"><?php echo htmlspecialchars(substr($row['slot_time'], 0, 5)); ?> &middot; <?php echo (int)$row['duration_minutes']; ?> min</p>
            <p class="slot-title"><?php echo htmlspecialchars($row['title']); ?></p>
            <span class="badge badge-taken">Taken</span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>

<div class="card">
    <p class="card-title">My Requests &amp; Appointments</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Topic</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($myRequests->num_rows == 0): ?>
                <tr><td colspan="6" class="table-empty">No requests yet &mdash; pick an open slot below.</td></tr>
                <?php endif; ?>
                <?php while ($row = $myRequests->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['slot_date']); ?></td>
                    <td><?php echo htmlspecialchars(substr($row['slot_time'], 0, 5)); ?> &middot; <?php echo (int)$row['duration_minutes']; ?> min</td>
                    <td><?php echo htmlspecialchars($row['topic'] ?? ''); ?></td>
                    <td>
                        <?php if ($row['status'] == 'pending'): ?>
                            <span class="badge badge-pending">Pending</span>
                        <?php else: ?>
                            <span class="badge badge-mine">Approved</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <?php if ($row['status'] == 'pending'): ?>
                            <form action="index.php" method="POST">
                                <input type="hidden" name="action" value="withdraw_request">
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                <input type="hidden" name="week" value="<?php echo $weekStart->format('Y-m-d'); ?>">
                                <input type="hidden" name="view" value="<?php echo $view; ?>">
                                <input type="submit" value="Withdraw" class="btn btn-primary-gray btn-sm" onclick="return confirm('Withdraw this request?');">
                            </form>
                            <?php else: ?>
                            <a href="<?php echo htmlspecialchars(gcal_url($row['title'], slot_start_dt($row), (int)$row['duration_minutes'], $row['topic'] ?? '')); ?>"
                               target="_blank" rel="noopener" class="btn btn-primary-soft btn-sm">Google</a>
                            <a href="../ics.php?slot=<?php echo (int)$row['id']; ?>" class="btn btn-primary-soft btn-sm">.ics</a>
                            <form action="index.php" method="POST">
                                <input type="hidden" name="action" value="cancel_appointment">
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                <input type="hidden" name="week" value="<?php echo $weekStart->format('Y-m-d'); ?>">
                                <input type="hidden" name="view" value="<?php echo $view; ?>">
                                <input type="submit" value="Cancel" class="btn btn-danger-soft btn-sm" onclick="return confirm('Cancel this appointment?');">
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="card-title">Open Slots</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Request</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($openSlots->num_rows == 0): ?>
                <tr><td colspan="4" class="table-empty">No open slots right now.</td></tr>
                <?php endif; ?>
                <?php while ($row = $openSlots->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['slot_date']); ?></td>
                    <td><?php echo htmlspecialchars(substr($row['slot_time'], 0, 5)); ?> &middot; <?php echo (int)$row['duration_minutes']; ?> min</td>
                    <td>
                        <form action="index.php" method="POST" class="request-form">
                            <input type="hidden" name="action" value="request_slot">
                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                            <input type="hidden" name="week" value="<?php echo $weekStart->format('Y-m-d'); ?>">
                            <input type="hidden" name="view" value="<?php echo $view; ?>">
                            <input type="text" name="topic" class="input-text input-topic" placeholder="Topic of the meeting" maxlength="500" required>
                            <input type="submit" value="Request" class="btn btn-primary btn-sm">
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include("../includes/footer.php"); ?>
