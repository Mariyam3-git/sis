<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/shift_helpers.php';
requireRole('agent');
sisEnsureAdvancedShiftTables($conn);

$agent_id = (int)($_SESSION['user_id'] ?? 0);
$outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);
$today = date('Y-m-d');

// Load the current assignment (read-only) for display.
// NOTE: Auto-login on page load has been removed. Login is only recorded
// through the normal Start Shift / Add Customer flow.
$assignment = sisGetRelevantAgentAssignment($conn, $agent_id);

$scheduleFrom = $assignment && $assignment['shift_date'] < $today ? $assignment['shift_date'] : $today;
$upcoming = [];
$stmt = mysqli_prepare($conn, "SELECT sa.*, o.name AS outlet_name, ash.login_time, ash.logout_time FROM shift_assignments sa LEFT JOIN users u ON sa.agent_id=u.id LEFT JOIN outlets o ON u.outlet_id=o.id " . sisAgentShiftAggregateJoin('ash') . " WHERE sa.agent_id=? AND sa.shift_date >= ? ORDER BY sa.shift_date, sa.shift_start LIMIT 30");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'is', $agent_id, $scheduleFrom);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $upcoming[] = $row;
}
$requests = [];
$stmt = mysqli_prepare($conn, "SELECT sr.*, sa.shift_date, sa.shift_start, sa.shift_end FROM shift_requests sr LEFT JOIN shift_assignments sa ON sr.assignment_id=sa.id WHERE sr.agent_id=? ORDER BY sr.requested_at DESC LIMIT 20");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $agent_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $requests[] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Shift - SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
    <style>
        .login-time-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #e7f5ff;
            color: #0c63e4;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }
        .login-time-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        .login-time-badge.missed {
            background: #f8d7da;
            color: #721c24;
        }
        .login-time-label {
            display: block;
            font-size: 11px;
            color: #6c757d;
            margin-top: 3px;
        }
    </style>
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('agent', 'My Shift', 'View your assigned shift, login window, and requests'); ?>

<div class="container-fluid">
    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><strong><i class="bi bi-calendar-check"></i> Current Work Shift</strong></div>
                <div class="card-body">
                    <?php if($assignment): [$start_ts,$end_ts] = sisShiftWindowTimestamps($assignment); ?>
                        <div class="mb-3"><span class="badge bg-primary"><?php echo e(sisShiftLabel($assignment['shift_type'])); ?></span></div>
                        <h4><?php echo e(date('D, d M Y', strtotime($assignment['shift_date']))); ?></h4>
                        <p class="mb-1"><strong>Shift Time:</strong> <?php echo e(date('H:i', $start_ts)); ?> - <?php echo e(date('H:i', $end_ts)); ?></p>
                        <p class="mb-1"><strong>Login Allowed:</strong> <?php echo e(date('H:i', $start_ts - 3600)); ?> - <?php echo e(date('H:i', $end_ts + 3600)); ?></p>
                        <p class="mb-1"><strong>Outlet:</strong> <?php echo e($assignment['outlet_name'] ?: 'N/A'); ?></p>

                        <?php
                        $current_login = $assignment['login_time'] ?? null;
                        $current_logout = $assignment['logout_time'] ?? null;
                        ?>
                        <?php if ($current_login): ?>
                            <p class="mb-1">
                                <strong><i class="bi bi-box-arrow-in-right text-success"></i> Logged In:</strong>
                                <span class="login-time-badge">
                                    <?php echo e(date('H:i:s', strtotime($current_login))); ?>
                                </span>
                            </p>
                            <?php if ($current_logout): ?>
                                <p class="mb-1">
                                    <strong><i class="bi bi-box-arrow-right text-danger"></i> Logged Out:</strong>
                                    <span class="login-time-badge missed">
                                        <?php echo e(date('H:i:s', strtotime($current_logout))); ?>
                                    </span>
                                </p>
                            <?php else: ?>
                                <p class="mb-1"><span class="badge bg-success"><i class="bi bi-broadcast"></i> Currently Active</span></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="mb-1">
                                <strong>Login:</strong>
                                <span class="login-time-badge pending">
                                    <i class="bi bi-hourglass-split"></i> Not logged in yet
                                </span>
                            </p>
                        <?php endif; ?>

                        <p class="text-muted mb-0 mt-2"><?php echo e($assignment['notes'] ?: 'No notes for this shift.'); ?></p>
                    <?php else: ?>
                        <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> No shift is allocated for the current work period. If managed scheduling is active, request Supervisor approval before starting.</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light"><a class="btn btn-outline-danger" href="shift_logout.php"><i class="bi bi-box-arrow-right"></i> End Shift / Request Early End</a></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><strong><i class="bi bi-calendar3"></i> Upcoming Schedule</strong></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Shift</th>
                            <th>Time</th>
                            <th>Login</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if($upcoming): foreach($upcoming as $row): 
                        $badge = sisShiftStatusBadge($row); 
                        [$st,$en] = sisShiftWindowTimestamps($row);
                        $row_login = $row['login_time'] ?? null;
                        $row_logout = $row['logout_time'] ?? null;
                    ?>
                        <tr>
                            <td><?php echo e(date('d/m/Y', strtotime($row['shift_date']))); ?></td>
                            <td><?php echo e(sisShiftLabel($row['shift_type'])); ?></td>
                            <td><?php echo e(date('H:i',$st)); ?> - <?php echo e(date('H:i',$en)); ?></td>
                            <td>
                                <?php if ($row_login): ?>
                                    <span class="login-time-badge">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                        <?php echo e(date('H:i', strtotime($row_login))); ?>
                                    </span>
                                    <?php if ($row_logout): ?>
                                        <span class="login-time-label">
                                            Out: <?php echo e(date('H:i', strtotime($row_logout))); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="login-time-badge pending">
                                        <i class="bi bi-dash-circle"></i> No login
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?php echo e($badge[1]); ?>"><?php echo e($badge[0]); ?></span></td>
                            <td><?php echo e($row['notes'] ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; else: ?><tr><td colspan="6" class="text-center text-muted py-4">No upcoming shifts.</td></tr><?php endif; ?>
                    </tbody></table></div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong><i class="bi bi-bell"></i> My Shift Requests & Overtime Notices</strong></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date</th><th>Type</th><th>Reason</th><th>Status</th><th>Supervisor Note</th></tr></thead><tbody>
            <?php if($requests): foreach($requests as $r): ?>
                <tr><td><?php echo e(date('d/m/Y H:i', strtotime($r['requested_at']))); ?></td><td><?php echo e(str_replace('_',' ', ucfirst($r['request_type']))); ?></td><td><?php echo e($r['reason'] ?: '—'); ?></td><td><span class="badge bg-<?php echo $r['status']==='approved'?'success':($r['status']==='rejected'?'danger':($r['status']==='notified'?'info':'warning')); ?>"><?php echo e(ucfirst($r['status'])); ?></span></td><td><?php echo e($r['supervisor_note'] ?: '—'); ?></td></tr>
            <?php endforeach; else: ?><tr><td colspan="5" class="text-center text-muted py-4">No requests yet.</td></tr><?php endif; ?>
            </tbody></table></div></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body></html>