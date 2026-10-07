<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$settings = [];
if (tableExists($conn, 'settings')) {
    $settings_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings");
    if ($settings_query) {
        while ($row = mysqli_fetch_assoc($settings_query)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}
$currency_symbol = $settings['currency_symbol'] ?? 'D';

$products_data = [];
$recent_movements = false;
$total_products = $total_stock_in = $total_stock_out = $total_remaining = $stock_value = 0;
$low_stock_count = $critical_stock_count = $out_of_stock_count = 0;

if (tableExists($conn, 'stock_products') && tableExists($conn, 'stock_movements')) {
    $stock_summary = mysqli_query($conn, "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.selling_price,
            COALESCE(p.low_stock_threshold, 10) AS low_stock_threshold,
            COALESCE(SUM(CASE WHEN sm.movement_type IN ('received', 'received_from_warehouse') THEN sm.quantity ELSE 0 END), 0) AS stock_in,
            COALESCE(SUM(CASE WHEN sm.movement_type IN ('sold', 'transferred', 'released_to_outlet', 'damaged') THEN sm.quantity ELSE 0 END), 0) AS stock_out,
            COALESCE(SUM(CASE
                WHEN sm.movement_type IN ('received', 'received_from_warehouse') THEN sm.quantity
                WHEN sm.movement_type IN ('sold', 'transferred', 'released_to_outlet', 'damaged') THEN -sm.quantity
                ELSE 0
            END), 0) AS remaining
        FROM stock_products p
        LEFT JOIN stock_movements sm ON p.id = sm.product_id
        WHERE p.is_active = 1
        GROUP BY p.id, p.name, p.sku, p.selling_price, p.low_stock_threshold
        ORDER BY remaining ASC, p.name ASC
    ");

    if ($stock_summary) {
        while ($row = mysqli_fetch_assoc($stock_summary)) {
            $row['stock_in'] = (int)$row['stock_in'];
            $row['stock_out'] = (int)$row['stock_out'];
            $row['remaining'] = (int)$row['remaining'];
            $row['selling_price'] = (float)$row['selling_price'];
            $row['low_stock_threshold'] = (int)$row['low_stock_threshold'];
            $products_data[] = $row;
            $total_products++;
            $total_stock_in += $row['stock_in'];
            $total_stock_out += $row['stock_out'];
            $total_remaining += $row['remaining'];
            $stock_value += ($row['remaining'] * $row['selling_price']);

            if ($row['remaining'] <= 0) {
                $out_of_stock_count++;
            } elseif ($row['remaining'] <= max(1, floor($row['low_stock_threshold'] / 2))) {
                $critical_stock_count++;
            } elseif ($row['remaining'] <= $row['low_stock_threshold']) {
                $low_stock_count++;
            }
        }
    }

    if (tableExists($conn, 'outlets') && tableExists($conn, 'users')) {
        $recent_movements = mysqli_query($conn, "
            SELECT sm.*, p.name AS product_name, o.name AS outlet_name, u.full_name AS user_name
            FROM stock_movements sm
            JOIN stock_products p ON sm.product_id = p.id
            JOIN outlets o ON sm.outlet_id = o.id
            LEFT JOIN users u ON sm.created_by = u.id
            ORDER BY sm.created_at DESC
            LIMIT 30
        ");
    }
} elseif (tableExists($conn, 'stock_products') && tableExists($conn, 'outlet_stock')) {
    $stock_summary = mysqli_query($conn, "
        SELECT p.id, p.name, p.sku, p.selling_price, COALESCE(p.low_stock_threshold, 10) AS low_stock_threshold,
               0 AS stock_in, 0 AS stock_out, COALESCE(SUM(os.quantity), 0) AS remaining
        FROM stock_products p
        LEFT JOIN outlet_stock os ON p.id = os.product_id
        WHERE p.is_active = 1
        GROUP BY p.id, p.name, p.sku, p.selling_price, p.low_stock_threshold
        ORDER BY remaining ASC, p.name ASC
    ");
    if ($stock_summary) {
        while ($row = mysqli_fetch_assoc($stock_summary)) {
            $row['remaining'] = (int)$row['remaining'];
            $products_data[] = $row;
            $total_products++;
            $total_remaining += $row['remaining'];
            $stock_value += ($row['remaining'] * (float)$row['selling_price']);
            if ($row['remaining'] <= 0) $out_of_stock_count++;
            elseif ($row['remaining'] <= max(1, floor(((int)$row['low_stock_threshold']) / 2))) $critical_stock_count++;
            elseif ($row['remaining'] <= (int)$row['low_stock_threshold']) $low_stock_count++;
        }
    }
}

$top_alerts = array_slice(array_filter($products_data, function ($item) {
    return (int)$item['remaining'] <= (int)$item['low_stock_threshold'];
}), 0, 10);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Dashboard - SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'Stock Dashboard', 'Stock in, stock out, remaining quantity, value, and alerts'); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-boxes"></i> Stock Dashboard</h3>
            <p class="text-muted mb-0">Merged from the Showroom stock dashboard and standardized for SIS.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="stock_receive.php" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> Receive Stock</a>
            <a href="stock_transfer.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left-right"></i> Transfer Stock</a>
            <a href="stock_reports.php" class="btn btn-outline-secondary"><i class="bi bi-file-bar-graph"></i> Reports</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">Products</small><h3><?php echo number_format($total_products); ?></h3><span class="badge bg-light text-dark border">Active stock items</span></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">Stock In</small><h3><?php echo number_format($total_stock_in); ?></h3><span class="badge bg-success-subtle text-success border">Received</span></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">Stock Out</small><h3><?php echo number_format($total_stock_out); ?></h3><span class="badge bg-danger-subtle text-danger border">Sold / transferred / damaged</span></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-muted">Remaining Value</small><h3><?php echo e($currency_symbol) . ' ' . number_format($stock_value, 2); ?></h3><span class="badge bg-info-subtle text-info border"><?php echo number_format($total_remaining); ?> units</span></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="alert alert-warning h-100"><strong><?php echo number_format($low_stock_count); ?></strong> low stock item(s)</div></div>
        <div class="col-md-4"><div class="alert alert-danger h-100"><strong><?php echo number_format($critical_stock_count); ?></strong> critical stock item(s)</div></div>
        <div class="col-md-4"><div class="alert alert-dark h-100"><strong><?php echo number_format($out_of_stock_count); ?></strong> out-of-stock item(s)</div></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-table"></i> Product Stock Summary</strong>
                    <span class="badge bg-secondary"><?php echo count($products_data); ?> rows</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th><th>SKU</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Remaining</th><th>Status</th><th class="text-end">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($products_data): foreach ($products_data as $item):
                                $remaining = (int)$item['remaining'];
                                $threshold = (int)$item['low_stock_threshold'];
                                $status = ['OK', 'success'];
                                if ($remaining <= 0) $status = ['Out', 'dark'];
                                elseif ($remaining <= max(1, floor($threshold / 2))) $status = ['Critical', 'danger'];
                                elseif ($remaining <= $threshold) $status = ['Low', 'warning'];
                            ?>
                                <tr>
                                    <td><strong><?php echo e($item['name']); ?></strong></td>
                                    <td><?php echo e($item['sku']); ?></td>
                                    <td class="text-end text-success fw-semibold"><?php echo number_format((int)$item['stock_in']); ?></td>
                                    <td class="text-end text-danger fw-semibold"><?php echo number_format((int)$item['stock_out']); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($remaining); ?></td>
                                    <td><span class="badge bg-<?php echo e($status[1]); ?>"><?php echo e($status[0]); ?></span></td>
                                    <td class="text-end"><?php echo e($currency_symbol) . ' ' . number_format($remaining * (float)$item['selling_price'], 2); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No stock data found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header"><strong><i class="bi bi-exclamation-triangle"></i> Top Stock Alerts</strong></div>
                <div class="card-body">
                    <?php if ($top_alerts): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_alerts as $item): ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div><strong><?php echo e($item['name']); ?></strong><br><small class="text-muted">Threshold: <?php echo number_format((int)$item['low_stock_threshold']); ?></small></div>
                                <span class="badge bg-danger"><?php echo number_format((int)$item['remaining']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No low stock alerts.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong><i class="bi bi-clock-history"></i> Recent Movements</strong></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                    <?php if ($recent_movements && mysqli_num_rows($recent_movements) > 0): while ($m = mysqli_fetch_assoc($recent_movements)): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <strong><?php echo e($m['product_name']); ?></strong>
                                <span class="badge bg-light text-dark border"><?php echo e($m['movement_type']); ?></span>
                            </div>
                            <small class="text-muted"><?php echo e($m['outlet_name']); ?> • Qty <?php echo number_format((int)$m['quantity']); ?> • <?php echo e(date('d/m/Y H:i', strtotime($m['created_at']))); ?></small>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="list-group-item text-muted">No recent movement found.</div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
