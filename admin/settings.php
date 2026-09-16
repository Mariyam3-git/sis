<?php
require_once '../config.php';
requireSuperAdmin();

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$message = '';
$message_type = '';

// Handle settings sections
$section = isset($_GET['section']) ? $_GET['section'] : 'general';
$allowedSections = ['general','hours','notifications','security','backup','profile'];
if (!in_array($section, $allowedSections, true)) $section = 'general';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
    http_response_code(403);
    exit('Security validation failed. Refresh this page and try again.');
}

// ============================================
// GENERAL SETTINGS
// ============================================
if ($section == 'general' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // PHP 8+ safe defaults. Currency is fixed to Gambian Dalasis for this project.
    $site_name = mysqli_real_escape_string($conn, (string)($_POST['site_name'] ?? 'Comium GM'));
    $company_name = mysqli_real_escape_string($conn, (string)($_POST['company_name'] ?? 'Comium GM'));
    $company_email = mysqli_real_escape_string($conn, (string)($_POST['company_email'] ?? ''));
    $company_phone = mysqli_real_escape_string($conn, (string)($_POST['company_phone'] ?? ''));
    $company_address = mysqli_real_escape_string($conn, (string)($_POST['company_address'] ?? ''));
    $timezone = mysqli_real_escape_string($conn, (string)($_POST['timezone'] ?? 'Africa/Banjul'));
    $date_format = mysqli_real_escape_string($conn, (string)($_POST['date_format'] ?? 'd/m/Y'));
    $currency = 'D';
    $currency_symbol = 'D';
    
    $settings = [
        'site_name' => $site_name,
        'company_name' => $company_name,
        'company_email' => $company_email,
        'company_phone' => $company_phone,
        'company_address' => $company_address,
        'timezone' => $timezone,
        'date_format' => $date_format,
        'currency' => $currency,
        'currency_symbol' => $currency_symbol
    ];
    
    foreach ($settings as $key => $value) {
        $check = mysqli_query($conn, "SELECT * FROM settings WHERE setting_key = '$key'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
        } else {
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
        }
    }
    
    logAction($conn, 'UPDATE_GENERAL_SETTINGS');
    $message = 'General settings saved!';
    $message_type = 'success';
}

// ============================================
// BUSINESS HOURS
// ============================================
if ($section == 'hours' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        $open = (string)($_POST[$day . '_open'] ?? '');
        $close = (string)($_POST[$day . '_close'] ?? '');
        $hours_value = $open . '-' . $close;
        $key = 'hours_' . $day;
        
        $check = mysqli_query($conn, "SELECT * FROM settings WHERE setting_key = '$key'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE settings SET setting_value = '$hours_value' WHERE setting_key = '$key'");
        } else {
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$hours_value')");
        }
    }
    
    logAction($conn, 'UPDATE_BUSINESS_HOURS');
    $message = 'Business hours saved!';
    $message_type = 'success';
}

// ============================================
// NOTIFICATION SETTINGS
// ============================================
if ($section == 'notifications' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifications = [
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
        'daily_report' => isset($_POST['daily_report']) ? 1 : 0,
        'weekly_report' => isset($_POST['weekly_report']) ? 1 : 0,
        'monthly_report' => isset($_POST['monthly_report']) ? 1 : 0,
        'target_alert' => isset($_POST['target_alert']) ? 1 : 0,
        'cash_discrepancy_alert' => isset($_POST['cash_discrepancy_alert']) ? 1 : 0,
        'report_emails' => mysqli_real_escape_string($conn, (string)($_POST['report_emails'] ?? ''))
    ];
    
    foreach ($notifications as $key => $value) {
        $check = mysqli_query($conn, "SELECT * FROM settings WHERE setting_key = '$key'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
        } else {
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
        }
    }
    
    logAction($conn, 'UPDATE_NOTIFICATION_SETTINGS');
    $message = 'Notification settings saved!';
    $message_type = 'success';
}

// ============================================
// SECURITY SETTINGS
// ============================================
if ($section == 'security' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $security = [
        'password_expiry' => (int)($_POST['password_expiry'] ?? 0),
        'max_login_attempts' => (int)($_POST['max_login_attempts'] ?? 5),
        'session_timeout' => (int)($_POST['session_timeout'] ?? 30),
        'two_factor_auth' => isset($_POST['two_factor_auth']) ? 1 : 0,
        'audit_log_retention' => (int)($_POST['audit_log_retention'] ?? 365)
    ];
    
    foreach ($security as $key => $value) {
        $check = mysqli_query($conn, "SELECT * FROM settings WHERE setting_key = '$key'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
        } else {
            mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
        }
    }
    
    logAction($conn, 'UPDATE_SECURITY_SETTINGS');
    $message = 'Security settings saved!';
    $message_type = 'success';
}

