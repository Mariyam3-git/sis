<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/support_helpers.php';
require_once dirname(__DIR__) . '/include/shift_helpers.php';
require_once dirname(__DIR__) . '/include/announcement_helpers.php';
requireSuperAdmin();

$messages = [];

function runUpgradeQuery($conn, $sql, &$messages, $label = '') {
    if (mysqli_query($conn, $sql)) {
        $messages[] = ['ok', $label ?: $sql];
    } else {
        $messages[] = ['warn', ($label ?: $sql) . ' -- ' . mysqli_error($conn)];
    }
}

function runIfColumnMissing($conn, $table, $column, $sql, &$messages) {
    if (!tableColumnExists($conn, $table, $column)) {
        runUpgradeQuery($conn, $sql, $messages, "Added column {$table}.{$column}");
    } else {
        $messages[] = ['ok', "Column already exists: {$table}.{$column}"];
    }
}

function runIfColumnExists($conn, $table, $column, $sql, &$messages) {
    if (tableColumnExists($conn, $table, $column)) {
        runUpgradeQuery($conn, $sql, $messages, "Removed old column {$table}.{$column}");
    } else {
        $messages[] = ['ok', "Old column not present: {$table}.{$column}"];
    }
}

function runIfIndexMissing($conn, $table, $index, $sql, &$messages) {
    if (!function_exists('indexExists') || !indexExists($conn, $table, $index)) {
        runUpgradeQuery($conn, $sql, $messages, "Added/checked index {$index}");
    } else {
        $messages[] = ['ok', "Index already exists: {$index}"];
    }
}

