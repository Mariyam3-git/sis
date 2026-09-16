<?php
// admin/cancelled_sales.php
// Read-only audit showing cancelled sales across ALL outlets.
// Includes cancellations made by both agents and supervisors.
// Super Admin inherits this page via role inheritance.

require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole(['admin', 'super_admin']);

if (!hasRole(['admin', 'super_admin'])) {
    redirectTo(appPath('dashboard.php'));
}

$viewer_role = strtolower((string)($_SESSION['role'] ?? 'admin'));
$is_super_admin = ($viewer_role === 'super_admin');

// Date range filter
$date_from = isset($_GET['date_from']) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? (string)$_GET['date_to'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = date('Y-m-d');
if (strtotime($date_from) > strtotime($date_to)) {
    $tmp = $date_from; $date_from = $date_to; $date_to = $tmp;
}

$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$outlet_filter = isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : 0;
$agent_filter = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$role_filter = isset($_GET['role']) ? trim((string)$_GET['role']) : ''; // 'agent' | 'supervisor' | ''

// Currency
$currency_symbol = 'D';
$settings_query = mysqli_query($conn, "SELECT * FROM settings");
if ($settings_query) {
    while ($row = mysqli_fetch_assoc($settings_query)) {
        if ($row['setting_key'] === 'currency_symbol' && !empty($row['setting_value'])) $currency_symbol = $row['setting_value'];
    }
}

// Outlets dropdown
$outlets_list = [];
$outlets_query = mysqli_query($conn, "SELECT id, name FROM outlets WHERE is_active = 1 ORDER BY name");
while ($row = mysqli_fetch_assoc($outlets_query)) $outlets_list[] = $row;

// People dropdown (agents + supervisors, filtered by outlet if selected)
$agents_list = [];
if ($outlet_filter > 0) {
    $agents_stmt = mysqli_prepare($conn, "SELECT id, full_name, role FROM users WHERE role IN ('agent','supervisor') AND outlet_id = ? ORDER BY role, full_name");
    if ($agents_stmt) {
        mysqli_stmt_bind_param($agents_stmt, 'i', $outlet_filter);
        mysqli_stmt_execute($agents_stmt);
        $res = mysqli_stmt_get_result($agents_stmt);
        while ($row = mysqli_fetch_assoc($res)) $agents_list[] = $row;
        mysqli_stmt_close($agents_stmt);
    }
} else {
    $agents_stmt = mysqli_prepare($conn, "SELECT id, full_name, role FROM users WHERE role IN ('agent','supervisor') ORDER BY role, full_name");
    if ($agents_stmt) {
        mysqli_stmt_execute($agents_stmt);
        $res = mysqli_stmt_get_result($agents_stmt);
        while ($row = mysqli_fetch_assoc($res)) $agents_list[] = $row;
        mysqli_stmt_close($agents_stmt);
    }
}

// Helper: build WHERE clause (reused by export + main query)
function buildAdminCancelledWhere($date_from, $date_to, $outlet_filter, $agent_filter, $role_filter, $search) {
    $where = "u.role IN ('agent','supervisor') 
              AND (v.notes LIKE '%[CANCELLED by%') 
              AND DATE(sl.payment_date) BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    $types = 'ss';

    if ($outlet_filter > 0) { $where .= " AND u.outlet_id = ?"; $params[] = $outlet_filter; $types .= 'i'; }
    if ($agent_filter > 0) { $where .= " AND sl.agent_id = ?"; $params[] = $agent_filter; $types .= 'i'; }
    if ($role_filter === 'agent' || $role_filter === 'supervisor') {
        $where .= " AND u.role = ?";
        $params[] = $role_filter; $types .= 's';
    }
    if ($search !== '') {
        $where .= " AND (v.contact LIKE ? OR s.name LIKE ? OR p.name LIKE ? OR v.notes LIKE ? OR u.full_name LIKE ? OR o.name LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types .= 'ssssss';
    }
    return [$where, $params, $types];
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cancelled_sales_all_' . $date_from . '_to_' . $date_to . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Date Cancelled', 'Cancelled By', 'Role', 'Outlet', 'Customer GSM', 'Service', 'Purpose', 'Quantity', 'Amount', 'Reason']);

    [$where, $params, $types] = buildAdminCancelledWhere($date_from, $date_to, $outlet_filter, $agent_filter, $role_filter, $search);
    $export_sql = "SELECT sl.id, sl.payment_date, sl.amount, v.contact AS gsm, v.notes,
                          v.quantity, s.name AS service_name, p.name AS purpose_name,
                          u.full_name AS agent_name, u.role AS agent_role,
                          o.name AS outlet_name
                   FROM sales sl
                   LEFT JOIN visitors v ON sl.visitor_id = v.id
                   LEFT JOIN services s ON v.service_id = s.id
                   LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
                   LEFT JOIN users u ON sl.agent_id = u.id
                   LEFT JOIN outlets o ON u.outlet_id = o.id
                   WHERE $where
                   ORDER BY sl.payment_date DESC";

    $stmt = mysqli_prepare($conn, $export_sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            preg_match('/\[CANCELLED by [^\]]*?(?:\| Reason:\s*([^\]]+))?\]/i', (string)$row['notes'], $m);
            $reason = isset($m[1]) ? trim($m[1]) : '';
            fputcsv($output, [
                $row['payment_date'],
                $row['agent_name'] ?: 'N/A',
                ucfirst($row['agent_role'] ?: 'agent'),
                $row['outlet_name'] ?: 'N/A',
                $row['gsm'] ?: 'N/A',
                $row['service_name'] ?: 'N/A',
                $row['purpose_name'] ?: 'N/A',
                max(1, (int)$row['quantity']),
                number_format(abs((float)$row['amount']), 2),
                $reason,
            ]);
        }
        mysqli_stmt_close($stmt);
    }
    fclose($output);
    exit;
}

