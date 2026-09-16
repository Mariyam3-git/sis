<?php
require_once '../config.php';
require_once '../include/reporting_helpers.php';
requireSuperAdmin();

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$message = '';
$message_type = '';

// Add/Edit target
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_target') {
        $target_type = mysqli_real_escape_string($conn, (string)($_POST['target_type'] ?? ''));
        $assigned_to_ids = isset($_POST['assigned_to_ids']) ? $_POST['assigned_to_ids'] : [];
        $target_value_type = mysqli_real_escape_string($conn, (string)($_POST['target_value_type'] ?? ''));
        $target_percentage = isset($_POST['target_percentage']) ? (float)$_POST['target_percentage'] : null;
        $base_amount = isset($_POST['base_amount']) ? (float)$_POST['base_amount'] : null;
        $target_amount = isset($_POST['target_amount']) ? (float)$_POST['target_amount'] : null;
        $period_type = mysqli_real_escape_string($conn, (string)($_POST['period_type'] ?? ''));
        $start_date = mysqli_real_escape_string($conn, (string)($_POST['start_date'] ?? ''));
        $end_date = mysqli_real_escape_string($conn, (string)($_POST['end_date'] ?? ''));
        
        // Calculate the actual target value to store
        if ($target_value_type === 'percentage' && $base_amount && $target_percentage) {
            $target_value = ($target_percentage / 100) * $base_amount;
        } else {
            $target_value = $target_amount;
        }
        
        // Insert targets for selected agents/outlets
        $success_count = 0;
        $error_count = 0;
        
        foreach ($assigned_to_ids as $assigned_to_id) {
            // Check if target already exists for this period
            $check_query = "SELECT id FROM targets 
                           WHERE target_type = '$target_type' 
                           AND assigned_to_id = $assigned_to_id 
                           AND period_type = '$period_type'
                           AND start_date = '$start_date'
                           AND end_date = '$end_date'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing target
                $update_query = "UPDATE targets 
                                SET target_value = $target_value,
                                    updated_at = NOW()
                                WHERE target_type = '$target_type' 
                                AND assigned_to_id = $assigned_to_id 
                                AND period_type = '$period_type'
                                AND start_date = '$start_date'
                                AND end_date = '$end_date'";
                if (mysqli_query($conn, $update_query)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                // Insert new target
                $insert_query = "INSERT INTO targets (target_type, assigned_to_id, target_value, period_type, start_date, end_date, created_by, created_at) 
                                VALUES ('$target_type', $assigned_to_id, $target_value, '$period_type', '$start_date', '$end_date', " . $_SESSION['user_id'] . ", NOW())";
                
                if (mysqli_query($conn, $insert_query)) {
                    $target_id = mysqli_insert_id($conn);
                    logAction($conn, 'ADD_TARGET', $target_id);
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            $message = "$success_count target(s) set successfully!";
            if ($error_count > 0) {
                $message .= " ($error_count failed)";
            }
            $message_type = 'success';
        } else {
            $message = 'Error setting targets: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
}

// Delete target
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $query = "DELETE FROM targets WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'DELETE_TARGET', $id);
        $message = 'Target deleted successfully!';
        $message_type = 'success';
    }
}

// Bulk delete targets
if (isset($_POST['bulk_delete']) && isset($_POST['target_ids'])) {
    $ids = implode(',', array_map('intval', $_POST['target_ids']));
    $query = "DELETE FROM targets WHERE id IN ($ids)";
    if (mysqli_query($conn, $query)) {
        $message = 'Targets deleted successfully!';
        $message_type = 'success';
    }
}

// Get all agents for dropdown
$agents_query = "SELECT id, full_name, outlet_id FROM users WHERE role = 'agent' AND is_active = 1 ORDER BY full_name";
$agents_result = mysqli_query($conn, $agents_query);

// Get all outlets for dropdown
$outlets_query = "SELECT id, name FROM outlets WHERE is_active = 1 ORDER BY name";
$outlets_result = mysqli_query($conn, $outlets_query);

// Get current targets with achievement data
$targets_query = "SELECT t.*, 
                  CASE 
                    WHEN t.target_type = 'agent' THEN (SELECT full_name FROM users WHERE id = t.assigned_to_id)
                    WHEN t.target_type = 'outlet' THEN (SELECT name FROM outlets WHERE id = t.assigned_to_id)
                  END as assigned_name,
                  CASE
                    WHEN t.target_type = 'agent' THEN 
                      (SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) FROM sales WHERE agent_id = t.assigned_to_id 
                       AND payment_status = 'Paid' 
                       AND DATE(payment_date) BETWEEN t.start_date AND t.end_date)
                    WHEN t.target_type = 'outlet' THEN
                      (SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) FROM sales WHERE outlet_id = t.assigned_to_id 
                       AND payment_status = 'Paid' 
                       AND DATE(payment_date) BETWEEN t.start_date AND t.end_date)
                  END as achieved
                  FROM targets t 
                  ORDER BY t.created_at DESC";
