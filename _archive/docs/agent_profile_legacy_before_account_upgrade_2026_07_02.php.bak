<?php
require_once '../config.php';
require_once '../include/reporting_helpers.php';

requireRole('agent');

$agent_id = $_SESSION['user_id'];
$outlet_id = $_SESSION['outlet_id'];

// Date filter and schema-safe performance queries
$period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$selected_date = sisReportDate($_GET['date'] ?? null);

switch ($period) {
    case 'daily':
        $date_from = $selected_date;
        $date_to = $selected_date;
        $chart_label = 'Daily Performance';
        break;
    case 'weekly':
        $date_from = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
        $date_to = date('Y-m-d', strtotime('sunday this week', strtotime($selected_date)));
        $chart_label = 'Weekly Performance';
        break;
    case 'monthly':
    default:
        $date_from = date('Y-m-01', strtotime($selected_date));
        $date_to = date('Y-m-t', strtotime($selected_date));
        $chart_label = 'Monthly Performance';
        break;
}

$salesDateColumn = tableColumnExists($conn, 'sales', 'payment_date') ? 'payment_date' : (tableColumnExists($conn, 'sales', 'sale_date') ? 'sale_date' : 'created_at');
$visitorDateColumn = tableColumnExists($conn, 'visitors', 'visit_time') ? 'visit_time' : (tableColumnExists($conn, 'visitors', 'visited_at') ? 'visited_at' : 'created_at');
$visitorAgentColumn = tableColumnExists($conn, 'visitors', 'agent_id') ? 'agent_id' : (tableColumnExists($conn, 'visitors', 'added_by') ? 'added_by' : 'agent_id');

$sales_summary = [
    'total_transactions' => 0,
    'total_sales' => 0,
    'avg_sale' => 0,
    'active_days' => 0,
];
$stmt = mysqli_prepare($conn, "SELECT COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) AS total_transactions, COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) AS total_sales, COALESCE(AVG(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount END), 0) AS avg_sale, COUNT(DISTINCT CASE WHEN payment_status = 'Paid' AND amount > 0 THEN DATE($salesDateColumn) END) AS active_days FROM sales WHERE agent_id = ? AND DATE($salesDateColumn) BETWEEN ? AND ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iss', $agent_id, $date_from, $date_to);
    mysqli_stmt_execute($stmt);
    $tmp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($tmp) $sales_summary = array_merge($sales_summary, $tmp);
    mysqli_stmt_close($stmt);
}

$visitors_stats = [
    'total_visitors' => 0,
    'converted' => 0,
    'pending' => 0,
    'completed' => 0,
];
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total_visitors, SUM(CASE WHEN status IN ('Completed','converted','Converted') THEN 1 ELSE 0 END) AS converted, SUM(CASE WHEN status IN ('Waiting','Pending','pending') THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status IN ('Completed','completed') THEN 1 ELSE 0 END) AS completed FROM visitors WHERE $visitorAgentColumn = ? AND DATE($visitorDateColumn) BETWEEN ? AND ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iss', $agent_id, $date_from, $date_to);
    mysqli_stmt_execute($stmt);
    $tmp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($tmp) $visitors_stats = array_merge($visitors_stats, $tmp);
    mysqli_stmt_close($stmt);
}
$conversion_rate = ((int)$visitors_stats['total_visitors'] > 0) ? round(((int)$visitors_stats['converted'] / (int)$visitors_stats['total_visitors']) * 100, 1) : 0;

