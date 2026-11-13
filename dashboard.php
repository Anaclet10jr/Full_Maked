<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$summary = db_one(
    'SELECT COUNT(*) AS product_count,
            COALESCE(SUM(quantity), 0) AS unit_count,
            COALESCE(SUM(quantity * unit_price), 0) AS stock_value,
            COALESCE(SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END), 0) AS low_count
     FROM products WHERE is_active = 1'
) ?? ['product_count' => 0, 'unit_count' => 0, 'stock_value' => 0, 'low_count' => 0];

$recent = db_all(
    "SELECT m.movement_type, m.quantity, m.note, m.created_at, p.name AS product_name, p.sku, u.name AS user_name
     FROM (
         SELECT 'in' AS movement_type, id, product_id, user_id, quantity, note, created_at FROM stock_in
         UNION ALL
         SELECT 'out' AS movement_type, id, product_id, user_id, quantity, note, created_at FROM stock_out
     ) AS m
     JOIN products p ON p.id = m.product_id
     JOIN users u ON u.id = m.user_id
     ORDER BY m.created_at DESC, m.id DESC
     LIMIT 6"
);

$lowStock = db_all(
    'SELECT name, sku, quantity, reorder_level FROM products
     WHERE is_active = 1 AND quantity <= reorder_level
     ORDER BY quantity ASC, name ASC LIMIT 5'
);

$pageTitle = 'Overview';
$activePage = 'dashboard';
require __DIR__ . '/header.php';
?>
<section class="page-heading heading-with-action">
    <div>
        <p class="eyebrow">SCHOOL KITCHEN</p>
        <h1>Stock overview</h1>
        <p class="muted">Current quantities, stock value and recent activity.</p>
    </div>
    <a class="button button-primary" href="stock_in.php"><span aria-hidden="true">＋</span> Receive stock</a>
</section>

<section class="stats-grid" aria-label="Inventory summary">
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">Active products</span><span class="stat-icon icon-lilac">▦</span></div>
        <strong class="stat-value"><?= number_format((int) $summary['product_count']) ?></strong>
        <span class="stat-foot">Active items</span>
    </article>
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">Units on hand</span><span class="stat-icon icon-blue">◫</span></div>
        <strong class="stat-value"><?= number_format((int) $summary['unit_count']) ?></strong>
        <span class="stat-foot">Across the kitchen</span>
    </article>
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">Stock value</span><span class="stat-icon icon-green">◈</span></div>
        <strong class="stat-value stat-money"><?= e(format_money($summary['stock_value'])) ?></strong>
        <span class="stat-foot">Using current unit prices</span>
    </article>
    <article class="stat-card">
        <div class="stat-top">        <span class="stat-label">Low stock</span><span class="stat-icon icon-amber">!</span></div>
        <strong class="stat-value"><?= number_format((int) $summary['low_count']) ?></strong>
        <span class="stat-foot">At or below reorder level</span>
    </article>
</section>

<div class="content-grid">
    <section class="panel activity-panel">
        <div class="panel-heading">
            <div><h2>Recent movements</h2><p class="muted">Latest stock received and issued.</p></div>
            <a class="text-link" href="reports.php">View report <span aria-hidden="true">→</span></a>
        </div>
        <?php if (!$recent): ?>
            <div class="empty-state"><span class="empty-icon">↔</span><strong>No movements recorded</strong><p>Stock received or issued will appear here.</p><a class="text-link" href="stock_in.php">Record stock received →</a></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Product</th><th>Change</th><th>By</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $movement): ?>
                        <tr>
                            <td><strong><?= e($movement['product_name']) ?></strong><small class="table-sub"><?= e($movement['sku']) ?></small></td>
                            <td><span class="movement-pill <?= $movement['movement_type'] === 'in' ? 'movement-in' : 'movement-out' ?>"><?= $movement['movement_type'] === 'in' ? '+' : '−' ?><?= number_format((int) $movement['quantity']) ?></span></td>
                            <td><?= e($movement['user_name']) ?></td>
                            <td class="table-muted"><?= e(date('M j, g:i a', strtotime($movement['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel low-stock-panel">
        <div class="panel-heading">
            <div><h2>Below reorder level</h2><p class="muted">Items that may need replenishing.</p></div>
            <span class="small-count"><?= number_format((int) $summary['low_count']) ?></span>
        </div>
        <?php if (!$lowStock): ?>
            <div class="good-state"><span class="good-check">✓</span><strong>All items are above reorder level</strong><p>No products need replenishing right now.</p></div>
        <?php else: ?>
            <ul class="low-stock-list">
                <?php foreach ($lowStock as $product): ?>
                    <li><span class="product-dot"><?= e(strtoupper(substr($product['name'], 0, 1))) ?></span><span class="low-stock-name"><strong><?= e($product['name']) ?></strong><small><?= e($product['sku']) ?></small></span><span class="low-stock-qty"><?= number_format((int) $product['quantity']) ?><small>left</small></span></li>
                <?php endforeach; ?>
            </ul>
            <a class="button button-secondary button-wide" href="products.php">View products</a>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/footer.php'; ?>