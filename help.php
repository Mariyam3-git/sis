<?php
require_once __DIR__ . '/config.php';
requireLogin();
if (function_exists('sisRequirePageAccess')) { sisRequirePageAccess(); }
$role = strtolower((string)($_SESSION['role'] ?? ''));
$isAdminHelp = in_array($role, ['admin','super_admin'], true);

$guide = [
    'agent' => [
        'title' => 'Agent Guide',
        'subtitle' => 'Daily customer service, sales, support, stock and shift actions for Agents.',
        'sections' => [
            'Daily Work' => [
                ['Dashboard / Add Customer Sale', 'Start your shift, open Customer Service Timer, add GSM, select purpose/service, confirm quantity/amount, and submit. The timer stops automatically on submit.'],
                ['Same Customer / Multiple Services', 'Use the same-customer option from today’s records when one customer needs more than one service. This keeps the customer flow connected.'],
                ['Edit Sale Record', 'Use Edit on today’s transaction list to correct service, purpose, quantity, amount, status, or notes. GSM/contact remains locked to protect the customer trail.'],
                ['Mark Payment / Pending Payment', 'Paid services can be marked Paid, Pending or Unpaid depending on collection status. Free services are completed automatically.'],
            ],
            'Support & Complaints' => [
                ['Complaint / Support', 'Choose the Main Reason and Sub Reason, complete the visible form fields, and submit. Admin controls which extra fields appear.'],
                ['Support Notes', 'Add clear notes so Supervisor/Admin can understand what happened without calling back the customer.'],
            ],
            'Shift & Account' => [
                ['My Shift', 'View your assigned shift, start/end status, overtime prompts and early-end approval status.'],
                ['Outlet Stock', 'Check stock items available at your outlet and avoid promising items that are out of stock.'],
                ['My Performance', 'Review your sales, visitor count, conversion and performance trend.'],
                ['My Account / Password', 'Upload your profile photo and change your password. After Admin resets your password, you must change it before continuing.'],
                ['Announcements', 'Read company announcements from the board or dashboard banner.'],
                ['Help Center', 'This page shows only the guide relevant to your account role.'],
            ],
        ],
    ],
    'supervisor' => [
        'title' => 'Supervisor Guide',
        'subtitle' => 'Team management, customer service, approvals, stock and reports for Supervisors.',
        'sections' => [
            'Customer Service & Sales' => [
                ['Dashboard', 'Monitor today’s outlet activity, team performance and key alerts.'],
                ['Start Customer / Add Sale', 'Use the same guided customer service form as Agents: open the timer, add GSM, select purpose/service, confirm amount/quantity and submit.'],
                ['Sales Transactions', 'Review and edit outlet sales records, payment status and notes. Edits are stamped for audit history.'],
                ['Complaint / Support', 'Record or review support issues using the Admin-configured reason and form fields.'],
            ],
            'Team & Shift Control' => [
                ['Agent Report', 'Review agent performance, transaction counts, sales amounts and conversion.'],
                ['Visitor Log', 'Track outlet visitors and service completion status.'],
                ['Shift Allocation', 'Assign agents to Morning, Afternoon or Night shifts.'],
                ['Shift Requests', 'Approve or reject early-end, overtime and login-access requests.'],
                ['Shift Report', 'Review attendance, login/logout times and exceptions.'],
                ['Targets', 'Track outlet and agent targets where configured.'],
            ],
            'Cash & Stock' => [
                ['Pending Payments', 'Check pending/unpaid payments and follow up before reconciliation.'],
                ['Cash Reconciliation', 'Compare collected money, withdrawals, pending amounts and expected closing figures.'],
                ['Stock Dashboard', 'Monitor stock levels and low-stock risks.'],
                ['Stock Requests / Stock', 'Request stock and view outlet stock availability.'],
            ],
            'Account' => [
                ['My Performance', 'See team trends and agent ranking for your outlet.'],
                ['My Account / Password', 'Upload your profile photo and change your password. Reset passwords must be changed at next login.'],
                ['Announcements', 'Read official updates and operational instructions.'],
            ],
        ],
    ],
    'admin' => [
        'title' => 'Admin & System Owner Guide',
        'subtitle' => 'Full configuration, users, outlets, services, reports, stock and system control.',
        'sections' => [
            'Main Administration' => [
                ['Dashboard', 'Review system-wide KPIs, alerts and operational summary.'],
                ['Announcements', 'Create, edit, publish and archive announcements for users.'],
                ['Users & Roles', 'Search users, double-click a user row for full details, edit account data, reset password, activate/deactivate accounts and manage roles.'],
                ['Outlets', 'Create/edit outlets, manage active/inactive status and region grouping.'],
                ['Agents', 'Create, edit, activate/deactivate and assign agents to outlets and shifts.'],
                ['Supervisors', 'Create, edit, activate/deactivate and assign supervisors to outlets and shifts.'],
            ],
            'Customer Care Setup' => [
                ['Services', 'Create paid/free services, fixed/flexible pricing, quantity settings and activation status.'],
                ['Visit Purposes', 'Create the purpose options Agents/Supervisors select during customer service.'],
                ['Purpose Categories', 'Group purposes for cleaner selection and reporting.'],
                ['Main/Sub Reasons', 'Configure complaint/support main reasons and sub reasons.'],
                ['Complaint / Support Form Builder', 'Create extra support form fields and control what users see.'],
            ],
            'Reports' => [
                ['Sales Report', 'Review paid, pending, unpaid, withdrawals and transaction details.'],
                ['Advanced KPI Report', 'Filter KPI data by date, outlet, service and role.'],
                ['Visitor Log', 'Audit customer visits, handling status and service history.'],
                ['Complaint / Support Reports', 'Analyze support reasons, complaints and form responses.'],
                ['Daily Showroom Report', 'Daily outlet performance and shift-separated report.'],
                ['Shift Report', 'Attendance, shifts, overtime and login/logout tracking.'],
                ['Shift Requests', 'Approve/decline supervisor or agent shift requests.'],
                ['Shift Allocation', 'Assign and edit scheduled shifts.'],
                ['Cash Reconciliation', 'Compare collections, pending payments and withdrawals.'],
                ['Visitor Analytics', 'Analyze visitor trends and conversion.'],
                ['Handle Time Debug', 'Check timer start/end/seconds records when troubleshooting.'],
            ],
            'Stock Management' => [
                ['Stock Dashboard', 'View stock health, low-stock alerts and movement summary.'],
                ['Stock Products / Categories', 'Create product catalog and group stock items.'],
                ['Receive Stock', 'Record stock received into outlets.'],
                ['Transfer Stock / Recent Transfers', 'Move stock between outlets and confirm transfer trail.'],
                ['Stock Adjustments', 'Correct stock counts with reason notes.'],
                ['Stock Requests', 'Review requested stock from supervisors/agents.'],
                ['Stock Alerts / Reports / Thresholds', 'Manage low-stock thresholds, alerts and exportable reports.'],
            ],
            'System Owner Tools' => [
                ['Targets', 'Set and monitor performance targets.'],
                ['Settings', 'Configure session timeout, password expiry and system preferences.'],
                ['Audit Logs / Owner Audit Logs', 'Review system actions and sensitive owner-level events.'],
                ['Run Upgrade', 'Apply safe database upgrades after replacing files.'],
                ['Page Access Control', 'Control which pages each normal role can open.'],
                ['Database Console', 'Owner-only advanced database inspection tool.'],
            ],
        ],
    ],
];