$sales_by_service = mysqli_query($conn, "SELECT COALESCE(s.name, 'Unknown Service') AS service_name, COUNT(CASE WHEN sa.payment_status = 'Paid' AND sa.amount > 0 THEN 1 END) AS transaction_count, COALESCE(SUM(CASE WHEN sa.payment_status = 'Paid' AND sa.amount > 0 THEN sa.amount ELSE 0 END), 0) AS total_amount FROM sales sa LEFT JOIN visitors v ON sa.visitor_id = v.id LEFT JOIN services s ON v.service_id = s.id WHERE sa.agent_id = " . (int)$agent_id . " AND DATE(sa.$salesDateColumn) BETWEEN '" . mysqli_real_escape_string($conn, $date_from) . "' AND '" . mysqli_real_escape_string($conn, $date_to) . "' GROUP BY COALESCE(s.name, 'Unknown Service') ORDER BY total_amount DESC");
if (!$sales_by_service) $sales_by_service = mysqli_query($conn, "SELECT 'No Sales Yet' AS service_name, 0 AS transaction_count, 0 AS total_amount WHERE 1=0");

$trend_data = mysqli_query($conn, "SELECT DATE($salesDateColumn) AS sale_day, COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) AS transactions, COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) AS daily_total FROM sales WHERE agent_id = " . (int)$agent_id . " AND DATE($salesDateColumn) BETWEEN '" . mysqli_real_escape_string($conn, $date_from) . "' AND '" . mysqli_real_escape_string($conn, $date_to) . "' GROUP BY DATE($salesDateColumn) ORDER BY DATE($salesDateColumn) ASC");
if (!$trend_data) $trend_data = mysqli_query($conn, "SELECT CURDATE() AS sale_day, 0 AS transactions, 0 AS daily_total WHERE 1=0");

if (tableColumnExists($conn, 'targets', 'assigned_to_id')) {
    $targetStart = date('Y-m-01', strtotime($selected_date));
    $targetEnd = date('Y-m-t', strtotime($selected_date));
    $targets = mysqli_query($conn, "SELECT 'Overall Sales Target' AS service_name, target_value AS target_amount, COALESCE((SELECT SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END) FROM sales WHERE agent_id = " . (int)$agent_id . " AND DATE($salesDateColumn) BETWEEN '" . mysqli_real_escape_string($conn, $targetStart) . "' AND '" . mysqli_real_escape_string($conn, $targetEnd) . "'), 0) AS actual_sales FROM targets WHERE target_type = 'agent' AND assigned_to_id = " . (int)$agent_id . " AND start_date <= '" . mysqli_real_escape_string($conn, $targetEnd) . "' AND end_date >= '" . mysqli_real_escape_string($conn, $targetStart) . "' ORDER BY target_value DESC");
} else {
    $targets = mysqli_query($conn, "SELECT 'Overall Sales Target' AS service_name, 0 AS target_amount, 0 AS actual_sales WHERE 1=0");
}
if (!$targets) $targets = mysqli_query($conn, "SELECT 'Overall Sales Target' AS service_name, 0 AS target_amount, 0 AS actual_sales WHERE 1=0");

if (tableExists($conn, 'agent_shifts')) {
    $shifts = mysqli_query($conn, "SELECT ash.*, DATE_FORMAT(ash.login_time, '%Y-%m-%d %H:%i') AS login_formatted, DATE_FORMAT(ash.logout_time, '%Y-%m-%d %H:%i') AS logout_formatted, CASE WHEN ash.logout_time IS NULL THEN 'Active' ELSE 'Completed' END AS shift_status FROM agent_shifts ash WHERE ash.agent_id = " . (int)$agent_id . " AND DATE(ash.login_time) BETWEEN '" . mysqli_real_escape_string($conn, $date_from) . "' AND '" . mysqli_real_escape_string($conn, $date_to) . "' ORDER BY ash.login_time DESC LIMIT 10");
} elseif (tableExists($conn, 'session_logs')) {
    $shifts = mysqli_query($conn, "SELECT sl.*, DATE_FORMAT(sl.login_time, '%Y-%m-%d %H:%i') AS login_formatted, DATE_FORMAT(sl.logout_time, '%Y-%m-%d %H:%i') AS logout_formatted, CASE WHEN sl.logout_time IS NULL THEN 'Active' ELSE 'Completed' END AS shift_status FROM session_logs sl WHERE sl.user_id = " . (int)$agent_id . " AND DATE(sl.login_time) BETWEEN '" . mysqli_real_escape_string($conn, $date_from) . "' AND '" . mysqli_real_escape_string($conn, $date_to) . "' ORDER BY sl.login_time DESC LIMIT 10");
} else {
    $shifts = mysqli_query($conn, "SELECT NULL AS login_time, NULL AS logout_time, NULL AS login_formatted, NULL AS logout_formatted, 'Completed' AS shift_status WHERE 1=0");
}
if (!$shifts) $shifts = mysqli_query($conn, "SELECT NULL AS login_time, NULL AS logout_time, NULL AS login_formatted, NULL AS logout_formatted, 'Completed' AS shift_status WHERE 1=0");

