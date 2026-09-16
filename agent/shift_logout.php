<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/shift_helpers.php';
requireRole('agent');
sisEnsureAdvancedShiftTables($conn);

$agent_id = (int)($_SESSION['user_id'] ?? 0);
$today = date('Y-m-d');
$assignment = sisGetRelevantAgentAssignment($conn, $agent_id);
$shift_work_date = $assignment['shift_date'] ?? $today;
$active = getAgentActiveShift($conn, $agent_id, $shift_work_date);
$message = '';
$message_type = 'info';

if (!$assignment || !$active) {
    redirectTo(appPath('logout.php?force=1'));
}

[$start_ts, $end_ts] = sisShiftWindowTimestamps($assignment);
$is_early = time() < $end_ts;
$pending = sisGetPendingShiftRequest($conn, (int)$assignment['id'], 'early_end');

if (!$is_early) {
    redirectTo(appPath('logout.php?force=1'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_early_end'])) {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            $message = 'Please write the reason for ending your shift early.';
            $message_type = 'danger';
        } elseif ($pending) {
            $message = 'You already have a pending early-end request for this shift.';
            $message_type = 'warning';
        } else {
            sisCreateShiftRequest($conn, (int)$assignment['id'], $agent_id, 'early_end', $reason, 'pending');
            $message = 'Your request has been sent to your Supervisor. Stay logged in until it is approved or continue working.';
            $message_type = 'success';
            $pending = sisGetPendingShiftRequest($conn, (int)$assignment['id'], 'early_end');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request Early Shift End - SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('agent', 'End Shift Request', 'Supervisor approval is required before your scheduled end time'); ?>

<div class="container-fluid">
    <?php if($message): ?><div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><strong><i class="bi bi-exclamation-triangle"></i> You are trying to end your shift early</strong></div>
                <div class="card-body">
                    <p>Your scheduled shift ends at <strong><?php echo e(date('H:i', $end_ts)); ?></strong>. Because it is not yet time, the system will send an early-end request to your Supervisor.</p>
                    <?php if($pending): ?>
                        <div class="alert alert-warning"><i class="bi bi-hourglass-split"></i> Request pending. Your Supervisor must approve before your shift can be closed early.</div>
                        <a href="dashboard.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Continue Working</a>
                    <?php else: ?>
                        <form method="post">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="request_early_end" value="1">
                            <div class="mb-3"><label class="form-label">Reason for early end <span class="text-danger">*</span></label><textarea class="form-control" name="reason" rows="4" required placeholder="Example: Emergency, health issue, transport issue, supervisor instruction..."></textarea></div>
                            <div class="d-flex gap-2"><button class="btn btn-danger"><i class="bi bi-send"></i> Send Request</button><a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a></div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body></html>
