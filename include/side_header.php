<?php
require_once __DIR__ . '/navbar.php';
require_once __DIR__ . '/footer.php';
require_once __DIR__ . '/shift_helpers.php';

if (!function_exists('sisMenuSections')) {
    function sisMenuSections($role) {
        $role = strtolower((string)$role);
        if ($role === 'agent') {
            return [
                ['title' => 'Agent Workspace', 'items' => [
                    ['dashboard.php', 'bi-speedometer2', 'Dashboard'],
                    ['../announcements.php', 'bi-megaphone', 'Announcements'],
                    ['../help.php', 'bi-question-circle', 'Help Center'],
                    ['sales.php', 'bi-person-plus', 'Add Customer / Sale'],
                    ['support.php', 'bi-headset', 'Complaint / Support'],
                    ['my_shift.php', 'bi-calendar-check', 'My Shift'],
                    ['stock.php', 'bi-box-seam', 'Outlet Stock'],
                    ['performanceprofile.php', 'bi-graph-up', 'My Performance'],
                    ['my_services.php', 'bi-grid-3x3-gap', 'My Services'],
                    ['cancelled_sales.php', 'bi-shield-check', 'My Cancelled Sales'],
                    ['../profile.php', 'bi-person-circle', 'My Account'],
                ]],
            ];
        }
        if ($role === 'supervisor') {
            return [
                ['title' => 'Supervisor Workspace', 'items' => [
                    ['dashboard.php', 'bi-speedometer2', 'Dashboard'],
                    ['../announcements.php', 'bi-megaphone', 'Announcements'],
                    ['../help.php', 'bi-question-circle', 'Help Center'],
                    ['performanceprofile.php', 'bi-graph-up', 'My Performance'],
                    ['my_services.php', 'bi-grid-3x3-gap', 'My Services'],
                    ['../profile.php', 'bi-person-circle', 'My Account'],
                    ['add_sale.php', 'bi-stopwatch', 'Start Customer / Add Sale'],
                    ['support.php', 'bi-headset', 'Complaint / Support'],
                ]],
                ['title' => 'Reports', 'items' => [
                    ['agent_performance.php', 'bi-bar-chart', 'Agent Report'],
                    ['staff_services.php', 'bi-people', 'Staff Services'],
                    ['pending_payments.php', 'bi-clock-history', 'Pending Payments'],
                    ['visitors.php', 'bi-people', 'Visitor Log'],
                    ['service_analysis.php', 'bi-pie-chart', 'Service Analysis'],
                    ['cancelled_sales.php', 'bi-shield-check', 'Cancelled Sales Audit'],
                    ['cash_reconciliation.php', 'bi-calculator', 'Cash Reconciliation'],
                    ['shift_report.php', 'bi-clipboard-data', 'Shift Report'],
                    ['shift_requests.php', 'bi-bell', 'Shift Requests'],
                ]],
                ['title' => 'Stock, Shifts & Targets', 'items' => [
                    ['stock_dashboard.php', 'bi-boxes', 'Stock Dashboard'],
                    ['stock_requests.php', 'bi-arrow-left-right', 'Stock Requests'],
                    ['stock.php', 'bi-box-seam', 'Stock'],
                    ['shift_allocation.php', 'bi-calendar2-week', 'Shift Allocation'],
                    ['targets.php', 'bi-bullseye', 'Targets'],
                ]],
            ];
        }
        $sections = [
            ['title' => 'Main', 'items' => [
                ['dashboard.php', 'bi-speedometer2', 'Dashboard'],
                ['announcements.php', 'bi-megaphone', 'Announcements'],
                ['../help.php', 'bi-question-circle', 'Help Center'],
                ['../profile.php', 'bi-person-circle', 'My Account'],
                ['users.php', 'bi-person-gear', 'Users & Roles'],
                ['outlets.php', 'bi-building', 'Outlets'],
                ['agents.php', 'bi-person-badge', 'Agents'],
                ['supervisors.php', 'bi-people', 'Supervisors'],
            ]],
            ['title' => 'Customer Care Setup', 'items' => [
                ['services.php', 'bi-grid', 'Services'],
                ['purposes.php', 'bi-tags', 'Visit Purposes'],
                ['purpose_categories.php', 'bi-folder', 'Purpose Categories'],
                ['complaint_reasons.php', 'bi-chat-left-text', 'Main/Sub Reasons'],
                ['support_forms.php', 'bi-ui-checks-grid', 'Complaint / Support Form Builder'],
            ]],
            ['title' => 'Reports', 'items' => [
                ['sales.php', 'bi-cash-stack', 'Sales Report'],
                ['reports.php', 'bi-file-earmark-bar-graph', 'Advanced KPI Report'],
                ['visitors.php', 'bi-person-lines-fill', 'Visitor Log'],
                ['support_reports.php', 'bi-headset', 'Complaint / Support Reports'],
                ['daily_showroom_report.php', 'bi-clipboard-data', 'Daily Showroom Report'],
                ['shift_report.php', 'bi-calendar-check', 'Shift Report'],
                ['shift_requests.php', 'bi-bell', 'Shift Requests'],
                ['shift_allocation.php', 'bi-calendar2-week', 'Shift Allocation'],
                ['reconciliation.php', 'bi-calculator', 'Cash Reconciliation'],
                ['visitor_analytics.php', 'bi-graph-up-arrow', 'Visitor Analytics'],
                ['cancelled_sales.php', 'bi-shield-check', 'Cancelled Sales Audit'],
            ]],
            ['title' => 'Stock Management', 'items' => [
                ['stock_dashboard.php', 'bi-boxes', 'Stock Dashboard'],
                ['stock_products.php', 'bi-box', 'Products'],
                ['stock_categories.php', 'bi-tags', 'Stock Categories'],
                ['stock_receive.php', 'bi-box-arrow-in-down', 'Receive Stock'],
                ['stock_transfer.php', 'bi-arrow-left-right', 'Transfer Stock'],
                ['stock_transferrecennt.php', 'bi-clock-history', 'Recent Transfers'],
                ['stock_adjust.php', 'bi-pencil-square', 'Stock Adjustments'],
                ['stock_requests.php', 'bi-send', 'Stock Requests'],
                ['stock_alerts.php', 'bi-exclamation-triangle', 'Stock Alerts'],
                ['stock_reports.php', 'bi-file-bar-graph', 'Stock Reports'],
                ['threshold_sethings.php', 'bi-sliders', 'Stock Thresholds'],
            ]],
        ];

        if ($role === 'super_admin') {
            $sections[] = ['title' => 'Owner Tools & System', 'items' => [
                ['targets.php', 'bi-bullseye', 'Targets'],
                ['settings.php', 'bi-gear', 'Settings'],
                ['audit_logs.php', 'bi-journal-text', 'Audit Logs'],
                ['super_audit_logs.php', 'bi-shield-lock', 'Owner Audit Logs'],
                ['run_upgrade.php', 'bi-database-gear', 'Run Upgrade'],
                ['page_access.php', 'bi-unlock', 'Page Access Control'],
                ['database_console.php', 'bi-database-check', 'Database Console'],
                ['handle_timer_debug.php', 'bi-stopwatch', 'Handle Time Debug'],
            ]];
        }

        return $sections;
    }
}



