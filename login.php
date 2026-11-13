<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter both your username and password.';
    } else {
        $account = db_one(
            'SELECT id, username, name, password_hash FROM users WHERE username = ? LIMIT 1',
            's',
            $username
        );

        if ($account && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $account['id'],
                'username' => $account['username'],
                'name' => $account['name'],
            ];
            redirect('dashboard.php');
        }

        $error = 'That username and password do not match.';
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/header.php';
?>
<section class="auth-card">
    <a class="brand auth-brand" href="index.php"><span class="brand-mark">C</span><span>Canteen Stock</span></a>
    <p class="eyebrow">SCHOOL KITCHEN INVENTORY</p>
    <h1>Sign in</h1>
    <p class="muted">Record deliveries, issues and current stock.</p>
    <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
        <?= csrf_field() ?>
        <label for="username">Registration number</label>
        <input id="username" name="username" type="text" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="button button-primary button-wide" type="submit">Sign in <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-switch">Use the login details supplied with this project.</p>
</section>
<?php require __DIR__ . '/footer.php'; ?>