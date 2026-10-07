<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('admin');
if (!hasRole('admin')) redirectTo(appPath('dashboard.php'));

function dsMoney($value) { return 'D ' . number_format((float)($value ?? 0), 2); }
function dsServiceCategory($service) {
    $s = strtolower((string)$service);
    if (str_contains($s, 'comcach') || str_contains($s, 'mobile money') || str_contains($s, 'money transfer')) return 'ComCach';
    if (str_contains($s, 'fastnet') || str_contains($s, 'broadband') || str_contains($s, 'router') || str_contains($s, 'cpe') || str_contains($s, 'internet') || str_contains($s, 'isp')) return 'ISP';
    return 'Telecom / General';
}
function dsShiftBadge($shift) {
    $shift = trim((string)$shift) ?: 'No Shift';
    $class = strtolower($shift) === 'morning' ? 'warning text-dark' : (strtolower($shift) === 'afternoon' ? 'info text-dark' : (strtolower($shift) === 'night' ? 'dark' : 'secondary'));
    return '<span class="badge bg-' . $class . '">' . e($shift) . '</span>';
}

[$date_from, $date_to] = sisDateRange($_GET['date_from'] ?? null, $_GET['date_to'] ?? null, date('Y-m-d'), date('Y-m-d'));
$filter_outlet = isset($_GET['outlet_id']) && $_GET['outlet_id'] !== '' ? (int)$_GET['outlet_id'] : 0;
$filter_shift = trim((string)($_GET['shift'] ?? ''));
$filter_category = trim((string)($_GET['category'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$quantityExpr = tableColumnExists($conn, 'visitors', 'quantity') ? 'GREATEST(COALESCE(v.quantity, 1), 1)' : '1';
$handleExpr = tableColumnExists($conn, 'sales', 'handle_seconds') ? 's.handle_seconds' : (tableColumnExists($conn, 'visitors', 'handling_seconds') ? 'v.handling_seconds' : 'NULL');
$shiftJoin = sisLatestAgentShiftJoin('s', 'a', $conn);
$where = [sisDateWhere($conn, 's.payment_date', $date_from, $date_to)];
if ($filter_outlet > 0) $where[] = 's.outlet_id = ' . $filter_outlet;
if ($q !== '') {
    $like = "'%" . mysqli_real_escape_string($conn, $q) . "%'";
    $where[] = "(COALESCE(s.invoice_number,'') LIKE $like OR COALESCE(v.name,'') LIKE $like OR COALESCE(v.contact,'') LIKE $like OR COALESCE(sv.name,'') LIKE $like OR COALESCE(o.name,'') LIKE $like OR COALESCE(u.full_name,'') LIKE $like)";
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT s.id, s.amount, s.payment_status, s.payment_date, s.invoice_number,
               v.id AS visitor_id, v.name AS customer_name, v.contact AS customer_gsm, $quantityExpr AS quantity,
               sv.name AS service_name,
               o.name AS outlet_name,
               u.full_name AS agent_name,
               COALESCE(a.shift_type, u.shift) AS reported_shift,
               $handleExpr AS handle_seconds
        FROM sales s
        LEFT JOIN visitors v ON s.visitor_id = v.id
        LEFT JOIN services sv ON v.service_id = sv.id
        LEFT JOIN outlets o ON s.outlet_id = o.id
        LEFT JOIN users u ON s.agent_id = u.id
        $shiftJoin
        WHERE $whereSql
        ORDER BY s.payment_date DESC, s.id DESC";
$result = mysqli_query($conn, $sql);
$reportError = $result ? '' : sisDbErrorMessage($conn);

$detailRows = [];
$summaryRows = [];
$serviceRows = [];
$totals = [
    'transactions'=>0,'visitors'=>[],'qty'=>0,'paid'=>0,'pending'=>0,'unpaid'=>0,'withdrawals'=>0,'net'=>0,
    'comcach'=>0,'isp'=>0,'telecom'=>0,'Morning'=>0,'Afternoon'=>0,'Night'=>0
];

while ($result && ($row = mysqli_fetch_assoc($result))) {
    $date = date('Y-m-d', strtotime($row['payment_date']));
    $shift = trim((string)($row['reported_shift'] ?? '')) ?: getShift($row['payment_date']);
    if ($filter_shift !== '' && strtolower($shift) !== strtolower($filter_shift)) continue;
    $service = $row['service_name'] ?: 'Unspecified Service';
    $category = dsServiceCategory($service);
    if ($filter_category !== '' && $category !== $filter_category) continue;
    $outlet = $row['outlet_name'] ?: 'Unknown Showroom';
    $qty = max(1, (int)($row['quantity'] ?? 1));
    $amount = (float)($row['amount'] ?? 0);
    $status = (string)($row['payment_status'] ?? 'Pending');
    $paid = ($status === 'Paid' && $amount > 0) ? $amount : 0;
    $withdrawal = ($status === 'Paid' && $amount < 0) ? abs($amount) : 0;
    $pending = ($status === 'Pending' && $amount > 0) ? $amount : 0;
    $unpaid = ($status === 'Unpaid' && $amount > 0) ? $amount : 0;
    $net = $status === 'Paid' ? $amount : 0;

    $detailRows[] = [
        'date'=>$date,'payment_date'=>$row['payment_date'],'shift'=>$shift,'outlet'=>$outlet,'service'=>$service,'category'=>$category,
        'agent'=>$row['agent_name'] ?: '—','customer'=>$row['customer_name'] ?: '—','gsm'=>$row['customer_gsm'] ?: '—',
        'qty'=>$qty,'paid'=>$paid,'withdrawal'=>$withdrawal,'pending'=>$pending,'unpaid'=>$unpaid,'net'=>$net,
        'status'=>$status,'invoice'=>$row['invoice_number'] ?: '—','handle_seconds'=>$row['handle_seconds']
    ];

    $summaryKey = $date . '|' . $outlet . '|' . $shift;
    if (!isset($summaryRows[$summaryKey])) {
        $summaryRows[$summaryKey] = ['date'=>$date,'outlet'=>$outlet,'shift'=>$shift,'transactions'=>0,'visitors'=>[],'qty'=>0,'paid'=>0,'withdrawals'=>0,'pending'=>0,'unpaid'=>0,'net'=>0,'comcach'=>0,'isp'=>0,'telecom'=>0];
    }
    $serviceKey = $date . '|' . $outlet . '|' . $shift . '|' . $service;
    if (!isset($serviceRows[$serviceKey])) {
        $serviceRows[$serviceKey] = ['date'=>$date,'outlet'=>$outlet,'shift'=>$shift,'service'=>$service,'category'=>$category,'transactions'=>0,'qty'=>0,'paid'=>0,'withdrawals'=>0,'pending'=>0,'unpaid'=>0,'net'=>0];
    }

    $summaryRows[$summaryKey]['transactions']++;
    $summaryRows[$summaryKey]['qty'] += $qty;
    $summaryRows[$summaryKey]['paid'] += $paid;
    $summaryRows[$summaryKey]['withdrawals'] += $withdrawal;
    $summaryRows[$summaryKey]['pending'] += $pending;
    $summaryRows[$summaryKey]['unpaid'] += $unpaid;
    $summaryRows[$summaryKey]['net'] += $net;

    $serviceRows[$serviceKey]['transactions']++;
    $serviceRows[$serviceKey]['qty'] += $qty;
    $serviceRows[$serviceKey]['paid'] += $paid;
    $serviceRows[$serviceKey]['withdrawals'] += $withdrawal;
    $serviceRows[$serviceKey]['pending'] += $pending;
    $serviceRows[$serviceKey]['unpaid'] += $unpaid;
    $serviceRows[$serviceKey]['net'] += $net;
    $summaryRows[$summaryKey]['visitors'][(int)($row['visitor_id'] ?? 0)] = true;
    if ($category === 'ComCach') $summaryRows[$summaryKey]['comcach'] += $paid;
    elseif ($category === 'ISP') $summaryRows[$summaryKey]['isp'] += $paid;
    else $summaryRows[$summaryKey]['telecom'] += $paid;

    $totals['transactions']++;
    $totals['visitors'][(int)($row['visitor_id'] ?? 0)] = true;
    $totals['qty'] += $qty;
    $totals['paid'] += $paid;
    $totals['pending'] += $pending;
    $totals['unpaid'] += $unpaid;
    $totals['withdrawals'] += $withdrawal;
    $totals['net'] += $net;
    $totals[$shift] = ($totals[$shift] ?? 0) + $paid;
    if ($category === 'ComCach') $totals['comcach'] += $paid;
    elseif ($category === 'ISP') $totals['isp'] += $paid;
    else $totals['telecom'] += $paid;
}

usort($summaryRows, fn($a,$b) => [$b['date'],$a['outlet'],$a['shift']] <=> [$a['date'],$b['outlet'],$b['shift']]);
usort($serviceRows, fn($a,$b) => [$b['date'],$a['outlet'],$a['service']] <=> [$a['date'],$b['outlet'],$b['service']]);
$outlets = mysqli_query($conn, "SELECT id, name FROM outlets WHERE is_active = 1 ORDER BY name");
$categories = ['Telecom / General','ComCach','ISP'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Services Report - Comium SIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'Daily Services Report', 'Daily service, shift and showroom totals'); ?>

<div class="sis-report-page">
    <div class="sis-module-hero mb-3">
        <div>
            <span class="sis-eyebrow"><i class="bi bi-clipboard-data"></i> Daily Services Report</span>
            <h2>Daily service performance by showroom and shift</h2>
            <p>This report uses the sales table as the source of truth and separates ComCach, ISP, and general telecom totals.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-lg-end">
            <a class="btn btn-outline-secondary" href="general_report.php"><i class="bi bi-file-earmark-bar-graph"></i> General Report</a>
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>

    <?php if($reportError): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> The report data could not be loaded. Run <a class="alert-link" href="run_upgrade.php">System Upgrade</a>, then reload this page.<?php if (defined('APP_DEBUG') && APP_DEBUG): ?><small class="d-block mt-1"><?php echo e($reportError); ?></small><?php endif; ?></div><?php endif; ?>

    <form method="GET" class="card sis-filter-card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?php echo e($date_from); ?>"></div>
                <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?php echo e($date_to); ?>"></div>
                <div class="col-md-2"><label class="form-label">Showroom</label><select name="outlet_id" class="form-select"><option value="">All</option><?php while($o=mysqli_fetch_assoc($outlets)): ?><option value="<?php echo (int)$o['id']; ?>" <?php echo $filter_outlet===(int)$o['id']?'selected':''; ?>><?php echo e($o['name']); ?></option><?php endwhile; ?></select></div>
                <div class="col-md-2"><label class="form-label">Shift</label><select name="shift" class="form-select"><option value="">All</option><?php foreach(['Morning','Afternoon','Night'] as $s): ?><option value="<?php echo e($s); ?>" <?php echo strtolower($filter_shift)===strtolower($s)?'selected':''; ?>><?php echo e($s); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-select"><option value="">All</option><?php foreach($categories as $c): ?><option value="<?php echo e($c); ?>" <?php echo $filter_category===$c?'selected':''; ?>><?php echo e($c); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Search</label><input type="search" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="Service, customer, agent..."></div>
                <div class="col-md-12 d-flex gap-2 justify-content-end"><button class="btn btn-danger" type="submit"><i class="bi bi-funnel"></i> Apply</button><a class="btn btn-light border" href="daily_showroom_report.php">Reset</a></div>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="sis-kpi-card"><span>Transactions</span><strong><?php echo number_format($totals['transactions']); ?></strong><small><?php echo number_format(count($totals['visitors'])); ?> unique visitors</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card success"><span>Collected</span><strong><?php echo dsMoney($totals['paid']); ?></strong><small>Paid positive sales</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card warning"><span>ComCach Total</span><strong><?php echo dsMoney($totals['comcach']); ?></strong><small>Deposits, bills, data and transfers</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card danger"><span>Withdrawals</span><strong><?php echo dsMoney($totals['withdrawals']); ?></strong><small>Paid negative transactions</small></div></div>
    </div>

    <div class="card sis-report-card">
        <div class="card-header bg-white border-0 pb-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <ul class="nav nav-pills sis-report-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#summary" type="button">Daily Summary</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#services" type="button">Service Breakdown</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#transactions" type="button">Transactions</button></li>
                </ul>
                <div class="sis-table-search"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="Search visible table..." data-sis-table-search></div>
            </div>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="summary">
                    <div class="table-responsive sis-table-shell sis-report-table-shell"><table class="table table-striped table-hover align-middle sis-searchable-table sis-report-data-table" data-sis-searchable-table>
                        <caption class="visually-hidden">Daily showroom and shift summary</caption>
                        <thead><tr><th>Date</th><th>Showroom</th><th>Shift</th><th>Transactions</th><th>Visitors</th><th>Qty</th><th class="text-end">Telecom</th><th class="text-end">ComCach</th><th class="text-end">ISP</th><th class="text-end">Collected</th><th class="text-end">Withdrawals</th><th class="text-end">Pending</th><th class="text-end">Unpaid</th><th class="text-end">Net Cash</th></tr></thead>
                        <tbody><?php if($summaryRows): foreach($summaryRows as $r): ?><tr><td><?php echo date('d/m/Y', strtotime($r['date'])); ?></td><td><?php echo e($r['outlet']); ?></td><td><?php echo dsShiftBadge($r['shift']); ?></td><td><?php echo number_format($r['transactions']); ?></td><td><?php echo number_format(count($r['visitors'])); ?></td><td><?php echo number_format($r['qty']); ?></td><td class="text-end"><?php echo dsMoney($r['telecom']); ?></td><td class="text-end"><?php echo dsMoney($r['comcach']); ?></td><td class="text-end"><?php echo dsMoney($r['isp']); ?></td><td class="text-end fw-semibold"><?php echo dsMoney($r['paid']); ?></td><td class="text-end text-danger fw-semibold"><?php echo dsMoney($r['withdrawals']); ?></td><td class="text-end text-warning fw-semibold"><?php echo dsMoney($r['pending']); ?></td><td class="text-end"><?php echo dsMoney($r['unpaid']); ?></td><td class="text-end fw-semibold"><?php echo dsMoney($r['net']); ?></td></tr><?php endforeach; else: ?><tr><td colspan="14" class="text-center text-muted py-4">No daily services found for the selected filters.</td></tr><?php endif; ?></tbody>
                    </table></div>
                </div>
                <div class="tab-pane fade" id="services">
                    <div class="table-responsive sis-table-shell sis-report-table-shell"><table class="table table-striped table-hover align-middle sis-searchable-table sis-report-data-table" data-sis-searchable-table>
                        <caption class="visually-hidden">Daily service breakdown</caption>
                        <thead><tr><th>Date</th><th>Showroom</th><th>Shift</th><th>Service</th><th>Category</th><th>Transactions</th><th>Qty</th><th class="text-end">Collected</th><th class="text-end">Withdrawals</th><th class="text-end">Pending</th><th class="text-end">Unpaid</th><th class="text-end">Net Cash</th></tr></thead>
                        <tbody><?php if($serviceRows): foreach($serviceRows as $r): ?><tr><td><?php echo date('d/m/Y', strtotime($r['date'])); ?></td><td><?php echo e($r['outlet']); ?></td><td><?php echo dsShiftBadge($r['shift']); ?></td><td><strong><?php echo e($r['service']); ?></strong></td><td><?php echo e($r['category']); ?></td><td><?php echo number_format($r['transactions']); ?></td><td><?php echo number_format($r['qty']); ?></td><td class="text-end fw-semibold"><?php echo dsMoney($r['paid']); ?></td><td class="text-end text-danger fw-semibold"><?php echo dsMoney($r['withdrawals']); ?></td><td class="text-end text-warning fw-semibold"><?php echo dsMoney($r['pending']); ?></td><td class="text-end"><?php echo dsMoney($r['unpaid']); ?></td><td class="text-end fw-semibold"><?php echo dsMoney($r['net']); ?></td></tr><?php endforeach; else: ?><tr><td colspan="12" class="text-center text-muted py-4">No services found.</td></tr><?php endif; ?></tbody>
                    </table></div>
                </div>
                <div class="tab-pane fade" id="transactions">
                    <div class="table-responsive sis-table-shell sis-report-table-shell"><table class="table table-striped table-hover align-middle sis-searchable-table sis-report-data-table" data-sis-searchable-table>
                        <caption class="visually-hidden">Daily transaction details</caption>
                        <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>GSM</th><th>Agent</th><th>Showroom</th><th>Shift</th><th>Service</th><th>Category</th><th>Qty</th><th class="text-end">Amount</th><th>Status</th><th>Handle</th></tr></thead>
                        <tbody><?php if($detailRows): foreach($detailRows as $r): ?><tr><td><?php echo date('d/m/Y H:i', strtotime($r['payment_date'])); ?></td><td><?php echo e($r['invoice']); ?></td><td><?php echo e($r['customer']); ?></td><td><?php echo e($r['gsm']); ?></td><td><?php echo e($r['agent']); ?></td><td><?php echo e($r['outlet']); ?></td><td><?php echo dsShiftBadge($r['shift']); ?></td><td><?php echo e($r['service']); ?></td><td><?php echo e($r['category']); ?></td><td><?php echo number_format($r['qty']); ?></td><td class="text-end fw-semibold"><?php echo dsMoney($r['paid'] > 0 ? $r['paid'] : -$r['withdrawal']); ?></td><td><?php echo e($r['status']); ?></td><td><?php echo renderHandleTimeCell($r['handle_seconds'] ?? null); ?></td></tr><?php endforeach; else: ?><tr><td colspan="13" class="text-center text-muted py-4">No transactions found.</td></tr><?php endif; ?></tbody>
                    </table></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<script>
(function(){
    function activeTable(){ return document.querySelector('.tab-pane.active [data-sis-searchable-table]'); }
    function filterTable(value){ var t=activeTable(); if(!t) return; var q=(value||'').toLowerCase().trim(); t.querySelectorAll('tbody tr').forEach(function(row){ row.style.display = !q || row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none'; }); }
    var search=document.querySelector('[data-sis-table-search]');
    if(search) search.addEventListener('input', function(){ filterTable(this.value); });
    document.querySelectorAll('[data-bs-toggle="pill"]').forEach(function(btn){ btn.addEventListener('shown.bs.tab', function(){ if(search) filterTable(search.value); }); });
})();
</script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
