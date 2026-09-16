<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Get all cash reconciliations
$recon_query = "SELECT c.*, o.name as outlet_name, u.full_name as agent_name
                FROM cash_reconciliation c 
                LEFT JOIN outlets o ON c.outlet_id = o.id 
                LEFT JOIN users u ON c.agent_id = u.id 
                ORDER BY c.reconciliation_date DESC 
                LIMIT 500";
$recon_result = mysqli_query($conn, $recon_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Reconciliation - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
    

    <div class="main-content">
        <h3 class="page-title">
            <i class="bi bi-calculator"></i> Cash Reconciliation Report
        </h3>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Outlet</th>
                                <th>Reconciled By</th>
                                <th>Expected</th>
                                <th>Actual</th>
                                <th>Discrepancy</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($recon = mysqli_fetch_assoc($recon_result)): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($recon['reconciliation_date'])); ?></td>
                                <td><?php echo $recon['outlet_name'] ?? '-'; ?></td>
                                <td><?php echo $recon['agent_name'] ?? '-'; ?></td>
                                <td>D <?php echo number_format($recon['expected_amount'], 2); ?></td>
                                <td>D <?php echo number_format($recon['actual_amount'], 2); ?></td>
                                <td class="<?php echo $recon['discrepancy'] >= 0 ? 'discrepancy-positive' : 'discrepancy-negative'; ?>">
                                    <strong>D <?php echo number_format($recon['discrepancy'], 2); ?></strong>
                                </td>
                                <td><?php echo $recon['notes'] ?? '-'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>