<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('agent');

// Load settings
$settings = [];
$settings_query = mysqli_query($conn, "SELECT * FROM settings");
while($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currency_symbol = $settings['currency_symbol'] ?? 'D';

$agent_id = $_SESSION['user_id'];
$outlet_id = $_SESSION['outlet_id'];
$today = date('Y-m-d');

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sale'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Security validation failed. Please try again.';
        $message_type = 'danger';
    } else {
        // Get form data
        $customer_name = mysqli_real_escape_string($conn, trim($_POST['customer_name'] ?? ''));
        $customer_contact = mysqli_real_escape_string($conn, trim($_POST['customer_contact'] ?? ''));
        $service_id = (int)($_POST['service_id'] ?? 0);
        $purpose_id = (int)($_POST['purpose_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $amount = (float)($_POST['amount'] ?? 0);
        $notes = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
        
        // Validate
        $errors = [];
        if (empty($customer_name)) $errors[] = 'Customer name is required.';
        if ($service_id <= 0) $errors[] = 'Please select a service.';
        if ($purpose_id <= 0) $errors[] = 'Please select a purpose of visit.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
        
        if (empty($errors)) {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert visitor
                $visitor_query = "INSERT INTO visitors (
                    name, contact, service_id, purpose_id, outlet_id, agent_id, 
                    visit_time, status, quantity, notes
                ) VALUES (
                    '$customer_name', '$customer_contact', $service_id, $purpose_id, 
                    $outlet_id, $agent_id, NOW(), 'Completed', $quantity, '$notes'
                )";
                
                if (!mysqli_query($conn, $visitor_query)) {
                    throw new Exception('Failed to save visitor: ' . mysqli_error($conn));
                }
                
                $visitor_id = mysqli_insert_id($conn);
                
                // Insert sale
                $sale_query = "INSERT INTO sales (
                    visitor_id, agent_id, outlet_id, amount, payment_status, 
                    payment_date, created_at
                ) VALUES (
                    $visitor_id, $agent_id, $outlet_id, $amount, 'Pending', 
                    NOW(), NOW()
                )";
                
                if (!mysqli_query($conn, $sale_query)) {
                    throw new Exception('Failed to save sale: ' . mysqli_error($conn));
                }
                
                $sale_id = mysqli_insert_id($conn);
                
                // Commit transaction
                mysqli_commit($conn);
                
                logAction($conn, 'ADD_SALE', $sale_id);
                $message = 'Sale added successfully!';
                $message_type = 'success';
                
                // Clear form data
                $_POST = [];
                
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
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get data for dropdowns
$purposes = mysqli_query($conn, "SELECT id, name FROM purpose_of_visit WHERE is_active = 1 ORDER BY name");
$services = mysqli_query($conn, "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sale - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
    <style>
        .form-card { max-width: 800px; margin: 0 auto; }
        .required:after { content: " *"; color: #dc3545; }
        .help-text { font-size: 0.8rem; color: #6c757d; }
    </style>
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('agent'); ?>

<div class="container">
    <div class="form-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="bi bi-plus-circle" style="color: var(--comium-red);"></i> Add New Sale</h4>
            <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><i class="bi bi-cash-stack"></i> Sale Details</div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Customer Information -->
                    <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-person"></i> Customer Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label required">Customer Name</label>
                            <input type="text" class="form-control" name="customer_name" 
                                   value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="customer_contact" 
                                   value="<?php echo htmlspecialchars($_POST['customer_contact'] ?? ''); ?>">
                            <div class="help-text">Phone number or email address</div>
                        </div>
                    </div>

                    <!-- Service & Purpose -->
                    <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-tags"></i> Service Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label required">Purpose of Visit</label>
                            <select class="form-select" name="purpose_id" id="purpose_id" required>
                                <option value="">Select Purpose</option>
                                <?php while($p = mysqli_fetch_assoc($purposes)): ?>
                                    <option value="<?php echo $p['id']; ?>" 
                                        <?php echo (isset($_POST['purpose_id']) && $_POST['purpose_id'] == $p['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Service</label>
                            <select class="form-select" name="service_id" id="service_id" required>
                                <option value="">Select Service</option>
                                <?php while($s = mysqli_fetch_assoc($services)): ?>
                                    <option value="<?php echo $s['id']; ?>" 
                                        <?php echo (isset($_POST['service_id']) && $_POST['service_id'] == $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="help-text">Services are filtered based on the selected purpose</div>
                        </div>
                    </div>

                    <!-- Sale Details -->
                    <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-cash"></i> Sale Details</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" 
                                   value="<?php echo htmlspecialchars($_POST['quantity'] ?? 1); ?>" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Amount (<?php echo $currency_symbol; ?>)</label>
                            <input type="number" class="form-control" name="amount" id="amount" 
                                   value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total (<?php echo $currency_symbol; ?>)</label>
                            <input type="text" class="form-control" id="total" readonly 
                                   value="<?php echo $currency_symbol; ?> 0.00" style="background: #f8f9fa;">
                            <div class="help-text">Quantity × Amount</div>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Any additional notes about this sale..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" name="submit_sale" class="btn btn-danger">
                            <i class="bi bi-check-circle"></i> Complete Sale
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // ============================================
    // DYNAMIC SERVICE FILTERING
    // ============================================
    // When purpose of visit changes, update services dropdown
    $('#purpose_id').on('change', function() {
        var purposeId = $(this).val();
        var serviceSelect = $('#service_id');
        
        // Show loading state
        serviceSelect.html('<option value="">Loading services...</option>');
        serviceSelect.prop('disabled', true);
        
        if (purposeId && purposeId > 0) {
            $.ajax({
                url: '../get_services_by_purpose.php',
                type: 'GET',
                data: { purpose_id: purposeId },
                dataType: 'json',
                success: function(data) {
                    serviceSelect.prop('disabled', false);
                    serviceSelect.html('<option value="">Select Service</option>');
                    if (data.length > 0) {
                        $.each(data, function(key, service) {
                            serviceSelect.append('<option value="' + service.id + '">' + service.name + '</option>');
                        });
                    } else {
                        serviceSelect.append('<option value="">No services available for this purpose</option>');
                    }
                },
                error: function() {
                    serviceSelect.prop('disabled', false);
                    serviceSelect.html('<option value="">Error loading services</option>');
                }
            });
        } else {
            // If no purpose selected, show all services
            $.ajax({
                url: '../get_services_by_purpose.php',
                type: 'GET',
                data: { purpose_id: 0 },
                dataType: 'json',
                success: function(data) {
                    serviceSelect.prop('disabled', false);
                    serviceSelect.html('<option value="">Select Service</option>');
                    $.each(data, function(key, service) {
                        serviceSelect.append('<option value="' + service.id + '">' + service.name + '</option>');
                    });
                },
                error: function() {
                    serviceSelect.prop('disabled', false);
                    serviceSelect.html('<option value="">Error loading services</option>');
                }
            });
        }
    });

    // ============================================
    // AUTO-CALCULATE TOTAL
    // ============================================
    function calculateTotal() {
        var quantity = parseInt($('#quantity').val()) || 0;
        var amount = parseFloat($('#amount').val()) || 0;
        var total = quantity * amount;
        var currency = '<?php echo $currency_symbol; ?>';
        $('#total').val(currency + ' ' + total.toFixed(2));
    }

    $('#quantity, #amount').on('input', calculateTotal);
    
    // Trigger initial calculation
    calculateTotal();
});
</script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>