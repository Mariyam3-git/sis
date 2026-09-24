<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$message = '';
$message_type = '';

// Handle approval
if (sisPostAction('approve')) {
    $request_id = (int)$_POST['id'];
    
    mysqli_query($conn, "UPDATE stock_requests SET 
        status = 'approved',
        approved_by = {$_SESSION['user_id']},
        approved_at = NOW()
        WHERE id = $request_id");
    
    $message = "Request approved successfully!";
    $message_type = 'success';
    logAction($conn, 'APPROVE_STOCK_REQUEST', $request_id);
}

// Handle rejection
if (sisPostAction('reject')) {
    $request_id = (int)$_POST['id'];
    $reason = mysqli_real_escape_string($conn, (string)($_POST['reason'] ?? 'No reason provided'));
    
    mysqli_query($conn, "UPDATE stock_requests SET 
        status = 'rejected',
        approved_by = {$_SESSION['user_id']},
        approved_at = NOW(),
        rejection_reason = '$reason'
        WHERE id = $request_id");
    
    $message = "Request rejected";
    $message_type = 'warning';
    logAction($conn, 'REJECT_STOCK_REQUEST', $request_id);
}

// Handle fulfillment
if (sisPostAction('fulfill')) {
    $request_id = (int)$_POST['id'];
    
    // Get request details
    $request = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT sr.*, p.name as product_name, p.selling_price
        FROM stock_requests sr
        JOIN stock_products p ON sr.product_id = p.id
        WHERE sr.id = $request_id
    "));
    
    mysqli_begin_transaction($conn);
    
    try {
        // Update request status
        $update = "UPDATE stock_requests SET 
            status = 'fulfilled',
            fulfilled_by = {$_SESSION['user_id']},
            fulfilled_at = NOW()
            WHERE id = $request_id";
        
        if (!mysqli_query($conn, $update)) {
            throw new Exception(sisDbErrorMessage($conn));
        }
        
        // Check if outlet already has this product
        $check_outlet = mysqli_query($conn, "
            SELECT id, quantity FROM outlet_stock 
            WHERE outlet_id = {$request['outlet_id']} 
            AND product_id = {$request['product_id']}
        ");
        
        $before_qty = 0;
        $after_qty = $request['quantity'];
        
        if (mysqli_num_rows($check_outlet) > 0) {
            $outlet = mysqli_fetch_assoc($check_outlet);
            $before_qty = $outlet['quantity'];
            $after_qty = $before_qty + $request['quantity'];
            
            $update_outlet = "UPDATE outlet_stock SET quantity = $after_qty WHERE id = {$outlet['id']}";
            if (!mysqli_query($conn, $update_outlet)) {
                throw new Exception(sisDbErrorMessage($conn));
            }
        } else {
            $insert_outlet = "INSERT INTO outlet_stock (outlet_id, product_id, quantity) 
                              VALUES ({$request['outlet_id']}, {$request['product_id']}, {$request['quantity']})";
            if (!mysqli_query($conn, $insert_outlet)) {
                throw new Exception(sisDbErrorMessage($conn));
            }
        }
        
        // Record in stock movements - WITH before_quantity and after_quantity
        $movement = "INSERT INTO stock_movements (
            outlet_id, product_id, movement_type, quantity, 
            before_quantity, after_quantity, notes, created_by
        ) VALUES (
            {$request['outlet_id']}, {$request['product_id']}, 'received', 
            {$request['quantity']}, $before_qty, $after_qty,
            'Fulfilled stock request: {$request['reference_number']}', 
            {$_SESSION['user_id']}
        )";
        
        if (!mysqli_query($conn, $movement)) {
            throw new Exception(sisDbErrorMessage($conn));
        }
        
        mysqli_commit($conn);
        
        $message = "Request fulfilled and stock added to outlet!";
        $message_type = 'success';
        logAction($conn, 'FULFILL_STOCK_REQUEST', $request_id);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get all outlets for filter
$outlets = mysqli_query($conn, "SELECT * FROM outlets WHERE is_active = 1 ORDER BY name");

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, (string)($_GET['status'] ?? '')) : '';
$outlet_filter = isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : 0;
$date_from = sisCleanDate($_GET['date_from'] ?? '', date('Y-m-d', strtotime('-30 days')));
$date_to = sisCleanDate($_GET['date_to'] ?? '', date('Y-m-d'));

// Build where clause
$where = ["1=1"];
if ($status_filter) {
    $where[] = "sr.status = '$status_filter'";
}
if ($outlet_filter > 0) {
    $where[] = "sr.outlet_id = $outlet_filter";
}
$where[] = "DATE(sr.request_date) BETWEEN '$date_from' AND '$date_to'";
$where_clause = implode(" AND ", $where);

// Get all requests with details
$requests = mysqli_query($conn, "
    SELECT sr.*, 
           o.name as outlet_name,
           u.full_name as supervisor_name,
           p.name as product_name,
           p.sku,
           p.unit_price,
           p.selling_price
    FROM stock_requests sr
    JOIN outlets o ON sr.outlet_id = o.id
    JOIN users u ON sr.supervisor_id = u.id
    JOIN stock_products p ON sr.product_id = p.id
    WHERE $where_clause
    ORDER BY 
        CASE sr.priority 
            WHEN 'critical' THEN 1
            WHEN 'urgent' THEN 2
            WHEN 'normal' THEN 3
        END,
        sr.request_date DESC
");

// Get summary statistics
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) as critical,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
    FROM stock_requests
    WHERE DATE(request_date) BETWEEN '$date_from' AND '$date_to'
"));

// Get counts for sidebar
$outlets_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM outlets WHERE is_active = 1"))['count'] ?? 0;
$agents_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND is_active = 1"))['count'] ?? 0;
$supervisors_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'supervisor' AND is_active = 1"))['count'] ?? 0;
$products_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_products WHERE is_active = 1"))['count'] ?? 0;
$pending_requests_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_requests WHERE status = 'pending'"))['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Requests - Comium GM</title>
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
            <h3><i class="bi bi-send sis-u-002e4b71"></i> Stock Requests</h3>
            <span class="date-badge"><?php echo date('l, d M Y'); ?></span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card pending">
                <h6>Pending</h6>
                <h2><?php echo e($stats['pending'] ?? 0); ?></h2>
                <small>Awaiting approval</small>
            </div>
            <div class="stat-card approved">
                <h6>Approved</h6>
                <h2><?php echo e($stats['approved'] ?? 0); ?></h2>
                <small>Ready to fulfill</small>
            </div>
            <div class="stat-card fulfilled">
                <h6>Fulfilled</h6>
                <h2><?php echo e($stats['fulfilled'] ?? 0); ?></h2>
                <small>Completed</small>
            </div>
            <div class="stat-card rejected">
                <h6>Rejected</h6>
                <h2><?php echo e($stats['rejected'] ?? 0); ?></h2>
                <small>Cancelled</small>
            </div>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Critical</h6>
                <h2><?php echo e($stats['critical'] ?? 0); ?></h2>
                <small>Urgent attention</small>
            </div>
            <div class="stat-card">
                <h6>Urgent</h6>
                <h2><?php echo e($stats['urgent'] ?? 0); ?></h2>
                <small>High priority</small>
            </div>
            <div class="stat-card">
                <h6>Total Requests</h6>
                <h2><?php echo e($stats['total'] ?? 0); ?></h2>
                <small>All time</small>
            </div>
            <div class="stat-card">
                <h6>Outlets</h6>
                <h2><?php echo $outlets_count; ?></h2>
                <small>Active</small>
            </div>
        </div>

        <?php if($message): ?>
        <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show">
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="filter-section">
            <form method="GET" class="filter-grid">
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
                </div>
                <div class="filter-group">
                    <label>Outlet</label>
                    <select class="form-select" name="outlet_id">
                        <option value="0">All Outlets</option>
                        <?php while($o = mysqli_fetch_assoc($outlets)): ?>
                        <option value="<?php echo e($o['id']); ?>" <?php echo $outlet_filter == $o['id'] ? 'selected' : ''; ?>>
                            <?php echo e($o['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="fulfilled" <?php echo $status_filter == 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <a href="stock_requests.php" class="btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-list"></i> Stock Requests</span>
                <span class="badge bg-secondary"><?php echo mysqli_num_rows($requests); ?> requests</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Outlet</th>
                                <th>Supervisor</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($requests) > 0): ?>
                                <?php while($r = mysqli_fetch_assoc($requests)): 
                                    $row_class = '';
                                    if($r['priority'] == 'critical') $row_class = 'priority-critical';
                                    elseif($r['priority'] == 'urgent') $row_class = 'priority-urgent';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo date('d/m/Y H:i', strtotime($r['request_date'])); ?></td>
                                    <td><?php echo e($r['outlet_name']); ?></td>
                                    <td><?php echo e($r['supervisor_name']); ?></td>
                                    <td>
                                        <strong><?php echo e($r['product_name']); ?></strong>
                                        <br><small><?php echo e($r['sku']); ?></small>
                                    </td>
                                    <td class="fw-bold"><?php echo e($r['quantity']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo e($r['priority']); ?>">
                                            <?php echo ucfirst($r['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo e($r['status']); ?>">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($r['status'] == 'pending'): ?>
                                            <a href="?approve=1&id=<?php echo e($r['id']); ?>" class="action-btn btn-approve" 
                                               onclick="return confirm('Approve this request?')">✓</a>
                                            <a href="#" class="action-btn btn-reject" 
                                               onclick="promptReason(<?php echo e($r['id']); ?>)">✗</a>
                                        <?php elseif($r['status'] == 'approved'): ?>
                                            <a href="?fulfill=1&id=<?php echo e($r['id']); ?>" class="action-btn btn-fulfill"
                                               onclick="return confirm('Fulfill this request?')">📦</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if($r['rejection_reason']): ?>
                                <tr>
                                    <td colspan="8" class="text-danger small">
                                        <strong>Rejection reason:</strong> <?php echo e($r['rejection_reason']); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="bi bi-inbox sis-u-4b77038a"></i>
                                        <p class="mt-3 text-muted">No stock requests found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="quick-actions">
            <a href="stock_receive.php" class="action-btn sis-u-78893813">
                <i class="bi bi-box-arrow-in-down sis-u-e118d5ea"></i>
                Receive Stock
            </a>
            <a href="stock_transfer.php" class="action-btn sis-u-78893813">
                <i class="bi bi-arrow-left-right sis-u-335ab84e"></i>
                Transfer Stock
            </a>
            <a href="stock_reports.php" class="action-btn sis-u-78893813">
                <i class="bi bi-file-bar-graph sis-u-7d8ba3b6"></i>
                Stock Reports
            </a>
        </div>
    </div>

    <script>
        function promptReason(requestId) {
            let reason = prompt('Enter rejection reason:');
            if (reason) {
                window.sisSubmitPost({ reject: 1, id: requestId, reason: reason });
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>