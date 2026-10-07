<?php
// supervisor/my_services.php
// Supervisor view: services handled by THIS supervisor.
// Paid only, excludes cancelled, pending, unpaid.

require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('supervisor');

if (!hasRole('supervisor')) {
    redirectTo(appPath('dashboard.php'));
}

$agent_id = (int)$_SESSION['user_id'];   // works for supervisors too
$agent_name = $_SESSION['full_name'] ?? 'Supervisor';

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

$has_quantity = tableColumnExists($conn, 'visitors', 'quantity');
$quantityExpr = $has_quantity ? "COALESCE(SUM(GREATEST(COALESCE(v.quantity, 1), 1)), 0)" : "COUNT(*)";

// Base filter — PAID ONLY + excludes cancelled
$base_where = "
    v.agent_id = ?
    AND DATE(v.visit_time) BETWEEN ? AND ?
    AND s.payment_status = 'Paid'
    AND s.amount >= 0
    AND (v.notes IS NULL OR v.notes NOT LIKE '%[CANCELLED by%')
";
$base_types = 'iss';

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="my_services_' . $date_from . '_to_' . $date_to . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Service', 'Requests', 'Total Units', 'Total Revenue']);

    $export_sql = "
        SELECT sv.name,
               COUNT(DISTINCT v.id) AS request_count,
               $quantityExpr AS total_units,
               COALESCE(SUM(s.amount), 0) AS total_revenue
        FROM visitors v
        LEFT JOIN services sv ON v.service_id = sv.id
        LEFT JOIN sales s ON v.id = s.visitor_id
        WHERE $base_where
        GROUP BY sv.id, sv.name
        ORDER BY request_count DESC";
    $stmt = mysqli_prepare($conn, $export_sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $base_types, $agent_id, $date_from, $date_to);
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
    fclose($output);
    exit;
}

// Main query
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
    WHERE $base_where
    GROUP BY sv.id, sv.name, sv.price, sv.is_paid, sv.is_fixed_price, sv.is_quantity_based, sv.unit_name
    ORDER BY request_count DESC, total_revenue DESC";
$stmt = mysqli_prepare($conn, $sql);
$services = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $base_types, $agent_id, $date_from, $date_to);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $services[] = $row;
    mysqli_stmt_close($stmt);
}

if ($search !== '') {
    $search_lower = mb_strtolower($search);
    $services = array_values(array_filter($services, function($svc) use ($search_lower) {
        return mb_strpos(mb_strtolower((string)$svc['service_name']), $search_lower) !== false;
    }));
}

$total_services_count = count($services);
$total_requests = 0;
$total_units = 0;
$total_revenue = 0;
$top_service = null;
$top_service_requests = 0;
foreach ($services as $s) {
    $total_requests += (int)$s['request_count'];
    $total_units += (int)$s['total_units'];
    $total_revenue += (float)$s['total_revenue'];
    if ((int)$s['request_count'] > $top_service_requests) {
        $top_service_requests = (int)$s['request_count'];
        $top_service = $s['service_name'];
    }
}

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$year_start = date('Y-01-01');
$last_30_start = date('Y-m-d', strtotime('-30 days'));
$has_active_filters = ($date_from !== $last_30_start || $date_to !== $today || $search !== '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Services - Comium GM</title>
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
sisRenderLayoutStart('supervisor', 'My Services', 'Services you have recorded (Paid only, excludes cancelled)');
?>

<div class="container mb-4">

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-grid-3x3-gap text-danger"></i> My Services</h3>
            <p class="text-muted mb-0 small">Complete view of every <strong>paid</strong> service you have recorded, ranked by number of requests.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&q=<?php echo urlencode($search); ?>&export=csv"
               class="btn btn-success">
                <i class="bi bi-file-earmark-excel"></i> Export CSV
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <div class="kpi-section">
        <div class="kpi-section-header"><i class="bi bi-bar-chart"></i> Summary (Paid Only)</div>
        <div class="kpi-cards">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-grid"></i></div>
                <div class="kpi-label">Services Handled</div>
                <div class="kpi-value"><?php echo number_format($total_services_count); ?></div>
                <div class="kpi-trend">Distinct service types</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-receipt"></i></div>
                <div class="kpi-label">Total Requests</div>
                <div class="kpi-value"><?php echo number_format($total_requests); ?></div>
                <div class="kpi-trend">Paid customer visits</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_revenue, 2); ?></div>
                <div class="kpi-trend">From paid sales</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bi bi-trophy"></i></div>
                <div class="kpi-label">Top Service</div>
                <div class="kpi-value" style="font-size: 1.2rem;"><?php echo htmlspecialchars($top_service ?: 'N/A'); ?></div>
                <div class="kpi-trend"><?php echo number_format($top_service_requests); ?> requests</div>
            </div>
        </div>
    </div>

    <div class="kpi-filter-card">
        <div class="kpi-filter-head">
            <div>
                <h5 class="kpi-filter-title"><i class="bi bi-funnel"></i> Filter My Services</h5>
                <div class="kpi-filter-subtitle">Choose a date range or search to narrow down the list.</div>
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
                <div class="kpi-filter-field" style="grid-column: span 6;">
                    <label for="q"><i class="bi bi-search"></i> Search Service</label>
                    <input type="text" class="form-control" name="q" id="q" placeholder="Search by service name..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <a href="my_services.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-funnel"></i> Apply Filters</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($services)): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h4>No paid services found</h4>
            <p class="text-muted">You haven't recorded any paid services in the selected period.</p>
            <a href="add_sale.php" class="btn btn-danger mt-2"><i class="bi bi-person-plus"></i> Start Customer / Add Sale</a>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="table-card-header">
                <h5><i class="bi bi-list-check"></i> Service Performance</h5>
                <span class="badge-count"><i class="bi bi-database"></i> <?php echo number_format($total_services_count); ?> service<?php echo $total_services_count === 1 ? '' : 's'; ?></span>
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
                        <?php $rank = 1; foreach ($services as $svc):
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
                            <td class="text-center"><?php echo number_format($total_requests); ?></td>
                            <td class="text-center"><?php echo number_format($total_units); ?></td>
                            <td class="text-end text-success"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_revenue, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="alert alert-light border small mb-0">
            <i class="bi bi-info-circle text-danger"></i>
            Showing <strong>paid services only</strong>. Cancelled, Pending, and Unpaid sales are automatically excluded.
        </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {
    if ($.fn.DataTable && document.getElementById('servicesTable')) {
        $('#servicesTable').DataTable({
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
            order: [[4, 'desc']],
            scrollX: true,
            autoWidth: false,
            searching: false,
            info: true,
            language: { lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ services" }
        });
    }
});
</script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>