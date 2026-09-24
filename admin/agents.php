<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Handle operations
$message = '';
$message_type = '';

// Deactivate agent (soft delete)
if (sisPostAction('delete')) {
    $id = (int)$_POST['delete'];
    $query = "UPDATE users SET is_active = 0 WHERE id = $id AND role = 'agent'";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'DEACTIVATE_AGENT', $id);
        $message = 'Agent deactivated successfully!';
        $message_type = 'success';
    }
}

// Activate agent
if (sisPostAction('restore')) {
    $id = (int)$_POST['restore'];
    $query = "UPDATE users SET is_active = 1 WHERE id = $id AND role = 'agent'";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'ACTIVATE_AGENT', $id);
        $message = 'Agent activated successfully!';
        $message_type = 'success';
    }
}

// PERMANENT DELETE AGENT (with dependency check)
if (sisPostAction('hard_delete')) {
    $id = (int)$_POST['hard_delete'];
    
    // Check for related records before deletion
    $checks = [];
    
    // Check sales
    $sales_check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM sales WHERE agent_id = $id");
    $sales_cnt = mysqli_fetch_assoc($sales_check)['cnt'];
    if ($sales_cnt > 0) $checks[] = "$sales_cnt sale(s)";
    
    // Check visitors (agent assigned as creator)
    $visitors_check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM visitors WHERE agent_id = $id");
    $visitors_cnt = mysqli_fetch_assoc($visitors_check)['cnt'];
    if ($visitors_cnt > 0) $checks[] = "$visitors_cnt visitor record(s)";
    
    // Check stock movements (if any)
    $movements_check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM stock_movements WHERE created_by = $id");
    $movements_cnt = mysqli_fetch_assoc($movements_check)['cnt'];
    if ($movements_cnt > 0) $checks[] = "$movements_cnt stock movement(s)";
    
    // Check stock transfers (requested_by, approved_by, etc.)
    $transfers_check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM stock_transfers WHERE requested_by = $id OR approved_by = $id OR transferred_by = $id OR received_by = $id");
    $transfers_cnt = mysqli_fetch_assoc($transfers_check)['cnt'];
    if ($transfers_cnt > 0) $checks[] = "$transfers_cnt stock transfer(s)";
    
    if (!empty($checks)) {
        $message = "Cannot delete agent: They have associated records (" . implode(', ', $checks) . "). Deactivate instead or reassign records first.";
        $message_type = 'danger';
    } else {
        $query = "DELETE FROM users WHERE id = $id AND role = 'agent'";
        if (mysqli_query($conn, $query)) {
            logAction($conn, 'PERMANENT_DELETE_AGENT', $id);
            $message = 'Agent permanently deleted!';
            $message_type = 'success';
        } else {
            $message = sisDbErrorMessage($conn);
            $message_type = 'danger';
        }
    }
}

// Add/Edit agent (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_agent'])) {
    $editing = (int)($_POST['id'] ?? 0) > 0;
    [$saved, $saveError, $record_id] = sisSaveStaffUser(
        $conn, 'agent', $_POST['id'] ?? 0, $_POST['username'] ?? '', $_POST['full_name'] ?? '',
        $_POST['outlet_id'] ?? 0, $_POST['shift'] ?? '', $_POST['password'] ?? ''
    );
    if ($saved) {
        logAction($conn, $editing ? 'UPDATE_AGENT' : 'ADD_AGENT', $record_id);
        $message = $editing ? 'Agent updated successfully!' : 'Agent added successfully!';
        $message_type = 'success';
    } else {
        $message = $saveError;
        $message_type = 'danger';
    }
}

// Get search term
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';

// Get all active outlets for dropdown
$outlets_query = "SELECT id, name, region FROM outlets WHERE is_active = 1 ORDER BY region, name";
$outlets_result = mysqli_query($conn, $outlets_query);

// Get agents with search and status filter
if ($search) {
    $agents_query = "SELECT u.*, o.name as outlet_name, o.region as outlet_region 
                    FROM users u 
                    LEFT JOIN outlets o ON u.outlet_id = o.id 
                    WHERE u.role = 'agent' 
                    AND (u.is_active = 1 OR u.is_active = 0)
                    AND (u.full_name LIKE '%$search%' 
                         OR u.username LIKE '%$search%' 
                         OR o.name LIKE '%$search%'
                         OR o.region LIKE '%$search%')
                    ORDER BY u.is_active DESC, o.region, u.full_name";
} elseif ($show_inactive) {
    $agents_query = "SELECT u.*, o.name as outlet_name, o.region as outlet_region 
                    FROM users u 
                    LEFT JOIN outlets o ON u.outlet_id = o.id 
                    WHERE u.role = 'agent' 
                    ORDER BY u.is_active DESC, o.region, u.full_name";
} else {
    $agents_query = "SELECT u.*, o.name as outlet_name, o.region as outlet_region 
                    FROM users u 
                    LEFT JOIN outlets o ON u.outlet_id = o.id 
                    WHERE u.role = 'agent' AND u.is_active = 1 
                    ORDER BY o.region, u.full_name";
}
$agents_result = mysqli_query($conn, $agents_query);

