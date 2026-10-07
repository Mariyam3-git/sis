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

// ============================================
// Calculate expected amount from ALL sales in this outlet.
// Business rule: Expected Cash = Paid positive sales ONLY.
// Withdrawals are paid from a SEPARATE float and do NOT reduce
// the expected cash in the sales drawer.
// ============================================
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

// Expected = Paid positive sales only (withdrawals tracked separately)
$expected_amount = (float)($sales_data['paid_amount'] ?? 0);
$withdrawals_today = (float)($sales_data['withdrawal_amount'] ?? 0);

// ============================================
// Role-based breakdown for this outlet today
// ============================================
$role_breakdown_query = "SELECT 
                            u.role,
                            COUNT(DISTINCT s.id) as tx_count,
                            SUM(CASE WHEN s.payment_status = 'Paid' AND s.amount > 0 THEN s.amount ELSE 0 END) as paid_amount,
                            SUM(CASE WHEN s.payment_status = 'Paid' AND s.amount < 0 THEN ABS(s.amount) ELSE 0 END) as withdrawal_amount,
                            SUM(CASE WHEN s.payment_status = 'Paid' THEN s.amount ELSE 0 END) as net_cash
                        FROM sales s
                        LEFT JOIN users u ON s.agent_id = u.id
                        WHERE s.outlet_id = $outlet_id 
                        AND DATE(s.payment_date) = '$selected_date'
                        GROUP BY u.role";
$role_breakdown_result = mysqli_query($conn, $role_breakdown_query);
$role_breakdown = [];
if ($role_breakdown_result) {
    while ($row = mysqli_fetch_assoc($role_breakdown_result)) {
        $role_breakdown[$row['role'] ?? 'unknown'] = $row;
    }
}
$agent_paid = (float)($role_breakdown['agent']['paid_amount'] ?? 0);
$agent_withdrawals = (float)($role_breakdown['agent']['withdrawal_amount'] ?? 0);
$supervisor_paid = (float)($role_breakdown['supervisor']['paid_amount'] ?? 0);
$supervisor_withdrawals = (float)($role_breakdown['supervisor']['withdrawal_amount'] ?? 0);

