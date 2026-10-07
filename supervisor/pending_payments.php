<?php
require_once '../config.php';
requireRole('supervisor');

$outlet_id = (int)($_SESSION['outlet_id'] ?? 0);
$outlet = null;
if ($outlet_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM outlets WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $outlet_id);
        mysqli_stmt_execute($stmt);
        $outlet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
}
if (!$outlet) {
    // A supervisor without an outlet has no payments to manage.
    $outlet = ['name' => 'No outlet assigned'];
}

// Handle marking as paid. Only sales belonging to this supervisor's outlet can be changed.
if (sisPostAction('mark_paid')) {
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE sales SET payment_status = 'Paid', payment_date = NOW() WHERE id = ? AND outlet_id = ? AND payment_status IN ('Pending', 'Unpaid')");
    if ($stmt && $outlet_id > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $sale_id, $outlet_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            logAction($conn, 'PAYMENT_COLLECTED', $sale_id);
            header("Location: pending_payments.php?success=1");
            exit();
        }
    }
    header("Location: pending_payments.php");
    exit();
}

// Get all pending and unpaid payments
$payments_query = "
    SELECT s.*, v.name as visitor_name, v.contact, u.full_name as agent_name,
           sv.name as service_name, sv.is_quantity_based, sv.unit_name, v.quantity
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN services sv ON v.service_id = sv.id
    WHERE s.outlet_id = $outlet_id
    AND s.payment_status IN ('Pending', 'Unpaid')
    ORDER BY s.payment_date DESC";

$payments_result = mysqli_query($conn, $payments_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Payments - <?php echo e($outlet['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

    <div class="page-header">
        <div class="container">
            <h4><?php echo e($outlet['name']); ?> - Pending & Unpaid Payments</h4>
        </div>
    </div>

    <div class="container">
        <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Payment collected successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-clock-history"></i> Outstanding Payments</span>
                <span class="badge bg-warning">Pending</span>
                <span class="badge bg-danger">Unpaid</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Agent</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Days</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_pending = 0;
                            $total_unpaid = 0;
                            while($payment = mysqli_fetch_assoc($payments_result)): 
                                $days = floor((time() - strtotime($payment['payment_date'])) / 86400);
                                if($payment['payment_status'] == 'Pending') {
                                    $total_pending += $payment['amount'];
                                    $row_class = 'pending-row';
                                } else {
                                    $total_unpaid += $payment['amount'];
                                    $row_class = 'unpaid-row';
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($payment['visitor_name']); ?>
                                    <small class="d-block text-muted"><?php echo e($payment['contact']); ?></small>
                                </td>
                                <td>
                                    <?php echo e($payment['service_name']); ?>
                                    <?php if($payment['is_quantity_based'] && $payment['quantity'] > 1): ?>
                                        <small class="d-block">x<?php echo e($payment['quantity']); ?> units</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($payment['agent_name']); ?></td>
                                <td><strong>D <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td>
                                    <span class="badge-<?php echo strtolower($payment['payment_status']); ?>">
                                        <?php echo e($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($days > 0): ?>
                                        <span class="text-danger"><?php echo $days; ?> days</span>
                                    <?php else: ?>
                                        <span class="text-muted">Today</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form class="sis-u-5f8f4af2" method="POST">
                                        <input type="hidden" name="sale_id" value="<?php echo e($payment['id']); ?>">
                                        <button type="submit" name="mark_paid" class="btn btn-sm btn-success" 
                                                onclick="return confirm('Mark this payment as collected?')">
                                            <i class="bi bi-check-circle"></i> Mark Paid
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Totals:</th>
                                <th>
                                    <span class="text-warning">Pending: D <?php echo number_format($total_pending, 2); ?></span><br>
                                    <span class="text-danger">Unpaid: D <?php echo number_format($total_unpaid, 2); ?></span>
                                </th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <a href="?export=1" class="btn btn-success">
                <i class="bi bi-download"></i> Export List
            </a>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>