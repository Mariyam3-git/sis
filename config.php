<?php
// config.php - Database Configuration
// Kept in the same simple mysqli style as the working SIS project.

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

error_reporting(E_ALL);
$sisDebug = filter_var(getenv('SIS_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $sisDebug ? '1' : '0');
ini_set('log_errors', '1');
// Keep mysqli in non-exception mode so older AMpps/MySQL compatibility checks do not crash the page.
mysqli_report(MYSQLI_REPORT_OFF);

// Database Configuration
// You can either edit the fallback values below or set environment variables:
// SIS_DB_HOST, SIS_DB_USER, SIS_DB_PASS, SIS_DB_NAME.
// Defaults are kept compatible with the previous AMpps/XAMPP setup.
define('DB_HOST', getenv('SIS_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('SIS_DB_USER') ?: 'root');
define('DB_PASS', getenv('SIS_DB_PASS') !== false ? getenv('SIS_DB_PASS') : 'YES');
define('DB_NAME', getenv('SIS_DB_NAME') ?: 'comium_gm');

// Establish MySQLi Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    error_log('SIS database connection failed: ' . mysqli_connect_error());
    http_response_code(503);
    exit('The system is temporarily unavailable. Please contact the administrator.');
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Timezone
date_default_timezone_set('Africa/Banjul');

// Build correct relative paths from root, admin, agent, and supervisor folders.
// Example: from /admin/dashboard.php, appPath('login.php') becomes ../login.php
function appBasePrefix() {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = basename(dirname($script));
    return in_array($dir, ['admin', 'agent', 'supervisor'], true) ? '../' : '';
}

function appPath($path) {
    return appBasePrefix() . ltrim($path, '/');
}

function appAsset($path) {
    $resolved = appPath($path);
    $clean = ltrim((string)$path, '/');
    // Bust browser cache for CSS/JS updates so old theme files do not stay loaded after replacing the project.
    if (preg_match('/\.(css|js)$/i', $clean)) {
        $resolved .= (strpos($resolved, '?') === false ? '?' : '&') . 'v=20260728_final_audit_v3';
    }
    return $resolved;
}


// Root-safe URL path for API responses. Unlike appPath(), this is safe from /admin, /agent, /supervisor, and AJAX endpoints.
function sisAbsoluteAppPath($path) {
    $path = ltrim((string)$path, '/');
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = rtrim(dirname($script), '/');
    foreach (['/admin', '/agent', '/supervisor'] as $roleDir) {
        if (substr($base, -strlen($roleDir)) === $roleDir) {
            $base = substr($base, 0, -strlen($roleDir));
            break;
        }
    }
    if ($base === '' || $base === '.') $base = '';
    return $base . '/' . $path;
}

function redirectTo($path) {
    header('Location: ' . $path);
    exit();
}

// Escape output safely
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Function to detect shift based on time. Existing calls still work.
// New reports may pass a specific datetime for accurate historical shift reporting.
function getShift($datetime = null) {
    $timestamp = $datetime ? strtotime((string)$datetime) : time();
    if ($timestamp === false) {
        $timestamp = time();
    }
    $hour = (int)date('H', $timestamp);
    if ($hour >= 6 && $hour < 14) return 'Morning';
    if ($hour >= 14 && $hour < 22) return 'Afternoon';
    return 'Night';
}

function formatSecondsDuration($seconds) {
    $seconds = max(0, (int)$seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    if ($hours > 0) return sprintf('%dh %02dm %02ds', $hours, $minutes, $secs);
    if ($minutes > 0) return sprintf('%dm %02ds', $minutes, $secs);
    return sprintf('%ds', $secs);
}

function formatHandleDateTimeLabel($value) {
    $value = trim((string)($value ?? ''));
    if ($value === '' || $value === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($value);
    if ($ts === false || $ts <= 0) return e($value);
    return date('d/m/Y H:i:s', $ts);
}

function renderHandleTimeCell($seconds, $started_at = null, $completed_at = null) {
    $hasSeconds = !($seconds === null || $seconds === '');
    if (!$hasSeconds) {
        return '<span class="text-muted">—</span>';
    }

    $html = '<span class="badge bg-light text-dark border"><i class="bi bi-stopwatch"></i> ' . e(formatSecondsDuration($seconds)) . '</span>';
    if (!empty($started_at) || !empty($completed_at)) {
        $html .= '<small class="d-block text-muted mt-1 sis-u-5264fe74">Start: ' . e(formatHandleDateTimeLabel($started_at)) . '<br>End: ' . e(formatHandleDateTimeLabel($completed_at)) . '</small>';
    }
    return $html;
}

// Check authentication
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Backward compatibility for old pages that use isLoggedIn()
function isLoggedIn() {
    return isAuthenticated();
}

function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

// Check role. Owner account inherits Admin access.
function hasRole($role) {
    if (!isset($_SESSION['role'])) return false;

    if (is_array($role)) {
        if (in_array($_SESSION['role'], $role, true)) return true;
        return $_SESSION['role'] === 'super_admin' && in_array('admin', $role, true);
    }

    if ($role === 'admin' && $_SESSION['role'] === 'super_admin') return true;
    return $_SESSION['role'] === $role;
}

function sisNormalizeRole($role) {
    $role = strtolower(trim((string)$role));
    $role = str_replace(['-', ' '], '_', $role);
    if ($role === 'superadmin' || $role === 'super_administrator') return 'super_admin';
    if (in_array($role, ['admin', 'super_admin', 'supervisor', 'agent'], true)) return $role;
    return '';
}



function sisDisplayRole($role) {
    $role = sisNormalizeRole($role);
    $labels = [
        'admin' => 'Admin',
        'super_admin' => 'System Owner',
        'supervisor' => 'Supervisor',
        'agent' => 'Agent',
    ];
    return $labels[$role] ?? 'User';
}

function sisSetSettingValue($key, $value) {
    if (!isset($GLOBALS['conn']) || !$GLOBALS['conn'] || !tableExists($GLOBALS['conn'], 'settings')) return false;
    $key = (string)$key;
    $value = (string)$value;
    $stmt = mysqli_prepare($GLOBALS['conn'], "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function sisShiftLateGraceMinutes() {
    $minutes = (int)sisGetSettingValue('shift_late_grace_minutes', '15');
    return max(0, min(180, $minutes));
}

function roleDashboardPath($role = null) {
    $role = sisNormalizeRole($role ?? ($_SESSION['role'] ?? ''));

    if ($role === 'admin' || $role === 'super_admin') {
        return appPath('admin/dashboard.php');
    }
    if ($role === 'supervisor') {
        return appPath('supervisor/dashboard.php');
    }
    if ($role === 'agent') {
        return appPath('agent/dashboard.php');
    }

    return appPath('login.php');
}


// Owner page access control. The owner can decide which pages normal Admin, Supervisor, and Agent roles can open.
function sisPageRegistry() {
    return [
        'admin/dashboard.php' => ['label' => 'Admin Dashboard', 'roles' => ['admin']],
        'admin/announcements.php' => ['label' => 'Manage Announcements', 'roles' => ['admin']],
        'admin/outlets.php' => ['label' => 'Outlets', 'roles' => ['admin']],
        'admin/agents.php' => ['label' => 'Agents', 'roles' => ['admin']],
        'admin/supervisors.php' => ['label' => 'Supervisors', 'roles' => ['admin']],
        'admin/users.php' => ['label' => 'Users & Roles', 'roles' => ['admin']],
        'admin/services.php' => ['label' => 'Services', 'roles' => ['admin']],
        'admin/purposes.php' => ['label' => 'Visit Purposes', 'roles' => ['admin']],
        'admin/purpose_categories.php' => ['label' => 'Purpose Categories', 'roles' => ['admin']],
        'admin/complaint_reasons.php' => ['label' => 'Main/Sub Reasons', 'roles' => ['admin']],
        'admin/support_forms.php' => ['label' => 'Complaint / Support Form Builder', 'roles' => ['admin']],
        'admin/sales.php' => ['label' => 'Sales Report', 'roles' => ['admin']],
        'admin/reports.php' => ['label' => 'Advanced KPI Report', 'roles' => ['admin']],
        'admin/general_report.php' => ['label' => 'General Report', 'roles' => ['admin']],
        'admin/visitors.php' => ['label' => 'Visitor Log', 'roles' => ['admin']],
        'admin/support_reports.php' => ['label' => 'Complaint / Support Reports', 'roles' => ['admin']],
        'admin/daily_showroom_report.php' => ['label' => 'Daily Showroom Report', 'roles' => ['admin']],
        'admin/shift_report.php' => ['label' => 'Shift Report', 'roles' => ['admin']],
        'admin/shift_requests.php' => ['label' => 'Shift Requests Approval', 'roles' => ['admin']],
        'admin/shift_allocation.php' => ['label' => 'Shift Allocation', 'roles' => ['admin']],
        'admin/reconciliation.php' => ['label' => 'Cash Reconciliation', 'roles' => ['admin']],
        'admin/visitor_analytics.php' => ['label' => 'Visitor Analytics', 'roles' => ['admin']],
        'admin/handle_timer_debug.php' => ['label' => 'Handle Time Debug', 'roles' => ['admin']],
        'admin/stock_dashboard.php' => ['label' => 'Stock Dashboard', 'roles' => ['admin']],
        'admin/stock_products.php' => ['label' => 'Stock Products', 'roles' => ['admin']],
        'admin/stock_categories.php' => ['label' => 'Stock Categories', 'roles' => ['admin']],
        'admin/stock_receive.php' => ['label' => 'Receive Stock', 'roles' => ['admin']],
        'admin/stock_transfer.php' => ['label' => 'Transfer Stock', 'roles' => ['admin']],
        'admin/stock_transferrecennt.php' => ['label' => 'Recent Stock Transfers', 'roles' => ['admin']],
        'admin/stock_adjust.php' => ['label' => 'Stock Adjustments', 'roles' => ['admin']],
        'admin/stock_requests.php' => ['label' => 'Stock Requests', 'roles' => ['admin']],
        'admin/stock_alerts.php' => ['label' => 'Stock Alerts', 'roles' => ['admin']],
        'admin/stock_reports.php' => ['label' => 'Stock Reports', 'roles' => ['admin']],
        'admin/threshold_sethings.php' => ['label' => 'Stock Thresholds', 'roles' => ['admin']],
        'admin/database_console.php' => ['label' => 'Database Console', 'roles' => ['super_admin']],
        'supervisor/dashboard.php' => ['label' => 'Supervisor Dashboard', 'roles' => ['supervisor']],
        'supervisor/add_entry.php' => ['label' => 'Start Customer / Add Sale', 'roles' => ['supervisor']],
        'supervisor/add_sale.php' => ['label' => 'Sales Transactions', 'roles' => ['supervisor']],
        'supervisor/support.php' => ['label' => 'Supervisor Complaint / Support', 'roles' => ['supervisor']],
        'supervisor/agent_performance.php' => ['label' => 'Agent Report', 'roles' => ['supervisor']],
        'supervisor/pending_payments.php' => ['label' => 'Pending Payments', 'roles' => ['supervisor']],
        'supervisor/visitors.php' => ['label' => 'Visitor Log', 'roles' => ['supervisor']],
        'supervisor/service_analysis.php' => ['label' => 'Service Analysis', 'roles' => ['supervisor']],
        'supervisor/cash_reconciliation.php' => ['label' => 'Cash Reconciliation', 'roles' => ['supervisor']],
        'supervisor/shift_report.php' => ['label' => 'Shift Report', 'roles' => ['supervisor']],
        'supervisor/shift_requests.php' => ['label' => 'Shift Requests', 'roles' => ['supervisor']],
        'supervisor/stock_dashboard.php' => ['label' => 'Stock Dashboard', 'roles' => ['supervisor']],
        'supervisor/stock_requests.php' => ['label' => 'Stock Requests', 'roles' => ['supervisor']],
        'supervisor/stock.php' => ['label' => 'Stock', 'roles' => ['supervisor']],
        'supervisor/shift_allocation.php' => ['label' => 'Shift Allocation', 'roles' => ['supervisor']],
        'supervisor/targets.php' => ['label' => 'Targets', 'roles' => ['supervisor']],
        'agent/dashboard.php' => ['label' => 'Agent Dashboard', 'roles' => ['agent']],
        'agent/sales.php' => ['label' => 'Agent Add Customer / Sale', 'roles' => ['agent']],
        'agent/support.php' => ['label' => 'Agent Complaint / Support', 'roles' => ['agent']],
        'agent/my_shift.php' => ['label' => 'My Shift', 'roles' => ['agent']],
        'agent/stock.php' => ['label' => 'Outlet Stock', 'roles' => ['agent']],
        'agent/profile.php' => ['label' => 'My Performance', 'roles' => ['agent']],
        'supervisor/profile.php' => ['label' => 'My Performance', 'roles' => ['supervisor']],
        'agent/process_payment.php' => ['label' => 'Process Payment', 'roles' => ['agent']],
        'profile.php' => ['label' => 'My Account / Password', 'roles' => ['admin','super_admin','supervisor','agent']],
        'announcements.php' => ['label' => 'Announcement Board', 'roles' => ['admin','super_admin','supervisor','agent']],
        'help.php' => ['label' => 'Help Center', 'roles' => ['admin','super_admin','supervisor','agent']],
    ];
}

function sisCurrentPageKey() {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (preg_match('#/(admin|agent|supervisor)/([^/]+\.php)$#', $script, $m)) {
        return $m[1] . '/' . $m[2];
    }
    return basename($script ?: '');
}

function sisPageKeyFromHref($href) {
    $href = preg_replace('/[?#].*$/', '', trim((string)$href));
    $href = str_replace('\\', '/', $href);
    $href = preg_replace('#^\./#', '', $href);
    while (str_starts_with($href, '../')) $href = substr($href, 3);
    return ltrim($href, '/');
}

function sisLockedAccessPage($pageKey) {
    return in_array($pageKey, [
        'admin/dashboard.php', 'admin/users.php', 'supervisor/dashboard.php', 'agent/dashboard.php',
        'announcements.php', 'help.php', 'profile.php', 'agent/profile.php', 'supervisor/profile.php'
    ], true);
}

function sisEnsurePageAccessTable($conn) {
    if (!$conn) return false;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS page_access_rules (
        id INT NOT NULL AUTO_INCREMENT,
        page_key VARCHAR(160) NOT NULL,
        role_name ENUM('admin','supervisor','agent') NOT NULL,
        is_allowed TINYINT(1) NOT NULL DEFAULT 1,
        updated_by INT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_page_access (page_key, role_name),
        KEY idx_page_access_role (role_name, is_allowed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    return tableExists($conn, 'page_access_rules');
}

function sisDefaultPageAccessAllowed($role, $pageKey) {
    $role = strtolower((string)$role);
    if ($role === 'super_admin') return true;
    $registry = sisPageRegistry();
    if (!isset($registry[$pageKey])) return true;
    return in_array($role, $registry[$pageKey]['roles'] ?? [], true);
}

function sisPageAccessAllowedForRole($role, $pageKey) {
    $role = strtolower((string)$role);
    $pageKey = sisPageKeyFromHref($pageKey);
    if ($role === 'super_admin') return true;
    if (!in_array($role, ['admin','supervisor','agent'], true)) return true;
    if (sisLockedAccessPage($pageKey)) return sisDefaultPageAccessAllowed($role, $pageKey);
    if (!sisDefaultPageAccessAllowed($role, $pageKey)) return false;
    if (!isset($GLOBALS['conn']) || !$GLOBALS['conn'] || !tableExists($GLOBALS['conn'], 'page_access_rules')) return true;
    $stmt = mysqli_prepare($GLOBALS['conn'], "SELECT is_allowed FROM page_access_rules WHERE page_key = ? AND role_name = ? LIMIT 1");
    if (!$stmt) return true;
    mysqli_stmt_bind_param($stmt, 'ss', $pageKey, $role);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $allowed);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found) return true;
    return (int)$allowed === 1;
}

function sisCurrentPageAccessAllowed() {
    return sisPageAccessAllowedForRole($_SESSION['role'] ?? '', sisCurrentPageKey());
}

function sisRequirePageAccess() {
    if (!isAuthenticated()) return;
    if (!sisCurrentPageAccessAllowed()) {
        if (function_exists('auditAction') && isset($GLOBALS['conn'])) {
            @auditAction($GLOBALS['conn'], 'PAGE_ACCESS_DENIED', $_SESSION['user_id'] ?? null, 'Denied page: ' . sisCurrentPageKey());
        }
        sisSetFlash('You do not have access to that page. Please contact the system owner if you need it.', 'warning');
        $dashboard = roleDashboardPath($_SESSION['role'] ?? '');
        if (sisCurrentPageKey() === sisPageKeyFromHref($dashboard)) {
            http_response_code(403);
            die('Access denied. Please contact the system owner.');
        }
        redirectTo($dashboard);
    }
}


// User account helpers: profile photos, password expiry, and safe account repair.
function sisGetSettingValue($key, $default = '') {
    if (!isset($GLOBALS['conn']) || !$GLOBALS['conn'] || !tableExists($GLOBALS['conn'], 'settings')) return $default;
    $stmt = mysqli_prepare($GLOBALS['conn'], "SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) return $default;
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (string)($row['setting_value'] ?? $default) : $default;
}

function sisEnsureUserAccountColumns($conn) {
    if (!$conn || !tableExists($conn, 'users')) return false;
    $queries = [];
    if (!tableColumnExists($conn, 'users', 'email')) {
        $after = tableColumnExists($conn, 'users', 'full_name') ? ' AFTER full_name' : '';
        $queries[] = "ALTER TABLE users ADD COLUMN email VARCHAR(160) NULL" . $after;
    }
    if (!tableColumnExists($conn, 'users', 'profile_photo')) {
        if (tableColumnExists($conn, 'users', 'language_preference')) $after = ' AFTER language_preference';
        elseif (tableColumnExists($conn, 'users', 'email')) $after = ' AFTER email';
        elseif (tableColumnExists($conn, 'users', 'full_name')) $after = ' AFTER full_name';
        else $after = '';
        $queries[] = "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL" . $after;
    }
    if (!tableColumnExists($conn, 'users', 'password_changed_at')) {
        $after = tableColumnExists($conn, 'users', 'password_hash') ? ' AFTER password_hash' : '';
        $queries[] = "ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL" . $after;
    }
    if (!tableColumnExists($conn, 'users', 'must_change_password')) {
        $after = tableColumnExists($conn, 'users', 'password_changed_at') ? ' AFTER password_changed_at' : '';
        $queries[] = "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0" . $after;
    }
    foreach ($queries as $sql) { @mysqli_query($conn, $sql); }
    if (tableColumnExists($conn, 'users', 'password_changed_at')) {
        $createdExpr = tableColumnExists($conn, 'users', 'created_at') ? 'created_at' : 'NOW()';
        @mysqli_query($conn, "UPDATE users SET password_changed_at = COALESCE(password_changed_at, " . $createdExpr . ", NOW()) WHERE password_changed_at IS NULL");
    }
    if (function_exists('sisEnsureUserRoleColumn')) {
        sisEnsureUserRoleColumn($conn);
    }
    return true;
}

function sisPasswordExpiryDays() {
    $days = (int)sisGetSettingValue('password_expiry', '90');
    return max(0, min(3650, $days));
}

function sisUserNeedsPasswordChange($conn, $user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0 || !$conn || !tableExists($conn, 'users')) return false;
    if (!tableColumnExists($conn, 'users', 'password_changed_at') || !tableColumnExists($conn, 'users', 'must_change_password')) return false;
    $stmt = mysqli_prepare($conn, "SELECT must_change_password, COALESCE(password_changed_at, created_at) AS changed_at FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) return false;
    if ((int)($row['must_change_password'] ?? 0) === 1) return true;
    $days = sisPasswordExpiryDays();
    if ($days <= 0) return false;
    $changed = strtotime((string)($row['changed_at'] ?? ''));
    if ($changed === false || $changed <= 0) return true;
    return (time() - $changed) >= ($days * 86400);
}

function sisPasswordChangeAllowedCurrentPage() {
    $page = sisCurrentPageKey();
    return in_array($page, ['profile.php', 'logout.php', 'login.php', 'notifications_api.php', 'announcements_api.php'], true);
}

function sisProfilePhotoPath($path = '') {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('~^(https?:)?//~i', $path)) return $path;
    return appAsset($path);
}

function sisCurrentUserPhoto() {
    if (!isAuthenticated() || !isset($GLOBALS['conn']) || !$GLOBALS['conn'] || !tableExists($GLOBALS['conn'], 'users') || !tableColumnExists($GLOBALS['conn'], 'users', 'profile_photo')) return '';
    static $photo = null;
    if ($photo !== null) return $photo;
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $stmt = mysqli_prepare($GLOBALS['conn'], 'SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) return $photo = '';
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $photo = (string)($row['profile_photo'] ?? '');
}

function sisSafeUserRoleOptions($viewerRole) {
    $viewerRole = function_exists('sisNormalizeRole') ? sisNormalizeRole($viewerRole) : strtolower((string)$viewerRole);
    if ($viewerRole === 'super_admin') return ['admin'=>'Admin','supervisor'=>'Supervisor','agent'=>'Agent','super_admin'=>'System Owner'];
    return ['admin'=>'Admin','supervisor'=>'Supervisor','agent'=>'Agent'];
}

function sisEnsureUserRoleColumn($conn) {
    if (!$conn || !tableExists($conn, 'users') || !tableColumnExists($conn, 'users', 'role')) return false;
    // Keep role changes working on older databases that did not yet know the super_admin enum value.
    @mysqli_query($conn, "ALTER TABLE users MODIFY role ENUM('admin','super_admin','supervisor','agent') NOT NULL DEFAULT 'agent'");
    return true;
}

// Session timeout helpers. The timeout defaults to 30 minutes and can be changed in Settings.
function sisSessionTimeoutSeconds() {
    static $seconds = null;
    if ($seconds !== null) return $seconds;

    $minutes = 30;
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] && tableExists($GLOBALS['conn'], 'settings')) {
        $res = @mysqli_query($GLOBALS['conn'], "SELECT setting_value FROM settings WHERE setting_key = 'session_timeout' LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $stored = (int)($row['setting_value'] ?? 30);
            if ($stored > 0) $minutes = $stored;
        }
    }

    // Keep a safe minimum/maximum so a bad setting cannot lock users out.
    $minutes = max(5, min(480, $minutes));
    return $seconds = ($minutes * 60);
}

function sisSessionExpired() {
    if (!isAuthenticated()) return false;
    if (empty($_SESSION['last_activity'])) return false;
    return (time() - (int)$_SESSION['last_activity']) > sisSessionTimeoutSeconds();
}

function sisDestroyCurrentSession($audit_action = 'SESSION_TIMEOUT', $audit_details = 'Automatic logout after inactivity') {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (isset($GLOBALS['conn']) && $uid > 0 && function_exists('auditAction')) {
        @auditAction($GLOBALS['conn'], $audit_action, $uid, $audit_details);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function sisTouchSessionActivity() {
    if (isAuthenticated()) {
        $_SESSION['last_activity'] = time();
    }
}

// Redirect if not authenticated and enforce inactivity timeout.
// $touchActivity=false is used by background polling, so notification refreshes do not keep idle users logged in forever.
function requireLogin($touchActivity = true) {
    if (!isAuthenticated()) {
        redirectTo(appPath('login.php'));
    }

    if (sisSessionExpired()) {
        sisDestroyCurrentSession('SESSION_TIMEOUT', 'Automatic logout after inactivity');
        redirectTo(appPath('login.php?timeout=1'));
    }

    if ($touchActivity && function_exists('sisPreventDuplicatePostRefresh')) {
        sisPreventDuplicatePostRefresh();
    }

    if ($touchActivity) {
        sisTouchSessionActivity();
    }

    if ($touchActivity && function_exists('sisUserNeedsPasswordChange') && !sisPasswordChangeAllowedCurrentPage() && sisUserNeedsPasswordChange($GLOBALS['conn'], (int)($_SESSION['user_id'] ?? 0))) {
        $_SESSION['force_password_change'] = 1;
        redirectTo(appPath('profile.php?force_password=1'));
    }
}


function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        if (isset($GLOBALS['conn']) && function_exists('auditAction')) {
            @auditAction($GLOBALS['conn'], 'ACCESS_DENIED_SUPERADMIN_PAGE', $_SESSION['user_id'] ?? null, 'Attempted to access owner-only page: ' . ($_SERVER['REQUEST_URI'] ?? ''));
        }
        redirectTo(roleDashboardPath($_SESSION['role'] ?? ''));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
        if (isset($GLOBALS['conn']) && function_exists('auditAction')) {
            @auditAction($GLOBALS['conn'], 'CSRF_VALIDATION_FAILED', $_SESSION['user_id'] ?? null, $_SERVER['REQUEST_URI'] ?? '');
        }
        http_response_code(403);
        exit('Security validation failed. Please return to the previous page, refresh it, and try again.');
    }
    if (function_exists('sisRequirePageAccess')) {
        sisRequirePageAccess();
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        redirectTo(roleDashboardPath());
    }
    // Enforce CSRF consistently for every authenticated role page. The shared footer
    // injects this token into legacy POST forms that do not render csrfField() themselves.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
        if (isset($GLOBALS['conn']) && function_exists('auditAction')) {
            @auditAction($GLOBALS['conn'], 'CSRF_VALIDATION_FAILED', $_SESSION['user_id'] ?? null, $_SERVER['REQUEST_URI'] ?? '');
        }
        http_response_code(403);
        exit('Security validation failed. Please return to the previous page, refresh it, and try again.');
    }
    if (function_exists('sisRequirePageAccess')) {
        sisRequirePageAccess();
    }
}

function tableExists($conn, $table) {
    static $cache = [];
    $table = trim((string)$table);
    if ($table === '') return false;
    if (isset($cache[$table])) return $cache[$table];

    // Compatible with AMpps/MySQL versions that reject prepared SHOW TABLES ... LIKE ?
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $exists = ((int)$count > 0);
    if ($exists) $cache[$table] = true;
    return $exists;
}

function tableColumnExists($conn, $table, $column) {
    static $cache = [];
    $table = trim((string)$table);
    $column = trim((string)$column);
    if ($table === '' || $column === '') return false;
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];

    // Compatible with AMpps/MySQL versions that reject prepared SHOW COLUMNS ... LIKE ?
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $exists = ((int)$count > 0);
    if ($exists) $cache[$key] = true;
    return $exists;
}


function indexExists($conn, $table, $index) {
    static $cache = [];
    $key = $table . '.' . $index;
    if (isset($cache[$key])) return $cache[$key];
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'ss', $table, $index);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $exists = ((int)$count > 0);
    if ($exists) $cache[$key] = true;
    return $exists;
}

function constraintExists($conn, $constraint) {
    static $cache = [];
    if (isset($cache[$constraint])) return $cache[$constraint];
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 's', $constraint);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $exists = ((int)$count > 0);
    if ($exists) $cache[$constraint] = true;
    return $exists;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function sisPostOnceToken($scope = 'global') {
    $scope = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$scope) ?: 'global';
    if (!isset($_SESSION['sis_post_once_tokens']) || !is_array($_SESSION['sis_post_once_tokens'])) {
        $_SESSION['sis_post_once_tokens'] = [];
    }

    $now = time();
    foreach ($_SESSION['sis_post_once_tokens'] as $token => $meta) {
        if (!is_array($meta) || ($now - (int)($meta['created_at'] ?? 0)) > 7200) {
            unset($_SESSION['sis_post_once_tokens'][$token]);
        }
    }
    if (count($_SESSION['sis_post_once_tokens']) > 200) {
        $_SESSION['sis_post_once_tokens'] = array_slice($_SESSION['sis_post_once_tokens'], -120, null, true);
    }

    $token = bin2hex(random_bytes(24));
    $_SESSION['sis_post_once_tokens'][$token] = ['scope' => $scope, 'created_at' => $now];
    return $token;
}

function sisPostOnceTokenField($scope = 'global') {
    return '<input type="hidden" name="sis_post_once_token" value="' . e(sisPostOnceToken($scope)) . '">';
}

function csrfField() {
    // CSRF and duplicate-submit protection are separate concerns. Rendering a new
    // one-time token here created four or more session tokens for every displayed
    // sales row. At about 51 records, cleanup removed the Add Sale form token that
    // was rendered earlier, causing a false "expired submission" warning.
    // The shared footer now adds one page-level duplicate token to every POST form.
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}


function sisSetFlash($message, $type = 'success') {
    $_SESSION['sis_flash_message'] = (string)($message ?? '');
    $_SESSION['sis_flash_type'] = in_array($type, ['success','danger','warning','info','primary','secondary'], true) ? $type : 'info';
}

function sisGetFlash() {
    $message = $_SESSION['sis_flash_message'] ?? '';
    $type = $_SESSION['sis_flash_type'] ?? 'info';
    unset($_SESSION['sis_flash_message'], $_SESSION['sis_flash_type']);
    return ['message' => $message, 'type' => $type];
}

function sisCurrentRequestUriForRedirect() {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? ''));
    if ($uri === '') return appPath('dashboard.php');
    // Keep redirects local. Never reflect external locations from user input.
    if (preg_match('/^https?:\/\//i', $uri)) return appPath('dashboard.php');
    return $uri;
}

