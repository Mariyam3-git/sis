<?php
// agent/add_sale.php
// Compatibility redirect. The maintained Agent Add Customer / Sale workflow is agent/sales.php.
require_once '../config.php';
requireLogin();

if (hasRole('agent')) {
    redirectTo(appPath('agent/sales.php'));
}
if (hasRole('supervisor')) {
    redirectTo(appPath('supervisor/add_sale.php'));
}
redirectTo(appPath('dashboard.php'));
