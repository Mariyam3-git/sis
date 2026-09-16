<?php
require_once '../config.php';
require_once '../include/reporting_helpers.php';
requireRole('supervisor');

$supervisor_id = (int)($_SESSION['user_id'] ?? 0);
$outlet_id = (int)($_SESSION['outlet_id'] ?? 0);
$period = isset($_GET['period']) ? (string)$_GET['period'] : 'monthly';
$selected_date = sisReportDate($_GET['date'] ?? null);

switch ($period) {
    case 'daily':
        $date_from = $selected_date; $date_to = $selected_date; $chart_label = 'Daily Team Performance'; break;
    case 'weekly':
        $date_from = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
        $date_to = date('Y-m-d', strtotime('sunday this week', strtotime($selected_date)));
        $chart_label = 'Weekly Team Performance'; break;
    case 'monthly':
    default:
        $period = 'monthly';
        $date_from = date('Y-m-01', strtotime($selected_date));
        $date_to = date('Y-m-t', strtotime($selected_date));
        $chart_label = 'Monthly Team Performance'; break;
}

$salesDateColumn = tableColumnExists($conn, 'sales', 'payment_date') ? 'payment_date' : (tableColumnExists($conn, 'sales', 'sale_date') ? 'sale_date' : 'created_at');
$visitorDateColumn = tableColumnExists($conn, 'visitors', 'visit_time') ? 'visit_time' : (tableColumnExists($conn, 'visitors', 'visited_at') ? 'visited_at' : 'created_at');
$visitorAgentColumn = tableColumnExists($conn, 'visitors', 'agent_id') ? 'agent_id' : (tableColumnExists($conn, 'visitors', 'added_by') ? 'added_by' : 'agent_id');

$supervisor_info = ['full_name' => 'Supervisor', 'username' => '', 'outlet_name' => 'N/A', 'created_at' => date('Y-m-d H:i:s')];
$stmt = mysqli_prepare($conn, "SELECT u.*, o.name AS outlet_name FROM users u LEFT JOIN outlets o ON u.outlet_id = o.id WHERE u.id = ? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $supervisor_id);
    mysqli_stmt_execute($stmt);
    $tmp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($tmp) $supervisor_info = array_merge($supervisor_info, $tmp);
    mysqli_stmt_close($stmt);
}

$team_summary = ['agent_count' => 0, 'total_transactions' => 0, 'total_sales' => 0, 'avg_sale' => 0, 'visitors' => 0, 'converted' => 0];
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS agent_count FROM users WHERE role = 'agent' AND is_active = 1 AND outlet_id = ?");
if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $outlet_id); mysqli_stmt_execute($stmt); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); if($row) $team_summary['agent_count']=(int)$row['agent_count']; mysqli_stmt_close($stmt); }

$stmt = mysqli_prepare($conn, "SELECT COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) AS total_transactions, COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END),0) AS total_sales, COALESCE(AVG(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount END),0) AS avg_sale FROM sales WHERE outlet_id = ? AND DATE($salesDateColumn) BETWEEN ? AND ?");
if ($stmt) { mysqli_stmt_bind_param($stmt, 'iss', $outlet_id, $date_from, $date_to); mysqli_stmt_execute($stmt); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); if($row) $team_summary=array_merge($team_summary,$row); mysqli_stmt_close($stmt); }

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS visitors, SUM(CASE WHEN status IN ('Completed','completed','Converted','converted') THEN 1 ELSE 0 END) AS converted FROM visitors WHERE outlet_id = ? AND DATE($visitorDateColumn) BETWEEN ? AND ?");
if ($stmt) { mysqli_stmt_bind_param($stmt, 'iss', $outlet_id, $date_from, $date_to); mysqli_stmt_execute($stmt); $row=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); if($row) $team_summary=array_merge($team_summary,$row); mysqli_stmt_close($stmt); }
$conversion_rate = ((int)$team_summary['visitors'] > 0) ? round(((int)$team_summary['converted'] / (int)$team_summary['visitors']) * 100, 1) : 0;

$agent_rows = mysqli_query($conn, "SELECT u.id, u.full_name, u.username, COUNT(CASE WHEN s.payment_status='Paid' AND s.amount > 0 THEN 1 END) AS transactions, COALESCE(SUM(CASE WHEN s.payment_status='Paid' AND s.amount > 0 THEN s.amount ELSE 0 END),0) AS total_amount FROM users u LEFT JOIN sales s ON s.agent_id = u.id AND DATE(s.$salesDateColumn) BETWEEN '" . mysqli_real_escape_string($conn,$date_from) . "' AND '" . mysqli_real_escape_string($conn,$date_to) . "' WHERE u.role='agent' AND u.outlet_id = " . (int)$outlet_id . " GROUP BY u.id, u.full_name, u.username ORDER BY total_amount DESC, u.full_name ASC");
if (!$agent_rows) $agent_rows = mysqli_query($conn, "SELECT 0 AS id, 'No agents' AS full_name, '' AS username, 0 AS transactions, 0 AS total_amount WHERE 1=0");