// Get recent transactions for display
$transactions = mysqli_query($conn, "
    SELECT s.*, v.name as visitor_name, v.contact, u.full_name as recorder_name, u.role as recorder_role
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN users u ON s.agent_id = u.id
    WHERE s.outlet_id = $outlet_id 
    AND DATE(s.payment_date) = '$selected_date'
    AND s.payment_status = 'Paid'
    ORDER BY s.payment_date DESC
    LIMIT 50
");

// ============================================
// Handle reconciliation submission
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reconciliation'])) {
    $actual_amount = (float)$_POST['actual_amount'];
    $notes = mysqli_real_escape_string($conn, (string)($_POST['notes'] ?? ''));
    $reconciliation_date = mysqli_real_escape_string($conn, (string)($_POST['reconciliation_date'] ?? ''));

    // Recalculate expected for the posted date (in case the page was loaded earlier)
    $expected_check_query = "SELECT 
                                SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END) as paid_amount
                             FROM sales 
                             WHERE outlet_id = $outlet_id 
                             AND DATE(payment_date) = '$reconciliation_date'";
    $expected_check_result = mysqli_query($conn, $expected_check_query);
    $expected_check_data = mysqli_fetch_assoc($expected_check_result);
    $expected_amount_post = (float)($expected_check_data['paid_amount'] ?? 0);

    $rounding_tolerance = 0.01;
    $short_by = $expected_amount_post - $actual_amount;

    if ($actual_amount < 0) {
        $message = "Actual amount cannot be negative";
        $message_type = 'danger';
    } elseif (abs($short_by) > $rounding_tolerance) {
        // NOT an exact match — block the save
        if ($short_by > 0) {
            $message = "❌ Cannot reconcile: Cash is SHORT by D " . number_format($short_by, 2)
                     . ". Expected D " . number_format($expected_amount_post, 2)
                     . " but counted D " . number_format($actual_amount, 2)
                     . ". Please recount the drawer.";
        } else {
            $message = "❌ Cannot reconcile: Cash is OVER by D " . number_format(abs($short_by), 2)
                     . ". Expected D " . number_format($expected_amount_post, 2)
                     . " but counted D " . number_format($actual_amount, 2)
                     . ". Please verify the amount.";
        }
        $message_type = 'danger';
    } else {
        // Exact match — proceed with save
        $check = mysqli_query($conn, "SELECT id FROM cash_reconciliation 
                                       WHERE outlet_id = $outlet_id AND reconciliation_date = '$reconciliation_date'");

        if (mysqli_num_rows($check) > 0) {
            // Update existing record
            $update = "UPDATE cash_reconciliation SET 
                       expected_amount = $expected_amount_post,
                       actual_amount = $actual_amount, 
                       notes = '$notes',
                       reconciled_at = NOW()
                       WHERE outlet_id = $outlet_id AND reconciliation_date = '$reconciliation_date'";

            if (mysqli_query($conn, $update)) {
                $message = "✓ Reconciliation updated and balanced successfully!";
                $message_type = 'success';
                logAction($conn, 'UPDATE_RECONCILIATION', $outlet_id);
            } else {
                $message = sisDbErrorMessage($conn);
                $message_type = 'danger';
            }
        } else {
            // Insert new record
            $insert = "INSERT INTO cash_reconciliation 
                       (outlet_id, agent_id, expected_amount, actual_amount, reconciliation_date, notes) 
                       VALUES ($outlet_id, {$_SESSION['user_id']}, $expected_amount_post, $actual_amount, '$reconciliation_date', '$notes')";

            if (mysqli_query($conn, $insert)) {
                $recon_id = mysqli_insert_id($conn);
                $message = "✓ Reconciliation completed and balanced successfully!";
                $message_type = 'success';
                logAction($conn, 'CASH_RECONCILIATION', $recon_id);
            } else {
                $message = sisDbErrorMessage($conn);
                $message_type = 'danger';
            }
        }

        // Refresh data after submission
        header("Location: cash_reconciliation.php?date=$reconciliation_date&success=1");
        exit();
    }
}

// Get reconciliation history
// Join users WITHOUT a role filter so records display correctly even after role changes.
$history_query = "SELECT cr.*, u.full_name as reconciled_by, u.role as reconciled_role
                  FROM cash_reconciliation cr
                  LEFT JOIN users u ON cr.agent_id = u.id
                  WHERE cr.outlet_id = $outlet_id
                  ORDER BY cr.reconciliation_date DESC
                  LIMIT 30";
$history_result = mysqli_query($conn, $history_query);

