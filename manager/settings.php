<?php
require_once __DIR__ . '/../includes/session.php';
include("../connection.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'manager') {
    header("location: ../login.php");
    exit;
}

$formError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action == 'update_profile') {
        $stmt = $database->prepare("UPDATE manager SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $_POST['name'], $_SESSION['user_id']);
        $stmt->execute();

        $_SESSION['name'] = $_POST['name'];
        $_SESSION['flash'] = "Profile updated.";
        header("location: settings.php");
        exit;

    } elseif ($action == 'change_password') {
        $stmt = $database->prepare("SELECT password_hash FROM manager WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();

        if (!password_verify($_POST['current_password'] ?? '', $account['password_hash'])) {
            $formError = "Current password is incorrect.";
        } elseif (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
            $formError = "New password and confirmation don't match.";
        } else {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $database->prepare("UPDATE manager SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $_SESSION['user_id']);
            $stmt->execute();

            $_SESSION['flash'] = "Password changed.";
            header("location: settings.php");
            exit;
        }
    }
}

$stmt = $database->prepare("SELECT * FROM manager WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

$pageTitle = "Settings";
$activeNav = "settings";
include("../includes/header.php");
?>

<div class="card">
    <p class="card-title">Profile</p>
    <form action="settings.php" method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-grid">
            <div>
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="input-text" placeholder="Full Name" required
                    value="<?php echo htmlspecialchars($me['name'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label">Email</label>
                <input type="text" class="input-text" value="<?php echo htmlspecialchars($me['email']); ?>" disabled>
            </div>
            <div>
                <input type="submit" value="Save" class="btn btn-primary">
            </div>
        </div>
    </form>
</div>

<div class="card">
    <p class="card-title">Change Password</p>
    <?php if ($formError): ?>
        <p class="form-error"><?php echo htmlspecialchars($formError); ?></p>
    <?php endif; ?>
    <form action="settings.php" method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-grid">
            <div>
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="input-text" placeholder="Current Password" required>
            </div>
            <div>
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="input-text" placeholder="New Password" required>
            </div>
            <div>
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="input-text" placeholder="Confirm New Password" required>
            </div>
            <div>
                <input type="submit" value="Change Password" class="btn btn-primary">
            </div>
        </div>
    </form>
</div>

<?php include("../includes/footer.php"); ?>