$agent_info = ['fullname' => 'Agent', 'full_name' => 'Agent', 'username' => '', 'outlet_name' => 'N/A', 'created_at' => date('Y-m-d H:i:s')];
$stmt = mysqli_prepare($conn, "SELECT u.*, u.full_name AS fullname, o.name AS outlet_name FROM users u LEFT JOIN outlets o ON u.outlet_id = o.id WHERE u.id = ? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $agent_id);
    mysqli_stmt_execute($stmt);
    $tmp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($tmp) $agent_info = array_merge($agent_info, $tmp);
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('agent'); ?>

    <div class="container-fluid mt-4">
        
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="me-4">
                            <i class="bi bi-person-circle sis-u-b0bccfc5"></i>
                        </div>
                        <div>
                            <h2 class="mb-2"><?php echo htmlspecialchars($agent_info['fullname']); ?></h2>
                            <p class="greeting mb-2">
                                <span>👋 Welcome back!</span> Here's your performance overview
                            </p>
                            <div class="quick-stats">
                                <div class="quick-stat-item">
                                    <small class="text-muted">Outlet</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($agent_info['outlet_name']); ?></div>
                                </div>
                                <div class="quick-stat-item">
                                    <small class="text-muted">Employee ID</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($agent_info['username']); ?></div>
                                </div>
                                <div class="quick-stat-item">
                                    <small class="text-muted">Member Since</small>
                                    <div class="fw-bold"><?php echo date('M Y', strtotime($agent_info['created_at'] ?? 'now')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    
                    <div class="btn-group float-end" role="group">
                        <a href="?period=daily&date=<?php echo date('Y-m-d'); ?>" 
                           class="btn btn-period <?php echo $period == 'daily' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-day"></i> Daily
                        </a>
                        <a href="?period=weekly&date=<?php echo date('Y-m-d'); ?>" 
                           class="btn btn-period <?php echo $period == 'weekly' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-week"></i> Weekly
                        </a>
                        <a href="?period=monthly&date=<?php echo date('Y-m-d'); ?>" 
                           class="btn btn-period <?php echo $period == 'monthly' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-month"></i> Monthly
                        </a>
                    </div>
                    <div class="mt-3 text-end">
                        <span class="badge bg-light text-dark p-3">
                            <i class="bi bi-calendar-range"></i> 
                            <?php echo date('M d, Y', strtotime($date_from)); ?> - 
                            <?php echo date('M d, Y', strtotime($date_to)); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Total Sales</div>
                            <div class="stat-value">D<?php echo number_format($sales_summary['total_sales'], 2); ?></div>
                            <small class="text-success">
                                <i class="bi bi-arrow-up"></i> 
                                <?php echo $sales_summary['total_transactions']; ?> transactions
                            </small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Average Sale</div>
                            <div class="stat-value">D<?php echo number_format($sales_summary['avg_sale'], 2); ?></div>
                            <small class="text-info">
                                <i class="bi bi-graph-up"></i> Per transaction
                            </small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Visitors</div>
                            <div class="stat-value"><?php echo number_format($visitors_stats['total_visitors']); ?></div>
                            <small class="text-warning">
                                <i class="bi bi-people"></i> 
                                <?php echo $visitors_stats['converted']; ?> converted
                            </small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-person-badge"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Conversion Rate</div>
                            <div class="stat-value"><?php echo $conversion_rate; ?>%</div>
                            <small class="text-primary">
                                <i class="bi bi-check-circle"></i> 
                                <?php echo $visitors_stats['completed']; ?> completed
                            </small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-pie-chart"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            
            <div class="col-lg-8">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="bi bi-graph-up sis-u-3696a3ec"></i> 
                        <?php echo $chart_label; ?> Trend
                    </h5>
                    <div class="chart-container">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>

            
            <div class="col-lg-4">
                <div class="stat-card text-center">
                    <h5 class="mb-3">
                        <i class="bi bi-pie-chart sis-u-3696a3ec"></i> 
                        Visitor Conversion
                    </h5>
                    <div class="conversion-circle" data-rate="<?php echo e($conversion_rate); ?>" data-progress="<?php echo e($conversion_rate); ?>"></div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Visitors:</span>
                            <span class="fw-bold"><?php echo $visitors_stats['total_visitors']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Converted:</span>
                            <span class="fw-bold text-success"><?php echo $visitors_stats['converted']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Pending:</span>
                            <span class="fw-bold text-warning"><?php echo $visitors_stats['pending']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            
            <div class="col-lg-6">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="bi bi-grid-3x3 sis-u-3696a3ec"></i> 
                        Sales by Service
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Transactions</th>
                                    <th>Total Amount</th>
                                    <th>% Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_all_sales = $sales_summary['total_sales'];
                                while ($service = mysqli_fetch_assoc($sales_by_service)): 
                                    $percentage = $total_all_sales > 0 ? round(($service['total_amount'] / $total_all_sales) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                    </td>
                                    <td><?php echo $service['transaction_count']; ?></td>
                                    <td>D<?php echo number_format($service['total_amount'], 2); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2"><?php echo $percentage; ?>%</span>
                                            <div class="progress sis-u-158a3915">
                                                <div class="progress-bar sis-progress" data-progress="<?php echo e($percentage); ?>"></div>
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

            
            <div class="col-lg-6">
                <div class="target-progress">
                    <h5 class="mb-3">
                        <i class="bi bi-bullseye sis-u-3696a3ec"></i> 
                        Monthly Targets Progress
                    </h5>
                    <?php if (mysqli_num_rows($targets) > 0): ?>
                        <?php while ($target = mysqli_fetch_assoc($targets)): 
                            $progress = $target['target_amount'] > 0 
                                ? min(round(($target['actual_sales'] / $target['target_amount']) * 100), 100) 
                                : 0;
                            $remaining = $target['target_amount'] - $target['actual_sales'];
                            $status_class = $progress >= 100 ? 'success' : ($progress >= 70 ? 'info' : ($progress >= 40 ? 'warning' : 'danger'));
                        ?>
                        <div class="target-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="fw-bold"><?php echo htmlspecialchars($target['service_name']); ?></span>
                                    <?php if ($progress >= 100): ?>
                                        <span class="achievement-badge ms-2">
                                            <i class="bi bi-trophy-fill"></i> Achieved!
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">Target:</small>
                                    <span class="fw-bold">D<?php echo number_format($target['target_amount'], 2); ?></span>
                                </div>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $status_class; ?> sis-progress" role="progressbar" 
                                     data-progress="<?php echo e($progress); ?>"
                                     aria-valuenow="<?php echo $progress; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo $progress; ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-success">
                                    <i class="bi bi-check-circle"></i> 
                                    D<?php echo number_format($target['actual_sales'], 2); ?> achieved
                                </small>
                                <?php if ($remaining > 0): ?>
                                    <small class="text-danger">
                                        <i class="bi bi-hourglass"></i> 
                                        D<?php echo number_format($remaining, 2); ?> remaining
                                    </small>
                                <?php else: ?>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle-fill"></i> 
                                        Target completed!
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No targets set for this month.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            
            <div class="col-lg-6">
                <div class="shift-timeline">
                    <h5 class="mb-3">
                        <i class="bi bi-clock-history sis-u-3696a3ec"></i> 
                        Recent Shift History
                    </h5>
                    <?php if (mysqli_num_rows($shifts) > 0): ?>
                        <?php while ($shift = mysqli_fetch_assoc($shifts)): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="fw-bold">
                                            <?php echo $shift['login_formatted']; ?>
                                        </span>
                                        <?php if ($shift['logout_time']): ?>
                                            <span class="text-muted"> → </span>
                                            <span class="fw-bold">
                                                <?php echo $shift['logout_formatted']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-<?php echo $shift['shift_status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                        <?php echo $shift['shift_status']; ?>
                                    </span>
                                </div>
                                <?php if ($shift['logout_time']): ?>
                                    <?php 
                                    $login = strtotime($shift['login_time']);
                                    $logout = strtotime($shift['logout_time']);
                                    $duration = $logout - $login;
                                    $hours = floor($duration / 3600);
                                    $minutes = floor(($duration % 3600) / 60);
                                    ?>
                                    <small class="text-muted">
                                        <i class="bi bi-hourglass-split"></i> 
                                        Duration: <?php echo $hours; ?>h <?php echo $minutes; ?>m
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No shift history available.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="col-lg-6">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="bi bi-lightbulb sis-u-3696a3ec"></i> 
                        Performance Insights
                    </h5>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Active Days</small>
                                <span class="fw-bold sis-u-40b72cba"><?php echo $sales_summary['active_days']; ?></span>
                                <small> / <?php echo $period == 'daily' ? '1' : ($period == 'weekly' ? '7' : date('t', strtotime($selected_date))); ?></small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Avg Daily Sales</small>
                                <span class="fw-bold sis-u-fa415b95">
                                    D<?php 
                                    $avg_daily = $sales_summary['active_days'] > 0 
                                        ? $sales_summary['total_sales'] / $sales_summary['active_days'] 
                                        : 0;
                                    echo number_format($avg_daily, 2); 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mt-3 mb-2">Tips to Improve:</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success"></i> 
                            <?php if ($conversion_rate < 30): ?>
                                Focus on converting pending visitors - your conversion rate is below target.
                            <?php elseif ($conversion_rate < 60): ?>
                                Good conversion rate! Try upselling additional services to increase value.
                            <?php else: ?>
                                Excellent conversion! Maintain this performance and help train new agents.
                            <?php endif; ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success"></i> 
                            <?php 
                            $top_service = mysqli_fetch_assoc($sales_by_service);
                            mysqli_data_seek($sales_by_service, 0);
                            if ($top_service): 
                            ?>
                                Your top service is <?php echo $top_service['service_name']; ?> - keep promoting it!
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script>    document.addEventListener('DOMContentLoaded', function() {
        <?php
        $trend_labels = [];
        $trend_values = [];
        $trend_transactions = [];
        
        mysqli_data_seek($trend_data, 0);
        while ($trend = mysqli_fetch_assoc($trend_data)) {
            $trend_labels[] = date('M d', strtotime($trend['sale_day']));
            $trend_values[] = $trend['daily_total'];
            $trend_transactions[] = $trend['transactions'];
        }
        ?>
        
        const ctx = document.getElementById('salesTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_labels); ?>,
                datasets: [
                    {
                        label: 'Daily Sales (D)',
                        data: <?php echo json_encode($trend_values); ?>,
                        borderColor: '#E30613',
                        backgroundColor: 'rgba(227, 6, 19, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#E30613',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Transactions',
                        data: <?php echo json_encode($trend_transactions); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        tension: 0.3,
                        fill: false,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'D' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.raw || 0;
                                if (context.dataset.label.includes('Sales')) {
                                    return label + ': D' + value.toLocaleString();
                                }
                                return label + ': ' + value;
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>