function sisConsumePostOnceToken($token = null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = is_string($token) ? $token : (string)($_POST['sis_post_once_token'] ?? '');
    if ($token === '') return true; // Older/API forms without the token remain compatible.

    // Keep a short history of tokens that were actually consumed. This lets us
    // distinguish a real replay/double-click from a valid form whose active token
    // disappeared after session refresh, token pruning, deployment, or a long-open tab.
    if (!isset($_SESSION['sis_used_post_once_tokens']) || !is_array($_SESSION['sis_used_post_once_tokens'])) {
        $_SESSION['sis_used_post_once_tokens'] = [];
    }
    $now = time();
    foreach ($_SESSION['sis_used_post_once_tokens'] as $usedToken => $usedAt) {
        if (($now - (int)$usedAt) > 7200) unset($_SESSION['sis_used_post_once_tokens'][$usedToken]);
    }
    if (count($_SESSION['sis_used_post_once_tokens']) > 240) {
        $_SESSION['sis_used_post_once_tokens'] = array_slice($_SESSION['sis_used_post_once_tokens'], -160, null, true);
    }

    // A token in the used list is a confirmed replay of a previously accepted POST.
    if (isset($_SESSION['sis_used_post_once_tokens'][$token])) return false;

    // Normal first submission: consume the active token and remember it as used.
    if (isset($_SESSION['sis_post_once_tokens'][$token])) {
        unset($_SESSION['sis_post_once_tokens'][$token]);
        $_SESSION['sis_used_post_once_tokens'][$token] = $now;
        return true;
    }

    // Unknown/expired tokens are not proof of a duplicate. CSRF validation in
    // requireRole() still protects the request, while the browser submit guard and
    // POST/Redirect/GET flow prevent accidental repeat submissions.
    return true;
}

