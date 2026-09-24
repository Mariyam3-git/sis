<?php
require_once '../config.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get stock summary with IN/OUT/REMAINING calculation
$stock_summary = mysqli_query($conn, "
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.selling_price,
        p.low_stock_threshold,
        COALESCE(SUM(CASE WHEN sm.movement_type IN ('received', 'received_from_warehouse') THEN sm.quantity ELSE 0 END), 0) as stock_in,
        COALESCE(SUM(CASE WHEN sm.movement_type IN ('sold', 'transferred', 'released_to_outlet', 'damaged') THEN sm.quantity ELSE 0 END), 0) as stock_out,
        COALESCE(SUM(CASE WHEN sm.movement_type IN ('received', 'received_from_warehouse') THEN sm.quantity ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN sm.movement_type IN ('sold', 'transferred', 'released_to_outlet', 'damaged') THEN sm.quantity ELSE 0 END), 0) as remaining
    FROM stock_products p
    LEFT JOIN stock_movements sm ON p.id = sm.product_id
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY remaining ASC
");

// Calculate totals
$total_products = mysqli_num_rows($stock_summary);
$total_stock_in = 0;
$total_stock_out = 0;
$total_remaining = 0;

$products_data = [];
while($row = mysqli_fetch_assoc($stock_summary)) {
    $products_data[] = $row;
    $total_stock_in += $row['stock_in'];
    $total_stock_out += $row['stock_out'];
    $total_remaining += $row['remaining'];
}

// Get low stock items
$low_stock_items = array_filter($products_data, function($item) {
    $threshold = $item['low_stock_threshold'] ?? 10;
    return $item['remaining'] <= $threshold;
});

// Get recent movements
$recent_movements = mysqli_query($conn, "
    SELECT sm.*, p.name as product_name, o.name as outlet_name, u.full_name as user_name
    FROM stock_movements sm
    JOIN stock_products p ON sm.product_id = p.id
    JOIN outlets o ON sm.outlet_id = o.id
    JOIN users u ON sm.created_by = u.id
    ORDER BY sm.created_at DESC
    LIMIT 30
");

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Dashboard - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --comium-red: #E30613;
            --comium-dark: #333;
            --comium-light: #F5F5F5;
            --sidebar-width: 250px;
        }
        body {
            background: var(--comium-light);
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }
        .sidebar {
            position: fixed;
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            padding: 20px 0;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--comium-red);
        }
        .nav-section {
            margin-top: 20px;
        }
        .nav-section-title {
            padding: 0 20px;
            font-size: 11px;
            text-transform: uppercase;
            color: #999;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--comium-dark);
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active {
            background: var(--comium-red);
            color: white;
        }
        .nav-link i {
            margin-right: 10px;
            width: 20px;
            font-size: 18px;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px 30px;
            min-height: 100vh;
        }
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--comium-red);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .page-header h3 {
            margin: 0;
            font-size: 24px;
            color: var(--comium-dark);
        }
        .date-badge {
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-left: 4px solid;
        }
        .stat-card.total { border-left-color: var(--comium-red); }
        .stat-card.in { border-left-color: #28a745; }
        .stat-card.out { border-left-color: #dc3545; }
        .stat-card.remaining { border-left-color: #17a2b8; }
        .stat-card h6 {
            color: #666;
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .stat-card h3 {
            font-size: 28px;
            margin: 0;
        }
        .stat-card.total h3 { color: var(--comium-red); }
        .stat-card.in h3 { color: #28a745; }
        .stat-card.out h3 { color: #dc3545; }
        .stat-card.remaining h3 { color: #17a2b8; }
        .stat-card small { color: #999; font-size: 11px; }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background: white;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-body {
            padding: 20px;
        }
        .card-body.p-0 {
            padding: 0;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th {
            text-align: left;
            padding: 12px 15px;
            background: #f8f9fa;
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        .stock-in { color: #28a745; font-weight: bold; }
        .stock-out { color: #dc3545; font-weight: bold; }
        .stock-remaining { color: #17a2b8; font-weight: bold; }
        
        .low-stock-row { background-color: #fff3cd !important; }
        .critical-stock-row { background-color: #f8d7da !important; }
        
        .btn-primary {
            background: var(--comium-red);
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
        }
        .btn-primary:hover { background: #b3040f; }
        .btn-success { background: #28a745; border: none; }
        .btn-danger { background: #dc3545; border: none; }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content {
                margin-left: 0;
                padding: 70px 15px 20px 15px;
            }
            .menu-toggle { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i> Menu
    </button>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">COMIUM</div>
            <small style="color: #999;">Admin Panel</small>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">MAIN</div>
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="outlets.php" class="nav-link"><i class="bi bi-building"></i> Outlets</a>
            <a href="agents.php" class="nav-link"><i class="bi bi-people"></i> Agents</a>
            <a href="supervisors.php" class="nav-link"><i class="bi bi-person-badge"></i> Supervisors</a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">SERVICES & CONFIGURATION</div>
            <a href="services.php" class="nav-link"><i class="bi bi-grid"></i> Services</a>
            <a href="purposes.php" class="nav-link"><i class="bi bi-tags"></i> Visit Purposes</a>
            <a href="purpose_categories.php" class="nav-link"><i class="bi bi-folder"></i> Purpose Categories</a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">PERFORMANCE</div>
            <a href="targets.php" class="nav-link"><i class="bi bi-bullseye"></i> Targets</a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">STOCK MANAGEMENT</div>
            <a href="stock_dashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Stock Dashboard</a>
            <a href="stock_products.php" class="nav-link"><i class="bi bi-box"></i> Products</a>
            <a href="stock_categories.php" class="nav-link"><i class="bi bi-tags"></i> Categories</a>
            <a href="stock_receive.php" class="nav-link"><i class="bi bi-box-arrow-in-down"></i> Receive Stock</a>
            <a href="stock_sale.php" class="nav-link"><i class="bi bi-cart"></i> Sell Stock</a>
            <a href="stock_transfer.php" class="nav-link"><i class="bi bi-arrow-left-right"></i> Transfer</a>
            <a href="stock_adjust.php" class="nav-link"><i class="bi bi-pencil-square"></i> Adjust</a>
            <a href="stock_reports.php" class="nav-link"><i class="bi bi-file-bar-graph"></i> Reports</a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">REPORTS</div>
            <a href="reports.php" class="nav-link"><i class="bi bi-file-earmark-text"></i> Sales Reports</a>
            <a href="visitors.php" class="nav-link"><i class="bi bi-person-lines-fill"></i> Visitor Log</a>
            <a href="sales.php" class="nav-link"><i class="bi bi-cash"></i> Transactions</a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">SYSTEM</div>
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
            <a href="../logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h3><i class="bi bi-boxes" style="color: var(--comium-red); margin-right: 10px;"></i>Stock Management Dashboard</h3>
            <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h6>Total Products</h6>
                <h3><?php echo $total_products; ?></h3>
                <small>Active items</small>
            </div>
            <div class="stat-card in">
                <h6>Total Stock In</h6>
                <h3><?php echo $total_stock_in; ?></h3>
                <small>Units received</small>
            </div>
            <div class="stat-card out">
                <h6>Total Stock Out</h6>
                <h3><?php echo $total_stock_out; ?></h3>
                <small>Units sold/used</small>
            </div>
            <div class="stat-card remaining">
                <h6>Remaining Stock</h6>
                <h3><?php echo $total_remaining; ?></h3>
                <small>Current inventory</small>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if(count($low_stock_items) > 0): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Low Stock Alert!</strong> <?php echo count($low_stock_items); ?> item(s) are below threshold.
        </div>
        <?php endif; ?>

        <!-- Stock Summary Table -->
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-list-ul"></i> Stock Summary (In - Out = Remaining)</span>
                <div>
                    <a href="stock_receive.php" class="btn btn-success btn-sm">
                        <i class="bi bi-plus-circle"></i> Receive Stock
                    </a>
                    <a href="stock_sale.php" class="btn btn-danger btn-sm">
                        <i class="bi bi-cart"></i> Sell Stock
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Stock In</th>
                                <th>Stock Out</th>
                                <th>Remaining</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products_data as $item): 
                                $remaining = $item['remaining'];
                                $threshold = $item['low_stock_threshold'] ?? 10;
                                $row_class = '';
                                $status_text = 'Good';
                                $status_class = 'success';
                                
                                if($remaining <= 0) {
                                    $row_class = 'critical-stock-row';
                                    $status_text = 'Out of Stock';
                                    $status_class = 'danger';
                                } elseif($remaining <= $threshold/2) {
                                    $row_class = 'critical-stock-row';
                                    $status_text = 'Critical';
                                    $status_class = 'danger';
                                } elseif($remaining <= $threshold) {
                                    $row_class = 'low-stock-row';
                                    $status_text = 'Low';
                                    $status_class = 'warning';
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($item['sku']); ?></small>
                                 </div>
                                <td><?php echo htmlspecialchars($item['sku']); ?></div>
                                <td class="stock-in"><?php echo $item['stock_in']; ?></div>
                                <td class="stock-out"><?php echo $item['stock_out']; ?></div>
                                <td class="stock-remaining"><?php echo $item['remaining']; ?></div>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                 </div>
                                <td>
                                    <a href="stock_receive.php?product_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-success" title="Add Stock">
                                        <i class="bi bi-plus"></i>
                                    </a>
                                    <a href="stock_sale.php?product_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" title="Sell">
                                        <i class="bi bi-cart"></i>
                                    </a>
                                 </div>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($products_data)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-box" style="font-size: 3rem; color: #ddd;"></i>
                                    <p class="text-muted mt-2">No products found</p>
                                    <a href="stock_products.php?action=add" class="btn btn-primary btn-sm">Add Product</a>
                                 </div>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Movements -->
        <div class="card mt-4">
            <div class="card-header">
                <span><i class="bi bi-clock-history"></i> Recent Stock Movements</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Product</th>
                                <th>Outlet</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Before</th>
                                <th>After</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($mov = mysqli_fetch_assoc($recent_movements)): 
                                $type_class = $mov['movement_type'] == 'received' ? 'success' : ($mov['movement_type'] == 'sold' ? 'danger' : 'info');
                                $type_icon = $mov['movement_type'] == 'received' ? 'arrow-down' : ($mov['movement_type'] == 'sold' ? 'cart' : 'arrow-left-right');
                            ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($mov['created_at'])); ?>
                                    <br><small><?php echo date('H:i', strtotime($mov['created_at'])); ?></small>
                                 </div>
                                <td><?php echo htmlspecialchars($mov['product_name']); ?></div>
                                <td><?php echo htmlspecialchars($mov['outlet_name']); ?></div>
                                <td>
                                    <span class="badge bg-<?php echo $type_class; ?>">
                                        <i class="bi bi-<?php echo $type_icon; ?>"></i>
                                        <?php echo ucfirst($mov['movement_type']); ?>
                                    </span>
                                 </div>
                                <td class="fw-bold"><?php echo $mov['quantity']; ?></div>
                                <td><?php echo $mov['before_quantity']; ?></div>
                                <td><?php echo $mov['after_quantity']; ?></div>
                                <td><?php echo htmlspecialchars($mov['user_name']); ?></div>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }
        
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                }
            }
        });
    </script>
</body>
</html>