if (!function_exists('sisSidebarResolvedHref')) {
    function sisSidebarResolvedHref($menuRole, $href) {
        $href = trim((string)$href);
        if ($href === '' || preg_match('~^(https?:|mailto:|tel:|javascript:|#)~i', $href)) return $href;
        $href = str_replace('\\', '/', $href);
        if (strpos($href, '../') === 0) {
            return appPath(substr($href, 3));
        }
        if (strpos($href, '/') === 0) {
            return appPath(ltrim($href, '/'));
        }
        if (strpos($href, '/') !== false) {
            return appPath($href);
        }
        $menuRole = strtolower((string)$menuRole);
        if (in_array($menuRole, ['admin','supervisor','agent'], true)) {
            return appPath($menuRole . '/' . $href);
        }
        return appPath($href);
    }
}

if (!function_exists('sisSidebarAccessKey')) {
    function sisSidebarAccessKey($menuRole, $href) {
        $href = trim((string)$href);
        if ($href === '' || preg_match('~^(https?:|mailto:|tel:|javascript:|#)~i', $href)) return $href;
        $href = str_replace('\\', '/', $href);
        while (strpos($href, '../') === 0) $href = substr($href, 3);
        $href = ltrim($href, '/');
        if (strpos($href, '/') === false && in_array($menuRole, ['admin','supervisor','agent'], true)) {
            return $menuRole . '/' . $href;
        }
        return $href;
    }
}

