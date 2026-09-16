<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Handle Add/Edit/Delete
$message = '';
$message_type = '';

function sisOutletPostedIds($key = 'outlet_ids') {
    $raw = $_POST[$key] ?? [];
    if (is_array($raw)) {
        $ids = array_map('intval', $raw);
    } else {
        $ids = array_map('intval', preg_split('/[,\s]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY));
    }
    return array_values(array_unique(array_filter($ids, function($id){ return $id > 0; })));
}

// Delete outlet (soft delete - set inactive)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $query = "UPDATE outlets SET is_active = 0 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'DELETE_OUTLET', $id);
        $message = 'Outlet deactivated successfully!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Permanent delete (hard delete) - NEW
if (isset($_GET['hard_delete'])) {
    $id = (int)$_GET['hard_delete'];
    
    // Check if outlet has any agents assigned
    $check_agents = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE outlet_id = $id AND role = 'agent'");
    $agent_count = mysqli_fetch_assoc($check_agents)['count'] ?? 0;
    
    if ($agent_count > 0) {
        $message = "Cannot delete outlet. It has $agent_count agent(s) assigned. Please reassign or delete agents first.";
        $message_type = 'danger';
    } else {
        $query = "DELETE FROM outlets WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            logAction($conn, 'PERMANENT_DELETE_OUTLET', $id);
            $message = 'Outlet permanently deleted!';
            $message_type = 'success';
        } else {
            $message = 'Error: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
}

// Bulk permanent delete - NEW
if (isset($_POST['bulk_permanent_delete']) && !empty(sisOutletPostedIds('outlet_ids'))) {
    $ids = sisOutletPostedIds('outlet_ids');
    $ids_string = implode(',', $ids);
    
    $check_agents = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE outlet_id IN ($ids_string)");
    $agent_count = mysqli_fetch_assoc($check_agents)['count'] ?? 0;
    
    if ($agent_count > 0) {
        $message = "Cannot delete outlets. $agent_count agent(s) are assigned to these outlets.";
        $message_type = 'danger';
    } else {
        $query = "DELETE FROM outlets WHERE id IN ($ids_string)";
        if (mysqli_query($conn, $query)) {
            logAction($conn, 'BULK_PERMANENT_DELETE_OUTLETS', count($ids));
            $message = count($ids) . ' outlet(s) permanently deleted!';
            $message_type = 'success';
        } else {
            $message = 'Error: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
}

// Bulk deactivate - NEW
if (isset($_POST['bulk_deactivate']) && !empty(sisOutletPostedIds('outlet_ids'))) {
    $ids = sisOutletPostedIds('outlet_ids');
    $ids_string = implode(',', $ids);
    $query = "UPDATE outlets SET is_active = 0 WHERE id IN ($ids_string)";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'BULK_DEACTIVATE_OUTLETS', count($ids));
        $message = count($ids) . ' outlet(s) deactivated!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Bulk restore - NEW
if (isset($_POST['bulk_restore']) && !empty(sisOutletPostedIds('outlet_ids'))) {
    $ids = sisOutletPostedIds('outlet_ids');
    $ids_string = implode(',', $ids);
    $query = "UPDATE outlets SET is_active = 1 WHERE id IN ($ids_string)";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'BULK_RESTORE_OUTLETS', count($ids));
        $message = count($ids) . ' outlet(s) restored!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Restore outlet
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $query = "UPDATE outlets SET is_active = 1 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'RESTORE_OUTLET', $id);
        $message = 'Outlet restored successfully!';
        $message_type = 'success';
    }
}

// Add/Edit outlet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_deactivate']) && !isset($_POST['bulk_restore']) && !isset($_POST['bulk_permanent_delete'])) {
    $name = mysqli_real_escape_string($conn, (string)($_POST['name'] ?? ''));
    $location = mysqli_real_escape_string($conn, (string)($_POST['location'] ?? ''));
    $region = mysqli_real_escape_string($conn, (string)($_POST['region'] ?? ''));
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $query = "UPDATE outlets SET name = '$name', location = '$location', region = '$region' WHERE id = $id";
        $action = 'UPDATE_OUTLET';
    } else {
        $query = "INSERT INTO outlets (name, location, region) VALUES ('$name', '$location', '$region')";
        $action = 'ADD_OUTLET';
    }
    
    if (mysqli_query($conn, $query)) {
        $record_id = isset($id) ? $id : mysqli_insert_id($conn);
        logAction($conn, $action, $record_id);
        $message = 'Outlet saved successfully!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$filter_region = isset($_GET['region']) ? mysqli_real_escape_string($conn, (string)($_GET['region'] ?? '')) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, (string)($_GET['status'] ?? '')) : 'active';

// Build WHERE clause for filtering
$where_conditions = [];
if ($filter_status == 'active') {
    $where_conditions[] = "is_active = 1";
} elseif ($filter_status == 'inactive') {
    $where_conditions[] = "is_active = 0";
}
if ($filter_region) {
    $where_conditions[] = "region = '$filter_region'";
}
if ($search) {
    $where_conditions[] = "(name LIKE '%$search%' OR location LIKE '%$search%' OR region LIKE '%$search%')";
}
$where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Get all outlets with filters
$outlets_query = "SELECT * FROM outlets $where_clause ORDER BY name";
$outlets_result = mysqli_query($conn, $outlets_query);

// Get statistics
$total_outlets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM outlets"))['total'];
$active_outlets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM outlets WHERE is_active = 1"))['total'];
$inactive_outlets = $total_outlets - $active_outlets;

// Get region distribution
$region_stats = mysqli_query($conn, "
    SELECT region, COUNT(*) as count 
    FROM outlets 
    WHERE is_active = 1 
    GROUP BY region 
    ORDER BY count DESC
");

// Get outlet for editing
$edit_outlet = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM outlets WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_outlet = mysqli_fetch_assoc($edit_result);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outlets - Comium GM</title>
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
            <h3><i class="bi bi-building sis-u-20e33279"></i>Outlets Management</h3>
            <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Outlets</h6>
                <h3><?php echo $total_outlets; ?></h3>
                <small>All time</small>
            </div>
            <div class="stat-card">
                <h6>Active Outlets</h6>
                <h3><?php echo $active_outlets; ?></h3>
                <small>Currently operating</small>
            </div>
            <div class="stat-card">
                <h6>Inactive Outlets</h6>
                <h3><?php echo $inactive_outlets; ?></h3>
                <small>Closed/temporary</small>
            </div>
            <div class="stat-card">
                <h6>Regions</h6>
                <h3><?php echo mysqli_num_rows($region_stats); ?></h3>
                <small>Covered areas</small>
            </div>
        </div>

        
        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="filter-bar">
            <form class="sis-u-f0d95916" method="GET">
                <div class="search-box">
                    <input type="text" class="form-control" name="search" placeholder="Search by name, location, region..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select class="form-select" name="region">
                    <option value="">All Regions</option>
                    <option value="Banjul" <?php echo $filter_region == 'Banjul' ? 'selected' : ''; ?>>Banjul</option>
                    <option value="Kanifing" <?php echo $filter_region == 'Kanifing' ? 'selected' : ''; ?>>Kanifing</option>
                    <option value="West Coast" <?php echo $filter_region == 'West Coast' ? 'selected' : ''; ?>>West Coast</option>
                    <option value="North Bank" <?php echo $filter_region == 'North Bank' ? 'selected' : ''; ?>>North Bank</option>
                    <option value="Central River" <?php echo $filter_region == 'Central River' ? 'selected' : ''; ?>>Central River</option>
                    <option value="Upper River" <?php echo $filter_region == 'Upper River' ? 'selected' : ''; ?>>Upper River</option>
                </select>
                <select class="form-select" name="status">
                    <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                    <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Outlets</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="outlets.php" class="btn btn-outline-secondary">Reset</a>
            </form>
        </div>

        
        <div id="outletPageWrap">
            
            <div id="bulkActionsBar" class="bulk-actions-bar">
                <div>
                    <i class="bi bi-check2-square"></i>
                    <span id="selectedCount">0</span> outlet(s) selected
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-warning btn-sm" onclick="confirmBulkDeactivate()">
                        <i class="bi bi-building-x"></i> Deactivate
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="confirmBulkRestore()">
                        <i class="bi bi-building-check"></i> Restore
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmBulkDelete()">
                        <i class="bi bi-trash"></i> Permanently Delete
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </div>

            
            <div class="two-col">
                
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-<?php echo $edit_outlet ? 'pencil-square' : 'plus-circle'; ?>"></i>
                        <?php echo $edit_outlet ? 'Edit Outlet' : 'Add New Outlet'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="outletForm">
                        <?php echo csrfField(); ?>
                        <?php if($edit_outlet): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_outlet['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Outlet Name *</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($edit_outlet['name'] ?? ''); ?>" required>
                            <small class="text-muted">Example: Banjul Showroom, Serekunda Branch</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location / Address</label>
                            <input type="text" class="form-control" name="location" 
                                   value="<?php echo htmlspecialchars($edit_outlet['location'] ?? ''); ?>">
                            <small class="text-muted">Street address or area description</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Region *</label>
                            <select class="form-select" name="region" required>
                                <option value="">Select Region</option>
                                <option value="Banjul" <?php echo ($edit_outlet['region'] ?? '') == 'Banjul' ? 'selected' : ''; ?>>Banjul</option>
                                <option value="Kanifing" <?php echo ($edit_outlet['region'] ?? '') == 'Kanifing' ? 'selected' : ''; ?>>Kanifing</option>
                                <option value="West Coast" <?php echo ($edit_outlet['region'] ?? '') == 'West Coast' ? 'selected' : ''; ?>>West Coast</option>
                                <option value="North Bank" <?php echo ($edit_outlet['region'] ?? '') == 'North Bank' ? 'selected' : ''; ?>>North Bank</option>
                                <option value="Central River" <?php echo ($edit_outlet['region'] ?? '') == 'Central River' ? 'selected' : ''; ?>>Central River</option>
                                <option value="Upper River" <?php echo ($edit_outlet['region'] ?? '') == 'Upper River' ? 'selected' : ''; ?>>Upper River</option>
                            </select>
                        </div>
                        
                        <div class="sis-u-0ed0e90f">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_outlet ? 'Update Outlet' : 'Add Outlet'; ?>
                            </button>
                            <?php if($edit_outlet): ?>
                            <a href="outlets.php" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                
                <div class="card-header mt-2 sis-region-distribution-header">
                    <i class="bi bi-pie-chart"></i> Region Distribution
                </div>
                <div class="card-body sis-exact-dark-section sis-region-distribution-panel">
                    <div class="region-stats">
                        <?php if(mysqli_num_rows($region_stats) > 0): ?>
                            <?php while($region = mysqli_fetch_assoc($region_stats)): ?>
                            <div class="region-stat">
                                <span class="name"><?php echo $region['region']; ?></span>
                                <span class="count"><?php echo $region['count']; ?> outlet(s)</span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-muted text-center">No region data available</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            
            <form method="POST" id="bulkActionForm">
            <?php echo csrfField(); ?>
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list"></i> Outlet Directory</span>
                    <span class="badge bg-secondary">
                        <?php echo mysqli_num_rows($outlets_result); ?> outlet(s) found
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="checkbox-col"><input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)"></th>
                                    <th>Outlet</th>
                                    <th>Region</th>
                                    <th>Location</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                    <th class="sis-u-e3141199">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($outlets_result) > 0): ?>
                                    <?php while($outlet = mysqli_fetch_assoc($outlets_result)): ?>
                                    <tr>
                                        <td class="checkbox-col">
                                            <input type="checkbox" class="outlet-checkbox" value="<?php echo $outlet['id']; ?>" onclick="updateSelectedCount()">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="outlet-icon">
                                                    <i class="bi bi-shop"></i>
                                                </div>
                                                <div class="outlet-info">
                                                    <h6><?php echo htmlspecialchars($outlet['name']); ?></h6>
                                                    <?php if($outlet['location']): ?>
                                                    <small><?php echo htmlspecialchars($outlet['location']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-region">
                                                <?php echo $outlet['region'] ?? 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $outlet['location'] ? htmlspecialchars($outlet['location']) : '—'; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($outlet['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $outlet['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $outlet['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                
                                                <a href="?edit=<?php echo $outlet['id']; ?>&<?php echo http_build_query(array_filter(['search' => $search, 'region' => $filter_region, 'status' => $filter_status])); ?>" 
                                                   class="action-btn" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                
                                                
                                                <?php if($outlet['is_active']): ?>
                                                <a href="?delete=<?php echo $outlet['id']; ?>&<?php echo http_build_query(array_filter(['search' => $search, 'region' => $filter_region, 'status' => $filter_status])); ?>" 
                                                   class="action-btn text-warning" 
                                                   onclick="return confirm('Deactivate this outlet? It can be restored later.')" 
                                                   title="Deactivate">
                                                    <i class="bi bi-building-x"></i>
                                                </a>
                                                <?php else: ?>
                                                <a href="?restore=<?php echo $outlet['id']; ?>&<?php echo http_build_query(array_filter(['search' => $search, 'region' => $filter_region, 'status' => $filter_status])); ?>" 
                                                   class="action-btn text-success" 
                                                   onclick="return confirm('Restore this outlet?')" 
                                                   title="Restore">
                                                    <i class="bi bi-building-check"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                
                                                <a href="?hard_delete=<?php echo $outlet['id']; ?>&<?php echo http_build_query(array_filter(['search' => $search, 'region' => $filter_region, 'status' => $filter_status])); ?>" 
                                                   class="action-btn text-danger" 
                                                   onclick="return confirm('⚠️ PERMANENTLY DELETE this outlet? This cannot be undone! ⚠️')" 
                                                   title="Permanently Delete">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="bi bi-building sis-u-8c1fb573"></i>
                                            <p class="text-muted mt-2">No outlets found</p>
                                            <small class="text-muted">Try adjusting your search filters or add a new outlet</small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
        </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let selectedOutlets = new Set();
    
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
    
    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.outlet-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = source.checked;
        });
        updateSelectedCount();
    }
    
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.outlet-checkbox:checked');
        selectedOutlets.clear();
        checkboxes.forEach(cb => {
            selectedOutlets.add(cb.value);
        });
        
        const count = selectedOutlets.size;
        document.getElementById('selectedCount').innerText = count;
        
        const bulkBar = document.getElementById('bulkActionsBar');
        if (count > 0) {
            bulkBar.classList.add('show');
        } else {
            bulkBar.classList.remove('show');
        }
        
        const allCheckboxes = document.querySelectorAll('.outlet-checkbox');
        const selectAll = document.getElementById('selectAllCheckbox');
        if (selectAll) {
            selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
        }
    }
    
    function clearSelection() {
        document.querySelectorAll('.outlet-checkbox').forEach(cb => {
            cb.checked = false;
        });
        updateSelectedCount();
    }
    
    function confirmBulkDeactivate() {
        const count = selectedOutlets.size;
        if (count === 0) {
            alert('Please select at least one outlet to deactivate.');
            return;
        }
        (window.sisCenterConfirm ? window.sisCenterConfirm(`Deactivate ${count} outlet(s)? They can be restored later.`, 'Confirm Deactivate') : Promise.resolve(confirm(`Deactivate ${count} outlet(s)? They can be restored later.`))).then(function(ok){ if (!ok) return;
            const form = document.getElementById('bulkActionForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bulk_deactivate';
            input.value = '1';
            form.appendChild(input);
            
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'outlet_ids';
            idsInput.value = Array.from(selectedOutlets).join(',');
            form.appendChild(idsInput);
            
            form.submit();
        });
    }
    
    function confirmBulkRestore() {
        const count = selectedOutlets.size;
        if (count === 0) {
            alert('Please select at least one outlet to restore.');
            return;
        }
        (window.sisCenterConfirm ? window.sisCenterConfirm(`Restore ${count} outlet(s)?`, 'Confirm Restore') : Promise.resolve(confirm(`Restore ${count} outlet(s)?`))).then(function(ok){ if (!ok) return;
            const form = document.getElementById('bulkActionForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bulk_restore';
            input.value = '1';
            form.appendChild(input);
            
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'outlet_ids';
            idsInput.value = Array.from(selectedOutlets).join(',');
            form.appendChild(idsInput);
            
            form.submit();
        });
    }
    
    function confirmBulkDelete() {
        const count = selectedOutlets.size;
        if (count === 0) {
            alert('Please select at least one outlet to delete.');
            return;
        }
        const message = `⚠️ PERMANENT DELETE ⚠️\n\nAre you sure you want to permanently delete ${count} outlet(s)?\n\nThis action CANNOT be undone!`;
        (window.sisCenterConfirm ? window.sisCenterConfirm(message, 'Confirm Permanent Delete') : Promise.resolve(confirm(message))).then(function(ok){
            if (!ok) return;
            const form = document.getElementById('bulkActionForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bulk_permanent_delete';
            input.value = '1';
            form.appendChild(input);
            
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'outlet_ids';
            idsInput.value = Array.from(selectedOutlets).join(',');
            form.appendChild(idsInput);
            
            form.submit();
        });
    }
    
    document.querySelectorAll('.outlet-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }
    }, 5000);
</script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>