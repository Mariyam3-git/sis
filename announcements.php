<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/include/announcement_helpers.php';
requireLogin();
if (function_exists('sisRequirePageAccess')) { sisRequirePageAccess(); }
sisEnsureAnnouncementTables($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf()) {
        $action = $_POST['action'] ?? '';
        if ($action === 'mark_read') {
            sisMarkAnnouncementRead($conn, (int)($_POST['announcement_id'] ?? 0));
        } elseif ($action === 'mark_all_read') {
            sisMarkAllAnnouncementsRead($conn);
        }
    }
    redirectTo(appPath('announcements.php'));
}

$announcements = sisGetActiveAnnouncementsForCurrentUser($conn, 30);
$role = strtolower((string)($_SESSION['role'] ?? ''));
$titleRole = ucfirst(str_replace('_', ' ', $role));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcements - Showroom Information System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once __DIR__ . '/include/side_header.php'; sisRenderLayoutStart($role, 'Announcements', 'Management updates and notices'); ?>
<div class="container-fluid px-0">
    <div class="sis-page-heading-card mb-4">
        <div>
            <span class="sis-kicker"><i class="bi bi-megaphone-fill"></i> Announcement Board</span>
            <h2>Announcements for <?php echo e($titleRole); ?></h2>
            <p>Read important updates shared by management.</p>
        </div>
        <form method="post" class="m-0">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button class="btn btn-outline-primary"><i class="bi bi-check2-all"></i> Mark All Read</button>
        </form>
    </div>

    <?php if (!empty($announcements)): ?>
        <div class="row g-3">
            <?php foreach ($announcements as $a): $class = sisAnnouncementPriorityClass($a['priority'] ?? 'info'); ?>
            <div class="col-12">
                <article id="announcement-<?php echo (int)$a['id']; ?>" class="sis-announcement-card large <?php echo empty($a['is_read']) ? 'unread' : ''; ?> priority-<?php echo e($class); ?>">
                    <div class="sis-announcement-icon"><i class="bi <?php echo e(sisAnnouncementPriorityIcon($a['priority'] ?? 'info')); ?>"></i></div>
                    <div class="sis-announcement-body">
                        <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start">
                            <div>
                                <h3><?php echo e($a['title']); ?></h3>
                                <div class="d-flex gap-2 flex-wrap mb-2">
                                    <?php if (!empty($a['is_pinned'])): ?><span class="badge text-bg-dark"><i class="bi bi-pin-angle"></i> Pinned</span><?php endif; ?>
                                    <span class="badge text-bg-<?php echo e($class); ?>"><?php echo e(ucfirst($a['priority'] ?? 'info')); ?></span>
                                    <?php if (!empty($a['requires_ack'])): ?><span class="badge text-bg-light border text-dark"><i class="bi bi-check2-square"></i> Please read</span><?php endif; ?>
                                    <span class="badge text-bg-light border text-dark"><i class="bi bi-people"></i> <?php echo e(ucfirst(str_replace('_',' ', $a['audience'] ?? 'all'))); ?></span>
                                    <?php if (!empty($a['ends_at'])): ?><span class="badge text-bg-light border text-dark"><i class="bi bi-calendar-x"></i> Ends <?php echo e(sisAnnouncementDateLabel($a['ends_at'])); ?></span><?php endif; ?>
                                    <?php if (empty($a['is_read'])): ?><span class="badge text-bg-success">New</span><?php endif; ?>
                                </div>
                            </div>
                            <form method="post" class="m-0">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="announcement_id" value="<?php echo (int)$a['id']; ?>">
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2"></i> Mark Read</button>
                            </form>
                        </div>
                        <p><?php echo nl2br(e($a['message'])); ?></p>
                        <small><i class="bi bi-clock"></i> Posted <?php echo e(sisAnnouncementDateLabel($a['created_at'] ?? '')); ?></small>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="sis-empty-state-card">
            <i class="bi bi-megaphone"></i>
            <h3>No announcement yet</h3>
            <p>When a new announcement is published, it will appear here and on your dashboard.</p>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
