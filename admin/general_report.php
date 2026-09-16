<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('admin');
if (!hasRole('admin')) redirectTo(appPath('dashboard.php'));

function grMoney($value) { return 'D ' . number_format((float)($value ?? 0), 2); }
function grInt($value) { return number_format((int)($value ?? 0)); }

[$date_from, $date_to] = sisDateRange($_GET['date_from'] ?? null, $_GET['date_to'] ?? null, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$filter_outlet = isset($_GET['outlet_id']) && $_GET['outlet_id'] !== '' ? (int)$_GET['outlet_id'] : 0;
$filter_agent = isset($_GET['agent_id']) && $_GET['agent_id'] !== '' ? (int)$_GET['agent_id'] : 0;
$q = trim((string)($_GET['q'] ?? ''));

$outlets_result = mysqli_query($conn, "SELECT id, name FROM outlets WHERE is_active = 1 ORDER BY name");
$agents_result = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'agent' AND is_active = 1 ORDER BY full_name");

$visitorWhere = [sisDateWhere($conn, 'v.visit_time', $date_from, $date_to)];
$salesWhere = [sisDateWhere($conn, 's.payment_date', $date_from, $date_to)];
if ($filter_outlet > 0) { $visitorWhere[] = "v.outlet_id = $filter_outlet"; $salesWhere[] = "s.outlet_id = $filter_outlet"; }
if ($filter_agent > 0) { $visitorWhere[] = "v.agent_id = $filter_agent"; $salesWhere[] = "s.agent_id = $filter_agent"; }
if ($q !== '') {
    $like = "'%" . mysqli_real_escape_string($conn, $q) . "%'";
    $visitorWhere[] = "(COALESCE(v.name,'') LIKE $like OR COALESCE(v.contact,'') LIKE $like OR COALESCE(u.full_name,'') LIKE $like OR COALESCE(o.name,'') LIKE $like OR COALESCE(sv.name,'') LIKE $like OR COALESCE(p.name,'') LIKE $like)";
    $salesWhere[] = "(COALESCE(s.invoice_number,'') LIKE $like OR COALESCE(v.name,'') LIKE $like OR COALESCE(v.contact,'') LIKE $like OR COALESCE(u.full_name,'') LIKE $like OR COALESCE(o.name,'') LIKE $like OR COALESCE(sv.name,'') LIKE $like)";
}
$visitorClause = implode(' AND ', $visitorWhere);
$salesClause = implode(' AND ', $salesWhere);

$visitorSummary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total_visits,
           COUNT(CASE WHEN v.status = 'Completed' THEN 1 END) AS completed_visits,
           COUNT(CASE WHEN v.status = 'Waiting' THEN 1 END) AS waiting_visits,
           COUNT(CASE WHEN v.status = 'In Progress' THEN 1 END) AS in_progress_visits,
           COUNT(DISTINCT NULLIF(v.contact,'')) AS distinct_contacts,
           COALESCE(ROUND(AVG(v.handling_seconds),0),0) AS avg_handle_seconds
    FROM visitors v
    LEFT JOIN users u ON v.agent_id = u.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    WHERE $visitorClause
")) ?: [];

$salesSummary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS sales_transactions,
           " . sisPaidPositiveSum('s') . " AS paid_revenue,
           " . sisPaidWithdrawalSum('s') . " AS withdrawals,
           " . sisPendingPositiveSum('s') . " AS pending_amount,
           " . sisUnpaidPositiveSum('s') . " AS unpaid_amount,
           " . sisPaidNetSum('s') . " AS net_cash,
           COUNT(CASE WHEN s.payment_status = 'Paid' THEN 1 END) AS paid_count,
           COUNT(CASE WHEN s.payment_status = 'Pending' THEN 1 END) AS pending_count,
           COUNT(CASE WHEN s.payment_status = 'Unpaid' THEN 1 END) AS unpaid_count
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    WHERE $salesClause
")) ?: [];

$ticketSummary = ['total_tickets'=>0,'open_tickets'=>0,'resolved_tickets'=>0,'urgent_tickets'=>0];
if (tableExists($conn, 'support_tickets')) {
    $ticketWhere = [sisDateWhere($conn, 'st.created_at', $date_from, $date_to)];
    if ($filter_outlet > 0) $ticketWhere[] = "st.outlet_id = $filter_outlet";
    if ($filter_agent > 0) $ticketWhere[] = "st.submitted_by = $filter_agent";
    if ($q !== '') {
        $like = "'%" . mysqli_real_escape_string($conn, $q) . "%'";
        $ticketWhere[] = "(COALESCE(st.ticket_no,'') LIKE $like OR COALESCE(st.customer_name,'') LIKE $like OR COALESCE(st.customer_phone,'') LIKE $like OR COALESCE(st.subject,'') LIKE $like OR COALESCE(st.description,'') LIKE $like)";
    }
    $ticketClause = implode(' AND ', $ticketWhere);
    $ticketSummary = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS total_tickets,
               COUNT(CASE WHEN status IN ('Open','In Progress') THEN 1 END) AS open_tickets,
               COUNT(CASE WHEN status IN ('Resolved','Closed') THEN 1 END) AS resolved_tickets,
               COUNT(CASE WHEN priority = 'Urgent' THEN 1 END) AS urgent_tickets
        FROM support_tickets st WHERE $ticketClause
    ")) ?: $ticketSummary;
}

$byOutlet = mysqli_query($conn, "
    SELECT o.name AS showroom, o.region,
           COALESCE(vs.total_visits,0) AS total_visits,
           COALESCE(vs.completed_visits,0) AS completed_visits,
           COALESCE(ss.sales_transactions,0) AS sales_transactions,
           COALESCE(ss.paid_revenue,0) AS paid_revenue,
           COALESCE(ss.withdrawals,0) AS withdrawals,
           COALESCE(ss.pending_amount,0) AS pending_amount,
           COALESCE(ss.unpaid_amount,0) AS unpaid_amount
    FROM outlets o
    LEFT JOIN (
        SELECT v.outlet_id, COUNT(*) AS total_visits, COUNT(CASE WHEN v.status='Completed' THEN 1 END) AS completed_visits
        FROM visitors v LEFT JOIN users u ON v.agent_id = u.id LEFT JOIN outlets o ON v.outlet_id = o.id LEFT JOIN services sv ON v.service_id = sv.id LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
        WHERE $visitorClause GROUP BY v.outlet_id
    ) vs ON vs.outlet_id = o.id
    LEFT JOIN (
        SELECT s.outlet_id, COUNT(*) AS sales_transactions, " . sisPaidPositiveSum('s') . " AS paid_revenue, " . sisPaidWithdrawalSum('s') . " AS withdrawals, " . sisPendingPositiveSum('s') . " AS pending_amount, " . sisUnpaidPositiveSum('s') . " AS unpaid_amount
        FROM sales s LEFT JOIN visitors v ON s.visitor_id = v.id LEFT JOIN users u ON s.agent_id = u.id LEFT JOIN outlets o ON s.outlet_id = o.id LEFT JOIN services sv ON v.service_id = sv.id
        WHERE $salesClause GROUP BY s.outlet_id
    ) ss ON ss.outlet_id = o.id
    WHERE o.is_active = 1
    ORDER BY paid_revenue DESC, total_visits DESC, o.name
");

$byAgent = mysqli_query($conn, "
    SELECT u.full_name AS agent_name, o.name AS showroom,
           COALESCE(vs.total_visits,0) AS total_visits,
           COALESCE(vs.completed_visits,0) AS completed_visits,
           COALESCE(ss.sales_transactions,0) AS sales_transactions,
           COALESCE(ss.paid_revenue,0) AS paid_revenue,
           COALESCE(ss.pending_amount,0) AS pending_amount,
           COALESCE(vs.avg_handle_seconds,0) AS avg_handle_seconds
    FROM users u
    LEFT JOIN outlets o ON u.outlet_id = o.id
    LEFT JOIN (
        SELECT v.agent_id, COUNT(*) AS total_visits, COUNT(CASE WHEN v.status='Completed' THEN 1 END) AS completed_visits, COALESCE(ROUND(AVG(v.handling_seconds),0),0) AS avg_handle_seconds
        FROM visitors v LEFT JOIN outlets o ON v.outlet_id = o.id LEFT JOIN services sv ON v.service_id = sv.id LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
        WHERE $visitorClause GROUP BY v.agent_id
    ) vs ON vs.agent_id = u.id
    LEFT JOIN (
        SELECT s.agent_id, COUNT(*) AS sales_transactions, " . sisPaidPositiveSum('s') . " AS paid_revenue, " . sisPendingPositiveSum('s') . " AS pending_amount
        FROM sales s LEFT JOIN visitors v ON s.visitor_id = v.id LEFT JOIN outlets o ON s.outlet_id = o.id LEFT JOIN services sv ON v.service_id = sv.id
        WHERE $salesClause GROUP BY s.agent_id
    ) ss ON ss.agent_id = u.id
    WHERE u.role = 'agent' AND u.is_active = 1
    ORDER BY paid_revenue DESC, total_visits DESC, u.full_name
");

$recent = mysqli_query($conn, "
    SELECT s.payment_date, s.invoice_number, s.amount, s.payment_status, v.name AS customer, v.contact, sv.name AS service_name, u.full_name AS agent_name, o.name AS showroom
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    WHERE $salesClause
    ORDER BY s.payment_date DESC, s.id DESC
    LIMIT 250
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>General Report - Comium SIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'General Report', 'Overall showroom, sales, support and agent performance'); ?>
<div class="sis-report-page">
    <div class="sis-module-hero mb-3">
        <div><span class="sis-eyebrow"><i class="bi bi-file-earmark-bar-graph"></i> General Report</span><h2>Complete system overview</h2><p>One page for management to see visitors, sales, revenue, complaints, agents and showrooms.</p></div>
        <div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-danger" href="reports.php"><i class="bi bi-graph-up"></i> Advanced KPI Report</a><a class="btn btn-outline-secondary" href="sales.php"><i class="bi bi-receipt"></i> Sales Report</a><button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div>
    </div>

    <form method="GET" class="card sis-filter-card mb-3"><div class="card-body"><div class="row g-3 align-items-end">
        <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?php echo e($date_from); ?>"></div>
        <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?php echo e($date_to); ?>"></div>
        <div class="col-md-3"><label class="form-label">Showroom</label><select name="outlet_id" class="form-select"><option value="">All Showrooms</option><?php while($o=mysqli_fetch_assoc($outlets_result)): ?><option value="<?php echo (int)$o['id']; ?>" <?php echo $filter_outlet===(int)$o['id']?'selected':''; ?>><?php echo e($o['name']); ?></option><?php endwhile; ?></select></div>
        <div class="col-md-3"><label class="form-label">Agent</label><select name="agent_id" class="form-select"><option value="">All Agents</option><?php while($a=mysqli_fetch_assoc($agents_result)): ?><option value="<?php echo (int)$a['id']; ?>" <?php echo $filter_agent===(int)$a['id']?'selected':''; ?>><?php echo e($a['full_name']); ?></option><?php endwhile; ?></select></div>
        <div class="col-md-2"><button class="btn btn-danger w-100" type="submit"><i class="bi bi-funnel"></i> Apply</button></div>
        <div class="col-md-10"><input type="search" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="Search customer, phone, invoice, agent, showroom, ticket..."></div>
        <div class="col-md-2"><a class="btn btn-light border w-100" href="general_report.php">Reset</a></div>
    </div></div></form>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="sis-kpi-card"><span>Total Visits</span><strong><?php echo grInt($visitorSummary['total_visits'] ?? 0); ?></strong><small><?php echo grInt($visitorSummary['completed_visits'] ?? 0); ?> completed</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card success"><span>Collected Revenue</span><strong><?php echo grMoney($salesSummary['paid_revenue'] ?? 0); ?></strong><small><?php echo grInt($salesSummary['paid_count'] ?? 0); ?> paid transactions</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card warning"><span>Pending + Unpaid</span><strong><?php echo grMoney(($salesSummary['pending_amount'] ?? 0)+($salesSummary['unpaid_amount'] ?? 0)); ?></strong><small>Pending <?php echo grMoney($salesSummary['pending_amount'] ?? 0); ?></small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card danger"><span>Support Tickets</span><strong><?php echo grInt($ticketSummary['total_tickets'] ?? 0); ?></strong><small><?php echo grInt($ticketSummary['open_tickets'] ?? 0); ?> open</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card"><span>Sales Transactions</span><strong><?php echo grInt($salesSummary['sales_transactions'] ?? 0); ?></strong><small>All payment statuses</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card"><span>Withdrawals</span><strong><?php echo grMoney($salesSummary['withdrawals'] ?? 0); ?></strong><small>Money paid out</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card"><span>Net Cash</span><strong><?php echo grMoney($salesSummary['net_cash'] ?? 0); ?></strong><small>Paid income less withdrawals</small></div></div>
        <div class="col-md-3"><div class="sis-kpi-card"><span>Avg Handle Time</span><strong><?php echo renderHandleTimeCell($visitorSummary['avg_handle_seconds'] ?? 0); ?></strong><small>Customer handling</small></div></div>
    </div>

    <div class="card sis-report-card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="mb-0"><i class="bi bi-building"></i> Showroom Performance</h5><div class="sis-table-search"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="Search table..." data-sis-table-search></div></div><div class="card-body"><div class="table-responsive sis-table-shell"><table class="table table-hover sis-searchable-table" data-sis-searchable-table><thead><tr><th>Showroom</th><th>Region</th><th>Visits</th><th>Completed</th><th>Sales</th><th>Revenue</th><th>Withdrawals</th><th>Pending</th><th>Unpaid</th></tr></thead><tbody><?php if($byOutlet && mysqli_num_rows($byOutlet)>0): while($r=mysqli_fetch_assoc($byOutlet)): ?><tr><td><strong><?php echo e($r['showroom']); ?></strong></td><td><?php echo e($r['region'] ?: '-'); ?></td><td><?php echo grInt($r['total_visits']); ?></td><td><?php echo grInt($r['completed_visits']); ?></td><td><?php echo grInt($r['sales_transactions']); ?></td><td class="text-success fw-semibold"><?php echo grMoney($r['paid_revenue']); ?></td><td class="text-danger fw-semibold"><?php echo grMoney($r['withdrawals']); ?></td><td><?php echo grMoney($r['pending_amount']); ?></td><td><?php echo grMoney($r['unpaid_amount']); ?></td></tr><?php endwhile; else: ?><tr><td colspan="9" class="text-center text-muted py-4">No showroom data found.</td></tr><?php endif; ?></tbody></table></div></div></div>

    <div class="row g-3">
        <div class="col-lg-6"><div class="card sis-report-card h-100"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-person-badge"></i> Agent Performance</h5></div><div class="card-body"><div class="table-responsive sis-table-shell"><table class="table table-hover sis-searchable-table" data-sis-searchable-table><thead><tr><th>Agent</th><th>Showroom</th><th>Visits</th><th>Sales</th><th>Revenue</th><th>Pending</th><th>Avg Handle</th></tr></thead><tbody><?php if($byAgent && mysqli_num_rows($byAgent)>0): while($a=mysqli_fetch_assoc($byAgent)): ?><tr><td><strong><?php echo e($a['agent_name']); ?></strong></td><td><?php echo e($a['showroom'] ?: '-'); ?></td><td><?php echo grInt($a['total_visits']); ?></td><td><?php echo grInt($a['sales_transactions']); ?></td><td class="text-success fw-semibold"><?php echo grMoney($a['paid_revenue']); ?></td><td><?php echo grMoney($a['pending_amount']); ?></td><td><?php echo renderHandleTimeCell($a['avg_handle_seconds'] ?? 0); ?></td></tr><?php endwhile; else: ?><tr><td colspan="7" class="text-center text-muted py-4">No agent data found.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
        <div class="col-lg-6"><div class="card sis-report-card h-100"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Sales</h5></div><div class="card-body"><div class="table-responsive sis-table-shell"><table class="table table-hover sis-searchable-table" data-sis-searchable-table><thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Service</th><th>Agent</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php if($recent && mysqli_num_rows($recent)>0): while($r=mysqli_fetch_assoc($recent)): ?><tr><td><?php echo date('d/m/Y H:i', strtotime($r['payment_date'])); ?></td><td><?php echo e($r['invoice_number'] ?: '—'); ?></td><td><?php echo e($r['customer'] ?: '—'); ?><small class="d-block text-muted"><?php echo e($r['contact'] ?: ''); ?></small></td><td><?php echo e($r['service_name'] ?: '—'); ?></td><td><?php echo e($r['agent_name'] ?: '—'); ?></td><td class="fw-semibold"><?php echo grMoney($r['amount']); ?></td><td><span class="badge bg-<?php echo $r['payment_status']==='Paid'?'success':($r['payment_status']==='Pending'?'warning text-dark':'danger'); ?>"><?php echo e($r['payment_status']); ?></span></td></tr><?php endwhile; else: ?><tr><td colspan="7" class="text-center text-muted py-4">No recent sales found.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<script>(function(){document.querySelectorAll('[data-sis-table-search]').forEach(function(input){input.addEventListener('input',function(){var card=input.closest('.card');var q=input.value.toLowerCase().trim();(card?card:document).querySelectorAll('[data-sis-searchable-table] tbody tr').forEach(function(row){row.style.display=(!q||row.textContent.toLowerCase().indexOf(q)!==-1)?'':'none';});});});})();</script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
