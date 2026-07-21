<?php
// Expects $pageTitle and $activeNav to be set by the caller, which must
// already have called session_start() and enforced the role check.
$role = $_SESSION['role'];
$cssFile = $role == 'admin' ? 'admin.css' : ($role == 'manager' ? 'manager.css' : 'employee.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/calendar.css">
    <link rel="stylesheet" href="../css/<?php echo $cssFile; ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Dortmund Handling Services GmbH</title>
</head>
<body>
    <input type="checkbox" id="nav-toggle" class="nav-toggle-input">
    <div class="app-layout">
        <label for="nav-toggle" class="nav-burger" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </label>
        <label for="nav-toggle" class="nav-scrim"></label>
        <aside class="sidebar">
            <div class="sidebar-brand">
                Dortmund Handling Services
                <span>Scheduler</span>
            </div>
            <div class="sidebar-profile">
                <img src="../img/user.png" alt="">
                <div>
                    <p class="profile-title"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></p>
                    <p class="profile-subtitle"><?php echo $role == 'admin' ? 'Administrator' : ($role == 'manager' ? 'Manager' : 'Employee'); ?></p>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php if ($role == 'admin'): ?>
                    <a href="index.php" class="nav-link<?php echo $activeNav == 'appointments' ? ' nav-link-active' : ''; ?>">Appointments</a>
                    <a href="employees.php" class="nav-link<?php echo $activeNav == 'employees' ? ' nav-link-active' : ''; ?>">Employees</a>
                    <a href="settings.php" class="nav-link<?php echo $activeNav == 'settings' ? ' nav-link-active' : ''; ?>">Settings</a>
                <?php elseif ($role == 'manager'): ?>
                    <a href="index.php" class="nav-link<?php echo $activeNav == 'slots' ? ' nav-link-active' : ''; ?>">Slots</a>
                    <a href="employees.php" class="nav-link<?php echo $activeNav == 'employees' ? ' nav-link-active' : ''; ?>">Employees</a>
                    <a href="settings.php" class="nav-link<?php echo $activeNav == 'settings' ? ' nav-link-active' : ''; ?>">Settings</a>
                <?php else: ?>
                    <a href="index.php" class="nav-link<?php echo $activeNav == 'dashboard' ? ' nav-link-active' : ''; ?>">Dashboard</a>
                    <a href="settings.php" class="nav-link<?php echo $activeNav == 'settings' ? ' nav-link-active' : ''; ?>">Settings</a>
                <?php endif; ?>
            </nav>
            <a href="../logout.php" class="logout-btn">Log out</a>
        </aside>
        <main class="content">
            <header class="page-header">
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            </header>
            <?php if (!empty($_SESSION['flash'])): ?>
                <p class="flash"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></p>
            <?php endif; ?>