function sisPreventDuplicatePostRefresh() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!empty($GLOBALS['sis_post_once_checked'])) return;
    $GLOBALS['sis_post_once_checked'] = true;
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, ['notifications_api.php', 'announcements_api.php'], true)) return;

    if (!sisConsumePostOnceToken()) {
        sisSetFlash('Duplicate form submission blocked. Refreshing the page will not submit the same form again.', 'warning');
        redirectTo(sisCurrentRequestUriForRedirect());
    }
}

// One-time POST request tokens prevent duplicate inserts when a user refreshes,
// double-clicks Submit, or the browser retries a POST request.
function sisFormRequestToken($scope = 'default') {
    $scope = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$scope) ?: 'default';
    if (!isset($_SESSION['sis_form_tokens']) || !is_array($_SESSION['sis_form_tokens'])) {
        $_SESSION['sis_form_tokens'] = [];
    }
    $now = time();
    foreach ($_SESSION['sis_form_tokens'] as $token => $meta) {
        if (!is_array($meta) || ($now - (int)($meta['created_at'] ?? 0)) > 7200) {
            unset($_SESSION['sis_form_tokens'][$token]);
        }
    }
    if (count($_SESSION['sis_form_tokens']) > 120) {
        $_SESSION['sis_form_tokens'] = array_slice($_SESSION['sis_form_tokens'], -80, null, true);
    }
    $token = bin2hex(random_bytes(24));
    $_SESSION['sis_form_tokens'][$token] = ['scope' => $scope, 'created_at' => $now];
    return $token;
}

