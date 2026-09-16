<?php
require_once '../config.php';
requireRole('supervisor');

$outlet_id = $_SESSION['outlet_id'];
$outlet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM outlets WHERE id = $outlet_id"));

// Get stock for this outlet
$stock_query = "
    SELECT os.*, p.name as product_name, p.sku, p.selling_price, p.low_stock_threshold,
           c.name as category_name, c.color as category_color,
           b.batch_number, b.expiry_date
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
    LEFT JOIN stock_categories c ON p.category_id = c.id
    LEFT JOIN stock_batches b ON os.batch_id = b.id
    WHERE os.outlet_id = $outlet_id
    ORDER BY 
        CASE 
            WHEN os.quantity <= p.low_stock_threshold/2 THEN 0
            WHEN os.quantity <= p.low_stock_threshold THEN 1
            ELSE 2
        END,
        p.name
";

$stock_result = mysqli_query($conn, $stock_query);

// Get summary
$summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(DISTINCT product_id) as total_products,
        SUM(quantity) as total_items,
        SUM(quantity * selling_price) as total_value,
        SUM(CASE WHEN quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
    WHERE os.outlet_id = $outlet_id
"));

// Get recent movements
$movements = mysqli_query($conn, "
    SELECT sm.*, p.name as product_name, u.full_name as user_name
    FROM stock_movements sm
    JOIN stock_products p ON sm.product_id = p.id
    JOIN users u ON sm.created_by = u.id
    WHERE sm.outlet_id = $outlet_id
    ORDER BY sm.created_at DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - <?php echo $outlet['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

    <div class="page-header">
        <div class="container">
            <h4><?php echo $outlet['name']; ?> - Stock Management</h4>
        </div>
    </div>

    <div class="container">
        
        <div class="stat-grid">
            <div class="stat-card">
                <h6>Total Products</h6>
                <h2><?php echo $summary['total_products'] ?? 0; ?></h2>
            </div>
            <div class="stat-card">
                <h6>Total Items</h6>
                <h2><?php echo number_format($summary['total_items'] ?? 0); ?></h2>
            </div>
            <div class="stat-card">
                <h6>Stock Value</h6>
                <h2>D <?php echo number_format($summary['total_value'] ?? 0, 2); ?></h2>
            </div>
            <div class="stat-card">
                <h6>Low Stock</h6>
                <h2><?php echo $summary['low_stock_count'] ?? 0; ?></h2>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-box"></i> Current Stock
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Batch</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($item = mysqli_fetch_assoc($stock_result)): 
                            $row_class = '';
                            $status_badge = '';
                            
                            if ($item['quantity'] == 0) {
                                $row_class = 'stock-critical';
                                $status_badge = '<span class="badge badge-critical">Out</span>';
                            } elseif ($item['quantity'] <= $item['low_stock_threshold']/2) {
                                $row_class = 'stock-critical';
                                $status_badge = '<span class="badge badge-critical">Critical</span>';
                            } elseif ($item['quantity'] <= $item['low_stock_threshold']) {
                                $row_class = 'stock-low';
                                $status_badge = '<span class="badge badge-low">Low</span>';
                            } else {
                                $status_badge = '<span class="badge badge-normal">Good</span>';
                            }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td>
                                <strong><?php echo $item['product_name']; ?></strong>
                                <small class="d-block text-muted"><?php echo $item['sku']; ?></small>
                            </td>
                            <td>
                                <span class="sis-dynamic-color-text" data-color="<?php echo e($item['category_color']); ?>">
                                    <?php echo $item['category_name']; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $item['batch_number'] ?? '—'; ?>
                                <?php if($item['expiry_date']): ?>
                                <br><small>Exp: <?php echo date('d/m/Y', strtotime($item['expiry_date'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold"><?php echo $item['quantity']; ?></td>
                            <td>D <?php echo number_format($item['selling_price'], 2); ?></td>
                            <td><?php echo $status_badge; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Recent Movements
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($m = mysqli_fetch_assoc($movements)): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                            <td><?php echo $m['product_name']; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $m['movement_type'] == 'received' ? 'success' : 
                                        ($m['movement_type'] == 'sold' ? 'primary' : 
                                        ($m['movement_type'] == 'transferred' ? 'info' : 'warning')); 
                                ?>">
                                    <?php echo ucfirst($m['movement_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $m['quantity']; ?></td>
                            <td><?php echo $m['user_name']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>