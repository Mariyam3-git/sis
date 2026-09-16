<?php
require_once '../config.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// Check if columns exist
$check_fixed = mysqli_query($conn, "SHOW COLUMNS FROM services LIKE 'is_fixed_price'");
if (mysqli_num_rows($check_fixed) == 0) {
    mysqli_query($conn, "ALTER TABLE services ADD COLUMN is_fixed_price BOOLEAN DEFAULT TRUE AFTER price");
    mysqli_query($conn, "ALTER TABLE services ADD COLUMN min_amount DECIMAL(10,2) NULL AFTER is_fixed_price");
    mysqli_query($conn, "ALTER TABLE services ADD COLUMN max_amount DECIMAL(10,2) NULL AFTER min_amount");
    mysqli_query($conn, "ALTER TABLE services ADD COLUMN is_quantity_based BOOLEAN DEFAULT FALSE AFTER is_fixed_price");
    mysqli_query($conn, "ALTER TABLE services ADD COLUMN unit_name VARCHAR(50) DEFAULT 'unit' AFTER is_quantity_based");
}

// Handle operations
$message = '';
$message_type = '';

// Delete service
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $query = "UPDATE services SET is_active = 0 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'DELETE_SERVICE', $id);
        $message = 'Service archived!';
        $message_type = 'success';
    }
}

// Restore service
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $query = "UPDATE services SET is_active = 1 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'RESTORE_SERVICE', $id);
        $message = 'Service restored!';
        $message_type = 'success';
    }
}

// Permanent delete
if (isset($_GET['hard_delete'])) {
    $id = (int)$_GET['hard_delete'];
    $query = "DELETE FROM services WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'PERMANENT_DELETE_SERVICE', $id);
        $message = 'Service permanently deleted!';
        $message_type = 'success';
    }
}

// Toggle paid/free
if (isset($_GET['toggle_paid'])) {
    $id = (int)$_GET['toggle_paid'];
    $query = "UPDATE services SET is_paid = NOT is_paid WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'TOGGLE_SERVICE_PAID', $id);
        $message = 'Payment status updated!';
        $message_type = 'success';
    }
}

// Add/Edit service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $price = (float)$_POST['price'];
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $is_fixed_price = isset($_POST['is_fixed_price']) ? 1 : 0;
    $is_quantity_based = isset($_POST['is_quantity_based']) ? 1 : 0;
    $unit_name = mysqli_real_escape_string($conn, $_POST['unit_name'] ?? 'unit');
    $min_amount = !empty($_POST['min_amount']) ? (float)$_POST['min_amount'] : 'NULL';
    $max_amount = !empty($_POST['max_amount']) ? (float)$_POST['max_amount'] : 'NULL';
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    if (!$is_paid) {
        $price = 0;
        $is_fixed_price = 1;
        $is_quantity_based = 0;
        $min_amount = 'NULL';
        $max_amount = 'NULL';
    }
    
    $min_sql = ($min_amount === 'NULL') ? 'NULL' : $min_amount;
    $max_sql = ($max_amount === 'NULL') ? 'NULL' : $max_amount;
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $query = "UPDATE services SET 
                  name = '$name', 
                  price = $price, 
                  is_paid = $is_paid,
                  is_fixed_price = $is_fixed_price,
                  is_quantity_based = $is_quantity_based,
                  unit_name = '$unit_name',
                  min_amount = $min_sql,
                  max_amount = $max_sql,
                  description = '$description' 
                  WHERE id = $id";
        $action = 'UPDATE_SERVICE';
    } else {
        $query = "INSERT INTO services (name, price, is_paid, is_fixed_price, is_quantity_based, unit_name, min_amount, max_amount, description) 
                  VALUES ('$name', $price, $is_paid, $is_fixed_price, $is_quantity_based, '$unit_name', $min_sql, $max_sql, '$description')";
        $action = 'ADD_SERVICE';
    }
    
    if (mysqli_query($conn, $query)) {
        $record_id = isset($id) ? $id : mysqli_insert_id($conn);
        logAction($conn, $action, $record_id);
        $message = 'Service saved successfully!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$filter_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';

// Get services with search and filters
if ($search || $filter_type) {
    $where_conditions = [];
    
    if ($show_inactive) {
        $where_conditions[] = "(is_active = 1 OR is_active = 0)";
    } else {
        $where_conditions[] = "is_active = 1";
    }
    
    if ($filter_type == 'paid') {
        $where_conditions[] = "is_paid = 1";
    } elseif ($filter_type == 'free') {
        $where_conditions[] = "is_paid = 0";
    }
    
    if ($search) {
        $where_conditions[] = "(name LIKE '%$search%' OR description LIKE '%$search%')";
    }
    
    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    $services_query = "SELECT * FROM services $where_sql ORDER BY is_active DESC, is_paid DESC, name";
} elseif ($show_inactive) {
    $services_query = "SELECT * FROM services ORDER BY is_active DESC, is_paid DESC, name";
} else {
    $services_query = "SELECT * FROM services WHERE is_active = 1 ORDER BY is_paid DESC, name";
}
$services_result = mysqli_query($conn, $services_query);

// Group services by type
$paid_services = [];
$free_services = [];
$inactive_services = [];

while ($service = mysqli_fetch_assoc($services_result)) {
    if ($service['is_active'] == 0) {
        $inactive_services[] = $service;
    } else if ($service['is_paid'] == 1) {
        $paid_services[] = $service;
    } else {
        $free_services[] = $service;
    }
}

// Get service for editing
$edit_service = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM services WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_service = mysqli_fetch_assoc($edit_result);
}