function sisFormRequestTokenField($scope = 'default') {
    return '<input type="hidden" name="request_token" value="' . e(sisFormRequestToken($scope)) . '">';
}

function sisConsumeFormRequestToken($scope = 'default', $token = null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $scope = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$scope) ?: 'default';
    $token = is_string($token) ? $token : (string)($_POST['request_token'] ?? '');
    if ($token === '' || empty($_SESSION['sis_form_tokens'][$token])) return false;
    $meta = $_SESSION['sis_form_tokens'][$token];
    unset($_SESSION['sis_form_tokens'][$token]);
    return is_array($meta) && (($meta['scope'] ?? '') === $scope);
}

function logSuperAction($conn, $action_type, $record_id = null, $details = '') {
    if (!isset($_SESSION['user_id'])) return;
    if (!tableExists($conn, 'super_audit_log')) return;

    $user_id = (int)$_SESSION['user_id'];
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $record_id = $record_id !== null ? (int)$record_id : null;

    $stmt = mysqli_prepare($conn, 'INSERT INTO super_audit_log (user_id, action_type, record_id, details, timestamp, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'isissss', $user_id, $action_type, $record_id, $details, $timestamp, $ip_address, $user_agent);
        mysqli_stmt_execute($stmt);
    }
}

// Log user action
function logAction($conn, $action_type, $record_id = null) {
    if (!isset($_SESSION['user_id'])) return;

    // Owner actions are separated from normal Admin audit logs.
    // They are still recorded in super_audit_log when the upgrade table exists.
    if (isSuperAdmin() && tableExists($conn, 'super_audit_log')) {
        logSuperAction($conn, $action_type, $record_id, 'Routed from standard audit logger');
        return;
    }

    $user_id = (int)$_SESSION['user_id'];
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = mysqli_prepare($conn, "INSERT INTO audit_log (user_id, action_type, record_id, timestamp, ip_address) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $record_id = $record_id !== null ? (int)$record_id : null;
        mysqli_stmt_bind_param($stmt, "isiss", $user_id, $action_type, $record_id, $timestamp, $ip_address);
        mysqli_stmt_execute($stmt);
    }
}

