<?php
// supervisor/staff_services.php
// Supervisor view of services handled by AGENTS and SUPERVISORS in their outlet.
// Two modes:
//   1. Overview — one row per staff member with totals
//   2. Detail   — one staff member's full service breakdown (?agent_id=X)
// PAID ONLY. Cancelled, Pending, and Unpaid are excluded.

require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('supervisor');

if (!hasRole('supervisor')) {
    redirectTo(appPath('dashboard.php'));
}

$supervisor_id = (int)$_SESSION['user_id'];
$outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);

// Outlet name
$outlet_name = 'Unknown Outlet';
if ($outlet_id) {
    $stmt = mysqli_prepare($conn, "SELECT name FROM outlets WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $outlet_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) $outlet_name = $row['name'];
        mysqli_stmt_close($stmt);
    }
}

// Currency
$currency_symbol = 'D';
$settings_query = mysqli_query($conn, "SELECT * FROM settings");
if ($settings_query) {
    while ($row = mysqli_fetch_assoc($settings_query)) {
        if ($row['setting_key'] === 'currency_symbol' && !empty($row['setting_value'])) $currency_symbol = $row['setting_value'];
    }
}

// Date range
$date_from = isset($_GET['date_from']) ? (string)$_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? (string)$_GET['date_to'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = date('Y-m-d');
if (strtotime($date_from) > strtotime($date_to)) {
    $tmp = $date_from; $date_from = $date_to; $date_to = $tmp;
}

$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$role_filter = isset($_GET['role']) ? trim((string)$_GET['role']) : ''; // 'agent' | 'supervisor' | ''
$view_mode = $agent_id > 0 ? 'detail' : 'overview';

$has_quantity = tableColumnExists($conn, 'visitors', 'quantity');
$quantityExpr = $has_quantity ? "COALESCE(SUM(GREATEST(COALESCE(v.quantity, 1), 1)), 0)" : "COUNT(*)";

// Validate staff belongs to outlet (agent OR supervisor)
$selected_agent = null;
if ($agent_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT id, full_name, role FROM users WHERE id = ? AND outlet_id = ? AND role IN ('agent','supervisor') LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $agent_id, $outlet_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) $selected_agent = $row;
        mysqli_stmt_close($stmt);
    }
    if (!$selected_agent) {
        header('Location: staff_services.php');
        exit();
    }
}

// ============================================
// SHARED base filter — PAID ONLY + excludes cancelled
// ============================================
$base_where = "
    u.outlet_id = ?
    AND DATE(v.visit_time) BETWEEN ? AND ?
    AND s.payment_status = 'Paid'
    AND s.amount >= 0
    AND (v.notes IS NULL OR v.notes NOT LIKE '%[CANCELLED by%')
";
$base_types = 'iss';

