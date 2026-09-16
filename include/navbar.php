<?php
require_once __DIR__ . '/shift_helpers.php';
require_once __DIR__ . '/announcement_helpers.php';
if (!function_exists('sisPageTitle')) {
    function sisPageTitle($role = '') {
        $page = basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
        $titles = [
            'dashboard.php' => 'Dashboard',
            'dashboard1.php' => 'Add Customer / Sale (Legacy Redirect)',
            'add_sale.php' => 'Add Sale / Transaction',
            'add_entry.php' => 'Start Customer / Add Sale',
            'stock.php' => 'Stock Management',
            'stock_dashboard.php' => 'Stock Dashboard',
            'stock_requests.php' => 'Stock Requests',
            'cash_reconciliation.php' => 'Cash Reconciliation',
            'agent_performance.php' => 'Agent Performance',
            'pending_payments.php' => 'Pending Payments',
            'service_analysis.php' => 'Service Analysis',
            'visitors.php' => 'Visitor Log',
            'targets.php' => 'Targets',
            'support.php' => 'Complaint / Support',
            'profile.php' => 'My Account',
            'process_payment.php' => 'Process Payment',
            'agents.php' => 'Agents Management',
            'supervisors.php' => 'Supervisors Management',
            'outlets.php' => 'Outlets Management',
            'services.php' => 'Services Setup',
            'purposes.php' => 'Visit Purposes',
            'purpose_categories.php' => 'Purpose Categories',
            'complaint_reasons.php' => 'Main/Sub Reasons',
            'support_forms.php' => 'Complaint / Support Form Builder',
            'support_reports.php' => 'Complaint / Support Reports',
            'reports.php' => 'Advanced KPI Reports',
            'general_report.php' => 'General Report',
            'sales.php' => 'Sales Report',
            'daily_showroom_report.php' => 'Daily Services Report',
            'reconciliation.php' => 'Cash Reconciliation',
            'visitor_analytics.php' => 'Visitor Analytics',
            'handle_timer_debug.php' => 'Handle Time Debug',
            'stock_products.php' => 'Stock Products',
            'stock_categories.php' => 'Stock Categories',
            'stock_receive.php' => 'Receive Stock',
            'stock_transfer.php' => 'Stock Transfer',
            'stock_transferrecennt.php' => 'Recent Stock Transfers',
            'stock_adjust.php' => 'Stock Adjustments',
            'stock_alerts.php' => 'Stock Alerts',
            'stock_reports.php' => 'Stock Reports',
            'threshold_sethings.php' => 'Stock Thresholds',
            'settings.php' => 'Settings',
            'audit_logs.php' => 'Audit Logs',
            'super_audit_logs.php' => 'Owner Audit Logs',
            'run_upgrade.php' => 'Database Upgrade',
            'announcements.php' => 'Announcements',
            'page_access.php' => 'Page Access Control',
            'help.php' => 'Help Center',
        ];
        if ($page === 'sales.php' && strtolower((string)$role) === 'agent') {
            return 'Add Customer / Sale';
        }
        return $titles[$page] ?? ucwords(str_replace(['_', '.php'], [' ', ''], $page));
    }
}

if (!function_exists('sisNotificationHref')) {
    function sisNotificationHref($role, $url) {
        $url = trim((string)$url);
        return $url !== '' ? appPath($url) : roleDashboardPath($role);
    }
}

