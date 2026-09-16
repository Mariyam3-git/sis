<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/shift_helpers.php';
requireRole('admin');
sisEnsureAdvancedShiftTables($conn);

$approver_id = (int)($_SESSION['user_id'] ?? 0);
$outlet_id = null; // Admin can approve requests even when the Supervisor is not around.
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decide_request'])) {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $request_id = (int)($_POST['request_id'] ?? 0);
        $decision = ($_POST['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
        $note = trim((string)($_POST['supervisor_note'] ?? ''));

        $stmt = mysqli_prepare($conn, "SELECT sr.*, sa.shift_date, u.outlet_id FROM shift_requests sr LEFT JOIN shift_assignments sa ON sr.assignment_id=sa.id JOIN users u ON sr.agent_id=u.id WHERE sr.id=? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $request_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $req = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($req && $req['status'] === 'pending') {
                $now = date('Y-m-d H:i:s');
                $upd = mysqli_prepare($conn, "UPDATE shift_requests SET status=?, supervisor_id=?, supervisor_note=?, decided_at=? WHERE id=?");
                if ($upd) {
                    mysqli_stmt_bind_param($upd, 'sissi', $decision, $approver_id, $note, $now, $request_id);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }
                if ($decision === 'approved' && $req['request_type'] === 'early_end') {
                    sisCloseAgentShiftAfterApproval($conn, (int)$req['agent_id'], $req['shift_date'] ?: date('Y-m-d'), $now);
                }
                if (function_exists('sisCreateNotification')) {
                    $title = $decision === 'approved' ? 'Shift request approved' : 'Shift request rejected';
                    if ($req['request_type'] === 'login_access') {
                        $title = $decision === 'approved' ? 'Login access approved' : 'Login access rejected';
                    }
                    sisCreateNotification($conn, (int)$req['agent_id'], null, null, $title, 'Admin decision: ' . ucfirst($decision) . '. Note: ' . ($note ?: 'No note'), 'agent/my_shift.php', 'shift_request', $request_id);
                }
                auditAction($conn, strtoupper($decision) . '_SHIFT_REQUEST', $request_id, $note);
                if ($req['request_type'] === 'login_access') {
                    $message = $decision === 'approved' ? 'Login access approved. The agent can sign in now.' : 'Login access rejected.';
                } else {
                    $message = $decision === 'approved' ? 'Request approved. Agent shift has been ended where applicable.' : 'Request rejected.';
                }
                $message_type = $decision === 'approved' ? 'success' : 'warning';
            } else {
                $message = 'Request not found or already processed.';
                $message_type = 'danger';
            }
        }
    }
}