// Calculate discrepancy for display
$actual_for_display = (float)($existing_reconciliation['actual_amount'] ?? 0);
$discrepancy = $expected_amount - $actual_for_display;
$is_balanced = (abs($discrepancy) <= 0.01);
$discrepancy_class = $is_balanced ? 'text-success' : ($discrepancy > 0 ? 'text-danger' : 'text-warning');
$discrepancy_icon = $is_balanced ? 'bi-check-circle' : ($discrepancy > 0 ? 'bi-arrow-down' : 'bi-arrow-up');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Reconciliation - <?php echo e($outlet['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
    <style>
        .role-breakdown-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.06);
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .role-breakdown-card.supervisor {
            border-left-color: #9d174d;
        }
        .role-breakdown-card h6 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: 6px;
            font-weight: 800;
        }
        .role-breakdown-card h3 {
            font-weight: 800;
            margin: 0 0 6px 0;
            font-size: 1.8rem;
        }
        .role-breakdown-card.supervisor h3 { color: #9d174d; }
        .role-breakdown-card small {
            color: #64748b;
            font-size: 12px;
        }
        .role-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .role-chip.agent { background: #fee2e2; color: #991b1b; }
        .role-chip.supervisor { background: #fce7f3; color: #9d174d; }

        /* Disabled save button styling */
        .btn-save-disabled {
            background: #e2e8f0 !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed !important;
        }
        .balance-notice {
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 12px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .balance-notice.balanced {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
            color: #166534;
        }
        .balance-notice.unbalanced {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }
        .balance-notice.empty {
            background: #f1f5f9;
            border-left: 4px solid #94a3b8;
            color: #475569;
        }
        .balance-notice i {
            font-size: 1.3rem;
            margin-top: 2px;
        }
    </style>
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

    
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-2">💰 Cash Reconciliation</h4>
                    <p class="text-muted mb-0">
                        <i class="bi bi-building"></i> <?php echo e($outlet['name']); ?> • 
                        <i class="bi bi-geo-alt"></i> <?php echo e($outlet['location']); ?> • 
                        <i class="bi bi-globe"></i> <?php echo e($outlet['region']); ?>
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
        <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo e($message); ?>
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
                <input type="date" id="datePicker" class="form-control" value="<?php echo e($selected_date); ?>" 
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
                <small>Paid positive sales (agents + supervisors)</small>
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
                    if (!$existing_reconciliation) echo "Enter actual amount";
                    elseif ($is_balanced) echo "Exact match";
                    elseif ($discrepancy > 0) echo "Cash is short";
                    else echo "Cash is over";
                    ?>
                </small>
            </div>
            
            <div class="stat-card count">
                <h6><i class="bi bi-receipt"></i> Transactions</h6>
                <h2><?php echo e($sales_data['transaction_count'] ?? 0); ?></h2>
                <small>
                    P: <?php echo number_format($sales_data['pending_amount'] ?? 0, 0); ?> | 
                    U: <?php echo number_format($sales_data['unpaid_amount'] ?? 0, 0); ?>
                </small>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- Role Breakdown Cards -->
        <!-- ============================================ -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="role-breakdown-card">
                    <h6><i class="bi bi-person-badge"></i> Agent Sales Today</h6>
                    <h3>D <?php echo number_format($agent_paid, 2); ?></h3>
                    <small>
                        <strong>Paid sales: D <?php echo number_format($agent_paid, 2); ?></strong>
                        <?php if ($agent_withdrawals > 0): ?>
                        <br>Withdrawals (separate float): D <?php echo number_format($agent_withdrawals, 2); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="role-breakdown-card supervisor">
                    <h6><i class="bi bi-shield-check"></i> Supervisor Sales Today</h6>
                    <h3>D <?php echo number_format($supervisor_paid, 2); ?></h3>
                    <small>
                        <strong>Paid sales: D <?php echo number_format($supervisor_paid, 2); ?></strong>
                        <?php if ($supervisor_withdrawals > 0): ?>
                        <br>Withdrawals (separate float): D <?php echo number_format($supervisor_withdrawals, 2); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- Withdrawals Info Notice -->
        <!-- ============================================ -->
        <?php if ($withdrawals_today > 0): ?>
        <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-info-circle-fill" style="font-size:1.3rem;margin-top:2px;"></i>
            <div>
                <strong>Withdrawals today: D <?php echo number_format($withdrawals_today, 2); ?></strong>
                <div class="small mt-1">
                    Withdrawals are paid from a <strong>separate float</strong> and are NOT deducted from the expected cash.
                    The expected cash in the sales drawer = <strong>paid positive sales only</strong>.
                    <br>
                    If your business instead pays withdrawals from the sales drawer, expected cash would be
                    <strong>D <?php echo number_format($expected_amount - $withdrawals_today, 2); ?></strong>.
                </div>
            </div>
        </div>
        <?php endif; ?>

        
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
                                <td class="fw-bold"><?php echo e($sales_data['transaction_count'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Paid Positive Sales:</td>
                                <td class="fw-bold text-success">D <?php echo number_format($sales_data['paid_amount'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Paid Withdrawals (separate float):</td>
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

                        <div class="alert alert-light border small mb-0">
                            <i class="bi bi-info-circle text-danger"></i>
                            <strong>Note:</strong> Expected cash = paid positive sales only.
                            Withdrawals are handled from a separate float and do not reduce this amount.
                        </div>
                        
                        <?php if($existing_reconciliation): ?>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6><i class="bi bi-info-circle"></i> Previous Reconciliation</h6>
                            <p class="mb-1"><strong>Actual Amount:</strong> D <?php echo number_format($existing_reconciliation['actual_amount'], 2); ?></p>
                            <p class="mb-1"><strong>Reconciled By:</strong> <?php echo $existing_reconciliation['agent_id'] == $_SESSION['user_id'] ? 'You' : 'Another supervisor'; ?></p>
                            <p class="mb-1"><strong>Date/Time:</strong> <?php echo date('d/m/Y H:i', strtotime($existing_reconciliation['reconciled_at'] ?? $existing_reconciliation['reconciliation_date'])); ?></p>
                            <?php if($existing_reconciliation['notes']): ?>
                            <p class="mb-0"><strong>Notes:</strong> <?php echo e($existing_reconciliation['notes']); ?></p>
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
                            
                            <form method="POST" id="reconciliationForm" data-expected="<?php echo e($expected_amount); ?>">
                                <input type="hidden" name="reconciliation_date" value="<?php echo e($selected_date); ?>">
                                
                                <div class="mb-4">
                                    <label class="form-label">Actual Cash Counted (D)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">D</span>
                                        <input type="number" step="0.01" class="form-control form-control-lg" 
                                               name="actual_amount" id="actualAmountInput"
                                               value="<?php echo e($existing_reconciliation['actual_amount'] ?? ''); ?>" 
                                               placeholder="Enter amount counted"
                                               required>
                                    </div>
                                    <small class="text-muted">Enter the total cash amount you physically counted in the sales drawer</small>
                                </div>

                                <!-- Live balance indicator -->
                                <div class="balance-notice empty" id="balanceNotice">
                                    <i class="bi bi-hourglass-split"></i>
                                    <div>
                                        <strong>Enter the cash amount to check if it balances</strong>
                                        <div class="small mt-1">Reconciliation saves only when the amount matches the expected cash exactly.</div>
                                    </div>
                                </div>
                                
                                <div class="mb-4 mt-4">
                                    <label class="form-label">Notes / Explanation</label>
                                    <textarea class="form-control" name="notes" rows="3" 
                                              placeholder="Any notes about today's cash count"><?php echo e($existing_reconciliation['notes'] ?? ''); ?></textarea>
                                </div>
                                
                                <?php if(!$existing_reconciliation && $expected_amount > 0): ?>
                                <div class="alert alert-info mb-4">
                                    <i class="bi bi-lightbulb"></i>
                                    <strong>Tip:</strong> Count all cash in the drawer. Expected amount is D <?php echo number_format($expected_amount, 2); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="submit_reconciliation" id="submitBtn"
                                            class="btn btn-success flex-grow-1 py-3" disabled>
                                        <i class="bi bi-check-circle"></i> 
                                        <?php echo $existing_reconciliation ? 'Update Reconciliation' : 'Complete Reconciliation'; ?>
                                    </button>
                                    <?php if($existing_reconciliation): ?>
                                    <a href="?date=<?php echo e($selected_date); ?>&reset=1" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
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
                                <th>Recorded By</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($transactions) > 0): ?>
                                <?php while($t = mysqli_fetch_assoc($transactions)): 
                                    $rec_role = $t['recorder_role'] ?? 'agent';
                                    $t_amount = (float)$t['amount'];
                                ?>
                                <tr>
                                    <td><?php echo date('H:i', strtotime($t['payment_date'])); ?></td>
                                    <td><small><?php echo e($t['invoice_number'] ?? '—'); ?></small></td>
                                    <td><?php echo e($t['visitor_name'] ?? '—'); ?></td>
                                    <td>
                                        <?php echo e($t['recorder_name'] ?? '—'); ?>
                                        <span class="role-chip <?php echo e($rec_role); ?>">
                                            <?php echo $rec_role === 'supervisor' ? 'SUP' : 'AGT'; ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold <?php echo $t_amount < 0 ? 'text-danger' : 'text-success'; ?> text-end">
                                        <?php echo $t_amount < 0 ? '- D ' : '+ D '; ?><?php echo number_format(abs($t_amount), 2); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox sis-u-1e52ae17"></i>
                                        <p class="mt-2">No paid transactions for this date</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Expected Cash (Paid Positive Sales):</th>
                                <th class="fw-bold text-end">D <?php echo number_format($expected_amount, 2); ?></th>
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
                                    $disc = (float)$h['expected_amount'] - (float)$h['actual_amount'];
                                    $balanced = abs($disc) <= 0.01;
                                    $disc_class = $balanced ? 'text-success' : ($disc > 0 ? 'text-danger' : 'text-warning');
                                    $h_role = $h['reconciled_role'] ?? 'supervisor';
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($h['reconciliation_date'])); ?></td>
                                    <td>D <?php echo number_format($h['expected_amount'], 2); ?></td>
                                    <td>D <?php echo number_format($h['actual_amount'], 2); ?></td>
                                    <td class="<?php echo $disc_class; ?> fw-bold">
                                        <?php echo ($disc > 0 ? '-' : ($disc < 0 ? '+' : '')); ?>
                                        D <?php echo number_format(abs($disc), 2); ?>
                                    </td>
                                    <td>
                                        <?php echo e($h['reconciled_by'] ?? '—'); ?>
                                        <?php if (!empty($h['reconciled_role'])): ?>
                                            <span class="role-chip <?php echo e($h_role === 'supervisor' ? 'supervisor' : 'agent'); ?>">
                                                <?php echo $h_role === 'supervisor' ? 'SUP' : 'AGT'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($balanced): ?>
                                        <span class="badge-paid">✓ Balanced</span>
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
    
    <script>
    (function() {
        var expected = parseFloat(document.getElementById('reconciliationForm')?.dataset.expected || '0');
        var input = document.getElementById('actualAmountInput');
        var submitBtn = document.getElementById('submitBtn');
        var notice = document.getElementById('balanceNotice');

        function fmt(n) {
            return n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function checkBalance() {
            if (!input || !submitBtn || !notice) return;
            var raw = input.value.trim();
            if (raw === '') {
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-save-disabled');
                notice.className = 'balance-notice empty';
                notice.innerHTML = '<i class="bi bi-hourglass-split"></i><div><strong>Enter the cash amount to check if it balances</strong><div class="small mt-1">Reconciliation saves only when the amount matches the expected cash exactly.</div></div>';
                return;
            }
            var actual = parseFloat(raw);
            if (isNaN(actual)) actual = 0;
            var diff = expected - actual;

            if (Math.abs(diff) <= 0.01) {
                // Balanced — enable save
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-save-disabled');
                submitBtn.classList.add('btn-success');
                notice.className = 'balance-notice balanced';
                notice.innerHTML = '<i class="bi bi-check-circle-fill"></i><div><strong>Balanced — ready to reconcile</strong><div class="small mt-1">Actual matches expected cash exactly (D ' + fmt(expected) + ').</div></div>';
            } else {
                // Not balanced — disable save
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-save-disabled');
                submitBtn.classList.remove('btn-success');
                if (diff > 0) {
                    notice.className = 'balance-notice unbalanced';
                    notice.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><div><strong>Cash is SHORT by D ' + fmt(diff) + '</strong><div class="small mt-1">Expected D ' + fmt(expected) + ', counted D ' + fmt(actual) + '. Please recount the drawer before saving.</div></div>';
                } else {
                    notice.className = 'balance-notice unbalanced';
                    notice.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><div><strong>Cash is OVER by D ' + fmt(Math.abs(diff)) + '</strong><div class="small mt-1">Expected D ' + fmt(expected) + ', counted D ' + fmt(actual) + '. Please verify the amount before saving.</div></div>';
                }
            }
        }

        if (input) {
            input.addEventListener('input', checkBalance);
            input.addEventListener('blur', function() {
                if (this.value) {
                    var v = parseFloat(this.value);
                    if (!isNaN(v)) this.value = v.toFixed(2);
                }
                checkBalance();
            });
            // Initialize on page load (in case a value is pre-filled)
            checkBalance();
        }
    })();
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>