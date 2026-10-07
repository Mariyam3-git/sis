<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('admin');
if (!hasRole('admin')) redirectTo(appPath('dashboard.php'));

$currency_symbol = 'D';
$settings_query = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('currency','currency_symbol')");
while ($settings_query && ($row = mysqli_fetch_assoc($settings_query))) {
    if (in_array($row['setting_key'], ['currency_symbol','currency'], true) && trim((string)$row['setting_value']) !== '') $currency_symbol = 'D';
}

function salesMoney($value) { return 'D ' . number_format((float)($value ?? 0), 2); }
function salesStatusBadge($status) {
    $status = (string)$status;
    $class = $status === 'Paid' ? 'success' : ($status === 'Pending' ? 'warning text-dark' : 'danger');
    return '<span class="badge bg-' . $class . '">' . e($status ?: 'Unknown') . '</span>';
}
function salesShiftBadge($shift) {
    $label = trim((string)$shift);
    if ($label === '') $label = 'No Shift';
    $lower = strtolower($label);
    $class = $lower === 'morning' ? 'warning text-dark' : ($lower === 'afternoon' ? 'info text-dark' : ($lower === 'night' ? 'dark' : 'secondary'));
    return '<span class="badge bg-' . $class . '">' . e(ucfirst($label)) . '</span>';
}

