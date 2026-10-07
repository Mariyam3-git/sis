<?php
require_once '../config.php';
requireRole('supervisor');

$supervisor_id = $_SESSION['user_id'];
$outlet_id = $_SESSION['outlet_id'];

if (!$outlet_id) {
    die('No outlet assigned to this supervisor. Please contact administrator.');
}

// Get outlet details
$outlet_query = "SELECT * FROM outlets WHERE id = $outlet_id";
$outlet_result = mysqli_query($conn, $outlet_query);
$outlet = mysqli_fetch_assoc($outlet_result);

// ============================================
// STOCK CALCULATIONS
// ============================================

// Get current stock levels for this outlet (with In/Out/Remaining)
$outlet_stock = mysqli_query($conn, "
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.selling_price,
        p.low_stock_threshold,
        p.reorder_quantity,
        COALESCE((
            SELECT SUM(quantity) 
            FROM stock_movements 
            WHERE product_id = p.id 
            AND outlet_id = $outlet_id 
            AND movement_type IN ('received', 'received_from_warehouse')
        ), 0) as stock_in,
        COALESCE((
            SELECT SUM(quantity) 
            FROM stock_movements 
            WHERE product_id = p.id 
            AND outlet_id = $outlet_id 
            AND movement_type IN ('sold', 'transferred', 'damaged')
        ), 0) as stock_out,
        COALESCE((
            SELECT SUM(CASE 
                WHEN movement_type IN ('received', 'received_from_warehouse') THEN quantity 
                WHEN movement_type IN ('sold', 'transferred', 'damaged') THEN -quantity 
                ELSE 0 
            END)
            FROM stock_movements 
            WHERE product_id = p.id 
            AND outlet_id = $outlet_id
        ), 0) as current_stock
    FROM stock_products p
    WHERE p.is_active = 1
    ORDER BY current_stock ASC
");

// Calculate low stock items
$low_stock_items = [];
$critical_stock_items = [];
$out_of_stock_items = [];
$total_products = 0;

while($item = mysqli_fetch_assoc($outlet_stock)) {
    $total_products++;
    $threshold = $item['low_stock_threshold'] ?? 10;
    $current = $item['current_stock'];
    
    if($current <= 0) {
        $out_of_stock_items[] = $item;
    } elseif($current <= $threshold/2) {
        $critical_stock_items[] = $item;
    } elseif($current <= $threshold) {
        $low_stock_items[] = $item;
    }
}

// Reset pointer for later use
mysqli_data_seek($outlet_stock, 0);

// ============================================
// SALES TARGET AND PERFORMANCE
// ============================================

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Today's sales for this outlet
$today_sales = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(CASE WHEN amount > 0 THEN 1 END) as transaction_count,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as collected,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as withdrawals,
        COALESCE(SUM(CASE WHEN payment_status = 'Pending' AND amount > 0 THEN amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN payment_status = 'Unpaid' AND amount > 0 THEN amount ELSE 0 END), 0) as unpaid,
        COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total
    FROM sales 
    WHERE outlet_id = $outlet_id AND DATE(payment_date) = '$today'
"));

// Month-to-date sales
$month_sales = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(CASE WHEN amount > 0 THEN 1 END) as transaction_count,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as collected,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as withdrawals,
        COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total
    FROM sales 
    WHERE outlet_id = $outlet_id AND DATE(payment_date) BETWEEN '$month_start' AND '$month_end'
"));

// Agent performance (fixed GROUP BY issue)
$agent_performance = mysqli_query($conn, "
    SELECT 
        u.id,
        u.full_name,
        u.shift,
        COUNT(DISTINCT v.id) as visitors,
        COUNT(DISTINCT CASE WHEN s.amount > 0 THEN s.id END) as sales_count,
        COALESCE(SUM(CASE WHEN s.payment_status = 'Paid' AND s.amount > 0 THEN s.amount ELSE 0 END), 0) as collected,
        COALESCE(SUM(CASE WHEN s.payment_status = 'Paid' AND s.amount < 0 THEN ABS(s.amount) ELSE 0 END), 0) as withdrawals,
        COALESCE(SUM(CASE WHEN s.payment_status = 'Pending' AND s.amount > 0 THEN s.amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN s.payment_status = 'Unpaid' AND s.amount > 0 THEN s.amount ELSE 0 END), 0) as unpaid,
        COALESCE(SUM(CASE WHEN s.amount > 0 THEN s.amount ELSE 0 END), 0) as total
    FROM users u
    LEFT JOIN visitors v ON u.id = v.agent_id AND DATE(v.visit_time) = '$today'
    LEFT JOIN sales s ON v.id = s.visitor_id
    WHERE u.role = 'agent' AND u.outlet_id = $outlet_id AND u.is_active = 1
    GROUP BY u.id, u.full_name, u.shift
    ORDER BY collected DESC
");

// Get outlet targets (fixed - separate queries to avoid GROUP BY issues)
$daily_target = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT target_value 
    FROM targets 
    WHERE target_type = 'outlet' 
    AND assigned_to_id = $outlet_id 
    AND period_type = 'daily'
    AND start_date <= CURDATE() 
    AND end_date >= CURDATE()
    LIMIT 1
"));

$weekly_target = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT target_value 
    FROM targets 
    WHERE target_type = 'outlet' 
    AND assigned_to_id = $outlet_id 
    AND period_type = 'weekly'
    AND start_date <= CURDATE() 
    AND end_date >= CURDATE()
    LIMIT 1
"));

$monthly_target = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT target_value 
    FROM targets 
    WHERE target_type = 'outlet' 
    AND assigned_to_id = $outlet_id 
    AND period_type = 'monthly'
    AND start_date <= CURDATE() 
    AND end_date >= CURDATE()
    LIMIT 1
"));

// Get pending stock requests
$pending_requests = mysqli_query($conn, "
    SELECT sr.*, p.name as product_name, p.sku
    FROM stock_requests sr
    JOIN stock_products p ON sr.product_id = p.id
    WHERE sr.outlet_id = $outlet_id AND sr.status = 'pending'
    ORDER BY 
        CASE sr.priority 
            WHEN 'critical' THEN 1
            WHEN 'urgent' THEN 2
            WHEN 'normal' THEN 3
        END,
        sr.request_date ASC
");

// Get recent stock request history
$request_history = mysqli_query($conn, "
    SELECT sr.*, p.name as product_name, p.sku,
           u.full_name as approved_by_name
    FROM stock_requests sr
    JOIN stock_products p ON sr.product_id = p.id
    LEFT JOIN users u ON sr.approved_by = u.id
    WHERE sr.outlet_id = $outlet_id
    ORDER BY sr.request_date DESC
    LIMIT 10
");

// Get available products for request
$products = mysqli_query($conn, "
    SELECT id, name, sku FROM stock_products WHERE is_active = 1 ORDER BY name
");

// Handle stock request submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $priority = mysqli_real_escape_string($conn, (string)($_POST['priority'] ?? ''));
    $required_by = sisCleanDate($_POST['required_by'] ?? '') !== '' ? "'" . sisCleanDate($_POST['required_by']) . "'" : 'NULL';
    $request_notes = mysqli_real_escape_string($conn, (string)($_POST['request_notes'] ?? ''));
    $ref_num = 'REQ-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    if ($quantity <= 0) {
        $message = "Invalid quantity";
        $message_type = "danger";
    } else {
        $insert = "INSERT INTO stock_requests (outlet_id, supervisor_id, product_id, quantity, priority, required_by_date, request_notes, reference_number, status) 
                   VALUES ($outlet_id, $supervisor_id, $product_id, $quantity, '$priority', $required_by, '$request_notes', '$ref_num', 'pending')";
        
        if (mysqli_query($conn, $insert)) {
            $message = "Stock request submitted! Reference: $ref_num";
            $message_type = "success";
            logAction($conn, 'STOCK_REQUEST', mysqli_insert_id($conn));
            // Refresh to show new request
            header("Location: stock_dashboard.php");
            exit();
        } else {
            $message = sisDbErrorMessage($conn);
            $message_type = "danger";
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Stock Dashboard - <?php echo htmlspecialchars($outlet['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

    <div class="outlet-header">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col">
                    <h4 class="mb-0"><?php echo htmlspecialchars($outlet['name']); ?></h4>
                    <small class="text-muted"><?php echo htmlspecialchars($outlet['location']); ?> • <?php echo htmlspecialchars($outlet['region']); ?></small>
                </div>
                <div class="col text-end">
                    <span class="badge bg-secondary"><?php echo date('l, d M Y'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4">
        <?php if($message): ?>
        <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show">
            <?php echo e($message); ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="stats-grid">
            <div class="stat-card sales">
                <h6>Today's Sales</h6>
                <h3>D <?php echo number_format($today_sales['collected'], 2); ?></h3>
                <small><?php echo e($today_sales['transaction_count']); ?> transactions</small>
            </div>
            <div class="stat-card visitors">
                <h6>Month to Date</h6>
                <h3>D <?php echo number_format($month_sales['collected'], 2); ?></h3>
                <small><?php echo e($month_sales['transaction_count']); ?> transactions</small>
            </div>
            <div class="stat-card target">
                <h6>Products in Stock</h6>
                <h3><?php echo $total_products; ?></h3>
                <small>Active items</small>
            </div>
            <div class="stat-card stock">
                <h6>Low/Out of Stock</h6>
                <h3><?php echo count($low_stock_items) + count($critical_stock_items) + count($out_of_stock_items); ?></h3>
                <small><?php echo count($out_of_stock_items); ?> out of stock</small>
            </div>
        </div>

        
        <?php if(count($low_stock_items) > 0 || count($critical_stock_items) > 0 || count($out_of_stock_items) > 0): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Stock Alert!</strong> 
            <?php if(count($out_of_stock_items) > 0): ?><?php echo count($out_of_stock_items); ?> out of stock, <?php endif; ?>
            <?php if(count($critical_stock_items) > 0): ?><?php echo count($critical_stock_items); ?> critical, <?php endif; ?>
            <?php if(count($low_stock_items) > 0): ?><?php echo count($low_stock_items); ?> low stock items. <?php endif; ?>
            <a href="#stock-table" class="alert-link">View below</a>
        </div>
        <?php endif; ?>

        
        <div class="two-col">
            
            <div>
                
                <div class="card" id="stock-table">
                    <div class="card-header">
                        <span><i class="bi bi-box"></i> Current Stock Levels</span>
                        <span class="badge bg-secondary">In Stock: <?php echo $total_products - count($out_of_stock_items); ?> items</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>In</th>
                                        <th>Out</th>
                                        <th>Current</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    mysqli_data_seek($outlet_stock, 0);
                                    while($item = mysqli_fetch_assoc($outlet_stock)): 
                                        $current = $item['current_stock'];
                                        $threshold = $item['low_stock_threshold'] ?? 10;
                                        $row_class = '';
                                        $status_text = 'Good';
                                        $status_class = 'success';
                                        
                                        if($current <= 0) {
                                            $row_class = 'critical-stock-row';
                                            $status_text = 'Out of Stock';
                                            $status_class = 'danger';
                                        } elseif($current <= $threshold/2) {
                                            $row_class = 'critical-stock-row';
                                            $status_text = 'Critical';
                                            $status_class = 'critical';
                                        } elseif($current <= $threshold) {
                                            $row_class = 'low-stock-row';
                                            $status_text = 'Low';
                                            $status_class = 'low';
                                        }
                                    ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                        <td class="stock-in"><?php echo e($item['stock_in']); ?></td>
                                        <td class="stock-out"><?php echo e($item['stock_out']); ?></td>
                                        <td class="stock-current"><?php echo $current; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $status_class; ?>"><?php echo e($status_text); ?></span>
                                         </td>
                                        <td>
                                            <?php if($current <= $threshold): ?>
                                            <button class="btn btn-sm btn-primary" onclick="quickRequest(<?php echo e($item['id']); ?>, <?php echo e($item['reorder_quantity'] ?? 50); ?>)">
                                                Request Stock
                                            </button>
                                            <?php else: ?>
                                            <span class="text-muted">OK</span>
                                            <?php endif; ?>
                                         </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-send"></i> Request Stock</span>
                        <?php if(mysqli_num_rows($pending_requests) > 0): ?>
                        <span class="badge bg-warning"><?php echo mysqli_num_rows($pending_requests); ?> pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-2">
                                <div class="col-12">
                                    <select class="form-select" name="product_id" required>
                                        <option value="">Select Product</option>
                                        <?php 
                                        mysqli_data_seek($products, 0);
                                        while($p = mysqli_fetch_assoc($products)): 
                                        ?>
                                        <option value="<?php echo e($p['id']); ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo e($p['sku']); ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="quantity" placeholder="Quantity" required>
                                </div>
                                <div class="col-4">
                                    <select class="form-select" name="priority">
                                        <option value="normal">Normal</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <button type="submit" name="submit_request" class="btn btn-primary w-100">Submit</button>
                                </div>
                                <div class="col-12">
                                    <textarea class="form-control" name="request_notes" rows="2" placeholder="Notes (reason for request)..."></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                
                <?php if(mysqli_num_rows($pending_requests) > 0): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-clock-history"></i> Pending Requests</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Priority</th>
                                    <th>Requested</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($req = mysqli_fetch_assoc($pending_requests)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['product_name']); ?></td>
                                    <td><?php echo e($req['quantity']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $req['priority'] == 'critical' ? 'bg-danger' : ($req['priority'] == 'urgent' ? 'bg-warning' : 'bg-secondary'); ?>">
                                            <?php echo ucfirst($req['priority']); ?>
                                        </span>
                                     </td>
                                    <td><?php echo date('d/m H:i', strtotime($req['request_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-warning">Pending</span>
                                     </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            
            <div>
                
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-bullseye"></i> Sales Targets</span>
                        <span class="badge bg-info"><?php echo date('F Y'); ?></span>
                    </div>
                    <div class="card-body">
                        <?php 
                        $has_targets = false;
                        
                        // Daily Target
                        if($daily_target && $daily_target['target_value'] > 0):
                            $has_targets = true;
                            $target_value = $daily_target['target_value'];
                            $achieved = $today_sales['collected'];
                            $percentage = $target_value > 0 ? min(100, ($achieved / $target_value) * 100) : 0;
                            $progress_class = $percentage >= 100 ? 'success' : ($percentage >= 70 ? 'info' : ($percentage >= 50 ? 'warning' : 'danger'));
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Daily Target</strong>
                                <span>D <?php echo number_format($achieved, 2); ?> / D <?php echo number_format($target_value, 2); ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $progress_class; ?> sis-progress" data-progress="<?php echo e($percentage); ?>"></div>
                            </div>
                            <div class="target-progress text-end"><?php echo round($percentage, 1); ?>% achieved today</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        // Weekly Target
                        if($weekly_target && $weekly_target['target_value'] > 0):
                            $has_targets = true;
                            // Get week-to-date sales
                            $week_start = date('Y-m-d', strtotime('monday this week'));
                            $week_sales = mysqli_fetch_assoc(mysqli_query($conn, "
                                SELECT COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as collected
                                FROM sales 
                                WHERE outlet_id = $outlet_id AND DATE(payment_date) >= '$week_start'
                            "));
                            $week_achieved = $week_sales['collected'] ?? 0;
                            $target_value = $weekly_target['target_value'];
                            $percentage = $target_value > 0 ? min(100, ($week_achieved / $target_value) * 100) : 0;
                            $progress_class = $percentage >= 100 ? 'success' : ($percentage >= 70 ? 'info' : ($percentage >= 50 ? 'warning' : 'danger'));
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Weekly Target</strong>
                                <span>D <?php echo number_format($week_achieved, 2); ?> / D <?php echo number_format($target_value, 2); ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $progress_class; ?> sis-progress" data-progress="<?php echo e($percentage); ?>"></div>
                            </div>
                            <div class="target-progress text-end"><?php echo round($percentage, 1); ?>% achieved this week</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        // Monthly Target
                        if($monthly_target && $monthly_target['target_value'] > 0):
                            $has_targets = true;
                            $target_value = $monthly_target['target_value'];
                            $achieved = $month_sales['collected'];
                            $percentage = $target_value > 0 ? min(100, ($achieved / $target_value) * 100) : 0;
                            $progress_class = $percentage >= 100 ? 'success' : ($percentage >= 70 ? 'info' : ($percentage >= 50 ? 'warning' : 'danger'));
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Monthly Target</strong>
                                <span>D <?php echo number_format($achieved, 2); ?> / D <?php echo number_format($target_value, 2); ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $progress_class; ?> sis-progress" data-progress="<?php echo e($percentage); ?>"></div>
                            </div>
                            <div class="target-progress text-end"><?php echo round($percentage, 1); ?>% achieved this month</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!$has_targets): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-info-circle"></i>
                            <p>No targets set for this outlet</p>
                            <small>Contact admin to set targets</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-bar-chart"></i> Agent Performance Today</span>
                        <a href="agent_performance.php" class="small">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Agent</th>
                                        <th>Shift</th>
                                        <th>Visitors</th>
                                        <th>Sales</th>
                                        <th>Collected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($agent_performance) > 0): ?>
                                        <?php while($agent = mysqli_fetch_assoc($agent_performance)): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($agent['full_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo e($agent['shift']); ?></small>
                                             </td>
                                            <td><?php echo e($agent['visitors']); ?></td>
                                            <td><?php echo e($agent['sales_count']); ?></td>
                                            <td class="fw-bold text-success">D <?php echo number_format($agent['collected'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">No agents found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                
                <div class="card mt-3">
                    <div class="card-header">
                        <span><i class="bi bi-clock-history"></i> Recent Stock Requests</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Qty</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($request_history) > 0): ?>
                                        <?php while($req = mysqli_fetch_assoc($request_history)): ?>
                                        <tr>
                                            <td><?php echo date('d/m', strtotime($req['request_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($req['product_name']); ?></td>
                                            <td><?php echo e($req['quantity']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $req['priority'] == 'critical' ? 'bg-danger' : ($req['priority'] == 'urgent' ? 'bg-warning' : 'bg-secondary'); ?>">
                                                    <?php echo ucfirst($req['priority']); ?>
                                                </span>
                                             </td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $req['status'] == 'fulfilled' ? 'bg-success' : 
                                                        ($req['status'] == 'approved' ? 'bg-info' : 
                                                        ($req['status'] == 'rejected' ? 'bg-danger' : 'bg-warning')); 
                                                ?>">
                                                    <?php echo ucfirst($req['status']); ?>
                                                </span>
                                             </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">No stock requests yet</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function quickRequest(productId, quantity) {
            const select = document.querySelector('select[name="product_id"]');
            const qtyInput = document.querySelector('input[name="quantity"]');
            const prioritySelect = document.querySelector('select[name="priority"]');
            
            if (select) select.value = productId;
            if (qtyInput) qtyInput.value = quantity;
            if (prioritySelect) prioritySelect.value = 'urgent';
            document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
        }
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        }, 5000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
