<?php
require_once '../config.php';
require_once '../include/reporting_helpers.php';
requireRole('supervisor');

$outlet_id = $_SESSION['outlet_id'];
$outlet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM outlets WHERE id = $outlet_id"));

// Get targets for this outlet and its agents
$targets_query = "
    SELECT t.*,
           CASE 
               WHEN t.target_type = 'agent' THEN (SELECT full_name FROM users WHERE id = t.assigned_to_id)
               WHEN t.target_type = 'outlet' THEN (SELECT name FROM outlets WHERE id = t.assigned_to_id)
           END as target_name,
           CASE
               WHEN t.target_type = 'agent' THEN 
                   (SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) FROM sales WHERE agent_id = t.assigned_to_id 
                    AND payment_status = 'Paid' AND DATE(payment_date) BETWEEN t.start_date AND t.end_date)
               WHEN t.target_type = 'outlet' THEN
                   (SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) FROM sales WHERE outlet_id = t.assigned_to_id 
                    AND payment_status = 'Paid' AND DATE(payment_date) BETWEEN t.start_date AND t.end_date)
           END as achieved
    FROM targets t
    WHERE (t.target_type = 'outlet' AND t.assigned_to_id = $outlet_id)
       OR (t.target_type = 'agent' AND t.assigned_to_id IN (SELECT id FROM users WHERE outlet_id = $outlet_id AND role = 'agent'))
    ORDER BY t.created_at DESC";

$targets_result = mysqli_query($conn, $targets_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Targets - <?php echo e($outlet['name']); ?></title>
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
            <h4><?php echo e($outlet['name']); ?> - Sales Targets</h4>
            <small class="text-muted">View-only mode</small>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bullseye"></i> Current Targets
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Target For</th>
                                <th>Period</th>
                                <th>Target</th>
                                <th>Achieved</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($target = mysqli_fetch_assoc($targets_result)): 
                                $achieved = $target['achieved'] ?? 0;
                                $percentage = $target['target_value'] > 0 ? ($achieved / $target['target_value']) * 100 : 0;
                                $status_class = $percentage >= 100 ? 'achieved' : 'missed';
                                $status_text = $percentage >= 100 ? 'Achieved' : 'In Progress';
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $target['target_type'] == 'agent' ? 'info' : 'primary'; ?>">
                                        <?php echo ucfirst($target['target_type']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo e($target['target_name']); ?></strong></td>
                                <td>
                                    <?php echo ucfirst($target['period_type']); ?><br>
                                    <small><?php echo date('d/m', strtotime($target['start_date'])); ?> - 
                                           <?php echo date('d/m', strtotime($target['end_date'])); ?></small>
                                </td>
                                <td>D <?php echo number_format($target['target_value'], 2); ?></td>
                                <td class="<?php echo $status_class; ?>">D <?php echo number_format($achieved, 2); ?></td>
                                <td class="sis-u-5c2dc9e8">
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo $percentage >= 100 ? 'success' : 'warning'; ?> sis-progress" data-progress="<?php echo e(min(100, $percentage)); ?>">
                                            <?php echo round($percentage, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $percentage >= 100 ? 'success' : 'warning'; ?>">
                                        <?php echo e($status_text); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>