// ============================================
// BACKUP & MAINTENANCE
// ============================================
if ($section == 'backup' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maintenance_action'])) {
    $maintenanceAction = (string)$_POST['maintenance_action'];
    if ($maintenanceAction === 'backup') {
        $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'sis_backups';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0700, true);
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $backup_file;
        $dumpOk = false;
        if (is_dir($backupDir) && is_writable($backupDir) && function_exists('proc_open')) {
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['file', $backupPath, 'wb'],
                2 => ['pipe', 'w'],
            ];
            $currentEnv = getenv();
            $env = array_merge(is_array($currentEnv) ? $currentEnv : $_ENV, ['MYSQL_PWD' => DB_PASS]);
            $process = @proc_open(
                ['mysqldump', '--host=' . DB_HOST, '--user=' . DB_USER, '--single-transaction', '--skip-lock-tables', DB_NAME],
                $descriptorSpec,
                $pipes,
                null,
                $env
            );
            if (is_resource($process)) {
                fclose($pipes[0]);
                $dumpError = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $dumpOk = proc_close($process) === 0 && is_file($backupPath) && filesize($backupPath) > 0;
                if (!$dumpOk) {
                    @unlink($backupPath);
                    error_log('SIS database backup failed: ' . trim((string)$dumpError));
                }
            }
        }
        if ($dumpOk) {
            @chmod($backupPath, 0600);
            logAction($conn, 'CREATE_BACKUP');
            $message = 'Database backup created securely outside the public website folder.';
            $message_type = 'success';
        } else {
            $message = 'Backup could not be created. Confirm that mysqldump is installed and the backup directory is writable.';
            $message_type = 'danger';
        }
    }
    
    if ($maintenanceAction === 'clear_logs') {
        $days = max(1, min(3650, (int)($_POST['days'] ?? 365)));
        $date = date('Y-m-d', strtotime("-$days days"));
        $stmt = mysqli_prepare($conn, 'DELETE FROM audit_log WHERE timestamp < ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $date);
            mysqli_stmt_execute($stmt);
            $deleted = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            logAction($conn, 'CLEAR_AUDIT_LOGS');
            $message = number_format(max(0, $deleted)) . " audit log entries older than $days days cleared.";
            $message_type = 'success';
        }
    }
}

// ============================================
// PROFILE UPDATE
// ============================================
if ($section == 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $full_name = mysqli_real_escape_string($conn, (string)($_POST['full_name'] ?? ''));
    $email = mysqli_real_escape_string($conn, (string)($_POST['email'] ?? ''));
    
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        $newPasswordPlain = (string)$_POST['new_password'];
        $confirmPasswordPlain = (string)($_POST['confirm_password'] ?? '');
        $check = mysqli_query($conn, "SELECT password_hash FROM users WHERE id = $user_id");
        $user = mysqli_fetch_assoc($check);
        
        if (strlen($newPasswordPlain) < 10) {
            $message = 'New password must contain at least 10 characters.';
            $message_type = 'danger';
        } elseif (!hash_equals($newPasswordPlain, $confirmPasswordPlain)) {
            $message = 'New password and confirmation do not match.';
            $message_type = 'danger';
        } elseif ($user && password_verify((string)$_POST['current_password'], $user['password_hash'])) {
            $new_password = password_hash($newPasswordPlain, PASSWORD_DEFAULT);
            $update = "UPDATE users SET full_name = '$full_name', email = '$email', password_hash = '$new_password', password_changed_at = NOW(), must_change_password = 0 WHERE id = $user_id";
            $message = 'Profile and password updated!';
        } else {
            $message = 'Current password is incorrect!';
            $message_type = 'danger';
        }
    } else {
        $update = "UPDATE users SET full_name = '$full_name', email = '$email' WHERE id = $user_id";
        $message = 'Profile updated!';
    }
    
    if (!isset($message_type) && mysqli_query($conn, $update)) {
        $_SESSION['full_name'] = $full_name;
        logAction($conn, 'UPDATE_PROFILE', $user_id);
        $message_type = 'success';
    }
}

