<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$message = '';
$message_type = '';

// Get counts for sidebar
$outlets_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM outlets WHERE is_active = 1"))['count'] ?? 0;
$agents_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND is_active = 1"))['count'] ?? 0;
$supervisors_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'supervisor' AND is_active = 1"))['count'] ?? 0;
$products_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_products WHERE is_active = 1"))['count'] ?? 0;
$pending_transfers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_transfers WHERE status = 'pending'"))['count'] ?? 0;

// Get all outlets
$outlets = mysqli_query($conn, "SELECT * FROM outlets WHERE is_active = 1 ORDER BY name");

// Get products with stock information
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
            
            // Debug: Log the stock found
            error_log("Source stock found: ID={$stock['id']}, Qty={$stock['quantity']}, Batch={$stock['batch_id']}");
            
            mysqli_begin_transaction($conn);
            
            try {
                // 1. REDUCE from source outlet
                $new_from_qty = $stock['quantity'] - $quantity;
                $update_source = "UPDATE outlet_stock SET quantity = $new_from_qty WHERE id = {$stock['id']}";
                
                if (!mysqli_query($conn, $update_source)) {
                    throw new Exception("Error reducing source stock: " . mysqli_error($conn));
                }
                
                // Debug: Confirm reduction
                error_log("Source stock reduced from {$stock['quantity']} to $new_from_qty");
                
                // 2. ADD to destination outlet
                $dest_check = mysqli_query($conn, "
                    SELECT id, quantity FROM outlet_stock 
                    WHERE outlet_id = $to_outlet 
                    AND product_id = $product_id 
                    AND batch_id = {$stock['batch_id']}
                ");
                
                if (mysqli_num_rows($dest_check) > 0) {
                    // Update existing stock
                    $dest = mysqli_fetch_assoc($dest_check);
                    $new_to_qty = $dest['quantity'] + $quantity;
                    $update_dest = "UPDATE outlet_stock SET quantity = $new_to_qty WHERE id = {$dest['id']}";
                    
                    if (!mysqli_query($conn, $update_dest)) {
                        throw new Exception("Error updating destination stock: " . mysqli_error($conn));
                    }
                    
                    $to_before = $dest['quantity'];
                    error_log("Destination stock updated from {$dest['quantity']} to $new_to_qty");
                    
                } else {
                    // Insert new stock record
                    $insert_dest = "INSERT INTO outlet_stock (outlet_id, product_id, batch_id, quantity) 
                                   VALUES ($to_outlet, $product_id, {$stock['batch_id']}, $quantity)";
                    
                    if (!mysqli_query($conn, $insert_dest)) {
                        throw new Exception("Error inserting destination stock: " . mysqli_error($conn));
                    }
                    
                    $to_before = 0;
                    error_log("New destination stock created with $quantity units");
                }
                
                // 3. Record source outlet movement
                $movement1 = "INSERT INTO stock_movements (
                    outlet_id, product_id, batch_id, movement_type, quantity, 
                    before_quantity, after_quantity, notes, created_by
                ) VALUES (
                    $from_outlet, $product_id, {$stock['batch_id']}, 'transferred', $quantity, 
                    {$stock['quantity']}, $new_from_qty, 
                    'Transferred to outlet $to_outlet: $notes', {$_SESSION['user_id']}
                )";
                
                if (!mysqli_query($conn, $movement1)) {
                    throw new Exception("Error recording source movement: " . mysqli_error($conn));
                }
                
                // 4. Record destination outlet movement
                $after_to = ($to_before + $quantity);
                $movement2 = "INSERT INTO stock_movements (
                    outlet_id, product_id, batch_id, movement_type, quantity, 
                    before_quantity, after_quantity, notes, created_by
                ) VALUES (
                    $to_outlet, $product_id, {$stock['batch_id']}, 'transferred', $quantity, 
                    $to_before, $after_to, 
                    'Received from outlet $from_outlet: $notes', {$_SESSION['user_id']}
                )";
                
                if (!mysqli_query($conn, $movement2)) {
                    throw new Exception("Error recording destination movement: " . mysqli_error($conn));
                }
                
                // 5. Create transfer record
                $status = $requires_approval ? 'pending' : 'received';
                $insert_transfer = "INSERT INTO stock_transfers (
                    from_outlet_id, to_outlet_id, product_id, batch_id, quantity, 
                    requested_by, request_notes, status, requested_at
                ) VALUES (
                    $from_outlet, $to_outlet, $product_id, {$stock['batch_id']}, $quantity,
                    {$_SESSION['user_id']}, '$notes', '$status', NOW()
                )";
                
                if (!mysqli_query($conn, $insert_transfer)) {
                    throw new Exception("Error creating transfer record: " . mysqli_error($conn));
                }
                
                mysqli_commit($conn);
                
                // Success message with confirmation of both actions
                $message = "Stock transferred successfully! $quantity units moved from source to destination.";
                $message_type = 'success';
                
                // Log the action
                logAction($conn, 'STOCK_TRANSFER', mysqli_insert_id($conn));
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Transfer failed: " . $e->getMessage();
                $message_type = 'danger';
                error_log("Transfer error: " . $e->getMessage());
            }
        }
    }
}

