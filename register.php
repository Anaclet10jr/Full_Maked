<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

set_flash('info', 'Account creation is disabled. Sign in with the details in README.md.');
redirect('login.php');
