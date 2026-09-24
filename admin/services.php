<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
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

// All state changes use POST + CSRF. GET is reserved for filters and editing views.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
    $message = 'Security check failed. Please refresh and try again.';
    $message_type = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $id = (int)$_POST['delete'];
    $query = "UPDATE services SET is_active = 0 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'DELETE_SERVICE', $id);
        $message = 'Service archived!';
        $message_type = 'success';
    }
}

// Restore service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['restore'])) {
    $id = (int)$_POST['restore'];
    $query = "UPDATE services SET is_active = 1 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'RESTORE_SERVICE', $id);
        $message = 'Service restored!';
        $message_type = 'success';
    }
}

// Permanent delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['hard_delete'])) {
    $id = (int)$_POST['hard_delete'];
    $query = "DELETE FROM services WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'PERMANENT_DELETE_SERVICE', $id);
        $message = 'Service permanently deleted!';
        $message_type = 'success';
    }
}

// Toggle paid/free
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['toggle_paid'])) {
    $id = (int)$_POST['toggle_paid'];
    $query = "UPDATE services SET is_paid = NOT is_paid WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'TOGGLE_SERVICE_PAID', $id);
        $message = 'Payment status updated!';
        $message_type = 'success';
    }
}

// Add/Edit service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['save_service'])) {
    $name = mysqli_real_escape_string($conn, (string)($_POST['name'] ?? ''));
    $price = (float)$_POST['price'];
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $is_fixed_price = isset($_POST['is_fixed_price']) ? 1 : 0;
    $is_quantity_based = isset($_POST['is_quantity_based']) ? 1 : 0;
    $unit_name = mysqli_real_escape_string($conn, (string)($_POST['unit_name'] ?? 'unit'));
    $min_amount = !empty($_POST['min_amount']) ? (float)$_POST['min_amount'] : 'NULL';
    $max_amount = !empty($_POST['max_amount']) ? (float)$_POST['max_amount'] : 'NULL';
    $description = mysqli_real_escape_string($conn, (string)($_POST['description'] ?? ''));
    
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
        $message = sisDbErrorMessage($conn);
        $message_type = 'danger';
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$filter_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, (string)($_GET['type'] ?? '')) : '';

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
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>


    
    <div class="main-content">
        
        <div class="page-header">
            <h3><i class="bi bi-grid sis-u-20e33279"></i>Services Management</h3>
            <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
        </div>

        
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

        
        <?php if($message): ?>
        <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="filter-bar">
            <form class="sis-u-f0d95916" method="GET">
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

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?php echo $edit_service ? 'pencil-square' : 'plus-circle'; ?>"></i>
                    <?php echo $edit_service ? 'Edit Service' : 'Add New Service'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" id="serviceForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="save_service" value="1">
                        <?php if($edit_service): ?>
                        <input type="hidden" name="id" value="<?php echo e($edit_service['id']); ?>">
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
                        
                        <div id="priceTypeSection" class="<?php echo (!isset($edit_service) || $edit_service['is_paid'] == 1) ? '' : 'sis-hidden'; ?>">
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
                                   value="<?php echo e($edit_service['price'] ?? '0.00'); ?>">
                        </div>
                        
                        <div class="sis-u-224b51a7" id="flexiblePriceSection">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Min Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="min_amount" id="minAmount"
                                           value="<?php echo e($edit_service['min_amount'] ?? ''); ?>" placeholder="Optional">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Max Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="max_amount" id="maxAmount"
                                           value="<?php echo e($edit_service['max_amount'] ?? ''); ?>" placeholder="Optional">
                                </div>
                            </div>
                        </div>
                        
                        <div class="sis-u-224b51a7" id="quantitySection">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_quantity_based" id="isQuantityBased" value="1"
                                           <?php echo (isset($edit_service['is_quantity_based']) && $edit_service['is_quantity_based'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isQuantityBased">📦 Quantity Based</label>
                                </div>
                            </div>
                            
                            <div id="quantityDetails" class="mb-3 <?php echo (isset($edit_service['is_quantity_based']) && $edit_service['is_quantity_based'] == 1) ? '' : 'sis-hidden'; ?>">
                                <label class="form-label">Unit Name</label>
                                <input type="text" class="form-control" name="unit_name" placeholder="e.g., SIM, GB, minute"
                                       value="<?php echo e($edit_service['unit_name'] ?? 'unit'); ?>">
                                <small class="text-muted">Price per unit × quantity</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Brief description of the service"><?php echo htmlspecialchars($edit_service['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="alert alert-info mb-3 small sis-u-501691c3" id="priceHint">
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

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list-ul"></i> Services Directory</span>
                    <span class="badge bg-secondary"><?php echo $total_active; ?> active</span>
                </div>
                <div class="card-body p-0">
                    
                    <?php if (count($paid_services) > 0): ?>
                    <div class="service-group" data-group="paid">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <span>
                                <i class="bi bi-chevron-down"></i>
                                <i class="bi bi-cash-stack me-1 sis-u-e118d5ea"></i>
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
                                            <th class="sis-u-e3d221c5">Actions</th>
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
                                             </td>
                                            <td>
                                                <span class="badge <?php echo $service['is_quantity_based'] ? 'badge-quantity' : ($service['is_fixed_price'] ? 'badge-fixed' : 'badge-flexible'); ?>">
                                                    <?php echo $service['is_quantity_based'] ? 'Quantity' : ($service['is_fixed_price'] ? 'Fixed' : 'Flexible'); ?>
                                                </span>
                                                <div class="small mt-1"><?php echo $price_display; ?></div>
                                             </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="?edit=<?php echo e($service['id']); ?>" class="action-btn" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="post" class="d-inline"><?php echo csrfField(); ?><input type="hidden" name="toggle_paid" value="<?php echo (int)$service['id']; ?>"><button class="action-btn text-info border-0 bg-transparent" title="Toggle Free"><i class="bi bi-gift"></i></button></form>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Archive this service?')"><?php echo csrfField(); ?><input type="hidden" name="delete" value="<?php echo (int)$service['id']; ?>"><button class="action-btn text-warning border-0 bg-transparent" title="Archive"><i class="bi bi-archive"></i></button></form>
                                                </div>
                                             </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    
                    <?php if (count($free_services) > 0): ?>
                    <div class="service-group" data-group="free">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <span>
                                <i class="bi bi-chevron-down"></i>
                                <i class="bi bi-gift me-1 sis-u-ff8a0ce5"></i>
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
                                            <th class="sis-u-e3d221c5">Actions</th>
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
                                             </td>
                                            <td>
                                                <span class="badge badge-free">Free</span>
                                             </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="?edit=<?php echo e($service['id']); ?>" class="action-btn" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form method="post" class="d-inline"><?php echo csrfField(); ?><input type="hidden" name="toggle_paid" value="<?php echo (int)$service['id']; ?>"><button class="action-btn text-success border-0 bg-transparent" title="Make Paid"><i class="bi bi-cash"></i></button></form>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Archive this service?')"><?php echo csrfField(); ?><input type="hidden" name="delete" value="<?php echo (int)$service['id']; ?>"><button class="action-btn text-warning border-0 bg-transparent" title="Archive"><i class="bi bi-archive"></i></button></form>
                                                </div>
                                             </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    
                    <?php if (count($paid_services) == 0 && count($free_services) == 0 && count($inactive_services) == 0): ?>
                    <div class="empty-state">
                        <i class="bi bi-grid"></i>
                        <p>No services found<?php echo $search ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?></p>
                        <a href="services.php" class="btn btn-primary btn-sm">View All Services</a>
                    </div>
                    <?php endif; ?>

                    
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
                                            <th class="sis-u-e3d221c5">Actions</th>
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
                                             </td>
                                            <td>
                                                <span class="badge <?php echo $service['is_paid'] ? 'badge-paid' : 'badge-free'; ?>">
                                                    <?php echo $original_type; ?>
                                                </span>
                                             </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Restore this service?')"><?php echo csrfField(); ?><input type="hidden" name="restore" value="<?php echo (int)$service['id']; ?>"><button class="action-btn text-success border-0 bg-transparent" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('PERMANENTLY DELETE this service? This cannot be undone!')"><?php echo csrfField(); ?><input type="hidden" name="hard_delete" value="<?php echo (int)$service['id']; ?>"><button class="action-btn text-danger border-0 bg-transparent" title="Permanently Delete"><i class="bi bi-trash"></i></button></form>
                                                </div>
                                             </td>
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
        updateForm();
        document.querySelectorAll('.group-header').forEach(header => {
            const serviceList = header.nextElementSibling;
            if (serviceList && !serviceList.classList.contains('collapsed')) {
                serviceList.classList.remove('collapsed');
            }
        });
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
