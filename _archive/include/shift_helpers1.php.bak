<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/shift_helpers.php';
requireRole('supervisor');

ensureShiftAssignmentsTable($conn);

$is_admin = false;
$role_for_layout = 'supervisor';
$outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);

$message = '';
$message_type = 'info';
$late_grace_minutes = function_exists('sisShiftLateGraceMinutes') ? sisShiftLateGraceMinutes() : 30;
$allowed_reasons = ['', 'Sick', 'Emergency', 'Disaster', 'Personal', 'Transport Issue', 'Late Permission', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_reason') {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!in_array($reason, $allowed_reasons, true)) $reason = 'Other';
        if ($assignment_id > 0) {
            if ($is_admin) {
                $stmt = mysqli_prepare($conn, 'UPDATE shift_assignments SET no_show_reason = ? WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'si', $reason, $assignment_id);
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE shift_assignments sa JOIN users u ON sa.agent_id = u.id SET sa.no_show_reason = ? WHERE sa.id = ? AND u.outlet_id = ?');
                mysqli_stmt_bind_param($stmt, 'sii', $reason, $assignment_id, $outlet_id);
            }
            if ($stmt && mysqli_stmt_execute($stmt)) {
                $message = 'No-show reason updated.';
                $message_type = 'success';
                auditAction($conn, 'UPDATE_SHIFT_REASON', $assignment_id, $reason);
            }
            if ($stmt) mysqli_stmt_close($stmt);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Shift report updated.', $message_type);
    redirectTo('shift_report.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
if (!sisValidDate($date_from)) $date_from = date('Y-m-d', strtotime('-7 days'));
if (!sisValidDate($date_to)) $date_to = date('Y-m-d');
$agent_filter = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$status_filter = trim((string)($_GET['status'] ?? ''));

$scope = (!$is_admin && $outlet_id) ? ' AND u.outlet_id = ' . (int)$outlet_id : '';
$agent_scope = $agent_filter > 0 ? ' AND sa.agent_id = ' . $agent_filter : '';
$attendanceJoin = tableExists($conn, 'agent_shifts') ? sisAgentShiftAggregateJoin('ash') : "LEFT JOIN (SELECT NULL AS agent_id, NULL AS shift_date, NULL AS login_time, NULL AS logout_time, 0 AS shift_session_count, 0 AS total_work_minutes WHERE 1=0) ash ON 1=0";

// Get shift assignments with login data. Late calculation is done in PHP using
// sisShiftLateMinutes() so the grace period stays consistent everywhere.
$rows_result = mysqli_query($conn, "
    SELECT sa.*, u.full_name AS agent_name, u.shift AS default_shift, o.name AS outlet_name,
           ash.login_time, ash.logout_time, ash.total_work_minutes, ash.shift_session_count
    FROM shift_assignments sa
    JOIN users u ON sa.agent_id = u.id
    LEFT JOIN outlets o ON u.outlet_id = o.id
    $attendanceJoin
    WHERE sa.shift_date BETWEEN '$date_from' AND '$date_to' $scope $agent_scope
    ORDER BY sa.shift_date DESC, sa.shift_start ASC, u.full_name ASC
");

$rows = [];
$summary = [
    'scheduled' => 0,
    'present' => 0,
    'on_time' => 0,
    'late' => 0,
    'absent' => 0,
    'expected' => 0,
    'total_late_minutes' => 0,
];
if ($rows_result) {
    while ($row = mysqli_fetch_assoc($rows_result)) {
        $badge = sisShiftStatusBadge($row);
        $key = strtolower($badge[0]);

        // Compute late minutes (only meaningful if there's a login)
        $late_minutes = 0;
        if (!empty($row['login_time'])) {
            $late_minutes = sisShiftLateMinutes($row, $row['login_time']);
        }
        $row['_late_minutes'] = $late_minutes;
        $row['_badge'] = $badge;

        if ($key === 'scheduled') $summary['scheduled']++;
        elseif ($key === 'present') { $summary['present']++; $summary['on_time']++; }
        elseif ($key === 'late') {
            $summary['late']++;
            $summary['total_late_minutes'] += $late_minutes;
        }
        elseif ($key === 'absent') $summary['absent']++;
        else $summary['expected']++;

        if ($status_filter === '' || strtolower($badge[0]) === strtolower($status_filter)) {
            $rows[] = $row;
        }
    }
}

$total_rows = count($rows);
$agents_scope = (!$is_admin && $outlet_id) ? ' AND outlet_id = ' . (int)$outlet_id : '';
$agents_sql = "SELECT id, full_name FROM users WHERE role = 'agent' AND is_active = 1" . $agents_scope . " ORDER BY full_name";
$agents = mysqli_query($conn, $agents_sql);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shift Report - SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
    <style>
        .late-badge {
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .late-minutes {
            font-weight: 800;
            font-size: 13px;
        }
        .on-time-badge {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .early-badge {
            background: #0dcaf0;
            color: #000;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .row-late {
            background: #fff5f5 !important;
        }
        .row-late td:first-child {
            border-left: 4px solid #dc3545;
        }
        .row-absent {
            background: #fff5f5 !important;
            opacity: 0.85;
        }
        .row-absent td:first-child {
            border-left: 4px solid #dc3545;
        }
        .late-expected-time {
            font-size: 11px;
            color: #6c757d;
            display: block;
            margin-top: 3px;
        }
        .stat-late {
            border: 2px solid #dc3545 !important;
        }
        .stat-late h3 {
            color: #dc3545;
        }
    </style>
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart($role_for_layout, 'Shift Report', 'Attendance, late logins, absent agents, and no-show reasons'); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-clipboard-data"></i> Shift Report</h3>
            <p class="text-muted mb-0">Track scheduled, present, late, and absent agents. Late is counted after <strong><?php echo (int)$late_grace_minutes; ?> minute(s)</strong>.</p>
        </div>
        <a href="shift_allocation.php" class="btn btn-primary"><i class="bi bi-calendar-plus"></i> Allocate Shift</a>
    </div>

    <?php if ($message): ?><div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <form method="get" class="card card-body mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label">From</label><input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>"></div>
            <div class="col-md-3"><label class="form-label">Agent</label><select class="form-select" name="agent_id"><option value="0">All agents</option><?php if ($agents): while ($a = mysqli_fetch_assoc($agents)): ?><option value="<?php echo (int)$a['id']; ?>" <?php echo $agent_filter === (int)$a['id'] ? 'selected' : ''; ?>><?php echo e($a['full_name']); ?></option><?php endwhile; endif; ?></select></div>
            <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All</option><?php foreach (['Scheduled','Expected','Present','Late','Absent'] as $st): ?><option <?php echo strtolower($status_filter) === strtolower($st) ? 'selected' : ''; ?>><?php echo e($st); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button><a href="shift_report.php" class="btn btn-outline-secondary">Reset</a><button type="button" onclick="window.print()" class="btn btn-outline-dark"><i class="bi bi-printer"></i></button></div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md"><div class="card"><div class="card-body"><small class="text-muted">Rows</small><h3><?php echo number_format($total_rows); ?></h3></div></div></div>
        <div class="col-md"><div class="card"><div class="card-body"><small class="text-muted">On Time</small><h3 class="text-success"><?php echo number_format($summary['on_time']); ?></h3></div></div></div>
        <div class="col-md"><div class="card stat-late"><div class="card-body"><small class="text-muted">Late</small><h3><?php echo number_format($summary['late']); ?></h3></div></div></div>
        <div class="col-md"><div class="card"><div class="card-body"><small class="text-muted">Absent</small><h3 class="text-danger"><?php echo number_format($summary['absent']); ?></h3></div></div></div>
        <div class="col-md"><div class="card"><div class="card-body"><small class="text-muted">Total Late Minutes</small><h3 class="text-danger"><?php echo number_format($summary['total_late_minutes']); ?> min</h3></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between"><strong><i class="bi bi-table"></i> Shift Attendance Details</strong><span class="badge bg-secondary"><?php echo number_format($total_rows); ?> records</span></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Agent</th>
                    <th>Outlet</th>
                    <th>Shift</th>
                    <th>Scheduled Time</th>
                    <th>Login</th>
                    <th>Logout</th>
                    <th>Status</th>
                    <th>Late Details</th>
                    <th>No-show Reason</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $row): 
                $badge = $row['_badge']; 
                $late_minutes = (int)$row['_late_minutes'];
                $is_late = ($badge[0] === 'Late');
                $is_absent = ($badge[0] === 'Absent');
                $is_early = ($badge[0] === 'Early');
                $is_present = ($badge[0] === 'Present');
                $row_class = '';
                if ($is_late) $row_class = 'row-late';
                elseif ($is_absent) $row_class = 'row-absent';

                // Compute expected time = shift_start + grace minutes
                $expected_ts = strtotime($row['shift_date'] . ' ' . $row['shift_start']) + ($late_grace_minutes * 60);
            ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><?php echo e(date('d/m/Y', strtotime($row['shift_date']))); ?></td>
                    <td><strong><?php echo e($row['agent_name']); ?></strong></td>
                    <td><?php echo e($row['outlet_name'] ?? 'N/A'); ?></td>
                    <td><?php echo e(sisShiftLabel($row['shift_type'])); ?></td>
                    <td><?php echo e(substr($row['shift_start'], 0, 5)); ?> - <?php echo e(substr($row['shift_end'], 0, 5)); ?></td>
                    <td>
                        <?php if ($row['login_time']): ?>
                            <strong><?php echo e(date('H:i:s', strtotime($row['login_time']))); ?></strong>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $row['logout_time'] ? e(date('H:i:s', strtotime($row['logout_time']))) : '<span class="text-muted">—</span>'; ?></td>
                    <td>
                        <span class="badge bg-<?php echo e($badge[1]); ?>"><?php echo e($badge[0]); ?></span>
                    </td>
                    <td>
                        <?php if ($is_late): ?>
                            <span class="late-badge">
                                <i class="bi bi-clock-history"></i> 
                                <span class="late-minutes"><?php echo number_format($late_minutes); ?> min late</span>
                            </span>
                            <span class="late-expected-time">
                                Expected by <?php echo e(date('H:i', $expected_ts)); ?>
                            </span>
                        <?php elseif ($is_present && $row['login_time']): ?>
                            <?php 
                            $login_ts = strtotime($row['login_time']);
                            $start_ts = strtotime($row['shift_date'] . ' ' . $row['shift_start']);
                            $mins_early = 0;
                            if ($login_ts < $start_ts) $mins_early = (int)floor(($start_ts - $login_ts) / 60);
                            ?>
                            <?php if ($mins_early > 0): ?>
                                <span class="early-badge">
                                    <i class="bi bi-sunrise"></i> <?php echo number_format($mins_early); ?> min early
                                </span>
                            <?php else: ?>
                                <span class="on-time-badge">
                                    <i class="bi bi-check-circle"></i> On Time
                                </span>
                            <?php endif; ?>
                        <?php elseif ($is_early): ?>
                            <span class="early-badge">
                                <i class="bi bi-sunrise"></i> Early
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_absent): ?>
                            <form method="post" class="d-flex gap-2">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_reason">
                                <input type="hidden" name="assignment_id" value="<?php echo (int)$row['id']; ?>">
                                <select name="reason" class="form-select form-select-sm">
                                    <?php foreach ($allowed_reasons as $reason): ?><option value="<?php echo e($reason); ?>" <?php echo ($row['no_show_reason'] ?? '') === $reason ? 'selected' : ''; ?>><?php echo $reason === '' ? 'Select reason' : e($reason); ?></option><?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline-primary">Save</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted"><?php echo e($row['no_show_reason'] ?: '—'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No shift records found for this filter.</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>