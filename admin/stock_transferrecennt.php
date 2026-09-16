<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Get counts for sidebar
$outlets_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM outlets WHERE is_active = 1"))['count'] ?? 0;
$agents_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND is_active = 1"))['count'] ?? 0;
$supervisors_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'supervisor' AND is_active = 1"))['count'] ?? 0;
$products_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_products WHERE is_active = 1"))['count'] ?? 0;
$pending_transfers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_transfers WHERE status = 'pending'"))['count'] ?? 0;

$message = '';
$message_type = '';

// Get all outlets
$outlets = mysqli_query($conn, "SELECT * FROM outlets WHERE is_active = 1 ORDER BY name");

// Get products with stock and threshold information
$products = mysqli_query($conn, "
    SELECT 
        p.*,
        SUM(os.quantity) as total_available,
        MIN(os.quantity) as min_outlet_stock,
        MAX(os.quantity) as max_outlet_stock,
        COUNT(DISTINCT os.outlet_id) as outlets_with_stock
    FROM stock_products p
    LEFT JOIN outlet_stock os ON p.id = os.product_id
    WHERE p.is_active = 1
    GROUP BY p.id
    HAVING total_available > 0
    ORDER BY p.name
");

// Handle transfer request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_transfer'])) {
    $from_outlet = (int)$_POST['from_outlet'];
    $to_outlet = (int)$_POST['to_outlet'];
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $notes = mysqli_real_escape_string($conn, (string)($_POST['notes'] ?? ''));
    $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
    
    if ($from_outlet == $to_outlet) {
        $message = "Source and destination outlets cannot be the same";
        $message_type = 'danger';
    } elseif ($quantity <= 0) {
        $message = "Quantity must be greater than zero";
        $message_type = 'danger';
    } else {
        // Check available stock in source outlet
        $check = mysqli_query($conn, "
            SELECT os.*, p.low_stock_threshold, p.name as product_name
            FROM outlet_stock os 
            JOIN stock_products p ON os.product_id = p.id
            WHERE os.outlet_id = $from_outlet 
            AND os.product_id = $product_id 
            AND os.quantity >= $quantity
            ORDER BY os.quantity DESC
            LIMIT 1
        ");
        
        if (mysqli_num_rows($check) == 0) {
            $message = "Insufficient stock in source outlet";
            $message_type = 'danger';
        } else {
            $stock = mysqli_fetch_assoc($check);
            $remaining = $stock['quantity'] - $quantity;
            
            // Check if transfer will cause low stock in source
            $low_stock_warning = "";
            if ($remaining <= $stock['low_stock_threshold'] && $remaining > 0) {
                $low_stock_warning = "Warning: After transfer, source outlet will have only $remaining units left (below threshold of {$stock['low_stock_threshold']}).";
            } elseif ($remaining == 0) {
                $low_stock_warning = "Warning: After transfer, source outlet will have 0 units left (out of stock).";
            }
            
            mysqli_begin_transaction($conn);
            
            try {
                // Insert transfer request
                $status = $requires_approval ? 'pending' : 'approved';
                $insert = "INSERT INTO stock_transfers (
                    from_outlet_id, to_outlet_id, product_id, batch_id, quantity, 
                    requested_by, request_notes, status, requested_at
                ) VALUES (
                    $from_outlet, $to_outlet, $product_id, {$stock['batch_id']}, $quantity,
                    {$_SESSION['user_id']}, '$notes', '$status', NOW()
                )";
                
                if (!mysqli_query($conn, $insert)) {
                    throw new Exception("Error creating transfer: " . mysqli_error($conn));
                }
                
                $transfer_id = mysqli_insert_id($conn);
                
                // If auto-approved, process immediately
                if (!$requires_approval) {
                    // Reduce from source
                    $new_from_qty = $stock['quantity'] - $quantity;
                    mysqli_query($conn, "UPDATE outlet_stock SET quantity = $new_from_qty WHERE id = {$stock['id']}");
                    
                    // Check if this creates a low stock situation and create alert
                    if ($new_from_qty <= $stock['low_stock_threshold']) {
                        // Check if alert already exists
                        $check_alert = mysqli_query($conn, "
                            SELECT id FROM stock_alerts 
                            WHERE outlet_id = $from_outlet 
                            AND product_id = $product_id 
                            AND is_resolved = 0
                        ");
                        
                        if (mysqli_num_rows($check_alert) == 0) {
                            mysqli_query($conn, "
                                INSERT INTO stock_alerts (outlet_id, product_id, current_quantity, threshold) 
                                VALUES ($from_outlet, $product_id, $new_from_qty, {$stock['low_stock_threshold']})
                            ");
                        }
                    }
                    
                    // Add to destination
                    $dest_check = mysqli_query($conn, "
                        SELECT id, quantity FROM outlet_stock 
                        WHERE outlet_id = $to_outlet 
                        AND product_id = $product_id 
                        AND batch_id = {$stock['batch_id']}
                    ");
                    
                    if (mysqli_num_rows($dest_check) > 0) {
                        $dest = mysqli_fetch_assoc($dest_check);
                        $new_to_qty = $dest['quantity'] + $quantity;
                        mysqli_query($conn, "UPDATE outlet_stock SET quantity = $new_to_qty WHERE id = {$dest['id']}");
                        $to_before = $dest['quantity'];
                    } else {
                        mysqli_query($conn, "INSERT INTO outlet_stock (outlet_id, product_id, batch_id, quantity) 
                                            VALUES ($to_outlet, $product_id, {$stock['batch_id']}, $quantity)");
                        $to_before = 0;
                    }
                    
                    // Record movements
                    mysqli_query($conn, "INSERT INTO stock_movements (
                        outlet_id, product_id, batch_id, movement_type, quantity, 
                        before_quantity, after_quantity, notes, created_by
                    ) VALUES (
                        $from_outlet, $product_id, {$stock['batch_id']}, 'transferred_out', $quantity, 
                        {$stock['quantity']}, $new_from_qty, 
                        'Transferred to outlet $to_outlet: $notes', {$_SESSION['user_id']}
                    )");
                    
                    mysqli_query($conn, "INSERT INTO stock_movements (
                        outlet_id, product_id, batch_id, movement_type, quantity, 
                        before_quantity, after_quantity, notes, created_by
                    ) VALUES (
                        $to_outlet, $product_id, {$stock['batch_id']}, 'transferred_in', $quantity, 
                        $to_before, " . ($to_before + $quantity) . ", 
                        'Received from outlet $from_outlet: $notes', {$_SESSION['user_id']}
                    )");
                    
                    // Update transfer as completed
                    mysqli_query($conn, "UPDATE stock_transfers SET 
                        status = 'received',
                        approved_by = {$_SESSION['user_id']},
                        approved_at = NOW(),
                        received_by = {$_SESSION['user_id']},
                        received_at = NOW()
                        WHERE id = $transfer_id");
                    
                    $message = "Stock transferred successfully!";
                    if ($low_stock_warning) {
                        $message .= " " . $low_stock_warning;
                        $message_type = 'warning';
                    } else {
                        $message_type = 'success';
                    }
                } else {
                    $message = "Transfer request submitted for approval. Reference ID: #$transfer_id";
                    $message_type = 'success';
                }
                
                mysqli_commit($conn);
                logAction($conn, 'TRANSFER_REQUEST', $transfer_id);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Handle approval/rejection
if (isset($_GET['approve']) || isset($_GET['reject'])) {
    $transfer_id = (int)$_GET['id'];
    $action = isset($_GET['approve']) ? 'approve' : 'reject';
    
    $transfer = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT st.*, os.quantity as available_qty, os.id as stock_id,
               p.low_stock_threshold
        FROM stock_transfers st
        JOIN outlet_stock os ON st.from_outlet_id = os.outlet_id 
            AND st.product_id = os.product_id AND st.batch_id = os.batch_id
        JOIN stock_products p ON st.product_id = p.id
        WHERE st.id = $transfer_id
    "));
    
    if ($action == 'approve') {
        if ($transfer['available_qty'] < $transfer['quantity']) {
            $message = "Insufficient stock available. Requested: {$transfer['quantity']}, Available: {$transfer['available_qty']}";
            $message_type = 'danger';
        } else {
            mysqli_begin_transaction($conn);
            
            try {
                // Reduce from source
                $new_from_qty = $transfer['available_qty'] - $transfer['quantity'];
                mysqli_query($conn, "UPDATE outlet_stock SET quantity = $new_from_qty WHERE id = {$transfer['stock_id']}");
                
                // Check for low stock alert
                if ($new_from_qty <= $transfer['low_stock_threshold']) {
                    $check_alert = mysqli_query($conn, "
                        SELECT id FROM stock_alerts 
                        WHERE outlet_id = {$transfer['from_outlet_id']} 
                        AND product_id = {$transfer['product_id']} 
                        AND is_resolved = 0
                    ");
                    
                    if (mysqli_num_rows($check_alert) == 0) {
                        mysqli_query($conn, "
                            INSERT INTO stock_alerts (outlet_id, product_id, current_quantity, threshold) 
                            VALUES ({$transfer['from_outlet_id']}, {$transfer['product_id']}, $new_from_qty, {$transfer['low_stock_threshold']})
                        ");
                    }
                }
                
                // Add to destination
                $dest_check = mysqli_query($conn, "
                    SELECT id, quantity FROM outlet_stock 
                    WHERE outlet_id = {$transfer['to_outlet_id']} 
                    AND product_id = {$transfer['product_id']} 
                    AND batch_id = {$transfer['batch_id']}
                ");
                
                if (mysqli_num_rows($dest_check) > 0) {
                    $dest = mysqli_fetch_assoc($dest_check);
                    $new_to_qty = $dest['quantity'] + $transfer['quantity'];
                    mysqli_query($conn, "UPDATE outlet_stock SET quantity = $new_to_qty WHERE id = {$dest['id']}");
                    $to_before = $dest['quantity'];
                } else {
                    mysqli_query($conn, "INSERT INTO outlet_stock (outlet_id, product_id, batch_id, quantity) 
                                        VALUES ({$transfer['to_outlet_id']}, {$transfer['product_id']}, {$transfer['batch_id']}, {$transfer['quantity']})");
                    $to_before = 0;
                }
                
                // Record movements
                mysqli_query($conn, "INSERT INTO stock_movements (
                    outlet_id, product_id, batch_id, movement_type, quantity, 
                    before_quantity, after_quantity, notes, created_by
                ) VALUES (
                    {$transfer['from_outlet_id']}, {$transfer['product_id']}, {$transfer['batch_id']}, 'transferred_out', {$transfer['quantity']}, 
                    {$transfer['available_qty']}, $new_from_qty, 
                    'Approved transfer to outlet {$transfer['to_outlet_id']}', {$_SESSION['user_id']}
                )");
                
                mysqli_query($conn, "INSERT INTO stock_movements (
                    outlet_id, product_id, batch_id, movement_type, quantity, 
                    before_quantity, after_quantity, notes, created_by
                ) VALUES (
                    {$transfer['to_outlet_id']}, {$transfer['product_id']}, {$transfer['batch_id']}, 'transferred_in', {$transfer['quantity']}, 
                    $to_before, " . ($to_before + $transfer['quantity']) . ", 
                    'Received from outlet {$transfer['from_outlet_id']}', {$_SESSION['user_id']}
                )");
                
                // Update transfer
                mysqli_query($conn, "UPDATE stock_transfers SET 
                    status = 'approved',
                    approved_by = {$_SESSION['user_id']},
                    approved_at = NOW()
                    WHERE id = $transfer_id");
                
                mysqli_commit($conn);
                $message = "Transfer approved and processed!";
                $message_type = 'success';
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } else {
        // Reject
        mysqli_query($conn, "UPDATE stock_transfers SET 
            status = 'rejected',
            approved_by = {$_SESSION['user_id']},
            approved_at = NOW()
            WHERE id = $transfer_id");
        
        $message = "Transfer request rejected";
        $message_type = 'warning';
    }
}

// Handle receipt confirmation
if (isset($_GET['receive'])) {
    $transfer_id = (int)$_GET['id'];
    
    mysqli_query($conn, "UPDATE stock_transfers SET 
        status = 'received',
        received_by = {$_SESSION['user_id']},
        received_at = NOW()
        WHERE id = $transfer_id");
    
    $message = "Receipt confirmed!";
    $message_type = 'success';
}

// Get transfers list
$transfers = mysqli_query($conn, "
    SELECT st.*, 
           p.name as product_name,
           p.low_stock_threshold,
           from_o.name as from_outlet_name,
           to_o.name as to_outlet_name,
           u1.full_name as requested_by_name,
           u2.full_name as approved_by_name,
           u3.full_name as received_by_name
    FROM stock_transfers st
    JOIN stock_products p ON st.product_id = p.id
    JOIN outlets from_o ON st.from_outlet_id = from_o.id
    JOIN outlets to_o ON st.to_outlet_id = to_o.id
    LEFT JOIN users u1 ON st.requested_by = u1.id
    LEFT JOIN users u2 ON st.approved_by = u2.id
    LEFT JOIN users u3 ON st.received_by = u3.id
    ORDER BY st.requested_at DESC
    LIMIT 50
");

// Get threshold settings for products
$threshold_settings = mysqli_query($conn, "
    SELECT id, name, low_stock_threshold, reorder_quantity
    FROM stock_products
    WHERE is_active = 1
    ORDER BY name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Stock - Comium GM</title>
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
            <h3><i class="bi bi-arrow-left-right sis-u-002e4b71"></i> Transfer Stock</h3>
            <span class="date-badge"><?php echo date('l, d M Y'); ?></span>
        </div>

        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-sliders2"></i> Current Threshold Settings
            </div>
            <div class="card-body">
                <div class="row">
                    <?php while($t = mysqli_fetch_assoc($threshold_settings)): ?>
                    <div class="col-md-3 mb-2">
                        <span class="fw-bold"><?php echo $t['name']; ?>:</span>
                        <span class="threshold-badge">Threshold: <?php echo $t['low_stock_threshold']; ?></span>
                        <span class="threshold-badge">Reorder: <?php echo $t['reorder_quantity']; ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>
                <small class="text-muted">Products will trigger alerts when stock falls below threshold</small>
            </div>
        </div>

        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-plus-circle"></i> New Transfer
                </div>
                <div class="card-body">
                    <form method="POST" id="transferForm">
                        <div class="mb-3">
                            <label class="form-label">From Outlet</label>
                            <select class="form-select" name="from_outlet" id="from_outlet" required>
                                <option value="">Select Source</option>
                                <?php while($o = mysqli_fetch_assoc($outlets)): ?>
                                <option value="<?php echo $o['id']; ?>"><?php echo $o['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">To Outlet</label>
                            <select class="form-select" name="to_outlet" id="to_outlet" required>
                                <option value="">Select Destination</option>
                                <?php 
                                mysqli_data_seek($outlets, 0);
                                while($o = mysqli_fetch_assoc($outlets)): 
                                ?>
                                <option value="<?php echo $o['id']; ?>"><?php echo $o['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select class="form-select" name="product_id" id="productSelect" required>
                                <option value="">Select Product</option>
                                <?php while($p = mysqli_fetch_assoc($products)): ?>
                                <option value="<?php echo $p['id']; ?>" 
                                        data-max="<?php echo $p['total_available']; ?>"
                                        data-threshold="<?php echo $p['low_stock_threshold']; ?>">
                                    <?php echo $p['name']; ?> 
                                    (Available: <?php echo $p['total_available']; ?>)
                                    <?php if($p['total_available'] <= $p['low_stock_threshold']): ?>
                                    ⚠️ LOW STOCK
                                    <?php endif; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <div id="stockInfo" class="mt-1 small"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" min="1" required>
                            <div id="thresholdWarning" class="warning-text sis-u-224b51a7"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="requires_approval" id="requiresApproval" value="1">
                            <label class="form-check-label" for="requiresApproval">Requires Approval</label>
                        </div>

                        <button type="submit" name="request_transfer" class="btn btn-primary w-100">
                            <i class="bi bi-send"></i> Submit Transfer
                        </button>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list"></i> Recent Transfers</span>
                    <span class="badge bg-secondary"><?php echo mysqli_num_rows($transfers); ?> records</span>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>From → To</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($transfers) > 0): ?>
                                <?php while($t = mysqli_fetch_assoc($transfers)): ?>
                                <tr>
                                    <td><?php echo date('d/m H:i', strtotime($t['requested_at'])); ?></td>
                                    <td><?php echo $t['product_name']; ?></td>
                                    <td>
                                        <small><?php echo $t['from_outlet_name']; ?> → <?php echo $t['to_outlet_name']; ?></small>
                                    </td>
                                    <td class="fw-bold"><?php echo $t['quantity']; ?></td>
                                    <td>
                                        <?php if($t['status'] == 'pending'): ?>
                                            <span class="status-pending">Pending</span>
                                        <?php elseif($t['status'] == 'approved'): ?>
                                            <span class="status-approved">Approved</span>
                                        <?php elseif($t['status'] == 'received'): ?>
                                            <span class="status-received">Received</span>
                                        <?php elseif($t['status'] == 'rejected'): ?>
                                            <span class="status-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($t['status'] == 'pending'): ?>
                                            <a href="?approve=1&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this transfer?')">✓</a>
                                            <a href="?reject=1&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this transfer?')">✗</a>
                                        <?php elseif($t['status'] == 'approved'): ?>
                                            <a href="?receive=1&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-info" onclick="return confirm('Confirm receipt?')">📦</a>
                                        <?php endif; ?>
                                        <i class="bi bi-info-circle" 
                                           title="Req: <?php echo $t['requested_by_name']; ?><?php echo $t['approved_by_name'] ? ' | App: '.$t['approved_by_name'] : ''; ?><?php echo $t['received_by_name'] ? ' | Rec: '.$t['received_by_name'] : ''; ?>"
                                          ></i>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox sis-u-1e52ae17"></i>
                                        <p class="mt-2">No transfers found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('productSelect').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const maxQty = selected.dataset.max;
            const threshold = selected.dataset.threshold;
            const quantityInput = document.getElementById('quantity');
            
            quantityInput.max = maxQty;
            document.getElementById('stockInfo').innerHTML = `Available: ${maxQty} units | Threshold: ${threshold}`;
            checkThreshold();
        });

        document.getElementById('quantity').addEventListener('input', checkThreshold);

        function checkThreshold() {
            const selected = document.getElementById('productSelect').options[document.getElementById('productSelect').selectedIndex];
            const threshold = parseInt(selected.dataset.threshold) || 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            const fromOutlet = document.getElementById('from_outlet').value;
            
            if (fromOutlet && quantity > 0) {
                const warningDiv = document.getElementById('thresholdWarning');
                if (quantity > threshold * 2) {
                    warningDiv.style.display = 'block';
                    warningDiv.innerHTML = '⚠️ Large transfer - this may significantly impact source outlet stock';
                } else {
                    warningDiv.style.display = 'none';
                }
            }
        }
        document.querySelector('select[name="from_outlet"]').addEventListener('change', function() {
            const toSelect = document.querySelector('select[name="to_outlet"]');
            const selected = this.value;
            
            Array.from(toSelect.options).forEach(option => {
                option.disabled = option.value === selected;
            });
            
            checkThreshold();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>