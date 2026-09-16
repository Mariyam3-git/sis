<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Load settings
$settings = [];
$settings_query = mysqli_query($conn, "SELECT * FROM settings");
while($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currency_symbol = $settings['currency_symbol'] ?? 'D';

// Date filters
[$date_from, $date_to] = sisDateRange($_GET['date_from'] ?? null, $_GET['date_to'] ?? null, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));

// Check which columns exist in stock_transfers
$transfer_columns = [];
$check_columns = mysqli_query($conn, "SHOW COLUMNS FROM stock_transfers");
if ($check_columns) {
    while($col = mysqli_fetch_assoc($check_columns)) {
        $transfer_columns[] = $col['Field'];
    }
}

$has_received_at = in_array('received_at', $transfer_columns);
$has_transferred_by = in_array('transferred_by', $transfer_columns);
$has_received_by = in_array('received_by', $transfer_columns);

// ============================================
// EXISTING METRICS
// ============================================
$stats = [];

// Total outlets
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM outlets WHERE is_active = 1");
$stats['outlets'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Total agents
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND is_active = 1");
$stats['agents'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Total supervisors
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'supervisor' AND is_active = 1");
$stats['supervisors'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Period visitors
$period_visitors = mysqli_query($conn, "SELECT COUNT(*) as count FROM visitors WHERE " . sisDateWhere($conn, 'visit_time', $date_from, $date_to));
$stats['period_visitors'] = mysqli_fetch_assoc($period_visitors)['count'] ?? 0;

// Period sales
$period_sales = mysqli_query($conn, "SELECT COUNT(*) as count, " . sisPaidPositiveSum('sales') . " as total, " . sisPaidWithdrawalSum('sales') . " as withdrawals, " . sisPaidNetSum('sales') . " as net_total FROM sales WHERE " . sisDateWhere($conn, 'payment_date', $date_from, $date_to) . " AND payment_status = 'Paid'");
$sales_data = mysqli_fetch_assoc($period_sales);
$stats['period_sales'] = $sales_data['count'] ?? 0;
$stats['period_revenue'] = $sales_data['total'] ?? 0;
$stats['period_withdrawals'] = $sales_data['withdrawals'] ?? 0;
$stats['period_net_cash'] = $sales_data['net_total'] ?? 0;

// ============================================
// STOCK METRICS
// ============================================

// Total products
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_products WHERE is_active = 1");
$stats['total_products'] = mysqli_fetch_assoc($result)['count'] ?? 0;

// Total stock value across all outlets
$stock_value = mysqli_query($conn, "
    SELECT SUM(os.quantity * p.selling_price) as total_value 
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
");
$stats['stock_value'] = mysqli_fetch_assoc($stock_value)['total_value'] ?? 0;

// Low stock items count
$low_stock = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
    WHERE os.quantity <= p.low_stock_threshold
");
$stats['low_stock_count'] = mysqli_fetch_assoc($low_stock)['count'] ?? 0;

// Critical stock (half of threshold)
$critical_stock = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
    WHERE os.quantity <= (p.low_stock_threshold / 2) AND os.quantity > 0
");
$stats['critical_stock_count'] = mysqli_fetch_assoc($critical_stock)['count'] ?? 0;

// Out of stock items
$out_of_stock = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM stock_products p
    WHERE p.is_active = 1 
    AND NOT EXISTS (
        SELECT 1 FROM outlet_stock os 
        WHERE os.product_id = p.id AND os.quantity > 0
    )
");
$stats['out_of_stock_count'] = mysqli_fetch_assoc($out_of_stock)['count'] ?? 0;

// ============================================
// ENHANCED TRANSFER METRICS (with error handling)
// ============================================

// Pending transfers
$pending_transfers = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM stock_transfers 
    WHERE status = 'pending'
");
$stats['pending_transfers'] = mysqli_fetch_assoc($pending_transfers)['count'] ?? 0;

// Transfers awaiting receipt confirmation - FIXED with column check
if ($has_received_at) {
    $awaiting_receipt = mysqli_query($conn, "
        SELECT COUNT(*) as count 
        FROM stock_transfers 
        WHERE status = 'approved' AND received_at IS NULL
    ");
} else {
    // Fallback if received_at doesn't exist
    $awaiting_receipt = mysqli_query($conn, "
        SELECT COUNT(*) as count 
        FROM stock_transfers 
        WHERE status = 'approved'
    ");
}
$stats['awaiting_receipt'] = mysqli_fetch_assoc($awaiting_receipt)['count'] ?? 0;

// Completed today transfers - FIXED with column check
if ($has_received_at) {
    $completed_today = mysqli_query($conn, "
        SELECT COUNT(*) as count FROM stock_transfers 
        WHERE status = 'received' AND DATE(received_at) = CURDATE()
    ");
} else {
    $completed_today = mysqli_query($conn, "
        SELECT COUNT(*) as count FROM stock_transfers 
        WHERE status = 'received'
    ");
}
$stats['completed_today'] = mysqli_fetch_assoc($completed_today)['count'] ?? 0;

// Total transfers
$total_transfers = mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_transfers");
$stats['total_transfers'] = mysqli_fetch_assoc($total_transfers)['count'] ?? 0;

// Recent transfers with enhanced tracking - FIXED with conditional joins
if ($has_transferred_by && $has_received_by) {
    $recent_transfers = mysqli_query($conn, "
        SELECT st.*, 
               p.name as product_name,
               from_o.name as from_outlet_name,
               to_o.name as to_outlet_name,
               u1.full_name as transferred_by_name,
               u2.full_name as approved_by_name,
               u3.full_name as received_by_name
        FROM stock_transfers st
        JOIN stock_products p ON st.product_id = p.id
        JOIN outlets from_o ON st.from_outlet_id = from_o.id
        JOIN outlets to_o ON st.to_outlet_id = to_o.id
        LEFT JOIN users u1 ON st.transferred_by = u1.id
        LEFT JOIN users u2 ON st.approved_by = u2.id
        LEFT JOIN users u3 ON st.received_by = u3.id
        ORDER BY st.requested_at DESC
        LIMIT 10
    ");
} else {
    // Fallback if new columns don't exist
    $recent_transfers = mysqli_query($conn, "
        SELECT st.*, 
               p.name as product_name,
               from_o.name as from_outlet_name,
               to_o.name as to_outlet_name,
               u.full_name as requested_by_name
        FROM stock_transfers st
        JOIN stock_products p ON st.product_id = p.id
        JOIN outlets from_o ON st.from_outlet_id = from_o.id
        JOIN outlets to_o ON st.to_outlet_id = to_o.id
        LEFT JOIN users u ON st.requested_by = u.id
        ORDER BY st.requested_at DESC
        LIMIT 10
    ");
}

// Recent stock movements
$recent_movements = mysqli_query($conn, "
    SELECT sm.*, p.name as product_name, o.name as outlet_name, u.full_name as user_name
    FROM stock_movements sm
    JOIN stock_products p ON sm.product_id = p.id
    JOIN outlets o ON sm.outlet_id = o.id
    JOIN users u ON sm.created_by = u.id
    ORDER BY sm.created_at DESC
    LIMIT 10
");

// Low stock items for alerts
$low_stock_items = mysqli_query($conn, "
    SELECT os.*, p.name as product_name, p.sku, p.low_stock_threshold, o.name as outlet_name
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
    JOIN outlets o ON os.outlet_id = o.id
    WHERE os.quantity <= p.low_stock_threshold
    ORDER BY os.quantity ASC
    LIMIT 10
");

// Chart data for stock movements (last 7 days)
$stock_movement_labels = [];
$stock_movement_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stock_movement_labels[] = date('D', strtotime("-$i days"));
    
    $received = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT SUM(quantity) as total FROM stock_movements WHERE DATE(created_at) = '$date' AND movement_type = 'received'"
    ))['total'] ?? 0;
    
    $sold = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT SUM(quantity) as total FROM stock_movements WHERE DATE(created_at) = '$date' AND movement_type = 'sold'"
    ))['total'] ?? 0;
    
    $transferred = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT SUM(quantity) as total FROM stock_movements WHERE DATE(created_at) = '$date' AND movement_type = 'transferred'"
    ))['total'] ?? 0;
    
    $stock_movement_data['received'][] = $received;
    $stock_movement_data['sold'][] = $sold;
    $stock_movement_data['transferred'][] = $transferred;
}

// Existing chart data
$visitor_chart_data = [];
$visitor_labels = [];
$sales_chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $visitor_labels[] = date('D', strtotime("-$i days"));
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM visitors WHERE DATE(visit_time) = '$date'");
    $visitor_chart_data[] = mysqli_fetch_assoc($result)['count'] ?? 0;
    
    $result = mysqli_query($conn, "SELECT " . sisPaidPositiveSum('sales') . " as total FROM sales WHERE DATE(payment_date) = '$date' AND payment_status = 'Paid'");
    $sales_chart_data[] = mysqli_fetch_assoc($result)['total'] ?? 0;
}

// Top selling products
$top_products = mysqli_query($conn, "
    SELECT p.name, SUM(sm.quantity) as total_sold
    FROM stock_movements sm
    JOIN stock_products p ON sm.product_id = p.id
    WHERE sm.movement_type = 'sold' 
    AND DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY sm.product_id
    ORDER BY total_sold DESC
    LIMIT 5
");

// Outlet stock summary
$outlet_stock = mysqli_query($conn, "
    SELECT o.name, 
           COUNT(DISTINCT os.product_id) as products,
           SUM(os.quantity) as total_items,
           SUM(os.quantity * p.selling_price) as total_value,
           SUM(CASE WHEN os.quantity <= p.low_stock_threshold THEN 1 ELSE 0 END) as low_stock
    FROM outlets o
    LEFT JOIN outlet_stock os ON o.id = os.outlet_id
    LEFT JOIN stock_products p ON os.product_id = p.id
    WHERE o.is_active = 1
    GROUP BY o.id
    ORDER BY total_value DESC
    LIMIT 5
");

// Payment summary
$payment_summary = mysqli_query($conn, "
    SELECT payment_status, COUNT(*) as count,
           SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total,
           SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as withdrawals,
           SUM(amount) as net_total
    FROM sales 
    WHERE DATE(payment_date) BETWEEN '$date_from' AND '$date_to'
    GROUP BY payment_status
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
    
    

    
    <div class="main-content">
        
        <?php sisRenderDashboardAnnouncements($conn, 'Management Announcements'); ?>

        <div class="filter-bar">
            <form class="sis-u-6f321c75" method="GET">
                <div class="sis-u-611fa361">
                    <i class="bi bi-calendar-range text-muted"></i>
                    <input type="date" name="date_from" class="form-control sis-w-150" value="<?php echo $date_from; ?>">
                    <span>to</span>
                    <input type="date" name="date_to" class="form-control sis-w-150" value="<?php echo $date_to; ?>">
                </div>
                <button type="submit" class="btn btn-primary sis-u-e1831612">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary">Reset</a>
                <a href="reports.php" class="btn btn-success sis-u-dc310e06">
                    <i class="bi bi-file-bar-graph"></i> Detailed Reports
                </a>
            </form>
        </div>

        
        <?php if($stats['pending_transfers'] > 0 || $stats['awaiting_receipt'] > 0): ?>
        <div class="alert-banner info">
            <i class="bi bi-arrow-left-right sis-u-fa415b95"></i>
            <div class="sis-u-49cdf874">
                <strong>Transfer Updates!</strong> 
                <?php if($stats['pending_transfers'] > 0): ?>
                <span class="badge bg-warning me-2"><?php echo $stats['pending_transfers']; ?> pending approval</span>
                <?php endif; ?>
                <?php if($stats['awaiting_receipt'] > 0): ?>
                <span class="badge bg-info"><?php echo $stats['awaiting_receipt']; ?> awaiting receipt confirmation</span>
                <?php endif; ?>
                <a class="sis-u-5c26d385" href="stock_transfer.php">Manage Transfers →</a>
            </div>
        </div>
        <?php endif; ?>

        
        <?php if(($stats['low_stock_count'] ?? 0) > 0): ?>
        <div class="alert-banner <?php echo ($stats['critical_stock_count'] ?? 0) > 0 ? 'danger' : 'warning'; ?>">
            <i class="bi bi-exclamation-triangle-fill sis-u-fa415b95"></i>
            <div class="sis-u-49cdf874">
                <strong>Stock Alert!</strong> 
                <?php echo $stats['low_stock_count'] ?? 0; ?> items are low on stock 
                (<?php echo $stats['critical_stock_count'] ?? 0; ?> critical, <?php echo $stats['out_of_stock_count'] ?? 0; ?> out of stock).
                <a class="sis-u-5c26d385" href="stock_reports.php?type=low_stock">View Details →</a>
            </div>
        </div>
        <?php endif; ?>

        
        <div class="stat-grid">
            <div class="stat-card">
                <h6>Total Outlets</h6>
                <h2><?php echo $stats['outlets']; ?></h2>
                <small>Active branches</small>
            </div>
            <div class="stat-card">
                <h6>Total Agents</h6>
                <h2><?php echo $stats['agents']; ?></h2>
                <small>Sales staff</small>
            </div>
            <div class="stat-card">
                <h6>Period Visitors</h6>
                <h2><?php echo number_format($stats['period_visitors']); ?></h2>
                <small><?php echo $date_from; ?> to <?php echo $date_to; ?></small>
            </div>
            <div class="stat-card">
                <h6>Period Revenue</h6>
                <h2><?php echo $currency_symbol; ?> <?php echo number_format($stats['period_revenue'] ?? 0, 2); ?></h2>
                <small><?php echo $stats['period_sales']; ?> transactions</small>
            </div>
        </div>

        
        <div class="stat-grid">
            <div class="stat-card stock">
                <h6><i class="bi bi-box"></i> Total Products</h6>
                <h2><?php echo $stats['total_products']; ?></h2>
                <small>Active inventory items</small>
            </div>
            <div class="stat-card value">
                <h6><i class="bi bi-cash-stack"></i> Stock Value</h6>
                <h2><?php echo $currency_symbol; ?> <?php echo number_format($stats['stock_value'] ?? 0, 2); ?></h2>
                <small>Total inventory value</small>
            </div>
            <div class="stat-card low">
                <h6><i class="bi bi-exclamation-triangle"></i> Low Stock</h6>
                <h2><?php echo $stats['low_stock_count'] ?? 0; ?></h2>
                <small>Items below threshold</small>
            </div>
            <div class="stat-card critical">
                <h6><i class="bi bi-x-circle"></i> Critical/Out</h6>
                <h2><?php echo ($stats['critical_stock_count'] ?? 0) + ($stats['out_of_stock_count'] ?? 0); ?></h2>
                <small><?php echo $stats['out_of_stock_count'] ?? 0; ?> out of stock</small>
            </div>
        </div>

        
        <div class="stat-grid">
            <div class="stat-card transfer">
                <h6><i class="bi bi-arrow-left-right"></i> Pending Transfers</h6>
                <h2><?php echo $stats['pending_transfers'] ?? 0; ?></h2>
                <small>Awaiting approval</small>
            </div>
            <div class="stat-card transfer">
                <h6><i class="bi bi-clock-history"></i> Awaiting Receipt</h6>
                <h2><?php echo $stats['awaiting_receipt'] ?? 0; ?></h2>
                <small>Need confirmation</small>
            </div>
            <div class="stat-card transfer">
                <h6><i class="bi bi-check-circle"></i> Completed Today</h6>
                <h2><?php echo $stats['completed_today'] ?? 0; ?></h2>
                <small>Transfers received</small>
            </div>
            <div class="stat-card value">
                <h6><i class="bi bi-truck"></i> Total Transfers</h6>
                <h2><?php echo $stats['total_transfers'] ?? 0; ?></h2>
                <small>All time</small>
            </div>
        </div>

        
        <div class="chart-row">
            
            <div class="chart-card">
                <h5><i class="bi bi-graph-up sis-u-002e4b71"></i> Visitors & Revenue (7 Days)</h5>
                <canvas id="visitorChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h5><i class="bi bi-arrow-left-right sis-u-4edeeeb3"></i> Stock Movements (7 Days)</h5>
                <canvas id="stockChart"></canvas>
            </div>
        </div>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-exclamation-triangle sis-u-00c66c06"></i> Low Stock Items</span>
                    <a class="sis-u-96187b2b" href="stock_reports.php?type=low_stock">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if($low_stock_items && mysqli_num_rows($low_stock_items) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Outlet</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($item = mysqli_fetch_assoc($low_stock_items)): 
                                $status_class = $item['quantity'] == 0 ? 'danger' : ($item['quantity'] <= $item['low_stock_threshold']/2 ? 'danger' : 'warning');
                                $status_text = $item['quantity'] == 0 ? 'Out' : ($item['quantity'] <= $item['low_stock_threshold']/2 ? 'Critical' : 'Low');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo $item['product_name']; ?></strong>
                                    <small class="d-block text-muted"><?php echo $item['sku']; ?></small>
                                </td>
                                <td><?php echo $item['outlet_name']; ?></td>
                                <td class="fw-bold"><?php echo $item['quantity']; ?> / <?php echo $item['low_stock_threshold']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center py-4 sis-u-4632cffb">
                        <i class="bi bi-check-circle sis-u-1e52ae17"></i>
                        <p class="mt-2">No low stock items</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-arrow-left-right sis-u-335ab84e"></i> Recent Transfers</span>
                    <a class="sis-u-96187b2b" href="stock_transfer.php">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if($recent_transfers && mysqli_num_rows($recent_transfers) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>From → To</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Track</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($transfer = mysqli_fetch_assoc($recent_transfers)): ?>
                            <tr>
                                <td><?php echo date('d/m H:i', strtotime($transfer['requested_at'])); ?></td>
                                <td><?php echo $transfer['product_name']; ?></td>
                                <td>
                                    <small><?php echo $transfer['from_outlet_name']; ?> → <?php echo $transfer['to_outlet_name']; ?></small>
                                </td>
                                <td class="fw-bold"><?php echo $transfer['quantity']; ?></td>
                                <td>
                                    <?php if($transfer['status'] == 'pending'): ?>
                                        <span class="badge status-pending">Pending</span>
                                    <?php elseif($transfer['status'] == 'approved'): ?>
                                        <span class="badge status-approved">Approved</span>
                                    <?php elseif($transfer['status'] == 'received'): ?>
                                        <span class="badge status-received">Received</span>
                                    <?php elseif($transfer['status'] == 'rejected'): ?>
                                        <span class="badge status-rejected">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo $transfer['status']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(isset($transfer['transferred_by_name']) || isset($transfer['approved_by_name']) || isset($transfer['received_by_name'])): ?>
                                    <i class="bi bi-info-circle" 
                                       title="Requested: <?php echo $transfer['requested_by_name'] ?? $transfer['transferred_by_name'] ?? 'N/A'; ?><?php echo isset($transfer['approved_by_name']) ? ' | Approved: '.$transfer['approved_by_name'] : ''; ?><?php echo isset($transfer['received_by_name']) ? ' | Received: '.$transfer['received_by_name'] : ''; ?>"
                                      ></i>
                                    <?php else: ?>
                                    <i class="bi bi-info-circle text-muted"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center py-4 sis-u-4632cffb">
                        <i class="bi bi-arrow-left-right sis-u-1e52ae17"></i>
                        <p class="mt-2">No recent transfers</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-clock-history"></i> Recent Stock Movements</span>
                <a class="sis-u-96187b2b" href="stock_reports.php?type=movements">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Outlet</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_movements && mysqli_num_rows($recent_movements) > 0): ?>
                            <?php while($mov = mysqli_fetch_assoc($recent_movements)): ?>
                            <tr>
                                <td><?php echo date('H:i d/m', strtotime($mov['created_at'])); ?></td>
                                <td><?php echo $mov['outlet_name']; ?></td>
                                <td><?php echo $mov['product_name']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $mov['movement_type'] == 'received' ? 'success' : 
                                            ($mov['movement_type'] == 'sold' ? 'primary' : 
                                            ($mov['movement_type'] == 'transferred' ? 'info' : 'warning')); 
                                    ?>">
                                        <?php echo ucfirst($mov['movement_type']); ?>
                                    </span>
                                </td>
                                <td class="fw-bold"><?php echo $mov['quantity']; ?></td>
                                <td><?php echo $mov['user_name']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 sis-u-4632cffb">
                                    No stock movements found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-building"></i> Stock by Outlet</span>
                <span>Top 5 by value</span>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Outlet</th>
                            <th>Products</th>
                            <th>Total Items</th>
                            <th>Stock Value</th>
                            <th>Low Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($os = mysqli_fetch_assoc($outlet_stock)): ?>
                        <tr>
                            <td><strong><?php echo $os['name']; ?></strong></td>
                            <td><?php echo $os['products'] ?? 0; ?></td>
                            <td><?php echo number_format($os['total_items'] ?? 0); ?></td>
                            <td class="fw-bold text-success"><?php echo $currency_symbol; ?> <?php echo number_format($os['total_value'] ?? 0, 2); ?></td>
                            <td>
                                <?php if(($os['low_stock'] ?? 0) > 0): ?>
                                <span class="badge bg-warning text-dark"><?php echo $os['low_stock']; ?> items</span>
                                <?php else: ?>
                                <span class="badge bg-success">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-lightning-charge"></i> Quick Actions</span>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="outlets.php?action=add" class="action-btn">
                        <i class="bi bi-plus-circle sis-u-e118d5ea"></i>
                        Add Outlet
                    </a>
                    <a href="agents.php?action=add" class="action-btn">
                        <i class="bi bi-person-plus sis-u-4edeeeb3"></i>
                        Add Agent
                    </a>
                    <a href="stock_products.php?action=add" class="action-btn">
                        <i class="bi bi-box sis-u-31bd35f5"></i>
                        Add Product
                    </a>
                    <a href="stock_receive.php" class="action-btn">
                        <i class="bi bi-box-arrow-in-down sis-u-335ab84e"></i>
                        Receive Stock
                    </a>
                    <a href="stock_transfer.php" class="action-btn">
                        <i class="bi bi-arrow-left-right sis-u-00c66c06"></i>
                        Transfer Stock
                    </a>
                    <a href="reports.php" class="action-btn">
                        <i class="bi bi-file-bar-graph sis-u-7d8ba3b6"></i>
                        Generate Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>        new Chart(document.getElementById('visitorChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($visitor_labels); ?>,
                datasets: [
                    {
                        label: 'Visitors',
                        data: <?php echo json_encode($visitor_chart_data); ?>,
                        borderColor: '#E30613',
                        backgroundColor: 'rgba(227,6,19,0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Revenue (<?php echo $currency_symbol; ?>)',
                        data: <?php echo json_encode($sales_chart_data); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40,167,69,0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Visitors' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Revenue' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
        new Chart(document.getElementById('stockChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($stock_movement_labels); ?>,
                datasets: [
                    {
                        label: 'Received',
                        data: <?php echo json_encode($stock_movement_data['received']); ?>,
                        backgroundColor: '#28a745',
                        borderRadius: 5
                    },
                    {
                        label: 'Sold',
                        data: <?php echo json_encode($stock_movement_data['sold']); ?>,
                        backgroundColor: '#E30613',
                        borderRadius: 5
                    },
                    {
                        label: 'Transferred',
                        data: <?php echo json_encode($stock_movement_data['transferred']); ?>,
                        backgroundColor: '#6610f2',
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Quantity' }
                    }
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>