// Group agents by outlet region
$agents_by_region = [];
$unassigned_agents = [];
$inactive_agents = [];

while ($agent = mysqli_fetch_assoc($agents_result)) {
    if ($agent['is_active'] == 0) {
        $inactive_agents[] = $agent;
    } else {
        $region = $agent['outlet_region'] ?? 'Unassigned';
        if (!isset($agents_by_region[$region])) {
            $agents_by_region[$region] = [];
        }
        $agents_by_region[$region][] = $agent;
    }
}

ksort($agents_by_region);

// Get agent for editing
$edit_agent = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM users WHERE id = $edit_id AND role = 'agent'";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_agent = mysqli_fetch_assoc($edit_result);
}

// Count totals
$total_active = 0;
foreach ($agents_by_region as $region_agents) {
    $total_active += count($region_agents);
}

// Get shift counts for stats
$shift_counts = ['Morning' => 0, 'Afternoon' => 0, 'Night' => 0, 'Rotational' => 0];
foreach ($agents_by_region as $region_agents) {
    foreach ($region_agents as $agent) {
        $shift = $agent['shift'] ?? 'Morning';
        if (isset($shift_counts[$shift])) {
            $shift_counts[$shift]++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents - Comium GM</title>
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
            <h3><i class="bi bi-people sis-u-20e33279"></i>Agents Management</h3>
            <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Active Agents</h6>
                <h3><?php echo $total_active; ?></h3>
                <small>Currently working</small>
            </div>
            <div class="stat-card">
                <h6>Regions Covered</h6>
                <h3><?php echo count($agents_by_region); ?></h3>
                <small>Geographic presence</small>
            </div>
            <div class="stat-card">
                <h6>Deactivated</h6>
                <h3><?php echo count($inactive_agents); ?></h3>
                <small>Inactive agents</small>
            </div>
            <div class="stat-card">
                <h6>Total All Time</h6>
                <h3><?php echo $total_active + count($inactive_agents); ?></h3>
                <small>Including deactivated</small>
            </div>
        </div>

        
        <?php if($message): ?>
        <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?php echo $edit_agent ? 'pencil-square' : 'person-plus'; ?>"></i>
                    <?php echo $edit_agent ? 'Edit Agent' : 'Add New Agent'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="save_agent" value="1">
                        <?php if($edit_agent): ?>
                        <input type="hidden" name="id" value="<?php echo e($edit_agent['id']); ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($edit_agent['full_name'] ?? ''); ?>" 
                                   placeholder="e.g., John Doe" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo htmlspecialchars($edit_agent['username'] ?? ''); ?>" 
                                   placeholder="e.g., john.doe" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password <?php echo $edit_agent ? '<small class="text-muted">(leave blank to keep current)</small>' : '<span class="text-danger">*</span>'; ?></label>
                            <input type="password" class="form-control" name="password" minlength="8" autocomplete="new-password"
                                   <?php echo $edit_agent ? '' : 'required'; ?>>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assign Outlet <span class="text-danger">*</span></label>
                            <select class="form-select" name="outlet_id" required>
                                <option value="">Select Outlet</option>
                                <?php 
                                mysqli_data_seek($outlets_result, 0);
                                while($outlet = mysqli_fetch_assoc($outlets_result)): 
                                ?>
                                <option value="<?php echo e($outlet['id']); ?>" 
                                    <?php echo ($edit_agent['outlet_id'] ?? '') == $outlet['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($outlet['name']); ?> (<?php echo e($outlet['region']); ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Agent will only see data from this outlet</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Default Shift</label>
                            <select class="form-select" name="shift">
                                <option value="Morning" <?php echo ($edit_agent['shift'] ?? 'Morning') == 'Morning' ? 'selected' : ''; ?>>Morning (6:00 - 14:00)</option>
                                <option value="Afternoon" <?php echo ($edit_agent['shift'] ?? '') == 'Afternoon' ? 'selected' : ''; ?>>Afternoon (14:00 - 22:00)</option>
                                <option value="Night" <?php echo ($edit_agent['shift'] ?? '') == 'Night' ? 'selected' : ''; ?>>Night (22:00 - 6:00)</option>
                                <option value="Rotational" <?php echo ($edit_agent['shift'] ?? '') == 'Rotational' ? 'selected' : ''; ?>>Rotational</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_agent ? 'Update Agent' : 'Save Agent'; ?>
                            </button>
                            <?php if($edit_agent): ?>
                            <a href="agents.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                    
                    
                    <?php if ($total_active > 0): ?>
                    <hr>
                    <div class="mt-3 sis-exact-dark-section sis-shift-distribution-panel">
                        <h6 class="mb-2"><i class="bi bi-clock-history"></i> Shift Distribution</h6>
                        <div class="shift-stats">
                            <div class="shift-stat">
                                <span class="text-primary"><?php echo e($shift_counts['Morning']); ?></span>
                                <small>Morning</small>
                            </div>
                            <div class="shift-stat">
                                <span class="text-warning"><?php echo e($shift_counts['Afternoon']); ?></span>
                                <small>Afternoon</small>
                            </div>
                            <div class="shift-stat">
                                <span class="text-info"><?php echo e($shift_counts['Night']); ?></span>
                                <small>Night</small>
                            </div>
                            <div class="shift-stat">
                                <span class="text-secondary"><?php echo e($shift_counts['Rotational']); ?></span>
                                <small>Rotational</small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-grid-3x3-gap-fill"></i> Organized by Region</span>
                    <span class="badge bg-secondary"><?php echo $total_active; ?> active agents</span>
                </div>
                <div class="card-body">
                    
                    <div class="toolbar">
                        <div class="search-bar w-100">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" 
                                       placeholder="Search by name, username, outlet or region..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <?php if($search): ?>
                                <a href="agents.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="filter-buttons">
                            <button class="filter-btn <?php echo !$show_inactive ? 'active' : ''; ?>" 
                                    onclick="window.location.href='agents.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>'">
                                <i class="bi bi-person-check"></i> Active
                            </button>
                            <button class="filter-btn <?php echo $show_inactive ? 'active' : ''; ?>" 
                                    onclick="window.location.href='agents.php?show_inactive=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>'">
                                <i class="bi bi-archive"></i> All (incl. Deactivated)
                            </button>
                        </div>
                    </div>

                    
                    <div id="agentsContainer">
                        <?php if (count($agents_by_region) > 0): ?>
                            <?php foreach ($agents_by_region as $region => $agents): ?>
                            <div class="region-group" data-region="<?php echo htmlspecialchars($region); ?>">
                                <div class="region-header" onclick="toggleRegion(this)">
                                    <span>
                                        <i class="bi bi-chevron-down"></i>
                                        <i class="bi bi-geo-alt-fill me-1 sis-u-002e4b71"></i>
                                        <?php echo htmlspecialchars($region); ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo count($agents); ?> agents</span>
                                </div>
                                <div class="agent-list">
                                    <table class="region-table">
                                        <thead>
                                            <tr>
                                                <th>Agent</th>
                                                <th>Outlet</th>
                                                <th>Shift</th>
                                                <th>Last Login</th>
                                                <th class="sis-u-b15addac">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($agents as $agent): 
                                                $shift_class = 'badge-shift';
                                                if($agent['shift'] == 'Morning') $shift_class = 'badge-morning';
                                                else if($agent['shift'] == 'Afternoon') $shift_class = 'badge-afternoon';
                                                else if($agent['shift'] == 'Night') $shift_class = 'badge-night';
                                                else if($agent['shift'] == 'Rotational') $shift_class = 'badge-rotational';
                                            ?>
                                            <tr data-agent-name="<?php echo strtolower(htmlspecialchars($agent['full_name'])); ?>"
                                                data-agent-username="<?php echo strtolower(htmlspecialchars($agent['username'])); ?>"
                                                data-outlet-name="<?php echo strtolower(htmlspecialchars($agent['outlet_name'] ?? '')); ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="status-dot active"></span>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($agent['full_name']); ?></strong>
                                                            <div class="small text-muted">@<?php echo htmlspecialchars($agent['username']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge-outlet badge">
                                                        <?php echo htmlspecialchars($agent['outlet_name'] ?? 'Not Assigned'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $shift_class; ?>">
                                                        <i class="bi bi-clock"></i> <?php echo e($agent['shift'] ?? 'Morning'); ?>
                                                    </span>
                                                </td>
                                                <td class="text-muted small">
                                                    <?php echo $agent['last_login'] ? date('d/m/Y H:i', strtotime($agent['last_login'])) : 'Never'; ?>
                                                </td>
                                                <td>
                                                    
                                                    <a href="?edit=<?php echo e($agent['id']); ?>" class="action-btn" data-tooltip="Edit agent">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    
                                                    <a href="?delete=<?php echo e($agent['id']); ?>" class="action-btn text-warning" 
                                                       onclick="return confirm('Deactivate this agent? They will not be able to log in until reactivated.')" data-tooltip="Deactivate agent">
                                                        <i class="bi bi-person-x"></i>
                                                    </a>
                                                    
                                                    <a href="?hard_delete=<?php echo e($agent['id']); ?>" class="action-btn text-danger" 
                                                       onclick="return confirm('⚠️ PERMANENTLY DELETE this agent? This action cannot be undone and will remove all associated data. Deactivate is recommended unless you are sure there are no dependencies. Continue?')" 
                                                       data-tooltip="Permanently delete (irreversible)">
                                                        <i class="bi bi-trash3"></i>
                                                    </a>
                                                </tr>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <p>No agents found<?php echo $search ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?></p>
                                <a href="agents.php" class="btn btn-primary btn-sm">View All Agents</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    
                    <?php if ($show_inactive && count($inactive_agents) > 0): ?>
                    <div class="region-group inactive-section mt-3">
                        <div class="region-header" onclick="toggleRegion(this)">
                            <span>
                                <i class="bi bi-chevron-down"></i>
                                <i class="bi bi-archive me-1"></i>
                                Deactivated Agents
                            </span>
                            <span class="badge bg-secondary"><?php echo count($inactive_agents); ?> deactivated</span>
                        </div>
                        <div class="agent-list">
                            <table class="region-table">
                                <thead>
                                    <tr>
                                        <th>Agent</th>
                                        <th>Outlet</th>
                                        <th>Shift</th>
                                        <th>Deactivated Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inactive_agents as $agent): 
                                        $shift_class = 'badge-shift';
                                        if($agent['shift'] == 'Morning') $shift_class = 'badge-morning';
                                        else if($agent['shift'] == 'Afternoon') $shift_class = 'badge-afternoon';
                                        else if($agent['shift'] == 'Night') $shift_class = 'badge-night';
                                        else if($agent['shift'] == 'Rotational') $shift_class = 'badge-rotational';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="status-dot inactive"></span>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($agent['full_name']); ?></strong>
                                                    <div class="small text-muted">@<?php echo htmlspecialchars($agent['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-outlet badge">
                                                <?php echo htmlspecialchars($agent['outlet_name'] ?? 'Not Assigned'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $shift_class; ?>">
                                                <i class="bi bi-clock"></i> <?php echo e($agent['shift'] ?? 'Morning'); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo $agent['last_logout'] ? date('d/m/Y', strtotime($agent['last_logout'])) : 'Unknown'; ?>
                                        </td>
                                        <td>
                                            
                                            <a href="?restore=<?php echo e($agent['id']); ?>" class="action-btn text-success" 
                                               onclick="return confirm('Activate this agent? They will be able to log in again.')" data-tooltip="Activate agent">
                                                <i class="bi bi-person-check"></i>
                                            </a>
                                            
                                            <a href="?hard_delete=<?php echo e($agent['id']); ?>" class="action-btn text-danger" 
                                               onclick="return confirm('⚠️ PERMANENTLY DELETE this agent? This cannot be undone!')" data-tooltip="Permanently delete">
                                                <i class="bi bi-trash3"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                             </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>        function toggleRegion(headerElement) {
            const agentList = headerElement.nextElementSibling;
            const icon = headerElement.querySelector('i:first-child');
            
            if (agentList.classList.contains('collapsed')) {
                agentList.classList.remove('collapsed');
                if (icon) icon.style.transform = 'rotate(0deg)';
                headerElement.classList.remove('collapsed');
            } else {
                agentList.classList.add('collapsed');
                if (icon) icon.style.transform = 'rotate(-90deg)';
                headerElement.classList.add('collapsed');
            }
        }
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const regionGroups = document.querySelectorAll('.region-group');
                
                regionGroups.forEach(group => {
                    let hasVisibleAgents = false;
                    const rows = group.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const agentName = row.getAttribute('data-agent-name') || '';
                        const agentUsername = row.getAttribute('data-agent-username') || '';
                        const outletName = row.getAttribute('data-outlet-name') || '';
                        const rowText = (agentName + ' ' + agentUsername + ' ' + outletName).toLowerCase();
                        
                        if (searchTerm === '' || rowText.includes(searchTerm)) {
                            row.style.display = '';
                            hasVisibleAgents = true;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    if (hasVisibleAgents || searchTerm === '') {
                        group.style.display = '';
                    } else {
                        group.style.display = 'none';
                    }
                });
            });
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const searchValue = encodeURIComponent(this.value);
                    const currentUrl = new URL(window.location.href);
                    if (searchValue) {
                        currentUrl.searchParams.set('search', searchValue);
                    } else {
                        currentUrl.searchParams.delete('search');
                    }
                    window.location.href = currentUrl.toString();
                }
            });
        }
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
        document.querySelectorAll('.region-header').forEach(header => {
            const agentList = header.nextElementSibling;
            if (agentList && !agentList.classList.contains('collapsed')) {
                agentList.classList.remove('collapsed');
            }
        });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>