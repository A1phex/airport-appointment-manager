<?php
// DB config comes from the environment (DB_* preferred, Railway's MYSQL* as
// fallback), with defaults matching the local docker-compose setup.
$servername = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'db';
$port       = (int)(getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306);
$username   = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'user';
$password   = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: 'password';
$dbname     = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'dhs_scheduler';

try {
    $database = new mysqli($servername, $username, $password, $dbname, $port);
} catch (mysqli_sql_exception $e) {
    // PHP 8.1+ mysqli throws instead of setting connect_error.
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed.");
}

if ($database->connect_error) {
    error_log("Database connection failed: " . $database->connect_error);
    die("Database connection failed.");
}

$database->set_charset('utf8mb4');

// Align NOW() with the app's timezone. The stock mysql image ships without
// timezone tables, so set the current Berlin UTC offset (DST-correct today).
$tzOffset = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('P');
$database->query("SET time_zone = '" . $tzOffset . "'");