if (!function_exists('sisDashboardHref')) {
    function sisDashboardHref($role) {
        $role = strtolower((string)$role);
        if ($role === 'admin' || $role === 'super_admin') return appPath('admin/dashboard.php');
        if ($role === 'supervisor') return appPath('supervisor/dashboard.php');
        if ($role === 'agent') return appPath('agent/dashboard.php');
        return appPath('dashboard.php');
    }
}

if (!function_exists('sisRenderSideHeader')) {
    function sisRenderSideHeader($role = '', $active = null) {
        $role = strtolower((string)($role ?: ($_SESSION['role'] ?? '')));
        $actualRole = strtolower((string)($_SESSION['role'] ?? $role));
        $menuRole = ($role === 'super_admin') ? 'admin' : $role;
        $active = $active ?: basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
        $roleLabel = function_exists('sisDisplayRole') ? sisDisplayRole($actualRole) : ucfirst(str_replace('_', ' ', $actualRole));
        $shiftRequestCount = 0;
        if (function_exists('sisSupervisorPendingShiftCount') && isset($GLOBALS['conn'])) {
            if ($menuRole === 'supervisor') {
                $shiftRequestCount = sisSupervisorPendingShiftCount($GLOBALS['conn'], $_SESSION['outlet_id'] ?? null);
            } elseif ($menuRole === 'admin') {
                $shiftRequestCount = sisSupervisorPendingShiftCount($GLOBALS['conn'], null);
            }
        }
        ?>
        <aside class="sis-side-header" data-sis-sidebar>
            <div class="sis-side-brand">
                <a href="<?php echo sisDashboardHref($menuRole); ?>" class="sis-logo sis-logo-with-mark sis-logo-image-only"><img src="<?php echo e(appAsset('assets/images/logo.svg')); ?>" alt="Company logo"></a>
                <small><?php echo e($roleLabel); ?> Panel</small>
            </div>
            <?php if($menuRole === 'admin'): ?>
            <div class="sis-sidebar-search">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Search admin menu..." data-sis-sidebar-search>
            </div>
            <?php endif; ?>
            <nav class="sis-side-nav">
                <?php foreach (sisMenuSections($actualRole === 'super_admin' ? 'super_admin' : $menuRole) as $section): ?>
                    <div class="sis-side-section">
                        <div class="sis-side-section-title"><?php echo e($section['title']); ?></div>
                        <?php foreach ($section['items'] as $item): ?>
                            <?php [$href, $icon, $label] = $item; $accessHref = sisSidebarAccessKey($menuRole, $href); if (function_exists('sisPageAccessAllowedForRole') && !sisPageAccessAllowedForRole($actualRole, $accessHref)) { continue; } $resolvedHref = sisSidebarResolvedHref($menuRole, $href); $isActive = basename($accessHref) === $active || sisCurrentPageKey() === sisPageKeyFromHref($accessHref); ?>
                            <a href="<?php echo e($resolvedHref); ?>" class="sis-side-link <?php echo $isActive ? 'active' : ''; ?>">
                                <i class="bi <?php echo e($icon); ?>"></i>
                                <span><?php echo e($label); ?><?php if ($href === 'shift_requests.php' && $shiftRequestCount > 0): ?> <span class="badge bg-danger ms-1"><?php echo (int)$shiftRequestCount; ?></span><?php endif; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="sis-side-footer">
                <div class="sis-side-user"><?php echo e($_SESSION['full_name'] ?? 'User'); ?></div>
                <a href="<?php echo ($_SESSION['role'] ?? '') === 'agent' ? appPath('agent/shift_logout.php') : appPath('logout.php?confirm=1'); ?>" class="sis-side-logout" data-confirm-logout><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </aside>
        <?php
    }
}

if (!function_exists('sisRenderLayoutStart')) {
    function sisRenderLayoutStart($role = '', $title = null, $subtitle = null) {
        $role = strtolower((string)($role ?: ($_SESSION['role'] ?? '')));
        echo "\n    <script>(function(){try{var t=localStorage.getItem('sis-theme')||'light';document.documentElement.dataset.theme=(t==='dark'?'dark':'light');document.documentElement.setAttribute('data-bs-theme',document.documentElement.dataset.theme);}catch(e){document.documentElement.dataset.theme='light';}})();</script>\n    <div class=\"sis-app-layout\">\n";
        sisRenderSideHeader($role);
        echo "\n        <main class=\"sis-main-panel\">\n";
        sisRenderNavbar($role, $title, $subtitle);
        echo "\n            <section class=\"sis-content-area\">\n";
    }
}