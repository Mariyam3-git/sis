<?php
require_once '../config.php';
requireRole('agent');

if (!hasRole('agent')) {
    redirectTo(appPath('dashboard.php'));
}

$agent_id = $_SESSION['user_id'];
$outlet_id = $_SESSION['outlet_id'];

$message = '';
$message_type = '';

// Handle stock sale (when agent sells an item)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_stock'])) {
    $handle_timing = getPostedHandleTiming();
    $product_id = (int)$_POST['product_id'];
    $batch_id = (int)$_POST['batch_id'];
    $quantity = (int)$_POST['quantity'];
    $visitor_id = (int)$_POST['visitor_id'];
    
    // Check current stock
    $stock_check = mysqli_query($conn, "SELECT quantity FROM outlet_stock WHERE outlet_id = $outlet_id AND product_id = $product_id AND batch_id = $batch_id");
    $current = mysqli_fetch_assoc($stock_check);
    
    if ($current['quantity'] < $quantity) {
        $message = 'Insufficient stock! Only ' . $current['quantity'] . ' available.';
        $message_type = 'danger';
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            // Update stock
            $new_quantity = $current['quantity'] - $quantity;
            mysqli_query($conn, "UPDATE outlet_stock SET quantity = $new_quantity WHERE outlet_id = $outlet_id AND product_id = $product_id AND batch_id = $batch_id");
            
            // Get product details
            $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stock_products WHERE id = $product_id"));
            
            // Record movement
            mysqli_query($conn, "INSERT INTO stock_movements (outlet_id, product_id, batch_id, movement_type, quantity, before_quantity, after_quantity, unit_price, total_price, reference_id, reference_type, created_by) 
                                VALUES ($outlet_id, $product_id, $batch_id, 'sold', $quantity, {$current['quantity']}, $new_quantity, {$product['selling_price']}, " . ($product['selling_price'] * $quantity) . ", $visitor_id, 'visitor', $agent_id)");
            
            // Create sale record
            $invoice = 'INV-' . date('Ymd') . '-' . $visitor_id;
            $sale_id = insertSaleSafe($conn, [
                'visitor_id' => $visitor_id,
                'agent_id' => $agent_id,
                'outlet_id' => $outlet_id,
                'amount' => ($product['selling_price'] * $quantity),
                'payment_status' => 'Paid',
                'invoice_number' => $invoice,
                'payment_date' => date('Y-m-d H:i:s'),
            ]);
            setSaleHandleTime($conn, $sale_id, $handle_timing['started_at'], $handle_timing['completed_at'], $handle_timing['seconds']);
            applyVisitorHandleTime($conn, $visitor_id, null, null, $handle_timing['seconds']);
            
            // Check low stock alert
            if ($new_quantity <= $product['low_stock_threshold']) {
                $alert_check = mysqli_query($conn, "SELECT id FROM stock_alerts WHERE outlet_id = $outlet_id AND product_id = $product_id AND is_resolved = 0");
                if (mysqli_num_rows($alert_check) == 0) {
                    mysqli_query($conn, "INSERT INTO stock_alerts (outlet_id, product_id, current_quantity, threshold) 
                                        VALUES ($outlet_id, $product_id, $new_quantity, {$product['low_stock_threshold']})");
                }
            }
            
            mysqli_commit($conn);
            $message = 'Sale completed! Stock updated.';
            $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get outlet stock with low stock alerts
$stock_query = "
    SELECT os.*, p.name as product_name, p.sku, p.selling_price, p.low_stock_threshold,
           b.batch_number, b.expiry_date,
           (SELECT COUNT(*) FROM stock_alerts WHERE outlet_id = $outlet_id AND product_id = os.product_id AND is_resolved = 0) as has_alert
    FROM outlet_stock os
    JOIN stock_products p ON os.product_id = p.id
    LEFT JOIN stock_batches b ON os.batch_id = b.id
    WHERE os.outlet_id = $outlet_id AND os.quantity > 0
    ORDER BY 
        CASE WHEN os.quantity <= p.low_stock_threshold THEN 0 ELSE 1 END,
        p.name
";
$stock_result = mysqli_query($conn, $stock_query);

// Get low stock alerts for this outlet
$alerts = mysqli_query($conn, "
    SELECT a.*, p.name as product_name, p.low_stock_threshold
    FROM stock_alerts a
    JOIN stock_products p ON a.product_id = p.id
    WHERE a.outlet_id = $outlet_id AND a.is_resolved = 0
    ORDER BY a.created_at DESC
");

// Get today's visitors for dropdown
$visitors = mysqli_query($conn, "SELECT id, name FROM visitors WHERE agent_id = $agent_id AND DATE(visit_time) = CURDATE() AND status = 'Waiting'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('agent'); ?>

    <div class="container mt-4">
        <h4><i class="bi bi-box"></i> Outlet Stock</h4>

        
        <?php if(mysqli_num_rows($alerts) > 0): ?>
            <div class="alert-banner warning">
                <i class="bi bi-exclamation-triangle-fill sis-u-fa415b95"></i>
                <div>
                    <strong>Low Stock Alert!</strong>
                    <ul class="mb-0">
                        <?php while($alert = mysqli_fetch_assoc($alerts)): ?>
                        <li><?php echo $alert['product_name']; ?> - Only <?php echo $alert['current_quantity']; ?> left (Threshold: <?php echo $alert['threshold']; ?>)</li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        
        <div class="row">
            <?php while($item = mysqli_fetch_assoc($stock_result)): 
                $status_class = 'normal';
                if ($item['quantity'] <= $item['low_stock_threshold'] / 2) $status_class = 'critical';
                else if ($item['quantity'] <= $item['low_stock_threshold']) $status_class = 'low';
            ?>
            <div class="col-md-4">
                <div class="stock-card <?php echo $status_class; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1"><?php echo $item['product_name']; ?></h6>
                            <small class="text-muted"><?php echo $item['sku']; ?></small>
                            <?php if($item['batch_number']): ?>
                            <br><small>Batch: <?php echo $item['batch_number']; ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="quantity"><?php echo $item['quantity']; ?></span>
                    </div>
                    
                    <div class="mt-2">
                        <small>Price: D <?php echo number_format($item['selling_price'], 2); ?></small>
                    </div>
                    
                    <?php if($item['has_alert']): ?>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Low Stock!</span>
                    </div>
                    <?php endif; ?>
                    
                    
                    <button class="btn btn-sell btn-sm mt-2 w-100" onclick="showSellModal(<?php echo $item['product_id']; ?>, <?php echo $item['batch_id'] ?? 0; ?>, '<?php echo $item['product_name']; ?>', <?php echo $item['quantity']; ?>, <?php echo $item['selling_price']; ?>)">
                        <i class="bi bi-cart"></i> Sell
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    
    <div class="modal fade" id="sellModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sell Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="stockSaleForm" data-customer-service-form="1" onsubmit="return window.SISHandleTimer ? window.SISHandleTimer.stopFormBeforeSubmit(this) : true;">
                    <div class="modal-body">
                        <?php renderHandleTimerFields('Stock Sale Handle Time', true, 'stock_sale'); ?>
                        <input type="hidden" name="product_id" id="sell_product_id">
                        <input type="hidden" name="batch_id" id="sell_batch_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="sell_product_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Available Stock</label>
                            <input type="text" class="form-control" id="sell_available" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Price per unit (D)</label>
                            <input type="text" class="form-control" id="sell_price" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quantity to sell</label>
                            <input type="number" class="form-control" name="quantity" id="sell_quantity" min="1" required oninput="calculateTotal()">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Visitor</label>
                            <select class="form-select" name="visitor_id" required>
                                <option value="">Choose visitor</option>
                                <?php while($v = mysqli_fetch_assoc($visitors)): ?>
                                <option value="<?php echo $v['id']; ?>"><?php echo $v['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Total: D <span id="total_amount">0.00</span></strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="sell_stock" class="btn btn-primary">Complete Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showSellModal(productId, batchId, productName, available, price) {
            document.getElementById('sell_product_id').value = productId;
            document.getElementById('sell_batch_id').value = batchId;
            document.getElementById('sell_product_name').value = productName;
            document.getElementById('sell_available').value = available;
            document.getElementById('sell_price').value = price;
            document.getElementById('sell_quantity').value = 1;
            document.getElementById('sell_quantity').max = available;
            calculateTotal();
            const form = document.getElementById('stockSaleForm');
            if (window.SISHandleTimer && form) { window.SISHandleTimer.resetForForm(form); window.SISHandleTimer.startForForm(form); }
            new bootstrap.Modal(document.getElementById('sellModal')).show();
        }
        
        function calculateTotal() {
            const qty = document.getElementById('sell_quantity').value || 0;
            const price = document.getElementById('sell_price').value || 0;
            document.getElementById('total_amount').textContent = (qty * price).toFixed(2);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>