// Load settings
$settings = [];
$settings_query = mysqli_query($conn, "SELECT * FROM settings");
while($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Defaults
$defaults = [
    'site_name' => 'Comium GM',
    'company_name' => 'Comium GM',
    'company_email' => 'info@comium.gm',
    'company_phone' => '+220 123 4567',
    'company_address' => 'Banjul, The Gambia',
    'timezone' => 'Africa/Banjul',
    'date_format' => 'd/m/Y',
    'currency' => 'D',
    'currency_symbol' => 'D',
    'email_notifications' => '1',
    'daily_report' => '1',
    'weekly_report' => '1',
    'monthly_report' => '1',
    'target_alert' => '1',
    'cash_discrepancy_alert' => '1',
    'report_emails' => '',
    'password_expiry' => '90',
    'max_login_attempts' => '5',
    'session_timeout' => '30',
    'two_factor_auth' => '0',
    'audit_log_retention' => '365',
    'hours_monday' => '09:00-17:00',
    'hours_tuesday' => '09:00-17:00',
    'hours_wednesday' => '09:00-17:00',
    'hours_thursday' => '09:00-17:00',
    'hours_friday' => '09:00-17:00',
    'hours_saturday' => '09:00-13:00',
    'hours_sunday' => 'closed-closed'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get user data for profile
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$user = mysqli_fetch_assoc($user_query);

// Get backups
$backups = [];
$backupDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'sis_backups';
if (is_dir($backupDirectory)) {
    $backups = array_values(array_filter(array_diff(scandir($backupDirectory), ['.', '..']), fn($name) => preg_match('/^backup_[0-9_-]+\.sql$/', $name)));
    rsort($backups);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
    
    

    
    <div class="main-content">
        
        <div class="page-header">
            <h3><i class="bi bi-gear sis-u-20e33279"></i>Settings</h3>
            <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
        </div>

        
        <?php if($message): ?>
        <div class="alert alert-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        
        <div class="settings-tabs">
            <a href="?section=general" class="tab-btn <?php echo $section == 'general' ? 'active' : ''; ?>">
                <i class="bi bi-sliders2"></i> General
            </a>
            <a href="?section=hours" class="tab-btn <?php echo $section == 'hours' ? 'active' : ''; ?>">
                <i class="bi bi-clock"></i> Hours
            </a>
            <a href="?section=notifications" class="tab-btn <?php echo $section == 'notifications' ? 'active' : ''; ?>">
                <i class="bi bi-bell"></i> Notifications
            </a>
            <a href="?section=security" class="tab-btn <?php echo $section == 'security' ? 'active' : ''; ?>">
                <i class="bi bi-shield-lock"></i> Security
            </a>
            <a href="?section=backup" class="tab-btn <?php echo $section == 'backup' ? 'active' : ''; ?>">
                <i class="bi bi-database"></i> Backup
            </a>
            <a href="?section=profile" class="tab-btn <?php echo $section == 'profile' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle"></i> Profile
            </a>
        </div>

        
        <div class="card">
            <div class="card-header">
                <?php
                $titles = [
                    'general' => 'General Settings',
                    'hours' => 'Business Hours',
                    'notifications' => 'Notification Preferences',
                    'security' => 'Security Settings',
                    'backup' => 'Backup & Maintenance',
                    'profile' => 'My Profile'
                ];
                echo e($titles[$section]);
                ?>
            </div>
            <div class="card-body">
                
                <?php if($section == 'general'): ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Site Name</label>
                            <input type="text" class="form-control" name="site_name" value="<?php echo e($settings['site_name']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="company_name" value="<?php echo e($settings['company_name']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="company_email" value="<?php echo e($settings['company_email']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="company_phone" value="<?php echo e($settings['company_phone']); ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="company_address" rows="2"><?php echo e($settings['company_address']); ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Timezone</label>
                            <select class="form-select" name="timezone">
                                <option value="Africa/Banjul" <?php echo $settings['timezone'] == 'Africa/Banjul' ? 'selected' : ''; ?>>Africa/Banjul</option>
                                <option value="UTC" <?php echo $settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date Format</label>
                            <select class="form-select" name="date_format">
                                <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Currency</label>
                            <input type="text" class="form-control" name="currency" value="D" readonly>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Symbol</label>
                            <input type="text" class="form-control" name="currency_symbol" value="D" readonly>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
                <?php endif; ?>

                
                <?php if($section == 'hours'): ?>
                <form method="POST">
                    <table class="hours-table">
                        <?php
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        
                        for($i = 0; $i < count($days); $i++):
                            $hours = explode('-', $settings['hours_' . $days[$i]] ?? '09:00-17:00');
                            $open = $hours[0] ?? '09:00';
                            $close = $hours[1] ?? '17:00';
                            
                            if($open == 'closed') $open = '';
                            if($close == 'closed') $close = '';
                        ?>
                        <tr>
                            <td class="sis-u-b15addac"><strong><?php echo $day_names[$i]; ?></strong></td>
                            <td class="sis-u-c25159cb">
                                <input type="time" class="form-control" name="<?php echo $days[$i]; ?>_open" value="<?php echo $open; ?>">
                            </td>
                            <td class="sis-u-c25159cb">
                                <input type="time" class="form-control" name="<?php echo $days[$i]; ?>_close" value="<?php echo $close; ?>">
                            </td>
                            <td>
                                <small class="text-muted">Leave blank for closed</small>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </table>
                    <button type="submit" class="btn btn-primary mt-3">Save Hours</button>
                </form>
                <?php endif; ?>

                
                <?php if($section == 'notifications'): ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" value="1" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="daily_report" id="daily_report" value="1" <?php echo $settings['daily_report'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="daily_report">Daily Report</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="weekly_report" id="weekly_report" value="1" <?php echo $settings['weekly_report'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="weekly_report">Weekly Report</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="monthly_report" id="monthly_report" value="1" <?php echo $settings['monthly_report'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="monthly_report">Monthly Report</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="target_alert" id="target_alert" value="1" <?php echo $settings['target_alert'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="target_alert">Target Alerts</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="cash_discrepancy_alert" id="cash_discrepancy_alert" value="1" <?php echo $settings['cash_discrepancy_alert'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="cash_discrepancy_alert">Cash Alerts</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Report Recipients (comma separated)</label>
                            <input type="text" class="form-control" name="report_emails" value="<?php echo e($settings['report_emails']); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
                <?php endif; ?>

                
                <?php if($section == 'security'): ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Expiry (days)</label>
                            <input type="number" class="form-control" name="password_expiry" value="<?php echo (int)$settings['password_expiry']; ?>">
                            <small>0 = never expires</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Login Attempts</label>
                            <input type="number" class="form-control" name="max_login_attempts" value="<?php echo (int)$settings['max_login_attempts']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" name="session_timeout" value="<?php echo (int)$settings['session_timeout']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Audit Log Retention (days)</label>
                            <input type="number" class="form-control" name="audit_log_retention" value="<?php echo (int)$settings['audit_log_retention']; ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="two_factor_auth" id="two_factor_auth" value="1" <?php echo $settings['two_factor_auth'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="two_factor_auth">Two-Factor Authentication</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
                <?php endif; ?>

                
                <?php if($section == 'backup'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <h6>Database Backup</h6>
                            <p class="text-muted small">Create a complete backup of your database.</p>
                            <form method="post" class="d-inline"><?php echo csrfField(); ?><input type="hidden" name="maintenance_action" value="backup"><button type="submit" class="btn btn-primary" data-confirm-message="Create a new database backup now?">
                                <i class="bi bi-database"></i> Create Backup
                            </button></form>
                        </div>
                        
                        <div>
                            <h6>Clear Audit Logs</h6>
                            <p class="text-muted small">Remove old audit log entries.</p>
                            <div class="d-flex gap-2"><?php foreach([30=>'warning',90=>'warning',365=>'danger'] as $days=>$class): ?><form method="post"><?php echo csrfField(); ?><input type="hidden" name="maintenance_action" value="clear_logs"><input type="hidden" name="days" value="<?php echo $days; ?>"><button type="submit" class="btn btn-<?php echo $class; ?>" data-confirm-message="Clear logs older than <?php echo $days; ?> days?"><?php echo $days === 365 ? '1+ year' : $days . '+ days'; ?></button></form><?php endforeach; ?></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Recent Backups</h6>
                        <?php if(empty($backups)): ?>
                            <p class="text-muted">No backups found.</p>
                        <?php else: ?>
                            <div class="backup-list">
                                <?php foreach(array_slice($backups, 0, 5) as $backup): ?>
                                <div class="backup-item">
                                    <span><i class="bi bi-file-earmark"></i> <?php echo e($backup); ?></span>
                                    <span class="backup-date"><?php echo date('d/m/Y H:i', filemtime($backupDirectory . DIRECTORY_SEPARATOR . $backup)); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                
                <?php if($section == 'profile'): ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo e($user['username']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="<?php echo e($user['full_name']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo e($user['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="Administrator" readonly>
                        </div>
                        
                        <div class="col-12 mt-3">
                            <h6>Change Password</h6>
                            <hr>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" id="new_password">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                            <small id="passwordMatch" class="text-danger"></small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>

                <script>
                document.getElementById('confirm_password').addEventListener('input', function() {
                    var newPass = document.getElementById('new_password').value;
                    var confirmPass = this.value;
                    var matchMsg = document.getElementById('passwordMatch');
                    
                    if (newPass != confirmPass) {
                        matchMsg.innerHTML = 'Passwords do not match';
                    } else {
                        matchMsg.innerHTML = '';
                    }
                });
                </script>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> System Information
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <small class="text-muted">PHP</small>
                        <div><?php echo phpversion(); ?></div>
                    </div>
                    <div class="col-3">
                        <small class="text-muted">MySQL</small>
                        <div><?php echo substr(mysqli_get_server_info($conn), 0, 10); ?></div>
                    </div>
                    <div class="col-3">
                        <small class="text-muted">Last Login</small>
                        <div><?php echo $user['last_login'] ? date('d/m/Y', strtotime($user['last_login'])) : 'Never'; ?></div>
                    </div>
                    <div class="col-3">
                        <small class="text-muted">Version</small>
                        <div>2.0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
