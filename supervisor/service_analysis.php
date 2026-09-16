<?php
require_once '../config.php';
requireRole('supervisor');

$outlet_id = $_SESSION['outlet_id'];
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

$outlet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM outlets WHERE id = $outlet_id"));

// Service analysis query
$service_query = "
    SELECT s.name, s.price, s.is_paid, s.is_fixed_price, s.is_quantity_based, s.unit_name,
           COUNT(DISTINCT v.id) as visit_count,
           COUNT(DISTINCT s2.id) as sale_count,
           SUM(s2.amount) as revenue,
           SUM(CASE WHEN s2.payment_status = 'Pending' THEN s2.amount ELSE 0 END) as pending,
           SUM(CASE WHEN s2.payment_status = 'Unpaid' THEN s2.amount ELSE 0 END) as unpaid,
           AVG(s2.amount) as avg_amount,
           SUM(v.quantity) as total_quantity
    FROM services s
    LEFT JOIN visitors v ON s.id = v.service_id AND v.outlet_id = $outlet_id AND DATE(v.visit_time) BETWEEN '$date_from' AND '$date_to'
    LEFT JOIN sales s2 ON v.id = s2.visitor_id
    WHERE s.is_active = 1
    GROUP BY s.id
    HAVING visit_count > 0 OR sale_count > 0
    ORDER BY revenue DESC";

$services_result = mysqli_query($conn, $service_query);

// Category analysis
$category_query = "
    SELECT pc.name as category, 
           COUNT(DISTINCT v.id) as visits,
           COUNT(DISTINCT s.id) as sales,
           SUM(s.amount) as revenue
    FROM purpose_categories pc
    LEFT JOIN purpose_of_visit pv ON pc.id = pv.category_id
    LEFT JOIN visitors v ON pv.id = v.purpose_id AND v.outlet_id = $outlet_id AND DATE(v.visit_time) BETWEEN '$date_from' AND '$date_to'
    LEFT JOIN sales s ON v.id = s.visitor_id
    WHERE pc.is_active = 1
    GROUP BY pc.id
    HAVING visits > 0
    ORDER BY revenue DESC";

$category_result = mysqli_query($conn, $category_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Analysis - <?php echo $outlet['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

    <div class="page-header">
        <div class="container">
            <h4><?php echo $outlet['name']; ?> - Service Analysis</h4>
            <small><?php echo $date_from; ?> to <?php echo $date_to; ?></small>
        </div>
    </div>

    <div class="container">
        
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label>From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-4">
                        <label>To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Apply Filter</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        
        <div class="row">
            <?php while($cat = mysqli_fetch_assoc($category_result)): ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6><?php echo $cat['category']; ?></h6>
                        <h3 class="text-danger">D <?php echo number_format($cat['revenue'] ?? 0, 2); ?></h3>
                        <small><?php echo $cat['visits']; ?> visits • <?php echo $cat['sales']; ?> sales</small>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        
        <div class="card">
            <div class="card-header">Service Performance Details</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Service</th>
                                <th>Type</th>
                                <th>Unit Price</th>
                                <th>Visits</th>
                                <th>Sales</th>
                                <th>Quantity</th>
                                <th>Revenue</th>
                                <th>Pending</th>
                                <th>Unpaid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($service = mysqli_fetch_assoc($services_result)): 
                                $type_class = 'free';
                                $type_label = 'Free';
                                if($service['is_paid']) {
                                    if($service['is_quantity_based']) {
                                        $type_class = 'quantity';
                                        $type_label = 'Qty-based';
                                    } elseif(!$service['is_fixed_price']) {
                                        $type_class = 'flexible';
                                        $type_label = 'Flexible';
                                    } else {
                                        $type_class = 'paid';
                                        $type_label = 'Fixed';
                                    }
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo $service['name']; ?></strong></td>
                                <td><span class="badge badge-<?php echo $type_class; ?>"><?php echo $type_label; ?></span></td>
                                <td>D <?php echo number_format($service['price'], 2); ?></td>
                                <td><?php echo $service['visit_count']; ?></td>
                                <td><?php echo $service['sale_count']; ?></td>
                                <td><?php echo $service['total_quantity'] ?: '-'; ?></td>
                                <td class="text-success">D <?php echo number_format($service['revenue'] ?? 0, 2); ?></td>
                                <td class="text-warning">D <?php echo number_format($service['pending'] ?? 0, 2); ?></td>
                                <td class="text-danger">D <?php echo number_format($service['unpaid'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>