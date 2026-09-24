<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$message = '';
$message_type = '';

// Get all outlets
$outlets = mysqli_query($conn, "SELECT * FROM outlets WHERE is_active = 1 ORDER BY name");

// Get all products
$products = mysqli_query($conn, "SELECT * FROM stock_products WHERE is_active = 1 ORDER BY name");

// Handle adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $outlet_id = (int)$_POST['outlet_id'];
    $product_id = (int)$_POST['product_id'];
    $adjustment_type = mysqli_real_escape_string($conn, (string)($_POST['adjustment_type'] ?? ''));
    $quantity = (int)$_POST['quantity'];
    $reason = mysqli_real_escape_string($conn, (string)($_POST['reason'] ?? ''));
    $notes = mysqli_real_escape_string($conn, (string)($_POST['notes'] ?? ''));
    
    // Get current stock
    $stock = mysqli_query($conn, "
        SELECT os.*, b.batch_number 
        FROM outlet_stock os 
        LEFT JOIN stock_batches b ON os.batch_id = b.id
        WHERE os.outlet_id = $outlet_id AND os.product_id = $product_id 
        LIMIT 1
    ");
    
    if (mysqli_num_rows($stock) == 0) {
        $message = "Product not found in this outlet";
        $message_type = 'danger';
    } else {
        $current = mysqli_fetch_assoc($stock);
        $before = $current['quantity'];
        
        if ($adjustment_type == 'remove' && $quantity > $before) {
            $message = "Cannot remove more than available stock";
            $message_type = 'danger';
        } else {
            mysqli_begin_transaction($conn);
            
            try {
                // Calculate new quantity
                if ($adjustment_type == 'add') {
                    $after = $before + $quantity;
                    $movement_type = 'adjusted_add';
                    $action_text = 'added';
                } else {
                    $after = $before - $quantity;
                    $movement_type = 'adjusted_remove';
                    $action_text = 'removed';
                }
                
                // Update stock
                mysqli_query($conn, "UPDATE outlet_stock SET quantity = $after WHERE id = {$current['id']}");
                
                // Record movement
                mysqli_query($conn, "INSERT INTO stock_movements (
                    outlet_id, product_id, batch_id, movement_type, quantity, 
                    before_quantity, after_quantity, notes, created_by
                ) VALUES (
                    $outlet_id, $product_id, {$current['batch_id']}, '$movement_type', 
                    $quantity, $before, $after, 
                    'Adjustment: $reason - $notes', {$_SESSION['user_id']}
                )");
                
                mysqli_commit($conn);
                $message = "Stock adjusted successfully! $quantity items $action_text.";
                $message_type = 'success';
                logAction($conn, 'ADJUST_STOCK');
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Get recent adjustments
$adjustments = mysqli_query($conn, "
    SELECT sm.*, o.name as outlet_name, p.name as product_name, u.full_name as user_name
    FROM stock_movements sm
    JOIN outlets o ON sm.outlet_id = o.id
    JOIN stock_products p ON sm.product_id = p.id
    JOIN users u ON sm.created_by = u.id
    WHERE sm.movement_type IN ('adjusted_add', 'adjusted_remove')
    ORDER BY sm.created_at DESC
    LIMIT 50
");

// Get summary stats
$total_adjustments = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty
    FROM stock_movements 
    WHERE movement_type IN ('adjusted_add', 'adjusted_remove')
    AND MONTH(created_at) = MONTH(CURDATE())
")) ?? ['count' => 0, 'total_qty' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adjust Stock - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
    
    

    
    <div class="main-content">
        <div class="page-header">
            <h3><i class="bi bi-pencil-square sis-u-002e4b71"></i> Stock Adjustments</h3>
            <span class="date-badge"><?php echo date('l, d M Y'); ?></span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Adjustments This Month</h6>
                <h2><?php echo e($total_adjustments['count']); ?></h2>
                <small>Total transactions</small>
            </div>
            <div class="stat-card">
                <h6>Quantity Adjusted</h6>
                <h2><?php echo e($total_adjustments['total_qty']); ?></h2>
                <small>Units affected</small>
            </div>
            <div class="stat-card">
                <h6>Net Effect</h6>
                <?php
                $net = mysqli_fetch_assoc(mysqli_query($conn, "
                    SELECT 
                        SUM(CASE WHEN movement_type = 'adjusted_add' THEN quantity ELSE 0 END) as total_added,
                        SUM(CASE WHEN movement_type = 'adjusted_remove' THEN quantity ELSE 0 END) as total_removed
                    FROM stock_movements 
                    WHERE MONTH(created_at) = MONTH(CURDATE())
                "));
                $net_effect = ($net['total_added'] ?? 0) - ($net['total_removed'] ?? 0);
                ?>
                <h2 class="<?php echo $net_effect >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $net_effect >= 0 ? '+' : ''; ?><?php echo $net_effect; ?>
                </h2>
                <small>Added - Removed</small>
            </div>
        </div>

        <?php if($message): ?>
        <div class="alert alert-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-plus-circle"></i> New Adjustment
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Outlet</label>
                            <select class="form-select" name="outlet_id" required>
                                <option value="">Select Outlet</option>
                                <?php while($o = mysqli_fetch_assoc($outlets)): ?>
                                <option value="<?php echo e($o['id']); ?>"><?php echo e($o['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select class="form-select" name="product_id" required>
                                <option value="">Select Product</option>
                                <?php while($p = mysqli_fetch_assoc($products)): ?>
                                <option value="<?php echo e($p['id']); ?>"><?php echo e($p['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Adjustment Type</label>
                            <select class="form-select" name="adjustment_type" id="adjustType" required>
                                <option value="add">Add Stock (Found, Return, Correction)</option>
                                <option value="remove">Remove Stock (Damaged, Lost, Expired)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" min="1" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <select class="form-select" name="reason" required>
                                <option value="">Select Reason</option>
                                <option value="Damaged Goods">Damaged Goods</option>
                                <option value="Expired Products">Expired Products</option>
                                <option value="Inventory Count Correction">Inventory Count Correction</option>
                                <option value="Customer Return">Customer Return</option>
                                <option value="Theft/Loss">Theft/Loss</option>
                                <option value="Quality Check">Quality Check</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>

                        <button type="submit" name="adjust_stock" class="btn btn-primary w-100" id="submitBtn">
                            <i class="bi bi-check-circle"></i> Apply Adjustment
                        </button>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-clock-history"></i> Recent Adjustments</span>
                    <span class="badge bg-secondary"><?php echo mysqli_num_rows($adjustments); ?> records</span>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Outlet</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Reason</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($adjustments) > 0): ?>
                                <?php while($a = mysqli_fetch_assoc($adjustments)): ?>
                                <tr>
                                    <td><?php echo date('d/m H:i', strtotime($a['created_at'])); ?></td>
                                    <td><?php echo e($a['outlet_name']); ?></td>
                                    <td><?php echo e($a['product_name']); ?></td>
                                    <td>
                                        <span class="badge-<?php echo $a['movement_type'] == 'adjusted_add' ? 'add' : 'remove'; ?>">
                                            <?php echo $a['movement_type'] == 'adjusted_add' ? '+ Add' : '- Remove'; ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold"><?php echo e($a['quantity']); ?></td>
                                    <td><small><?php echo substr($a['notes'] ?? '', 0, 30); ?></small></td>
                                    <td><?php echo e($a['user_name']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox sis-u-1e52ae17"></i>
                                        <p class="mt-2">No adjustments found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> Adjustment Guidelines
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6><i class="bi bi-plus-circle text-success"></i> Add Stock When:</h6>
                        <ul class="small">
                            <li>Found extra items during count</li>
                            <li>Customer returns items</li>
                            <li>Correcting previous errors</li>
                            <li>Sample/demo items</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="bi bi-dash-circle text-danger"></i> Remove Stock When:</h6>
                        <ul class="small">
                            <li>Items damaged or broken</li>
                            <li>Products expired</li>
                            <li>Theft or loss</li>
                            <li>Quality control rejects</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="bi bi-shield-check text-primary"></i> Approval Required:</h6>
                        <ul class="small">
                            <li>Large adjustments > 50 units</li>
                            <li>High-value items</li>
                            <li>Theft/loss cases</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('adjustType').addEventListener('change', function() {
            const btn = document.getElementById('submitBtn');
            if (this.value === 'add') {
                btn.classList.remove('btn-remove');
                btn.classList.add('btn-primary');
                btn.innerHTML = '<i class="bi bi-plus-circle"></i> Add Stock';
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-remove');
                btn.innerHTML = '<i class="bi bi-dash-circle"></i> Remove Stock';
            }
        });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>