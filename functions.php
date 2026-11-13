<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/config.php';

function db(): mysqli
{
    global $mysqli;
    return $mysqli;
}

function db_execute(string $sql, string $types = '', mixed ...$params): mysqli_stmt
{
    $statement = db()->prepare($sql);

    if ($types !== '') {
        if (strlen($types) !== count($params)) {
            throw new InvalidArgumentException('The number of bind types must match the number of values.');
        }

        $arguments = [$types];
        foreach ($params as $index => &$value) {
            $arguments[] = &$value;
        }
        call_user_func_array([$statement, 'bind_param'], $arguments);
    }

    $statement->execute();
    return $statement;
}

function db_one(string $sql, string $types = '', mixed ...$params): ?array
{
    $result = db_execute($sql, $types, ...$params)->get_result();
    $row = $result->fetch_assoc();

    return $row === null ? null : $row;
}

function db_all(string $sql, string $types = '', mixed ...$params): array
{
    return db_execute($sql, $types, ...$params)->get_result()->fetch_all(MYSQLI_ASSOC);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_valid_csrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals(csrf_token(), $submittedToken)) {
        http_response_code(403);
        exit('This form has expired. Go back, refresh the page, and try again.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login(): void
{
    if (current_user() === null) {
        set_flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function format_money(int|float|string $amount): string
{
    return 'RWF ' . number_format((float) $amount, 2);
}

function product_label(array $product): string
{
    return $product['name'] . ' (' . $product['sku'] . ')';
}

function valid_date(string $value): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
        return false;
    }

    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
}