<?php

require_once __DIR__ . '/includes/session.php';

$_SESSION = array();

include("connection.php");

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = $_POST['useremail'] ?? '';
    $password = $_POST['userpassword'] ?? '';

    $stmt = $database->prepare("SELECT role FROM login_directory WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $role = $result->fetch_assoc()['role'];

        if ($role == 'admin') {
            $stmt = $database->prepare("SELECT id, name, password_hash FROM admin WHERE email = ?");
        } elseif ($role == 'manager') {
            $stmt = $database->prepare("SELECT id, name, password_hash FROM manager WHERE email = ?");
        } else {
            $stmt = $database->prepare("SELECT id, name, password_hash FROM employee WHERE email = ?");
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();

        if ($account && password_verify($password, $account['password_hash'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $account['id'];
            $_SESSION['name'] = $account['name'];
            $_SESSION['role'] = $role;

            if ($role == 'admin') {
                header('location: admin/index.php');
            } elseif ($role == 'manager') {
                header('location: manager/index.php');
            } else {
                header('location: employee/index.php');
            }
            exit;

        } else {
            $error = "Wrong credentials: invalid email or password.";
        }
    } else {
        $error = "We couldn't find an account for this email.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/animations.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/login.css">

    <title>Login - Dortmund Handling Services GmbH</title>
</head>
<body class="login-page">

    <div class="login-split">
        <div class="login-visual">
            <div class="login-visual-text">
                <p class="login-brand">Dortmund Handling Services GmbH</p>
                <p class="login-brand-sub">Internal Appointment Scheduler</p>
            </div>
        </div>

        <div class="login-panel">
            <form action="" method="POST" class="login-card">
                <h1>Welcome back</h1>
                <p class="login-hint">Sign in with your staff account.</p>

                <label for="useremail" class="form-label">Email</label>
                <input type="email" id="useremail" name="useremail" class="input-text" placeholder="Email Address" required>

                <label for="userpassword" class="form-label">Password</label>
                <input type="password" id="userpassword" name="userpassword" class="input-text" placeholder="Password" required>

                <?php if ($error): ?>
                    <p class="form-error"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <input type="submit" value="Login" class="btn btn-primary login-submit">

                <a href="index.html" class="login-back">&larr; Back to home</a>
            </form>
        </div>
    </div>

</body>
</html>
