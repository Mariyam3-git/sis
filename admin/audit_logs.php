<?php
// admin/audit_logs.php - ENHANCED WITH SEARCH & FILTERS

require_once '../config.php';
requireSuperAdmin();

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$action_filter = isset($_GET['action_type']) ? mysqli_real_escape_string($conn, (string)($_GET['action_type'] ?? '')) : '';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = [];
if ($search) {
    $where_conditions[] = "(a.action_type LIKE '%$search%' OR a.record_id LIKE '%$search%' OR a.ip_address LIKE '%$search%' OR u.full_name LIKE '%$search%' OR u.username LIKE '%$search%')";
}
if ($action_filter) {
    $where_conditions[] = "a.action_type = '$action_filter'";
}
if ($user_filter) {
    $where_conditions[] = "a.user_id = $user_filter";
}
if ($date_from) {
    $where_conditions[] = "DATE(a.timestamp) >= '$date_from'";
}
if ($date_to) {
    $where_conditions[] = "DATE(a.timestamp) <= '$date_to'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get total records with filters
$count_query = "SELECT COUNT(*) as total FROM audit_log a LEFT JOIN users u ON a.user_id = u.id $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Get audit logs with filters
$logs_query = "SELECT a.*, u.username, u.full_name, u.role 
               FROM audit_log a 
               LEFT JOIN users u ON a.user_id = u.id 
               $where_clause
               ORDER BY a.timestamp DESC 
               LIMIT $offset, $limit";
$logs_result = mysqli_query($conn, $logs_query);

// Get unique action types for filter dropdown
$actions_query = "SELECT DISTINCT action_type FROM audit_log ORDER BY action_type";
$actions_result = mysqli_query($conn, $actions_query);

// Get users for filter dropdown
$users_query = "SELECT id, full_name, username, role FROM users WHERE role IN ('agent', 'supervisor', 'admin') ORDER BY full_name";
$users_result = mysqli_query($conn, $users_query);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT DATE(timestamp)) as days_covered,
                    COUNT(DISTINCT user_id) as unique_users
                FROM audit_log a LEFT JOIN users u ON a.user_id = u.id $where_clause";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get action breakdown for chart
$breakdown_query = "SELECT action_type, COUNT(*) as count 
                    FROM audit_log a LEFT JOIN users u ON a.user_id = u.id
                    $where_clause
                    GROUP BY action_type 
                    ORDER BY count DESC 
                    LIMIT 8";
