<?php
date_default_timezone_set('Europe/Berlin');

// Shared session bootstrap. Marks the cookie Secure when the request came in
// over HTTPS, including behind a TLS-terminating proxy (X-Forwarded-Proto).
$https = $_SERVER['HTTPS'] ?? '';
$secure = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || ($https !== '' && $https !== 'off');

session_set_cookie_params([
    'httponly' => true,
    'secure'   => $secure,
    'samesite' => 'Lax',
]);

session_start();