$trend_data = mysqli_query($conn, "SELECT DATE($salesDateColumn) AS sale_day, COUNT(CASE WHEN payment_status='Paid' AND amount > 0 THEN 1 END) AS transactions, COALESCE(SUM(CASE WHEN payment_status='Paid' AND amount > 0 THEN amount ELSE 0 END),0) AS daily_total FROM sales WHERE outlet_id = " . (int)$outlet_id . " AND DATE($salesDateColumn) BETWEEN '" . mysqli_real_escape_string($conn,$date_from) . "' AND '" . mysqli_real_escape_string($conn,$date_to) . "' GROUP BY DATE($salesDateColumn) ORDER BY DATE($salesDateColumn) ASC");
if (!$trend_data) $trend_data = mysqli_query($conn, "SELECT CURDATE() AS sale_day, 0 AS transactions, 0 AS daily_total WHERE 1=0");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Supervisor Performance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
<link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor', 'My Performance', 'Supervisor team performance overview'); ?>
<div class="container-fluid py-3">
    <div class="profile-header mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8 d-flex align-items-center gap-3">
                <div class="sis-profile-photo-xl">
                    <?php $supervisorPhoto = function_exists('sisProfilePhotoPath') ? sisProfilePhotoPath($supervisor_info['profile_photo'] ?? '') : ''; ?>
                    <?php if($supervisorPhoto): ?><img src="<?php echo e($supervisorPhoto); ?>" alt="Profile photo"><?php else: ?><span><?php echo e(strtoupper(substr((string)($supervisor_info['full_name'] ?? 'S'),0,1))); ?></span><?php endif; ?>
                </div>
                <div>
                    <h2 class="mb-1"><?php echo e($supervisor_info['full_name'] ?? 'Supervisor'); ?></h2>
                    <p class="mb-2 text-muted">Team performance for <?php echo e($supervisor_info['outlet_name'] ?? 'your outlet'); ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-dark border">@<?php echo e($supervisor_info['username'] ?? ''); ?></span>
                        <span class="badge bg-light text-dark border"><?php echo e(date('M d, Y', strtotime($date_from))); ?> - <?php echo e(date('M d, Y', strtotime($date_to))); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="d-flex justify-content-lg-end gap-2 flex-wrap">
                <a href="<?php echo appPath('profile.php'); ?>" class="btn btn-outline-primary"><i class="bi bi-person-circle"></i> My Account</a>
                <div class="btn-group" role="group">
                    <a href="?period=daily&date=<?php echo date('Y-m-d'); ?>" class="btn btn-period <?php echo $period==='daily'?'active':''; ?>">Daily</a>
                    <a href="?period=weekly&date=<?php echo date('Y-m-d'); ?>" class="btn btn-period <?php echo $period==='weekly'?'active':''; ?>">Weekly</a>
                    <a href="?period=monthly&date=<?php echo date('Y-m-d'); ?>" class="btn btn-period <?php echo $period==='monthly'?'active':''; ?>">Monthly</a>
                </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="stat-card"><div class="stat-label">Team Sales</div><div class="stat-value">D<?php echo number_format((float)$team_summary['total_sales'],2); ?></div><small class="text-muted"><?php echo (int)$team_summary['total_transactions']; ?> transactions</small></div></div>
        <div class="col-xl-3 col-md-6"><div class="stat-card"><div class="stat-label">Active Agents</div><div class="stat-value"><?php echo number_format((int)$team_summary['agent_count']); ?></div><small class="text-muted">Assigned to your outlet</small></div></div>
        <div class="col-xl-3 col-md-6"><div class="stat-card"><div class="stat-label">Visitors</div><div class="stat-value"><?php echo number_format((int)$team_summary['visitors']); ?></div><small class="text-muted"><?php echo (int)$team_summary['converted']; ?> converted</small></div></div>
        <div class="col-xl-3 col-md-6"><div class="stat-card"><div class="stat-label">Conversion</div><div class="stat-value"><?php echo e($conversion_rate); ?>%</div><small class="text-muted">Team conversion rate</small></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7"><div class="stat-card"><h5 class="mb-3"><i class="bi bi-graph-up"></i> <?php echo e($chart_label); ?></h5><div class="chart-container"><canvas id="teamTrendChart"></canvas></div></div></div>
        <div class="col-lg-5"><div class="stat-card"><h5 class="mb-3"><i class="bi bi-people"></i> Agent Ranking</h5><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Agent</th><th>Transactions</th><th class="text-end">Sales</th></tr></thead><tbody><?php if($agent_rows && mysqli_num_rows($agent_rows)>0): while($a=mysqli_fetch_assoc($agent_rows)): ?><tr><td><strong><?php echo e($a['full_name']); ?></strong><small class="d-block text-muted">@<?php echo e($a['username']); ?></small></td><td><?php echo (int)$a['transactions']; ?></td><td class="text-end">D<?php echo number_format((float)$a['total_amount'],2); ?></td></tr><?php endwhile; else: ?><tr><td colspan="3" class="text-center text-muted py-4">No agent activity found.</td></tr><?php endif; ?></tbody></table></div></div></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
<?php $labels=[]; $values=[]; $tx=[]; if($trend_data && mysqli_num_rows($trend_data)>0){ mysqli_data_seek($trend_data,0); while($t=mysqli_fetch_assoc($trend_data)){ $labels[]=date('M d', strtotime($t['sale_day'])); $values[]=(float)$t['daily_total']; $tx[]=(int)$t['transactions']; } } ?>
const el=document.getElementById('teamTrendChart');
if(el && window.Chart){ new Chart(el.getContext('2d'),{type:'line',data:{labels:<?php echo json_encode($labels); ?>,datasets:[{label:'Team Sales (D)',data:<?php echo json_encode($values); ?>,borderWidth:3,tension:.3,fill:true},{label:'Transactions',data:<?php echo json_encode($tx); ?>,borderWidth:2,tension:.3,yAxisID:'y1'}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{callback:function(v){return 'D'+Number(v).toLocaleString();}}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false},ticks:{stepSize:1}}}}}); }
});
</script>
<?php sisRenderLayoutEnd(); ?>
</body></html>
