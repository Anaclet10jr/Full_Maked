<?php
$user = current_user();
$pageTitle = $pageTitle ?? 'Stockroom';
$activePage = $activePage ?? '';
$flash = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f6f2">
    <title><?= e($pageTitle) ?> · Canteen Stock</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?= $user ? 'app-body' : 'auth-body' ?>">
<?php if ($user): ?>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="dashboard.php" aria-label="Canteen Stock home">
                <span class="brand-mark">C</span>
                <span>Canteen Stock</span>
            </a>
            <p class="nav-caption">INVENTORY</p>
            <nav class="main-nav" aria-label="Main navigation">
                <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php"><span class="nav-icon">⌂</span>Overview</a>
                <a class="<?= $activePage === 'products' ? 'active' : '' ?>" href="products.php"><span class="nav-icon">▦</span>Products</a>
                <a class="<?= $activePage === 'stock_in' ? 'active' : '' ?>" href="stock_in.php"><span class="nav-icon">↙</span>Stock in</a>
                <a class="<?= $activePage === 'stock_out' ? 'active' : '' ?>" href="stock_out.php"><span class="nav-icon">↗</span>Stock out</a>
                <a class="<?= $activePage === 'reports' ? 'active' : '' ?>" href="reports.php"><span class="nav-icon">▤</span>Reports</a>
            </nav>
            <div class="sidebar-bottom">
                <div class="user-card">
                    <span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
                    <span class="user-details"><strong><?= e($user['name']) ?></strong><small>Signed in</small></span>
                </div>
                <form action="logout.php" method="post">
                    <?= csrf_field() ?>
                    <button class="signout-button" type="submit"><span class="nav-icon">↪</span>Sign out</button>
                </form>
            </div>
        </aside>
        <div class="app-main">
            <header class="topbar">
                <span class="topbar-label">School kitchen</span>
                <span class="topbar-date"><?= e(date('l, F j')) ?></span>
            </header>
            <main class="page-content">
                <?php if ($flash): ?>
                    <div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
                <?php endif; ?>
<?php else: ?>
    <main class="auth-page">
        <?php if ($flash): ?>
            <div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
        <?php endif; ?>
<?php endif; ?>