<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Handle operations
$message = '';
$message_type = '';

// Deactivate supervisor (soft delete)
if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    $query = "UPDATE users SET is_active = 0 WHERE id = $id AND role = 'supervisor'";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'DEACTIVATE_SUPERVISOR', $id);
        $message = 'Supervisor deactivated successfully!';
        $message_type = 'success';
    }
}

// Activate supervisor (restore)
if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    $query = "UPDATE users SET is_active = 1 WHERE id = $id AND role = 'supervisor'";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'ACTIVATE_SUPERVISOR', $id);
        $message = 'Supervisor activated successfully!';
        $message_type = 'success';
    }
}

// Permanent delete (hard delete) – only if no dependencies
if (isset($_GET['hard_delete'])) {
    $id = (int)$_GET['hard_delete'];
    // Check if supervisor has any associated data (stock requests)
    $check_requests = mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_requests WHERE supervisor_id = $id");
    $request_count = mysqli_fetch_assoc($check_requests)['count'] ?? 0;
    
    if ($request_count > 0) {
        $message = "Cannot delete supervisor. They have $request_count stock request(s) associated.";
        $message_type = 'danger';
    } else {
        $query = "DELETE FROM users WHERE id = $id AND role = 'supervisor'";
        if (mysqli_query($conn, $query)) {
            logAction($conn, 'PERMANENT_DELETE_SUPERVISOR', $id);
            $message = 'Supervisor permanently deleted!';
            $message_type = 'success';
        } else {
            $message = 'Error: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
}

// Add/Edit supervisor (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_supervisor'])) {
    $username = mysqli_real_escape_string($conn, (string)($_POST['username'] ?? ''));
    $full_name = mysqli_real_escape_string($conn, (string)($_POST['full_name'] ?? ''));
    $outlet_id = (int)$_POST['outlet_id'];
    $shift = mysqli_real_escape_string($conn, (string)($_POST['shift'] ?? 'Morning'));
    
    // Check if username already exists
    $check_query = "SELECT id FROM users WHERE username = '$username' AND role = 'supervisor'";
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $check_query .= " AND id != " . (int)$_POST['id'];
    }
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $message = 'Username already exists! Please choose a different username.';
        $message_type = 'danger';
    } else {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Edit
            $id = (int)$_POST['id'];
            if (!empty($_POST['password'])) {
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query = "UPDATE users SET username = '$username', full_name = '$full_name', 
                          outlet_id = $outlet_id, shift = '$shift', password_hash = '$password_hash' 
                          WHERE id = $id AND role = 'supervisor'";
            } else {
                $query = "UPDATE users SET username = '$username', full_name = '$full_name', 
                          outlet_id = $outlet_id, shift = '$shift'
                          WHERE id = $id AND role = 'supervisor'";
            }
            $action = 'UPDATE_SUPERVISOR';
            
            if (mysqli_query($conn, $query)) {
                logAction($conn, $action, $id);
                $message = 'Supervisor updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
        } else {
            // Add new
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, password_hash, full_name, role, outlet_id, shift, is_active) 
                      VALUES ('$username', '$password_hash', '$full_name', 'supervisor', $outlet_id, '$shift', 1)";
            
            if (mysqli_query($conn, $query)) {
                $record_id = mysqli_insert_id($conn);
                logAction($conn, 'ADD_SUPERVISOR', $record_id);
                $message = 'Supervisor added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$filter_outlet = isset($_GET['outlet']) ? (int)$_GET['outlet'] : '';
$filter_shift = isset($_GET['shift']) ? mysqli_real_escape_string($conn, (string)($_GET['shift'] ?? '')) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, (string)($_GET['status'] ?? '')) : 'active';

// Get all active outlets for dropdown
$outlets_query = "SELECT id, name, region FROM outlets WHERE is_active = 1 ORDER BY region, name";
$outlets_result = mysqli_query($conn, $outlets_query);

// Build WHERE clause for filtering
$where_conditions = ["u.role = 'supervisor'"];
if ($filter_status == 'active') {
    $where_conditions[] = "u.is_active = 1";
} elseif ($filter_status == 'inactive') {
    $where_conditions[] = "u.is_active = 0";
}
if ($filter_outlet) {
    $where_conditions[] = "u.outlet_id = $filter_outlet";
}
if ($filter_shift) {
    $where_conditions[] = "u.shift = '$filter_shift'";
}
if ($search) {
    $where_conditions[] = "(u.full_name LIKE '%$search%' OR u.username LIKE '%$search%' OR o.name LIKE '%$search%' OR o.region LIKE '%$search%')";
}
$where_clause = implode(' AND ', $where_conditions);

// Get supervisors with filters
$supervisors_query = "SELECT u.*, o.name as outlet_name, o.region as outlet_region 
                     FROM users u 
                     LEFT JOIN outlets o ON u.outlet_id = o.id 
                     WHERE $where_clause 
                     ORDER BY u.is_active DESC, o.region, u.full_name";
$supervisors_result = mysqli_query($conn, $supervisors_query);

// Group supervisors by outlet region
$supervisors_by_region = [];
$unassigned_supervisors = [];
$inactive_supervisors = [];

while ($supervisor = mysqli_fetch_assoc($supervisors_result)) {
    if ($supervisor['is_active'] == 0) {
        $inactive_supervisors[] = $supervisor;
    } else {
        $region = $supervisor['outlet_region'] ?? 'Unassigned';
        if ($region == 'Unassigned') {
            $unassigned_supervisors[] = $supervisor;
        } else {
            if (!isset($supervisors_by_region[$region])) {
                $supervisors_by_region[$region] = [];
            }
            $supervisors_by_region[$region][] = $supervisor;
        }
    }
}

// Sort regions alphabetically
ksort($supervisors_by_region);

// Add unassigned at the end if any
if (!empty($unassigned_supervisors)) {
    $supervisors_by_region['Unassigned'] = $unassigned_supervisors;
}

// Get supervisor for editing
$edit_supervisor = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM users WHERE id = $edit_id AND role = 'supervisor'";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_supervisor = mysqli_fetch_assoc($edit_result);
}

