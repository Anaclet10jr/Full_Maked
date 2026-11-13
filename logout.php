<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

require_valid_csrf();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, [
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
redirect('login.php');