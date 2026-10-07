<?php
require_once '../config.php';
require_once '../support_helpers.php';
requireRole('admin');
supportEnsureTables($conn);

$message = '';
$message_type = 'success';
$allowedTypes = supportAllowedFieldTypes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $label = trim($_POST['field_label'] ?? '');
        $key = supportSlug($_POST['field_key'] ?? $label);
        $type = trim($_POST['field_type'] ?? 'text');
        if (!in_array($type, $allowedTypes, true)) $type = 'text';
        $options = trim($_POST['field_options'] ?? '');
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $created_by = (int)($_SESSION['user_id'] ?? 0);

        if ($action === 'create') {
            if ($label === '') {
                $message = 'Field label is required.';
                $message_type = 'danger';
            } else {
                $originalKey = $key;
                $suffix = 1;
                while (true) {
                    $check = mysqli_prepare($conn, 'SELECT id FROM support_form_fields WHERE field_key = ? LIMIT 1');
                    mysqli_stmt_bind_param($check, 's', $key);
                    mysqli_stmt_execute($check);
                    $res = mysqli_stmt_get_result($check);
                    if (!$res || mysqli_num_rows($res) === 0) break;
                    $key = substr($originalKey, 0, 65) . '_' . $suffix++;
                }
                $stmt = mysqli_prepare($conn, 'INSERT INTO support_form_fields (field_label, field_key, field_type, field_options, is_required, is_active, sort_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssssiiii', $label, $key, $type, $options, $is_required, $is_active, $sort_order, $created_by);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Support form field added successfully.';
                        logAction($conn, 'ADD_SUPPORT_FORM_FIELD', mysqli_insert_id($conn));
                    } else {
                        $message = sisDbErrorMessage($conn);
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0 || $label === '') {
                $message = 'Field ID and label are required.';
                $message_type = 'danger';
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE support_form_fields SET field_label=?, field_type=?, field_options=?, is_required=?, is_active=?, sort_order=? WHERE id=?');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sssiiii', $label, $type, $options, $is_required, $is_active, $sort_order, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Support form field updated successfully.';
                        logAction($conn, 'UPDATE_SUPPORT_FORM_FIELD', $id);
                    } else {
                        $message = sisDbErrorMessage($conn);
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn, 'UPDATE support_form_fields SET is_active = IF(is_active=1,0,1) WHERE id=?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $message = 'Field status changed.';
                logAction($conn, 'TOGGLE_SUPPORT_FORM_FIELD', $id);
            }
        }
    }
}

// Refresh after POST guard 2026-07-01
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Action completed.', $message_type);
    redirectTo('support_forms.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

$fields = supportGetFields($conn, false);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complaint / Support Form Builder - Comium SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>

    <h1 class="page-title"><i class="bi bi-ui-checks-grid text-danger"></i> Complaint / Support Form Builder</h1>
    <p class="text-muted">Admin controls the form fields here. Agent and Supervisor will see only active fields on their Complaint / Support page.</p>
    <?php if($message): ?><div class="alert alert-<?php echo e($message_type); ?>"><?php echo e($message); ?></div><?php endif; ?>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3">Add New Field</h5>
        <form method="post" class="row g-3">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-3"><label class="form-label">Field Label</label><input class="form-control" name="field_label" required placeholder="e.g. SIM Number"></div>
            <div class="col-md-2"><label class="form-label">Field Key</label><input class="form-control" name="field_key" placeholder="auto if blank"><div class="type-help">No spaces. Auto generated if blank.</div></div>
            <div class="col-md-2"><label class="form-label">Type</label><select class="form-select" name="field_type"><option value="text">Text</option><option value="textarea">Textarea</option><option value="select">Dropdown</option><option value="number">Number</option><option value="date">Date</option><option value="phone">Phone</option><option value="email">Email</option></select></div>
            <div class="col-md-2"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="0"></div>
            <div class="col-md-3 d-flex align-items-end gap-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_required"><label class="form-check-label">Required</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" checked><label class="form-check-label">Active</label></div><button class="btn btn-primary ms-auto"><i class="bi bi-plus-circle"></i> Add</button></div>
            <div class="col-12"><label class="form-label">Dropdown Options</label><textarea class="form-control" name="field_options" rows="2" placeholder="For dropdown only. Put one option per line, e.g. Network Issue&#10;Billing Issue"></textarea></div>
        </form>
    </div></div>

    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h5 class="mb-0">Current Form Fields</h5><a href="../agent/support.php" class="btn btn-sm btn-outline-danger" target="_blank"><i class="bi bi-eye"></i> Preview Agent Form</a></div>
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead><tr><th>Label</th><th>Type</th><th>Options</th><th>Required</th><th>Active</th><th>Order</th><th class="sis-u-a2ad197f">Actions</th></tr></thead>
            <tbody>
            <?php foreach($fields as $f): ?>
                <tr><form method="post">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int)$f['id']; ?>">
                    <td><input class="form-control" name="field_label" value="<?php echo e($f['field_label']); ?>"><small class="text-muted"><?php echo e($f['field_key']); ?></small></td>
                    <td><select class="form-select" name="field_type"><?php foreach($allowedTypes as $t): ?><option value="<?php echo e($t); ?>" <?php echo $f['field_type']===$t?'selected':''; ?>><?php echo ucfirst($t); ?></option><?php endforeach; ?></select></td>
                    <td><textarea class="form-control" name="field_options" rows="2"><?php echo e($f['field_options']); ?></textarea></td>
                    <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_required" <?php echo $f['is_required']?'checked':''; ?>></div></td>
                    <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" <?php echo $f['is_active']?'checked':''; ?>></div></td>
                    <td><input type="number" class="form-control" name="sort_order" value="<?php echo (int)$f['sort_order']; ?>"></td>
                    <td><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Save</button></form><form method="post" class="d-inline"><?php echo csrfField(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$f['id']; ?>"><button class="btn btn-sm btn-outline-secondary mt-1"><?php echo $f['is_active']?'Disable':'Enable'; ?></button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
