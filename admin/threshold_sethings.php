<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$message = '';
$message_type = '';

// Get all products with their current thresholds
$products = mysqli_query($conn, "
    SELECT p.*, c.name as category_name,
           (SELECT AVG(quantity) FROM outlet_stock WHERE product_id = p.id) as avg_stock,
           (SELECT COUNT(*) FROM outlet_stock WHERE product_id = p.id AND quantity <= p.low_stock_threshold) as low_stock_count
    FROM stock_products p
    LEFT JOIN stock_categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY 
        CASE 
            WHEN (SELECT COUNT(*) FROM outlet_stock WHERE product_id = p.id AND quantity <= p.low_stock_threshold) > 0 THEN 0
            ELSE 1
        END,
        p.name
");

// Get threshold recommendations based on sales velocity
$recommendations = mysqli_query($conn, "
    SELECT 
        p.id,
        p.name,
        p.low_stock_threshold as current_threshold,
        AVG(sm.quantity) as avg_daily_sales,
        CEIL(AVG(sm.quantity) * 7) as recommended_threshold
    FROM stock_products p
    LEFT JOIN stock_movements sm ON p.id = sm.product_id 
        AND sm.movement_type = 'sold'
        AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE p.is_active = 1
    GROUP BY p.id
    HAVING avg_daily_sales > 0
    ORDER BY avg_daily_sales DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Threshold Settings - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
<div class="sis-module-hero">
    <div>
        <h2><i class="bi bi-sliders text-danger"></i> Threshold Management</h2>
        <p>Review stock thresholds, low-stock risks, and suggested reorder levels.</p>
    </div>
    <div class="sis-module-hero-icon"><i class="bi bi-box-seam"></i></div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card sis-record-panel">
            <div class="card-header"><i class="bi bi-table text-danger"></i> Active Product Thresholds</div>
            <div class="card-body p-0">
                <div class="table-responsive sis-table-scroll">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Product</th><th>Category</th><th>Current Threshold</th><th>Average Stock</th><th>Low Stock Outlets</th></tr></thead>
                        <tbody>
                            <?php if (!$products || mysqli_num_rows($products) === 0): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No active products found.</td></tr>
                            <?php else: while($p = mysqli_fetch_assoc($products)): ?>
                                <tr>
                                    <td><strong><?php echo e($p['name']); ?></strong></td>
                                    <td><?php echo e($p['category_name'] ?: '—'); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo (int)$p['low_stock_threshold']; ?></span></td>
                                    <td><?php echo number_format((float)($p['avg_stock'] ?? 0), 1); ?></td>
                                    <td><span class="badge <?php echo ((int)$p['low_stock_count'] > 0) ? 'bg-danger' : 'bg-success'; ?>"><?php echo (int)$p['low_stock_count']; ?></span></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card sis-record-panel">
            <div class="card-header"><i class="bi bi-lightbulb text-danger"></i> Suggested Thresholds</div>
            <div class="card-body p-0">
                <div class="table-responsive sis-table-scroll">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Product</th><th>Current</th><th>Suggested</th></tr></thead>
                        <tbody>
                            <?php if (!$recommendations || mysqli_num_rows($recommendations) === 0): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No recommendations yet.</td></tr>
                            <?php else: while($r = mysqli_fetch_assoc($recommendations)): ?>
                                <tr>
                                    <td><?php echo e($r['name']); ?></td>
                                    <td><?php echo (int)$r['current_threshold']; ?></td>
                                    <td><strong class="text-danger"><?php echo (int)$r['recommended_threshold']; ?></strong></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php sisRenderLayoutEnd(); ?>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
</body>
</html>