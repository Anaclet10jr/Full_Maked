<?php
declare(strict_types=1);

$databaseHost = getenv('DB_HOST') ?: '127.0.0.1';
$databaseName = getenv('DB_NAME') ?: 'inventory_app';
$databaseUser = getenv('DB_USER') ?: 'root';
$databasePassword = getenv('DB_PASS') ?: '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli($databaseHost, $databaseUser, $databasePassword, $databaseName);
    $mysqli->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The database is not available. Check the connection settings in config.php and make sure MySQL is running.');
}
