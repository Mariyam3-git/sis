<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('supervisor');

$outlet_id = $_SESSION['outlet_id'];
[$date_from, $date_to] = sisDateRange($_GET['date_from'] ?? null, $_GET['date_to'] ?? null, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));

// Get outlet details
$outlet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM outlets WHERE id = $outlet_id"));

// Get detailed agent AND supervisor performance without multiplying rows.
// Visitor and sales aggregates are calculated separately, then joined by user.
$agent_query = "
    SELECT u.id, u.full_name, u.role, u.shift,
           COALESCE(vs.visitor_count, 0) as visitor_count,
           COALESCE(ss.sales_count, 0) as sales_count,
           COALESCE(ss.collected, 0) as collected,
           COALESCE(ss.withdrawals, 0) as withdrawals,
           COALESCE(ss.pending, 0) as pending,
           COALESCE(ss.unpaid, 0) as unpaid,
           COALESCE(ss.avg_ticket_size, 0) as avg_ticket_size,
           COALESCE(vs.active_days, 0) as active_days,
           COALESCE(vs.completed_visits, 0) as completed_visits
    FROM users u
    LEFT JOIN (
        SELECT agent_id,
               COUNT(DISTINCT id) as visitor_count,
               COUNT(DISTINCT DATE(visit_time)) as active_days,
               SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_visits
        FROM visitors
        WHERE outlet_id = $outlet_id AND DATE(visit_time) BETWEEN '$date_from' AND '$date_to'
        GROUP BY agent_id
    ) vs ON u.id = vs.agent_id
    LEFT JOIN (
        SELECT agent_id,
               COUNT(DISTINCT id) as sales_count,
               COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as collected,
               COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as withdrawals,
               COALESCE(SUM(CASE WHEN payment_status = 'Pending' AND amount > 0 THEN amount ELSE 0 END), 0) as pending,
               COALESCE(SUM(CASE WHEN payment_status = 'Unpaid' AND amount > 0 THEN amount ELSE 0 END), 0) as unpaid,
               COALESCE(AVG(CASE WHEN amount > 0 THEN amount END), 0) as avg_ticket_size
        FROM sales
        WHERE outlet_id = $outlet_id AND DATE(payment_date) BETWEEN '$date_from' AND '$date_to'
        GROUP BY agent_id
    ) ss ON u.id = ss.agent_id
    WHERE u.role IN ('agent', 'supervisor') AND u.outlet_id = $outlet_id AND u.is_active = 1
    ORDER BY collected DESC";

$agents_result = mysqli_query($conn, $agent_query);

// Handle CSV export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="agent_performance_' . $date_from . '_to_' . $date_to . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Staff Performance Report - ' . $outlet['name']]);
    fputcsv($output, ['Period:', $date_from . ' to ' . $date_to]);
    fputcsv($output, []);
    fputcsv($output, ['Staff', 'Role', 'Shift', 'Visitors', 'Sales', 'Collected (D)', 'Pending (D)', 'Unpaid (D)', 'Avg Ticket', 'Active Days', 'Completion Rate']);
    
    while($agent = mysqli_fetch_assoc($agents_result)) {
        $completion_rate = $agent['visitor_count'] > 0 ? round(($agent['completed_visits'] / $agent['visitor_count']) * 100, 1) : 0;
        $shift_display = $agent['role'] == 'supervisor' ? 'N/A' : ($agent['shift'] ?? 'N/A');
        fputcsv($output, [
            $agent['full_name'],
            ucfirst($agent['role']),
            $shift_display,
            $agent['visitor_count'],
            $agent['sales_count'],
            number_format($agent['collected'] ?? 0, 2),
            number_format($agent['pending'] ?? 0, 2),
            number_format($agent['unpaid'] ?? 0, 2),
            number_format($agent['avg_ticket_size'] ?? 0, 2),
            $agent['active_days'],
            $completion_rate . '%'
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
    <title>Staff Performance - <?php echo e($outlet['name']); ?></title>
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
            <h4><?php echo e($outlet['name']); ?> - Staff Performance (Agents & Supervisors)</h4>
            <small><?php echo e($date_from); ?> to <?php echo e($date_to); ?></small>
        </div>
    </div>

    <div class="container">
        
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label>From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
                    </div>
                    <div class="col-md-4">
                        <label>To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
                    </div>
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Apply Filter</button>
                            <a href="?export=1&date_from=<?php echo e($date_from); ?>&date_to=<?php echo e($date_to); ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> Export
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">Staff Performance Details (Agents & Supervisors)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Staff</th>
                                <th>Role</th>
                                <th>Shift</th>
                                <th>Visitors</th>
                                <th>Sales</th>
                                <th>Collected</th>
                                <th>Pending</th>
                                <th>Unpaid</th>
                                <th>Avg Ticket</th>
                                <th>Active Days</th>
                                <th>Completion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($agent = mysqli_fetch_assoc($agents_result)): 
                                $completion_rate = $agent['visitor_count'] > 0 ? round(($agent['completed_visits'] / $agent['visitor_count']) * 100, 1) : 0;
                                $shift_display = $agent['role'] == 'supervisor' ? 'N/A' : ($agent['shift'] ?? 'N/A');
                                $role_badge = $agent['role'] == 'agent' ? 'badge-agent' : 'badge-supervisor';
                            ?>
                            <tr>
                                <td><strong><?php echo e($agent['full_name']); ?></strong></td>
                                <td><span class="badge <?php echo $role_badge; ?>"><?php echo ucfirst($agent['role']); ?></span></td>
                                <td><span class="badge bg-info"><?php echo $shift_display; ?></span></td>
                                <td><?php echo e($agent['visitor_count']); ?></td>
                                <td><?php echo e($agent['sales_count']); ?></td>
                                <td class="text-success">D <?php echo number_format($agent['collected'] ?? 0, 2); ?></td>
                                <td class="text-warning">D <?php echo number_format($agent['pending'] ?? 0, 2); ?></td>
                                <td class="text-danger">D <?php echo number_format($agent['unpaid'] ?? 0, 2); ?></td>
                                <td>D <?php echo number_format($agent['avg_ticket_size'] ?? 0, 2); ?></td>
                                <td><?php echo e($agent['active_days']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="me-2"><?php echo $completion_rate; ?>%</span>
                                        <div class="progress flex-grow-1 sis-u-9d49ecb1">
                                            <div class="progress-bar bg-success sis-progress" data-progress="<?php echo e($completion_rate); ?>"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
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