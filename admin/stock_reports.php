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
$pending_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_requests WHERE status = 'pending'"))['count'] ?? 0;

// Report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$outlet_filter = isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : 0;
$product_filter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Set date range based on report type
switch($report_type) {
    case 'daily':
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        $period_label = 'Today: ' . date('l, F j, Y');
        break;
    case 'weekly':
        $date_from = date('Y-m-d', strtotime('monday this week'));
        $date_to = date('Y-m-d', strtotime('sunday this week'));
        $period_label = 'Week: ' . date('M j', strtotime($date_from)) . ' - ' . date('M j, Y', strtotime($date_to));
        break;
    case 'monthly':
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
        $period_label = 'Month: ' . date('F Y');
        break;
    case 'custom':
        $period_label = date('M j, Y', strtotime($date_from)) . ' - ' . date('M j, Y', strtotime($date_to));
        break;
    default:
        // Default to daily if unknown type
        $report_type = 'daily';
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        $period_label = 'Today: ' . date('l, F j, Y');
        break;
}

// Get all outlets for filter
$outlets = mysqli_query($conn, "SELECT * FROM outlets WHERE is_active = 1 ORDER BY name");

// Get all products for filter
$products = mysqli_query($conn, "SELECT * FROM stock_products WHERE is_active = 1 ORDER BY name");

// Build WHERE clause for movements
$movement_where = [];
if ($outlet_filter > 0) {
    $movement_where[] = "sm.outlet_id = $outlet_filter";
}
if ($product_filter > 0) {
    $movement_where[] = "sm.product_id = $product_filter";
}
$movement_where[] = "DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to'";
$movement_where_clause = !empty($movement_where) ? "WHERE " . implode(" AND ", $movement_where) : "";

// ============================================
// SUMMARY STATISTICS
// ============================================
$summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_movements,
        SUM(CASE WHEN movement_type = 'received' THEN quantity ELSE 0 END) as total_received,
        SUM(CASE WHEN movement_type = 'sold' THEN quantity ELSE 0 END) as total_sold,
        SUM(CASE WHEN movement_type = 'transferred' THEN quantity ELSE 0 END) as total_transferred,
        COUNT(DISTINCT product_id) as products_moved,
        COUNT(DISTINCT outlet_id) as outlets_active
    FROM stock_movements sm
    $movement_where_clause
"));

