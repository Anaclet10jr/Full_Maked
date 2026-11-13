<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$error = '';
$form = [
    'product_id' => '',
    'movement_date' => date('Y-m-d'),
    'quantity' => '',
    'unit_price' => '',
    'note' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $form = [
        'product_id' => trim((string) ($_POST['product_id'] ?? '')),
        'movement_date' => trim((string) ($_POST['movement_date'] ?? '')),
        'quantity' => trim((string) ($_POST['quantity'] ?? '')),
        'unit_price' => trim((string) ($_POST['unit_price'] ?? '')),
        'note' => trim((string) ($_POST['note'] ?? '')),
    ];
    $productId = filter_var($form['product_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($form['quantity'], FILTER_VALIDATE_INT);

    if ($productId === false || $productId < 1) {
        $error = 'Choose a product.';
    } elseif (!valid_date($form['movement_date'])) {
        $error = 'Enter a valid date.';
    } elseif ($quantity === false || $quantity < 1 || $quantity > 2147483647) {
        $error = 'Quantity must be a whole number greater than zero.';
    } elseif (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $form['unit_price'])) {
        $error = 'Enter a valid unit cost, for example 12.50.';
    } elseif (strlen($form['note']) > 255) {
        $error = 'Keep the note under 255 characters.';
    } else {
        $transactionStarted = false;

        try {
            db()->begin_transaction();
            $transactionStarted = true;
            $product = db_one(
                'SELECT id, quantity FROM products WHERE id = ? AND is_active = 1 FOR UPDATE',
                'i',
                $productId
            );

            if ($product === null) {
                db()->rollback();
                $transactionStarted = false;
                $error = 'That product is not available.';
            } elseif ((int) $product['quantity'] + $quantity > 4294967295) {
                db()->rollback();
                $transactionStarted = false;
                $error = 'The new quantity is above the maximum allowed.';
            } else {
                $unitPrice = (float) $form['unit_price'];
                $totalPrice = round($unitPrice * $quantity, 2);

                db_execute(
                    'UPDATE products SET quantity = quantity + ? WHERE id = ? AND is_active = 1',
                    'ii',
                    $quantity,
                    $productId
                );
                db_execute(
                    'INSERT INTO stock_in
                        (product_id, user_id, movement_date, quantity, unit_price, total_price, note)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'iisidds',
                    $productId,
                    current_user()['id'],
                    $form['movement_date'],
                    $quantity,
                    $unitPrice,
                    $totalPrice,
                    $form['note'] !== '' ? $form['note'] : null
                );
                db()->commit();
                $transactionStarted = false;
                set_flash('success', 'Stock received and quantity updated.');
                redirect('stock_in.php');
            }
        } catch (mysqli_sql_exception $exception) {
            if ($transactionStarted) {
                db()->rollback();
            }
            error_log('Stock-in entry failed: ' . $exception->getMessage());
            $error = 'The stock-in record could not be saved. Please try again.';
        }
    }
}

$products = db_all(
    'SELECT id, name, sku, quantity, unit_price FROM products WHERE is_active = 1 ORDER BY name ASC'
);
$recentEntries = db_all(
    'SELECT s.movement_date, s.quantity, s.unit_price, s.total_price, s.note, p.name, p.sku
     FROM stock_in s
     JOIN products p ON p.id = s.product_id
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT 8'
);

$pageTitle = 'Stock in';
$activePage = 'stock_in';
require __DIR__ . '/header.php';
?>
<section class="page-heading">
    <p class="eyebrow">PURCHASES AND DELIVERIES</p>
    <h1>Stock received</h1>
    <p class="muted">Record a delivery and add its quantity to the product balance.</p>
</section>

<div class="entry-layout">
    <section class="panel entry-form-panel">
        <div class="panel-heading"><div><h2>New stock-in entry</h2><p class="muted">Enter the quantity and cost on the delivery record.</p></div></div>
        <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if (!$products): ?>
            <div class="empty-state"><strong>No active products</strong><p>Add a product before recording stock.</p><a class="text-link" href="products.php">Go to products →</a></div>
        <?php else: ?>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label for="product_id">Product</label>
            <select id="product_id" name="product_id" required>
                <option value="">Choose a product</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>" <?= (string) $product['id'] === $form['product_id'] ? 'selected' : '' ?>>
                        <?= e(product_label($product)) ?> — <?= number_format((int) $product['quantity']) ?> on hand
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="movement_date">Date received</label><input id="movement_date" name="movement_date" type="date" value="<?= e($form['movement_date']) ?>" required>
            <div class="form-row">
                <div><label for="quantity">Quantity received</label><input id="quantity" name="quantity" type="number" min="1" step="1" value="<?= e($form['quantity']) ?>" required></div>
                <div><label for="unit_price">Unit cost (RWF)</label><input id="unit_price" name="unit_price" type="number" min="0" step="0.01" value="<?= e($form['unit_price']) ?>" required></div>
            </div>
            <label for="note">Note <span class="label-hint">Optional</span></label><input id="note" name="note" type="text" maxlength="255" value="<?= e($form['note']) ?>" placeholder="Supplier or delivery reference">
            <button class="button button-primary button-wide" type="submit">Save stock-in</button>
        </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading"><div><h2>Recent stock received</h2><p class="muted">Latest eight entries</p></div></div>
        <?php if (!$recentEntries): ?>
            <div class="empty-state"><strong>No deliveries recorded</strong><p>Saved stock-in entries will appear here.</p></div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Product</th><th>Qty</th><th>Total cost</th></tr></thead>
                <tbody>
                <?php foreach ($recentEntries as $entry): ?>
                    <tr>
                        <td><?= e(date('M j, Y', strtotime($entry['movement_date']))) ?></td>
                        <td><strong><?= e($entry['name']) ?></strong><small class="table-sub"><?= e($entry['sku']) ?></small></td>
                        <td><?= number_format((int) $entry['quantity']) ?></td>
                        <td><?= e(format_money($entry['total_price'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/footer.php'; ?>