// Count totals
$total_active = count($paid_services) + count($free_services);
$total_paid = count($paid_services);
$total_free = count($free_services);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Services - Comium GM</title>
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
        
        /* Sidebar */
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
            padding: 10px 20px;
            color: var(--comium-dark);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--comium-red);
            color: white;
        }
        
        .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        
        /* Menu Toggle */
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
        
        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px 30px;
            min-height: 100vh;
        }
        
        /* Page header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-left: 4px solid var(--comium-red);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h6 {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .stat-card h3 {
            color: var(--comium-red);
            font-size: 28px;
            margin: 0;
        }
        
        .stat-card small {
            color: #999;
            font-size: 11px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            background: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 14px;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .card-body.p-0 {
            padding: 0;
        }
        
        /* Form */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 8px 12px;
            font-size: 13px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--comium-red);
            box-shadow: none;
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .btn-primary {
            background: var(--comium-red);
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
        }
        
        .btn-primary:hover {
            background: #b3040f;
        }
        
        .btn-outline-secondary {
            border: 1px solid #ddd;
            color: #666;
            font-size: 13px;
        }
        
        .form-switch .form-check-input:checked {
            background-color: var(--comium-red);
            border-color: var(--comium-red);
        }
        
        .form-check-label {
            font-size: 13px;
        }
        
        /* Search & Filter Bar */
        .filter-bar {
            background: white;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            font-size: 11px;
            color: #999;
            margin-bottom: 4px;
            display: block;
        }
        
        .filter-group .form-control,
        .filter-group .form-select {
            font-size: 13px;
            padding: 8px 12px;
        }
        
        .search-box {
            flex: 2;
            min-width: 200px;
        }
        
        /* Service group */
        .service-group {
            margin-bottom: 20px;
            border: 1px solid #eee;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .group-header {
            background: #f8f9fa;
            padding: 10px 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }
        
        .group-header:hover {
            background: #e9ecef;
        }
        
        .group-header i {
            transition: transform 0.2s;
        }
        
        .group-header.collapsed i {
            transform: rotate(-90deg);
        }
        
        .service-list {
            padding: 0;
            transition: all 0.3s;
        }
        
        .service-list.collapsed {
            display: none;
        }
        
        /* Compact table */
        .compact-table {
            width: 100%;
            font-size: 13px;
        }
        
        .compact-table th {
            background: #fafafa;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 600;
            color: #666;
        }
        
        .compact-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .compact-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-free { background: #e2e3e5; color: #383d41; }
        .badge-fixed { background: #cce5ff; color: #004085; }
        .badge-flexible { background: #fff3cd; color: #856404; }
        .badge-quantity { background: #d1ecf1; color: #0c5460; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        
        /* Action buttons */
        .action-btn {
            padding: 4px 6px;
            border-radius: 5px;
            color: #666;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }
        
        .action-btn:hover {
            background: #f0f0f0;
        }
        
        /* Alert */
        .alert {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: none;
            font-size: 13px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
        .alert-warning { background: #fff3cd; color: #856404; }
        
        /* Two column layout */
        .two-col {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 25px;
        }
        
        /* Scrollable table */
        .table-scroll {
            max-height: 400px;
            overflow-y: auto;
        }
        
        /* Inactive section */
        .inactive-section {
            background: #fff9e6;
            border: 1px solid #ffeeba;
        }
        .inactive-section .group-header {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 992px) {
            .two-col {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 70px 15px 20px 15px;
            }
            .menu-toggle {
                display: block;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-group {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i> Menu
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">COMIUM</div>
            <small style="color: #999;">Admin Panel</small>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">MAIN</div>
            <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="outlets.php" class="nav-link <?php echo $current_page == 'outlets.php' ? 'active' : ''; ?>">
                <i class="bi bi-building"></i> Outlets
            </a>
            <a href="agents.php" class="nav-link <?php echo $current_page == 'agents.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Agents
            </a>
            <a href="supervisors.php" class="nav-link <?php echo $current_page == 'supervisors.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i> Supervisors
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">SERVICES & CONFIGURATION</div>
            <a href="services.php" class="nav-link active">
                <i class="bi bi-grid"></i> Services
            </a>
            <a href="purposes.php" class="nav-link <?php echo $current_page == 'purposes.php' ? 'active' : ''; ?>">
                <i class="bi bi-tags"></i> Visit Purposes
            </a>
            <a href="purpose_categories.php" class="nav-link <?php echo $current_page == 'purpose_categories.php' ? 'active' : ''; ?>">
                <i class="bi bi-folder"></i> Purpose Categories
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">PERFORMANCE</div>
            <a href="targets.php" class="nav-link <?php echo $current_page == 'targets.php' ? 'active' : ''; ?>">
                <i class="bi bi-bullseye"></i> Targets
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">STOCK MANAGEMENT</div>
            <a href="stock_products.php" class="nav-link <?php echo $current_page == 'stock_products.php' ? 'active' : ''; ?>">
                <i class="bi bi-box"></i> Products
            </a>
            <a href="stock_categories.php" class="nav-link <?php echo $current_page == 'stock_categories.php' ? 'active' : ''; ?>">
                <i class="bi bi-tags"></i> Stock Categories
            </a>
            <a href="stock_receive.php" class="nav-link <?php echo $current_page == 'stock_receive.php' ? 'active' : ''; ?>">
                <i class="bi bi-box-arrow-in-down"></i> Receive
            </a>
            <a href="stock_transfer.php" class="nav-link <?php echo $current_page == 'stock_transfer.php' ? 'active' : ''; ?>">
                <i class="bi bi-arrow-left-right"></i> Transfer
            </a>
            <a href="stock_adjust.php" class="nav-link <?php echo $current_page == 'stock_adjust.php' ? 'active' : ''; ?>">
                <i class="bi bi-pencil-square"></i> Adjust
            </a>
            <a href="stock_reports.php" class="nav-link <?php echo $current_page == 'stock_reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-bar-graph"></i> Reports
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">REPORTS</div>
            <a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> Sales Reports
            </a>
            <a href="visitors.php" class="nav-link <?php echo $current_page == 'visitors.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-lines-fill"></i> Visitor Log
            </a>
            <a href="sales.php" class="nav-link <?php echo $current_page == 'sales.php' ? 'active' : ''; ?>">
                <i class="bi bi-cash"></i> Transactions
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">SYSTEM</div>
            <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i> Settings
            </a>
            <a href="../logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h3><i class="bi bi-grid" style="color: var(--comium-red); margin-right: 10px;"></i>Services Management</h3>
            <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Active Services</h6>
                <h3><?php echo $total_active; ?></h3>
                <small>Total active</small>
            </div>
            <div class="stat-card">
                <h6>Paid Services</h6>
                <h3><?php echo $total_paid; ?></h3>
                <small>Revenue generating</small>
            </div>
            <div class="stat-card">
                <h6>Free Services</h6>
                <h3><?php echo $total_free; ?></h3>
                <small>Support services</small>
            </div>
            <div class="stat-card">
                <h6>Archived</h6>
                <h3><?php echo count($inactive_services); ?></h3>
                <small>Inactive services</small>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Advanced Filter Bar -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%;">
                <div class="search-box">
                    <input type="text" class="form-control" name="search" placeholder="🔍 Search by name or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select class="form-select" name="type">
                        <option value="">All Types</option>
                        <option value="paid" <?php echo $filter_type == 'paid' ? 'selected' : ''; ?>>Paid Services</option>
                        <option value="free" <?php echo $filter_type == 'free' ? 'selected' : ''; ?>>Free Services</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select class="form-select" name="status">
                        <option value="active" <?php echo !$show_inactive ? 'selected' : ''; ?>>Active Only</option>
                        <option value="all" <?php echo $show_inactive ? 'selected' : ''; ?>>All (incl. Archived)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="services.php" class="btn btn-outline-secondary">Reset</a>
            </form>
        </div>

        <!-- Two Column Layout -->
        <div class="two-col">
            <!-- Left: Add/Edit Form -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?php echo $edit_service ? 'pencil-square' : 'plus-circle'; ?>"></i>
                    <?php echo $edit_service ? 'Edit Service' : 'Add New Service'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" id="serviceForm">
                        <input type="hidden" name="save_service" value="1">
                        <?php if($edit_service): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_service['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($edit_service['name'] ?? ''); ?>" 
                                   placeholder="e.g., Mobile Recharge" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_paid" id="isPaid" value="1" 
                                       <?php echo (!isset($edit_service) || $edit_service['is_paid'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isPaid">💰 Paid Service</label>
                            </div>
                        </div>
                        
                        <div id="priceTypeSection" style="<?php echo (!isset($edit_service) || $edit_service['is_paid'] == 1) ? 'display:block;' : 'display:none;'; ?>">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_fixed_price" id="isFixedPrice" value="1" 
                                           <?php echo (!isset($edit_service) || $edit_service['is_fixed_price'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isFixedPrice">📏 Fixed Price</label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="fixedPriceSection" class="mb-3">
                            <label class="form-label">Price (D)</label>
                            <input type="number" step="0.01" class="form-control" name="price" id="fixedPrice"
                                   value="<?php echo $edit_service['price'] ?? '0.00'; ?>">
                        </div>
                        
                        <div id="flexiblePriceSection" style="display: none;">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Min Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="min_amount" id="minAmount"
                                           value="<?php echo $edit_service['min_amount'] ?? ''; ?>" placeholder="Optional">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Max Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="max_amount" id="maxAmount"
                                           value="<?php echo $edit_service['max_amount'] ?? ''; ?>" placeholder="Optional">
                                </div>
                            </div>
                        </div>
                        
                        <div id="quantitySection" style="display: none;">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_quantity_based" id="isQuantityBased" value="1"
                                           <?php echo (isset($edit_service['is_quantity_based']) && $edit_service['is_quantity_based'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isQuantityBased">📦 Quantity Based</label>
                                </div>
                            </div>
                            
                            <div id="quantityDetails" style="<?php echo (isset($edit_service['is_quantity_based']) && $edit_service['is_quantity_based'] == 1) ? 'display:block;' : 'display:none;'; ?>" class="mb-3">
                                <label class="form-label">Unit Name</label>
                                <input type="text" class="form-control" name="unit_name" placeholder="e.g., SIM, GB, minute"
                                       value="<?php echo $edit_service['unit_name'] ?? 'unit'; ?>">
                                <small class="text-muted">Price per unit × quantity</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Brief description of the service"><?php echo htmlspecialchars($edit_service['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="alert alert-info mb-3 small" id="priceHint" style="display: none; padding: 8px 12px;">
                            <span id="hintText"></span>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_service ? 'Update Service' : 'Add Service'; ?>
                            </button>
                            <?php if($edit_service): ?>
                            <a href="services.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Services List -->
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list-ul"></i> Services Directory</span>
                    <span class="badge bg-secondary"><?php echo $total_active; ?> active</span>
                </div>
                <div class="card-body p-0">
                    <!-- Paid Services Group -->
                    <?php if (count($paid_services) > 0): ?>
                    <div class="service-group" data-group="paid">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <span>
                                <i class="bi bi-chevron-down"></i>
                                <i class="bi bi-cash-stack me-1" style="color: #28a745;"></i>
                                Paid Services <span class="badge bg-success ms-2"><?php echo count($paid_services); ?></span>
                            </span>
                            <span class="badge bg-secondary"><?php echo count($paid_services); ?> services</span>
                        </div>
                        <div class="service-list">
                            <div class="table-scroll">
                                <table class="compact-table">
                                    <thead>
                                        <tr>
                                            <th>Service Name</th>
                                            <th>Pricing</th>
                                            <th style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paid_services as $service): 
                                            $price_display = '';
                                            if($service['is_quantity_based']) {
                                                $price_display = 'D ' . number_format($service['price'], 2) . '/' . ($service['unit_name'] ?? 'unit');
                                            } elseif(!$service['is_fixed_price']) {
                                                if($service['min_amount'] && $service['max_amount']) {
                                                    $price_display = 'D ' . $service['min_amount'] . ' - ' . $service['max_amount'];
                                                } elseif($service['min_amount']) {
                                                    $price_display = 'Min D ' . $service['min_amount'];
                                                } elseif($service['max_amount']) {
                                                    $price_display = 'Max D ' . $service['max_amount'];
                                                } else {
                                                    $price_display = 'Any amount';
                                                }
                                            } else {
                                                $price_display = 'D ' . number_format($service['price'], 2);
                                            }
                                        ?>
                                        <tr data-service-name="<?php echo strtolower(htmlspecialchars($service['name'])); ?>"
                                            data-service-desc="<?php echo strtolower(htmlspecialchars($service['description'] ?? '')); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                                <?php if($service['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($service['description'], 0, 35)); ?></small>
                                                <?php endif; ?>
                                             </div>
                                            <td>
                                                <span class="badge <?php echo $service['is_quantity_based'] ? 'badge-quantity' : ($service['is_fixed_price'] ? 'badge-fixed' : 'badge-flexible'); ?>">
                                                    <?php echo $service['is_quantity_based'] ? 'Quantity' : ($service['is_fixed_price'] ? 'Fixed' : 'Flexible'); ?>
                                                </span>
                                                <div class="small mt-1"><?php echo $price_display; ?></div>
                                             </div>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="?edit=<?php echo $service['id']; ?>" class="action-btn" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="?toggle_paid=<?php echo $service['id']; ?>" class="action-btn text-info" title="Toggle Free">
                                                        <i class="bi bi-gift"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $service['id']; ?>" class="action-btn text-warning" 
                                                       onclick="return confirm('Archive this service?')" title="Archive">
                                                        <i class="bi bi-archive"></i>
                                                    </a>
                                                </div>
                                             </div>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Free Services Group -->
                    <?php if (count($free_services) > 0): ?>
                    <div class="service-group" data-group="free">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <span>
                                <i class="bi bi-chevron-down"></i>
                                <i class="bi bi-gift me-1" style="color: #6c757d;"></i>
                                Free Services <span class="badge bg-secondary ms-2"><?php echo count($free_services); ?></span>
                            </span>
                            <span class="badge bg-secondary"><?php echo count($free_services); ?> services</span>
                        </div>
                        <div class="service-list">
                            <div class="table-scroll">
                                <table class="compact-table">
                                    <thead>
                                        <tr>
                                            <th>Service Name</th>
                                            <th>Type</th>
                                            <th style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($free_services as $service): ?>
                                        <tr data-service-name="<?php echo strtolower(htmlspecialchars($service['name'])); ?>"
                                            data-service-desc="<?php echo strtolower(htmlspecialchars($service['description'] ?? '')); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                                <?php if($service['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($service['description'], 0, 35)); ?></small>
                                                <?php endif; ?>
                                             </div>
                                            <td>
                                                <span class="badge badge-free">Free</span>
                                             </div>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="?edit=<?php echo $service['id']; ?>" class="action-btn" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="?toggle_paid=<?php echo $service['id']; ?>" class="action-btn text-success" title="Make Paid">
                                                        <i class="bi bi-cash"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $service['id']; ?>" class="action-btn text-warning" 
                                                       onclick="return confirm('Archive this service?')" title="Archive">
                                                        <i class="bi bi-archive"></i>
                                                    </a>
                                                </div>
                                             </div>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Empty State -->
                    <?php if (count($paid_services) == 0 && count($free_services) == 0 && count($inactive_services) == 0): ?>
                    <div class="empty-state">
                        <i class="bi bi-grid"></i>
                        <p>No services found<?php echo $search ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?></p>
                        <a href="services.php" class="btn btn-primary btn-sm">View All Services</a>
                    </div>
                    <?php endif; ?>

                    <!-- Archived Services Section -->
                    <?php if ($show_inactive && count($inactive_services) > 0): ?>
                    <div class="service-group inactive-section mt-2">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <span>
                                <i class="bi bi-chevron-down"></i>
                                <i class="bi bi-archive me-1"></i>
                                Archived Services
                            </span>
                            <span class="badge bg-secondary"><?php echo count($inactive_services); ?> archived</span>
                        </div>
                        <div class="service-list">
                            <div class="table-scroll">
                                <table class="compact-table">
                                    <thead>
                                        <tr>
                                            <th>Service Name</th>
                                            <th>Original Type</th>
                                            <th style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inactive_services as $service): 
                                            $original_type = $service['is_paid'] ? 'Paid' : 'Free';
                                        ?>
                                        <tr data-service-name="<?php echo strtolower(htmlspecialchars($service['name'])); ?>"
                                            data-service-desc="<?php echo strtolower(htmlspecialchars($service['description'] ?? '')); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                                <?php if($service['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($service['description'], 0, 35)); ?></small>
                                                <?php endif; ?>
                                             </div>
                                            <td>
                                                <span class="badge <?php echo $service['is_paid'] ? 'badge-paid' : 'badge-free'; ?>">
                                                    <?php echo $original_type; ?>
                                                </span>
                                             </div>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="?restore=<?php echo $service['id']; ?>" class="action-btn text-success" 
                                                       onclick="return confirm('Restore this service?')" title="Restore">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </a>
                                                    <a href="?hard_delete=<?php echo $service['id']; ?>" class="action-btn text-danger" 
                                                       onclick="return confirm('⚠️ PERMANENTLY DELETE this service? This cannot be undone! ⚠️')" title="Permanently Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                             </div>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }
        
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                }
            }
        });
        
        // Get form elements
        const isPaid = document.getElementById('isPaid');
        const isFixed = document.getElementById('isFixedPrice');
        const isQuantity = document.getElementById('isQuantityBased');
        const priceTypeSection = document.getElementById('priceTypeSection');
        const fixedSection = document.getElementById('fixedPriceSection');
        const flexibleSection = document.getElementById('flexiblePriceSection');
        const quantitySection = document.getElementById('quantitySection');
        const quantityDetails = document.getElementById('quantityDetails');
        const priceHint = document.getElementById('priceHint');
        const hintText = document.getElementById('hintText');
        const fixedPrice = document.getElementById('fixedPrice');
        
        function updateForm() {
            const paid = isPaid.checked;
            const fixed = isFixed.checked;
            const quantity = isQuantity.checked;
            
            priceTypeSection.style.display = paid ? 'block' : 'none';
            
            if (!paid) {
                fixedSection.style.display = 'block';
                flexibleSection.style.display = 'none';
                quantitySection.style.display = 'none';
                fixedPrice.value = '0.00';
                fixedPrice.readOnly = true;
                hintText.innerHTML = 'Free service - no payment required';
                priceHint.style.display = 'block';
                priceHint.className = 'alert alert-secondary mb-3 small';
            } else {
                fixedPrice.readOnly = false;
                if (fixed) {
                    fixedSection.style.display = 'block';
                    flexibleSection.style.display = 'none';
                    quantitySection.style.display = 'none';
                    hintText.innerHTML = 'Fixed price - customer pays exactly this amount';
                    priceHint.style.display = 'block';
                    priceHint.className = 'alert alert-info mb-3 small';
                } else {
                    fixedSection.style.display = 'none';
                    flexibleSection.style.display = 'block';
                    quantitySection.style.display = 'block';
                    const min = document.getElementById('minAmount')?.value;
                    const max = document.getElementById('maxAmount')?.value;
                    if (quantity) {
                        quantityDetails.style.display = 'block';
                        if (min && max) {
                            hintText.innerHTML = `Quantity-based: D ${min} - ${max} per unit`;
                        } else if (min) {
                            hintText.innerHTML = `Quantity-based: Min D ${min} per unit`;
                        } else if (max) {
                            hintText.innerHTML = `Quantity-based: Max D ${max} per unit`;
                        } else {
                            hintText.innerHTML = 'Quantity-based: Enter any amount per unit';
                        }
                    } else {
                        quantityDetails.style.display = 'none';
                        if (min && max) {
                            hintText.innerHTML = `Flexible: D ${min} - ${max}`;
                        } else if (min) {
                            hintText.innerHTML = `Flexible: Min D ${min}`;
                        } else if (max) {
                            hintText.innerHTML = `Flexible: Max D ${max}`;
                        } else {
                            hintText.innerHTML = 'Flexible: Customer can pay any amount';
                        }
                    }
                    priceHint.style.display = 'block';
                    priceHint.className = 'alert alert-warning mb-3 small';
                }
            }
        }
        
        // Toggle group collapse/expand
        function toggleGroup(headerElement) {
            const serviceList = headerElement.nextElementSibling;
            const icon = headerElement.querySelector('i:first-child');
            
            if (serviceList.classList.contains('collapsed')) {
                serviceList.classList.remove('collapsed');
                if (icon) icon.style.transform = 'rotate(0deg)';
                headerElement.classList.remove('collapsed');
            } else {
                serviceList.classList.add('collapsed');
                if (icon) icon.style.transform = 'rotate(-90deg)';
                headerElement.classList.add('collapsed');
            }
        }
        
        // Live search functionality (client-side)
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const serviceGroups = document.querySelectorAll('.service-group');
                
                serviceGroups.forEach(group => {
                    let hasVisibleServices = false;
                    const rows = group.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const serviceName = row.getAttribute('data-service-name') || '';
                        const serviceDesc = row.getAttribute('data-service-desc') || '';
                        const rowText = (serviceName + ' ' + serviceDesc).toLowerCase();
                        
                        if (searchTerm === '' || rowText.includes(searchTerm)) {
                            row.style.display = '';
                            hasVisibleServices = true;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    if (hasVisibleServices || searchTerm === '') {
                        group.style.display = '';
                    } else {
                        group.style.display = 'none';
                    }
                });
            });
        }
        
        // Event listeners for form
        if (isPaid) isPaid.addEventListener('change', updateForm);
        if (isFixed) isFixed.addEventListener('change', updateForm);
        if (isQuantity) {
            isQuantity.addEventListener('change', function() {
                if (quantityDetails) quantityDetails.style.display = this.checked ? 'block' : 'none';
                updateForm();
            });
        }
        if (document.getElementById('minAmount')) document.getElementById('minAmount').addEventListener('input', updateForm);
        if (document.getElementById('maxAmount')) document.getElementById('maxAmount').addEventListener('input', updateForm);
        
        // Initialize form
        updateForm();
        
        // Initialize - all groups expanded by default
        document.querySelectorAll('.group-header').forEach(header => {
            const serviceList = header.nextElementSibling;
            if (serviceList && !serviceList.classList.contains('collapsed')) {
                serviceList.classList.remove('collapsed');
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>