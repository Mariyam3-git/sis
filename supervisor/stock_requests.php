<?php
require_once '../config.php';
requireRole('supervisor');

$outlet_id = $_SESSION['outlet_id'];
$supervisor_name = $_SESSION['full_name'];

// Get outlet details
$outlet_query = "SELECT * FROM outlets WHERE id = $outlet_id";
$outlet_result = mysqli_query($conn, $outlet_query);
$outlet = mysqli_fetch_assoc($outlet_result);

// Date filters
$date_from = sisCleanDate($_GET['date_from'] ?? '', date('Y-m-d', strtotime('-30 days')));
$date_to = sisCleanDate($_GET['date_to'] ?? '', date('Y-m-d'));
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, (string)($_GET['status'] ?? '')) : '';

// Build query with filters
$where = ["sr.outlet_id = $outlet_id"];
if ($date_from && $date_to) {
    $where[] = "DATE(sr.request_date) BETWEEN '$date_from' AND '$date_to'";
}
if (!empty($status_filter)) {
    $where[] = "sr.status = '$status_filter'";
}
$where_clause = implode(" AND ", $where);

// Get all stock requests
$requests = mysqli_query($conn, "
    SELECT sr.*, 
           p.name as product_name, 
           p.sku,
           u1.full_name as approved_by_name,
           u2.full_name as fulfilled_by_name
    FROM stock_requests sr
    JOIN stock_products p ON sr.product_id = p.id
    LEFT JOIN users u1 ON sr.approved_by = u1.id
    LEFT JOIN users u2 ON sr.fulfilled_by = u2.id
    WHERE $where_clause
    ORDER BY 
        CASE sr.priority 
            WHEN 'critical' THEN 1
            WHEN 'urgent' THEN 2
            WHEN 'normal' THEN 3
        END,
        sr.request_date DESC
");

// Get summary stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM stock_requests
    WHERE outlet_id = $outlet_id
"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Requests - <?php echo e($outlet['name']); ?></title>
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
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-0"><?php echo e($outlet['name']); ?> - Stock Requests</h4>
                    <p class="text-muted mb-0">Request history and tracking</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge bg-info"><?php echo e($stats['total']); ?> Total Requests</span>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-4">
        
        <div class="stats-grid">
            <div class="stat-card">
                <small>Pending</small>
                <h3><?php echo e($stats['pending'] ?? 0); ?></h3>
            </div>
            <div class="stat-card">
                <small>Approved</small>
                <h3><?php echo e($stats['approved'] ?? 0); ?></h3>
            </div>
            <div class="stat-card">
                <small>Fulfilled</small>
                <h3><?php echo e($stats['fulfilled'] ?? 0); ?></h3>
            </div>
            <div class="stat-card">
                <small>Rejected</small>
                <h3><?php echo e($stats['rejected'] ?? 0); ?></h3>
            </div>
            <div class="stat-card">
                <small>Success Rate</small>
                <h3>
                    <?php 
                    $total = $stats['fulfilled'] + $stats['rejected'];
                    $rate = $total > 0 ? round(($stats['fulfilled'] / $total) * 100, 1) : 0;
                    echo $rate . '%';
                    ?>
                </h3>
            </div>
        </div>

        
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="fulfilled" <?php echo $status_filter == 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply</button>
                    <a href="stock_requests.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-list"></i> Request History</span>
                <span class="badge bg-secondary"><?php echo mysqli_num_rows($requests); ?> records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Fulfilled By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($requests) > 0): ?>
                                <?php while($r = mysqli_fetch_assoc($requests)): 
                                    $priority_class = '';
                                    if($r['priority'] == 'critical') $priority_class = 'priority-critical';
                                    elseif($r['priority'] == 'urgent') $priority_class = 'priority-urgent';
                                ?>
                                <tr class="<?php echo $priority_class; ?>">
                                    <td><?php echo date('d/m/Y H:i', strtotime($r['request_date'])); ?></td>
                                    <td><small><?php echo e($r['reference_number']); ?></small></td>
                                    <td>
                                        <strong><?php echo e($r['product_name']); ?></strong>
                                        <br><small><?php echo e($r['sku']); ?></small>
                                    </td>
                                    <td class="fw-bold"><?php echo e($r['quantity']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo e($r['priority']); ?>">
                                            <?php echo ucfirst($r['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo e($r['status']); ?>">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($r['approved_by_name']): ?>
                                            <?php echo e($r['approved_by_name']); ?>
                                            <br><small><?php echo $r['approved_at'] ? date('d/m/Y', strtotime($r['approved_at'])) : ''; ?></small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($r['fulfilled_by_name']): ?>
                                            <?php echo e($r['fulfilled_by_name']); ?>
                                            <br><small><?php echo $r['fulfilled_at'] ? date('d/m/Y', strtotime($r['fulfilled_at'])) : ''; ?></small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($r['rejection_reason']): ?>
                                            <span class="text-danger" title="<?php echo e($r['rejection_reason']); ?>">
                                                <i class="bi bi-exclamation-circle"></i>
                                            </span>
                                        <?php elseif($r['request_notes']): ?>
                                            <span title="<?php echo e($r['request_notes']); ?>">
                                                <i class="bi bi-chat"></i>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if($r['rejection_reason']): ?>
                                <tr class="table-danger">
                                    <td colspan="9" class="text-danger small">
                                        <strong>Rejection Reason:</strong> <?php echo e($r['rejection_reason']); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="bi bi-inbox sis-u-4b77038a"></i>
                                        <p class="mt-3 text-muted">No stock requests found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i> Average Fulfillment Time
                    </div>
                    <div class="card-body">
                        <?php
                        $avg_time = mysqli_fetch_assoc(mysqli_query($conn, "
                            SELECT AVG(TIMESTAMPDIFF(HOUR, request_date, fulfilled_at)) as avg_hours
                            FROM stock_requests
                            WHERE outlet_id = $outlet_id AND fulfilled_at IS NOT NULL
                        "));
                        ?>
                        <h2><?php echo round($avg_time['avg_hours'] ?? 0); ?> hours</h2>
                        <p class="text-muted">From request to fulfillment</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-bar-chart"></i> Request by Priority
                    </div>
                    <div class="card-body">
                        <?php
                        $priority_stats = mysqli_query($conn, "
                            SELECT priority, COUNT(*) as count
                            FROM stock_requests
                            WHERE outlet_id = $outlet_id
                            GROUP BY priority
                        ");
                        while($p = mysqli_fetch_assoc($priority_stats)):
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <span class="badge badge-<?php echo e($p['priority']); ?>">
                                    <?php echo ucfirst($p['priority']); ?>
                                </span>
                            </span>
                            <span class="fw-bold"><?php echo e($p['count']); ?></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>