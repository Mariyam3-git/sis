<?php
require_once '../config.php';
requireRole('supervisor');

$outlet_id = $_SESSION['outlet_id'];
$supervisor_name = $_SESSION['full_name'];

// Get outlet details
$outlet_query = "SELECT * FROM outlets WHERE id = $outlet_id";
$outlet_result = mysqli_query($conn, $outlet_query);
$outlet = mysqli_fetch_assoc($outlet_result);

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$agent_filter = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, (string)($_GET['status'] ?? '')) : '';

// Get all staff (agents AND supervisors) for filter
$staff = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role IN ('agent', 'supervisor') AND outlet_id = $outlet_id AND is_active = 1 ORDER BY full_name");

// Build WHERE clause
$where = ["v.outlet_id = $outlet_id"];
if ($date_from && $date_to) {
    $where[] = "DATE(v.visit_time) BETWEEN '$date_from' AND '$date_to'";
}
if ($agent_filter > 0) {
    $where[] = "v.agent_id = $agent_filter";
}
if (!empty($status_filter)) {
    $where[] = "v.status = '$status_filter'";
}
$where_clause = implode(" AND ", $where);

// Get visitors with aggregated sales
$visitors_query = "
    SELECT v.*, 
           u.full_name as agent_name,
           s.name as service_name,
           p.name as purpose_name,
           COALESCE(SUM(sl.amount), 0) as total_amount,
           COUNT(sl.id) as sales_count,
           GROUP_CONCAT(DISTINCT sl.payment_status) as payment_statuses
    FROM visitors v
    LEFT JOIN users u ON v.agent_id = u.id
    LEFT JOIN services s ON v.service_id = s.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN sales sl ON v.id = sl.visitor_id
    WHERE $where_clause
    GROUP BY v.id
    ORDER BY v.visit_time DESC
";

$visitors = mysqli_query($conn, $visitors_query);

// Get summary stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Waiting' THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM visitors
    WHERE outlet_id = $outlet_id
    AND DATE(visit_time) BETWEEN '$date_from' AND '$date_to'
"));

// Handle CSV Export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="visitors_' . $outlet['name'] . '_' . $date_from . '_to_' . $date_to . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Date', 'Time', 'Visitor', 'Contact', 'Purpose', 'Service', 'Logged By', 'Status', 'Sales Count', 'Total Amount (D)']);
    
    mysqli_data_seek($visitors, 0);
    while($row = mysqli_fetch_assoc($visitors)) {
        fputcsv($output, [
            date('Y-m-d', strtotime($row['visit_time'])),
            date('H:i', strtotime($row['visit_time'])),
            $row['name'],
            $row['contact'],
            $row['purpose_name'],
            $row['service_name'],
            $row['agent_name'],
            $row['status'],
            $row['sales_count'],
            number_format($row['total_amount'], 2)
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
    <title>Visitor Log - <?php echo $outlet['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

    
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-0"><?php echo $outlet['name']; ?> - Visitor Log</h4>
                    <p class="text-muted mb-0">Track all customer visits (including sales made by agents & supervisors)</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="?export=1&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&agent_id=<?php echo $agent_filter; ?>&status=<?php echo $status_filter; ?>" class="btn-export">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-4">
        
        <div class="row stats-row g-3">
            <div class="col-sm-6 col-md-2">
                <div class="stat-card total">
                    <small>Total</small>
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                </div>
            </div>
            <div class="col-sm-6 col-md-2">
                <div class="stat-card completed">
                    <small>Completed</small>
                    <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                </div>
            </div>
            <div class="col-sm-6 col-md-2">
                <div class="stat-card waiting">
                    <small>Waiting</small>
                    <h3><?php echo $stats['waiting'] ?? 0; ?></h3>
                </div>
            </div>
            <div class="col-sm-6 col-md-2">
                <div class="stat-card progress">
                    <small>In Progress</small>
                    <h3><?php echo $stats['in_progress'] ?? 0; ?></h3>
                </div>
            </div>
            <div class="col-sm-6 col-md-2">
                <div class="stat-card cancelled">
                    <small>Cancelled</small>
                    <h3><?php echo $stats['cancelled'] ?? 0; ?></h3>
                </div>
            </div>
        </div>

        
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Logged By</label>
                    <select class="form-select" name="agent_id">
                        <option value="0">All Staff</option>
                        <?php while($s = mysqli_fetch_assoc($staff)): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $agent_filter == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo $s['full_name']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <option value="Waiting" <?php echo $status_filter == 'Waiting' ? 'selected' : ''; ?>>Waiting</option>
                        <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply</button>
                    <a href="visitors.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-people"></i> Visitor Records</span>
                <span class="badge bg-secondary"><?php echo mysqli_num_rows($visitors); ?> entries</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Visitor</th>
                                <th>Contact</th>
                                <th>Purpose</th>
                                <th>Service</th>
                                <th>Logged By</th>
                                <th>Status</th>
                                <th>Sales Count</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($visitors) > 0): ?>
                                <?php while($v = mysqli_fetch_assoc($visitors)): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($v['visit_time'])); ?>
                                        <br><small><?php echo date('H:i', strtotime($v['visit_time'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($v['name']); ?></strong>
                                    </td>
                                    <td><?php echo $v['contact'] ?: '-'; ?></td>
                                    <td><?php echo $v['purpose_name'] ?: '-'; ?></td>
                                    <td><?php echo $v['service_name'] ?: '-'; ?></td>
                                    <td><?php echo $v['agent_name'] ?: '-'; ?></td>
                                    <td>
                                        <?php 
                                        $status_class = strtolower(str_replace(' ', '', $v['status']));
                                        if ($status_class == 'inprogress') $status_class = 'inprogress';
                                        ?>
                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                            <?php echo $v['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $v['sales_count']; ?></td>
                                    <td class="fw-bold">
                                        <?php if($v['total_amount'] != 0): ?>
                                            D <?php echo number_format($v['total_amount'], 2); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="bi bi-inbox sis-u-4b77038a"></i>
                                        <p class="mt-3 text-muted">No visitors found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>