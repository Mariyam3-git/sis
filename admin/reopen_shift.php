<?php
require_once '../config.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo(appPath('admin/sales.php'));
}

if (!verifyCsrf()) {
    $_SESSION['shift_notice'] = ['danger', 'Security check failed. Please try again.'];
    redirectTo(appPath('admin/sales.php'));
}

$shift_id = (int)($_POST['shift_id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Reopened by admin after mistaken end shift');

if ($shift_id <= 0) {
    $_SESSION['shift_notice'] = ['danger', 'Invalid shift selected.'];
    redirectTo(appPath('admin/sales.php'));
}

if (reopenAgentShift($conn, $shift_id, $reason)) {
    $_SESSION['shift_notice'] = ['success', 'Shift reopened. The agent can log in again and continue the same shift.'];
} else {
    $_SESSION['shift_notice'] = ['warning', 'Shift could not be reopened. It may already be active or it is not today\'s shift.'];
}

redirectTo(appPath('admin/sales.php'));
