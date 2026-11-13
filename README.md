# Canteen Stock

A PHP and MySQL application for recording school-kitchen products, stock received, stock issued, and stock reports.

## Run with XAMPP

1. Copy the project folder into `C:\xampp\htdocs`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, select **Import**, and import `database.sql`.
4. Open `http://localhost/MUNYAWERA_Anaclet_25RP00508_PhpCAT/`.

The default database connection is in `config.php` (`127.0.0.1`, database `inventory_app`, user `root`, no password). If your MySQL account differs, set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in your PHP environment.

## Sign in

- **Username:** `25RP00508`
- **Password:** `12345`

The SQL file creates this account the first time it is imported. It does not replace an existing account with the same username.

## Main functions

- Add, update, and archive products.
- Record incoming stock with its unit and total cost.
- Record stock issued; the application checks that enough units are available.
- Review current quantities, low-stock items, and stock/value totals over a selected date range.

Database access uses MySQLi prepared statements. Stock changes and their transaction records are saved together so the quantity does not change without a matching record.

The supplied password is intended for this local class project. Change it before using the application on a shared or public server.

## Learn how the code works

Start with `products.php`. It shows the common pattern used by the app:

1. Read values sent by the form.
2. Check that the values are valid.
3. Use a MySQLi prepared statement to read or save data.
4. Show a message or redirect back to the page.

### Main files

- `config.php` connects PHP to the MySQL database.
- `functions.php` contains small helpers used on more than one page.
- `auth.php` checks that the user is signed in.
- `login.php` checks the username and password.
- `dashboard.php` displays current stock totals.
- `products.php` adds, edits, and archives products.
- `stock_in.php` adds received stock.
- `stock_out.php` subtracts issued stock.
- `reports.php` displays stock movements for dates you choose.
- `database.sql` creates the tables and the first login account.

### MySQLi prepared statement example

The `?` is a placeholder for a value. `bind_param()` safely supplies that value before the query runs:

```php
$statement = db()->prepare('SELECT id, name FROM products WHERE id = ?');
$statement->bind_param('i', $productId); // i means the value is an integer
$statement->execute();
$product = $statement->get_result()->fetch_assoc();
```

For `bind_param()`, use `i` for an integer, `d` for a decimal number, and `s` for text. Use prepared statements for values received from forms; do not put those values directly into SQL.

### Stock update example

When stock is received, the page starts a database transaction, updates the product quantity, and writes the stock-in record. It commits both changes together. If saving fails, it rolls back so the quantity and history stay in sync. `stock_out.php` follows the same idea when stock is issued.