$targets_result = mysqli_query($conn, $targets_query);

// Get summary stats
$total_targets = mysqli_num_rows($targets_result);
$active_targets = 0;
$achieved_targets = 0;
$total_target_value = 0;

// Reset data pointer and calculate stats
mysqli_data_seek($targets_result, 0);
while($target = mysqli_fetch_assoc($targets_result)) {
    if(strtotime($target['end_date']) >= strtotime(date('Y-m-d'))) {
        $active_targets++;
    }
    $achieved = $target['achieved'] ?? 0;
    if($achieved >= $target['target_value']) {
        $achieved_targets++;
    }
    $total_target_value += $target['target_value'];
}
mysqli_data_seek($targets_result, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Targets - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
    
    

    
    <div class="main-content">
        <div class="page-header">
            <h3><i class="bi bi-bullseye sis-u-20e33279"></i>Sales Targets</h3>
            <span class="date-badge"><?php echo date('l, d M Y'); ?></span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Targets</h6>
                <h2><?php echo $total_targets; ?></h2>
                <small>All time</small>
            </div>
            <div class="stat-card">
                <h6>Active Targets</h6>
                <h2><?php echo $active_targets; ?></h2>
                <small>Current period</small>
            </div>
            <div class="stat-card">
                <h6>Achieved</h6>
                <h2><?php echo $achieved_targets; ?></h2>
                <small>Met or exceeded</small>
            </div>
            <div class="stat-card">
                <h6>Total Value</h6>
                <h2>D <?php echo number_format($total_target_value, 2); ?></h2>
                <small>Combined targets</small>
            </div>
        </div>

        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-plus-circle"></i> Set New Target
                </div>
                <div class="card-body">
                    <form method="POST" id="targetForm">
                        <input type="hidden" name="action" value="add_target">
                        
                        <div class="mb-3">
                            <label class="form-label">Target For</label>
                            <select class="form-select" name="target_type" id="targetType" required>
                                <option value="">Select Type</option>
                                <option value="agent">Agent(s)</option>
                                <option value="outlet">Outlet(s)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 sis-u-224b51a7" id="agentSelect">
                            <label class="form-label">Select Agent(s)</label>
                            <div class="select-all-row">
                                <input type="checkbox" id="selectAllAgents"> 
                                <label for="selectAllAgents">Select All Agents</label>
                            </div>
                            <select class="form-select sis-u-199b6f0e" name="assigned_to_ids[]" id="agentSelect2" multiple="multiple">
                                <?php 
                                mysqli_data_seek($agents_result, 0);
                                while($agent = mysqli_fetch_assoc($agents_result)): 
                                ?>
                                <option value="<?php echo $agent['id']; ?>">
                                    <?php echo $agent['full_name']; ?> 
                                    <?php if($agent['outlet_id']): ?>
                                    (Outlet #<?php echo $agent['outlet_id']; ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3 sis-u-224b51a7" id="outletSelect">
                            <label class="form-label">Select Outlet(s)</label>
                            <div class="select-all-row">
                                <input type="checkbox" id="selectAllOutlets"> 
                                <label for="selectAllOutlets">Select All Outlets</label>
                            </div>
                            <select class="form-select sis-u-199b6f0e" name="assigned_to_ids[]" id="outletSelect2" multiple="multiple">
                                <?php 
                                mysqli_data_seek($outlets_result, 0);
                                while($outlet = mysqli_fetch_assoc($outlets_result)): 
                                ?>
                                <option value="<?php echo $outlet['id']; ?>"><?php echo $outlet['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Target Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="target_value_type" id="targetAmount" value="amount" checked>
                                <label class="form-check-label" for="targetAmount">
                                    Fixed Amount (D)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="target_value_type" id="targetPercentage" value="percentage">
                                <label class="form-check-label" for="targetPercentage">
                                    Percentage of Base Amount
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="amountField">
                            <label class="form-label">Target Amount (D)</label>
                            <input type="number" step="0.01" class="form-control" name="target_amount" id="targetAmountInput" placeholder="Enter target amount">
                            <small class="text-muted">Example: 10000 = D 10,000 target</small>
                        </div>
                        
                        <div class="mb-3 sis-u-224b51a7" id="percentageField">
                            <label class="form-label">Base Amount (D)</label>
                            <input type="number" step="0.01" class="form-control" id="baseAmount" placeholder="Base amount for percentage calculation">
                            <small class="text-muted">Example: 50000 = D 50,000 base amount</small>
                            
                            <label class="form-label mt-2">Target Percentage (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" id="targetPercentageInput" placeholder="e.g., 80">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Example: 80% of D 50,000 = D 40,000 target</small>
                            
                            <div class="alert alert-info mt-2 sis-u-224b51a7" id="calculatedTargetPreview">
                                <strong>Calculated Target:</strong> D <span id="calculatedAmount">0.00</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Period</label>
                            <select class="form-select" name="period_type" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle"></i> Set Target(s)
                        </button>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list"></i> Current Targets</span>
                    <span class="badge bg-secondary"><?php echo $total_targets; ?> targets</span>
                </div>
                <div class="card-body p-0">
                    <form method="POST" id="bulkDeleteForm">
                        <input type="hidden" name="bulk_delete" value="1">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="30"><input type="checkbox" id="selectAllTargets"></th>
                                        <th>Type</th>
                                        <th>Target For</th>
                                        <th>Period</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($targets_result) > 0): ?>
                                        <?php while($target = mysqli_fetch_assoc($targets_result)): 
                                            $achieved = $target['achieved'] ?? 0;
                                            $percentage = $target['target_value'] > 0 ? ($achieved / $target['target_value']) * 100 : 0;
                                            $status_class = $percentage >= 100 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                                            $status_text = $percentage >= 100 ? 'Achieved' : ($percentage >= 50 ? 'In Progress' : 'Behind');
                                            $is_active = strtotime($target['end_date']) >= strtotime(date('Y-m-d'));
                                        ?>
                                        <tr>
                                            <td><input type="checkbox" name="target_ids[]" value="<?php echo $target['id']; ?>" class="target-checkbox"></td>
                                            <td>
                                                <span class="badge bg-<?php echo $target['target_type'] == 'agent' ? 'info' : 'primary'; ?>">
                                                    <?php echo ucfirst($target['target_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo $target['assigned_name']; ?></strong>
                                                <small class="d-block text-muted">
                                                    Target: D <?php echo number_format($target['target_value'], 2); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo ucfirst($target['period_type']); ?>
                                                <small class="d-block text-muted">
                                                    <?php echo date('d/m', strtotime($target['start_date'])); ?> - 
                                                    <?php echo date('d/m', strtotime($target['end_date'])); ?>
                                                </small>
                                            </td>
                                            <td class="sis-u-a06603ef">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>D <?php echo number_format($achieved, 2); ?></span>
                                                    <span><?php echo round($percentage, 1); ?>%</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php echo $status_class; ?> sis-progress" data-progress="<?php echo e(min(100, $percentage)); ?>"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                                <?php if(!$is_active): ?>
                                                <br><small class="text-muted">Expired</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?delete=<?php echo $target['id']; ?>" class="btn-danger btn-sm" 
                                                   onclick="return confirm('Delete this target?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-bullseye sis-u-1e52ae17"></i>
                                                <p class="mt-2">No targets set yet</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if($total_targets > 0): ?>
                        <div class="p-3 border-top">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected targets?')">
                                <i class="bi bi-trash"></i> Delete Selected
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <i class="bi bi-graph-up"></i> Target Performance Overview
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="p-3">
                            <div class="display-4 text-success"><?php echo $achieved_targets; ?></div>
                            <h6>Targets Achieved</h6>
                            <small class="text-muted">Met or exceeded goal</small>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="p-3">
                            <div class="display-4 text-warning"><?php echo $active_targets - $achieved_targets; ?></div>
                            <h6>In Progress</h6>
                            <small class="text-muted">Still within period</small>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="p-3">
                            <div class="display-4"><?php echo $total_targets - $active_targets; ?></div>
                            <h6>Expired</h6>
                            <small class="text-muted">Past end date</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>        $(document).ready(function() {
            $('#agentSelect2').select2({
                placeholder: "Select agents...",
                allowClear: true
            });
            
            $('#outletSelect2').select2({
                placeholder: "Select outlets...",
                allowClear: true
            });
        });
        document.getElementById('targetType').addEventListener('change', function() {
            var agentSelect = document.getElementById('agentSelect');
            var outletSelect = document.getElementById('outletSelect');
            
            if (this.value === 'agent') {
                agentSelect.style.display = 'block';
                outletSelect.style.display = 'none';
            } else if (this.value === 'outlet') {
                agentSelect.style.display = 'none';
                outletSelect.style.display = 'block';
            } else {
                agentSelect.style.display = 'none';
                outletSelect.style.display = 'none';
            }
        });
        const targetAmountRadio = document.getElementById('targetAmount');
        const targetPercentageRadio = document.getElementById('targetPercentage');
        const amountField = document.getElementById('amountField');
        const percentageField = document.getElementById('percentageField');
        const targetAmountInput = document.getElementById('targetAmountInput');
        const baseAmountInput = document.getElementById('baseAmount');
        const targetPercentageInput = document.getElementById('targetPercentageInput');
        const calculatedTargetPreview = document.getElementById('calculatedTargetPreview');
        const calculatedAmountSpan = document.getElementById('calculatedAmount');
        
        function calculateTargetPreview() {
            const baseAmount = parseFloat(baseAmountInput.value);
            const targetPercentage = parseFloat(targetPercentageInput.value);
            
            if (!isNaN(baseAmount) && !isNaN(targetPercentage) && baseAmount > 0 && targetPercentage > 0) {
                const calculated = (targetPercentage / 100) * baseAmount;
                calculatedAmountSpan.textContent = calculated.toFixed(2);
                calculatedTargetPreview.style.display = 'block';
            } else {
                calculatedTargetPreview.style.display = 'none';
            }
        }
        
        targetAmountRadio.addEventListener('change', function() {
            if (this.checked) {
                amountField.style.display = 'block';
                percentageField.style.display = 'none';
                targetAmountInput.required = true;
            }
        });
        
        targetPercentageRadio.addEventListener('change', function() {
            if (this.checked) {
                amountField.style.display = 'none';
                percentageField.style.display = 'block';
                targetAmountInput.required = false;
                calculateTargetPreview();
            }
        });
        
        baseAmountInput.addEventListener('input', calculateTargetPreview);
        targetPercentageInput.addEventListener('input', calculateTargetPreview);
        document.getElementById('selectAllAgents').addEventListener('change', function() {
            const select = $('#agentSelect2');
            if (this.checked) {
                select.val(select.find('option').map(function() { return this.value; }).get()).trigger('change');
            } else {
                select.val(null).trigger('change');
            }
        });
        document.getElementById('selectAllOutlets').addEventListener('change', function() {
            const select = $('#outletSelect2');
            if (this.checked) {
                select.val(select.find('option').map(function() { return this.value; }).get()).trigger('change');
            } else {
                select.val(null).trigger('change');
            }
        });
        document.getElementById('selectAllTargets').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.target-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        document.getElementById('targetForm').addEventListener('submit', function(e) {
            const targetType = document.getElementById('targetType').value;
            if (!targetType) {
                e.preventDefault();
                alert('Please select target type (Agent or Outlet)');
                return false;
            }
            
            if (targetType === 'agent') {
                const agentSelect = $('#agentSelect2').val();
                if (!agentSelect || agentSelect.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one agent');
                    return false;
                }
            } else if (targetType === 'outlet') {
                const outletSelect = $('#outletSelect2').val();
                if (!outletSelect || outletSelect.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one outlet');
                    return false;
                }
            }
            
            if (targetPercentageRadio.checked) {
                const baseAmount = parseFloat(baseAmountInput.value);
                const targetPercentage = parseFloat(targetPercentageInput.value);
                
                if (isNaN(baseAmount) || baseAmount <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid base amount');
                    return false;
                }
                
                if (isNaN(targetPercentage) || targetPercentage <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid target percentage');
                    return false;
                }
                const calculatedAmount = (targetPercentage / 100) * baseAmount;
                const hiddenAmount = document.createElement('input');
                hiddenAmount.type = 'hidden';
                hiddenAmount.name = 'target_amount';
                hiddenAmount.value = calculatedAmount;
                this.appendChild(hiddenAmount);
                
                const hiddenPercentage = document.createElement('input');
                hiddenPercentage.type = 'hidden';
                hiddenPercentage.name = 'target_percentage';
                hiddenPercentage.value = targetPercentage;
                this.appendChild(hiddenPercentage);
                
                const hiddenBaseAmount = document.createElement('input');
                hiddenBaseAmount.type = 'hidden';
                hiddenBaseAmount.name = 'base_amount';
                hiddenBaseAmount.value = baseAmount;
                this.appendChild(hiddenBaseAmount);
            } else {
                const targetAmount = parseFloat(targetAmountInput.value);
                if (isNaN(targetAmount) || targetAmount <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid target amount');
                    return false;
                }
            }
            
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            
            if (startDate > endDate) {
                e.preventDefault();
                alert('End date must be after start date');
                return false;
            }
            
            return true;
        });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>