function runIfConstraintMissing($conn, $constraint, $sql, &$messages) {
    if (!function_exists('constraintExists') || !constraintExists($conn, $constraint)) {
        runUpgradeQuery($conn, $sql, $messages, "Added/checked constraint {$constraint}");
    } else {
        $messages[] = ['ok', "Constraint already exists: {$constraint}"];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $messages[] = ['warn', 'Security check failed. Please refresh and try again.'];
    } else {
    if (function_exists('sisEnsurePageAccessTable')) { $messages[] = sisEnsurePageAccessTable($conn) ? ['ok', 'Page access control table ready'] : ['err', 'Page access control table could not be created']; }
    // Failed-login tracking table used by login.php (normally created automatically on first login attempt).
    $messages[] = sisEnsureLoginAttemptsTable($conn) ? ['ok', 'Login attempt tracking table ready'] : ['warn', 'Login attempt tracking table could not be created'];
    runUpgradeQuery($conn, "CREATE TABLE IF NOT EXISTS complaint_reasons (
        id INT NOT NULL AUTO_INCREMENT,
        reason VARCHAR(150) NOT NULL,
        category VARCHAR(80) DEFAULT 'General',
        description TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_complaint_reason (reason),
        KEY idx_complaint_reasons_active (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", $messages, 'Complaint reasons table ready');

    $defaults = [
        ['No Service','Network',10], ['Poor Signal','Network',20], ['Data Not Working','Network',30],
        ['Wrong Deduction','Billing',40], ['Bundle Not Received','Billing',50],
        ['SIM Swap Issue','SIM / Registration',60], ['Registration Update','SIM / Registration',70],
        ['Cash-in Issue','ComCach',80], ['Withdrawal Issue','ComCach',90],
        ['Follow-up Required','Customer Care',100], ['Other','Customer Care',110],
    ];
    $stmt = mysqli_prepare($conn, 'INSERT IGNORE INTO complaint_reasons (reason, category, sort_order) VALUES (?, ?, ?)');
    if ($stmt) {
        foreach ($defaults as $d) {
            mysqli_stmt_bind_param($stmt, 'ssi', $d[0], $d[1], $d[2]);
            mysqli_stmt_execute($stmt);
        }
        $messages[] = ['ok', 'Default complaint reasons inserted/checked'];
    }

    runIfColumnMissing($conn, 'visitors', 'complaint_reason_id', "ALTER TABLE visitors ADD COLUMN complaint_reason_id INT NULL AFTER service_id", $messages);
    runIfColumnMissing($conn, 'visitors', 'attended_at', "ALTER TABLE visitors ADD COLUMN attended_at DATETIME NULL AFTER visit_time", $messages);
    runIfColumnMissing($conn, 'visitors', 'completed_at', "ALTER TABLE visitors ADD COLUMN completed_at DATETIME NULL AFTER attended_at", $messages);
    runIfColumnMissing($conn, 'visitors', 'handling_seconds', "ALTER TABLE visitors ADD COLUMN handling_seconds INT NULL AFTER completed_at", $messages);

    runUpgradeQuery($conn, "UPDATE visitors
        SET attended_at = COALESCE(attended_at, visit_time),
            completed_at = CASE WHEN status = 'Completed' THEN COALESCE(completed_at, updated_at) ELSE completed_at END,
            handling_seconds = CASE WHEN status = 'Completed' THEN TIMESTAMPDIFF(SECOND, COALESCE(attended_at, visit_time), COALESCE(completed_at, updated_at)) ELSE handling_seconds END
        WHERE attended_at IS NULL OR (status = 'Completed' AND (completed_at IS NULL OR handling_seconds IS NULL))", $messages, 'Backfilled visitor handling-time data');

    runIfIndexMissing($conn, 'visitors', 'idx_visitors_handling_time', "ALTER TABLE visitors ADD KEY idx_visitors_handling_time (handling_seconds)", $messages);
    runIfIndexMissing($conn, 'visitors', 'idx_visitors_attended_completed', "ALTER TABLE visitors ADD KEY idx_visitors_attended_completed (attended_at, completed_at)", $messages);
    runIfIndexMissing($conn, 'visitors', 'idx_visitors_complaint_reason', "ALTER TABLE visitors ADD KEY idx_visitors_complaint_reason (complaint_reason_id)", $messages);

    // Per-transaction handle time is stored directly on sales: start time, completed time, and seconds.
    runIfColumnMissing($conn, 'sales', 'handle_started_at', "ALTER TABLE sales ADD COLUMN handle_started_at DATETIME NULL", $messages);
    runIfColumnMissing($conn, 'sales', 'handle_completed_at', "ALTER TABLE sales ADD COLUMN handle_completed_at DATETIME NULL", $messages);
    runIfColumnMissing($conn, 'sales', 'handle_seconds', "ALTER TABLE sales ADD COLUMN handle_seconds INT DEFAULT 0", $messages);
    runUpgradeQuery($conn, "UPDATE sales s LEFT JOIN visitors v ON s.visitor_id = v.id
        SET s.handle_completed_at = COALESCE(s.handle_completed_at, s.payment_date),
            s.handle_seconds = COALESCE(NULLIF(s.handle_seconds, 0), v.handling_seconds, TIMESTAMPDIFF(SECOND, v.visit_time, s.payment_date), 1),
            s.handle_started_at = COALESCE(s.handle_started_at, DATE_SUB(COALESCE(s.handle_completed_at, s.payment_date), INTERVAL COALESCE(NULLIF(s.handle_seconds, 0), v.handling_seconds, TIMESTAMPDIFF(SECOND, v.visit_time, s.payment_date), 1) SECOND))
        WHERE s.handle_seconds IS NULL OR s.handle_seconds = 0 OR s.handle_started_at IS NULL OR s.handle_completed_at IS NULL", $messages, 'Backfilled sales handle start/end/seconds');
    runIfIndexMissing($conn, 'sales', 'idx_sales_handle_seconds', "ALTER TABLE sales ADD KEY idx_sales_handle_seconds (handle_seconds)", $messages);
    runIfIndexMissing($conn, 'sales', 'idx_sales_handle_times', "ALTER TABLE sales ADD KEY idx_sales_handle_times (handle_started_at, handle_completed_at)", $messages);
    runIfIndexMissing($conn, 'sales', 'idx_sales_report_date_status', "ALTER TABLE sales ADD KEY idx_sales_report_date_status (payment_date, payment_status, outlet_id, agent_id)", $messages);
    runIfIndexMissing($conn, 'visitors', 'idx_visitors_report_date_status', "ALTER TABLE visitors ADD KEY idx_visitors_report_date_status (visit_time, status, outlet_id, agent_id, service_id, purpose_id)", $messages);
    runIfConstraintMissing($conn, 'fk_visitors_complaint_reason', "ALTER TABLE visitors ADD CONSTRAINT fk_visitors_complaint_reason FOREIGN KEY (complaint_reason_id) REFERENCES complaint_reasons(id) ON DELETE SET NULL", $messages);

    // Owner role has admin access, but it is not shown to normal users.
    // Actions are separated into super_audit_log so normal Admin screens do not mix them with ordinary audit logs.
    runUpgradeQuery($conn, "ALTER TABLE users MODIFY role ENUM('admin','super_admin','supervisor','agent') NOT NULL", $messages, 'Users role enum supports super_admin');


    if (function_exists('sisEnsureUserAccountColumns')) {
        sisEnsureUserAccountColumns($conn);
        $messages[] = ['ok', 'User account columns checked: email, profile photo, password expiry fields'];
        $uploadDir = dirname(__DIR__) . '/assets/uploads/users';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
        if (is_dir($uploadDir)) {
            @chmod($uploadDir, 0777);
            clearstatcache(true, $uploadDir);
        }
        $htaccess = $uploadDir . '/.htaccess';
        if (is_dir($uploadDir) && !is_file($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|php[0-9]|phtml|phar|cgi|pl|py|jsp|asp|aspx|sh)$\">\n    Require all denied\n</FilesMatch>\n");
        }
        $messages[] = [is_dir($uploadDir) && is_writable($uploadDir) ? 'ok' : 'warn', 'Profile photo upload folder checked: assets/uploads/users'];
    }
    if (tableColumnExists($conn, 'users', 'password_changed_at')) {
        $createdExpr = tableColumnExists($conn, 'users', 'created_at') ? 'created_at' : 'NOW()';
        runUpgradeQuery($conn, "UPDATE users SET password_changed_at = COALESCE(password_changed_at, " . $createdExpr . ", NOW()) WHERE password_changed_at IS NULL", $messages, 'Backfilled password change dates');
    }
    runIfIndexMissing($conn, 'users', 'idx_users_role_active', "ALTER TABLE users ADD KEY idx_users_role_active (role, is_active)", $messages);

    runUpgradeQuery($conn, "CREATE TABLE IF NOT EXISTS sis_db_console_log (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NULL,
        query_text TEXT NOT NULL,
        query_type VARCHAR(20) NULL,
        affected_rows INT NULL,
        executed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) NULL,
        PRIMARY KEY (id),
        KEY idx_db_console_user_time (user_id, executed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", $messages, 'Owner database console log ready');

    runUpgradeQuery($conn, "CREATE TABLE IF NOT EXISTS super_audit_log (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NULL,
        action_type VARCHAR(120) NOT NULL,
        record_id INT NULL,
        details TEXT NULL,
        timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        PRIMARY KEY (id),
        KEY idx_super_audit_user_time (user_id, timestamp),
        KEY idx_super_audit_action_time (action_type, timestamp),
        CONSTRAINT fk_super_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", $messages, 'Owner audit log table ready');

    runIfIndexMissing($conn, 'agent_shifts', 'idx_agent_shifts_active', "ALTER TABLE agent_shifts ADD KEY idx_agent_shifts_active (agent_id, shift_date, logout_time)", $messages);
    runIfIndexMissing($conn, 'agent_shifts', 'idx_agent_shifts_date_status', "ALTER TABLE agent_shifts ADD KEY idx_agent_shifts_date_status (shift_date, logout_time)", $messages);

    if (function_exists('ensureShiftAssignmentsTable') && ensureShiftAssignmentsTable($conn)) {
        $messages[] = ['ok', 'Shift assignment table ready'];
    }
    if (function_exists('sisEnsureAdvancedShiftTables') && sisEnsureAdvancedShiftTables($conn)) {
        $messages[] = ['ok', 'Advanced shift request/overtime/login-access tables and notifications ready'];
    }
    if (function_exists('sisEnsureAnnouncementTables') && sisEnsureAnnouncementTables($conn)) {
        $messages[] = ['ok', 'Announcement tables ready: announcements and announcement_reads'];
    }
    runUpgradeQuery($conn, "UPDATE settings SET setting_value = 'D' WHERE setting_key IN ('currency','currency_symbol')", $messages, 'Currency set to Dalasis symbol D');
    runUpgradeQuery($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('shift_late_grace_minutes','15') ON DUPLICATE KEY UPDATE setting_value = setting_value", $messages, 'Shift late grace setting ready');

    // Never create or reactivate a predictable owner account during an upgrade.
    // Owner accounts must be created explicitly from Users & Roles with a unique password.
    $messages[] = ['ok', 'Existing owner accounts preserved; no default privileged account was created'];



    // Complaint / Support dynamic form module.
    runUpgradeQuery($conn, "CREATE TABLE IF NOT EXISTS support_form_fields (
        id INT NOT NULL AUTO_INCREMENT,
        field_label VARCHAR(150) NOT NULL,
        field_key VARCHAR(80) NOT NULL,
        field_type ENUM('text','textarea','select','number','date','phone','email') NOT NULL DEFAULT 'text',
        field_options TEXT NULL,
        is_required TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_support_field_key (field_key),
        KEY idx_support_fields_active (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", $messages, 'Support form fields table ready');

    runUpgradeQuery($conn, "CREATE TABLE IF NOT EXISTS support_tickets (
        id INT NOT NULL AUTO_INCREMENT,
        ticket_no VARCHAR(40) NOT NULL,
        submitted_by INT NULL,
        submitted_role VARCHAR(30) NULL,
        outlet_id INT NULL,
        customer_name VARCHAR(120) NULL,
        customer_phone VARCHAR(30) NULL,
        category VARCHAR(80) NOT NULL DEFAULT 'Complaint',
        priority ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
        status ENUM('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
        main_reason VARCHAR(100) NULL,
        sub_reason_id INT NULL,
        subject VARCHAR(180) NULL,
        description TEXT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_support_ticket_no (ticket_no),
        KEY idx_support_date_status (created_at, status),
        KEY idx_support_user (submitted_by, submitted_role),
        KEY idx_support_outlet (outlet_id),
        KEY idx_support_reason (main_reason, sub_reason_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", $messages, 'Support tickets table ready');

    runIfColumnMissing($conn, 'support_tickets', 'main_reason', "ALTER TABLE support_tickets ADD COLUMN main_reason VARCHAR(100) NULL AFTER status", $messages);
    runIfColumnMissing($conn, 'support_tickets', 'sub_reason_id', "ALTER TABLE support_tickets ADD COLUMN sub_reason_id INT NULL AFTER main_reason", $messages);
    runIfIndexMissing($conn, 'support_tickets', 'idx_support_reason', "ALTER TABLE support_tickets ADD KEY idx_support_reason (main_reason, sub_reason_id)", $messages);

    runUpgradeQuery($conn, "CREATE TABLE IF NOT EXISTS support_ticket_values (
        id INT NOT NULL AUTO_INCREMENT,
        ticket_id INT NOT NULL,
        field_id INT NOT NULL,
        field_label VARCHAR(150) NOT NULL,
        field_value TEXT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_support_values_ticket (ticket_id),
        KEY idx_support_values_field (field_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", $messages, 'Support ticket values table ready');

    runIfConstraintMissing($conn, 'fk_support_values_ticket', "ALTER TABLE support_ticket_values ADD CONSTRAINT fk_support_values_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE", $messages);
    runIfConstraintMissing($conn, 'fk_support_values_field', "ALTER TABLE support_ticket_values ADD CONSTRAINT fk_support_values_field FOREIGN KEY (field_id) REFERENCES support_form_fields(id) ON DELETE CASCADE", $messages);

    $supportDefaults = [
        ['MSISDN / Customer Number', 'msisdn_customer_number', 'phone', '', 0, 1, 20],
        ['Problem Details', 'problem_details', 'textarea', '', 1, 1, 30],
        ['Action Taken', 'action_taken', 'textarea', '', 0, 1, 40],
        ['Follow-up Required', 'follow_up_required', 'select', "No
Yes", 0, 1, 50],
    ];
    $supportStmt = mysqli_prepare($conn, "INSERT IGNORE INTO support_form_fields (field_label, field_key, field_type, field_options, is_required, is_active, sort_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($supportStmt) {
        $adminUserId = (int)($_SESSION['user_id'] ?? 0);
        foreach ($supportDefaults as $d) {
            mysqli_stmt_bind_param($supportStmt, 'ssssiiii', $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $adminUserId);
            mysqli_stmt_execute($supportStmt);
        }
        $messages[] = ['ok', 'Default Complaint / Support form fields inserted/checked'];
    }

    if (function_exists('supportEnsureTables') && supportEnsureTables($conn)) {
        $messages[] = ['ok', 'Complaint / Support Main Reason and Sub Reason tables repaired'];
    }

    auditAction($conn, 'RUN_SIS_UPGRADE', $_SESSION['user_id'], 'SIS upgrade runner executed');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIS Upgrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
<main class="container py-5">
    <div class="card">
        <div class="card-body">
            <h3 class="mb-3">SIS Database Upgrade</h3>
            <p>This safe upgrade creates Main Reason/Sub Reason dropdowns for Complaint/Support, dynamic support fields, handling-time columns, advanced shift scheduling, early-end requests, overtime notifications, announcement tables, report indexes, and owner audit separation. Back up the database before running in production.</p>
            <form method="post">
                <?php echo csrfField(); ?>
                <button class="btn btn-primary">Run Upgrade</button>
                <a class="btn btn-outline-secondary" href="dashboard.php">Back</a>
            </form>
            <?php if($messages): ?>
                <hr><h5>Results</h5>
                <ul class="list-group">
                    <?php foreach($messages as $m): ?>
                        <li class="list-group-item <?php echo $m[0]==='ok'?'list-group-item-success':'list-group-item-warning'; ?>"><code><?php echo e($m[1]); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