function auditAction($conn, $action_type, $record_id = null, $details = '') {
    if (isSuperAdmin()) {
        logSuperAction($conn, $action_type, $record_id, $details);
        return;
    }
    logAction($conn, $action_type, $record_id);
}

function normalizeShiftName($shift) {
    $shift = strtolower(trim((string)$shift));
    if ($shift === 'morning') return 'Morning';
    if ($shift === 'afternoon') return 'Afternoon';
    if ($shift === 'night') return 'Night';
    return 'Morning';
}

function getAgentActiveShift($conn, $agent_id, $date = null) {
    if (!tableExists($conn, 'agent_shifts')) return null;
    $date = $date ?: date('Y-m-d');
    $stmt = mysqli_prepare($conn, "SELECT * FROM agent_shifts WHERE agent_id = ? AND shift_date = ? AND logout_time IS NULL ORDER BY login_time DESC, id DESC LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'is', $agent_id, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

function startAgentShift($conn, $agent_id, $outlet_id, $shift, $date = null, $login_time = null) {
    if (!tableExists($conn, 'agent_shifts')) return null;
    $date = $date ?: date('Y-m-d');
    $login_time = $login_time ?: date('Y-m-d H:i:s');
    $shift = normalizeShiftName($shift);

    $lockName = 'sis_shift_' . (int)$agent_id . '_' . preg_replace('/[^0-9]/', '', $date);
    $lockStmt = mysqli_prepare($conn, 'SELECT GET_LOCK(?, 3)');
    $locked = false;
    if ($lockStmt) {
        mysqli_stmt_bind_param($lockStmt, 's', $lockName);
        mysqli_stmt_execute($lockStmt);
        mysqli_stmt_bind_result($lockStmt, $lockResult);
        mysqli_stmt_fetch($lockStmt);
        $locked = ((int)$lockResult === 1);
        mysqli_stmt_close($lockStmt);
    }
    if (!$locked) return null;

    $shiftId = null;
    try {
        $active = getAgentActiveShift($conn, $agent_id, $date);
        if ($active) {
            $shiftId = (int)$active['id'];
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO agent_shifts (agent_id, outlet_id, shift_date, shift_type, login_time, logout_time) VALUES (?, ?, ?, ?, ?, NULL)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iisss', $agent_id, $outlet_id, $date, $shift, $login_time);
                if (mysqli_stmt_execute($stmt)) $shiftId = (int)mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
            }
        }
    } finally {
        $release = mysqli_prepare($conn, 'SELECT RELEASE_LOCK(?)');
        if ($release) {
            mysqli_stmt_bind_param($release, 's', $lockName);
            mysqli_stmt_execute($release);
            mysqli_stmt_close($release);
        }
    }
    return $shiftId;
}

function endAgentShift($conn, $agent_id, $date = null, $logout_time = null) {
    if (!tableExists($conn, 'agent_shifts')) return false;
    $date = $date ?: date('Y-m-d');
    $logout_time = $logout_time ?: date('Y-m-d H:i:s');

    $stmt = mysqli_prepare($conn, 'UPDATE agent_shifts SET logout_time = ? WHERE agent_id = ? AND shift_date = ? AND logout_time IS NULL');
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'sis', $logout_time, $agent_id, $date);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_affected_rows($stmt) > 0;
}

function reopenAgentShift($conn, $shift_id, $reason = '') {
    if (!tableExists($conn, 'agent_shifts')) return false;
    $shift_id = (int)$shift_id;
    $shiftInfo = null;
    $lookup = mysqli_prepare($conn, 'SELECT agent_id, shift_date FROM agent_shifts WHERE id = ? LIMIT 1');
    if ($lookup) {
        mysqli_stmt_bind_param($lookup, 'i', $shift_id);
        mysqli_stmt_execute($lookup);
        $res = mysqli_stmt_get_result($lookup);
        $shiftInfo = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($lookup);
    }
    $stmt = mysqli_prepare($conn, 'UPDATE agent_shifts SET logout_time = NULL WHERE id = ? AND shift_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND CURDATE() AND logout_time IS NOT NULL');
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'i', $shift_id);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) > 0;
    if ($ok) {
        if ($shiftInfo && tableExists($conn, 'shift_requests') && tableExists($conn, 'shift_assignments')
            && tableColumnExists($conn, 'shift_requests', 'logout_deadline')) {
            $clear = mysqli_prepare($conn, "UPDATE shift_requests sr
                JOIN shift_assignments sa ON sa.id = sr.assignment_id
                SET sr.logout_deadline = NULL
                WHERE sr.agent_id = ? AND sa.shift_date = ? AND sr.request_type = 'overtime'");
            if ($clear) {
                mysqli_stmt_bind_param($clear, 'is', $shiftInfo['agent_id'], $shiftInfo['shift_date']);
                mysqli_stmt_execute($clear);
                mysqli_stmt_close($clear);
            }
        }
        auditAction($conn, 'REOPEN_AGENT_SHIFT', $shift_id, $reason);
    }
    return $ok;
}

