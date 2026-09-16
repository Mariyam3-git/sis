<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Get counts for sidebar
$outlets_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM outlets WHERE is_active = 1"))['count'] ?? 0;
$agents_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND is_active = 1"))['count'] ?? 0;
$supervisors_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'supervisor' AND is_active = 1"))['count'] ?? 0;
$products_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_products WHERE is_active = 1"))['count'] ?? 0;

$message = '';
$message_type = '';

// Get all outlets
$outlets = mysqli_query($conn, "SELECT * FROM outlets WHERE is_active = 1 ORDER BY name");

// Get all products
$products = mysqli_query($conn, "SELECT * FROM stock_products WHERE is_active = 1 ORDER BY name");

// Get suppliers list - UPDATED with Comium and Huawei
$suppliers = mysqli_query($conn, "SELECT DISTINCT supplier FROM stock_batches WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $outlet_id = (int)$_POST['outlet_id'];
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $purchase_price = (float)$_POST['purchase_price'];
    $selling_price = (float)$_POST['selling_price'];
    $supplier = mysqli_real_escape_string($conn, (string)($_POST['supplier'] ?? ''));
    $batch_number = mysqli_real_escape_string($conn, (string)($_POST['batch_number'] ?? ''));
    $expiry_date = !empty($_POST['expiry_date']) ? "'" . $_POST['expiry_date'] . "'" : 'NULL';
    $notes = mysqli_real_escape_string($conn, (string)($_POST['notes'] ?? ''));
    
    // Validation
    $errors = [];
    if ($quantity <= 0) $errors[] = "Quantity must be greater than 0";
    if ($purchase_price < 0) $errors[] = "Purchase price cannot be negative";
    if ($selling_price < 0) $errors[] = "Selling price cannot be negative";
    
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        
        try {
            // Create batch record
            $batch_query = "INSERT INTO stock_batches (
                product_id, batch_number, supplier, quantity, remaining_quantity, 
                purchase_price, selling_price, received_date, expiry_date, notes
            ) VALUES (
                $product_id, '$batch_number', '$supplier', $quantity, $quantity, 
                $purchase_price, $selling_price, CURDATE(), $expiry_date, '$notes'
            )";
            
            if (!mysqli_query($conn, $batch_query)) {
                throw new Exception("Error creating batch: " . mysqli_error($conn));
            }
            
            $batch_id = mysqli_insert_id($conn);
            
            // Add to outlet stock
            $check = mysqli_query($conn, "
                SELECT id, quantity 
                FROM outlet_stock 
                WHERE outlet_id = $outlet_id 
                AND product_id = $product_id 
                AND batch_id = $batch_id
            ");
            
            if (mysqli_num_rows($check) > 0) {
                $existing = mysqli_fetch_assoc($check);
                $new_quantity = $existing['quantity'] + $quantity;
                $update = "UPDATE outlet_stock SET quantity = $new_quantity WHERE id = {$existing['id']}";
                mysqli_query($conn, $update);
                $before_qty = $existing['quantity'];
                $after_qty = $new_quantity;
            } else {
                $insert = "INSERT INTO outlet_stock (outlet_id, product_id, batch_id, quantity) 
                          VALUES ($outlet_id, $product_id, $batch_id, $quantity)";
                mysqli_query($conn, $insert);
                $before_qty = 0;
                $after_qty = $quantity;
            }
            
            // Record stock movement
            $movement = "INSERT INTO stock_movements (
                outlet_id, product_id, batch_id, movement_type, quantity, 
                before_quantity, after_quantity, unit_price, total_price, notes, created_by
            ) VALUES (
                $outlet_id, $product_id, $batch_id, 'received', $quantity, 
                $before_qty, $after_qty, $purchase_price, " . ($purchase_price * $quantity) . ", 
                '$notes', {$_SESSION['user_id']}
            )";
            
            if (!mysqli_query($conn, $movement)) {
                throw new Exception("Error recording movement: " . mysqli_error($conn));
            }
            
            // Resolve any low stock alerts for this product
            mysqli_query($conn, "
                UPDATE stock_alerts 
                SET is_resolved = 1, 
                    resolved_at = NOW(), 
                    resolved_by = {$_SESSION['user_id']} 
                WHERE outlet_id = $outlet_id 
                AND product_id = $product_id 
                AND is_resolved = 0
            ");
            
            mysqli_commit($conn);
            
            $message = 'Stock received successfully!';
            $message_type = 'success';
            logAction($conn, 'RECEIVE_STOCK', $batch_id);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}

// Get recent receipts
$recent = mysqli_query($conn, "
    SELECT b.*, p.name as product_name, o.name as outlet_name, u.full_name as received_by
    FROM stock_batches b
    JOIN stock_products p ON b.product_id = p.id
    JOIN outlet_stock os ON os.batch_id = b.id
    JOIN outlets o ON os.outlet_id = o.id
    JOIN users u ON u.id = (SELECT created_by FROM stock_movements WHERE batch_id = b.id LIMIT 1)
    ORDER BY b.received_date DESC, b.id DESC
    LIMIT 20
");

// Get summary stats
$total_received_month = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty
    FROM stock_batches 
    WHERE MONTH(received_date) = MONTH(CURDATE()) 
    AND YEAR(received_date) = YEAR(CURDATE())
"));

$total_value_month = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(quantity * purchase_price) as total_value
    FROM stock_batches 
    WHERE MONTH(received_date) = MONTH(CURDATE()) 
    AND YEAR(received_date) = YEAR(CURDATE())
"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Stock - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
    
    

    
    <div class="main-content">
        
        <div class="page-header">
            <h3><i class="bi bi-box-arrow-in-down sis-u-20e33279"></i>Receive Stock</h3>
            <span class="date-badge">
                <i class="bi bi-calendar3"></i> <?php echo date('l, d M Y'); ?>
            </span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>This Month</h6>
                <h2><?php echo $total_received_month['count'] ?? 0; ?></h2>
                <small>Receipts</small>
            </div>
            <div class="stat-card">
                <h6>Units Received</h6>
                <h2><?php echo number_format($total_received_month['total_qty'] ?? 0); ?></h2>
                <small>This month</small>
            </div>
            <div class="stat-card">
                <h6>Total Value</h6>
                <h2>D <?php echo number_format($total_value_month['total_value'] ?? 0, 2); ?></h2>
                <small>Purchase cost</small>
            </div>
            <div class="stat-card">
                <h6>Products</h6>
                <h2><?php echo $products_count; ?></h2>
                <small>In catalog</small>
            </div>
        </div>

        
        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-plus-circle"></i> Receive New Stock
                </div>
                <div class="card-body">
                    <form method="POST" id="receiveForm">
                        <div class="mb-3">
                            <label class="form-label">Outlet</label>
                            <select class="form-select" name="outlet_id" required>
                                <option value="">Select Outlet</option>
                                <?php while($o = mysqli_fetch_assoc($outlets)): ?>
                                <option value="<?php echo $o['id']; ?>"><?php echo $o['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select class="form-select" name="product_id" id="productSelect" required>
                                <option value="">Select Product</option>
                                <?php while($p = mysqli_fetch_assoc($products)): ?>
                                <option value="<?php echo $p['id']; ?>" 
                                        data-price="<?php echo $p['selling_price']; ?>"
                                        data-cost="<?php echo $p['unit_price']; ?>">
                                    <?php echo $p['name']; ?> (<?php echo $p['sku']; ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" min="1" value="1" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Price (D)</label>
                                <input type="number" step="0.01" class="form-control" name="purchase_price" id="purchase_price" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Selling Price (D)</label>
                                <input type="number" step="0.01" class="form-control" name="selling_price" id="selling_price" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier</label>
                                <input type="text" class="form-control" name="supplier" id="supplier" list="supplierList" placeholder="e.g., Comium, Huawei">
                                <datalist id="supplierList">
                                    <option value="Comium">Comium</option>
                                    <option value="Huawei">Huawei</option>
                                    <option value="ZTE">ZTE</option>
                                    <option value="Samsung">Samsung</option>
                                    <option value="Nokia">Nokia</option>
                                    <option value="Ericsson">Ericsson</option>
                                    <?php while($sup = mysqli_fetch_assoc($suppliers)): ?>
                                    <option value="<?php echo $sup['supplier']; ?>">
                                    <?php endwhile; ?>
                                </datalist>
                                <small class="text-muted">Type "Comium" or "Huawei" for quick entry</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Batch Number</label>
                                <input type="text" class="form-control" name="batch_number" value="BATCH-<?php echo date('Ymd'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" name="expiry_date">
                            <small class="text-muted">Leave blank if no expiry</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Additional information..."></textarea>
                        </div>

                        
                        <div class="alert alert-info mb-3">
                            <div class="d-flex justify-content-between">
                                <span><strong>Total Cost:</strong></span>
                                <span class="fw-bold" id="totalCost">D 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><strong>Total Value:</strong></span>
                                <span class="fw-bold" id="totalValue">D 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span><strong>Expected Profit:</strong></span>
                                <span class="fw-bold text-success" id="expectedProfit">D 0.00</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle"></i> Receive Stock
                        </button>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-clock-history"></i> Recent Receipts</span>
                    <span class="badge bg-info">Last 20</span>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Outlet</th>
                                <th>Qty</th>
                                <th>Supplier</th>
                                <th>Received By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recent && mysqli_num_rows($recent) > 0): ?>
                                <?php while($r = mysqli_fetch_assoc($recent)): 
                                    $supplier_class = 'supplier-other';
                                    if(strpos(strtolower($r['supplier'] ?? ''), 'comium') !== false) {
                                        $supplier_class = 'supplier-comium';
                                    } elseif(strpos(strtolower($r['supplier'] ?? ''), 'huawei') !== false) {
                                        $supplier_class = 'supplier-huawei';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($r['received_date'])); ?></td>
                                    <td>
                                        <strong><?php echo $r['product_name']; ?></strong>
                                    </td>
                                    <td><?php echo $r['outlet_name']; ?></td>
                                    <td class="fw-bold"><?php echo $r['quantity']; ?></td>
                                    <td>
                                        <?php if($r['supplier']): ?>
                                        <span class="supplier-badge <?php echo $supplier_class; ?>">
                                            <?php echo $r['supplier']; ?>
                                        </span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $r['received_by']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox sis-u-1e52ae17"></i>
                                        <p class="mt-2">No receipts yet</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="quick-actions">
            <a href="stock_products.php?action=add" class="action-btn">
                <i class="bi bi-plus-circle sis-u-e118d5ea"></i>
                Add New Product
            </a>
            <a href="stock_requests.php" class="action-btn">
                <i class="bi bi-send sis-u-00c66c06"></i>
                View Stock Requests
            </a>
            <a href="stock_reports.php" class="action-btn">
                <i class="bi bi-file-bar-graph sis-u-4edeeeb3"></i>
                Stock Reports
            </a>
        </div>
    </div>

    <script>        document.getElementById('quantity').addEventListener('input', calculateTotals);
        document.getElementById('purchase_price').addEventListener('input', calculateTotals);
        document.getElementById('selling_price').addEventListener('input', calculateTotals);
        document.getElementById('productSelect').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const defaultPrice = selected.dataset.price;
            const defaultCost = selected.dataset.cost;
            
            if (defaultPrice) {
                document.getElementById('selling_price').value = defaultPrice;
            }
            if (defaultCost) {
                document.getElementById('purchase_price').value = defaultCost;
            }
            
            calculateTotals();
        });
        
        function calculateTotals() {
            const qty = parseFloat(document.getElementById('quantity').value) || 0;
            const purchase = parseFloat(document.getElementById('purchase_price').value) || 0;
            const selling = parseFloat(document.getElementById('selling_price').value) || 0;
            
            const totalCost = qty * purchase;
            const totalValue = qty * selling;
            const profit = totalValue - totalCost;
            
            document.getElementById('totalCost').textContent = 'D ' + totalCost.toFixed(2);
            document.getElementById('totalValue').textContent = 'D ' + totalValue.toFixed(2);
            document.getElementById('expectedProfit').textContent = 'D ' + profit.toFixed(2);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>