// Main query
[$where, $params, $types] = buildAdminCancelledWhere($date_from, $date_to, $outlet_filter, $agent_filter, $role_filter, $search);

$sql = "SELECT sl.id AS sale_id, sl.visitor_id, sl.amount, sl.payment_status, sl.payment_date,
               v.contact AS gsm, v.notes AS visitor_notes, v.quantity, v.status AS visitor_status,
               v.visit_time, s.name AS service_name, p.name AS purpose_name,
               u.full_name AS agent_name, u.id AS agent_id, u.role AS agent_role,
               o.name AS outlet_name, o.id AS outlet_id
        FROM sales sl
        LEFT JOIN visitors v ON sl.visitor_id = v.id
        LEFT JOIN services s ON v.service_id = s.id
        LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
        LEFT JOIN users u ON sl.agent_id = u.id
        LEFT JOIN outlets o ON u.outlet_id = o.id
        WHERE $where
        ORDER BY sl.payment_date DESC
        LIMIT 500";
$stmt = mysqli_prepare($conn, $sql);
$rows = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    mysqli_stmt_close($stmt);
}

// Summary stats
$total_cancelled = count($rows);
$total_amount = 0;
$today_count = 0;
$today = date('Y-m-d');
$per_agent = [];
$per_outlet = [];
$per_role = ['agent' => ['count' => 0, 'amount' => 0], 'supervisor' => ['count' => 0, 'amount' => 0]];
foreach ($rows as $r) {
    $total_amount += abs((float)$r['amount']);
    if (date('Y-m-d', strtotime($r['payment_date'])) === $today) $today_count++;

    $agent_name = $r['agent_name'] ?: 'Unknown';
    if (!isset($per_agent[$agent_name])) $per_agent[$agent_name] = ['count' => 0, 'amount' => 0, 'role' => $r['agent_role'] ?? 'agent'];
    $per_agent[$agent_name]['count']++;
    $per_agent[$agent_name]['amount'] += abs((float)$r['amount']);

    $outlet_name = $r['outlet_name'] ?: 'Unknown';
    if (!isset($per_outlet[$outlet_name])) $per_outlet[$outlet_name] = ['count' => 0, 'amount' => 0];
    $per_outlet[$outlet_name]['count']++;
    $per_outlet[$outlet_name]['amount'] += abs((float)$r['amount']);

    $role_key = ($r['agent_role'] === 'supervisor') ? 'supervisor' : 'agent';
    $per_role[$role_key]['count']++;
    $per_role[$role_key]['amount'] += abs((float)$r['amount']);
}
arsort($per_agent);
arsort($per_outlet);

