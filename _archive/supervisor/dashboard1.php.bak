<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('supervisor');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
    http_response_code(403);
    exit('Security check failed. Please return to the previous page and try again.');
}

$outlet_id = $_SESSION['outlet_id'];
$supervisor_id = $_SESSION['user_id'];
$supervisor_name = $_SESSION['full_name'];

if (!$outlet_id) {
    die('No outlet assigned to this supervisor');
}

// Get outlet details
$outlet_query = "SELECT * FROM outlets WHERE id = $outlet_id";
$outlet_result = mysqli_query($conn, $outlet_query);
$outlet = mysqli_fetch_assoc($outlet_result);

// Date filters
[$date_from, $date_to] = sisDateRange($_GET['date_from'] ?? null, $_GET['date_to'] ?? null, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));

$message = '';
$message_type = '';

// Handle status update for visitors
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_visitor_status'])) {
    $visitor_id = (int)$_POST['visitor_id'];
    $status = $_POST['status'] ?? 'Waiting';
    markVisitorStatus($conn, $visitor_id, $status);
    logAction($conn, 'UPDATE_VISITOR_STATUS', $visitor_id);
    $message = 'Visitor status updated!';
    $message_type = 'success';
}

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $sale_id = (int)$_POST['sale_id'];
    $payment_status = mysqli_real_escape_string($conn, (string)($_POST['payment_status'] ?? ''));
    
    $update = "UPDATE sales SET payment_status = '$payment_status' WHERE id = $sale_id";
    if (mysqli_query($conn, $update)) {
        logAction($conn, 'UPDATE_PAYMENT', $sale_id);
        $message = 'Payment status updated!';
        $message_type = 'success';
    }
}

