<?php
require_once '../config.php';
require_once '../support_helpers.php';
requireRole('admin');
supportEnsureTables($conn);

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $main_reason = trim($_POST['category'] ?? 'General') ?: 'General';
        $sub_reason = trim($_POST['reason'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'create' && $sub_reason !== '') {
            $stmt = mysqli_prepare($conn, 'INSERT INTO complaint_reasons (reason, category, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssii', $sub_reason, $main_reason, $description, $sort_order, $is_active);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Main/Sub reason added successfully.';
                    logAction($conn, 'ADD_SUPPORT_REASON', mysqli_insert_id($conn));
                } else {
                    $message = sisDbErrorMessage($conn);
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'update' && $id > 0 && $sub_reason !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE complaint_reasons SET reason=?, category=?, description=?, sort_order=?, is_active=? WHERE id=?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssiii', $sub_reason, $main_reason, $description, $sort_order, $is_active, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Reason updated successfully.';
                    logAction($conn, 'UPDATE_SUPPORT_REASON', $id);
                } else {
                    $message = sisDbErrorMessage($conn);
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'toggle' && $id > 0) {
            $stmt = mysqli_prepare($conn, 'UPDATE complaint_reasons SET is_active = IF(is_active=1,0,1) WHERE id=?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $message = 'Reason status changed.';
                logAction($conn, 'TOGGLE_SUPPORT_REASON', $id);
            }
        }
    }
}

// Refresh after POST guard 2026-07-01
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Action completed.', $message_type);
    redirectTo('complaint_reasons.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

$reasons = mysqli_query($conn, 'SELECT * FROM complaint_reasons ORDER BY category, sort_order, reason');
$main_reasons = [];
$resMain = mysqli_query($conn, "SELECT DISTINCT category FROM complaint_reasons WHERE category IS NOT NULL AND category <> '' ORDER BY category");
if ($resMain) while ($r = mysqli_fetch_assoc($resMain)) $main_reasons[] = $r['category'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Main/Sub Reasons - Comium SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'Complaint / Support Reasons', 'Manage Main Reason and Sub Reason dropdowns used only in Complaint / Support forms'); ?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="page-title"><i class="bi bi-diagram-3 text-danger"></i> Main Reason / Sub Reason</h1>
        <p class="text-muted mb-0">These dropdowns are shown on Agent and Supervisor Complaint / Support pages. They are no longer shown on Add Customer / Sale.</p>
    </div>
    <a href="support_forms.php" class="btn btn-outline-primary"><i class="bi bi-ui-checks-grid"></i> Edit Extra Form Fields</a>
</div>

<?php if($message): ?><div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-plus-circle"></i> Add New Reason</h5>
        <form method="post" class="row g-3">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <label class="form-label">Main Reason</label>
                <input class="form-control" name="category" list="mainReasons" required placeholder="e.g. Network">
                <datalist id="mainReasons"><?php foreach($main_reasons as $m): ?><option value="<?php echo e($m); ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="col-md-3"><label class="form-label">Sub Reason</label><input class="form-control" name="reason" required placeholder="e.g. Data Not Working"></div>
            <div class="col-md-2"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="0"></div>
            <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" checked><label class="form-check-label">Active</label></div></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-save"></i> Add</button></div>
            <div class="col-12"><label class="form-label">Description / Guidance</label><textarea class="form-control" name="description" rows="2" placeholder="Optional guidance for staff"></textarea></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-list-check"></i> Current Reasons</h5>
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead><tr><th>Main Reason</th><th>Sub Reason</th><th>Description</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if($reasons && mysqli_num_rows($reasons) > 0): while($r = mysqli_fetch_assoc($reasons)): ?>
                <tr>
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <td><input class="form-control" name="category" value="<?php echo e($r['category']); ?>" list="mainReasons"></td>
                        <td><input class="form-control" name="reason" value="<?php echo e($r['reason']); ?>"></td>
                        <td><textarea class="form-control" name="description" rows="1"><?php echo e($r['description']); ?></textarea></td>
                        <td><input type="number" class="form-control" name="sort_order" value="<?php echo (int)$r['sort_order']; ?>"></td>
                        <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" <?php echo $r['is_active'] ? 'checked' : ''; ?>></div></td>
                        <td class="d-flex gap-2"><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Save</button>
                    </form>
                    <form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-sm btn-outline-secondary"><?php echo $r['is_active'] ? 'Disable' : 'Enable'; ?></button></form></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No reasons yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