// Build query string for the export link
$export_query = http_build_query([
    'date_from' => $date_from,
    'date_to' => $date_to,
    'q' => $search,
    'outlet_id' => $outlet_filter,
    'agent_id' => $agent_filter,
    'role' => $role_filter,
    'export' => 'csv',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Cancelled Sales Audit - All Outlets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">

<style>
.kpi-filter-card { border: 1px solid var(--sis-border, #e2e8f0); border-radius: 18px; background: var(--sis-surface, #fff); box-shadow: var(--sis-shadow, 0 12px 32px rgba(15, 23, 42, 0.08)); margin-bottom: 22px; overflow: hidden; }
.kpi-filter-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; border-bottom: 1px solid var(--sis-border, #e2e8f0); background: var(--sis-surface-soft, #f8fafc); }
.kpi-filter-title { margin: 0; font-size: 16px; font-weight: 800; color: var(--sis-heading, #0f172a); }
.kpi-filter-subtitle { color: var(--sis-muted, #64748b); font-size: 13px; margin-top: 2px; }
.kpi-filter-body { padding: 18px; }
.kpi-quick-ranges { display: flex; gap: 8px; flex-wrap: wrap; }
.kpi-quick-ranges a { display: inline-flex; align-items: center; gap: 6px; padding: 7px 11px; border-radius: 999px; border: 1px solid var(--sis-border, #e2e8f0); background: var(--sis-surface, #fff); color: var(--sis-text, #172033); font-size: 12px; font-weight: 700; text-decoration: none; }
.kpi-quick-ranges a:hover { border-color: var(--sis-primary, #e30613); color: var(--sis-primary, #e30613); }
.kpi-filter-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 14px; align-items: end; }
.kpi-filter-field { grid-column: span 3; }
.kpi-filter-field label { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 12px; font-weight: 800; color: var(--sis-muted, #64748b); text-transform: uppercase; letter-spacing: .04em; }
.kpi-filter-field .form-control, .kpi-filter-field .form-select { min-height: 43px; border-radius: 12px; }
.kpi-filter-actions { grid-column: span 12; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding-top: 4px; }
.kpi-filter-actions-right { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
@media (max-width: 1200px) { .kpi-filter-field { grid-column: span 4; } }
@media (max-width: 768px) { .kpi-filter-head { align-items: flex-start; flex-direction: column; } .kpi-filter-field, .kpi-filter-actions { grid-column: span 12; } }
.kpi-section { background: #fff; border-radius: 18px; padding: 22px; margin-bottom: 22px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); }
.kpi-section-header { font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.kpi-section-header i { color: #dc3545; }
.kpi-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.kpi-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; background: #fff; transition: transform 0.2s, box-shadow 0.2s; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(220, 53, 69, 0.1); }
.kpi-icon { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; background: rgba(220, 53, 69, 0.1); color: #dc3545; margin-bottom: 10px; }
.kpi-label { font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
.kpi-value { font-size: 1.8rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
.kpi-trend { font-size: 12px; color: #64748b; margin-top: 6px; }
.table-card { background: #fff; border-radius: 18px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); overflow: hidden; margin-bottom: 22px; }
.table-card-header { padding: 18px 22px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.table-card-header h5 { margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.table-card-header h5 i { color: #dc3545; }
.table-card-header .badge-count { background: rgba(220, 53, 69, 0.1); color: #dc3545; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.audit-table thead th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; border-bottom: 2px solid #e2e8f0; padding: 14px 12px; }
.audit-table tbody td { padding: 14px 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.audit-table tbody tr:hover { background: #fef2f2; }
.reason-badge { background: #fef3c7; color: #92400e; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.strikethrough { text-decoration: line-through; color: #94a3b8; font-weight: 600; }
.gsm-chip { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; color: #0f172a; padding: 4px 10px; border-radius: 999px; font-size: 13px; font-weight: 600; }
.agent-chip { display: inline-flex; align-items: center; gap: 6px; background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.supervisor-chip { display: inline-flex; align-items: center; gap: 6px; background: #fce7f3; color: #9d174d; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.outlet-chip { display: inline-flex; align-items: center; gap: 6px; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.status-badge.unpaid { background: #fee2e2; color: #991b1b; }
.status-badge.pending { background: #fef3c7; color: #92400e; }
.status-badge.paid { background: #dcfce7; color: #166534; }
.empty-audit { text-align: center; padding: 70px 20px; background: #fff; border-radius: 18px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); }
.empty-audit i { font-size: 4rem; color: #cbd5e1; display: block; margin-bottom: 12px; }
.empty-audit h4 { font-weight: 700; color: #0f172a; }
@media (max-width: 768px) { .kpi-cards { grid-template-columns: 1fr 1fr; } .kpi-value { font-size: 1.4rem; } }
@media (max-width: 480px) { .kpi-cards { grid-template-columns: 1fr; } }
</style>
</head>
<body class="sis-app-body">
<?php
require_once dirname(__DIR__) . '/include/side_header.php';
sisRenderLayoutStart($is_super_admin ? 'super_admin' : 'admin', 'Cancelled Sales Audit', 'All cancellations across every outlet');
?>

<div class="container mb-4">

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-shield-check text-danger"></i> Cancelled Sales Audit — All Outlets</h3>
            <p class="text-muted mb-0 small">System-wide review of every cancellation by every agent <strong>and</strong> supervisor.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?<?php echo htmlspecialchars($export_query); ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-excel"></i> Export CSV
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="kpi-section">
        <div class="kpi-section-header"><i class="bi bi-bar-chart"></i> Summary</div>
        <div class="kpi-cards">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-x-circle-fill"></i></div>
                <div class="kpi-label">Total Cancelled</div>
                <div class="kpi-value"><?php echo number_format($total_cancelled); ?></div>
                <div class="kpi-trend">All outlets</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="kpi-label">Total Amount</div>
                <div class="kpi-value"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_amount, 2); ?></div>
                <div class="kpi-trend">Value of cancelled services</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-person-badge"></i></div>
                <div class="kpi-label">By Agents</div>
                <div class="kpi-value" style="color: #3730a3;"><?php echo number_format($per_role['agent']['count']); ?></div>
                <div class="kpi-trend">Value: <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($per_role['agent']['amount'], 2); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(157, 23, 77, 0.1); color: #9d174d;"><i class="bi bi-shield-check"></i></div>
                <div class="kpi-label">By Supervisors</div>
                <div class="kpi-value" style="color: #9d174d;"><?php echo number_format($per_role['supervisor']['count']); ?></div>
                <div class="kpi-trend">Value: <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($per_role['supervisor']['amount'], 2); ?></div>
            </div>
        </div>
    </div>

    <!-- Per-Outlet Summary -->
    <?php if (!empty($per_outlet)): ?>
    <div class="kpi-section">
        <div class="kpi-section-header"><i class="bi bi-building"></i> Cancellations By Outlet</div>
        <div class="row g-3">
            <?php foreach ($per_outlet as $outlet => $stats): ?>
                <div class="col-md-4">
                    <div class="kpi-card" style="border-left: 4px solid #dc3545;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="kpi-label"><?php echo htmlspecialchars($outlet); ?></div>
                                <div class="kpi-value" style="font-size: 1.4rem;"><?php echo number_format($stats['count']); ?> cancelled</div>
                                <div class="kpi-trend">Value: <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($stats['amount'], 2); ?></div>
                            </div>
                            <i class="bi bi-building" style="font-size: 2rem; color: #dc3545; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Per-Person Summary -->
    <?php if (!empty($per_agent)): ?>
    <div class="kpi-section">
        <div class="kpi-section-header"><i class="bi bi-person-lines-fill"></i> Top People With Cancellations</div>
        <div class="row g-3">
            <?php $top_agents = array_slice($per_agent, 0, 6, true); foreach ($top_agents as $agent => $stats): 
                $is_supervisor = ($stats['role'] === 'supervisor');
                $chip_color = $is_supervisor ? '#9d174d' : '#6f42c1';
                $chip_icon = $is_supervisor ? 'bi-shield-check' : 'bi-person-circle';
                $role_label = $is_supervisor ? 'Supervisor' : 'Agent';
            ?>
                <div class="col-md-4">
                    <div class="kpi-card" style="border-left: 4px solid <?php echo $chip_color; ?>;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="kpi-label">
                                    <?php echo htmlspecialchars($agent); ?>
                                    <span class="badge" style="background: <?php echo $is_supervisor ? '#fce7f3' : '#e0e7ff'; ?>; color: <?php echo $chip_color; ?>; margin-left: 6px; font-size: 10px;">
                                        <?php echo $role_label; ?>
                                    </span>
                                </div>
                                <div class="kpi-value" style="font-size: 1.4rem;"><?php echo number_format($stats['count']); ?> cancelled</div>
                                <div class="kpi-trend">Value: <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($stats['amount'], 2); ?></div>
                            </div>
                            <i class="bi <?php echo $chip_icon; ?>" style="font-size: 2rem; color: <?php echo $chip_color; ?>; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="kpi-filter-card">
        <div class="kpi-filter-head">
            <div>
                <h5 class="kpi-filter-title"><i class="bi bi-funnel"></i> Filter Cancellations</h5>
                <div class="kpi-filter-subtitle">Refine by date, outlet, role, person, or search.</div>
            </div>
            <div class="kpi-quick-ranges">
                <a href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>"><i class="bi bi-calendar-day"></i> Today</a>
                <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>"><i class="bi bi-calendar-month"></i> This Month</a>
                <a href="?date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>"><i class="bi bi-calendar-range"></i> Last 30 Days</a>
                <a href="?date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>"><i class="bi bi-calendar4-range"></i> This Year</a>
            </div>
        </div>
        <form method="GET" class="kpi-filter-body">
            <div class="kpi-filter-grid">
                <div class="kpi-filter-field">
                    <label for="date_from"><i class="bi bi-calendar-range"></i> From Date</label>
                    <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="kpi-filter-field">
                    <label for="date_to"><i class="bi bi-calendar-check"></i> To Date</label>
                    <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="kpi-filter-field">
                    <label for="outlet_id"><i class="bi bi-building"></i> Outlet</label>
                    <select class="form-select" name="outlet_id" id="outlet_id" onchange="this.form.submit()">
                        <option value="0">All Outlets</option>
                        <?php foreach ($outlets_list as $o): ?>
                            <option value="<?php echo (int)$o['id']; ?>" <?php echo $outlet_filter === (int)$o['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="kpi-filter-field">
                    <label for="role"><i class="bi bi-person-gear"></i> Role</label>
                    <select class="form-select" name="role" id="role">
                        <option value="">All Roles</option>
                        <option value="agent" <?php echo $role_filter === 'agent' ? 'selected' : ''; ?>>Agents Only</option>
                        <option value="supervisor" <?php echo $role_filter === 'supervisor' ? 'selected' : ''; ?>>Supervisors Only</option>
                    </select>
                </div>
                <div class="kpi-filter-field">
                    <label for="agent_id"><i class="bi bi-person"></i> Person</label>
                    <select class="form-select" name="agent_id" id="agent_id">
                        <option value="0">All People</option>
                        <?php foreach ($agents_list as $ag): ?>
                            <option value="<?php echo (int)$ag['id']; ?>" <?php echo $agent_filter === (int)$ag['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ag['full_name']); ?>
                                (<?php echo ucfirst($ag['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="kpi-filter-field" style="grid-column: span 7;">
                    <label for="q"><i class="bi bi-search"></i> Search</label>
                    <input type="text" class="form-control" name="q" id="q" placeholder="GSM, service, reason, name, outlet..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="kpi-filter-actions">
                    <div></div>
                    <div class="kpi-filter-actions-right">
                        <a href="cancelled_sales.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-funnel"></i> Apply Filters</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Audit Table -->
    <?php if (empty($rows)): ?>
        <div class="empty-audit">
            <i class="bi bi-shield-check"></i>
            <h4>No cancelled sales found</h4>
            <p class="text-muted">No cancellations match the current filters.</p>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-list-check"></i> Cancellation History</h5>
                <span class="badge-count"><i class="bi bi-database"></i> <?php echo number_format($total_cancelled); ?> record<?php echo $total_cancelled === 1 ? '' : 's'; ?></span>
            </div>
            <div class="table-responsive">
                <table class="table audit-table mb-0" id="cancelledTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cancelled By</th>
                            <th>Role</th>
                            <th>Outlet</th>
                            <th>Cancelled On</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Amount</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($rows as $row):
                            preg_match('/\[CANCELLED by (agent|supervisor): ([^@]+) @ ([^|]+)(?:\| Reason:\s*([^\]]+))?\]/i', (string)$row['visitor_notes'], $m);
                            $cancel_role = isset($m[1]) ? strtolower(trim($m[1])) : 'agent';
                            $cancel_by = isset($m[2]) ? trim($m[2]) : ($row['agent_name'] ?: 'Unknown');
                            $cancel_time = isset($m[3]) ? trim($m[3]) : date('d/m/Y H:i', strtotime($row['payment_date']));
                            $cancel_reason = isset($m[4]) ? trim($m[4]) : 'No reason given';
                            $is_supervisor_cancel = ($cancel_role === 'supervisor');
                            $chip_class = $is_supervisor_cancel ? 'supervisor-chip' : 'agent-chip';
                            $chip_icon = $is_supervisor_cancel ? 'bi-shield-check' : 'bi-person-badge';
                        ?>
                        <tr>
                            <td><span class="text-muted">#<?php echo $i++; ?></span></td>
                            <td>
                                <span class="<?php echo $chip_class; ?>">
                                    <i class="bi <?php echo $chip_icon; ?>"></i> <?php echo htmlspecialchars($cancel_by); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_supervisor_cancel): ?>
                                    <span class="badge" style="background: #fce7f3; color: #9d174d;">Supervisor</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #e0e7ff; color: #3730a3;">Agent</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="outlet-chip"><i class="bi bi-building"></i> <?php echo htmlspecialchars($row['outlet_name'] ?: 'Unknown'); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($cancel_time); ?></strong></td>
                            <td>
                                <?php if (!empty($row['gsm'])): ?>
                                    <span class="gsm-chip"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($row['gsm']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">No GSM</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['service_name'] ?: 'N/A'); ?></strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($row['purpose_name'] ?: 'N/A'); ?></small>
                            </td>
                            <td class="text-center"><span class="badge bg-secondary">x<?php echo max(1, (int)$row['quantity']); ?></span></td>
                            <td class="text-end"><span class="strikethrough"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format(abs((float)$row['amount']), 2); ?></span></td>
                            <td><span class="reason-badge"><i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($cancel_reason); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="alert alert-light border small mb-0">
            <i class="bi bi-info-circle text-danger"></i>
            Showing up to 500 cancellations from every outlet — both agents and supervisors included. Use filters to narrow the view.
        </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {
    if ($.fn.DataTable && document.getElementById('cancelledTable')) {
        $('#cancelledTable').DataTable({
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 75, 100, -1], [10, 25, 50, 75, 100, 'All']],
            order: [[4, 'desc']],
            scrollX: true,
            autoWidth: false,
            language: { search: "Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ entries" }
        });
    }
});
</script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>