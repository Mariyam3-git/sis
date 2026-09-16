<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/include/shift_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Background notification polling must not refresh last_activity, otherwise users never become idle.
if (!isAuthenticated() || (function_exists('sisSessionExpired') && sisSessionExpired())) {
    if (isAuthenticated() && function_exists('sisDestroyCurrentSession')) {
        sisDestroyCurrentSession('SESSION_TIMEOUT', 'Automatic logout after inactivity during notification polling');
    }
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Session expired']);
    exit;
}

sisEnsureNotificationTables($conn);

function sisNotificationVisibleSql(&$params, &$types) {
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if ($role === 'super_admin') $role = 'admin';
    $outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);
    $sql = '(user_id = ?';
    $params[] = $user_id;
    $types .= 'i';
    if ($role !== '') {
        if ($outlet_id !== null) {
            $sql .= ' OR (role_scope = ? AND (outlet_id IS NULL OR outlet_id = ?))';
            $params[] = $role;
            $params[] = $outlet_id;
            $types .= 'si';
        } else {
            $sql .= ' OR (role_scope = ? AND outlet_id IS NULL)';
            $params[] = $role;
            $types .= 's';
        }
    }
    $sql .= ')';
    return $sql;
}

function sisNotificationPayload($conn) {
    $count = sisNotificationCountForCurrentUser($conn);
    $items = sisGetNotificationsForCurrentUser($conn, 10);
    $list = [];
    foreach ($items as $n) {
        $url = trim((string)($n['url'] ?? ''));
        $list[] = [
            'id' => (int)$n['id'],
            'title' => (string)$n['title'],
            'message' => (string)($n['message'] ?? ''),
            'url' => $url !== '' ? sisAbsoluteAppPath($url) : sisAbsoluteAppPath(sisPageKeyFromHref(roleDashboardPath($_SESSION['role'] ?? ''))),
            'is_read' => (int)($n['is_read'] ?? 0),
            'created_at' => date('d/m/Y H:i', strtotime($n['created_at'] ?? 'now')),
        ];
    }
    return ['ok' => true, 'count' => $count, 'items' => $list, 'server_time' => date('Y-m-d H:i:s')];
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Security check failed']);
    exit;
}

if ($action === 'mark_read') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id > 0) {
        $params = [];
        $types = '';
        $visible = sisNotificationVisibleSql($params, $types);
        $sql = "UPDATE user_notifications SET is_read = 1 WHERE id = ? AND $visible";
        array_unshift($params, $id);
        $types = 'i' . $types;
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    echo json_encode(sisNotificationPayload($conn));
    exit;
}

if ($action === 'mark_all_read') {
    $params = [];
    $types = '';
    $visible = sisNotificationVisibleSql($params, $types);
    $stmt = mysqli_prepare($conn, "UPDATE user_notifications SET is_read = 1 WHERE $visible");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo json_encode(sisNotificationPayload($conn));
    exit;
}

echo json_encode(sisNotificationPayload($conn));
