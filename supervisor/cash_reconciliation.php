<?php
require_once '../config.php';
require_once '../include/reporting_helpers.php';
requireRole('supervisor');

$outlet_id = $_SESSION['outlet_id'];
$supervisor_name = $_SESSION['full_name'];

// Get outlet details
$outlet_query = "SELECT * FROM outlets WHERE id = $outlet_id";
$outlet_result = mysqli_query($conn, $outlet_query);
$outlet = mysqli_fetch_assoc($outlet_result);

$message = '';
$message_type = '';

// Get today's date
$today = date('Y-m-d');
$selected_date = sisReportDate($_GET['date'] ?? null, $today);

// Check if reconciliation already exists for selected date
$check_query = "SELECT * FROM cash_reconciliation 
                WHERE outlet_id = $outlet_id AND reconciliation_date = '$selected_date'";
$check_result = mysqli_query($conn, $check_query);
$existing_reconciliation = mysqli_fetch_assoc($check_result);

// Calculate expected amount from sales
$sales_query = "SELECT 
                    COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) as transaction_count,
                    SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN payment_status = 'Paid' AND amount < 0 THEN ABS(amount) ELSE 0 END) as withdrawal_amount,
                    SUM(CASE WHEN payment_status = 'Paid' THEN amount ELSE 0 END) as net_cash_amount,
                    SUM(CASE WHEN payment_status = 'Pending' AND amount > 0 THEN amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN payment_status = 'Unpaid' AND amount > 0 THEN amount ELSE 0 END) as unpaid_amount
                FROM sales 
                WHERE outlet_id = $outlet_id 
                AND DATE(payment_date) = '$selected_date'";
$sales_result = mysqli_query($conn, $sales_query);
$sales_data = mysqli_fetch_assoc($sales_result);

$expected_amount = $sales_data['net_cash_amount'] ?? 0;