// ============================================
// CSV EXPORT
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = ($view_mode === 'detail' && $selected_agent)
        ? 'staff_services_' . preg_replace('/[^a-z0-9]+/i', '_', $selected_agent['full_name']) . '_' . $date_from . '_to_' . $date_to . '.csv'
        : 'staff_services_' . preg_replace('/[^a-z0-9]+/i', '_', $outlet_name) . '_' . $date_from . '_to_' . $date_to . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($view_mode === 'detail' && $selected_agent) {
        // Per-service export for one staff member
        fputcsv($output, ['Service', 'Requests', 'Total Units', 'Total Revenue']);
        $sql = "
            SELECT sv.name,
                   COUNT(DISTINCT v.id) AS request_count,
                   $quantityExpr AS total_units,
                   COALESCE(SUM(s.amount), 0) AS total_revenue
            FROM visitors v
            LEFT JOIN services sv ON v.service_id = sv.id
            LEFT JOIN sales s ON v.id = s.visitor_id
            LEFT JOIN users u ON v.agent_id = u.id
            WHERE $base_where AND u.id = ?
            GROUP BY sv.id, sv.name
            ORDER BY request_count DESC";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = $base_types . 'i';
            mysqli_stmt_bind_param($stmt, $types, $outlet_id, $date_from, $date_to, $selected_agent['id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                fputcsv($output, [
                    $row['name'] ?: 'Unknown',
                    (int)$row['request_count'],
                    (int)$row['total_units'],
                    number_format((float)$row['total_revenue'], 2),
                ]);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Per-staff export
        fputcsv($output, ['Staff', 'Role', 'Services Handled', 'Total Requests', 'Total Units', 'Total Revenue']);
        $extra_where = '';
        $extra_params = [];
        $extra_types = '';
        if ($role_filter === 'agent' || $role_filter === 'supervisor') {
            $extra_where = " AND u.role = ?";
            $extra_params[] = $role_filter;
            $extra_types .= 's';
        }
        $sql = "
            SELECT u.id, u.full_name, u.role,
                   COUNT(DISTINCT sv.id) AS services_count,
                   COUNT(DISTINCT v.id) AS request_count,
                   $quantityExpr AS total_units,
                   COALESCE(SUM(s.amount), 0) AS total_revenue
            FROM visitors v
            JOIN users u ON v.agent_id = u.id
            LEFT JOIN services sv ON v.service_id = sv.id
            LEFT JOIN sales s ON v.id = s.visitor_id
            WHERE $base_where AND u.role IN ('agent','supervisor')$extra_where
            GROUP BY u.id, u.full_name, u.role
            ORDER BY total_revenue DESC";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $all_types = $base_types . $extra_types;
            $all_params = array_merge([$outlet_id, $date_from, $date_to], $extra_params);
            mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                fputcsv($output, [
                    $row['full_name'],
                    ucfirst($row['role']),
                    (int)$row['services_count'],
                    (int)$row['request_count'],
                    (int)$row['total_units'],
                    number_format((float)$row['total_revenue'], 2),
                ]);
            }
            mysqli_stmt_close($stmt);
        }
    }
    fclose($output);
    exit;
}

// ============================================
// DATA QUERIES
// ============================================

$agents_overview = [];
$agent_services = [];
$totals = ['services' => 0, 'requests' => 0, 'units' => 0, 'revenue' => 0];
$role_totals = [
    'agent' => ['count' => 0, 'requests' => 0, 'revenue' => 0],
    'supervisor' => ['count' => 0, 'requests' => 0, 'revenue' => 0],
];

if ($view_mode === 'overview') {
    // One row per staff member (agents + supervisors) in this outlet
    $extra_where = '';
    $extra_params = [];
    $extra_types = '';
    if ($role_filter === 'agent' || $role_filter === 'supervisor') {
        $extra_where = " AND u.role = ?";
        $extra_params[] = $role_filter;
        $extra_types .= 's';
    }
    $sql = "
        SELECT u.id, u.full_name, u.role,
               COUNT(DISTINCT sv.id) AS services_count,
               COUNT(DISTINCT v.id) AS request_count,
               $quantityExpr AS total_units,
               COALESCE(SUM(s.amount), 0) AS total_revenue
        FROM visitors v
        JOIN users u ON v.agent_id = u.id
        LEFT JOIN services sv ON v.service_id = sv.id
        LEFT JOIN sales s ON v.id = s.visitor_id
        WHERE $base_where AND u.role IN ('agent','supervisor')$extra_where
        GROUP BY u.id, u.full_name, u.role
        ORDER BY total_revenue DESC, request_count DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $all_types = $base_types . $extra_types;
        $all_params = array_merge([$outlet_id, $date_from, $date_to], $extra_params);
        mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $agents_overview[] = $row;
            $totals['services'] += (int)$row['services_count'];
            $totals['requests'] += (int)$row['request_count'];
            $totals['units'] += (int)$row['total_units'];
            $totals['revenue'] += (float)$row['total_revenue'];

            $role_key = ($row['role'] === 'supervisor') ? 'supervisor' : 'agent';
            $role_totals[$role_key]['count']++;
            $role_totals[$role_key]['requests'] += (int)$row['request_count'];
            $role_totals[$role_key]['revenue'] += (float)$row['total_revenue'];
        }
        mysqli_stmt_close($stmt);
    }

    // Search filter (PHP-side)
    if ($search !== '') {
        $search_lower = mb_strtolower($search);
        $agents_overview = array_values(array_filter($agents_overview, function($a) use ($search_lower) {
            return mb_strpos(mb_strtolower((string)$a['full_name']), $search_lower) !== false;
        }));
    }

} else {
    // Detail view for selected staff member
    $sql = "
        SELECT sv.id AS service_id,
               sv.name AS service_name,
               sv.price AS unit_price,
               sv.is_paid,
               sv.is_fixed_price,
               sv.is_quantity_based,
               sv.unit_name,
               COUNT(DISTINCT v.id) AS request_count,
               $quantityExpr AS total_units,
               COALESCE(SUM(s.amount), 0) AS total_revenue
        FROM visitors v
        LEFT JOIN services sv ON v.service_id = sv.id
        LEFT JOIN sales s ON v.id = s.visitor_id
        LEFT JOIN users u ON v.agent_id = u.id
        WHERE $base_where AND u.id = ?
        GROUP BY sv.id, sv.name, sv.price, sv.is_paid, sv.is_fixed_price, sv.is_quantity_based, sv.unit_name
        ORDER BY request_count DESC, total_revenue DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $types = $base_types . 'i';
        mysqli_stmt_bind_param($stmt, $types, $outlet_id, $date_from, $date_to, $selected_agent['id']);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $agent_services[] = $row;
            $totals['services'] += 1;
            $totals['requests'] += (int)$row['request_count'];
            $totals['units'] += (int)$row['total_units'];
            $totals['revenue'] += (float)$row['total_revenue'];
        }
        mysqli_stmt_close($stmt);
    }

    if ($search !== '') {
        $search_lower = mb_strtolower($search);
        $agent_services = array_values(array_filter($agent_services, function($svc) use ($search_lower) {
            return mb_strpos(mb_strtolower((string)$svc['service_name']), $search_lower) !== false;
        }));
    }
}

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$year_start = date('Y-01-01');
$last_30_start = date('Y-m-d', strtotime('-30 days'));
$has_active_filters = ($date_from !== $last_30_start || $date_to !== $today || $search !== '' || $role_filter !== '');

