<?php
// logout.php - Logout Handler
require_once 'config.php';
require_once __DIR__ . '/include/shift_helpers.php';

if (isAuthenticated()) {
    $now = date('Y-m-d H:i:s');
    $uid = (int)$_SESSION['user_id'];

    $stmt = mysqli_prepare($conn, 'UPDATE users SET last_logout = ? WHERE id = ?');
    if ($stmt) { mysqli_stmt_bind_param($stmt, 'si', $now, $uid); mysqli_stmt_execute($stmt); }

    if (($_SESSION['role'] ?? '') === 'agent' && empty($_GET['timeout'])) {
        sisEnsureAdvancedShiftTables($conn);
        $today = date('Y-m-d');
        $assignment = sisGetAgentAssignmentForDate($conn, $uid, $today);
        if ($assignment && empty($_GET['force'])) {
            [, $end_ts] = sisShiftWindowTimestamps($assignment);
            // Early logout must be approved by the Supervisor. A pending request alone is not enough.
            if (time() < $end_ts) {
                $approvedEarlyEnd = false;
                $reqStmt = mysqli_prepare($conn, "SELECT status FROM shift_requests WHERE assignment_id = ? AND request_type = 'early_end' ORDER BY id DESC LIMIT 1");
                if ($reqStmt) {
                    $assignmentId = (int)$assignment['id'];
                    mysqli_stmt_bind_param($reqStmt, 'i', $assignmentId);
                    mysqli_stmt_execute($reqStmt);
                    $reqRes = mysqli_stmt_get_result($reqStmt);
                    $reqRow = $reqRes ? mysqli_fetch_assoc($reqRes) : null;
                    mysqli_stmt_close($reqStmt);
                    $approvedEarlyEnd = ($reqRow && $reqRow['status'] === 'approved');
                }
                if (!$approvedEarlyEnd) {
                    redirectTo(appPath('agent/shift_logout.php'));
                }
            }
        }
        $shift = mysqli_prepare($conn, 'UPDATE agent_shifts SET logout_time = ? WHERE agent_id = ? AND shift_date = ? AND logout_time IS NULL');
        if ($shift) { mysqli_stmt_bind_param($shift, 'sis', $now, $uid, $today); mysqli_stmt_execute($shift); }
    }

    auditAction($conn, 'LOGOUT', $uid);

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

$loginTarget = 'login.php' . (!empty($_GET['timeout']) ? '?timeout=1' : '');
redirectTo(appPath($loginTarget));
?>