// Get outlet name


// Safe DB helpers used by agent/supervisor transaction forms.
// These prevent SQL like: ..., , 'Waiting', ... when outlet_id/amount is blank.
function normalizeNullableInt($value) {
    if ($value === null || $value === '' || strtolower((string)$value) === 'null') return null;
    return (int)$value;
}

function normalizeMoney($value, $default = 0.0) {
    if ($value === null || $value === '') return (float)$default;
    if (is_string($value)) $value = str_replace([',', 'D', 'd', 'GMD', 'gmd', ' '], '', $value);
    return is_numeric($value) ? (float)$value : (float)$default;
}

function insertVisitorSafe($conn, array $data, $includeQuantity = false) {
    $name = (string)($data['name'] ?? 'Customer');
    $contact = (string)($data['contact'] ?? '');
    $gender = (string)($data['gender'] ?? 'Other');
    $purpose_id = normalizeNullableInt($data['purpose_id'] ?? null);
    $service_id = normalizeNullableInt($data['service_id'] ?? null);
    $quantity = max(1, (int)($data['quantity'] ?? 1));
    $agent_id = normalizeNullableInt($data['agent_id'] ?? null);
    $outlet_id = normalizeNullableInt($data['outlet_id'] ?? null);
    $status = (string)($data['status'] ?? 'Waiting');
    $notes = (string)($data['notes'] ?? '');
    $visit_time = (string)($data['visit_time'] ?? date('Y-m-d H:i:s'));

    if ($includeQuantity) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO visitors (name, contact, gender, purpose_id, service_id, quantity, agent_id, outlet_id, status, notes, visit_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'sssiiiiisss', $name, $contact, $gender, $purpose_id, $service_id, $quantity, $agent_id, $outlet_id, $status, $notes, $visit_time);
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO visitors (name, contact, gender, purpose_id, service_id, agent_id, outlet_id, status, notes, visit_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'sssiiiisss', $name, $contact, $gender, $purpose_id, $service_id, $agent_id, $outlet_id, $status, $notes, $visit_time);
    }

    return mysqli_stmt_execute($stmt) ? mysqli_insert_id($conn) : false;
}

function insertSaleSafe($conn, array $data) {
    $visitor_id = normalizeNullableInt($data['visitor_id'] ?? null);
    $agent_id = normalizeNullableInt($data['agent_id'] ?? null);
    $outlet_id = normalizeNullableInt($data['outlet_id'] ?? null);
    $amount = normalizeMoney($data['amount'] ?? 0);
    $payment_status = (string)($data['payment_status'] ?? 'Paid');
    $invoice_number = (string)($data['invoice_number'] ?? ('SLS-' . date('YmdHis')));
    $payment_date = (string)($data['payment_date'] ?? date('Y-m-d H:i:s'));

    $stmt = mysqli_prepare($conn, 'INSERT INTO sales (visitor_id, agent_id, outlet_id, amount, payment_status, invoice_number, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'iiidsss', $visitor_id, $agent_id, $outlet_id, $amount, $payment_status, $invoice_number, $payment_date);
    return mysqli_stmt_execute($stmt) ? mysqli_insert_id($conn) : false;
}

function getOutletName($conn, $outlet_id) {
    if ($outlet_id) {
        $id = (int)$outlet_id;
        $stmt = mysqli_prepare($conn, 'SELECT name FROM outlets WHERE id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                return $row['name'];
            }
        }
    }
    return 'N/A';
}

function getComplaintReasons($conn) {
    if (!tableExists($conn, 'complaint_reasons')) return [];
    $result = mysqli_query($conn, "SELECT id, reason, category FROM complaint_reasons WHERE is_active = 1 ORDER BY sort_order, reason");
    $items = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
    }
    return $items;
}

function setVisitorComplaintReason($conn, $visitor_id, $reason_id) {
    if (!$reason_id || !tableColumnExists($conn, 'visitors', 'complaint_reason_id')) return;
    $visitor_id = (int)$visitor_id;
    $reason_id = (int)$reason_id;
    $stmt = mysqli_prepare($conn, 'UPDATE visitors SET complaint_reason_id = ? WHERE id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $reason_id, $visitor_id);
        mysqli_stmt_execute($stmt);
    }
}

function setVisitorAttended($conn, $visitor_id, $attended_at = null) {
    if (!tableColumnExists($conn, 'visitors', 'attended_at')) return;
    $visitor_id = (int)$visitor_id;
    $attended_at = $attended_at ?: date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($conn, 'UPDATE visitors SET attended_at = COALESCE(attended_at, ?) WHERE id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $attended_at, $visitor_id);
        mysqli_stmt_execute($stmt);
    }
}

function markVisitorStatus($conn, $visitor_id, $status) {
    $allowed = ['Waiting', 'In Progress', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowed, true)) return;

    $visitor_id = (int)$visitor_id;

    if ($status === 'In Progress') {
        setVisitorAttended($conn, $visitor_id);
        $stmt = mysqli_prepare($conn, 'UPDATE visitors SET status = ? WHERE id = ?');
    } elseif ($status === 'Completed' && tableColumnExists($conn, 'visitors', 'completed_at') && tableColumnExists($conn, 'visitors', 'handling_seconds')) {
        setVisitorAttended($conn, $visitor_id);
        $completed_at = date('Y-m-d H:i:s');
        $stmt = mysqli_prepare($conn, "UPDATE visitors SET status = ?, completed_at = COALESCE(completed_at, ?), handling_seconds = TIMESTAMPDIFF(SECOND, COALESCE(attended_at, visit_time), COALESCE(completed_at, ?)) WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssi', $status, $completed_at, $completed_at, $visitor_id);
            mysqli_stmt_execute($stmt);
        }
        return;
    } else {
        $stmt = mysqli_prepare($conn, 'UPDATE visitors SET status = ? WHERE id = ?');
    }

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $status, $visitor_id);
        mysqli_stmt_execute($stmt);
    }
}

function markVisitorCompleted($conn, $visitor_id) {
    markVisitorStatus($conn, $visitor_id, 'Completed');
}

function renderComplaintReasonSelect($conn) {
    $reasons = getComplaintReasons($conn);
    if (!$reasons) return;

    echo '<div class="col-md-6 complaint-reason-field"><label class="form-label"><i class="bi bi-chat-left-text"></i> Complaint Reason</label><select class="form-select" name="complaint_reason_id" id="complaintReasonSelect"><option value="">Select if this is a complaint...</option>';
    $currentCategory = null;
    foreach ($reasons as $reason) {
        $category = $reason['category'] ?: 'General';
        if ($category !== $currentCategory) {
            if ($currentCategory !== null) echo '</optgroup>';
            echo '<optgroup label="' . e($category) . '">';
            $currentCategory = $category;
        }
        echo '<option value="' . (int)$reason['id'] . '">' . e($reason['reason']) . '</option>';
    }
    if ($currentCategory !== null) echo '</optgroup>';
    echo '</select><small class="text-muted">Used for complaint categorization and reporting.</small></div>';
}


// Simple handle-time helpers.
// Official sales storage uses three columns only: handle_started_at, handle_completed_at, handle_seconds.
// The form receives a PHP server timestamp when it opens. On submit PHP calculates
// time() - handle_started_ts on the server and saves the start time, completion time, and seconds.
// JavaScript only displays the live clock and updates hidden fallback values.
function dbColumnExistsFresh($conn, $table, $column) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return ((int)$count > 0);
}

function startHandleTimerContext(string $context): void {
    $context = preg_replace('/[^a-zA-Z0-9_\-]/', '', $context);
    if ($context === '') $context = 'customer';
    if (!isset($_SESSION['handle_context_timers']) || !is_array($_SESSION['handle_context_timers'])) {
        $_SESSION['handle_context_timers'] = [];
    }
    $_SESSION['handle_context_timers'][$context] = [
        'started_ts' => time(),
        'started_at' => date('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'] ?? null,
        'role' => $_SESSION['role'] ?? null,
    ];
}

