<?php
require_once '../config.php';
require_once '../support_helpers.php';
requireRole('admin');
supportEnsureTables($conn);

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $status = supportNormalizeStatus($_POST['status'] ?? 'Open');
        $resolved_at = in_array($status, ['Resolved', 'Closed'], true) ? date('Y-m-d H:i:s') : null;
        $stmt = mysqli_prepare($conn, 'UPDATE support_tickets SET status=?, resolved_at=? WHERE id=?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssi', $status, $resolved_at, $ticket_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Support ticket status updated.';
                logAction($conn, 'UPDATE_SUPPORT_TICKET_STATUS', $ticket_id);
            } else {
                $message = 'Could not update status: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
        }
    }
}

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
// Refresh after POST guard 2026-07-01
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Status updated.', $message_type);
    redirectTo('support_reports.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

$status_filter = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = ["DATE(st.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];
$types = 'ss';
if (in_array($status_filter, ['Open','In Progress','Resolved','Closed'], true)) {
    $where[] = 'st.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}
if (in_array($role_filter, ['agent','supervisor'], true)) {
    $where[] = 'st.submitted_role = ?';
    $params[] = $role_filter;
    $types .= 's';
}
if ($search !== '') {
    $where[] = '(st.ticket_no LIKE ? OR st.customer_name LIKE ? OR st.customer_phone LIKE ? OR st.subject LIKE ? OR st.description LIKE ?)';
    $like = '%' . $search . '%';
    for ($i=0; $i<5; $i++) $params[] = $like;
    $types .= 'sssss';
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT st.*, u.full_name AS staff_name, o.name AS outlet_name
        FROM support_tickets st
        LEFT JOIN users u ON st.submitted_by = u.id
        LEFT JOIN outlets o ON st.outlet_id = o.id
        WHERE $whereSql
        ORDER BY st.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
$tickets = [];
if ($stmt) {
    $bind = [$stmt, $types];
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    call_user_func_array('mysqli_stmt_bind_param', $bind);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $tickets[] = $row;
}
$valuesMap = supportGetTicketValuesMap($conn, array_column($tickets, 'id'));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=support_complaints_report_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Ticket No', 'Date', 'Status', 'Category', 'Priority', 'Main Reason', 'Sub Reason', 'Customer Name', 'Customer Phone', 'Subject', 'Description', 'Submitted By', 'Role', 'Outlet', 'Dynamic Form Values']);
    foreach ($tickets as $t) {
        $pairs = [];
        foreach (($valuesMap[(int)$t['id']] ?? []) as $v) {
            $pairs[] = $v['label'] . ': ' . $v['value'];
        }
        fputcsv($out, [
            $t['ticket_no'], $t['created_at'], $t['status'], $t['category'], $t['priority'], $t['main_reason'], supportGetReasonLabel($conn, $t['sub_reason_id']),
            $t['customer_name'], $t['customer_phone'], $t['subject'], $t['description'],
            $t['staff_name'], $t['submitted_role'], $t['outlet_name'], implode(' | ', $pairs)
        ]);
    }
    fclose($out);
    exit;
}

$kpis = ['total'=>0,'open'=>0,'progress'=>0,'resolved'=>0,'closed'=>0];
foreach ($tickets as $t) {
    $kpis['total']++;
    if ($t['status'] === 'Open') $kpis['open']++;
    if ($t['status'] === 'In Progress') $kpis['progress']++;
    if ($t['status'] === 'Resolved') $kpis['resolved']++;
    if ($t['status'] === 'Closed') $kpis['closed']++;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support Reports - Comium SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2"><div><h1 class="page-title"><i class="bi bi-headset text-danger"></i> Complaint / Support Reports</h1><p class="text-muted">View Agent and Supervisor complaint/support records.</p></div><a class="btn btn-danger" href="support_forms.php"><i class="bi bi-ui-checks-grid"></i> Manage Form</a></div>
    <?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><?php echo e($message); ?></div><?php endif; ?>

    <div class="row g-3 mb-3"><div class="col-md"><div class="card kpi"><div class="card-body"><small>Total</small><h3><?php echo (int)$kpis['total']; ?></h3></div></div></div><div class="col-md"><div class="card kpi"><div class="card-body"><small>Open</small><h3><?php echo (int)$kpis['open']; ?></h3></div></div></div><div class="col-md"><div class="card kpi"><div class="card-body"><small>In Progress</small><h3><?php echo (int)$kpis['progress']; ?></h3></div></div></div><div class="col-md"><div class="card kpi"><div class="card-body"><small>Resolved</small><h3><?php echo (int)$kpis['resolved']; ?></h3></div></div></div><div class="col-md"><div class="card kpi"><div class="card-body"><small>Closed</small><h3><?php echo (int)$kpis['closed']; ?></h3></div></div></div></div>

    <div class="card mb-3"><div class="card-body"><form method="get" class="row g-3 align-items-end"><div class="col-md-2"><label class="form-label">From</label><input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>"></div><div class="col-md-2"><label class="form-label">To</label><input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>"></div><div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All</option><?php foreach(['Open','In Progress','Resolved','Closed'] as $s): ?><option value="<?php echo e($s); ?>" <?php echo $status_filter===$s?'selected':''; ?>><?php echo e($s); ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Role</label><select class="form-select" name="role"><option value="">All</option><option value="agent" <?php echo $role_filter==='agent'?'selected':''; ?>>Agent</option><option value="supervisor" <?php echo $role_filter==='supervisor'?'selected':''; ?>>Supervisor</option></select></div><div class="col-md-2"><label class="form-label">Search</label><input class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Ticket, phone, subject"></div><div class="col-md-2"><button class="btn btn-primary"><i class="bi bi-search"></i> Filter</button> <a class="btn btn-outline-success" href="?<?php echo e(http_build_query(array_merge($_GET, ['export'=>'csv']))); ?>"><i class="bi bi-file-earmark-excel"></i> Export</a></div></form></div></div>

    <div class="card"><div class="card-body p-0"><div class="table-responsive sis-record-scroll"><table class="table table-hover align-middle mb-0"><thead><tr><th>Ticket</th><th>Date</th><th>Customer</th><th>Main/Sub Reason</th><th>Subject</th><th>Staff</th><th>Status</th><th>Form Values</th></tr></thead><tbody>
        <?php if(!$tickets): ?><tr><td colspan="8" class="text-center text-muted py-4">No complaint/support records found.</td></tr><?php endif; ?>
        <?php foreach($tickets as $t): ?>
            <tr><td><strong><?php echo e($t['ticket_no']); ?></strong><small class="d-block text-muted"><?php echo e($t['category']); ?> • <?php echo e($t['priority']); ?></small></td><td><?php echo e(date('d/m/Y H:i', strtotime($t['created_at']))); ?></td><td><?php echo e($t['customer_name'] ?: '—'); ?><small class="d-block text-muted"><?php echo e($t['customer_phone'] ?: ''); ?></small></td><td><?php echo e($t['main_reason'] ?: '—'); ?><small class="d-block text-muted"><?php echo e(supportGetReasonLabel($conn, $t['sub_reason_id']) ?: '—'); ?></small></td><td><strong><?php echo e($t['subject']); ?></strong><small class="d-block text-muted"><?php echo e($t['description']); ?></small></td><td><?php echo e($t['staff_name'] ?: '—'); ?><small class="d-block text-muted"><?php echo e(ucfirst($t['submitted_role'])); ?> • <?php echo e($t['outlet_name'] ?: '—'); ?></small></td><td><form method="post" class="d-flex gap-1"><?php echo csrfField(); ?><input type="hidden" name="update_status" value="1"><input type="hidden" name="ticket_id" value="<?php echo (int)$t['id']; ?>"><select class="form-select form-select-sm" name="status"><?php foreach(['Open','In Progress','Resolved','Closed'] as $s): ?><option value="<?php echo e($s); ?>" <?php echo $t['status']===$s?'selected':''; ?>><?php echo e($s); ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary">Save</button></form></td><td><?php foreach(($valuesMap[(int)$t['id']] ?? []) as $v): ?><div class="field-value"><span class="text-muted"><?php echo e($v['label']); ?>:</span> <?php echo e($v['value'] ?: '—'); ?></div><?php endforeach; ?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
