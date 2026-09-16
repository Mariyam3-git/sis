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

$message = '';
$message_type = '';

// Handle product add/edit - FIXED: Added proper debug and error handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $name = mysqli_real_escape_string($conn, trim((string)($_POST['name'] ?? '')));
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $sku = mysqli_real_escape_string($conn, trim((string)($_POST['sku'] ?? '')));
    $description = mysqli_real_escape_string($conn, (string)($_POST['description'] ?? ''));
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 0);
    $reorder_quantity = (int)($_POST['reorder_quantity'] ?? 0);
    $track_serial = isset($_POST['track_serial']) ? 1 : 0;
    
    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    if (empty($category_id)) {
        $errors[] = "Please select a category";
    }
    
    if (empty($errors)) {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $query = "UPDATE stock_products SET 
                      name = '$name',
                      category_id = " . ($category_id ?: 'NULL') . ",
                      sku = '$sku',
                      description = '$description',
                      unit_price = $unit_price,
                      selling_price = $selling_price,
                      low_stock_threshold = $low_stock_threshold,
                      reorder_quantity = $reorder_quantity,
                      track_serial = $track_serial
                      WHERE id = $id";
            $action = 'UPDATE_PRODUCT';
        } else {
            $query = "INSERT INTO stock_products (name, category_id, sku, description, unit_price, selling_price, low_stock_threshold, reorder_quantity, track_serial, is_active) 
                      VALUES ('$name', " . ($category_id ?: 'NULL') . ", '$sku', '$description', $unit_price, $selling_price, $low_stock_threshold, $reorder_quantity, $track_serial, 1)";
            $action = 'ADD_PRODUCT';
        }
        
        if (mysqli_query($conn, $query)) {
            $record_id = isset($id) ? $id : mysqli_insert_id($conn);
            logAction($conn, $action, $record_id);
            $message = 'Product saved successfully!';
            $message_type = 'success';
            // Redirect to clear the form
            header("Location: stock_products.php?msg=success");
            exit();
        } else {
            $message = 'Error: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    } else {
        $message = implode(', ', $errors);
        $message_type = 'danger';
    }
}

// Check for success message from redirect
if (isset($_GET['msg']) && $_GET['msg'] == 'success') {
    $message = 'Product saved successfully!';
    $message_type = 'success';
}