// Refresh after POST guard 2026-07-01
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Shift request updated.', $message_type);
    redirectTo('shift_requests.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

$scope = $outlet_id ? ' AND u.outlet_id = ' . (int)$outlet_id : '';
$pending = mysqli_query($conn, "SELECT sr.*, u.full_name AS agent_name, o.name AS outlet_name, sa.shift_date, sa.shift_start, sa.shift_end, sa.shift_type
    FROM shift_requests sr
    JOIN users u ON sr.agent_id = u.id
    LEFT JOIN outlets o ON u.outlet_id = o.id
    LEFT JOIN shift_assignments sa ON sr.assignment_id = sa.id
    WHERE sr.status = 'pending' $scope
    ORDER BY sr.requested_at ASC");
$recent = mysqli_query($conn, "SELECT sr.*, u.full_name AS agent_name, o.name AS outlet_name, sa.shift_date, sa.shift_start, sa.shift_end, sa.shift_type
    FROM shift_requests sr
    JOIN users u ON sr.agent_id = u.id
    LEFT JOIN outlets o ON u.outlet_id = o.id
    LEFT JOIN shift_assignments sa ON sr.assignment_id = sa.id
    WHERE sr.status <> 'pending' $scope
    ORDER BY sr.requested_at DESC LIMIT 50");
$overtime = mysqli_query($conn, "SELECT sr.*, u.full_name AS agent_name, o.name AS outlet_name, sa.shift_date, sa.shift_start, sa.shift_end, sa.shift_type
    FROM shift_requests sr
    JOIN users u ON sr.agent_id = u.id
    LEFT JOIN outlets o ON u.outlet_id = o.id
    LEFT JOIN shift_assignments sa ON sr.assignment_id = sa.id
    WHERE sr.request_type = 'overtime' $scope
    ORDER BY sr.requested_at DESC LIMIT 30");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Shift Requests - SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'Shift Requests', 'Approve agent early shift-end, login-access, and overtime requests when Supervisors are not available'); ?>
<div class="container-fluid">
    <?php if($message): ?><div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-hourglass-split"></i> Pending Shift & Login Requests</strong><a href="shift_allocation.php" class="btn btn-sm btn-outline-primary">Shift Allocation</a></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Agent</th><th>Type</th><th>Shift</th><th>Requested</th><th>Reason</th><th>Admin Decision</th></tr></thead><tbody>
            <?php if($pending && mysqli_num_rows($pending)>0): while($r=mysqli_fetch_assoc($pending)): ?>
                <tr>
                    <td><strong><?php echo e($r['agent_name']); ?></strong><small class="d-block text-muted"><?php echo e($r['outlet_name'] ?? 'No outlet'); ?></small></td>
                    <td><span class="badge bg-<?php echo $r['request_type']==='login_access'?'primary':($r['request_type']==='overtime'?'info':'warning'); ?>"><?php echo e(ucwords(str_replace('_',' ', $r['request_type']))); ?></span></td>
                    <td><?php echo !empty($r['shift_date']) ? e(date('d/m/Y', strtotime($r['shift_date']))) : '—'; ?><small class="d-block text-muted"><?php echo !empty($r['shift_type']) ? e(sisShiftLabel($r['shift_type'])) . ' • ' . e(substr($r['shift_start'],0,5)) . '-' . e(substr($r['shift_end'],0,5)) : 'No current shift'; ?></small></td>
                    <td><?php echo e(date('d/m/Y H:i', strtotime($r['requested_at']))); ?></td>
                    <td><?php echo e($r['reason']); ?></td>
                    <td>
                        <form method="post" class="row g-2 align-items-center">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="decide_request" value="1"><input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                            <div class="col-12"><input class="form-control form-control-sm" name="supervisor_note" required placeholder="Write approval/rejection reason"></div>
                            <div class="col-auto"><button name="decision" value="approved" class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Approve</button></div>
                            <div class="col-auto"><button name="decision" value="rejected" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Reject</button></div>
                        </form>
                    </td>
                </tr>
            <?php endwhile; else: ?><tr><td colspan="6" class="text-center text-muted py-4">No pending requests.</td></tr><?php endif; ?>
            </tbody></table></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong><i class="bi bi-clock-history"></i> Overtime Notifications</strong></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Agent</th><th>Shift Date</th><th>Overtime</th><th>Message</th><th>Notified At</th></tr></thead><tbody>
            <?php if($overtime && mysqli_num_rows($overtime)>0): while($r=mysqli_fetch_assoc($overtime)): ?>
                <tr><td><strong><?php echo e($r['agent_name']); ?></strong><small class="d-block text-muted"><?php echo e($r['outlet_name'] ?? 'No outlet'); ?></small></td><td><?php echo e(date('d/m/Y', strtotime($r['shift_date'] ?? $r['requested_at']))); ?></td><td><?php echo e(floor(((int)$r['overtime_minutes'])/60)); ?>h <?php echo e(((int)$r['overtime_minutes'])%60); ?>m</td><td><?php echo e($r['reason']); ?></td><td><?php echo e(date('d/m/Y H:i', strtotime($r['requested_at']))); ?></td></tr>
            <?php endwhile; else: ?><tr><td colspan="5" class="text-center text-muted py-4">No overtime notifications yet.</td></tr><?php endif; ?>
            </tbody></table></div></div>
    </div>

    <div class="card">
        <div class="card-header"><strong><i class="bi bi-list-check"></i> Recent Decisions</strong></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Agent</th><th>Type</th><th>Status</th><th>Reason</th><th>Approver Note</th><th>Decided</th></tr></thead><tbody>
            <?php if($recent && mysqli_num_rows($recent)>0): while($r=mysqli_fetch_assoc($recent)): ?>
                <tr><td><strong><?php echo e($r['agent_name']); ?></strong><small class="d-block text-muted"><?php echo e($r['outlet_name'] ?? 'No outlet'); ?></small></td><td><?php echo e(str_replace('_',' ',ucfirst($r['request_type']))); ?></td><td><span class="badge bg-<?php echo $r['status']==='approved'?'success':($r['status']==='rejected'?'danger':'info'); ?>"><?php echo e(ucfirst($r['status'])); ?></span></td><td><?php echo e($r['reason']); ?></td><td><?php echo e($r['supervisor_note'] ?: '—'); ?></td><td><?php echo $r['decided_at'] ? e(date('d/m/Y H:i', strtotime($r['decided_at']))) : '—'; ?></td></tr>
            <?php endwhile; else: ?><tr><td colspan="6" class="text-center text-muted py-4">No processed requests yet.</td></tr><?php endif; ?>
            </tbody></table></div></div>
    </div>
</div>

<?php $pendingCount = ($pending ? mysqli_num_rows($pending) : 0); if ($pending && mysqli_num_rows($pending) > 0) mysqli_data_seek($pending, 0); ?>
<?php if($pendingCount > 0): ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000; margin-top: 78px;">
    <div id="shiftRequestToast" class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi bi-bell-fill text-danger me-2"></i>
            <strong class="me-auto">Admin Shift Notification</strong>
            <small>now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            There are <?php echo (int)$pendingCount; ?> pending agent shift/login request(s) waiting for approval.
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body></html>