$breakdown_result = mysqli_query($conn, $breakdown_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>


    
    <div class="main-content">
        <div class="page-title">
            <h3><i class="bi bi-journal-text"></i> Audit Logs</h3>
            <div>
                <a href="../export_report.php?type=logs<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $action_filter ? '&action_type=' . urlencode($action_filter) : ''; ?><?php echo $user_filter ? '&user_id=' . $user_filter : ''; ?><?php echo $date_from ? '&date_from=' . $date_from : ''; ?><?php echo $date_to ? '&date_to=' . $date_to : ''; ?>" class="btn-export">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6><i class="bi bi-journal-text"></i> Total Logs</h6>
                <h3><?php echo number_format($stats['total_logs'] ?? 0); ?></h3>
            </div>
            <div class="stat-card">
                <h6><i class="bi bi-calendar"></i> Days Covered</h6>
                <h3><?php echo $stats['days_covered'] ?? 0; ?></h3>
            </div>
            <div class="stat-card">
                <h6><i class="bi bi-people"></i> Active Users</h6>
                <h3><?php echo $stats['unique_users'] ?? 0; ?></h3>
            </div>
            <div class="stat-card">
                <h6><i class="bi bi-funnel"></i> Showing</h6>
                <h3><?php echo number_format($total_records); ?></h3>
            </div>
        </div>

        
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group sis-u-36ae11e9">
                        <label><i class="bi bi-search"></i> Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by user, action, record ID, or IP..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="bi bi-tag"></i> Action Type</label>
                        <select name="action_type" class="form-select">
                            <option value="">All Actions</option>
                            <?php while($action = mysqli_fetch_assoc($actions_result)): ?>
                            <option value="<?php echo $action['action_type']; ?>" <?php echo $action_filter == $action['action_type'] ? 'selected' : ''; ?>>
                                <?php echo $action['action_type']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="bi bi-person"></i> User</label>
                        <select name="user_id" class="form-select">
                            <option value="">All Users</option>
                            <?php 
                            mysqli_data_seek($users_result, 0);
                            while($user = mysqli_fetch_assoc($users_result)): 
                            ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?> (<?php echo $user['role']; ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="bi bi-calendar"></i> From</label>
                        <input type="date" name="date_from" class="form-control sis-w-150" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="bi bi-calendar"></i> To</label>
                        <input type="date" name="date_to" class="form-control sis-w-150" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="filter-group sis-u-9102618d">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Apply
                        </button>
                    </div>
                    <div class="filter-group sis-u-9102618d">
                        <label>&nbsp;</label>
                        <a href="audit_logs.php" class="btn btn-outline-secondary w-100 d-block text-center">
                            <i class="bi bi-arrow-repeat"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        
        <?php if(mysqli_num_rows($breakdown_result) > 0): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pie-chart"></i> Activity Breakdown
                <small class="text-muted">(Current filter results)</small>
            </div>
            <div class="card-body">
                <div class="action-breakdown">
                    <?php while($break = mysqli_fetch_assoc($breakdown_result)): ?>
                    <div class="breakdown-item">
                        <div class="h4 mb-0 text-<?php 
                            echo strpos($break['action_type'], 'DELETE') !== false ? 'danger' : 
                                (strpos($break['action_type'], 'ADD') !== false ? 'success' : 
                                (strpos($break['action_type'], 'UPDATE') !== false ? 'info' : 'secondary')); 
                        ?>">
                            <?php echo $break['count']; ?>
                        </div>
                        <small><?php echo $break['action_type']; ?></small>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list"></i> System Activity Logs
                <span class="badge bg-secondary"><?php echo number_format($total_records); ?> records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Record ID</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($logs_result) > 0): ?>
                                <?php while($log = mysqli_fetch_assoc($logs_result)): 
                                    $badge_class = 'badge-';
                                    if(strpos($log['action_type'], 'DELETE') !== false) {
                                        $badge_class .= 'delete';
                                    } elseif(strpos($log['action_type'], 'ADD') !== false || strpos($log['action_type'], 'START') !== false) {
                                        $badge_class .= 'add';
                                    } elseif(strpos($log['action_type'], 'UPDATE') !== false || strpos($log['action_type'], 'EDIT') !== false) {
                                        $badge_class .= 'update';
                                    } elseif(strpos($log['action_type'], 'LOGIN') !== false) {
                                        $badge_class .= 'login';
                                    } elseif(strpos($log['action_type'], 'LOGOUT') !== false) {
                                        $badge_class .= 'logout';
                                    } elseif(strpos($log['action_type'], 'SHIFT') !== false) {
                                        $badge_class .= 'shift';
                                    } else {
                                        $badge_class .= 'update';
                                    }
                                ?>
                                <tr>
                                    <td class="sis-u-0ca50382">
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?>
                                        <small class="d-block text-muted"><?php echo date('l', strtotime($log['timestamp'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'System'); ?></strong>
                                        <?php if($log['user_id']): ?>
                                        <small class="d-block text-muted">ID: <?php echo $log['user_id']; ?></small>
                                        <?php endif; ?>
                                     </td>
                                    <td>
                                        <?php if($log['role']): ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($log['role']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                     </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($log['action_type']); ?>
                                        </span>
                                     </td>
                                    <td>
                                        <?php if($log['record_id']): ?>
                                        <code><?php echo $log['record_id']; ?></code>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                     </td>
                                    <td>
                                        <code><?php echo $log['ip_address'] ?? '-'; ?></code>
                                     </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="bi bi-journal-text sis-u-e2718763"></i>
                                        <p class="text-muted mt-2">No audit logs found</p>
                                        <small class="text-muted">Try adjusting your search filters</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                
                <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4 mb-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        if($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">1</a>
                        </li>
                        <?php if($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; endif; ?>
                        
                        <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if($end_page < $total_pages): ?>
                        <?php if($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $total_pages; ?></a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo urlencode($action_filter); ?>&user_id=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                
                <div class="p-3 bg-light text-center small text-muted border-top">
                    <i class="bi bi-info-circle"></i> Showing <?php echo min($offset + 1, $total_records); ?> - <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> records
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
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>