function consumeHandleTimerStartRequest(string $context = 'customer'): bool {
    $requested = $_GET['start_handle_timer'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_string($requested) && $requested === $context) {
        startHandleTimerContext($context);
        return true;
    }
    return false;
}

function getHandleTimerContextUrl(string $context = 'customer'): string {
    $base = strtok($_SERVER['PHP_SELF'] ?? '', '?');
    if (!$base) $base = $_SERVER['PHP_SELF'] ?? '';
    return $base . '?start_handle_timer=' . urlencode($context) . '&_ht=' . time();
}

function getCurrentHandleTimerForContext(string $context = 'customer') {
    $context = preg_replace('/[^a-zA-Z0-9_\-]/', '', $context);
    if ($context === '') $context = 'customer';
    if (isset($_SESSION['handle_context_timers'][$context]) && is_array($_SESSION['handle_context_timers'][$context])) {
        return $_SESSION['handle_context_timers'][$context];
    }
    return null;
}

function normalizeHandleDateTime($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $ts = strtotime($value);
    if ($ts === false || $ts <= 0) return null;
    return date('Y-m-d H:i:s', $ts);
}

function getPostedHandleTiming() {
    $server_now = time();
    $started_ts = null;
    $posted_started_at = normalizeHandleDateTime($_POST['handle_started_at'] ?? '');

    // 1) Main source: PHP server timestamp printed into the form when it opened.
    $raw_ts = $_POST['handle_started_ts'] ?? '';
    if ($raw_ts !== '' && is_numeric($raw_ts)) {
        $started_ts = (int)$raw_ts;
        // Defensive: accept milliseconds accidentally posted by older JS.
        if ($started_ts > 20000000000) {
            $started_ts = (int)floor($started_ts / 1000);
        }
    }

    // 2) Fallback: current session context timer from the Start Attending button.
    if (!$started_ts) {
        $context = $_POST['handling_timer_context'] ?? 'customer';
        $context = is_string($context) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $context) : 'customer';
        if ($context === '') $context = 'customer';
        if (isset($_SESSION['handle_context_timers'][$context]['started_ts'])) {
            $started_ts = (int)$_SESSION['handle_context_timers'][$context]['started_ts'];
            if (!$posted_started_at && !empty($_SESSION['handle_context_timers'][$context]['started_at'])) {
                $posted_started_at = normalizeHandleDateTime($_SESSION['handle_context_timers'][$context]['started_at']);
            }
            unset($_SESSION['handle_context_timers'][$context]);
        }
    }

    // 3) Last fallback: old client hidden fields, only if PHP server timestamp is absent.
    if (!$started_ts) {
        $started_ms = isset($_POST['handling_started_ms']) && $_POST['handling_started_ms'] !== '' ? (float)$_POST['handling_started_ms'] : 0;
        if ($started_ms > 0) {
            $started_ts = (int)floor($started_ms / 1000);
        }
    }

    if (!$started_ts || $started_ts <= 0) {
        $client_seconds = isset($_POST['handling_seconds_client']) && $_POST['handling_seconds_client'] !== '' ? (int)$_POST['handling_seconds_client'] : 0;
        $seconds = ($client_seconds > 0 && $client_seconds <= 28800) ? $client_seconds : 1;
        $started_ts = $server_now - $seconds;
    }

    if ($started_ts > $server_now) {
        $started_ts = $server_now;
    }

    $seconds = $server_now - $started_ts;
    if ($seconds < 1) $seconds = 1;
    if ($seconds > 28800) {
        $seconds = 28800;
        $started_ts = $server_now - $seconds;
    }

    return [
        'started_at' => $posted_started_at ?: date('Y-m-d H:i:s', $started_ts),
        'completed_at' => date('Y-m-d H:i:s', $server_now),
        'seconds' => (int)$seconds,
        'source' => 'sales_handle_start_end_seconds',
    ];
}

function ensureHandleTimeStorage($conn) {
    static $done = false;
    if ($done) return true;

    if (tableExists($conn, 'sales')) {
        if (!dbColumnExistsFresh($conn, 'sales', 'handle_started_at')) {
            @mysqli_query($conn, "ALTER TABLE sales ADD COLUMN handle_started_at DATETIME NULL");
        }
        if (!dbColumnExistsFresh($conn, 'sales', 'handle_completed_at')) {
            @mysqli_query($conn, "ALTER TABLE sales ADD COLUMN handle_completed_at DATETIME NULL");
        }
        if (!dbColumnExistsFresh($conn, 'sales', 'handle_seconds')) {
            @mysqli_query($conn, "ALTER TABLE sales ADD COLUMN handle_seconds INT DEFAULT 0");
        }
    }

    // Visitors keep only seconds for compatibility with existing list/report pages.
    if (tableExists($conn, 'visitors') && !dbColumnExistsFresh($conn, 'visitors', 'handling_seconds')) {
        @mysqli_query($conn, "ALTER TABLE visitors ADD COLUMN handling_seconds INT DEFAULT 0");
    }

    $done = true;
    return true;
}

