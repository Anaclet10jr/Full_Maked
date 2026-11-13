<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$today = date('Y-m-d');
$dateFrom = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$dateTo = trim((string) ($_GET['to'] ?? $today));
$error = '';

if (!valid_date($dateFrom) || !valid_date($dateTo) || $dateFrom > $dateTo) {
    $error = 'Choose a valid date range.';
    $dateFrom = date('Y-m-01');
    $dateTo = $today;
}

$totals = [
    'in' => ['entry_count' => 0, 'quantity_total' => 0, 'value_total' => 0],
    'out' => ['entry_count' => 0, 'quantity_total' => 0, 'value_total' => 0],
];

$summaryRows = db_all(
    "SELECT movement_type, COUNT(*) AS entry_count,
            COALESCE(SUM(quantity), 0) AS quantity_total,
            COALESCE(SUM(total_price), 0) AS value_total
     FROM (
         SELECT 'in' AS movement_type, movement_date, quantity, total_price FROM stock_in
         UNION ALL
         SELECT 'out' AS movement_type, movement_date, quantity, total_price FROM stock_out
     ) AS movements
     WHERE movement_date BETWEEN ? AND ?
     GROUP BY movement_type",
    'ss',
    $dateFrom,
    $dateTo
);
foreach ($summaryRows as $row) {
    $totals[$row['movement_type']] = $row;
}

$movements = db_all(
    "SELECT m.movement_type, m.movement_date, m.quantity, m.unit_price, m.total_price,
            m.note, m.created_at, p.name AS product_name, p.sku, u.name AS user_name
     FROM (
         SELECT 'in' AS movement_type, id, product_id, user_id, movement_date, quantity,
                unit_price, total_price, note, created_at FROM stock_in
         UNION ALL
         SELECT 'out' AS movement_type, id, product_id, user_id, movement_date, quantity,
                unit_price, total_price, note, created_at FROM stock_out
     ) AS m
     JOIN products p ON p.id = m.product_id
     JOIN users u ON u.id = m.user_id
     WHERE m.movement_date BETWEEN ? AND ?
     ORDER BY m.movement_date DESC, m.created_at DESC, m.id DESC
     LIMIT 500",
    'ss',
    $dateFrom,
    $dateTo
);

$pageTitle = 'Reports';
$activePage = 'reports';
require __DIR__ . '/header.php';
?>
<section class="page-heading heading-with-action">
    <div><p class="eyebrow">STOCK RECORDS</p><h1>Reports</h1><p class="muted">Review stock received, items issued and their recorded value.</p></div>
    <button class="button button-secondary print-button" type="button" onclick="window.print()">Print report</button>
</section>

<section class="panel report-filter">
    <form method="get" class="filter-form">
        <div><label for="from">From</label><input id="from" name="from" type="date" value="<?= e($dateFrom) ?>" required></div>
        <div><label for="to">To</label><input id="to" name="to" type="date" value="<?= e($dateTo) ?>" required></div>
        <button class="button button-primary" type="submit">Show report</button>
    </form>
    <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
</section>

<section class="stats-grid report-stats" aria-label="Movement totals">
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">Stock received</span><span class="stat-icon icon-green">↙</span></div>
        <strong class="stat-value"><?= number_format((int) $totals['in']['quantity_total']) ?></strong>
        <span class="stat-foot"><?= number_format((int) $totals['in']['entry_count']) ?> entries · <?= e(format_money($totals['in']['value_total'])) ?></span>
    </article>
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">Stock issued</span><span class="stat-icon icon-amber">↗</span></div>
        <strong class="stat-value"><?= number_format((int) $totals['out']['quantity_total']) ?></strong>
        <span class="stat-foot"><?= number_format((int) $totals['out']['entry_count']) ?> entries · <?= e(format_money($totals['out']['value_total'])) ?></span>
    </article>
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">Net quantity change</span><span class="stat-icon icon-blue">±</span></div>
        <strong class="stat-value"><?= number_format((int) $totals['in']['quantity_total'] - (int) $totals['out']['quantity_total']) ?></strong>
        <span class="stat-foot">For <?= e(date('M j, Y', strtotime($dateFrom))) ?> – <?= e(date('M j, Y', strtotime($dateTo))) ?></span>
    </article>
</section>

<section class="panel report-table-panel">
    <div class="panel-heading">
        <div><h2>Movement details</h2><p class="muted">Showing up to 500 entries, newest first.</p></div>
    </div>
    <?php if (!$movements): ?>
        <div class="empty-state"><strong>No stock movements for these dates</strong><p>Change the date range or record a stock-in or stock-out entry.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Unit price</th><th>Total</th><th>Recorded by</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $movement): ?>
                <tr>
                    <td><?= e(date('M j, Y', strtotime($movement['movement_date']))) ?></td>
                    <td><strong><?= e($movement['product_name']) ?></strong><small class="table-sub"><?= e($movement['sku']) ?></small></td>
                    <td><span class="movement-pill <?= $movement['movement_type'] === 'in' ? 'movement-in' : 'movement-out' ?>"><?= $movement['movement_type'] === 'in' ? 'In' : 'Out' ?></span></td>
                    <td><?= number_format((int) $movement['quantity']) ?></td>
                    <td><?= e(format_money($movement['unit_price'])) ?></td>
                    <td><?= e(format_money($movement['total_price'])) ?></td>
                    <td><?= e($movement['user_name']) ?></td>
                    <td><?= e($movement['note'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
