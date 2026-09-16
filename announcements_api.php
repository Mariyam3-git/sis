<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/include/announcement_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Background announcement polling must not refresh last_activity.
if (!isAuthenticated() || (function_exists('sisSessionExpired') && sisSessionExpired())) {
    if (isAuthenticated() && function_exists('sisDestroyCurrentSession')) {
        sisDestroyCurrentSession('SESSION_TIMEOUT', 'Automatic logout after inactivity during announcement polling');
    }
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Session expired']);
    exit;
}

sisEnsureAnnouncementTables($conn);

function sisAnnouncementPayload($conn) {
    $count = sisAnnouncementUnreadCountForCurrentUser($conn);
    $items = sisGetActiveAnnouncementsForCurrentUser($conn, 8);
    $list = [];
    foreach ($items as $a) {
        $message = (string)($a['message'] ?? '');
        if (function_exists('mb_strimwidth')) {
            $message = mb_strimwidth($message, 0, 120, '...');
        } elseif (strlen($message) > 120) {
            $message = substr($message, 0, 117) . '...';
        }
        $list[] = [
            'id' => (int)$a['id'],
            'title' => (string)$a['title'],
            'message' => $message,
            'priority' => (string)($a['priority'] ?? 'info'),
            'priority_class' => sisAnnouncementPriorityClass($a['priority'] ?? 'info'),
            'url' => sisAbsoluteAppPath('announcements.php'),
            'is_read' => (int)($a['is_read'] ?? 0),
            'is_pinned' => (int)($a['is_pinned'] ?? 0),
            'requires_ack' => (int)($a['requires_ack'] ?? 0),
            'created_at' => date('d/m/Y H:i', strtotime($a['created_at'] ?? 'now')),
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
    if ($id > 0) sisMarkAnnouncementRead($conn, $id);
    echo json_encode(sisAnnouncementPayload($conn));
    exit;
}

if ($action === 'mark_all_read') {
    sisMarkAllAnnouncementsRead($conn);
    echo json_encode(sisAnnouncementPayload($conn));
    exit;
}

echo json_encode(sisAnnouncementPayload($conn));
