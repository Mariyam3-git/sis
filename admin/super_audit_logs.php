<?php
require_once '../config.php';
requireSuperAdmin();

$search = trim($_GET['search'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(s.action_type LIKE ? OR s.details LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR s.ip_address LIKE ?)';
    $like = '%' . $search . '%';
    for ($i=0; $i<5; $i++) { $params[] = $like; $types .= 's'; }
}
if ($date_from !== '') { $where[] = 'DATE(s.timestamp) >= ?'; $params[] = $date_from; $types .= 's'; }
if ($date_to !== '') { $where[] = 'DATE(s.timestamp) <= ?'; $params[] = $date_to; $types .= 's'; }
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

function stmtFetchAll(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    return $rows;
}

$countRows = stmtFetchAll($conn, "SELECT COUNT(*) AS total FROM super_audit_log s LEFT JOIN users u ON s.user_id = u.id $where_sql", $types, $params);
$total = (int)($countRows[0]['total'] ?? 0);
$total_pages = max(1, (int)ceil($total / $limit));

$listParams = $params;
$listTypes = $types . 'ii';
$listParams[] = $offset;
$listParams[] = $limit;
$logs = stmtFetchAll($conn, "SELECT s.*, u.username, u.full_name, u.role FROM super_audit_log s LEFT JOIN users u ON s.user_id = u.id $where_sql ORDER BY s.timestamp DESC LIMIT ?, ?", $listTypes, $listParams);

$stats = stmtFetchAll($conn, "SELECT COUNT(*) total_logs, COUNT(DISTINCT user_id) super_users, COUNT(DISTINCT DATE(timestamp)) days_covered FROM super_audit_log");
$stat = $stats[0] ?? ['total_logs'=>0,'super_users'=>0,'days_covered'=>0];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Super Audit Logs - SIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
<main class="main">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div><h3 class="mb-1"><i class="bi bi-shield-lock"></i> Owner Audit Logs</h3><p class="text-muted mb-0">Owner-level actions are recorded here and visible only to the system owner.</p></div>
  <div><a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-speedometer2"></i> Admin Dashboard</a> <a href="../logout.php?confirm=1" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to logout?')"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card stat"><div class="card-body"><small class="text-muted">Total Super Logs</small><h3><?php echo number_format((int)$stat['total_logs']); ?></h3></div></div></div>
  <div class="col-md-4"><div class="card stat"><div class="card-body"><small class="text-muted">Super Users Active</small><h3><?php echo number_format((int)$stat['super_users']); ?></h3></div></div></div>
  <div class="col-md-4"><div class="card stat"><div class="card-body"><small class="text-muted">Days Covered</small><h3><?php echo number_format((int)$stat['days_covered']); ?></h3></div></div></div>
</div>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="get"><div class="col-md-5"><input class="form-control" name="search" placeholder="Search action, details, user, IP" value="<?php echo e($search); ?>"></div><div class="col-md-2"><input type="date" name="date_from" class="form-control sis-w-150" value="<?php echo e($date_from); ?>"></div><div class="col-md-2"><input type="date" name="date_to" class="form-control sis-w-150" value="<?php echo e($date_to); ?>"></div><div class="col-md-3 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i> Filter</button></div></form></div></div>
<div class="card"><div class="card-header"><strong>Super Activity</strong> <span class="badge bg-secondary"><?php echo number_format($total); ?> records</span></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php if ($logs): foreach($logs as $log): ?>
<tr><td><?php echo e(date('d/m/Y H:i:s', strtotime($log['timestamp']))); ?></td><td><strong><?php echo e($log['full_name'] ?: $log['username']); ?></strong><br><span class="badge badge-super">System Owner</span></td><td><span class="badge bg-dark"><?php echo e($log['action_type']); ?></span></td><td><?php echo $log['record_id'] ? '<code>'.(int)$log['record_id'].'</code>' : '<span class="text-muted">—</span>'; ?></td><td><?php echo e($log['details']); ?></td><td><code><?php echo e($log['ip_address']); ?></code></td></tr>
<?php endforeach; else: ?>
<tr><td colspan="6" class="text-center text-muted py-5">No super audit logs found.</td></tr>
<?php endif; ?>
</tbody></table></div></div>
<?php if ($total_pages > 1): ?><nav class="mt-3"><ul class="pagination justify-content-center"><?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?><li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
</main>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body></html>