// Handle approval
if (isset($_GET['approve'])) {
    $transfer_id = (int)$_GET['id'];
    
    $transfer = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT st.*, os.quantity as available_qty, os.id as stock_id,
               p.low_stock_threshold
        FROM stock_transfers st
        JOIN outlet_stock os ON st.from_outlet_id = os.outlet_id 
            AND st.product_id = os.product_id AND st.batch_id = os.batch_id
        JOIN stock_products p ON st.product_id = p.id
        WHERE st.id = $transfer_id
    "));
    
    if ($transfer['available_qty'] < $transfer['quantity']) {
        $message = "Insufficient stock available. Requested: {$transfer['quantity']}, Available: {$transfer['available_qty']}";
        $message_type = 'danger';
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            // Reduce from source
            $new_from_qty = $transfer['available_qty'] - $transfer['quantity'];
            mysqli_query($conn, "UPDATE outlet_stock SET quantity = $new_from_qty WHERE id = {$transfer['stock_id']}");
            
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
                {$transfer['from_outlet_id']}, {$transfer['product_id']}, {$transfer['batch_id']}, 'transferred', {$transfer['quantity']}, 
                {$transfer['available_qty']}, $new_from_qty, 
                'Approved transfer to outlet {$transfer['to_outlet_id']}', {$_SESSION['user_id']}
            )");
            
            mysqli_query($conn, "INSERT INTO stock_movements (
                outlet_id, product_id, batch_id, movement_type, quantity, 
                before_quantity, after_quantity, notes, created_by
            ) VALUES (
                {$transfer['to_outlet_id']}, {$transfer['product_id']}, {$transfer['batch_id']}, 'transferred', {$transfer['quantity']}, 
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
}

// Handle rejection
if (isset($_GET['reject'])) {
    $transfer_id = (int)$_GET['id'];
    
    mysqli_query($conn, "UPDATE stock_transfers SET 
        status = 'rejected',
        approved_by = {$_SESSION['user_id']},
        approved_at = NOW()
        WHERE id = $transfer_id");
    
    $message = "Transfer request rejected";
    $message_type = 'warning';
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

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Products</h6>
                <h2><?php echo $products_count; ?></h2>
                <small>In catalog</small>
            </div>
            <div class="stat-card">
                <h6>Pending Transfers</h6>
                <h2><?php echo $pending_transfers; ?></h2>
                <small>Awaiting approval</small>
            </div>
            <div class="stat-card">
                <h6>Completed Today</h6>
                <?php
                $completed_today = mysqli_fetch_assoc(mysqli_query($conn, "
                    SELECT COUNT(*) as count FROM stock_transfers 
                    WHERE status = 'received' AND DATE(received_at) = CURDATE()
                "))['count'] ?? 0;
                ?>
                <h2><?php echo $completed_today; ?></h2>
                <small>Transfers received</small>
            </div>
            <div class="stat-card">
                <h6>Total Outlets</h6>
                <h2><?php echo $outlets_count; ?></h2>
                <small>Active branches</small>
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
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" min="1" required>
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
                            <i class="bi bi-send"></i> Transfer Stock
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
            document.getElementById('quantity').max = maxQty;
        });
        document.querySelector('select[name="from_outlet"]').addEventListener('change', function() {
            const toSelect = document.querySelector('select[name="to_outlet"]');
            const selected = this.value;
            
            Array.from(toSelect.options).forEach(option => {
                option.disabled = option.value === selected;
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>