// Handle threshold update
if (isset($_POST['update_threshold'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $new_threshold = (int)($_POST['low_stock_threshold'] ?? 0);
    $reorder_qty = (int)($_POST['reorder_quantity'] ?? 0);
    
    $update = "UPDATE stock_products SET 
                low_stock_threshold = $new_threshold,
                reorder_quantity = $reorder_qty
                WHERE id = $product_id";
    
    if (mysqli_query($conn, $update)) {
        $message = "Threshold updated successfully!";
        $message_type = 'success';
        logAction($conn, 'UPDATE_THRESHOLD', $product_id);
    } else {
        $message = "Error: " . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Handle bulk threshold update
if (isset($_POST['bulk_update'])) {
    $threshold_value = (int)($_POST['bulk_threshold'] ?? 0);
    $reorder_value = (int)($_POST['bulk_reorder'] ?? 0);
    $category_id = (int)($_POST['bulk_category'] ?? 0);
    
    $where = $category_id > 0 ? "WHERE category_id = $category_id" : "";
    
    $update = "UPDATE stock_products SET 
                low_stock_threshold = $threshold_value,
                reorder_quantity = $reorder_value
                $where";
    
    if (mysqli_query($conn, $update)) {
        $message = "Bulk threshold update completed!";
        $message_type = 'success';
        logAction($conn, 'BULK_UPDATE_THRESHOLD');
    } else {
        $message = "Error: " . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Archive product (soft delete)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $query = "UPDATE stock_products SET is_active = 0 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'DELETE_PRODUCT', $id);
        $message = 'Product archived!';
        $message_type = 'success';
    }
}

// Restore product
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $query = "UPDATE stock_products SET is_active = 1 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'RESTORE_PRODUCT', $id);
        $message = 'Product restored!';
        $message_type = 'success';
    }
}

// Permanent delete
if (isset($_GET['hard_delete'])) {
    $id = (int)$_GET['hard_delete'];
    $query = "DELETE FROM stock_products WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'PERMANENT_DELETE_PRODUCT', $id);
        $message = 'Product permanently deleted!';
        $message_type = 'success';
    }
}

// Get search term
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Get categories for filter and form
$categories = mysqli_query($conn, "SELECT * FROM stock_categories WHERE is_active = 1 ORDER BY name");

// Build products query with filters
$where_conditions = [];
if ($show_inactive) {
    $where_conditions[] = "(p.is_active = 1 OR p.is_active = 0)";
} else {
    $where_conditions[] = "p.is_active = 1";
}
if ($search) {
    $where_conditions[] = "(p.name LIKE '%$search%' OR p.sku LIKE '%$search%' OR c.name LIKE '%$search%')";
}
if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = $category_filter";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$products = mysqli_query($conn, "
    SELECT p.*, c.name as category_name, c.color as category_color,
           (SELECT SUM(quantity) FROM outlet_stock WHERE product_id = p.id) as total_stock,
           (SELECT COUNT(*) FROM outlet_stock WHERE product_id = p.id AND quantity <= p.low_stock_threshold) as low_stock_outlets,
           (SELECT COUNT(DISTINCT outlet_id) FROM outlet_stock WHERE product_id = p.id) as outlets_with_stock,
           (SELECT MIN(quantity) FROM outlet_stock WHERE product_id = p.id) as smallest_outlet_stock,
           (SELECT MAX(quantity) FROM outlet_stock WHERE product_id = p.id) as largest_outlet_stock
    FROM stock_products p
    LEFT JOIN stock_categories c ON p.category_id = c.id
    $where_sql
    ORDER BY p.is_active DESC, 
        CASE 
            WHEN (SELECT SUM(quantity) FROM outlet_stock WHERE product_id = p.id) <= p.low_stock_threshold THEN 0
            ELSE 1
        END,
        p.name
");

// Group products by category and status
$active_products_by_category = [];
$inactive_products = [];

while ($p = mysqli_fetch_assoc($products)) {
    if ($p['is_active'] == 0) {
        $inactive_products[] = $p;
    } else {
        $category = $p['category_name'] ?? 'Uncategorized';
        if (!isset($active_products_by_category[$category])) {
            $active_products_by_category[$category] = [];
        }
        $active_products_by_category[$category][] = $p;
    }
}

// Sort categories alphabetically
ksort($active_products_by_category);

// Get product for editing
$edit_product = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM stock_products WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_product = mysqli_fetch_assoc($edit_result);
}

// Get threshold statistics
$threshold_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        AVG(low_stock_threshold) as avg_threshold,
        MIN(low_stock_threshold) as min_threshold,
        MAX(low_stock_threshold) as max_threshold,
        SUM(CASE WHEN low_stock_threshold < 10 THEN 1 ELSE 0 END) as very_low,
        SUM(CASE WHEN low_stock_threshold BETWEEN 10 AND 20 THEN 1 ELSE 0 END) as low,
        SUM(CASE WHEN low_stock_threshold > 20 THEN 1 ELSE 0 END) as normal
    FROM stock_products WHERE is_active = 1
"));

// Handle session storage for transfer quantity
$transfer_qty = isset($_POST['transfer_qty']) ? (int)$_POST['transfer_qty'] : (isset($_SESSION['transfer_qty']) ? $_SESSION['transfer_qty'] : 10);
if (isset($_POST['transfer_qty'])) {
    $_SESSION['transfer_qty'] = $transfer_qty;
}

