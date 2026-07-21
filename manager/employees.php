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

    if ($action == 'create_employee') {
        $stmt = $database->prepare("SELECT email FROM login_directory WHERE email = ?");
        $stmt->bind_param("s", $_POST['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $formError = "An account with this email already exists.";
        } else {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $database->prepare("INSERT INTO employee (email, password_hash, name, employee_number, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $_POST['email'], $hash, $_POST['name'], $_POST['employee_number'], $_POST['phone']);
            $stmt->execute();

            $stmt = $database->prepare("INSERT INTO login_directory (email, role) VALUES (?, 'employee')");
            $stmt->bind_param("s", $_POST['email']);
            $stmt->execute();

            $_SESSION['flash'] = "Employee account created.";
            header("location: employees.php");
            exit;
        }

    } elseif ($action == 'update_employee') {
        $stmt = $database->prepare("UPDATE employee SET name = ?, employee_number = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $_POST['name'], $_POST['employee_number'], $_POST['phone'], $_POST['id']);
        $stmt->execute();

        if (!empty($_POST['password'])) {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $database->prepare("UPDATE employee SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $_POST['id']);
            $stmt->execute();
        }

        $_SESSION['flash'] = "Employee updated.";
        header("location: employees.php");
        exit;

    } elseif ($action == 'delete_employee') {
        $stmt = $database->prepare("SELECT email FROM employee WHERE id = ?");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();
        $emailRow = $stmt->get_result()->fetch_assoc();

        $stmt = $database->prepare("DELETE FROM employee WHERE id = ?");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();

        if ($emailRow) {
            $stmt = $database->prepare("DELETE FROM login_directory WHERE email = ?");
            $stmt->bind_param("s", $emailRow['email']);
            $stmt->execute();
        }

        // The FK sets requested_by to NULL; reopen the slots their requests held.
        $database->query("UPDATE slot SET status = 'open', topic = NULL, requested_at = NULL, approved_at = NULL
                          WHERE requested_by IS NULL AND status <> 'open'");

        $_SESSION['flash'] = "Employee deleted.";
        header("location: employees.php");
        exit;
    }
}

$editEmployee = null;
if (isset($_GET['edit'])) {
    $stmt = $database->prepare("SELECT * FROM employee WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editEmployee = $stmt->get_result()->fetch_assoc();
}

$employees = $database->query("SELECT * FROM employee ORDER BY name");

$pageTitle = "Employees";
$activeNav = "employees";
include("../includes/header.php");
?>

<div class="card">
    <p class="card-title"><?php echo $editEmployee ? "Edit Employee" : "Add a New Employee"; ?></p>
    <?php if ($formError): ?>
        <p class="form-error"><?php echo htmlspecialchars($formError); ?></p>
    <?php endif; ?>
    <form action="employees.php" method="POST">
        <input type="hidden" name="action" value="<?php echo $editEmployee ? "update_employee" : "create_employee"; ?>">
        <?php if ($editEmployee): ?>
            <input type="hidden" name="id" value="<?php echo (int)$editEmployee['id']; ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div>
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="input-text" placeholder="Full Name" required
                    value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['name'] ?? '') : ''; ?>">
            </div>
            <div>
                <label class="form-label">Email</label>
                <?php if ($editEmployee): ?>
                    <input type="text" class="input-text" value="<?php echo htmlspecialchars($editEmployee['email']); ?>" disabled>
                <?php else: ?>
                    <input type="email" name="email" class="input-text" placeholder="Email Address" required>
                <?php endif; ?>
            </div>
            <div>
                <label class="form-label">Employee #</label>
                <input type="text" name="employee_number" class="input-text" placeholder="Employee #"
                    value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['employee_number'] ?? '') : ''; ?>">
            </div>
            <div>
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="input-text" placeholder="Phone"
                    value="<?php echo $editEmployee ? htmlspecialchars($editEmployee['phone'] ?? '') : ''; ?>">
            </div>
            <div>
                <label class="form-label">Password</label>
                <input type="password" name="password" class="input-text"
                    placeholder="<?php echo $editEmployee ? "Leave blank to keep" : "Password"; ?>"
                    <?php echo $editEmployee ? '' : 'required'; ?>>
            </div>
            <div class="table-actions">
                <input type="submit" value="<?php echo $editEmployee ? "Save" : "Add Employee"; ?>" class="btn btn-primary">
                <?php if ($editEmployee): ?>
                    <a href="employees.php" class="btn btn-primary-gray">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Employee #</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($employees->num_rows == 0): ?>
                <tr><td colspan="5" class="table-empty">No employees yet &mdash; add one above.</td></tr>
                <?php endif; ?>
                <?php while ($row = $employees->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['employee_number'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                    <td>
                        <div class="table-actions">
                            <a href="employees.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-primary-soft btn-sm">Edit</a>
                            <form action="employees.php" method="POST">
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                <input type="submit" value="Delete" class="btn btn-danger-soft btn-sm" onclick="return confirm('Delete this employee? Their bookings will be released.');">
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
