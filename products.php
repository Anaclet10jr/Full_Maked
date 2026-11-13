<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$error = '';
$isEditing = false;
$productId = 0;
$form = [
    'name' => '',
    'sku' => '',
    'category' => '',
    'unit_price' => '',
    'reorder_level' => '5',
    'opening_stock' => '0',
];

// Load the product when the user clicks Edit.
if (isset($_GET['edit'])) {
    $productId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if (!$productId || $productId < 1) {
        set_flash('error', 'That product could not be found.');
        redirect('products.php');
    }

    $statement = db()->prepare(
        'SELECT id, name, sku, category, unit_price, reorder_level
         FROM products WHERE id = ? AND is_active = 1'
    );
    $statement->bind_param('i', $productId);
    $statement->execute();
    $result = $statement->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        set_flash('error', 'That product could not be found.');
        redirect('products.php');
    }

    $isEditing = true;
    $form = array_merge($form, $product);
}

// Process a submitted form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'archive') {
        $archiveId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$archiveId || $archiveId < 1) {
            set_flash('error', 'Choose a product to archive.');
        } else {
            $statement = db()->prepare('UPDATE products SET is_active = 0 WHERE id = ?');
            $statement->bind_param('i', $archiveId);
            $statement->execute();
            $message = $statement->affected_rows > 0
                ? 'Product archived.'
                : 'That product is already archived.';
            set_flash('success', $message);
        }
        redirect('products.php');
    } elseif ($action === 'save') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $isEditing = $productId !== false && $productId !== null && $productId > 0;
        $form = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'sku' => trim((string) ($_POST['sku'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'unit_price' => trim((string) ($_POST['unit_price'] ?? '')),
            'reorder_level' => trim((string) ($_POST['reorder_level'] ?? '')),
            'opening_stock' => trim((string) ($_POST['opening_stock'] ?? '0')),
        ];

        if (!$isEditing && isset($_POST['product_id'])) {
            $error = 'Choose a product to update.';
        } elseif ($form['name'] === '' || strlen($form['name']) > 150) {
            $error = 'Enter a product name of up to 150 characters.';
        } elseif ($form['sku'] === '' || strlen($form['sku']) > 64) {
            $error = 'Enter a product code of up to 64 characters.';
        } elseif (strlen($form['category']) > 100) {
            $error = 'Keep the category under 100 characters.';
        } elseif (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $form['unit_price'])) {
            $error = 'Enter a valid unit price, for example 12.50.';
        } elseif (!ctype_digit($form['reorder_level']) || (int) $form['reorder_level'] > 2147483647) {
            $error = 'The reorder level must be a whole number.';
        } elseif (!$isEditing && (!ctype_digit($form['opening_stock']) || (int) $form['opening_stock'] > 2147483647)) {
            $error = 'Starting quantity must be a whole number.';
        }

        // Only save after all fields have passed validation.
        if ($error === '') {
            $transactionStarted = false;

            try {
                if ($isEditing) {
                    $category = $form['category'] !== '' ? $form['category'] : null;
                    $reorderLevel = (int) $form['reorder_level'];
                    $statement = db()->prepare(
                        'UPDATE products SET name = ?, sku = ?, category = ?, unit_price = ?, reorder_level = ?
                         WHERE id = ? AND is_active = 1'
                    );
                    $statement->bind_param(
                        'sssdii',
                        $form['name'],
                        $form['sku'],
                        $category,
                        $form['unit_price'],
                        $reorderLevel,
                        $productId
                    );
                    $statement->execute();

                    set_flash('success', 'Product details saved.');
                    redirect('products.php');
                } else {
                    // Save the product and its starting stock as one operation.
                    db()->begin_transaction();
                    $transactionStarted = true;
                    $category = $form['category'] !== '' ? $form['category'] : null;
                    $statement = db()->prepare(
                        'INSERT INTO products
                            (name, sku, category, unit_price, quantity, reorder_level, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $openingStock = (int) $form['opening_stock'];
                    $reorderLevel = (int) $form['reorder_level'];
                    $userId = (int) current_user()['id'];
                    $statement->bind_param(
                        'sssdiii',
                        $form['name'],
                        $form['sku'],
                        $category,
                        $form['unit_price'],
                        $openingStock,
                        $reorderLevel,
                        $userId
                    );
                    $statement->execute();

                    $newProductId = db()->insert_id;
                    if ($openingStock > 0) {
                        $unitPrice = (float) $form['unit_price'];
                        $totalPrice = round($unitPrice * $openingStock, 2);
                        $statement = db()->prepare(
                            "INSERT INTO stock_in
                                (product_id, user_id, movement_date, quantity, unit_price, total_price, note)
                             VALUES (?, ?, CURDATE(), ?, ?, ?, 'Opening stock')"
                        );
                        $statement->bind_param(
                            'iiidd',
                            $newProductId,
                            $userId,
                            $openingStock,
                            $unitPrice,
                            $totalPrice
                        );
                        $statement->execute();
                    }

                    db()->commit();
                    $transactionStarted = false;
                    set_flash('success', 'Product added.');
                    redirect('products.php');
                }
            } catch (mysqli_sql_exception $exception) {
                if ($transactionStarted) {
                    db()->rollback();
                }

                if ($exception->getCode() === 1062) {
                    $error = 'That product code is already in use.';
                } else {
                    error_log('Product save failed: ' . $exception->getMessage());
                    $error = 'The product could not be saved. Please try again.';
                }
            }
        }
    }
}

$showArchived = ($_GET['archived'] ?? '') === '1';
$activeStatus = $showArchived ? 0 : 1;
$statement = db()->prepare(
    'SELECT id, name, sku, category, unit_price, quantity, reorder_level
     FROM products WHERE is_active = ? ORDER BY name ASC'
);
$statement->bind_param('i', $activeStatus);
$statement->execute();
$products = $statement->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Products';
$activePage = 'products';
require __DIR__ . '/header.php';
?>
<section class="page-heading heading-with-action">
    <div><p class="eyebrow">PRODUCT CATALOGUE</p><h1>Products</h1><p class="muted">Items kept in the school kitchen.</p></div>
    <?php if (!$showArchived): ?><a class="button button-primary" href="products.php#product-form"><span aria-hidden="true">＋</span> Add product</a><?php endif; ?>
</section>

<div class="products-layout">
    <section class="panel product-table-panel">
        <div class="panel-heading">
            <div><h2><?= $showArchived ? 'Archived products' : 'Active products' ?></h2><p class="muted"><?= number_format(count($products)) ?> <?= count($products) === 1 ? 'product' : 'products' ?></p></div>
            <a class="text-link" href="products.php<?= $showArchived ? '' : '?archived=1' ?>"><?= $showArchived ? 'Back to active' : 'View archived' ?></a>
        </div>
        <?php if (!$products): ?>
            <div class="empty-state"><span class="empty-icon">▦</span><strong><?= $showArchived ? 'No archived products' : 'No products added yet' ?></strong><p><?= $showArchived ? 'Archived items will remain here for reference.' : 'Add the items used by the kitchen to begin.' ?></p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Product</th><th>Category</th><th>Unit price</th><th>Quantity</th><th>Reorder at</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php $isLow = !$showArchived && (int) $product['quantity'] <= (int) $product['reorder_level']; ?>
                        <tr>
                            <td><strong><?= e($product['name']) ?></strong><small class="table-sub"><?= e($product['sku']) ?></small></td>
                            <td><?= e($product['category'] ?: '—') ?></td>
                            <td><?= e(format_money($product['unit_price'])) ?></td>
                            <td><span class="stock-count <?= $isLow ? 'stock-low' : '' ?>"><?= number_format((int) $product['quantity']) ?></span><?php if ($isLow): ?><small class="table-sub low-label">Reorder</small><?php endif; ?></td>
                            <td><?= number_format((int) $product['reorder_level']) ?></td>
                            <td class="row-actions">
                            <?php if (!$showArchived): ?>
                                <a class="table-action" href="products.php?edit=<?= (int) $product['id'] ?>#product-form">Edit</a>
                                <form method="post" class="inline-form" onsubmit="return confirm('Archive this product? Its stock records will be kept.');">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <button class="table-action table-action-muted" type="submit">Archive</button>
                                </form>
                            <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$showArchived): ?>
    <section class="panel product-form-panel" id="product-form">
        <div class="panel-heading"><div><h2><?= $isEditing ? 'Edit product' : 'Add product' ?></h2><p class="muted"><?= $isEditing ? 'Change the product details.' : 'Enter the item and its starting quantity.' ?></p></div></div>
        <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($isEditing): ?><input type="hidden" name="product_id" value="<?= (int) $productId ?>"><?php endif; ?>
            <label for="name">Product name</label><input id="name" name="name" type="text" maxlength="150" value="<?= e($form['name']) ?>" placeholder="e.g. Rice" required>
            <label for="sku">Product code</label><input id="sku" name="sku" type="text" maxlength="64" value="<?= e($form['sku']) ?>" placeholder="e.g. FOOD-001" required>
            <label for="category">Category <span class="label-hint">Optional</span></label><input id="category" name="category" type="text" maxlength="100" value="<?= e($form['category']) ?>" placeholder="e.g. Grains">
            <div class="form-row">
                <div><label for="unit_price">Unit price (RWF)</label><input id="unit_price" name="unit_price" type="number" min="0" step="0.01" value="<?= e($form['unit_price']) ?>" placeholder="0.00" required></div>
                <div><label for="reorder_level">Reorder level</label><input id="reorder_level" name="reorder_level" type="number" min="0" step="1" value="<?= e($form['reorder_level']) ?>" required></div>
            </div>
            <?php if (!$isEditing): ?><label for="opening_stock">Starting quantity</label><input id="opening_stock" name="opening_stock" type="number" min="0" step="1" value="<?= e($form['opening_stock']) ?>" required><p class="field-help">A positive starting quantity is added to the stock-in records.</p><?php endif; ?>
            <button class="button button-primary button-wide" type="submit"><?= $isEditing ? 'Save changes' : 'Add product' ?></button>
            <?php if ($isEditing): ?><a class="cancel-link" href="products.php">Cancel editing</a><?php endif; ?>
        </form>
    </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