// Count totals
$total_active = 0;
foreach ($supervisors_by_region as $region_supervisors) {
    $total_active += count($region_supervisors);
}

// Get shifts for filter dropdown
$shifts = ['Morning', 'Afternoon', 'Night', 'Rotational'];

// Get status options
$statuses = ['active' => 'Active Only', 'inactive' => 'Inactive Only', 'all' => 'All Supervisors'];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisors - Comium GM</title>
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
            <h3><i class="bi bi-person-badge sis-u-20e33279"></i>Supervisors Management</h3>
            <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Active Supervisors</h6>
                <h3><?php echo $total_active; ?></h3>
                <small>Currently working</small>
            </div>
            <div class="stat-card">
                <h6>Regions Covered</h6>
                <h3><?php echo count(array_filter(array_keys($supervisors_by_region), function($r) { return $r != 'Unassigned'; })); ?></h3>
                <small>Geographic presence</small>
            </div>
            <div class="stat-card">
                <h6>Deactivated</h6>
                <h3><?php echo count($inactive_supervisors); ?></h3>
                <small>Inactive supervisors</small>
            </div>
            <div class="stat-card">
                <h6>Total All Time</h6>
                <h3><?php echo $total_active + count($inactive_supervisors); ?></h3>
                <small>Including deactivated</small>
            </div>
        </div>

        
        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="filter-bar">
            <form class="sis-u-f0d95916" method="GET">
                <div class="search-box">
                    <input type="text" class="form-control" name="search" placeholder="🔍 Search by name, username, outlet, region..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select class="form-select" name="outlet">
                        <option value="">All Outlets</option>
                        <?php 
                        mysqli_data_seek($outlets_result, 0);
                        while($outlet = mysqli_fetch_assoc($outlets_result)): 
                        ?>
                        <option value="<?php echo $outlet['id']; ?>" <?php echo $filter_outlet == $outlet['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($outlet['name']); ?> (<?php echo $outlet['region']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select class="form-select" name="shift">
                        <option value="">All Shifts</option>
                        <?php foreach($shifts as $shift): ?>
                        <option value="<?php echo $shift; ?>" <?php echo $filter_shift == $shift ? 'selected' : ''; ?>>
                            <?php echo $shift; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select class="form-select" name="status">
                        <?php foreach($statuses as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $filter_status == $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="supervisors.php" class="btn btn-outline-secondary">Reset</a>
            </form>
        </div>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?php echo $edit_supervisor ? 'pencil-square' : 'person-plus'; ?>"></i>
                    <?php echo $edit_supervisor ? 'Edit Supervisor' : 'Add New Supervisor'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="save_supervisor" value="1">
                        <?php if($edit_supervisor): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_supervisor['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($edit_supervisor['full_name'] ?? ''); ?>" 
                                   placeholder="e.g., John Doe" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo htmlspecialchars($edit_supervisor['username'] ?? ''); ?>" 
                                   placeholder="e.g., john.doe" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password <?php echo $edit_supervisor ? '<small class="text-muted">(leave blank to keep current)</small>' : '<span class="text-danger">*</span>'; ?></label>
                            <input type="password" class="form-control" name="password" 
                                   <?php echo $edit_supervisor ? '' : 'required'; ?>>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assign Outlet <span class="text-danger">*</span></label>
                            <select class="form-select" name="outlet_id" required>
                                <option value="">Select Outlet</option>
                                <?php 
                                mysqli_data_seek($outlets_result, 0);
                                while($outlet = mysqli_fetch_assoc($outlets_result)): 
                                ?>
                                <option value="<?php echo $outlet['id']; ?>" 
                                    <?php echo ($edit_supervisor['outlet_id'] ?? '') == $outlet['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($outlet['name']); ?> (<?php echo $outlet['region']; ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Supervisor will only see data from this outlet</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Default Shift</label>
                            <select class="form-select" name="shift">
                                <option value="Morning" <?php echo ($edit_supervisor['shift'] ?? 'Morning') == 'Morning' ? 'selected' : ''; ?>>Morning (6:00 - 14:00)</option>
                                <option value="Afternoon" <?php echo ($edit_supervisor['shift'] ?? '') == 'Afternoon' ? 'selected' : ''; ?>>Afternoon (14:00 - 22:00)</option>
                                <option value="Night" <?php echo ($edit_supervisor['shift'] ?? '') == 'Night' ? 'selected' : ''; ?>>Night (22:00 - 6:00)</option>
                                <option value="Rotational" <?php echo ($edit_supervisor['shift'] ?? '') == 'Rotational' ? 'selected' : ''; ?>>Rotational</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_supervisor ? 'Update Supervisor' : 'Add Supervisor'; ?>
                            </button>
                            <?php if($edit_supervisor): ?>
                            <a href="supervisors.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-grid-3x3-gap-fill"></i> Organized by Region</span>
                    <span class="badge bg-secondary"><?php echo $total_active; ?> active supervisors</span>
                </div>
                <div class="card-body p-0">
                    
                    <div id="supervisorsContainer">
                        <?php if (count($supervisors_by_region) > 0): ?>
                            <?php foreach ($supervisors_by_region as $region => $supervisors): ?>
                            <div class="region-group" data-region="<?php echo htmlspecialchars($region); ?>">
                                <div class="region-header" onclick="toggleRegion(this)">
                                    <span>
                                        <i class="bi bi-chevron-down"></i>
                                        <i class="bi bi-geo-alt-fill me-1 sis-u-002e4b71"></i>
                                        <?php echo htmlspecialchars($region); ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo count($supervisors); ?> supervisors</span>
                                </div>
                                <div class="supervisor-list">
                                    <div class="table-responsive">
                                        <table class="region-table">
                                            <thead>
                                                <tr>
                                                    <th>Supervisor</th>
                                                    <th>Outlet</th>
                                                    <th>Shift</th>
                                                    <th>Last Login</th>
                                                    <th class="sis-u-b15addac">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($supervisors as $supervisor): ?>
                                                <tr>
                                                    <td data-supervisor-name="<?php echo strtolower(htmlspecialchars($supervisor['full_name'])); ?>"
                                                        data-supervisor-username="<?php echo strtolower(htmlspecialchars($supervisor['username'])); ?>"
                                                        data-outlet-name="<?php echo strtolower(htmlspecialchars($supervisor['outlet_name'] ?? '')); ?>">
                                                        <div class="d-flex align-items-center">
                                                            <span class="status-dot active"></span>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($supervisor['full_name']); ?></strong>
                                                                <div class="small text-muted">@<?php echo htmlspecialchars($supervisor['username']); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge-outlet badge">
                                                            <?php echo htmlspecialchars($supervisor['outlet_name'] ?? 'Not Assigned'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $shift_class = 'badge-shift-morning';
                                                        if($supervisor['shift'] == 'Afternoon') $shift_class = 'badge-shift-afternoon';
                                                        elseif($supervisor['shift'] == 'Night') $shift_class = 'badge-shift-night';
                                                        elseif($supervisor['shift'] == 'Rotational') $shift_class = 'badge-shift-rotational';
                                                        ?>
                                                        <span class="badge <?php echo $shift_class; ?>">
                                                            <i class="bi bi-clock"></i> <?php echo $supervisor['shift'] ?? 'Morning'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?php echo $supervisor['last_login'] ? date('d/m/Y H:i', strtotime($supervisor['last_login'])) : '<span class="text-muted">Never</span>'; ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <a href="?edit=<?php echo $supervisor['id']; ?>&<?php echo http_build_query(array_filter(['search' => $search, 'outlet' => $filter_outlet, 'shift' => $filter_shift, 'status' => $filter_status])); ?>" 
                                                               class="action-btn" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="?deactivate=<?php echo $supervisor['id']; ?>&<?php echo http_build_query(array_filter(['search' => $search, 'outlet' => $filter_outlet, 'shift' => $filter_shift, 'status' => $filter_status])); ?>" 
                                                               class="action-btn text-warning" 
                                                               onclick="return confirm('Deactivate this supervisor? They will not be able to log in until reactivated.')" 
                                                               title="Deactivate">
                                                                <i class="bi bi-person-x"></i> Deactivate
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
                                <i class="bi bi-person-badge"></i>
                                <p>No active supervisors found<?php echo $search ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?></p>
                                <a href="supervisors.php?status=all" class="btn btn-outline-secondary btn-sm">View all (including deactivated)</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    
                    <?php if (($filter_status == 'inactive' || $filter_status == 'all') && count($inactive_supervisors) > 0): ?>
                    <div class="region-group inactive-section mt-3">
                        <div class="region-header" onclick="toggleRegion(this)">
                            <span>
                                <i class="bi bi-chevron-down"></i>
                                <i class="bi bi-person-x me-1"></i>
                                Deactivated Supervisors
                            </span>
                            <span class="badge bg-secondary"><?php echo count($inactive_supervisors); ?> deactivated</span>
                        </div>
                        <div class="supervisor-list">
                            <div class="table-responsive">
                                <table class="region-table">
                                    <thead>
                                        <tr>
                                            <th>Supervisor</th>
                                            <th>Outlet</th>
                                            <th>Shift</th>
                                            <th>Deactivated Date</th>
                                            <th class="sis-u-14175304">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inactive_supervisors as $supervisor): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="status-dot inactive"></span>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($supervisor['full_name']); ?></strong>
                                                        <div class="small text-muted">@<?php echo htmlspecialchars($supervisor['username']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-outlet badge">
                                                    <?php echo htmlspecialchars($supervisor['outlet_name'] ?? 'Not Assigned'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $shift_class = 'badge-shift-morning';
                                                if($supervisor['shift'] == 'Afternoon') $shift_class = 'badge-shift-afternoon';
                                                elseif($supervisor['shift'] == 'Night') $shift_class = 'badge-shift-night';
                                                elseif($supervisor['shift'] == 'Rotational') $shift_class = 'badge-shift-rotational';
                                                ?>
                                                <span class="badge <?php echo $shift_class; ?>">
                                                    <i class="bi bi-clock"></i> <?php echo $supervisor['shift'] ?? 'Morning'; ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small">
                                                <?php echo $supervisor['last_logout'] ? date('d/m/Y', strtotime($supervisor['last_logout'])) : 'Unknown'; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="?activate=<?php echo $supervisor['id']; ?>&<?php echo http_build_query(array_filter(['search' => $search, 'outlet' => $filter_outlet, 'shift' => $filter_shift, 'status' => $filter_status])); ?>" 
                                                       class="action-btn text-success" 
                                                       onclick="return confirm('Activate this supervisor? They will be able to log in again.')" 
                                                       title="Activate">
                                                        <i class="bi bi-person-check"></i> Activate
                                                    </a>
                                                    <a href="?hard_delete=<?php echo $supervisor['id']; ?>&<?php echo http_build_query(array_filter(['search' => $search, 'outlet' => $filter_outlet, 'shift' => $filter_shift, 'status' => $filter_status])); ?>" 
                                                       class="action-btn text-danger" 
                                                       onclick="return confirm('⚠️ PERMANENTLY DELETE this supervisor? This cannot be undone! ⚠️')" 
                                                       title="Permanently Delete">
                                                        <i class="bi bi-trash"></i> Delete
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        
        <div class="permissions-box sis-exact-dark-panel sis-supervisor-permissions-panel">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="sis-u-e118d5ea"><i class="bi bi-check-circle"></i> Supervisor Permissions:</h6>
                    <ul>
                        <li><i class="bi bi-check-lg text-success"></i> View assigned outlet dashboard</li>
                        <li><i class="bi bi-check-lg text-success"></i> View all visitors & sales</li>
                        <li><i class="bi bi-check-lg text-success"></i> Monitor agent performance</li>
                        <li><i class="bi bi-check-lg text-success"></i> View sales targets</li>
                        <li><i class="bi bi-check-lg text-success"></i> Cash reconciliation</li>
                        <li><i class="bi bi-check-lg text-success"></i> Export reports (CSV)</li>
                        <li><i class="bi bi-check-lg text-success"></i> Request stock transfers</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="sis-u-7d8ba3b6"><i class="bi bi-x-circle"></i> Supervisor Restrictions:</h6>
                    <ul>
                        <li><i class="bi bi-x-lg text-danger"></i> Cannot create/modify services</li>
                        <li><i class="bi bi-x-lg text-danger"></i> Cannot create/modify purposes</li>
                        <li><i class="bi bi-x-lg text-danger"></i> Cannot manage users (agents/admins)</li>
                        <li><i class="bi bi-x-lg text-danger"></i> Cannot access other outlets' data</li>
                        <li><i class="bi bi-x-lg text-danger"></i> Cannot set sales targets</li>
                        <li><i class="bi bi-x-lg text-danger"></i> Cannot modify system settings</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        
        function toggleRegion(headerElement) {
            const supervisorList = headerElement.nextElementSibling;
            const icon = headerElement.querySelector('i:first-child');
            
            if (supervisorList.classList.contains('collapsed')) {
                supervisorList.classList.remove('collapsed');
                if (icon) icon.style.transform = 'rotate(0deg)';
                headerElement.classList.remove('collapsed');
            } else {
                supervisorList.classList.add('collapsed');
                if (icon) icon.style.transform = 'rotate(-90deg)';
                headerElement.classList.add('collapsed');
            }
        }
        
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        }, 5000);
        
        document.querySelectorAll('.region-header').forEach(header => {
            const supervisorList = header.nextElementSibling;
            if (supervisorList && !supervisorList.classList.contains('collapsed')) {
                supervisorList.classList.remove('collapsed');
            }
        });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>