// Build export query string
$export_qs = http_build_query([
    'date_from' => $date_from,
    'date_to' => $date_to,
    'q' => $search,
    'agent_id' => $agent_id,
    'role' => $role_filter,
    'export' => 'csv',
]);

// Quick range links
function qsQuick($extra = []) {
    $base = [
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'q' => $_GET['q'] ?? null,
        'agent_id' => $_GET['agent_id'] ?? null,
        'role' => $_GET['role'] ?? null,
    ];
    return http_build_query(array_filter(array_merge($base, $extra), function($v) { return $v !== null && $v !== ''; }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Staff Services - <?php echo htmlspecialchars($outlet_name); ?></title>
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
.kpi-active-filter-badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 11px; border-radius: 999px; background: var(--sis-primary-soft, rgba(227, 6, 19, 0.1)); color: var(--sis-primary, #e30613); font-size: 12px; font-weight: 800; }
@media (max-width: 1200px) { .kpi-filter-field { grid-column: span 4; } }
@media (max-width: 768px) { .kpi-filter-head { align-items: flex-start; flex-direction: column; } .kpi-filter-field, .kpi-filter-actions { grid-column: span 12; } }

.kpi-section { background: #fff; border-radius: 18px; padding: 22px; margin-bottom: 22px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); }
.kpi-section-header { font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.kpi-section-header i { color: #dc3545; }
.kpi-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.kpi-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; background: #fff; transition: transform 0.2s, box-shadow 0.2s; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(220, 53, 69, 0.1); }
.kpi-icon { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; background: rgba(220, 53, 69, 0.1); color: #dc3545; margin-bottom: 10px; }
.kpi-label { font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
.kpi-value { font-size: 1.8rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
.kpi-trend { font-size: 12px; color: #64748b; margin-top: 6px; }

/* Agent card - red/purple */
.kpi-card.kpi-agent { border-left: 4px solid #dc3545; }
.kpi-card.kpi-agent .kpi-icon { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
/* Supervisor card - pink */
.kpi-card.kpi-supervisor { border-left: 4px solid #9d174d; }
.kpi-card.kpi-supervisor .kpi-icon { background: rgba(157, 23, 77, 0.1); color: #9d174d; }

.table-card { background: #fff; border-radius: 18px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); overflow: hidden; margin-bottom: 22px; }
.table-card-header { padding: 18px 22px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.table-card-header h5 { margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.table-card-header h5 i { color: #dc3545; }
.table-card-header .badge-count { background: rgba(220, 53, 69, 0.1); color: #dc3545; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.services-table thead th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; border-bottom: 2px solid #e2e8f0; padding: 14px 12px; }
.services-table tbody td { padding: 14px 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.services-table tbody tr:hover { background: #fef2f2; }

.type-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.type-badge.free { background: #e0f2fe; color: #075985; }
.type-badge.fixed { background: #dcfce7; color: #166534; }
.type-badge.flexible { background: #fef3c7; color: #92400e; }
.type-badge.quantity { background: #ede9fe; color: #5b21b6; }

.rank-badge { width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: white; }
.rank-badge.rank-1 { background: linear-gradient(135deg, #f59e0b, #d97706); }
.rank-badge.rank-2 { background: linear-gradient(135deg, #94a3b8, #64748b); }
.rank-badge.rank-3 { background: linear-gradient(135deg, #b45309, #92400e); }
.rank-badge.rank-other { background: #e2e8f0; color: #475569; }

.agent-avatar { width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; color: white; margin-right: 10px; }
.agent-avatar.role-agent { background: linear-gradient(135deg, #dc3545, #b02a37); }
.agent-avatar.role-supervisor { background: linear-gradient(135deg, #9d174d, #831843); }

.role-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; margin-left: 8px; }
.role-chip.role-agent { background: #fee2e2; color: #991b1b; }
.role-chip.role-supervisor { background: #fce7f3; color: #9d174d; }

.empty-state { text-align: center; padding: 70px 20px; background: #fff; border-radius: 18px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08); }
.empty-state i { font-size: 4rem; color: #cbd5e1; display: block; margin-bottom: 12px; }
.empty-state h4 { font-weight: 700; color: #0f172a; }

@media (max-width: 768px) { .kpi-cards { grid-template-columns: 1fr 1fr; } .kpi-value { font-size: 1.4rem; } }
@media (max-width: 480px) { .kpi-cards { grid-template-columns: 1fr; } }
</style>
</head>
<body class="sis-app-body">
<?php
require_once dirname(__DIR__) . '/include/side_header.php';
$page_title = ($view_mode === 'detail' && $selected_agent)
    ? 'Staff Services — ' . $selected_agent['full_name']
    : 'Staff Services';
$page_subtitle = ($view_mode === 'detail' && $selected_agent)
    ? 'Services recorded by ' . $selected_agent['full_name'] . ' (' . ucfirst($selected_agent['role']) . ') • Paid only'
    : 'All agents and supervisors in ' . $outlet_name . ' • Paid only';
sisRenderLayoutStart('supervisor', $page_title, $page_subtitle);
?>

<div class="container mb-4">

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-people-fill text-danger"></i> Staff Services</h3>
            <?php if ($view_mode === 'detail' && $selected_agent): ?>
                <p class="text-muted mb-0 small">
                    Viewing services for <strong><?php echo htmlspecialchars($selected_agent['full_name']); ?></strong>
                    <span class="role-chip role-<?php echo htmlspecialchars($selected_agent['role']); ?>">
                        <i class="bi <?php echo $selected_agent['role'] === 'supervisor' ? 'bi-shield-check' : 'bi-person-badge'; ?>"></i>
                        <?php echo ucfirst($selected_agent['role']); ?>
                    </span>
                    • Outlet: <strong><?php echo htmlspecialchars($outlet_name); ?></strong>
                </p>
            <?php else: ?>
                <p class="text-muted mb-0 small">All <strong>agents</strong> and <strong>supervisors</strong> in <strong><?php echo htmlspecialchars($outlet_name); ?></strong> — Paid services only, excludes cancelled.</p>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($view_mode === 'detail'): ?>
                <a href="staff_services.php?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to All Staff
                </a>
            <?php endif; ?>
            <a href="?<?php echo htmlspecialchars($export_qs); ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-excel"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="kpi-section">
        <div class="kpi-section-header"><i class="bi bi-bar-chart"></i> Summary (Paid Only)</div>
        <div class="kpi-cards">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-people"></i></div>
                <div class="kpi-label"><?php echo $view_mode === 'detail' ? 'Services Handled' : 'Active Staff'; ?></div>
                <div class="kpi-value">
                    <?php echo $view_mode === 'detail'
                        ? number_format($totals['services'])
                        : number_format(count($agents_overview)); ?>
                </div>
                <div class="kpi-trend">
                    <?php echo $view_mode === 'detail' ? 'Distinct service types' : 'With paid sales'; ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
                <div class="kpi-label">Total Requests</div>
                <div class="kpi-value"><?php echo number_format($totals['requests']); ?></div>
                <div class="kpi-trend">Paid customer visits</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-box"></i></div>
                <div class="kpi-label">Total Units</div>
                <div class="kpi-value"><?php echo number_format($totals['units']); ?></div>
                <div class="kpi-trend">Quantity across services</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($totals['revenue'], 2); ?></div>
                <div class="kpi-trend">From paid sales</div>
            </div>
        </div>
    </div>

    <!-- Role Split (only in overview) -->
    <?php if ($view_mode === 'overview'): ?>
    <div class="kpi-section">
        <div class="kpi-section-header"><i class="bi bi-diagram-3"></i> Breakdown by Role</div>
        <div class="kpi-cards">
            <div class="kpi-card kpi-agent">
                <div class="kpi-icon"><i class="bi bi-person-badge"></i></div>
                <div class="kpi-label">Agents</div>
                <div class="kpi-value"><?php echo number_format($role_totals['agent']['count']); ?></div>
                <div class="kpi-trend">
                    <?php echo number_format($role_totals['agent']['requests']); ?> requests • 
                    <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($role_totals['agent']['revenue'], 2); ?>
                </div>
            </div>
            <div class="kpi-card kpi-supervisor">
                <div class="kpi-icon"><i class="bi bi-shield-check"></i></div>
                <div class="kpi-label">Supervisors</div>
                <div class="kpi-value"><?php echo number_format($role_totals['supervisor']['count']); ?></div>
                <div class="kpi-trend">
                    <?php echo number_format($role_totals['supervisor']['requests']); ?> requests • 
                    <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($role_totals['supervisor']['revenue'], 2); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="kpi-filter-card">
        <div class="kpi-filter-head">
            <div>
                <h5 class="kpi-filter-title"><i class="bi bi-funnel"></i> Filter</h5>
                <div class="kpi-filter-subtitle"><?php echo $view_mode === 'detail' ? 'Choose a date range or search by service.' : 'Choose a date range, filter by role, or search by name.'; ?></div>
            </div>
            <div class="kpi-quick-ranges">
                <a href="?<?php echo qsQuick(['date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')]); ?>"><i class="bi bi-calendar-day"></i> Today</a>
                <a href="?<?php echo qsQuick(['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d')]); ?>"><i class="bi bi-calendar-month"></i> This Month</a>
                <a href="?<?php echo qsQuick(['date_from' => date('Y-m-d', strtotime('-30 days')), 'date_to' => date('Y-m-d')]); ?>"><i class="bi bi-calendar-range"></i> Last 30 Days</a>
                <a href="?<?php echo qsQuick(['date_from' => date('Y-01-01'), 'date_to' => date('Y-m-d')]); ?>"><i class="bi bi-calendar4-range"></i> This Year</a>
            </div>
        </div>
        <form method="GET" class="kpi-filter-body">
            <?php if ($agent_id): ?>
                <input type="hidden" name="agent_id" value="<?php echo (int)$agent_id; ?>">
            <?php endif; ?>
            <div class="kpi-filter-grid">
                <div class="kpi-filter-field">
                    <label for="date_from"><i class="bi bi-calendar-range"></i> From Date</label>
                    <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="kpi-filter-field">
                    <label for="date_to"><i class="bi bi-calendar-check"></i> To Date</label>
                    <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <?php if ($view_mode === 'overview'): ?>
                <div class="kpi-filter-field">
                    <label for="role"><i class="bi bi-person-gear"></i> Role</label>
                    <select class="form-select" name="role" id="role">
                        <option value="">All Roles</option>
                        <option value="agent" <?php echo $role_filter === 'agent' ? 'selected' : ''; ?>>Agents Only</option>
                        <option value="supervisor" <?php echo $role_filter === 'supervisor' ? 'selected' : ''; ?>>Supervisors Only</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="kpi-filter-field" style="grid-column: span <?php echo $view_mode === 'overview' ? '3' : '6'; ?>;">
                    <label for="q"><i class="bi bi-search"></i> Search <?php echo $view_mode === 'detail' ? 'Service' : 'Name'; ?></label>
                    <input type="text" class="form-control" name="q" id="q" placeholder="Search by <?php echo $view_mode === 'detail' ? 'service name' : 'staff name'; ?>..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="kpi-filter-actions">
                    <div>
                        <?php if ($has_active_filters): ?>
                            <span class="kpi-active-filter-badge"><i class="bi bi-check2-circle"></i> Filters active</span>
                        <?php else: ?>
                            <span class="text-muted small"><i class="bi bi-info-circle"></i> Showing the default last 30 days.</span>
                        <?php endif; ?>
                    </div>
                    <div class="kpi-filter-actions-right">
                        <a href="staff_services.php<?php echo $agent_id ? '?agent_id=' . $agent_id : ''; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-funnel"></i> Apply Filters</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php if ($view_mode === 'overview'): ?>
        <!-- ============================================ -->
        <!-- OVERVIEW: List of staff (agents + supervisors) -->
        <!-- ============================================ -->
        <?php if (empty($agents_overview)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h4>No staff activity found</h4>
                <p class="text-muted">No paid services recorded by agents or supervisors in this outlet for the selected period.</p>
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="table-card-header">
                    <h5><i class="bi bi-people"></i> Staff Overview</h5>
                    <span class="badge-count"><i class="bi bi-database"></i> <?php echo number_format(count($agents_overview)); ?> staff member<?php echo count($agents_overview) === 1 ? '' : 's'; ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table services-table mb-0" id="agentsTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Rank</th>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th class="text-center">Services Handled</th>
                                <th class="text-center">Requests</th>
                                <th class="text-center">Total Units</th>
                                <th class="text-end">Total Revenue</th>
                                <th class="text-end" style="width: 140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($agents_overview as $ag):
                                $rank_class = $rank <= 3 ? 'rank-' . $rank : 'rank-other';
                                $initials = strtoupper(substr($ag['full_name'], 0, 1));
                                $is_supervisor = ($ag['role'] === 'supervisor');
                                $avatar_class = $is_supervisor ? 'role-supervisor' : 'role-agent';
                                $chip_class = $is_supervisor ? 'role-supervisor' : 'role-agent';
                                $chip_icon = $is_supervisor ? 'bi-shield-check' : 'bi-person-badge';
                            ?>
                            <tr>
                                <td><span class="rank-badge <?php echo $rank_class; ?>"><?php echo $rank; ?></span></td>
                                <td>
                                    <span class="agent-avatar <?php echo $avatar_class; ?>"><?php echo htmlspecialchars($initials); ?></span>
                                    <strong><?php echo htmlspecialchars($ag['full_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="role-chip <?php echo $chip_class; ?>">
                                        <i class="bi <?php echo $chip_icon; ?>"></i>
                                        <?php echo ucfirst($ag['role']); ?>
                                    </span>
                                </td>
                                <td class="text-center"><?php echo number_format((int)$ag['services_count']); ?></td>
                                <td class="text-center">
                                    <strong style="font-size: 1.05rem; color: #dc3545;"><?php echo number_format((int)$ag['request_count']); ?></strong>
                                </td>
                                <td class="text-center"><?php echo number_format((int)$ag['total_units']); ?></td>
                                <td class="text-end">
                                    <strong class="text-success"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format((float)$ag['total_revenue'], 2); ?></strong>
                                </td>
                                <td class="text-end">
                                    <a href="staff_services.php?agent_id=<?php echo (int)$ag['id']; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                                       class="btn btn-sm btn-danger">
                                        <i class="bi bi-eye"></i> View Services
                                    </a>
                                </td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8fafc; font-weight: 800;">
                                <td colspan="3" class="text-end">Summary Total:</td>
                                <td class="text-center"><?php echo number_format($totals['services']); ?></td>
                                <td class="text-center"><?php echo number_format($totals['requests']); ?></td>
                                <td class="text-center"><?php echo number_format($totals['units']); ?></td>
                                <td class="text-end text-success"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($totals['revenue'], 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="alert alert-light border small mb-0">
                <i class="bi bi-info-circle text-danger"></i>
                Showing <strong>paid services only</strong> for all <strong>agents and supervisors</strong> in this outlet. Cancelled, Pending, and Unpaid sales are automatically excluded. Click <strong>View Services</strong> to see a person's full service breakdown.
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ============================================ -->
        <!-- DETAIL: One staff member's services -->
        <!-- ============================================ -->
        <?php if (empty($agent_services)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h4>No paid services for <?php echo htmlspecialchars($selected_agent['full_name']); ?></h4>
                <p class="text-muted">This <?php echo htmlspecialchars($selected_agent['role']); ?> hasn't recorded any paid services in the selected period.</p>
                <a href="staff_services.php?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-outline-secondary mt-2">
                    <i class="bi bi-arrow-left"></i> Back to All Staff
                </a>
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="table-card-header">
                    <h5>
                        <i class="bi bi-list-check"></i>
                        Service Performance — <?php echo htmlspecialchars($selected_agent['full_name']); ?>
                        <span class="role-chip role-<?php echo htmlspecialchars($selected_agent['role']); ?>">
                            <i class="bi <?php echo $selected_agent['role'] === 'supervisor' ? 'bi-shield-check' : 'bi-person-badge'; ?>"></i>
                            <?php echo ucfirst($selected_agent['role']); ?>
                        </span>
                    </h5>
                    <span class="badge-count"><i class="bi bi-database"></i> <?php echo number_format($totals['services']); ?> service<?php echo $totals['services'] === 1 ? '' : 's'; ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table services-table mb-0" id="servicesTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Rank</th>
                                <th>Service</th>
                                <th>Type</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-center">Requests</th>
                                <th class="text-center">Total Units</th>
                                <th class="text-end">Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($agent_services as $svc):
                                $is_paid = !empty($svc['is_paid']);
                                $is_fixed = !empty($svc['is_fixed_price']);
                                $is_qty = !empty($svc['is_quantity_based']);
                                if (!$is_paid) { $type_class = 'free'; $type_label = 'Free'; }
                                elseif ($is_qty) { $type_class = 'quantity'; $type_label = 'Qty-based'; }
                                elseif (!$is_fixed) { $type_class = 'flexible'; $type_label = 'Flexible'; }
                                else { $type_class = 'fixed'; $type_label = 'Fixed'; }
                                $rank_class = $rank <= 3 ? 'rank-' . $rank : 'rank-other';
                            ?>
                            <tr>
                                <td><span class="rank-badge <?php echo $rank_class; ?>"><?php echo $rank; ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($svc['service_name'] ?: 'Unknown'); ?></strong>
                                    <?php if ($is_qty && !empty($svc['unit_name'])): ?>
                                        <small class="d-block text-muted">per <?php echo htmlspecialchars($svc['unit_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="type-badge <?php echo $type_class; ?>"><?php echo e($type_label); ?></span></td>
                                <td class="text-end">
                                    <?php if ($is_paid): ?>
                                        <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format((float)$svc['unit_price'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <strong style="font-size: 1.05rem; color: #dc3545;"><?php echo number_format((int)$svc['request_count']); ?></strong>
                                </td>
                                <td class="text-center"><?php echo number_format((int)$svc['total_units']); ?></td>
                                <td class="text-end">
                                    <strong class="text-success"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format((float)$svc['total_revenue'], 2); ?></strong>
                                </td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8fafc; font-weight: 800;">
                                <td colspan="4" class="text-end">Summary Total:</td>
                                <td class="text-center"><?php echo number_format($totals['requests']); ?></td>
                                <td class="text-center"><?php echo number_format($totals['units']); ?></td>
                                <td class="text-end text-success"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($totals['revenue'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="alert alert-light border small mb-0">
                <i class="bi bi-info-circle text-danger"></i>
                Showing <strong>paid services only</strong> for <strong><?php echo htmlspecialchars($selected_agent['full_name']); ?></strong>
                (<strong><?php echo ucfirst($selected_agent['role']); ?></strong>).
                Cancelled, Pending, and Unpaid sales are automatically excluded.
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {
    var commonOpts = {
        scrollX: true,
        autoWidth: false,
        searching: false,
        info: true,
        language: { lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_" }
    };
    if ($.fn.DataTable && document.getElementById('agentsTable')) {
        $('#agentsTable').DataTable(Object.assign({}, commonOpts, {
            pageLength: 25,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
            order: [[6, 'desc']]
        }));
    }
    if ($.fn.DataTable && document.getElementById('servicesTable')) {
        $('#servicesTable').DataTable(Object.assign({}, commonOpts, {
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
            order: [[4, 'desc']]
        }));
    }
});
</script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>