[$date_from, $date_to] = sisDateRange($_GET['date_from'] ?? null, $_GET['date_to'] ?? null, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$filter_agent = isset($_GET['agent_id']) && $_GET['agent_id'] !== '' ? (int)$_GET['agent_id'] : 0;
$filter_outlet = isset($_GET['outlet_id']) && $_GET['outlet_id'] !== '' ? (int)$_GET['outlet_id'] : 0;
$filter_shift = trim((string)($_GET['shift'] ?? ''));
$filter_status = trim((string)($_GET['payment_status'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$where = [sisDateWhere($conn, 's.payment_date', $date_from, $date_to)];
if ($filter_agent > 0) $where[] = "s.agent_id = " . $filter_agent;
if ($filter_outlet > 0) $where[] = "s.outlet_id = " . $filter_outlet;
if ($filter_status !== '') $where[] = "s.payment_status = " . sisSqlValue($conn, $filter_status);
if ($filter_shift !== '') $where[] = "LOWER(COALESCE(a.shift_type, u.shift, '')) = " . sisSqlValue($conn, strtolower($filter_shift));
if ($q !== '') {
    $like = "'%" . mysqli_real_escape_string($conn, $q) . "%'";
    $where[] = "(COALESCE(s.invoice_number,'') LIKE $like OR COALESCE(v.name,'') LIKE $like OR COALESCE(v.contact,'') LIKE $like OR COALESCE(u.full_name,'') LIKE $like OR COALESCE(o.name,'') LIKE $like OR COALESCE(sv.name,'') LIKE $like)";
}
$where_clause = implode(' AND ', $where);

$hasSalesHandle = tableColumnExists($conn, 'sales', 'handle_seconds');
$hasSalesStart = tableColumnExists($conn, 'sales', 'handle_started_at');
$hasSalesEnd = tableColumnExists($conn, 'sales', 'handle_completed_at');
$hasVisitorHandle = tableColumnExists($conn, 'visitors', 'handling_seconds');
$salesHandleExpr = $hasSalesHandle ? 's.handle_seconds' : 'NULL';
$visitorHandleExpr = $hasVisitorHandle ? 'v.handling_seconds' : 'NULL';
$handleExpr = $hasSalesHandle || $hasVisitorHandle ? "COALESCE($salesHandleExpr, $visitorHandleExpr)" : 'NULL';
$handleStartExpr = $hasSalesStart ? 's.handle_started_at' : 'NULL';
$handleEndExpr = $hasSalesEnd ? 's.handle_completed_at' : 'NULL';
$shiftJoin = sisLatestAgentShiftJoin('s', 'a', $conn);

$agents_result = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'agent' AND is_active = 1 ORDER BY full_name");
$outlets_result = mysqli_query($conn, "SELECT id, name FROM outlets WHERE is_active = 1 ORDER BY name");
$payment_statuses = ['Paid','Pending','Unpaid'];
$shifts = ['Morning','Afternoon','Night','Rotational'];

$reportErrors = [];
$summaryQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total_transactions,
           " . sisPaidPositiveSum('s') . " AS total_paid,
           " . sisPaidWithdrawalSum('s') . " AS total_withdrawals,
           " . sisPendingPositiveSum('s') . " AS total_pending,
           " . sisUnpaidPositiveSum('s') . " AS total_unpaid,
           " . sisPaidNetSum('s') . " AS net_cash,
           COALESCE(AVG(CASE WHEN s.payment_status = 'Paid' AND s.amount > 0 THEN s.amount END), 0) AS avg_paid_transaction
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    $shiftJoin
    WHERE $where_clause
");
$summary = $summaryQuery ? (mysqli_fetch_assoc($summaryQuery) ?: []) : [];
if (!$summaryQuery) $reportErrors[] = sisDbErrorMessage($conn);

$sales_result = mysqli_query($conn, "
    SELECT s.*, v.name AS visitor_name, v.contact AS customer_gsm, u.full_name AS agent_name,
           o.name AS outlet_name, o.region AS outlet_region, sv.name AS service_name,
           COALESCE(a.shift_type, u.shift) AS agent_shift,
           $handleExpr AS row_handle_seconds,
           $handleStartExpr AS row_handle_started_at,
           $handleEndExpr AS row_handle_completed_at
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    $shiftJoin
    WHERE $where_clause
    ORDER BY s.payment_date DESC, s.id DESC
    LIMIT 1500
");
if (!$sales_result) $reportErrors[] = sisDbErrorMessage($conn);

$report_by_agent = mysqli_query($conn, "
    SELECT COALESCE(u.full_name, 'Unknown Agent') AS label,
           COUNT(s.id) AS transactions,
           " . sisPaidPositiveSum('s') . " AS collected,
           " . sisPaidWithdrawalSum('s') . " AS withdrawals,
           " . sisPendingPositiveSum('s') . " AS pending,
           " . sisUnpaidPositiveSum('s') . " AS unpaid,
           COALESCE(AVG($handleExpr),0) AS avg_handle
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    $shiftJoin
    WHERE $where_clause
    GROUP BY u.id, u.full_name
    ORDER BY collected DESC, transactions DESC
");
if (!$report_by_agent) $reportErrors[] = sisDbErrorMessage($conn);

$report_by_outlet = mysqli_query($conn, "
    SELECT COALESCE(o.name, 'Unknown Showroom') AS label, COALESCE(o.region, '-') AS region,
           COUNT(s.id) AS transactions,
           " . sisPaidPositiveSum('s') . " AS collected,
           " . sisPaidWithdrawalSum('s') . " AS withdrawals,
           " . sisPendingPositiveSum('s') . " AS pending,
           " . sisUnpaidPositiveSum('s') . " AS unpaid
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    $shiftJoin
    WHERE $where_clause
    GROUP BY o.id, o.name, o.region
    ORDER BY collected DESC, transactions DESC
");
if (!$report_by_outlet) $reportErrors[] = sisDbErrorMessage($conn);

$report_by_shift = mysqli_query($conn, "
    SELECT COALESCE(a.shift_type, u.shift, 'No Shift') AS label,
           COUNT(s.id) AS transactions,
           " . sisPaidPositiveSum('s') . " AS collected,
           " . sisPaidWithdrawalSum('s') . " AS withdrawals,
           " . sisPendingPositiveSum('s') . " AS pending,
           " . sisUnpaidPositiveSum('s') . " AS unpaid
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    $shiftJoin
    WHERE $where_clause
    GROUP BY COALESCE(a.shift_type, u.shift, 'No Shift')
    ORDER BY collected DESC, transactions DESC
");
if (!$report_by_shift) $reportErrors[] = sisDbErrorMessage($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Report - Comium SIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'Sales Report', 'Clean searchable sales and revenue report'); ?>

<div class="sis-report-page">
    <div class="sis-module-hero mb-3">
        <div>
            <span class="sis-eyebrow"><i class="bi bi-receipt"></i> Sales Report</span>
            <h2>Transactions, revenue and showroom performance</h2>
            <p>Filter by date, agent, showroom, shift, payment status, or customer details. Search works instantly inside the displayed table.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
            <a class="btn btn-outline-secondary" href="general_report.php"><i class="bi bi-file-earmark-bar-graph"></i> General Report</a>
            <a class="btn btn-outline-success" href="../export_report.php?type=sales&from=<?php echo e($date_from); ?>&to=<?php echo e($date_to); ?>&agent=<?php echo (int)$filter_agent; ?>&outlet=<?php echo (int)$filter_outlet; ?>&shift=<?php echo e($filter_shift); ?>&status=<?php echo e($filter_status); ?>"><i class="bi bi-download"></i> Export</a>
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>

    <?php if ($reportErrors): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            The report could not load all data. Run <a class="alert-link" href="run_upgrade.php">System Upgrade</a>, then reload this page.
            <?php if (defined('APP_DEBUG') && APP_DEBUG): ?><small class="d-block mt-1"><?php echo e(implode(' | ', $reportErrors)); ?></small><?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="card sis-filter-card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?php echo e($date_from); ?>"></div>
                <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?php echo e($date_to); ?>"></div>
                <div class="col-md-2"><label class="form-label">Agent</label><select name="agent_id" class="form-select"><option value="">All Agents</option><?php mysqli_data_seek($agents_result, 0); while($a = mysqli_fetch_assoc($agents_result)): ?><option value="<?php echo (int)$a['id']; ?>" <?php echo $filter_agent === (int)$a['id'] ? 'selected' : ''; ?>><?php echo e($a['full_name']); ?></option><?php endwhile; ?></select></div>
                <div class="col-md-2"><label class="form-label">Showroom</label><select name="outlet_id" class="form-select"><option value="">All Showrooms</option><?php mysqli_data_seek($outlets_result, 0); while($o = mysqli_fetch_assoc($outlets_result)): ?><option value="<?php echo (int)$o['id']; ?>" <?php echo $filter_outlet === (int)$o['id'] ? 'selected' : ''; ?>><?php echo e($o['name']); ?></option><?php endwhile; ?></select></div>
                <div class="col-md-2"><label class="form-label">Shift</label><select name="shift" class="form-select"><option value="">All Shifts</option><?php foreach($shifts as $sft): ?><option value="<?php echo e($sft); ?>" <?php echo strtolower($filter_shift) === strtolower($sft) ? 'selected' : ''; ?>><?php echo e($sft); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Status</label><select name="payment_status" class="form-select"><option value="">All Statuses</option><?php foreach($payment_statuses as $ps): ?><option value="<?php echo e($ps); ?>" <?php echo $filter_status === $ps ? 'selected' : ''; ?>><?php echo e($ps); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-8"><label class="form-label">Search database</label><input type="search" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="Invoice, customer, GSM, service, agent, showroom..."></div>
                <div class="col-md-4 d-flex gap-2"><button class="btn btn-danger flex-fill" type="submit"><i class="bi bi-funnel"></i> Apply</button><a class="btn btn-light border flex-fill" href="sales.php">Reset</a></div>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="sis-kpi-card"><span>Total Transactions</span><strong><?php echo number_format((int)($summary['total_transactions'] ?? 0)); ?></strong><small>Selected period</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card success"><span>Collected</span><strong><?php echo salesMoney($summary['total_paid'] ?? 0); ?></strong><small>Paid positive sales</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card warning"><span>Pending</span><strong><?php echo salesMoney($summary['total_pending'] ?? 0); ?></strong><small>Awaiting payment</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card danger"><span>Unpaid</span><strong><?php echo salesMoney($summary['total_unpaid'] ?? 0); ?></strong><small>Outstanding</small></div></div>
    </div>

    <div class="card sis-report-card">
        <div class="card-header bg-white border-0 pb-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <ul class="nav nav-pills sis-report-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-transactions" type="button">Transactions</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-agent" type="button">By Agent</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-outlet" type="button">By Showroom</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-shift" type="button">By Shift</button></li>
                </ul>
                <div class="sis-table-search"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="Search shown table..." data-sis-table-search></div>
            </div>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-transactions">
                    <div class="table-responsive sis-table-shell sis-report-table-shell">
                        <table class="table table-striped table-hover align-middle sis-searchable-table sis-report-data-table" data-sis-searchable-table>
                            <caption class="visually-hidden">Sales transactions for the selected reporting period</caption>
                            <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>GSM</th><th>Service</th><th>Agent</th><th>Shift</th><th>Showroom</th><th>Handle Time</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php if($sales_result && mysqli_num_rows($sales_result) > 0): while($sale = mysqli_fetch_assoc($sales_result)): ?>
                                <tr>
                                    <td><strong><?php echo date('d/m/Y', strtotime($sale['payment_date'])); ?></strong><small class="d-block text-muted"><?php echo date('H:i', strtotime($sale['payment_date'])); ?></small></td>
                                    <td><?php echo e($sale['invoice_number'] ?: '—'); ?></td>
                                    <td><?php echo e($sale['visitor_name'] ?: '—'); ?></td>
                                    <td><?php echo e($sale['customer_gsm'] ?: '—'); ?></td>
                                    <td><?php echo e($sale['service_name'] ?: '—'); ?></td>
                                    <td><?php echo e($sale['agent_name'] ?: '—'); ?></td>
                                    <td><?php echo salesShiftBadge($sale['agent_shift'] ?? ''); ?></td>
                                    <td><?php echo e($sale['outlet_name'] ?: '—'); ?></td>
                                    <td><?php echo renderHandleTimeCell($sale['row_handle_seconds'] ?? null, $sale['row_handle_started_at'] ?? null, $sale['row_handle_completed_at'] ?? null); ?></td>
                                    <td class="text-end fw-semibold"><?php echo salesMoney($sale['amount']); ?></td>
                                    <td><?php echo salesStatusBadge($sale['payment_status']); ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="11" class="text-center text-muted py-4">No transactions found for the selected filters.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-agent"><?php $r=$report_by_agent; $cols=['Agent','Transactions','Collected','Withdrawals','Pending','Unpaid','Average Handle']; include __DIR__ . '/partials_sales_summary_table.php'; ?></div>
                <div class="tab-pane fade" id="tab-outlet"><?php $r=$report_by_outlet; $cols=['Showroom','Region','Transactions','Collected','Withdrawals','Pending','Unpaid']; include __DIR__ . '/partials_sales_summary_table.php'; ?></div>
                <div class="tab-pane fade" id="tab-shift"><?php $r=$report_by_shift; $cols=['Shift','Transactions','Collected','Withdrawals','Pending','Unpaid']; include __DIR__ . '/partials_sales_summary_table.php'; ?></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<script>
(function(){
    function activeTable(){return document.querySelector('.tab-pane.active [data-sis-searchable-table]');}
    function filterTable(q){
        var table=activeTable(); if(!table) return;
        q=(q||'').toLowerCase().trim();
        table.querySelectorAll('tbody tr').forEach(function(row){
            row.style.display = (!q || row.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
        });
    }
    var search=document.querySelector('[data-sis-table-search]');
    if(search){ search.addEventListener('input', function(){filterTable(this.value);}); }
    document.querySelectorAll('[data-bs-toggle="pill"]').forEach(function(btn){btn.addEventListener('shown.bs.tab', function(){ if(search) filterTable(search.value); });});
})();
</script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