$total_active = 0;
foreach ($active_products_by_category as $cat_products) {
    $total_active += count($cat_products);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Products - Comium GM</title>
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
            <h3><i class="bi bi-box sis-u-002e4b71"></i> Stock Products</h3>
            <span class="date-badge"><?php echo date('l, d M Y'); ?></span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Average Threshold</h6>
                <h2><?php echo round($threshold_stats['avg_threshold'] ?? 0); ?></h2>
                <small>Default alert level</small>
            </div>
            <div class="stat-card">
                <h6>Very Low (&lt;10)</h6>
                <h2><?php echo $threshold_stats['very_low'] ?? 0; ?></h2>
                <small>Need review</small>
            </div>
            <div class="stat-card">
                <h6>Low (10-20)</h6>
                <h2><?php echo $threshold_stats['low'] ?? 0; ?></h2>
                <small>Standard range</small>
            </div>
            <div class="stat-card">
                <h6>Normal (&gt;20)</h6>
                <h2><?php echo $threshold_stats['normal'] ?? 0; ?></h2>
                <small>High threshold</small>
            </div>
        </div>

        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="transfer-control">
            <form method="POST" class="d-flex align-items-center gap-3">
                <label><i class="bi bi-arrow-left-right"></i> Transfer Quantity:</label>
                <input type="number" name="transfer_qty" id="transfer_qty" class="transfer-input" 
                       value="<?php echo $transfer_qty; ?>" min="1" required>
                <button type="submit" class="transfer-btn">Update View</button>
                <small class="text-muted">Shows remaining stock after transferring this quantity</small>
            </form>
        </div>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?php echo $edit_product ? 'pencil-square' : 'plus-circle'; ?>"></i>
                    <?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="save_product" value="1">
                        <?php if($edit_product): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-2">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($edit_product['name'] ?? ''); ?>" 
                                   placeholder="e.g., COMIUM SIM Card" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-8 mb-2">
                                <label class="form-label">SKU (Stock Keeping Unit)</label>
                                <input type="text" class="form-control" name="sku" 
                                       value="<?php echo htmlspecialchars($edit_product['sku'] ?? ''); ?>"
                                       placeholder="e.g., SIM-COM-001">
                                <small class="text-muted">Unique identifier for this product</small>
                            </div>
                            <div class="col-4 mb-2">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">-- Select Category --</option>
                                    <?php 
                                    mysqli_data_seek($categories, 0);
                                    while($c = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                        <?php echo ($edit_product['category_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" 
                                      placeholder="Product description, specifications, etc."><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Unit Price (D)</label>
                                <input type="number" step="0.01" class="form-control" name="unit_price" 
                                       value="<?php echo $edit_product['unit_price'] ?? '0'; ?>" min="0">
                                <small class="text-muted">Cost price per unit</small>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label">Selling Price (D)</label>
                                <input type="number" step="0.01" class="form-control" name="selling_price" 
                                       value="<?php echo $edit_product['selling_price'] ?? '0'; ?>" min="0">
                                <small class="text-muted">Retail price per unit</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Low Stock Threshold</label>
                                <input type="number" class="form-control" name="low_stock_threshold" 
                                       value="<?php echo $edit_product['low_stock_threshold'] ?? '10'; ?>" min="1">
                                <small class="text-muted">Alert when stock falls below this</small>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label">Reorder Quantity</label>
                                <input type="number" class="form-control" name="reorder_quantity" 
                                       value="<?php echo $edit_product['reorder_quantity'] ?? '20'; ?>" min="1">
                                <small class="text-muted">Suggested order quantity</small>
                            </div>
                        </div>
                        
                        <div class="mb-2 form-check">
                            <input type="checkbox" class="form-check-input" name="track_serial" id="trackSerial" value="1"
                                   <?php echo (isset($edit_product['track_serial']) && $edit_product['track_serial'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="trackSerial">
                                <i class="bi bi-upc-scan"></i> Track Serial Numbers
                            </label>
                            <small class="d-block text-muted">Enable if this product requires serial number tracking</small>
                        </div>
                        
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" name="save_product_submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_product ? 'Update Product' : 'Add Product'; ?>
                            </button>
                            <?php if($edit_product): ?>
                            <a href="stock_products.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list-ul"></i> Products by Category</span>
                    <span class="badge bg-secondary"><?php echo $total_active; ?> active</span>
                </div>
                <div class="card-body">
                    
                    <div class="toolbar">
                        <div class="search-bar">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" 
                                       placeholder="Search by name, SKU or category..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <?php if($search): ?>
                                <a href="stock_products.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <select class="category-filter" id="categoryFilter">
                            <option value="0">All Categories</option>
                            <?php 
                            mysqli_data_seek($categories, 0);
                            while($c = mysqli_fetch_assoc($categories)): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $category_filter == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="filter-buttons">
                            <button class="filter-btn <?php echo !$show_inactive ? 'active' : ''; ?>" 
                                    onclick="applyFilter('active')">
                                <i class="bi bi-play-circle"></i> Active
                            </button>
                            <button class="filter-btn <?php echo $show_inactive ? 'active' : ''; ?>" 
                                    onclick="applyFilter('all')">
                                <i class="bi bi-archive"></i> All
                            </button>
                        </div>
                    </div>

                    
                    <div class="threshold-editor bg-light p-2 rounded mb-3">
                        <form method="POST" class="row g-2 align-items-end">
                            <div class="col">
                                <label class="form-label small">Category</label>
                                <select class="form-select form-select-sm" name="bulk_category">
                                    <option value="0">All Products</option>
                                    <?php 
                                    mysqli_data_seek($categories, 0);
                                    while($c = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col">
                                <label class="form-label small">Threshold</label>
                                <input type="number" class="form-control form-control-sm" name="bulk_threshold" value="10" min="1">
                            </div>
                            <div class="col">
                                <label class="form-label small">Reorder Qty</label>
                                <input type="number" class="form-control form-control-sm" name="bulk_reorder" value="20" min="1">
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="bulk_update" class="btn btn-primary btn-sm">Apply to All</button>
                            </div>
                        </form>
                    </div>

                    
                    <div id="productsContainer">
                        <?php if (count($active_products_by_category) > 0): ?>
                            <?php foreach ($active_products_by_category as $category => $products_list): ?>
                            <div class="category-group" data-category="<?php echo htmlspecialchars($category); ?>">
                                <div class="group-header" onclick="toggleGroup(this)">
                                    <span>
                                        <i class="bi bi-chevron-down"></i>
                                        <i class="bi bi-folder me-1 sis-u-002e4b71"></i>
                                        <?php echo htmlspecialchars($category); ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo count($products_list); ?> products</span>
                                </div>
                                <div class="product-list">
                                    <div class="table-scroll">
                                        <table class="compact-table">
                                            <thead>
                                                <tr>
                                                    <th>Product / SKU</th>
                                                    <th>Stock</th>
                                                    <th>After Transfer (<?php echo $transfer_qty; ?>)</th>
                                                    <th>Threshold</th>
                                                    <th>Status</th>
                                                    <th class="sis-u-40430a3c">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($products_list as $p): 
                                                    $total_stock = $p['total_stock'] ?? 0;
                                                    $remaining_after_transfer = $total_stock - $transfer_qty;
                                                    
                                                    if ($remaining_after_transfer < 0) {
                                                        $remaining_class = 'remaining-critical';
                                                        $remaining_status = 'Insufficient';
                                                    } elseif ($remaining_after_transfer <= ($p['low_stock_threshold'] ?? 10)) {
                                                        $remaining_class = 'remaining-warning';
                                                        $remaining_status = 'Low';
                                                    } else {
                                                        $remaining_class = 'remaining-positive';
                                                        $remaining_status = 'OK';
                                                    }
                                                    
                                                    $status_class = 'success';
                                                    $status_text = 'Normal';
                                                    if ($total_stock == 0) {
                                                        $status_class = 'danger';
                                                        $status_text = 'Out';
                                                    } elseif ($total_stock <= $p['low_stock_threshold']) {
                                                        $status_class = 'warning';
                                                        $status_text = 'Low';
                                                    }
                                                ?>
                                                <tr data-product-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>"
                                                    data-product-sku="<?php echo strtolower(htmlspecialchars($p['sku'] ?? '')); ?>">
                                                    <td>
                                                        <strong><?php echo htmlspecialchars(substr($p['name'], 0, 30)); ?></strong>
                                                        <br><small class="text-muted"><?php echo $p['sku']; ?></small>
                                                    </td>
                                                    <td class="fw-bold"><?php echo $total_stock; ?> units</td>
                                                    <td>
                                                        <span class="<?php echo $remaining_class; ?>">
                                                            <?php echo max(0, $remaining_after_transfer); ?> units
                                                        </span>
                                                        <br><small><?php echo $remaining_status; ?></small>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline" id="threshold-form-<?php echo $p['id']; ?>">
                                                            <input type="hidden" name="update_threshold" value="1">
                                                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                                            <input type="number" name="low_stock_threshold" 
                                                                   value="<?php echo $p['low_stock_threshold']; ?>" 
                                                                   class="threshold-input" min="1"
                                                                   onchange="updateThreshold(<?php echo $p['id']; ?>)">
                                                            <input type="hidden" name="reorder_quantity" value="<?php echo $p['reorder_quantity']; ?>">
                                                        </form>
                                                     </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                            <?php echo $status_text; ?>
                                                        </span>
                                                        <?php if($p['low_stock_outlets'] > 0): ?>
                                                        <br><small class="text-danger"><?php echo $p['low_stock_outlets']; ?> outlets low</small>
                                                        <?php endif; ?>
                                                     </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <a href="?edit=<?php echo $p['id']; ?>" class="action-btn" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="?delete=<?php echo $p['id']; ?>" class="action-btn text-warning" 
                                                               onclick="return confirm('Archive this product? It can be restored later.')" title="Archive">
                                                                <i class="bi bi-archive"></i>
                                                            </a>
                                                        </div>
                                                     </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-box"></i>
                                <p>No products found<?php echo $search ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?></p>
                                <a href="stock_products.php" class="btn btn-primary btn-sm">View All Products</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    
<?php if ($show_inactive && count($inactive_products) > 0): ?>
<div class="inactive-section mt-3">
    <div class="category-group">
        <div class="group-header" onclick="toggleGroup(this)">
            <span>
                <i class="bi bi-chevron-down"></i>
                <i class="bi bi-archive me-1"></i>
                Archived Products
            </span>
            <span class="badge bg-secondary"><?php echo count($inactive_products); ?> archived</span>
        </div>
        <div class="product-list">
            <div class="table-scroll">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Product / SKU</th>
                            <th>Category</th>
                            <th>Threshold</th>
                            <th class="sis-u-588c2dd8">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inactive_products as $p): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars(substr($p['name'], 0, 30)); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($p['sku']); ?></small>
                             </td>
                            <td><?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo $p['low_stock_threshold']; ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="?restore=<?php echo $p['id']; ?>" class="action-btn text-success" 
                                       onclick="return confirm('Restore this product?')" title="Restore">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                    <a href="?hard_delete=<?php echo $p['id']; ?>" class="action-btn text-danger" 
                                       onclick="return confirm('⚠️ PERMANENTLY DELETE this product? This cannot be undone! ⚠️')" title="Permanently Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                             </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar') || document.querySelector('[data-sis-sidebar]');
            if (sidebar) sidebar.classList.toggle('open');
        }
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar') || document.querySelector('[data-sis-sidebar]');
            const menuToggle = document.querySelector('.menu-toggle') || document.querySelector('[data-sis-sidebar-toggle]');
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                }
            }
        });
        function toggleGroup(headerElement) {
            const productList = headerElement.nextElementSibling;
            const icon = headerElement.querySelector('i:first-child');
            
            if (productList.classList.contains('collapsed')) {
                productList.classList.remove('collapsed');
                if (icon) icon.style.transform = 'rotate(0deg)';
                headerElement.classList.remove('collapsed');
            } else {
                productList.classList.add('collapsed');
                if (icon) icon.style.transform = 'rotate(-90deg)';
                headerElement.classList.add('collapsed');
            }
        }
        function updateThreshold(productId) {
            const form = document.getElementById(`threshold-form-${productId}`);
            const formData = new FormData(form);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(response => response.text()).then(() => {
                const alert = document.createElement('div');
                alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
                alert.style.zIndex = '9999';
                alert.innerHTML = '✓ Threshold updated! <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                document.body.appendChild(alert);
                setTimeout(() => alert.remove(), 3000);
            }).catch(error => {
                console.error('Error:', error);
            });
        }
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const categoryGroups = document.querySelectorAll('.category-group');
                
                categoryGroups.forEach(group => {
                    let hasVisibleProducts = false;
                    const rows = group.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const productName = row.getAttribute('data-product-name') || '';
                        const productSku = row.getAttribute('data-product-sku') || '';
                        const rowText = (productName + ' ' + productSku).toLowerCase();
                        
                        if (searchTerm === '' || rowText.includes(searchTerm)) {
                            row.style.display = '';
                            hasVisibleProducts = true;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    if (hasVisibleProducts || searchTerm === '') {
                        group.style.display = '';
                    } else {
                        group.style.display = 'none';
                    }
                });
            });
            
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const searchValue = encodeURIComponent(this.value);
                    const categoryFilter = document.getElementById('categoryFilter').value;
                    const showInactive = document.querySelector('.filter-btn.active:last-child')?.classList.contains('active') ? 1 : 0;
                    window.location.href = `stock_products.php?search=${searchValue}&category=${categoryFilter}&show_inactive=${showInactive}`;
                }
            });
        }
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                const category = this.value;
                const search = document.getElementById('searchInput').value;
                const showInactive = document.querySelector('.filter-btn.active:last-child')?.classList.contains('active') ? 1 : 0;
                window.location.href = `stock_products.php?search=${encodeURIComponent(search)}&category=${category}&show_inactive=${showInactive}`;
            });
        }
        function applyFilter(type) {
            const search = document.getElementById('searchInput').value;
            const category = document.getElementById('categoryFilter').value;
            const showInactive = type === 'all' ? 1 : 0;
            window.location.href = `stock_products.php?search=${encodeURIComponent(search)}&category=${category}&show_inactive=${showInactive}`;
        }
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
        document.querySelectorAll('.group-header').forEach(header => {
            const productList = header.nextElementSibling;
            if (productList && !productList.classList.contains('collapsed')) {
                productList.classList.remove('collapsed');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