// ============================================
// STOCK MOVEMENTS REPORT
// ============================================
$movements = mysqli_query($conn, "
    SELECT sm.*, 
           o.name as outlet_name,
           p.name as product_name,
           p.sku,
           u.full_name as user_name
    FROM stock_movements sm
    JOIN outlets o ON sm.outlet_id = o.id
    JOIN stock_products p ON sm.product_id = p.id
    JOIN users u ON sm.created_by = u.id
    $movement_where_clause
    ORDER BY sm.created_at DESC
");

// ============================================
// CURRENT STOCK SUMMARY BY OUTLET
// ============================================
$stock_by_outlet = mysqli_query($conn, "
    SELECT 
        o.id,
        o.name as outlet_name,
        COUNT(DISTINCT os.product_id) as total_products,
        SUM(os.quantity) as total_items,
        SUM(os.quantity * p.selling_price) as total_value,
        SUM(CASE WHEN os.quantity <= p.low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count
    FROM outlets o
    LEFT JOIN outlet_stock os ON o.id = os.outlet_id
    LEFT JOIN stock_products p ON os.product_id = p.id
    WHERE o.is_active = 1
    GROUP BY o.id
    ORDER BY total_value DESC
");

// ============================================
// PRODUCT PERFORMANCE
// ============================================
$product_performance = mysqli_query($conn, "
    SELECT 
        p.id,
        p.name as product_name,
        p.sku,
        SUM(CASE WHEN sm.movement_type = 'received' AND DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to' THEN sm.quantity ELSE 0 END) as received,
        SUM(CASE WHEN sm.movement_type = 'sold' AND DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to' THEN sm.quantity ELSE 0 END) as sold,
        SUM(CASE WHEN sm.movement_type = 'transferred' AND DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to' THEN sm.quantity ELSE 0 END) as transferred,
        (SELECT SUM(quantity) FROM outlet_stock WHERE product_id = p.id) as current_stock,
        p.low_stock_threshold
    FROM stock_products p
    LEFT JOIN stock_movements sm ON p.id = sm.product_id
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY sold DESC
");

// ============================================
// DAILY TREND DATA
// ============================================
$daily_trend = mysqli_query($conn, "
    SELECT 
        DATE(created_at) as date,
        SUM(CASE WHEN movement_type = 'received' THEN quantity ELSE 0 END) as received,
        SUM(CASE WHEN movement_type = 'sold' THEN quantity ELSE 0 END) as sold,
        COUNT(*) as total_movements
    FROM stock_movements
    WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY DATE(created_at)
    ORDER BY date
");

// ============================================
// TOP SELLING PRODUCTS
// ============================================
$top_selling = mysqli_query($conn, "
    SELECT 
        p.name,
        p.sku,
        SUM(sm.quantity) as total_sold,
        COUNT(DISTINCT sm.outlet_id) as outlets_sold
    FROM stock_movements sm
    JOIN stock_products p ON sm.product_id = p.id
    WHERE sm.movement_type = 'sold'
    AND DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY sm.product_id
    ORDER BY total_sold DESC
    LIMIT 10
");

// ============================================
// LOW STOCK ITEMS
// ============================================
$low_stock_items = mysqli_query($conn, "
    SELECT 
        o.name as outlet_name,
        p.name as product_name,
        p.sku,
        os.quantity,
        p.low_stock_threshold,
        (p.low_stock_threshold - os.quantity) as needed
    FROM outlet_stock os
    JOIN outlets o ON os.outlet_id = o.id
    JOIN stock_products p ON os.product_id = p.id
    WHERE os.quantity <= p.low_stock_threshold
    ORDER BY os.quantity ASC
    LIMIT 20
");

// Handle CSV Export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock_report_' . $report_type . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($output, ['STOCK REPORT - ' . strtoupper($report_type)]);
    fputcsv($output, ['Period:', $period_label]);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Summary
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Movements', $summary['total_movements'] ?? 0]);
    fputcsv($output, ['Total Received', $summary['total_received'] ?? 0]);
    fputcsv($output, ['Total Sold', $summary['total_sold'] ?? 0]);
    fputcsv($output, ['Total Transferred', $summary['total_transferred'] ?? 0]);
    fputcsv($output, ['Products Moved', $summary['products_moved'] ?? 0]);
    fputcsv($output, ['Active Outlets', $summary['outlets_active'] ?? 0]);
    fputcsv($output, []);
    
    // Movements
    fputcsv($output, ['STOCK MOVEMENTS']);
    fputcsv($output, ['Date', 'Outlet', 'Product', 'SKU', 'Type', 'Quantity', 'User']);
    
    mysqli_data_seek($movements, 0);
    while($row = mysqli_fetch_assoc($movements)) {
        fputcsv($output, [
            $row['created_at'],
            $row['outlet_name'],
            $row['product_name'],
            $row['sku'],
            $row['movement_type'],
            $row['quantity'],
            $row['user_name']
        ]);
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Reports - Comium GM</title>
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
        <div class="page-header">
            <h3><i class="bi bi-file-bar-graph sis-u-002e4b71"></i> Stock Reports</h3>
            <span class="date-badge"><?php echo date('l, d M Y'); ?></span>
        </div>

        
        <div class="report-controls">
            <div class="report-tabs">
                <a href="?type=daily" class="tab-btn <?php echo $report_type == 'daily' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-day"></i> Daily
                </a>
                <a href="?type=weekly" class="tab-btn <?php echo $report_type == 'weekly' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-week"></i> Weekly
                </a>
                <a href="?type=monthly" class="tab-btn <?php echo $report_type == 'monthly' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-month"></i> Monthly
                </a>
                <a href="?type=custom&date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="tab-btn <?php echo $report_type == 'custom' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-range"></i> Custom
                </a>
            </div>

            <form method="GET" class="filter-grid">
                <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                
                <?php if($report_type == 'custom'): ?>
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <?php endif; ?>
                
                <div class="filter-group">
                    <label>Outlet</label>
                    <select class="form-select" name="outlet_id">
                        <option value="0">All Outlets</option>
                        <?php while($o = mysqli_fetch_assoc($outlets)): ?>
                        <option value="<?php echo $o['id']; ?>" <?php echo $outlet_filter == $o['id'] ? 'selected' : ''; ?>>
                            <?php echo $o['name']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Product</label>
                    <select class="form-select" name="product_id">
                        <option value="0">All Products</option>
                        <?php while($p = mysqli_fetch_assoc($products)): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo $p['name']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="stock_reports.php?type=<?php echo $report_type; ?>" class="btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                <span class="period-badge">
                    <i class="bi bi-calendar3"></i> <?php echo $period_label; ?>
                </span>
                <a href="?export=1&type=<?php echo $report_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&outlet_id=<?php echo $outlet_filter; ?>&product_id=<?php echo $product_filter; ?>" class="btn-success">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Movements</h6>
                <h2><?php echo $summary['total_movements'] ?? 0; ?></h2>
                <small>Transactions</small>
            </div>
            <div class="stat-card">
                <h6>Received</h6>
                <h2><?php echo $summary['total_received'] ?? 0; ?></h2>
                <small>Units in</small>
            </div>
            <div class="stat-card">
                <h6>Sold</h6>
                <h2><?php echo $summary['total_sold'] ?? 0; ?></h2>
                <small>Units out</small>
            </div>
            <div class="stat-card">
                <h6>Transferred</h6>
                <h2><?php echo $summary['total_transferred'] ?? 0; ?></h2>
                <small>Between outlets</small>
            </div>
        </div>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-graph-up"></i> Daily Movement Trend</span>
                </div>
                <div class="card-body">
                    <canvas class="sis-u-d19b1670" id="trendChart"></canvas>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-trophy"></i> Top Selling Products</span>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sold</th>
                                <th>Outlets</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($t = mysqli_fetch_assoc($top_selling)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $t['name']; ?></strong>
                                    <br><small><?php echo $t['sku']; ?></small>
                                </td>
                                <td class="fw-bold"><?php echo $t['total_sold']; ?></td>
                                <td><?php echo $t['outlets_sold']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-building"></i> Stock by Outlet</span>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Outlet</th>
                            <th>Products</th>
                            <th>Total Items</th>
                            <th>Total Value</th>
                            <th>Low Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($s = mysqli_fetch_assoc($stock_by_outlet)): ?>
                        <tr>
                            <td><strong><?php echo $s['outlet_name']; ?></strong></td>
                            <td><?php echo $s['total_products'] ?? 0; ?></td>
                            <td><?php echo number_format($s['total_items'] ?? 0); ?></td>
                            <td class="fw-bold text-success">D <?php echo number_format($s['total_value'] ?? 0, 2); ?></td>
                            <td>
                                <?php if(($s['low_stock_count'] ?? 0) > 0): ?>
                                <span class="badge bg-warning text-dark"><?php echo $s['low_stock_count']; ?> items</span>
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
                <span><i class="bi bi-box"></i> Product Performance</span>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Received</th>
                            <th>Sold</th>
                            <th>Transferred</th>
                            <th>Current Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($p = mysqli_fetch_assoc($product_performance)): 
                            $status_class = 'success';
                            $status_text = 'Normal';
                            $row_class = '';
                            
                            $current_stock = $p['current_stock'] ?? 0;
                            $low_stock_threshold = $p['low_stock_threshold'] ?? 10;
                            
                            if ($current_stock == 0) {
                                $status_class = 'danger';
                                $status_text = 'Out';
                                $row_class = 'critical-stock-row';
                            } elseif ($current_stock <= $low_stock_threshold) {
                                $status_class = 'warning';
                                $status_text = 'Low';
                                $row_class = 'low-stock-row';
                            }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td>
                                <strong><?php echo $p['product_name']; ?></strong>
                                <br><small><?php echo $p['sku']; ?></small>
                            </td>
                            <td class="text-success"><?php echo $p['received'] ?? 0; ?></td>
                            <td class="text-primary"><?php echo $p['sold'] ?? 0; ?></td>
                            <td class="text-info"><?php echo $p['transferred'] ?? 0; ?></td>
                            <td class="fw-bold"><?php echo $current_stock; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-exclamation-triangle sis-u-00c66c06"></i> Low Stock Items</span>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Outlet</th>
                            <th>Product</th>
                            <th>Current</th>
                            <th>Threshold</th>
                            <th>Needed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($l = mysqli_fetch_assoc($low_stock_items)): ?>
                        <tr>
                            <td><?php echo $l['outlet_name']; ?></td>
                            <td>
                                <strong><?php echo $l['product_name']; ?></strong>
                                <br><small><?php echo $l['sku']; ?></small>
                            </td>
                            <td class="fw-bold text-danger"><?php echo $l['quantity']; ?></td>
                            <td><?php echo $l['low_stock_threshold']; ?></td>
                            <td class="text-warning"><?php echo $l['needed']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-clock-history"></i> Stock Movements</span>
                <span class="badge bg-secondary"><?php echo mysqli_num_rows($movements); ?> records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Outlet</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($movements) > 0): ?>
                                <?php while($m = mysqli_fetch_assoc($movements)): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                    <td><?php echo $m['outlet_name']; ?></td>
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
                                    <td class="fw-bold"><?php echo $m['quantity']; ?></td>
                                    <td><?php echo $m['user_name']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No stock movements found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>        const trendLabels = [];
        const receivedData = [];
        const soldData = [];
        
        <?php 
        mysqli_data_seek($daily_trend, 0);
        while($t = mysqli_fetch_assoc($daily_trend)): 
        ?>
        trendLabels.push('<?php echo date('d M', strtotime($t['date'])); ?>');
        receivedData.push(<?php echo $t['received'] ?? 0; ?>);
        soldData.push(<?php echo $t['sold'] ?? 0; ?>);
        <?php endwhile; ?>

        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Received',
                        data: receivedData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40,167,69,0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Sold',
                        data: soldData,
                        borderColor: '#E30613',
                        backgroundColor: 'rgba(227,6,19,0.1)',
                        tension: 0.4,
                        fill: true
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
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>