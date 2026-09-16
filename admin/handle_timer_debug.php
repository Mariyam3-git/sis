<?php
require_once '../config.php';
requireRole('admin');
if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$has_sales_handle = tableColumnExists($conn, 'sales', 'handle_seconds');
$has_sales_started = tableColumnExists($conn, 'sales', 'handle_started_at');
$has_sales_completed = tableColumnExists($conn, 'sales', 'handle_completed_at');
$handleExpr = $has_sales_handle ? 's.handle_seconds' : 'NULL';
$startedExpr = $has_sales_started ? 's.handle_started_at' : 'NULL';
$completedExpr = $has_sales_completed ? 's.handle_completed_at' : 'NULL';

$sales = mysqli_query($conn, "
    SELECT s.id, s.payment_date, s.amount, s.payment_status,
           $startedExpr AS handle_started_at,
           $completedExpr AS handle_completed_at,
           $handleExpr AS handle_seconds,
           u.full_name AS staff_name,
           v.name AS customer_name,
           v.contact AS customer_gsm
    FROM sales s
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN visitors v ON s.visitor_id = v.id
    ORDER BY s.id DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handle Time Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Handle Time Debug</h3>
            <p class="text-muted mb-0">Checks the three sales fields: <code>handle_started_at</code>, <code>handle_completed_at</code>, and <code>handle_seconds</code>.</p>
        </div>
        <a href="sales.php" class="btn btn-secondary">Back to Sales</a>
    </div>

    <?php if (!$has_sales_handle || !$has_sales_started || !$has_sales_completed): ?>
        <div class="alert alert-warning">
            Missing one or more sales handle-time columns. Run <code>admin/run_upgrade.php</code> or open any normal page once to let the app create them.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Latest 20 Sales</div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Staff</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Handle Started</th>
                        <th>Handle Completed</th>
                        <th>Handle Seconds</th>
                        <th>Formatted Handle Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sales && mysqli_num_rows($sales) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($sales)): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo e($row['payment_date']); ?></td>
                                <td><?php echo e($row['staff_name'] ?? '—'); ?></td>
                                <td><?php echo e(($row['customer_name'] ?? '—') . (!empty($row['customer_gsm']) ? ' / ' . $row['customer_gsm'] : '')); ?></td>
                                <td><?php echo number_format((float)$row['amount'], 2); ?></td>
                                <td><?php echo e($row['payment_status']); ?></td>
                                <td><?php echo e(formatHandleDateTimeLabel($row['handle_started_at'] ?? null)); ?></td>
                                <td><?php echo e(formatHandleDateTimeLabel($row['handle_completed_at'] ?? null)); ?></td>
                                <td><?php echo $row['handle_seconds'] !== null ? (int)$row['handle_seconds'] : '—'; ?></td>
                                <td><?php echo renderHandleTimeCell($row['handle_seconds'] ?? null, $row['handle_started_at'] ?? null, $row['handle_completed_at'] ?? null); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No sales found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-info mt-3">
        Test: open an Agent/Supervisor customer form, wait 15 seconds, submit, then refresh this page. The latest sale should show start time, completed time, and around <strong>15</strong> seconds.
    </div>
</div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