// ============================================
// HANDLE STOCK REQUEST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $priority = mysqli_real_escape_string($conn, (string)($_POST['priority'] ?? ''));
    $required_by = !empty($_POST['required_by']) ? "'" . $_POST['required_by'] . "'" : 'NULL';
    $request_notes = mysqli_real_escape_string($conn, (string)($_POST['request_notes'] ?? ''));
    
    // Generate reference number
    $ref_num = 'REQ-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    // Validate quantity
    if ($quantity <= 0) {
        $message = "Quantity must be greater than zero";
        $message_type = 'danger';
    } else {
        $insert = "INSERT INTO stock_requests (
            outlet_id, supervisor_id, product_id, quantity, priority, 
            required_by_date, request_notes, reference_number, status
        ) VALUES (
            $outlet_id, $supervisor_id, $product_id, $quantity, '$priority',
            $required_by, '$request_notes', '$ref_num', 'pending'
        )";
        
        if (mysqli_query($conn, $insert)) {
            $request_id = mysqli_insert_id($conn);
            $message = "Stock request submitted successfully! Reference: $ref_num";
            $message_type = 'success';
            logAction($conn, 'STOCK_REQUEST', $request_id);
        } else {
            $message = "Error: " . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Action completed.', $message_type ?? 'success');
    redirectTo('dashboard.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

// ============================================
// SUPERVISOR RESPONSIBILITIES QUERIES
// ============================================

// 1. TODAY'S STATS
$today = date('Y-m-d');
$visitors_today = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM visitors 
    WHERE outlet_id = $outlet_id AND DATE(visit_time) = '$today'
"))['count'] ?? 0;

$sales_today = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count,
           COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total,
           COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as withdrawals,
           COALESCE(SUM(amount), 0) as net_total
    FROM sales 
    WHERE outlet_id = $outlet_id AND DATE(payment_date) = '$today' AND payment_status = 'Paid'
"));

$pending_payments_today = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count, COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total 
    FROM sales 
    WHERE outlet_id = $outlet_id AND DATE(payment_date) = '$today' AND payment_status IN ('Pending', 'Unpaid')
"));

$agents_active = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role = 'agent' AND outlet_id = $outlet_id AND is_active = 1
"))['count'] ?? 0;

// 2. RECENT VISITORS (LIMITED TO 10)
$recent_visitors = mysqli_query($conn, "
    SELECT v.*, u.full_name as agent_name, s.name as service_name, p.name as purpose_name
    FROM visitors v
    LEFT JOIN users u ON v.agent_id = u.id
    LEFT JOIN services s ON v.service_id = s.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    WHERE v.outlet_id = $outlet_id
    ORDER BY v.visit_time DESC
    LIMIT 8
");

// 3. AGENT PERFORMANCE (LIMITED TO 5, WITH SUMMARY)
$agent_performance = mysqli_query($conn, "
    SELECT 
        u.id, u.full_name, u.shift,
        COUNT(DISTINCT v.id) as visitors,
        COUNT(DISTINCT s.id) as sales,
        COALESCE(SUM(CASE WHEN s.payment_status = 'Paid' AND s.amount > 0 THEN s.amount ELSE 0 END), 0) as collected,
        COALESCE(SUM(CASE WHEN s.payment_status = 'Paid' AND s.amount < 0 THEN ABS(s.amount) ELSE 0 END), 0) as withdrawals,
        COALESCE(SUM(CASE WHEN s.payment_status = 'Pending' AND s.amount > 0 THEN s.amount ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN s.payment_status = 'Unpaid' AND s.amount > 0 THEN s.amount ELSE 0 END), 0) as unpaid,
        ROUND(COUNT(DISTINCT s.id) / NULLIF(COUNT(DISTINCT v.id), 0) * 100, 1) as conversion
    FROM users u
    LEFT JOIN visitors v ON u.id = v.agent_id AND DATE(v.visit_time) BETWEEN '$date_from' AND '$date_to'
    LEFT JOIN sales s ON v.id = s.visitor_id AND " . sisDateWhere($conn, 's.payment_date', $date_from, $date_to) . "
    WHERE u.role = 'agent' AND u.outlet_id = $outlet_id AND u.is_active = 1
    GROUP BY u.id
    ORDER BY collected DESC
    LIMIT 8
");

// 4. OUTLET STOCK
$outlet_stock = mysqli_query($conn, "
    SELECT os.*, p.name as product_name, p.sku, p.selling_price, 
           p.low_stock_threshold, p.reorder_quantity,
           b.batch_number, b.expiry_date
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
    LEFT JOIN stock_batches b ON os.batch_id = b.id
    WHERE os.outlet_id = $outlet_id AND os.quantity > 0
    ORDER BY 
        CASE WHEN os.quantity <= p.low_stock_threshold THEN 0 ELSE 1 END,
        p.name
");

// 5. LOW STOCK ITEMS
$low_stock_items = mysqli_query($conn, "
    SELECT os.*, p.name as product_name, p.sku, p.low_stock_threshold, 
           p.reorder_quantity, b.batch_number
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
    LEFT JOIN stock_batches b ON os.batch_id = b.id
    WHERE os.outlet_id = $outlet_id 
    AND os.quantity <= p.low_stock_threshold
    ORDER BY os.quantity ASC
");

// 6. STOCK REQUESTS
$pending_requests = mysqli_query($conn, "
    SELECT sr.*, p.name as product_name, p.sku
    FROM stock_requests sr
    JOIN stock_products p ON sr.product_id = p.id
    WHERE sr.outlet_id = $outlet_id 
    AND sr.status IN ('pending', 'approved')
    ORDER BY 
        CASE sr.priority 
            WHEN 'critical' THEN 1
            WHEN 'urgent' THEN 2
            WHEN 'normal' THEN 3
        END,
        sr.request_date DESC
");

// 7. PRODUCTS FOR REQUEST FORM
$products = mysqli_query($conn, "
    SELECT p.*
    FROM stock_products p
    WHERE p.is_active = 1
    ORDER BY p.name
");

// 8. PENDING PAYMENTS (LIMITED TO 5)
$pending_payments = mysqli_query($conn, "
    SELECT s.*, v.name as visitor_name, v.contact, u.full_name as agent_name
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    WHERE s.outlet_id = $outlet_id AND s.payment_status IN ('Pending', 'Unpaid')
    ORDER BY s.payment_date DESC
    LIMIT 6
");

// 9. CASH RECONCILIATION STATUS
$recon_today = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM cash_reconciliation 
    WHERE outlet_id = $outlet_id AND reconciliation_date = '$today'
"))['count'] ?? 0;

// 10. VISITOR STATUS BREAKDOWN
$visitor_status = mysqli_query($conn, "
    SELECT status, COUNT(*) as count
    FROM visitors
    WHERE outlet_id = $outlet_id AND DATE(visit_time) = '$today'
    GROUP BY status
");

// Get agent performance summary for compact display
$agent_summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(DISTINCT u.id) as total_agents,
        COUNT(DISTINCT CASE WHEN v.id IS NOT NULL THEN u.id END) as agents_with_visitors,
        SUM(CASE WHEN s.payment_status = 'Paid' AND s.amount > 0 THEN s.amount ELSE 0 END) as total_collected
    FROM users u
    LEFT JOIN visitors v ON u.id = v.agent_id AND DATE(v.visit_time) BETWEEN '$date_from' AND '$date_to'
    LEFT JOIN sales s ON v.id = s.visitor_id AND " . sisDateWhere($conn, 's.payment_date', $date_from, $date_to) . "
    WHERE u.role = 'agent' AND u.outlet_id = $outlet_id AND u.is_active = 1
"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard - <?php echo $outlet['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

    
    <div class="container mb-4">
        <?php sisRenderDashboardAnnouncements($conn, 'Supervisor Announcements'); ?>
        <div class="sis-quick-action-strip mb-4">
            <div>
                <strong><?php echo e($outlet['name'] ?? 'Outlet'); ?></strong>
                <small><?php echo e(($outlet['location'] ?? '') . ' • ' . ($outlet['region'] ?? '')); ?></small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="add_sale.php" class="btn btn-danger"><i class="bi bi-plus-circle"></i> New Customer Sale</a>
                <a href="support.php" class="btn btn-outline-primary"><i class="bi bi-headset"></i> Complaint / Support</a>
            </div>
        </div>
        
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">From Date</label>
                    <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">To Date</label>
                    <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show small">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <?php if(mysqli_num_rows($low_stock_items) > 0): ?>
        <div class="alert alert-warning alert-sm small py-2 mb-3">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Low Stock Alert!</strong> <?php echo mysqli_num_rows($low_stock_items); ?> items need reordering.
            <a href="#stock" class="alert-link">View Stock</a>
        </div>
        <?php endif; ?>

        
        <div class="stat-grid">
            <div class="stat-card">
                <h6>Today's Visitors</h6>
                <h2><?php echo $visitors_today; ?></h2>
                <small>Total entries</small>
            </div>
            <div class="stat-card">
                <h6>Today's Sales</h6>
                <h2><?php echo $sales_today['count'] ?? 0; ?></h2>
                <small>D <?php echo number_format($sales_today['total'] ?? 0, 2); ?></small>
            </div>
            <div class="stat-card">
                <h6>Pending Payments</h6>
                <h2><?php echo $pending_payments_today['count'] ?? 0; ?></h2>
                <small>D <?php echo number_format($pending_payments_today['total'] ?? 0, 2); ?></small>
            </div>
            <div class="stat-card">
                <h6>Active Agents</h6>
                <h2><?php echo $agents_active; ?></h2>
                <small>On shift</small>
            </div>
        </div>

        
        <div class="stat-grid">
            <div class="stat-card">
                <h6>Products in Stock</h6>
                <h2><?php echo mysqli_num_rows($outlet_stock); ?></h2>
                <small>Different items</small>
            </div>
            <div class="stat-card">
                <h6>Low Stock Items</h6>
                <h2><?php echo mysqli_num_rows($low_stock_items); ?></h2>
                <small>Need reorder</small>
            </div>
            <div class="stat-card">
                <h6>Pending Requests</h6>
                <h2><?php echo mysqli_num_rows($pending_requests); ?></h2>
                <small>Awaiting approval</small>
            </div>
            <div class="stat-card">
                <h6>Reconciled Today</h6>
                <h2><?php echo $recon_today ? '✓' : '✗'; ?></h2>
                <small><?php echo $recon_today ? 'Done' : 'Pending'; ?></small>
            </div>
        </div>

        
        <div class="three-col">
            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-people"></i> Recent Visitors</span>
                    <a href="visitors.php" class="small view-all-link">View All →</a>
                </div>
                <div class="table-scroll-sm">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Visitor/Purpose</th>
                                <th>Agent</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($recent_visitors) > 0): ?>
                                <?php while($v = mysqli_fetch_assoc($recent_visitors)): ?>
                                <tr>
                                    <td class="text-muted small"><?php echo date('H:i', strtotime($v['visit_time'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($v['name'], 0, 20)); ?>
                                        <br><small class="text-muted"><?php echo $v['purpose_name'] ? htmlspecialchars(substr($v['purpose_name'], 0, 15)) : '-'; ?></small>
                                    </td>
                                    <td><?php echo $v['agent_name'] ? htmlspecialchars($v['agent_name']) : '-'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $v['status'])); ?>">
                                            <?php echo $v['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No recent visitors</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-bar-chart"></i> Agent Performance</span>
                    <span class="small text-muted"><?php echo $date_from; ?> → <?php echo $date_to; ?></span>
                </div>
                <div class="card-body">
                    <?php if(mysqli_num_rows($agent_performance) > 0): ?>
                        <?php while($a = mysqli_fetch_assoc($agent_performance)): ?>
                        <div class="agent-stats-mini">
                            <div>
                                <div class="agent-name"><?php echo htmlspecialchars($a['full_name']); ?></div>
                                <div class="agent-numbers">
                                    <span class="text-muted">👥 <?php echo $a['visitors']; ?> visits</span>
                                    <span class="text-muted ms-2">💰 <?php echo $a['sales']; ?> sales</span>
                                </div>
                            </div>
                            <div class="agent-collected">
                                D <?php echo number_format($a['collected'] ?? 0, 0); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($agent_performance) >= 8): ?>
                        <div class="text-center mt-2">
                            <a href="agent_performance.php" class="small">View all agents →</a>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-people"></i>
                            <p class="small mb-0">No agent data for selected period</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-cash"></i> Pending Payments</span>
                    <a href="pending_payments.php" class="small view-all-link">Manage →</a>
                </div>
                <div class="table-scroll-sm">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($pending_payments) > 0): ?>
                                <?php while($p = mysqli_fetch_assoc($pending_payments)): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars(substr($p['visitor_name'], 0, 20)); ?>
                                        <br><small class="text-muted"><?php echo $p['agent_name']; ?></small>
                                    </td>
                                    <td class="fw-bold">D <?php echo number_format($p['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $p['payment_status'] == 'Pending' ? 'badge-warning' : 'badge-danger'; ?>">
                                            <?php echo $p['payment_status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">No pending payments</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="two-col" id="stock">
            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-box"></i> Outlet Stock</span>
                    <span class="badge bg-info"><?php echo mysqli_num_rows($outlet_stock); ?> items</span>
                </div>
                <div class="table-scroll">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($outlet_stock) > 0): ?>
                                <?php while($item = mysqli_fetch_assoc($outlet_stock)): 
                                    $row_class = '';
                                    if ($item['quantity'] <= $item['low_stock_threshold']/2) {
                                        $row_class = 'critical-stock-row';
                                    } elseif ($item['quantity'] <= $item['low_stock_threshold']) {
                                        $row_class = 'low-stock-row';
                                    }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($item['product_name'], 0, 25)); ?></strong>
                                        <br><small class="text-muted"><?php echo $item['sku']; ?></small>
                                    </td>
                                    <td class="fw-bold"><?php echo $item['quantity']; ?></td>
                                    <td>
                                        <?php if($item['quantity'] == 0): ?>
                                            <span class="badge bg-danger">Out</span>
                                        <?php elseif($item['quantity'] <= $item['low_stock_threshold']/2): ?>
                                            <span class="badge bg-danger">Critical</span>
                                        <?php elseif($item['quantity'] <= $item['low_stock_threshold']): ?>
                                            <span class="badge bg-warning text-dark">Low</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Good</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">No stock items found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-send"></i> Stock Requests</span>
                    <span class="badge bg-warning"><?php echo mysqli_num_rows($pending_requests); ?> pending</span>
                </div>
                <div class="card-body">
                    
                    <form method="POST" class="mb-3 p-2 bg-light rounded">
                        <?php echo csrfField(); ?>
                        <div class="row g-2">
                            <div class="col-12">
                                <select class="form-select form-select-sm" name="product_id" required>
                                    <option value="">Select Product</option>
                                    <?php while($p = mysqli_fetch_assoc($products)): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo $p['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-5">
                                <input type="number" class="form-control form-control-sm" name="quantity" placeholder="Qty" required>
                            </div>
                            <div class="col-4">
                                <select class="form-select form-select-sm" name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-3">
                                <button type="submit" name="submit_request" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-send"></i> Request
                                </button>
                            </div>
                        </div>
                        <input type="date" class="form-control form-control-sm mt-2" name="required_by" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                        <textarea class="form-control form-control-sm mt-2" name="request_notes" rows="1" placeholder="Notes..."></textarea>
                    </form>

                    
                    <?php if(mysqli_num_rows($low_stock_items) > 0): ?>
                    <h6 class="mb-2 small fw-bold">Quick Reorder</h6>
                    <?php while($item = mysqli_fetch_assoc($low_stock_items)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1 p-1 border rounded small">
                        <div>
                            <strong><?php echo htmlspecialchars(substr($item['product_name'], 0, 20)); ?></strong>
                            <br><small class="text-muted">Stock: <?php echo $item['quantity']; ?></small>
                        </div>
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" 
                                onclick="quickRequest(<?php echo $item['product_id']; ?>, <?php echo $item['reorder_quantity']; ?>)">
                            Reorder <?php echo $item['reorder_quantity']; ?>
                        </button>
                    </div>
                    <?php endwhile; ?>
                    <?php endif; ?>

                    
                    <?php if(mysqli_num_rows($pending_requests) > 0): ?>
                    <h6 class="mb-2 mt-3 small fw-bold">Pending Requests</h6>
                    <div class="table-scroll-sm sis-u-4ca32a16">
                        <?php while($r = mysqli_fetch_assoc($pending_requests)): ?>
                        <div class="request-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars(substr($r['product_name'], 0, 20)); ?></strong>
                                <br><small class="text-muted">Qty: <?php echo $r['quantity']; ?></small>
                            </div>
                            <div>
                                <span class="badge badge-<?php echo $r['priority']; ?>"><?php echo ucfirst($r['priority']); ?></span>
                                <span class="badge badge-<?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        
        <div class="card mt-2">
            <div class="card-header">
                <i class="bi bi-lightning-charge"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="cash_reconciliation.php" class="action-btn">
                        <i class="bi bi-calculator"></i> Cash Reconciliation
                    </a>
                    <a href="agent_performance.php" class="action-btn">
                        <i class="bi bi-bar-chart"></i> Agent Report
                    </a>
                    <a href="pending_payments.php" class="action-btn">
                        <i class="bi bi-clock-history"></i> Pending Payments
                    </a>
                    <a href="visitors.php" class="action-btn">
                        <i class="bi bi-people"></i> Visitor Log
                    </a>
                    <a href="stock_requests.php" class="action-btn">
                        <i class="bi bi-box"></i> Request History
                    </a>
                    <a href="export_report.php?type=outlet&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="action-btn">
                        <i class="bi bi-download"></i> Export Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function quickRequest(productId, quantity) {
            document.querySelector('select[name="product_id"]').value = productId;
            document.querySelector('input[name="quantity"]').value = quantity;
            document.querySelector('select[name="priority"]').value = 'urgent';
            document.querySelector('form').submit();
        }
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