function applyVisitorHandleTime($conn, $visitor_id, $started_at = null, $completed_at = null, $seconds = null) {
    ensureHandleTimeStorage($conn);
    if (!dbColumnExistsFresh($conn, 'visitors', 'handling_seconds')) return;
    $visitor_id = (int)$visitor_id;
    if ($visitor_id <= 0) return;
    $seconds = max(1, (int)($seconds ?? 1));

    $stmt = mysqli_prepare($conn, 'UPDATE visitors SET handling_seconds = ? WHERE id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $seconds, $visitor_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function setSaleHandleTime($conn, $sale_id, $started_at = null, $completed_at = null, $seconds = null) {
    ensureHandleTimeStorage($conn);
    $sale_id = (int)$sale_id;
    if ($sale_id <= 0) return;

    $seconds = max(1, (int)($seconds ?? 1));
    $completed_at = normalizeHandleDateTime($completed_at) ?: date('Y-m-d H:i:s');
    $started_at = normalizeHandleDateTime($started_at) ?: date('Y-m-d H:i:s', strtotime($completed_at) - $seconds);

    $hasStarted = dbColumnExistsFresh($conn, 'sales', 'handle_started_at');
    $hasCompleted = dbColumnExistsFresh($conn, 'sales', 'handle_completed_at');
    $hasSeconds = dbColumnExistsFresh($conn, 'sales', 'handle_seconds');

    if ($hasStarted && $hasCompleted && $hasSeconds) {
        $stmt = mysqli_prepare($conn, 'UPDATE sales SET handle_started_at = ?, handle_completed_at = ?, handle_seconds = ? WHERE id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssii', $started_at, $completed_at, $seconds, $sale_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        return;
    }

    // Backward-compatible fallback if only the seconds column exists on an older database.
    if ($hasSeconds) {
        $stmt = mysqli_prepare($conn, 'UPDATE sales SET handle_seconds = ? WHERE id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $seconds, $sale_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

function renderHandleTimerFields($label = 'Customer Handle Time', $start_on_load = false, $context = 'customer') {
    static $timerScriptPrinted = false;
    static $timerWidgetCounter = 0;
    $timerWidgetCounter++;

    $auto = $start_on_load ? '1' : '0';
    $context = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$context);
    if ($context === '') $context = 'customer';

    if ($start_on_load && !getCurrentHandleTimerForContext($context)) {
        startHandleTimerContext($context);
    }

    $current_timer = getCurrentHandleTimerForContext($context);
    $started_ts = ($current_timer && !empty($current_timer['started_ts'])) ? (int)$current_timer['started_ts'] : time();
    $started_at = date('Y-m-d H:i:s', $started_ts);
    $started_ms = (string)($started_ts * 1000);
    $timer_token = bin2hex(random_bytes(8));
    $display_id = 'handleTimerDisplay' . $timerWidgetCounter;

    echo '<div class="sis-handle-timer handle-timer-widget alert alert-danger border border-danger mb-3 shadow-sm" data-handle-timer data-start-on-load="' . $auto . '">';
    echo '<div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">';
    echo '<div><strong class="d-block"><i class="bi bi-stopwatch-fill"></i> ' . e($label) . '</strong><small class="text-dark">Timer starts when this form opens. The sale stores Start Time, End Time, and Handle Seconds.</small></div>';
    echo '<div class="text-end"><div class="small fw-semibold">Live Customer Time</div><span id="' . e($display_id) . '" class="badge bg-dark fs-5 px-3 py-2 handle-timer-display">00:00:00</span></div>';
    echo '</div>';
    echo '<div class="row g-2 mt-2 small">';
    echo '<div class="col-md-4"><span class="fw-semibold">Started:</span> <span class="handle-timer-start-label">' . e(date('H:i:s', $started_ts)) . '</span></div>';
    echo '<div class="col-md-4"><span class="fw-semibold">Duration:</span> <span class="handle-timer-duration-label">00:00:00</span></div>';
    echo '<div class="col-md-4"><span class="fw-semibold">Completed:</span> <span class="handle-timer-completed-label">—</span></div>';
    echo '</div>';
    echo '<input type="hidden" name="handle_started_at" class="handle-started-at" value="' . e($started_at) . '">';
    echo '<input type="hidden" name="handle_completed_at" class="handle-completed-at" value="">';
    echo '<input type="hidden" name="handle_started_ts" class="handle-started-ts" value="' . (int)$started_ts . '">';
    echo '<input type="hidden" name="handling_started_ms" class="handling-started-ms" value="' . e($started_ms) . '">';
    echo '<input type="hidden" name="handling_completed_ms" class="handling-completed-ms" value="">';
    echo '<input type="hidden" name="handling_seconds_client" class="handling-seconds-client" value="0">';
    echo '<input type="hidden" name="handling_timer_token" class="handling-timer-token" value="' . e($timer_token) . '">';
    echo '<input type="hidden" name="handling_timer_context" class="handling-timer-context" value="' . e($context) . '">';
    echo '</div>';

    $timerScriptPrinted = true;

}



function ensureAgentShiftsTable($conn) {
    if (!$conn) return false;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS agent_shifts (
        id INT NOT NULL AUTO_INCREMENT,
        agent_id INT NOT NULL,
        outlet_id INT NULL,
        shift_date DATE NOT NULL,
        shift_type VARCHAR(30) NOT NULL DEFAULT 'Morning',
        login_time DATETIME NOT NULL,
        logout_time DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_agent_shifts_active (agent_id, shift_date, logout_time),
        KEY idx_agent_shifts_date_status (shift_date, logout_time),
        KEY idx_agent_shifts_outlet_date (outlet_id, shift_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if (tableExists($conn, 'agent_shifts')) {
        $columns = [
            'outlet_id' => "ALTER TABLE agent_shifts ADD COLUMN outlet_id INT NULL AFTER agent_id",
            'shift_date' => "ALTER TABLE agent_shifts ADD COLUMN shift_date DATE NOT NULL AFTER outlet_id",
            'shift_type' => "ALTER TABLE agent_shifts ADD COLUMN shift_type VARCHAR(30) NOT NULL DEFAULT 'Morning' AFTER shift_date",
            'login_time' => "ALTER TABLE agent_shifts ADD COLUMN login_time DATETIME NOT NULL AFTER shift_type",
            'logout_time' => "ALTER TABLE agent_shifts ADD COLUMN logout_time DATETIME NULL AFTER login_time",
        ];
        foreach ($columns as $column => $sql) {
            if (!tableColumnExists($conn, 'agent_shifts', $column)) @mysqli_query($conn, $sql);
        }
        if (function_exists('indexExists')) {
            if (!indexExists($conn, 'agent_shifts', 'idx_agent_shifts_active')) @mysqli_query($conn, "ALTER TABLE agent_shifts ADD KEY idx_agent_shifts_active (agent_id, shift_date, logout_time)");
            if (!indexExists($conn, 'agent_shifts', 'idx_agent_shifts_date_status')) @mysqli_query($conn, "ALTER TABLE agent_shifts ADD KEY idx_agent_shifts_date_status (shift_date, logout_time)");
            if (!indexExists($conn, 'agent_shifts', 'idx_agent_shifts_outlet_date')) @mysqli_query($conn, "ALTER TABLE agent_shifts ADD KEY idx_agent_shifts_outlet_date (outlet_id, shift_date)");
        }
    }
    return tableExists($conn, 'agent_shifts');
}

function ensureShiftAssignmentsTable($conn) {
    if (!$conn) return false;

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS shift_assignments (
        id INT NOT NULL AUTO_INCREMENT,
        agent_id INT NOT NULL,
        shift_date DATE NOT NULL,
        shift_type ENUM('morning','afternoon','night') NOT NULL DEFAULT 'morning',
        shift_start TIME NOT NULL DEFAULT '08:00:00',
        shift_end TIME NOT NULL DEFAULT '16:00:00',
        status ENUM('scheduled','present','late','absent','cancelled') NOT NULL DEFAULT 'scheduled',
        no_show_reason VARCHAR(60) NULL,
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_shift_agent_date (agent_id, shift_date),
        KEY idx_shift_date_status (shift_date, status),
        KEY idx_shift_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Older imported pages may have created the table without these newer columns.
    $columns = [
        'status' => "ALTER TABLE shift_assignments ADD COLUMN status ENUM('scheduled','present','late','absent','cancelled') NOT NULL DEFAULT 'scheduled' AFTER shift_end",
        'no_show_reason' => "ALTER TABLE shift_assignments ADD COLUMN no_show_reason VARCHAR(60) NULL AFTER status",
        'notes' => "ALTER TABLE shift_assignments ADD COLUMN notes TEXT NULL AFTER no_show_reason",
        'created_at' => "ALTER TABLE shift_assignments ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by",
        'updated_at' => "ALTER TABLE shift_assignments ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];
    foreach ($columns as $column => $sql) {
        if (!tableColumnExists($conn, 'shift_assignments', $column)) {
            @mysqli_query($conn, $sql);
        }
    }
    if (function_exists('indexExists')) {
        if (!indexExists($conn, 'shift_assignments', 'idx_shift_date_status')) {
            @mysqli_query($conn, "ALTER TABLE shift_assignments ADD KEY idx_shift_date_status (shift_date, status)");
        }
        if (!indexExists($conn, 'shift_assignments', 'idx_shift_created_by')) {
            @mysqli_query($conn, "ALTER TABLE shift_assignments ADD KEY idx_shift_created_by (created_by)");
        }
    }
    return true;
}

// Legacy management pages previously changed data through GET links. Convert those
// actions to POST (the shared footer submits them) and reject direct/prefetched GETs.
$sisMutationKeys = ['delete','hard_delete','restore','deactivate','activate','approve','reject','fulfill','receive','toggle_paid'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($sisMutationKeys as $mutationKey) {
        if (array_key_exists($mutationKey, $_POST)) {
            $_GET[$mutationKey] = $_POST[$mutationKey];
            foreach (['id','reason'] as $supportKey) {
                if (array_key_exists($supportKey, $_POST)) $_GET[$supportKey] = $_POST[$supportKey];
            }
            break;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach ($sisMutationKeys as $mutationKey) {
        if (array_key_exists($mutationKey, $_GET)) {
            http_response_code(405);
            header('Allow: POST');
            exit('This action requires a secure form submission. Return to the previous page and try again.');
        }
    }
}

// Block duplicate refresh/double-click POST requests as early as possible after helpers are loaded.
sisPreventDuplicatePostRefresh();

// Make sure the timer storage exists before any Agent/Supervisor/Admin page tries to save or display Handle Time.
if (isset($conn) && $conn) {
    ensureHandleTimeStorage($conn);
    ensureAgentShiftsTable($conn);
    ensureShiftAssignmentsTable($conn);
    if (function_exists('sisEnsurePageAccessTable')) sisEnsurePageAccessTable($conn);
}

?>