$visibleGuides = [];
if ($isAdminHelp) {
    $visibleGuides['admin'] = $guide['admin'];
    $visibleGuides['supervisor'] = $guide['supervisor'];
    $visibleGuides['agent'] = $guide['agent'];
} elseif ($role === 'supervisor') {
    $visibleGuides['supervisor'] = $guide['supervisor'];
} else {
    $visibleGuides['agent'] = $guide['agent'];
}

$commonFixes = [
    ['Password reset does not let user continue', 'Admin should reset the password from Users & Roles. The user logs in with the temporary password, opens My Account automatically, enters the temporary password as Current Password, then creates a new password.'],
    ['Profile photo will not upload', 'Use JPG, PNG, WEBP or GIF under 3MB. If it still fails, Admin should check that assets/uploads/users is writable.'],
    ['Page says access denied', 'The role may not have access. System Owner can review Page Access Control.'],
    ['Dark/light theme looks wrong', 'Use the icon in the top bar to switch theme. Refresh once after replacing files so the new CSS loads.'],
    ['Duplicate form submission blocked', 'The same completed request was submitted again. Refresh the current page before trying another action.'],
    ['Timer did not start', 'Open Add Customer / Sale using the Start Attending Customer button, not a bookmarked modal URL.'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Help Center - Showroom Information System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once __DIR__ . '/include/side_header.php'; sisRenderLayoutStart($role, 'Help Center', $isAdminHelp ? 'Complete user guide for all roles' : 'Guide for your account role'); ?>
<div class="container-fluid px-0">
    <div class="sis-module-hero">
        <div>
            <span class="sis-kicker"><i class="bi bi-question-circle"></i> User Guide</span>
            <h2>How to use the Showroom Information System</h2>
            <p><?php echo $isAdminHelp ? 'Admin and System Owner accounts can see every role guide below.' : 'This help page is filtered to your role so you only see the pages you need.'; ?></p>
        </div>
        <div class="sis-module-hero-icon"><i class="bi bi-life-preserver"></i></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="sis-mini-stat"><span>Your Role</span><strong><?php echo e(sisDisplayRole($role)); ?></strong></div></div>
        <div class="col-md-4"><div class="sis-mini-stat"><span>Guide Scope</span><strong><?php echo $isAdminHelp ? 'All Pages' : 'Your Pages'; ?></strong></div></div>
        <div class="col-md-4"><div class="sis-mini-stat"><span>Support</span><strong>Step-by-step</strong></div></div>
    </div>

    <?php foreach ($visibleGuides as $guideKey => $g): ?>
        <div class="card sis-help-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1"><i class="bi bi-<?php echo $guideKey === 'admin' ? 'shield-lock' : ($guideKey === 'supervisor' ? 'people' : 'person-badge'); ?> text-primary"></i> <?php echo e($g['title']); ?></h4>
                    <small class="text-muted"><?php echo e($g['subtitle']); ?></small>
                </div>
                <span class="badge bg-primary"><?php echo e(ucfirst($guideKey)); ?></span>
            </div>
            <div class="card-body">
                <div class="accordion" id="guide_<?php echo e($guideKey); ?>">
                    <?php $i=0; foreach ($g['sections'] as $sectionTitle => $items): $i++; $cid = 'guide_' . $guideKey . '_' . $i; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="h_<?php echo e($cid); ?>">
                                <button class="accordion-button <?php echo $i === 1 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo e($cid); ?>" aria-expanded="<?php echo $i === 1 ? 'true' : 'false'; ?>">
                                    <?php echo e($sectionTitle); ?>
                                </button>
                            </h2>
                            <div id="<?php echo e($cid); ?>" class="accordion-collapse collapse <?php echo $i === 1 ? 'show' : ''; ?>" data-bs-parent="#guide_<?php echo e($guideKey); ?>">
                                <div class="accordion-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead><tr><th style="width:260px">Page / Feature</th><th>What to do</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($items as $item): ?>
                                                    <tr><td><strong><?php echo e($item[0]); ?></strong></td><td><?php echo e($item[1]); ?></td></tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="card sis-help-card mb-4">
        <div class="card-header"><h4 class="mb-0"><i class="bi bi-tools text-primary"></i> Common fixes</h4></div>
        <div class="card-body p-0">
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Problem</th><th>What to do</th></tr></thead><tbody>
            <?php foreach($commonFixes as $fix): ?><tr><td><strong><?php echo e($fix[0]); ?></strong></td><td><?php echo e($fix[1]); ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>

    <div class="alert alert-info"><i class="bi bi-info-circle"></i> For training: Admin/Superadmin should review all sections. Supervisors and Agents will automatically see only the sections meant for their account role.</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
