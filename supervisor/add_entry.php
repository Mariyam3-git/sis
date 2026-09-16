<?php
// supervisor/add_entry.php
// Compatibility redirect. The maintained Supervisor Add Customer / Sale workflow
// is supervisor/add_sale.php, which includes the Agent-style form, edit actions,
// timer fields, and same-customer workflow.
require_once '../config.php';
requireLogin();
if (hasRole('supervisor')) {
    redirectTo(appPath('supervisor/add_sale.php'));
}
redirectTo(roleDashboardPath($_SESSION['role'] ?? ''));
