<?php
require_once '../config.php';
requireRole('agent');

if (!hasRole('agent')) {
    redirectTo(appPath('dashboard.php'));
}

$visitor_id = isset($_POST['visitor_id']) ? (int)$_POST['visitor_id'] : 0;
$agent_id = $_SESSION['user_id'];
$outlet_id = $_SESSION['outlet_id'];

// Get visitor and service details
$query = "SELECT v.*, s.name as service_name, s.is_fixed_price, s.price as fixed_price, s.min_amount, s.max_amount 
          FROM visitors v 
          LEFT JOIN services s ON v.service_id = s.id 
          WHERE v.id = $visitor_id AND v.agent_id = $agent_id";
$result = mysqli_query($conn, $query);
$visitor = mysqli_fetch_assoc($result);

if (!$visitor) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $payment_status = mysqli_real_escape_string($conn, (string)($_POST['payment_status'] ?? ''));
    $handle_timing = getPostedHandleTiming();
    
    // Validate amount based on service type
    $valid = true;
    $error_msg = '';
    
    if (!$visitor['is_fixed_price']) {
        // Flexible pricing validation
        if ($visitor['min_amount'] && $amount < $visitor['min_amount']) {
            $valid = false;
            $error_msg = "Amount cannot be less than $" . $visitor['min_amount'];
        }
        if ($visitor['max_amount'] && $amount > $visitor['max_amount']) {
            $valid = false;
            $error_msg = "Amount cannot be more than $" . $visitor['max_amount'];
        }
    } else {
        // Fixed price validation
        if ($amount != $visitor['fixed_price']) {
            $valid = false;
            $error_msg = "Amount must be exactly $" . $visitor['fixed_price'] . " for this service";
        }
    }
    
    if ($valid) {
        $invoice = 'INV-' . date('Ymd') . '-' . $visitor_id;
        
        // Check if sale already exists
        $check = mysqli_query($conn, "SELECT id FROM sales WHERE visitor_id = $visitor_id");
        
        if (mysqli_num_rows($check) > 0) {
            $existing_sale = mysqli_fetch_assoc($check);
            $sale_id = (int)$existing_sale['id'];
            $update = "UPDATE sales SET amount = $amount, payment_status = '$payment_status', payment_date = NOW() 
                       WHERE visitor_id = $visitor_id";
            mysqli_query($conn, $update);
            setSaleHandleTime($conn, $sale_id, $handle_timing['started_at'], $handle_timing['completed_at'], $handle_timing['seconds']);
        } else {
            $sale_id = insertSaleSafe($conn, [
                'visitor_id' => $visitor_id,
                'agent_id' => $agent_id,
                'outlet_id' => $outlet_id,
                'amount' => $amount,
                'payment_status' => $payment_status,
                'invoice_number' => $invoice,
                'payment_date' => date('Y-m-d H:i:s'),
            ]);
            setSaleHandleTime($conn, $sale_id, $handle_timing['started_at'], $handle_timing['completed_at'], $handle_timing['seconds']);
        }
        
        // Update visitor status and store the same customer handle time on the visitor row too.
        applyVisitorHandleTime($conn, $visitor_id, null, null, $handle_timing['seconds']);
        markVisitorCompleted($conn, $visitor_id);
        applyVisitorHandleTime($conn, $visitor_id, null, null, $handle_timing['seconds']);
        
        logAction($conn, 'PROCESS_PAYMENT', $visitor_id);
        $success = 'Payment processed successfully!';
        
        // Redirect after 2 seconds
        header('refresh:2;url=dashboard.php');
    } else {
        $error = $error_msg;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('agent'); ?>
    <div class="container">
        <div class="payment-card card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Process Payment</h5>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <h6>Customer: <?php echo htmlspecialchars($visitor['name']); ?></h6>
                    <p class="mb-1">Service: <?php echo htmlspecialchars($visitor['service_name']); ?></p>
                    
                    <?php if(!$visitor['is_fixed_price']): ?>
                        <div class="alert alert-warning service-badge">
                            <i class="bi bi-info-circle"></i> 
                            Flexible Pricing: 
                            <?php 
                            if($visitor['min_amount'] && $visitor['max_amount']) {
                                echo "Customer can pay between $" . $visitor['min_amount'] . " and $" . $visitor['max_amount'];
                            } elseif($visitor['min_amount']) {
                                echo "Minimum payment: $" . $visitor['min_amount'];
                            } elseif($visitor['max_amount']) {
                                echo "Maximum payment: $" . $visitor['max_amount'];
                            } else {
                                echo "Customer can pay any amount";
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info service-badge">
                            <i class="bi bi-tag"></i> Fixed Price: $<?php echo e($visitor['fixed_price']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="" id="processPaymentForm" data-customer-service-form="1" onsubmit="return window.SISHandleTimer ? window.SISHandleTimer.stopFormBeforeSubmit(this) : true;">
                    <input type="hidden" name="visitor_id" value="<?php echo $visitor_id; ?>">
                    <?php renderHandleTimerFields('Payment Handle Time', true, 'payment'); ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount ($)</label>
                        <input type="number" step="0.01" class="form-control form-control-lg" 
                               name="amount" 
                               value="<?php echo e($visitor['fixed_price'] ?? ''); ?>"
                               <?php echo $visitor['is_fixed_price'] ? 'readonly' : ''; ?>
                               required>
                        <?php if($visitor['is_fixed_price']): ?>
                            <small class="text-muted">Amount is fixed for this service</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select class="form-select" name="payment_status" required>
                            <option value="Paid">Paid in Full</option>
                            <option value="Pending">Partial / Pending</option>
                            <option value="Unpaid">Not Paid / Credit</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-danger w-100 py-3">
                        <i class="bi bi-check-circle"></i> Process Payment
                    </button>
                    
                    <a href="dashboard.php" class="btn btn-light w-100 mt-2">
                        Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>