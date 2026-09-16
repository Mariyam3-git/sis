<?php
require_once '../config.php';
require_once '../support_helpers.php';
requireRole('supervisor');
supportEnsureTables($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);
$user_name = $_SESSION['full_name'] ?? 'Supervisor';
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_support_ticket'])) {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        [$ok, $result, $ticket_id] = supportSaveTicket($conn, [
            'submitted_by' => $user_id,
            'submitted_role' => 'supervisor',
            'outlet_id' => $outlet_id,
            'customer_name' => $_POST['customer_name'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'category' => $_POST['category'] ?? 'Complaint',
            'priority' => $_POST['priority'] ?? 'Normal',
            'main_reason' => $_POST['main_reason'] ?? '',
            'sub_reason_id' => $_POST['sub_reason_id'] ?? null,
            'subject' => $_POST['subject'] ?? '',
            'description' => $_POST['description'] ?? '',
        ], $_POST);
        if ($ok) {
            $message = 'Complaint / Support record saved successfully. Ticket No: ' . e($result);
        } else {
            $message = $result;
            $message_type = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Support ticket submitted.', $message_type);
    redirectTo('support.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

$tickets = [];
$stmt = mysqli_prepare($conn, "SELECT st.*, o.name AS outlet_name FROM support_tickets st LEFT JOIN outlets o ON st.outlet_id=o.id WHERE st.submitted_by=? AND st.submitted_role='supervisor' ORDER BY st.created_at DESC LIMIT 20");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $tickets[] = $row;
}
$valuesMap = supportGetTicketValuesMap($conn, array_column($tickets, 'id'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complaint / Support - Supervisor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

<div class="sis-module-hero">
    <div>
        <h2><i class="bi bi-headset text-danger"></i> Complaint / Support</h2>
        <p>Record customer complaints and support requests with editable Main Reason and Sub Reason dropdowns controlled by Admin.</p>
    </div>
    <div class="sis-module-hero-icon"><i class="bi bi-ui-checks"></i></div>
</div>

<div class="sis-support-summary">
    <div class="sis-mini-stat"><span>Submitted By</span><strong><?php echo e($user_name); ?></strong></div>
    <div class="sis-mini-stat"><span>Role</span><strong>Supervisor</strong></div>
    <div class="sis-mini-stat"><span>Latest Records</span><strong><?php echo count($tickets); ?></strong></div>
</div>

<?php if($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 align-items-start">
    <div class="col-xl-5">
        <div class="card sis-form-panel">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span><i class="bi bi-pencil-square text-danger"></i> New Complaint / Support Record</span>
                <span class="badge bg-light text-dark border">Admin form enabled</span>
            </div>
            <div class="card-body">
                <form method="post" class="sis-form-grid">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="save_support_ticket" value="1">

                    <div class="sis-col-6">
                        <label class="form-label">Customer Name</label>
                        <input class="form-control" name="customer_name" placeholder="Customer name if available">
                    </div>
                    <div class="sis-col-6">
                        <label class="form-label">Customer Phone / GSM</label>
                        <input class="form-control" name="customer_phone" placeholder="e.g. 6600309">
                    </div>
                    <div class="sis-col-6">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option>Complaint</option><option>Support</option><option>Follow-up</option><option>Inquiry</option>
                        </select>
                    </div>
                    <div class="sis-col-6">
                        <label class="form-label">Priority</label>
                        <select class="form-select" name="priority">
                            <option>Normal</option><option>Low</option><option>High</option><option>Urgent</option>
                        </select>
                    </div>
                    <?php supportRenderReasonDropdowns($conn); ?>
                    <div class="sis-col-12">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input class="form-control" name="subject" required placeholder="Short complaint/support title">
                    </div>
                    <div class="sis-col-12">
                        <label class="form-label">Main Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Main customer explanation"></textarea>
                    </div>

                    <div class="sis-col-12">
                        <hr class="my-1">
                        <h6 class="sis-section-title"><i class="bi bi-ui-checks"></i> Admin-created Form Fields</h6>
                    </div>
                    <?php supportRenderDynamicFields($conn); ?>

                    <div class="sis-col-12 text-end">
                        <button class="btn btn-danger px-4"><i class="bi bi-save"></i> Save Complaint / Support</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card sis-record-panel">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span><i class="bi bi-clock-history text-danger"></i> My Latest Complaint / Support Records</span>
                <span class="text-muted small">Latest 20 records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive sis-table-scroll">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Ticket</th><th>Date</th><th>Customer</th><th>Main Reason</th><th>Sub Reason</th><th>Status</th><th>Details</th></tr>
                        </thead>
                        <tbody>
                            <?php if(!$tickets): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No records yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach($tickets as $t): ?>
                                <tr>
                                    <td><strong><?php echo e($t['ticket_no']); ?></strong></td>
                                    <td><?php echo e(date('d/m/Y H:i', strtotime($t['created_at']))); ?></td>
                                    <td><?php echo e($t['customer_name'] ?: '—'); ?><small class="d-block text-muted"><?php echo e($t['customer_phone'] ?: ''); ?></small></td>
                                    <td><?php echo e($t['main_reason'] ?: $t['category']); ?></td>
                                    <td><?php echo e(supportGetReasonLabel($conn, $t['sub_reason_id']) ?: '—'); ?><small class="d-block text-muted"><?php echo e($t['priority']); ?></small></td>
                                    <td><span class="badge bg-warning text-dark badge-status"><?php echo e($t['status']); ?></span></td>
                                    <td><strong><?php echo e($t['subject']); ?></strong><?php foreach(($valuesMap[(int)$t['id']] ?? []) as $v): ?><div class="field-value"><span class="text-muted"><?php echo e($v['label']); ?>:</span> <?php echo e($v['value'] ?: '—'); ?></div><?php endforeach; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