// Get recent transactions for display
$transactions = mysqli_query($conn, "
    SELECT s.*, v.name as visitor_name, v.contact
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    WHERE s.outlet_id = $outlet_id 
    AND DATE(s.payment_date) = '$selected_date'
    AND s.payment_status = 'Paid'
    ORDER BY s.payment_date DESC
    LIMIT 20
");

// Handle reconciliation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reconciliation'])) {
    $actual_amount = (float)$_POST['actual_amount'];
    $notes = mysqli_real_escape_string($conn, (string)($_POST['notes'] ?? ''));
    $reconciliation_date = mysqli_real_escape_string($conn, (string)($_POST['reconciliation_date'] ?? ''));
    
    // Validate amount
    if ($actual_amount < 0) {
        $message = "Actual amount cannot be negative";
        $message_type = 'danger';
    } else {
        // Check if reconciliation already exists
        $check = mysqli_query($conn, "SELECT id FROM cash_reconciliation 
                                       WHERE outlet_id = $outlet_id AND reconciliation_date = '$reconciliation_date'");
        
        if (mysqli_num_rows($check) > 0) {
            // Update existing
            $update = "UPDATE cash_reconciliation SET 
                       actual_amount = $actual_amount, 
                       notes = '$notes',
                       reconciled_at = NOW()
                       WHERE outlet_id = $outlet_id AND reconciliation_date = '$reconciliation_date'";
            
            if (mysqli_query($conn, $update)) {
                $message = "Reconciliation updated successfully!";
                $message_type = 'success';
                logAction($conn, 'UPDATE_RECONCILIATION', $outlet_id);
            } else {
                $message = "Error: " . mysqli_error($conn);
                $message_type = 'danger';
            }
        } else {
            // Insert new
            $insert = "INSERT INTO cash_reconciliation 
                       (outlet_id, agent_id, expected_amount, actual_amount, reconciliation_date, notes) 
                       VALUES ($outlet_id, {$_SESSION['user_id']}, $expected_amount, $actual_amount, '$reconciliation_date', '$notes')";
            
            if (mysqli_query($conn, $insert)) {
                $recon_id = mysqli_insert_id($conn);
                $message = "Reconciliation completed successfully!";
                $message_type = 'success';
                logAction($conn, 'CASH_RECONCILIATION', $recon_id);
                
                // Check for discrepancy
                $discrepancy = $expected_amount - $actual_amount;
                if (abs($discrepancy) > 0.01) {
                    $message .= " Warning: There is a discrepancy of D " . number_format(abs($discrepancy), 2);
                }
            } else {
                $message = "Error: " . mysqli_error($conn);
                $message_type = 'danger';
            }
        }
        
        // Refresh data after submission
        header("Location: cash_reconciliation.php?date=$reconciliation_date&success=1");
        exit();
    }
}

// Get reconciliation history
$history_query = "SELECT cr.*, u.full_name as reconciled_by
                  FROM cash_reconciliation cr
                  LEFT JOIN users u ON cr.agent_id = u.id
                  WHERE cr.outlet_id = $outlet_id
                  ORDER BY cr.reconciliation_date DESC
                  LIMIT 30";
$history_result = mysqli_query($conn, $history_query);

// Calculate discrepancy
$discrepancy = $expected_amount - ($existing_reconciliation['actual_amount'] ?? 0);
$discrepancy_class = $discrepancy > 0 ? 'text-danger' : ($discrepancy < 0 ? 'text-warning' : 'text-success');
$discrepancy_icon = $discrepancy > 0 ? 'bi-arrow-down' : ($discrepancy < 0 ? 'bi-arrow-up' : 'bi-check-circle');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Reconciliation - <?php echo $outlet['name']; ?></title>
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
                    <h4 class="mb-2">💰 Cash Reconciliation</h4>
                    <p class="text-muted mb-0">
                        <i class="bi bi-building"></i> <?php echo $outlet['name']; ?> • 
                        <i class="bi bi-geo-alt"></i> <?php echo $outlet['location']; ?> • 
                        <i class="bi bi-globe"></i> <?php echo $outlet['region']; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="outlet-badge">
                        <i class="bi bi-calendar"></i> <?php echo date('l, d M Y'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        
        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i>
            Reconciliation saved successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="date-selector">
            <div class="date-input-group">
                <label><i class="bi bi-calendar3"></i> Select Date:</label>
                <input type="date" id="datePicker" class="form-control" value="<?php echo $selected_date; ?>" 
                       onchange="window.location.href='?date=' + this.value">
            </div>
            <div class="ms-auto">
                <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-calendar-check"></i> Today
                </a>
                <a href="?date=<?php echo date('Y-m-d', strtotime('-1 day')); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i> Yesterday
                </a>
            </div>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card expected">
                <h6><i class="bi bi-calculator"></i> Expected Amount</h6>
                <h2>D <?php echo number_format($expected_amount, 2); ?></h2>
                <small>Positive sales minus paid withdrawals</small>
            </div>
            
            <div class="stat-card actual">
                <h6><i class="bi bi-cash-stack"></i> Actual Amount</h6>
                <h2>D <?php echo number_format($existing_reconciliation['actual_amount'] ?? 0, 2); ?></h2>
                <small><?php echo $existing_reconciliation ? 'Reconciled' : 'Not reconciled'; ?></small>
            </div>
            
            <div class="stat-card discrepancy">
                <h6><i class="bi bi-<?php echo $discrepancy_icon; ?>"></i> Discrepancy</h6>
                <h2 class="<?php echo $discrepancy_class; ?>">
                    <?php echo ($discrepancy >= 0 ? '' : '-'); ?>D <?php echo number_format(abs($discrepancy), 2); ?>
                </h2>
                <small>
                    <?php 
                    if ($discrepancy > 0) echo "Cash is short";
                    elseif ($discrepancy < 0) echo "Cash is over";
                    else echo "Exact match";
                    ?>
                </small>
            </div>
            
            <div class="stat-card count">
                <h6><i class="bi bi-receipt"></i> Transactions</h6>
                <h2><?php echo $sales_data['transaction_count'] ?? 0; ?></h2>
                <small>
                    P: <?php echo number_format($sales_data['pending_amount'] ?? 0, 0); ?> | 
                    U: <?php echo number_format($sales_data['unpaid_amount'] ?? 0, 0); ?>
                </small>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-cash"></i> Cash Reconciliation - <?php echo date('d/m/Y', strtotime($selected_date)); ?></span>
                <?php if($existing_reconciliation): ?>
                <span class="badge bg-success">Already Reconciled</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark">Pending</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        
                        <h5 class="mb-3">📊 Sales Breakdown</h5>
                        <table class="table table-sm">
                            <tr>
                                <td>Total Paid Transactions:</td>
                                <td class="fw-bold"><?php echo $sales_data['transaction_count'] ?? 0; ?></td>
                            </tr>
                            <tr>
                                <td>Paid Positive Sales:</td>
                                <td class="fw-bold text-success">D <?php echo number_format($sales_data['paid_amount'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Paid Withdrawals:</td>
                                <td class="fw-bold text-danger">D <?php echo number_format($sales_data['withdrawal_amount'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Pending Amount:</td>
                                <td class="fw-bold text-warning">D <?php echo number_format($sales_data['pending_amount'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Unpaid Amount:</td>
                                <td class="fw-bold text-danger">D <?php echo number_format($sales_data['unpaid_amount'] ?? 0, 2); ?></td>
                            </tr>
                            <tr class="table-active">
                                <td><strong>Expected Cash in Drawer:</strong></td>
                                <td class="fw-bold fs-5">D <?php echo number_format($expected_amount, 2); ?></td>
                            </tr>
                        </table>
                        
                        <?php if($existing_reconciliation): ?>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6><i class="bi bi-info-circle"></i> Previous Reconciliation</h6>
                            <p class="mb-1"><strong>Actual Amount:</strong> D <?php echo number_format($existing_reconciliation['actual_amount'], 2); ?></p>
                            <p class="mb-1"><strong>Reconciled By:</strong> <?php echo $existing_reconciliation['agent_id'] == $_SESSION['user_id'] ? 'You' : 'Another supervisor'; ?></p>
                            <p class="mb-1"><strong>Date/Time:</strong> <?php echo date('d/m/Y H:i', strtotime($existing_reconciliation['reconciled_at'] ?? $existing_reconciliation['reconciliation_date'])); ?></p>
                            <?php if($existing_reconciliation['notes']): ?>
                            <p class="mb-0"><strong>Notes:</strong> <?php echo $existing_reconciliation['notes']; ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        
                        <div class="reconciliation-form">
                            <h5 class="mb-3">
                                <i class="bi bi-pencil-square"></i> 
                                <?php echo $existing_reconciliation ? 'Update Reconciliation' : 'New Reconciliation'; ?>
                            </h5>
                            
                            <form method="POST">
                                <input type="hidden" name="reconciliation_date" value="<?php echo $selected_date; ?>">
                                
                                <div class="mb-4">
                                    <label class="form-label">Actual Cash Counted (D)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">D</span>
                                        <input type="number" step="0.01" class="form-control form-control-lg" 
                                               name="actual_amount" 
                                               value="<?php echo $existing_reconciliation['actual_amount'] ?? ''; ?>" 
                                               placeholder="Enter amount counted"
                                               <?php echo $existing_reconciliation ? '' : 'required'; ?>>
                                    </div>
                                    <small class="text-muted">Enter the total cash amount you physically counted</small>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Notes / Explanation</label>
                                    <textarea class="form-control" name="notes" rows="3" 
                                              placeholder="Any discrepancies or notes about today's cash count"><?php echo $existing_reconciliation['notes'] ?? ''; ?></textarea>
                                </div>
                                
                                <?php if(!$existing_reconciliation && $expected_amount > 0): ?>
                                <div class="alert alert-info mb-4">
                                    <i class="bi bi-lightbulb"></i>
                                    <strong>Tip:</strong> Count all cash in the drawer. Expected amount is D <?php echo number_format($expected_amount, 2); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($existing_reconciliation): ?>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="submit_reconciliation" class="btn btn-primary flex-grow-1">
                                        <i class="bi bi-check-circle"></i> Update Reconciliation
                                    </button>
                                    <a href="?date=<?php echo $selected_date; ?>&reset=1" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </a>
                                </div>
                                <?php else: ?>
                                <button type="submit" name="submit_reconciliation" class="btn btn-success w-100 py-3">
                                    <i class="bi bi-check-circle"></i> Complete Reconciliation
                                </button>
                                <?php endif; ?>
                            </form>
                        </div>
                        
                        <?php if(abs($discrepancy) > 0.01 && $existing_reconciliation): ?>
                        <div class="mt-3 <?php echo $discrepancy > 0 ? 'alert alert-danger' : 'alert alert-warning'; ?>">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Discrepancy Detected!</strong>
                            <?php if($discrepancy > 0): ?>
                            Cash is short by D <?php echo number_format($discrepancy, 2); ?>. Please investigate.
                            <?php else: ?>
                            Cash is over by D <?php echo number_format(abs($discrepancy), 2); ?>. Please verify.
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-receipt"></i> Paid Transactions - <?php echo date('d/m/Y', strtotime($selected_date)); ?></span>
                <span class="badge bg-secondary"><?php echo mysqli_num_rows($transactions); ?> transactions</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($transactions) > 0): ?>
                                <?php while($t = mysqli_fetch_assoc($transactions)): ?>
                                <tr>
                                    <td><?php echo date('H:i', strtotime($t['payment_date'])); ?></td>
                                    <td><small><?php echo $t['invoice_number'] ?? '—'; ?></small></td>
                                    <td><?php echo $t['visitor_name'] ?? '—'; ?></td>
                                    <td class="fw-bold text-success">D <?php echo number_format($t['amount'], 2); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox sis-u-1e52ae17"></i>
                                        <p class="mt-2">No paid transactions for this date</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th class="fw-bold">D <?php echo number_format($expected_amount, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-clock-history"></i> Reconciliation History</span>
                <span class="badge bg-secondary">Last 30 days</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Expected</th>
                                <th>Actual</th>
                                <th>Discrepancy</th>
                                <th>Reconciled By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($history_result) > 0): ?>
                                <?php while($h = mysqli_fetch_assoc($history_result)): 
                                    $disc = $h['expected_amount'] - $h['actual_amount'];
                                    $disc_class = $disc > 0 ? 'text-danger' : ($disc < 0 ? 'text-warning' : 'text-success');
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($h['reconciliation_date'])); ?></td>
                                    <td>D <?php echo number_format($h['expected_amount'], 2); ?></td>
                                    <td>D <?php echo number_format($h['actual_amount'], 2); ?></td>
                                    <td class="<?php echo $disc_class; ?> fw-bold">
                                        <?php echo ($disc > 0 ? '-' : ($disc < 0 ? '+' : '')); ?>
                                        D <?php echo number_format(abs($disc), 2); ?>
                                    </td>
                                    <td><?php echo $h['reconciled_by'] ?? '—'; ?></td>
                                    <td>
                                        <?php if($disc == 0): ?>
                                        <span class="badge-paid">✓ Exact</span>
                                        <?php elseif($disc > 0): ?>
                                        <span class="badge-unpaid">Short</span>
                                        <?php else: ?>
                                        <span class="badge-pending">Over</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-clock-history sis-u-1e52ae17"></i>
                                        <p class="mt-2">No reconciliation history found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="d-flex gap-2 justify-content-end mt-3">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="window.print()" class="btn btn-outline-primary">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>        document.querySelector('input[name="actual_amount"]')?.addEventListener('blur', function(e) {
            if(this.value) {
                let val = parseFloat(this.value).toFixed(2);
                this.value = val;
            }
        });
        document.querySelector('form')?.addEventListener('submit', function(e) {
            <?php if($existing_reconciliation): ?>
            const form = this;
            if (form.dataset.sisConfirmed === '1') return;
            e.preventDefault();
            const ask = window.sisCenterConfirm ? window.sisCenterConfirm('Are you sure you want to update this reconciliation? This will change the historical record.', 'Confirm Update') : Promise.resolve(confirm('Are you sure you want to update this reconciliation? This will change the historical record.'));
            ask.then(function(ok){ if (ok) { form.dataset.sisConfirmed = '1'; if (form.requestSubmit) form.requestSubmit(); else form.submit(); } });
            <?php endif; ?>
        });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>