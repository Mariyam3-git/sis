<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isAuthenticated() || (function_exists('sisSessionExpired') && sisSessionExpired())) {
    if (isAuthenticated() && function_exists('sisDestroyCurrentSession')) {
        sisDestroyCurrentSession('SESSION_TIMEOUT', 'Automatic logout after inactivity during handle timer call');
    }
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'security_check_failed']);
    exit();
}

$token = $_POST['token'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_token']);
    exit();
}

if (!isset($_SESSION['handle_timers']) || !is_array($_SESSION['handle_timers'])) {
    $_SESSION['handle_timers'] = [];
}

$context = $_POST['context'] ?? 'ajax';
$context = is_string($context) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $context) : 'ajax';
if ($context === '') $context = 'ajax';

$dbTimer = function_exists('createHandleTimerRecord') ? createHandleTimerRecord($conn, $context, $token) : null;
$started_ts = $dbTimer && !empty($dbTimer['started_ts']) ? (int)$dbTimer['started_ts'] : time();
$started_at = $dbTimer && !empty($dbTimer['started_at']) ? $dbTimer['started_at'] : date('Y-m-d H:i:s', $started_ts);

$_SESSION['handle_timers'][$token] = [
    'started_ts' => $started_ts,
    'started_at' => $started_at,
    'db_token' => $dbTimer['token'] ?? $token,
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role'] ?? null,
];

// Keep only the most recent timers to avoid growing the session forever.
if (count($_SESSION['handle_timers']) > 20) {
    $_SESSION['handle_timers'] = array_slice($_SESSION['handle_timers'], -20, null, true);
}

echo json_encode([
    'ok' => true,
    'started_at' => $started_at,
    'started_ts' => (int)$started_ts,
    'started_label' => date('H:i:s', $started_ts),
    'db_token' => $dbTimer['token'] ?? $token,
]);
?>