if (!function_exists('sisRenderNavbar')) {
    function sisRenderNavbar($role = '', $title = null, $subtitle = null) {
        $role = strtolower((string)($role ?: ($_SESSION['role'] ?? '')));
        $title = $title ?: sisPageTitle($role);
        $subtitle = $subtitle ?: date('l, d M Y');
        $user = $_SESSION['full_name'] ?? 'User';
        $userPhoto = function_exists('sisCurrentUserPhoto') ? sisProfilePhotoPath(sisCurrentUserPhoto()) : '';
        $notifications = [];
        $notificationCount = 0;
        $announcements = [];
        $announcementCount = 0;
        if (isset($GLOBALS['conn']) && function_exists('sisGetNotificationsForCurrentUser')) {
            $notifications = sisGetNotificationsForCurrentUser($GLOBALS['conn'], 8);
            $notificationCount = sisNotificationCountForCurrentUser($GLOBALS['conn']);
        }
        if (isset($GLOBALS['conn']) && function_exists('sisGetActiveAnnouncementsForCurrentUser')) {
            $announcements = sisGetActiveAnnouncementsForCurrentUser($GLOBALS['conn'], 6);
            $announcementCount = sisAnnouncementUnreadCountForCurrentUser($GLOBALS['conn']);
        }
        ?>
        <header class="sis-topbar">
            <button type="button" class="sis-sidebar-toggle" data-sis-sidebar-toggle aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="sis-topbar-title">
                <h1><?php echo e($title); ?></h1>
                <span><?php echo e($subtitle); ?></span>
            </div>
            <div class="sis-topbar-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm sis-icon-only" data-sis-theme-toggle aria-label="Toggle theme" title="Toggle theme">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <div class="dropdown sis-announcement-dropdown" data-sis-announcements data-api-url="<?php echo e(appPath('announcements_api.php')); ?>">
                    <button class="btn btn-light btn-sm sis-notification-btn sis-announcement-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Announcements">
                        <i class="bi bi-megaphone"></i>
                        <span class="sis-notification-badge sis-announcement-badge" data-announcement-count style="<?php echo $announcementCount > 0 ? '' : 'display:none'; ?>"><?php echo (int)$announcementCount; ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end sis-notification-menu sis-announcement-menu shadow">
                        <div class="sis-notification-head">
                            <strong>Announcements</strong>
                            <button type="button" class="btn btn-link btn-sm p-0" data-mark-all-announcements-read>Mark all read</button>
                        </div>
                        <div data-announcement-list>
                            <?php if(!empty($announcements)): foreach($announcements as $a): $priorityClass = function_exists('sisAnnouncementPriorityClass') ? sisAnnouncementPriorityClass($a['priority'] ?? 'info') : 'primary'; ?>
                                <a class="dropdown-item sis-notification-item sis-announcement-item <?php echo empty($a['is_read']) ? 'unread' : ''; ?>" href="<?php echo e(appPath('announcements.php')); ?>" data-announcement-id="<?php echo (int)$a['id']; ?>">
                                    <span class="sis-notification-dot bg-<?php echo e($priorityClass); ?>"></span>
                                    <span><strong><?php if (!empty($a['is_pinned'])): ?><span class="sis-mini-badge">Pinned</span> <?php endif; ?><?php echo e($a['title']); ?></strong><small><?php echo e(sisShortText($a['message'] ?? '', 95)); ?></small><em><?php if (!empty($a['requires_ack'])): ?><span class="sis-mini-badge">Read</span> <?php endif; ?><?php echo e(date('d/m/Y H:i', strtotime($a['created_at'] ?? 'now'))); ?></em></span>
                                </a>
                            <?php endforeach; else: ?>
                                <div class="sis-notification-empty"><i class="bi bi-check2-circle"></i> No active announcements</div>
                            <?php endif; ?>
                        </div>
                        <div class="border-top p-2 text-center"><a href="<?php echo e(appPath('announcements.php')); ?>" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-megaphone"></i> Open Announcement Board</a></div>
                    </div>
                </div>
                <div class="dropdown sis-notification-dropdown" data-sis-notifications data-api-url="<?php echo e(appPath('notifications_api.php')); ?>">
                    <button class="btn btn-light btn-sm sis-notification-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <span class="sis-notification-badge" data-notification-count style="<?php echo $notificationCount > 0 ? '' : 'display:none'; ?>"><?php echo (int)$notificationCount; ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end sis-notification-menu shadow">
                        <div class="sis-notification-head">
                            <strong>Notifications</strong>
                            <span class="d-flex gap-2 align-items-center">
                                <button type="button" class="btn btn-link btn-sm p-0" data-notification-sound-toggle title="Toggle notification sound"><i class="bi bi-volume-up"></i></button>
                                <button type="button" class="btn btn-link btn-sm p-0" data-mark-all-read>Mark all read</button>
                            </span>
                        </div>
                        <div data-notification-list>
                            <?php if(!empty($notifications)): foreach($notifications as $n): ?>
                                <a class="dropdown-item sis-notification-item <?php echo empty($n['is_read']) ? 'unread' : ''; ?>" href="<?php echo e(sisNotificationHref($role, $n['url'] ?? '')); ?>" data-notification-id="<?php echo (int)$n['id']; ?>">
                                    <span class="sis-notification-dot"></span>
                                    <span><strong><?php echo e($n['title']); ?></strong><small><?php echo e($n['message']); ?></small><em><?php echo e(date('d/m/Y H:i', strtotime($n['created_at']))); ?></em></span>
                                </a>
                            <?php endforeach; else: ?>
                                <div class="sis-notification-empty"><i class="bi bi-check2-circle"></i> No notifications</div>
                            <?php endif; ?>
                        </div>
                        <div class="border-top p-2 text-center"><button type="button" class="btn btn-sm btn-outline-secondary w-100" data-mark-all-read><i class="bi bi-check2-all"></i> Mark all notifications read</button></div>
                    </div>
                </div>
                <a href="<?php echo e(appPath('profile.php')); ?>" class="sis-user-chip text-decoration-none" title="My Account">
                    <span class="sis-user-avatar"><?php if(!empty($userPhoto)): ?><img src="<?php echo e($userPhoto); ?>" alt="Profile"><?php else: ?><?php echo e(strtoupper(substr((string)$user, 0, 1))); ?><?php endif; ?></span>
                    <div>
                        <strong><?php echo e($user); ?></strong>
                        <small><?php echo e(function_exists('sisDisplayRole') ? sisDisplayRole($role) : ucfirst(str_replace('_', ' ', $role))); ?></small>
                    </div>
                </a>
            </div>
        </header>
        <div class="toast-container position-fixed top-0 end-0 p-3 sis-navbar-toast" data-notification-toast-wrap></div>
        <?php
    }
}
