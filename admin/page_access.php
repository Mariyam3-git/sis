<?php
require_once '../config.php';
requireSuperAdmin();
sisEnsurePageAccessTable($conn);
$registry = sisPageRegistry();
$roles = ['admin' => 'Admin', 'supervisor' => 'Supervisor', 'agent' => 'Agent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        sisSetFlash('Security check failed. Please refresh and try again.', 'danger');
    } else {
        foreach ($registry as $pageKey => $meta) {
            if (sisLockedAccessPage($pageKey)) continue;
            foreach ($roles as $roleName => $roleLabel) {
                if (!in_array($roleName, $meta['roles'] ?? [], true)) continue;
                $allowed = isset($_POST['access'][$pageKey][$roleName]) ? 1 : 0;
                $stmt = mysqli_prepare($conn, "INSERT INTO page_access_rules (page_key, role_name, is_allowed, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed), updated_by=VALUES(updated_by), updated_at=CURRENT_TIMESTAMP");
                if ($stmt) {
                    $uid = (int)($_SESSION['user_id'] ?? 0);
                    mysqli_stmt_bind_param($stmt, 'ssii', $pageKey, $roleName, $allowed, $uid);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        auditAction($conn, 'UPDATE_PAGE_ACCESS_CONTROL', $_SESSION['user_id'] ?? null, 'Updated role page access rules');
        sisSetFlash('Page access rules updated successfully.', 'success');
    }
    redirectTo('page_access.php');
}

$current = [];
$res = mysqli_query($conn, "SELECT page_key, role_name, is_allowed FROM page_access_rules");
if ($res) while ($row = mysqli_fetch_assoc($res)) $current[$row['page_key']][$row['role_name']] = (int)$row['is_allowed'];
$flash = sisGetFlash();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page Access Control - Showroom Information System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
<link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('super_admin', 'Page Access Control', 'Decide which pages normal roles can open'); ?>
<div class="container-fluid px-0">
    <div class="sis-page-heading-card mb-4">
        <div><span class="sis-kicker"><i class="bi bi-unlock"></i> System Owner Control</span><h2>Page Access Control</h2><p>Turn pages on or off for Admin, Supervisor, and Agent. Dashboard, Help, Announcement Board, and Profile stay available for safety.</p></div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>
    <?php if(!empty($flash['message'])): ?><div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show"><?php echo e($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <form method="post" class="sis-access-card p-3 p-md-4">
        <?php echo csrfField(); ?>
        <div class="sis-access-row fw-bold d-none d-md-grid"><div>Page</div><div class="text-center">Admin</div><div class="text-center">Supervisor</div><div class="text-center">Agent</div></div>
        <div class="sis-access-grid mt-2">
        <?php foreach ($registry as $pageKey => $meta): $locked = sisLockedAccessPage($pageKey); ?>
            <div class="sis-access-row <?php echo $locked ? 'locked' : ''; ?>">
                <div><strong><?php echo e($meta['label']); ?></strong><small class="d-block text-muted"><?php echo e($pageKey); ?><?php if($locked): ?> · locked<?php endif; ?></small></div>
                <?php foreach ($roles as $roleName => $roleLabel): ?>
                    <label class="sis-access-check">
                        <?php if (in_array($roleName, $meta['roles'] ?? [], true)): $allowed = $current[$pageKey][$roleName] ?? 1; ?>
                            <span class="d-md-none me-2"><?php echo e($roleLabel); ?></span><input class="form-check-input" type="checkbox" name="access[<?php echo e($pageKey); ?>][<?php echo e($roleName); ?>]" <?php echo $allowed ? 'checked' : ''; ?> <?php echo $locked ? 'disabled' : ''; ?>>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-primary"><i class="bi bi-save"></i> Save Access Rules</button></div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body></html>
