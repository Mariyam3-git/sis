<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

[$date_from, $date_to] = sisDateRange($_GET['date_from'] ?? null, $_GET['date_to'] ?? null, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$period_days = sisInclusiveDayCount($date_from, $date_to);
$outlet_filter = isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : 0;
$agent_filter = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;

// Get all outlets for filter
$outlets = mysqli_query($conn, "SELECT * FROM outlets WHERE is_active = 1 ORDER BY name");

// Get all agents for filter
$agents = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'agent' AND is_active = 1 ORDER BY full_name");

$where = [sisDateWhere($conn, 'v.visit_time', $date_from, $date_to)];
if ($outlet_filter > 0) $where[] = "v.outlet_id = $outlet_filter";
if ($agent_filter > 0) $where[] = "v.agent_id = $agent_filter";
$where_clause = "WHERE " . implode(" AND ", $where);

$agents_data = mysqli_query($conn, "
    SELECT 
        u.id,
        u.full_name as agent_name,
        u.shift,
        o.name as outlet_name,
        COUNT(v.id) as total_visitors,
        SUM(CASE WHEN v.status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN v.status = 'Waiting' THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN v.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN v.status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
        COUNT(DISTINCT DATE(v.visit_time)) as active_days,
        ROUND(COUNT(v.id) / $period_days * 30, 1) as monthly_projection
    FROM visitors v
    JOIN users u ON u.id = v.agent_id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    $where_clause
    GROUP BY u.id, u.full_name, u.shift, o.name
    ORDER BY total_visitors DESC
");

// ============================================
// VISITORS PER OUTLET
// ============================================
$outlets_data = mysqli_query($conn, "
    SELECT 
        o.id,
        o.name as outlet_name,
        o.region,
        COUNT(v.id) as total_visitors,
        COUNT(DISTINCT v.agent_id) as active_agents,
        SUM(CASE WHEN v.status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN v.status = 'Waiting' THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN v.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        COUNT(DISTINCT DATE(v.visit_time)) as active_days,
        ROUND(COUNT(v.id) / $period_days, 1) as avg_daily
    FROM visitors v
    JOIN outlets o ON o.id = v.outlet_id
    $where_clause
    GROUP BY o.id, o.name, o.region
    ORDER BY total_visitors DESC
");

// ============================================
// HOURLY DISTRIBUTION (Peak Hours)
// ============================================
$hourly_data = mysqli_query($conn, "
    SELECT 
        HOUR(visit_time) as hour,
        COUNT(*) as visit_count,
        COUNT(DISTINCT agent_id) as active_agents
    FROM visitors v
    $where_clause
    GROUP BY HOUR(visit_time)
    ORDER BY hour
");

// ============================================
// PURPOSE BREAKDOWN
// ============================================
$purpose_data = mysqli_query($conn, "
    SELECT 
        p.name as purpose_name,
        pc.name as category_name,
        COUNT(v.id) as visit_count
    FROM visitors v
    JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN purpose_categories pc ON p.category_id = pc.id
    $where_clause
    GROUP BY p.id, p.name, pc.name
    ORDER BY visit_count DESC
");

$total_row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total, COUNT(DISTINCT v.agent_id) AS active_agents
    FROM visitors v
    $where_clause
")) ?: ['total' => 0, 'active_agents' => 0];
$total_visitors = (int)$total_row['total'];
$active_agents = (int)$total_row['active_agents'];
$avg_daily = $total_visitors / $period_days;
$busiest_day = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT DATE(v.visit_time) AS date, COUNT(*) AS total
    FROM visitors v
    $where_clause
    GROUP BY DATE(v.visit_time)
    ORDER BY total DESC, date ASC
    LIMIT 1
")) ?: ['date' => null, 'total' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Analytics - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
    
    

    <div class="main-content">
        <div class="page-header">
            <h3><i class="bi bi-bar-chart sis-u-002e4b71"></i> Visitor Analytics</h3>
            <span><?php echo date('d M Y'); ?></span>
        </div>

        
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Outlet</label>
                    <select class="form-select" name="outlet_id">
                        <option value="0">All Outlets</option>
                        <?php while($o = mysqli_fetch_assoc($outlets)): ?>
                        <option value="<?php echo $o['id']; ?>" <?php echo $outlet_filter == $o['id'] ? 'selected' : ''; ?>>
                            <?php echo e($o['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Agent</label>
                    <select class="form-select" name="agent_id">
                        <option value="0">All Agents</option>
                        <?php while($a = mysqli_fetch_assoc($agents)): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $agent_filter == $a['id'] ? 'selected' : ''; ?>>
                            <?php echo e($a['full_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Visitors</h6>
                <h2><?php echo number_format($total_visitors); ?></h2>
                <small><?php echo e($date_from); ?> to <?php echo e($date_to); ?></small>
            </div>
            <div class="stat-card">
                <h6>Daily Average</h6>
                <h2><?php echo round($avg_daily, 1); ?></h2>
                <small>Visitors per day</small>
            </div>
            <div class="stat-card">
                <h6>Busiest Day</h6>
                <h2><?php echo $busiest_day['date'] ? date('d M', strtotime($busiest_day['date'])) : '—'; ?></h2>
                <small><?php echo $busiest_day['total']; ?> visitors</small>
            </div>
            <div class="stat-card">
                <h6>Active Agents</h6>
                <h2><?php echo number_format($active_agents); ?></h2>
                <small>Served visitors</small>
            </div>
        </div>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people"></i> Visitors Per Agent
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive sis-table-shell sis-report-table-shell"><table class="table table-striped table-hover align-middle sis-report-data-table">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Outlet</th>
                                <th>Shift</th>
                                <th>Visitors</th>
                                <th>Completed</th>
                                <th>Completion %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($a = mysqli_fetch_assoc($agents_data)): 
                                $completion_rate = $a['total_visitors'] > 0 ? round(($a['completed'] / $a['total_visitors']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo e($a['agent_name']); ?></strong></td>
                                <td><?php echo e($a['outlet_name']); ?></td>
                                <td><span class="badge bg-info"><?php echo e($a['shift']); ?></span></td>
                                <td class="fw-bold"><?php echo $a['total_visitors']; ?></td>
                                <td><?php echo $a['completed']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $completion_rate >= 70 ? 'success' : ($completion_rate >= 40 ? 'warning' : 'danger'); ?>">
                                        <?php echo $completion_rate; ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table></div>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-building"></i> Visitors Per Outlet
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive sis-table-shell sis-report-table-shell"><table class="table table-striped table-hover align-middle sis-report-data-table">
                        <thead>
                            <tr>
                                <th>Outlet</th>
                                <th>Region</th>
                                <th>Visitors</th>
                                <th>Active Agents</th>
                                <th>Daily Avg</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($o = mysqli_fetch_assoc($outlets_data)): ?>
                            <tr>
                                <td><strong><?php echo e($o['outlet_name']); ?></strong></td>
                                <td><?php echo e($o['region']); ?></td>
                                <td class="fw-bold"><?php echo $o['total_visitors']; ?></td>
                                <td><?php echo $o['active_agents']; ?></td>
                                <td><?php echo $o['avg_daily']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table></div>
                </div>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock"></i> Peak Hours Distribution
            </div>
            <div class="card-body">
                <canvas class="sis-u-54b8b4e1" id="hourlyChart"></canvas>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-tags"></i> Visit Purposes Breakdown
            </div>
            <div class="card-body p-0">
                <div class="table-responsive sis-table-shell sis-report-table-shell"><table class="table table-striped table-hover align-middle sis-report-data-table">
                    <thead>
                        <tr>
                            <th>Purpose</th>
                            <th>Category</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_purpose = 0;
                        $purpose_rows = [];
                        while($p = mysqli_fetch_assoc($purpose_data)) {
                            $purpose_rows[] = $p;
                            $total_purpose += $p['visit_count'];
                        }
                        foreach($purpose_rows as $p):
                            $percentage = $total_purpose > 0 ? round(($p['visit_count'] / $total_purpose) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?php echo e($p['purpose_name']); ?></td>
                            <td><span class="badge bg-info"><?php echo e($p['category_name'] ?? 'Uncategorized'); ?></span></td>
                            <td class="fw-bold"><?php echo $p['visit_count']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-2 sis-u-d8269867">
                                        <div class="progress-bar bg-danger sis-progress" data-progress="<?php echo e($percentage); ?>"></div>
                                    </div>
                                    <span><?php echo $percentage; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>

    <script>        const hourlyLabels = [];
        const hourlyCounts = [];
        <?php while($h = mysqli_fetch_assoc($hourly_data)): ?>
        hourlyLabels.push('<?php echo $h['hour']; ?>:00');
        hourlyCounts.push(<?php echo $h['visit_count']; ?>);
        <?php endwhile; ?>

        new Chart(document.getElementById('hourlyChart'), {
            type: 'line',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Visitors',
                    data: hourlyCounts,
                    borderColor: '#E30613',
                    backgroundColor: 'rgba(227,6,19,0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
