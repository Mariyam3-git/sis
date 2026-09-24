<?php
// agent/dashboard.php

require_once '../config.php';
require_once dirname(__DIR__) . '/include/shift_helpers.php';
requireRole('agent');

if (!hasRole('agent')) {
    redirectTo(appPath('dashboard.php'));
}

$sisAgentSalesPage = (($sisAgentPageMode ?? '') === 'sales')
    || basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'sales.php';

$agent_id = $_SESSION['user_id'];
$outlet_id = $_SESSION['outlet_id'];
$agent_name = $_SESSION['full_name'];
sisEnsureAdvancedShiftTables($conn);
$today_assignment = sisGetRelevantAgentAssignment($conn, (int)$agent_id);
$show_overtime_modal = false;
$auto_logout_after_overtime_decline = false;
$early_end_pending = null;

// ============================================
// SHIFT MANAGEMENT (database-backed with cookie compatibility)
// ============================================
$today = date('Y-m-d');
$shift_work_date = $today_assignment['shift_date'] ?? $today;
$shift_selected = false;
$current_shift = null;
$shift_started_at = null;
$shift_ended = false;
$shift_ended_at = null;
$can_record = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_overtime']) && $today_assignment) {
    $mins = sisOvertimeMinutes($today_assignment);
    sisCreateShiftRequest($conn, (int)$today_assignment['id'], (int)$agent_id, 'overtime', 'Agent confirmed staying overtime from dashboard.', 'notified', $mins);
    $_SESSION['overtime_confirmed_assignment_' . (int)$today_assignment['id']] = true;
    setcookie('sis_overtime_confirmed_' . (int)$today_assignment['id'], '1', time() + 86400, '/');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decline_overtime']) && $today_assignment) {
    $mins = sisOvertimeMinutes($today_assignment);
    $requestId = sisCreateShiftRequest($conn, (int)$today_assignment['id'], (int)$agent_id, 'overtime', 'Agent declined overtime. System will logout after 15 minutes if still active.', 'notified', $mins);
    if ($requestId) sisSetOvertimeLogoutDeadline($conn, $requestId, 15);
    $auto_logout_after_overtime_decline = true;
}

$overtime_logout_deadline = $today_assignment
    ? sisGetOvertimeLogoutDeadline($conn, (int)$agent_id, (int)$today_assignment['id'])
    : null;
if ($overtime_logout_deadline && strtotime($overtime_logout_deadline) <= time()) {
    endAgentShift($conn, (int)$agent_id, $shift_work_date, $overtime_logout_deadline);
    redirectTo(appPath('logout.php?force=1'));
}
if ($overtime_logout_deadline && strtotime($overtime_logout_deadline) > time()) {
    $auto_logout_after_overtime_decline = true;
}

// Helper to format duration
function formatDuration($start, $end = null) {
    if (!$start) return '0 hrs 0 mins';
    $start_dt = new DateTime($start);
    $end_dt = $end ? new DateTime($end) : new DateTime();
    $diff = $start_dt->diff($end_dt);
    $hours = $diff->h + ($diff->days * 24);
    $minutes = $diff->i;
    return "{$hours} hrs {$minutes} mins";
}

// Handle shift selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_shift'])) {
    $shift = strtolower(trim($_POST['shift'] ?? ''));
    if ($today_assignment) {
        [$allowed, $shiftMsg] = sisCanAgentLoginForShift($today_assignment);
        if (!$allowed) {
            setFlashMessage($shiftMsg, 'danger');
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        $shift = sisNormalizeShiftType($today_assignment['shift_type']);
    }
    if (in_array($shift, ['morning', 'afternoon', 'night'], true)) {
        $start_time = date('Y-m-d H:i:s');
        $shift_id = startAgentShift($conn, (int)$agent_id, (int)$outlet_id, $shift, $shift_work_date, $start_time);
        if ($today_assignment) sisUpdateAssignmentAttendance($conn, $today_assignment, $start_time);
        $shift_data = [
            'shift_id' => $shift_id,
            'shift' => $shift,
            'date' => $shift_work_date,
            'started_at' => $start_time,
            'ended' => false,
            'ended_at' => null
        ];
        setcookie('agent_shift_' . $agent_id, json_encode($shift_data), time() + 86400 * 30, '/');
        auditAction($conn, 'START_AGENT_SHIFT', $shift_id ?: $agent_id, normalizeShiftName($shift));
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle end shift confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_shift_confirm'])) {
    if ($today_assignment) {
        [, $scheduled_end_ts] = sisShiftWindowTimestamps($today_assignment);
        if (time() < $scheduled_end_ts) {
            $reason = trim((string)($_POST['early_end_reason'] ?? 'Agent requested early shift end from dashboard.'));
            $pending = sisGetPendingShiftRequest($conn, (int)$today_assignment['id'], 'early_end');
            if (!$pending) sisCreateShiftRequest($conn, (int)$today_assignment['id'], (int)$agent_id, 'early_end', $reason, 'pending');
            setFlashMessage('Your shift has not reached the scheduled end time. A request has been sent to your Supervisor for approval.', 'warning');
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
    $shift_cookie = isset($_COOKIE['agent_shift_' . $agent_id]) ? json_decode($_COOKIE['agent_shift_' . $agent_id], true) : null;
    $ended_at = date('Y-m-d H:i:s');
    endAgentShift($conn, (int)$agent_id, $shift_work_date, $ended_at);
    if ($shift_cookie && isset($shift_cookie['date']) && $shift_cookie['date'] == $shift_work_date) {
        $shift_cookie['ended'] = true;
        $shift_cookie['ended_at'] = $ended_at;
        setcookie('agent_shift_' . $agent_id, json_encode($shift_cookie), time() + 86400 * 30, '/');
    }
    auditAction($conn, 'END_AGENT_SHIFT', $agent_id);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Auto-start shift
if ($today_assignment && !getAgentActiveShift($conn, (int)$agent_id, $shift_work_date)) {
    [$allowedAutoStart, $autoStartMsg] = sisCanAgentLoginForShift($today_assignment);
    if ($allowedAutoStart) {
        [$supervisorAllowedAuto, $supervisorAutoMsg] = sisMaybeRequireSupervisorLoginApproval($conn, (int)$agent_id, $outlet_id, $today_assignment, '');
        if ($supervisorAllowedAuto) {
            $autoStartTime = date('Y-m-d H:i:s');
            startAgentShift($conn, (int)$agent_id, (int)$outlet_id, $today_assignment['shift_type'], $shift_work_date, $autoStartTime);
            sisUpdateAssignmentAttendance($conn, $today_assignment, $autoStartTime);
        } else {
            setFlashMessage($supervisorAutoMsg, 'warning');
        }
    }
}

$active_shift = getAgentActiveShift($conn, (int)$agent_id, $shift_work_date);
if ($active_shift) {
    $shift_selected = true;
    $current_shift = strtolower((string)$active_shift['shift_type']);
    $shift_started_at = $active_shift['login_time'];
    $shift_ended = false;
    $shift_ended_at = null;
    $can_record = true;
    $shift_data = [
        'shift_id' => (int)$active_shift['id'],
        'shift' => $current_shift,
        'date' => $shift_work_date,
        'started_at' => $shift_started_at,
        'ended' => false,
        'ended_at' => null
    ];
    setcookie('agent_shift_' . $agent_id, json_encode($shift_data), time() + 86400 * 30, '/');
} else {
    $shift_cookie = isset($_COOKIE['agent_shift_' . $agent_id]) ? json_decode($_COOKIE['agent_shift_' . $agent_id], true) : null;
    $last_ended_db_shift = function_exists('sisAgentLastEndedShiftForDate') ? sisAgentLastEndedShiftForDate($conn, (int)$agent_id, $shift_work_date) : null;
    if ($today_assignment && $last_ended_db_shift) {
        $shift_selected = true;
        $current_shift = strtolower((string)($today_assignment['shift_type'] ?? $last_ended_db_shift['shift_type'] ?? 'morning'));
        $shift_started_at = $last_ended_db_shift['login_time'] ?? null;
        $shift_ended = true;
        $shift_ended_at = $last_ended_db_shift['logout_time'] ?? null;
        $can_record = false;
        $shift_data = [
            'shift_id' => (int)($last_ended_db_shift['id'] ?? 0),
            'shift' => $current_shift,
            'date' => $shift_work_date,
            'started_at' => $shift_started_at,
            'ended' => true,
            'ended_at' => $shift_ended_at
        ];
        setcookie('agent_shift_' . $agent_id, json_encode($shift_data), time() + 86400 * 30, '/');
    } elseif ($shift_cookie && isset($shift_cookie['date']) && $shift_cookie['date'] == $shift_work_date) {
        $shift_selected = true;
        $current_shift = $shift_cookie['shift'];
        $shift_started_at = $shift_cookie['started_at'];
        $shift_ended = isset($shift_cookie['ended']) ? (bool)$shift_cookie['ended'] : false;
        $shift_ended_at = $shift_cookie['ended_at'] ?? null;
        $can_record = !$shift_ended;
        if (!$shift_ended) {
            startAgentShift($conn, (int)$agent_id, (int)$outlet_id, $current_shift, $shift_work_date, $shift_started_at);
        }
    }
}

$duration_display = '';
if ($shift_started_at && !$shift_ended) {
    $duration_display = formatDuration($shift_started_at);
} elseif ($shift_started_at && $shift_ended && $shift_ended_at) {
    $duration_display = formatDuration($shift_started_at, $shift_ended_at);
}
$confirm_duration = $duration_display ?: '0 hrs 0 mins';
if ($today_assignment && $active_shift && !$shift_ended) {
    $overtime_minutes_now = sisOvertimeMinutes($today_assignment);
    $assignment_key = (int)$today_assignment['id'];
    $already_confirmed = !empty($_SESSION['overtime_confirmed_assignment_' . $assignment_key]) || !empty($_COOKIE['sis_overtime_confirmed_' . $assignment_key]);
    if ($overtime_minutes_now >= 60 && !$already_confirmed) {
        $show_overtime_modal = true;
    }
    $early_end_pending = sisGetPendingShiftRequest($conn, $assignment_key, 'early_end');
}

// ============================================
// ORIGINAL APPLICATION LOGIC
// ============================================

function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $type;
}

function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_message_type'] ?? null;
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);
    return ['message' => $message, 'type' => $type];
}

// ============================================
// CSRF VALIDATION
// Only require CSRF for record-creating/editing actions.
// Simple status actions (cancel, mark paid/pending) are session-bound and
// verify agent ownership inside the query, so they don't need CSRF.
// ============================================
$sisCustomerSalePostActions = ['add_visitor','edit_sale','add_multi_sale'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sisCustomerSalePost = false;
    foreach ($sisCustomerSalePostActions as $sisActionName) {
        if (isset($_POST[$sisActionName])) { $sisCustomerSalePost = true; break; }
    }
    if ($sisCustomerSalePost) {
        if (!verifyCsrf()) {
            setFlashMessage('Security validation failed. Please refresh the page and try again.', 'warning');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

function makeStamp($name, $role) {
    $role_label = ($role === 'supervisor') ? 'supervisor' : 'agent';
    return '[Recorded by ' . $role_label . ': ' . $name . ' @ ' . date('d/m/Y H:i') . ']';
}

function makeEditStamp($name, $role) {
    $role_label = ($role === 'supervisor') ? 'supervisor' : 'agent';
    return '[Edited by ' . $role_label . ': ' . $name . ' @ ' . date('d/m/Y H:i') . ']';
}

// ============================================
// CANCELLATION HELPERS (NO DATABASE CHANGES)
// ============================================

/**
 * Build a cancellation marker that will be appended to notes.
 * Format: [CANCELLED by agent: NAME @ date | Reason: TEXT]
 */
function makeCancelStamp($name, $role, $reason = '') {
    $role_label = ($role === 'supervisor') ? 'supervisor' : 'agent';
    $reason_clean = trim(str_replace(["\r", "\n", ']'], ' ', $reason));
    if (mb_strlen($reason_clean) > 200) $reason_clean = mb_substr($reason_clean, 0, 200);
    $stamp = '[CANCELLED by ' . $role_label . ': ' . $name . ' @ ' . date('d/m/Y H:i');
    if ($reason_clean !== '') $stamp .= ' | Reason: ' . $reason_clean;
    return $stamp . ']';
}

/**
 * Detect if a notes string contains a cancellation marker.
 */
function isCancelledNote($notes) {
    return (stripos((string)$notes, '[CANCELLED by') !== false);
}

/**
 * Extract the cancellation reason from notes, if present.
 */
function extractCancelReason($notes) {
    if (preg_match('/\[CANCELLED by [^\]]*?(?:\| Reason:\s*([^\]]+))?\]/i', (string)$notes, $m)) {
        return isset($m[1]) ? trim($m[1]) : '';
    }
    return '';
}

function isStrictWithdrawal($service_name) {
    $patterns = ['withdrawal', 'cash out', 'cashout', 'comcach withdrawal', 'comcah withdrawal'];
    foreach ($patterns as $pattern) {
        if (stripos($service_name, $pattern) !== false) {
            if (stripos($service_name, 'deposit') === false && 
                stripos($service_name, 'top up') === false &&
                stripos($service_name, 'topup') === false &&
                stripos($service_name, 'airtime') === false) {
                return true;
            }
        }
    }
    return false;
}

$quantity_column_exists = tableColumnExists($conn, 'visitors', 'quantity');

$services_query = "SELECT * FROM services WHERE is_active = 1 ORDER BY is_paid DESC, name";
$services_result = mysqli_query($conn, $services_query);

$purpose_query = "SELECT p.*, c.name as category_name, c.color as category_color 
                 FROM purpose_of_visit p 
                 LEFT JOIN purpose_categories c ON p.category_id = c.id 
                 WHERE p.is_active = 1 
                 ORDER BY c.sort_order, p.name";
$purpose_result = mysqli_query($conn, $purpose_query);

// Add visitor
$open_customer_form_from_server = $can_record ? consumeHandleTimerStartRequest('customer') : false;
$open_same_customer_form_from_server = false;
$open_same_customer_visitor_id = isset($_GET['same_customer_visitor_id']) ? (int)$_GET['same_customer_visitor_id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['start_handle_timer'] ?? '') === 'same_customer' && $open_same_customer_visitor_id > 0) {
    startHandleTimerContext('same_customer');
    $open_same_customer_form_from_server = true;
}

if ($can_record && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_visitor'])) {
    $purpose_id = (int)$_POST['purpose_id'];
    $service_id = (int)$_POST['service_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $notes = mysqli_real_escape_string($conn, (string)($_POST['notes'] ?? 'Fast entry'));
    $status = 'Waiting';
    $visit_time = date('Y-m-d H:i:s');
    $handle_timing = getPostedHandleTiming();
    $customer_gsm = mysqli_real_escape_string($conn, (string)($_POST['customer_gsm'] ?? ''));
    $default_name = 'Customer';
    $default_gender = 'Other';
    
    $service_check = mysqli_query($conn, "SELECT * FROM services WHERE id = $service_id");
    $service = $service_check ? mysqli_fetch_assoc($service_check) : null;
    if (!$service) {
        setFlashMessage('Invalid or inactive service selected. Please refresh and try again.', 'danger');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    $quantity = max(1, min(100000, $quantity));
    $custom_amount = null;
    if (isset($_POST['custom_amount']) && $_POST['custom_amount'] !== '') {
        $custom_amount = (float)$_POST['custom_amount'];
    }
    $is_withdrawal = isStrictWithdrawal($service['name']);
    $create_stamp = makeStamp($agent_name, 'agent');
    $notes_with_stamp = $create_stamp . ' — ' . $notes;
    
    mysqli_begin_transaction($conn);
    $visitor_id = insertVisitorSafe($conn, [
        'name' => $default_name,
        'contact' => $customer_gsm,
        'gender' => $default_gender,
        'purpose_id' => $purpose_id,
        'service_id' => $service_id,
        'quantity' => $quantity,
        'agent_id' => $agent_id,
        'outlet_id' => $outlet_id,
        'status' => $status,
        'notes' => $notes_with_stamp,
        'visit_time' => $visit_time,
    ], $quantity_column_exists);
    
    if ($visitor_id) {
        logAction($conn, 'ADD_VISITOR', $visitor_id);
        applyVisitorHandleTime($conn, $visitor_id, null, null, $handle_timing['seconds']);
        
        $total_amount = 0;
        $is_paid_service = false;
        if ($service && $service['is_paid'] == 1) {
            $is_paid_service = true;
            $is_fixed_price = isset($service['is_fixed_price']) ? $service['is_fixed_price'] : 1;
            if (!$is_fixed_price && $custom_amount !== null) {
                $total_amount = $custom_amount;
            } else if (isset($service['is_quantity_based']) && $service['is_quantity_based']) {
                $total_amount = $service['price'] * $quantity;
            } else {
                $total_amount = $service['price'];
            }
        }
        $payment_date = date('Y-m-d H:i:s');
        $invoice = ($is_withdrawal ? 'WDL-' : 'SLS-') . date('Ymd') . '-' . $visitor_id;
        $amount_to_store = $is_withdrawal ? -abs($total_amount) : abs($total_amount);
        $sale_id = insertSaleSafe($conn, [
            'visitor_id' => $visitor_id,
            'agent_id' => $agent_id,
            'outlet_id' => $outlet_id,
            'amount' => $amount_to_store,
            'payment_status' => 'Paid',
            'invoice_number' => $invoice,
            'payment_date' => $payment_date,
        ]);
        if (!$sale_id) {
            mysqli_rollback($conn);
            setFlashMessage('The sale could not be saved. No partial customer record was created. Please try again.', 'danger');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
        setSaleHandleTime($conn, $sale_id, $handle_timing['started_at'], $handle_timing['completed_at'], $handle_timing['seconds']);
        markVisitorCompleted($conn, $visitor_id);
        applyVisitorHandleTime($conn, $visitor_id, null, null, $handle_timing['seconds']);
        if (!mysqli_commit($conn)) {
            mysqli_rollback($conn);
            setFlashMessage('The sale could not be completed. Please try again.', 'danger');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
        
        if ($is_withdrawal) {
            setFlashMessage("✓ COMCAH WITHDRAWAL: D " . number_format($total_amount, 2) . " given to customer (" . ($customer_gsm ?: 'Not provided') . ")", "success");
        } else if ($is_paid_service) {
            setFlashMessage("✓ Sale added at " . date('h:i A') . " | GSM: " . ($customer_gsm ?: 'Not provided') . " | Amount: D " . number_format($total_amount, 2), "success");
        } else {
            setFlashMessage("✓ Free service recorded at " . date('h:i A') . " for GSM: " . ($customer_gsm ?: 'Not provided'), "success");
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        mysqli_rollback($conn);
        setFlashMessage(sisDbErrorMessage($conn), 'danger');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Edit customer/sale record
if ($can_record && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_sale'])) {
    $sale_id    = (int)($_POST['sale_id'] ?? 0);
    $visitor_id = (int)($_POST['visitor_id'] ?? 0);
    $amount     = normalizeMoney($_POST['amount'] ?? 0);
    $sale_date  = mysqli_real_escape_string($conn, (string)($_POST['sale_date'] ?? date('Y-m-d')));
    $pmt_status = mysqli_real_escape_string($conn, (string)($_POST['payment_status'] ?? 'Paid'));
    $purpose_id = normalizeNullableInt($_POST['purpose_id'] ?? null);
    $service_id = normalizeNullableInt($_POST['service_id'] ?? null);
    $quantity   = max(1, (int)($_POST['quantity'] ?? 1));
    $visitor_status = $_POST['visitor_status'] ?? 'Completed';
    if (!in_array($visitor_status, ['Waiting','In Progress','Completed','Cancelled'], true)) $visitor_status = 'Completed';
    $visitor_notes_raw = trim((string)($_POST['visitor_notes'] ?? ''));
    // Preserve any existing cancellation marker; do not strip it during edit
    $existing_cancel_marker = '';
    if (preg_match('/\[CANCELLED by [^\]]+\]/i', $visitor_notes_raw, $cm)) {
        $existing_cancel_marker = ' ' . $cm[0];
        $visitor_notes_raw = str_replace($cm[0], '', $visitor_notes_raw);
    }
    $visitor_notes_raw = preg_replace('/\s*\[Edited by [^\]]+\]/i', '', $visitor_notes_raw);
    $visitor_notes = trim($visitor_notes_raw . ' ' . makeEditStamp($agent_name, 'agent') . $existing_cancel_marker);

    $sale_check = mysqli_query($conn, "SELECT sl.*, v.id AS visitor_id, v.service_id, s.name AS service_name
                                        FROM sales sl
                                        LEFT JOIN visitors v ON sl.visitor_id = v.id
                                        LEFT JOIN services s ON v.service_id = s.id
                                        WHERE sl.id = $sale_id AND sl.agent_id = $agent_id LIMIT 1");
    $sale_data = $sale_check ? mysqli_fetch_assoc($sale_check) : null;
    if (!$sale_data) {
        setFlashMessage('Sale record not found or you are not allowed to edit it.', 'danger');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    if ($visitor_id <= 0) $visitor_id = (int)($sale_data['visitor_id'] ?? 0);

    $service_name_for_sign = (string)($sale_data['service_name'] ?? '');
    if ($service_id) {
        $svc_res = mysqli_query($conn, 'SELECT name FROM services WHERE id = ' . (int)$service_id . ' LIMIT 1');
        if ($svc_res && ($svc_row = mysqli_fetch_assoc($svc_res))) $service_name_for_sign = (string)$svc_row['name'];
    }
    $is_withdrawal = isStrictWithdrawal($service_name_for_sign);
    $amount_to_store = $is_withdrawal ? -abs($amount) : abs($amount);

    $stmt = mysqli_prepare($conn, "UPDATE sales SET amount = ?, payment_date = ?, payment_status = ? WHERE id = ? AND agent_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'dssii', $amount_to_store, $sale_date, $pmt_status, $sale_id, $agent_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    if ($visitor_id > 0) {
        if (tableColumnExists($conn, 'visitors', 'quantity')) {
            $vstmt = mysqli_prepare($conn, "UPDATE visitors SET purpose_id = ?, service_id = ?, quantity = ?, status = ?, notes = ? WHERE id = ? AND agent_id = ?");
            if ($vstmt) {
                mysqli_stmt_bind_param($vstmt, 'iiissii', $purpose_id, $service_id, $quantity, $visitor_status, $visitor_notes, $visitor_id, $agent_id);
                mysqli_stmt_execute($vstmt);
                mysqli_stmt_close($vstmt);
            }
        } else {
            $vstmt = mysqli_prepare($conn, "UPDATE visitors SET purpose_id = ?, service_id = ?, status = ?, notes = ? WHERE id = ? AND agent_id = ?");
            if ($vstmt) {
                mysqli_stmt_bind_param($vstmt, 'iissii', $purpose_id, $service_id, $visitor_status, $visitor_notes, $visitor_id, $agent_id);
                mysqli_stmt_execute($vstmt);
                mysqli_stmt_close($vstmt);
            }
        }
    }

    auditAction($conn, 'EDIT_CUSTOMER_SALE', $sale_id, 'Edited customer/sale record; GSM locked');
    setFlashMessage("✓ Customer/Sale record updated successfully. GSM number was locked and not changed.", "success");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Add multiple services
if ($can_record && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_multi_sale'])) {
    $visitor_id  = (int)$_POST['visitor_id'];
    $sale_date   = mysqli_real_escape_string($conn, (string)($_POST['sale_date_multi'] ?? ''));
    $handle_timing = getPostedHandleTiming();
    $service_ids  = $_POST['ms_service_id'] ?? [];
    $amounts     = $_POST['ms_amount']   ?? [];
    $statuses    = $_POST['ms_status']   ?? [];
    $inserted    = 0;
    foreach ($service_ids as $i => $service_id) {
        $service_id = (int)$service_id;
        if ($service_id <= 0) continue;
        $service_info = mysqli_query($conn, "SELECT name, price, is_paid FROM services WHERE id = $service_id");
        $service = mysqli_fetch_assoc($service_info);
        if (!$service) continue;
        $is_withdrawal = isStrictWithdrawal($service['name']);
        $amount = floatval($amounts[$i] ?? 0);
        $amount_to_store = $is_withdrawal ? -abs($amount) : abs($amount);
        $pmt_status = mysqli_real_escape_string($conn, $statuses[$i] ?? 'Paid');
        $invoice = 'MULTI-' . date('Ymd') . '-' . $visitor_id . '-' . ($i+1);
        $sale_id = insertSaleSafe($conn, [
            'visitor_id' => $visitor_id,
            'agent_id' => $agent_id,
            'outlet_id' => $outlet_id,
            'amount' => $amount_to_store,
            'payment_status' => $pmt_status,
            'invoice_number' => $invoice,
            'payment_date' => $sale_date,
        ]);
        if ($sale_id) {
            setSaleHandleTime($conn, $sale_id, $handle_timing['started_at'], $handle_timing['completed_at'], $handle_timing['seconds']);
            logAction($conn, 'ADD_MULTI_SALE', $sale_id);
            $inserted++;
        }
    }
    setFlashMessage("✓ $inserted service(s) added successfully to existing customer.", "success");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================
// CANCEL SERVICE - HIDES SALE FROM TRANSACTION TABLE
// Marks payment_status as 'Unpaid' + adds [CANCELLED by agent: ...] marker to notes.
// The transaction table excludes rows with this marker.
// ============================================
if ($can_record && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_single_service'])) {
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    $cancel_reason = trim((string)($_POST['cancel_reason'] ?? ''));

    if ($sale_id <= 0) {
        setFlashMessage('❌ Invalid sale ID. Please try again.', 'danger');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    if ($cancel_reason === '') {
        $cancel_reason = 'Cancelled by agent';
    }

    // Look up sale and its visitor
    $sale_check = mysqli_query($conn, "SELECT sl.id, sl.visitor_id, sl.amount, v.notes AS visitor_notes
                                       FROM sales sl
                                       LEFT JOIN visitors v ON sl.visitor_id = v.id
                                       WHERE sl.id = $sale_id AND sl.agent_id = $agent_id LIMIT 1");
    $sale_data = $sale_check ? mysqli_fetch_assoc($sale_check) : null;

    if (!$sale_data) {
        setFlashMessage('❌ Sale record not found or you are not allowed to cancel it.', 'danger');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $visitor_id = (int)($sale_data['visitor_id'] ?? 0);
    $previous_notes = (string)($sale_data['visitor_notes'] ?? '');

    // Already cancelled?
    if (stripos($previous_notes, '[CANCELLED by') !== false) {
        setFlashMessage('⚠️ This service is already cancelled.', 'warning');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Build a fresh cancellation marker, preserving other notes
    $existing_notes_clean = preg_replace('/\[CANCELLED by [^\]]+\]/i', '', $previous_notes);
    $existing_notes_clean = trim($existing_notes_clean);
    if ($existing_notes_clean !== '') $existing_notes_clean .= ' ';
    $new_notes = $existing_notes_clean . makeCancelStamp($agent_name, 'agent', $cancel_reason);
    $safe_notes = mysqli_real_escape_string($conn, $new_notes);

    // 1. Mark the sale as Unpaid (available ENUM value) - keeps it out of revenue
    $update_sale = "UPDATE sales SET payment_status = 'Unpaid' WHERE id = $sale_id AND agent_id = $agent_id";
    if (!mysqli_query($conn, $update_sale)) {
        error_log(sisDbErrorMessage($conn));
        setFlashMessage(sisDbErrorMessage($conn), 'danger');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // 2. Add cancellation marker to visitor notes (this is how the UI hides the row)
    if ($visitor_id > 0) {
        $update_visitor = "UPDATE visitors SET status = 'Cancelled', notes = '$safe_notes' 
                           WHERE id = $visitor_id AND agent_id = $agent_id";
        if (!mysqli_query($conn, $update_visitor)) {
            error_log(sisDbErrorMessage($conn));
        }
    }

    // 3. Full audit trail (existing table, no schema change needed)
    if (function_exists('auditAction')) {
        auditAction($conn, 'CANCEL_SALE', $sale_id, 'Cancelled by agent. Reason: ' . $cancel_reason);
    }

    setFlashMessage("✅ Service cancelled and removed from today's transactions. Reason: " . htmlspecialchars($cancel_reason), "success");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Mark as paid
if ($can_record && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_paid'])) {
    $sale_id = (int)$_POST['sale_id'];
    $update = "UPDATE sales SET payment_status = 'Paid', payment_date = NOW() WHERE id = $sale_id AND agent_id = $agent_id";
    mysqli_query($conn, $update);
    setFlashMessage("✓ Payment marked as paid!", "success");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Update payment status
if ($can_record && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    $sale_id = (int)$_POST['sale_id'];
    $payment_status = mysqli_real_escape_string($conn, (string)($_POST['payment_status'] ?? ''));
    if (in_array($payment_status, ['Paid', 'Pending', 'Unpaid'], true)) {
        $update = "UPDATE sales SET payment_status = '$payment_status' WHERE id = $sale_id AND agent_id = $agent_id";
        mysqli_query($conn, $update);
        setFlashMessage("✓ Payment status updated!", "success");
    } else {
        setFlashMessage("⚠️ Invalid payment status.", "warning");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$flash = getFlashMessage();
$message = $flash['message'];
$message_type = $flash['type'];

// Today's statistics (EXCLUDES cancelled sales)
$today = date('Y-m-d');
$visitors_query = "SELECT COUNT(DISTINCT v.id) as unique_visitors, COUNT(sl.id) as total_services
                   FROM visitors v 
                   LEFT JOIN sales sl ON v.id = sl.visitor_id
                   WHERE v.agent_id = $agent_id 
                     AND DATE(v.visit_time) = '$today'
                     AND (v.notes IS NULL OR v.notes NOT LIKE '%[CANCELLED by%')";
$visitors_count_result = mysqli_query($conn, $visitors_query);
$visitors_data = mysqli_fetch_assoc($visitors_count_result);
$unique_visitors = $visitors_data['unique_visitors'] ?? 0;
$total_services = $visitors_data['total_services'] ?? 0;

$collections_query = "SELECT SUM(sl.amount) as collected_amount
                      FROM sales sl
                      LEFT JOIN visitors v ON sl.visitor_id = v.id
                      LEFT JOIN services s ON v.service_id = s.id
                      WHERE sl.agent_id = $agent_id 
                        AND DATE(sl.payment_date) = '$today'
                        AND sl.payment_status = 'Paid'
                        AND sl.amount > 0
                        AND (v.notes IS NULL OR v.notes NOT LIKE '%[CANCELLED by%')";
$collections_result = mysqli_query($conn, $collections_query);
$collected_amount = mysqli_fetch_assoc($collections_result)['collected_amount'] ?? 0;

$withdrawal_query = "SELECT SUM(ABS(sl.amount)) as withdrawal_amount
                     FROM sales sl
                     LEFT JOIN visitors v ON sl.visitor_id = v.id
                     LEFT JOIN services s ON v.service_id = s.id
                     WHERE sl.agent_id = $agent_id 
                       AND DATE(sl.payment_date) = '$today'
                       AND sl.payment_status = 'Paid'
                       AND sl.amount < 0
                       AND (v.notes IS NULL OR v.notes NOT LIKE '%[CANCELLED by%')";
$withdrawal_result = mysqli_query($conn, $withdrawal_query);
$withdrawal_amount = mysqli_fetch_assoc($withdrawal_result)['withdrawal_amount'] ?? 0;

$pending_query = "SELECT SUM(sl.amount) as pending_amount
                  FROM sales sl
                  LEFT JOIN visitors v ON sl.visitor_id = v.id
                  LEFT JOIN services s ON v.service_id = s.id
                  WHERE sl.agent_id = $agent_id 
                    AND DATE(sl.payment_date) = '$today'
                    AND sl.payment_status = 'Pending'
                    AND sl.amount > 0
                    AND (v.notes IS NULL OR v.notes NOT LIKE '%[CANCELLED by%')";
$pending_result = mysqli_query($conn, $pending_query);
$pending_amount = mysqli_fetch_assoc($pending_result)['pending_amount'] ?? 0;

$salesHandleExpr = tableColumnExists($conn, 'sales', 'handle_seconds') ? 'sl.handle_seconds' : 'NULL';
$salesStartedExpr = tableColumnExists($conn, 'sales', 'handle_started_at') ? 'sl.handle_started_at' : 'NULL';
$salesCompletedExpr = tableColumnExists($conn, 'sales', 'handle_completed_at') ? 'sl.handle_completed_at' : 'NULL';
$visitorHandleExpr = tableColumnExists($conn, 'visitors', 'handling_seconds') ? 'v.handling_seconds' : 'NULL';

// FIXED: Exclude cancelled sales from the transaction table.
// Cancelled sales have the [CANCELLED by ...] marker in visitor notes.
$sales_query = "SELECT sl.*, 
                       CASE WHEN sl.amount < 0 THEN ABS(sl.amount) ELSE sl.amount END as display_amount,
                       COALESCE($salesHandleExpr, $visitorHandleExpr) as row_handle_seconds,
                       $salesStartedExpr as row_handle_started_at,
                       $salesCompletedExpr as row_handle_completed_at,
                       v.contact as customer_gsm, v.visit_time, v.id as visitor_id, 
                       v.name as visitor_name, v.purpose_id, v.service_id, v.quantity, v.status as visitor_status, v.notes as visitor_notes,
                       p.name as purpose_name, s.name as service_name
                FROM sales sl
                LEFT JOIN visitors v ON sl.visitor_id = v.id
                LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
                LEFT JOIN services s ON v.service_id = s.id
                WHERE sl.agent_id = $agent_id 
                  AND DATE(sl.payment_date) = '$today'
                  AND (v.notes IS NULL OR v.notes NOT LIKE '%[CANCELLED by%')
                ORDER BY sl.payment_date DESC";
$sales_result = mysqli_query($conn, $sales_query);

$services_for_modal = [];
mysqli_data_seek($services_result, 0);
while ($service = mysqli_fetch_assoc($services_result)) {
    $services_for_modal[] = [
        'id' => $service['id'],
        'name' => $service['name'],
        'price' => $service['price'],
        'is_paid' => $service['is_paid'],
        'is_withdrawal' => isStrictWithdrawal($service['name'])
    ];
}
$purposes_for_modal = [];
if ($purpose_result) {
    mysqli_data_seek($purpose_result, 0);
    while ($purpose = mysqli_fetch_assoc($purpose_result)) {
        $purposes_for_modal[] = [
            'id' => (int)$purpose['id'],
            'name' => (string)$purpose['name'],
            'category' => (string)($purpose['category_name'] ?? 'General'),
        ];
    }
}
$purposes_json = json_encode($purposes_for_modal);
$services_json = json_encode($services_for_modal);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $sisAgentSalesPage ? 'Agent Sales' : 'Agent Dashboard'; ?> - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
    <style>
        /* Cancelled row styling - rows are now excluded from the table so this is minimal fallback */
        .sis-tt-row.is-cancelled {
            opacity: 0.65;
            background: #fff5f5 !important;
        }
        .sis-tt-row.is-cancelled .sis-tt-amount {
            text-decoration: line-through;
            color: #999 !important;
        }
        .sis-tt-status.cancelled {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .cancel-reason-preview {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 8px;
        }
    </style>
</head>
<body class="sis-app-body">
<?php
require_once dirname(__DIR__) . '/include/side_header.php';
sisRenderLayoutStart(
    'agent',
    $sisAgentSalesPage ? 'Add Customer / Sale' : 'Agent Dashboard',
    $sisAgentSalesPage ? 'Record and manage today’s customer transactions' : 'Shift status and today’s performance overview'
);
?>

    <div class="container mb-4">
        <?php sisRenderDashboardAnnouncements($conn, 'Agent Announcements'); ?>
        <?php if(isset($message) && $message): ?>
        <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show small">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!$shift_selected): ?>
            <div class="alert alert-warning text-center">
                <i class="bi bi-exclamation-triangle-fill"></i> Please select your shift to start recording transactions.
            </div>
        <?php elseif ($shift_ended): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle-fill"></i> Your shift has ended. You can view today's transactions but cannot add new ones.
            </div>
        <?php endif; ?>

        <?php if ($today_assignment): [$assign_start_ts, $assign_end_ts] = sisShiftWindowTimestamps($today_assignment); ?>
            <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong><i class="bi bi-calendar-check text-danger"></i> Assigned Shift:</strong>
                    <?php echo e(sisShiftLabel($today_assignment['shift_type'])); ?> • <?php echo e(date('H:i', $assign_start_ts)); ?> - <?php echo e(date('H:i', $assign_end_ts)); ?>
                    <small class="d-block text-muted">Login allowed from <?php echo e(date('H:i', $assign_start_ts - 3600)); ?> to <?php echo e(date('H:i', $assign_end_ts + 3600)); ?>.</small>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="my_shift.php"><i class="bi bi-eye"></i> View My Shift</a>
            </div>
            <?php if($early_end_pending): ?><div class="alert alert-warning"><i class="bi bi-hourglass-split"></i> Early-end request pending Supervisor approval.</div><?php endif; ?>
        <?php endif; ?>

        <?php if (!$sisAgentSalesPage && $shift_selected): ?>
        <div class="stat-grid">
            <div class="stat-card border-visitors">
                <h6><i class="bi bi-people"></i> VISITORS</h6>
                <h3><?php echo $unique_visitors; ?></h3>
                <small>Unique customers today</small>
            </div>
            <div class="stat-card border-services">
                <h6><i class="bi bi-receipt"></i> SERVICES</h6>
                <h3><?php echo $total_services; ?></h3>
                <small>Total services sold</small>
            </div>
            <div class="stat-card border-collected">
                <h6><i class="bi bi-cash-stack"></i> MONEY COLLECTED</h6>
                <h3>D <?php echo number_format($collected_amount, 2); ?></h3>
                <small>From sales (excludes withdrawals)</small>
            </div>
            <div class="stat-card border-withdrawal">
                <h6><i class="bi bi-cash"></i> COMCAH WITHDRAWALS</h6>
                <h3>D <?php echo number_format($withdrawal_amount, 2); ?></h3>
                <small>Money given out (withdrawals only)</small>
            </div>
            <div class="stat-card border-pending">
                <h6><i class="bi bi-clock-history"></i> PENDING</h6>
                <h3>D <?php echo number_format($pending_amount, 2); ?></h3>
                <small>Awaiting payment</small>
            </div>
        </div>
        <div class="form-section text-center">
            <div class="form-section-title"><i class="bi bi-receipt text-danger me-2"></i> Customer Sales</div>
            <p class="text-muted mb-3">Record a new customer service or review today's transactions on the dedicated Sales page.</p>
            <a href="sales.php" class="btn btn-danger btn-lg px-5"><i class="bi bi-person-plus-fill"></i> Open Sales</a>
            <a href="support.php" class="btn btn-outline-primary btn-lg px-4 ms-2"><i class="bi bi-headset"></i> Complaint / Support</a>
        </div>
        <?php endif; ?>

        <?php if ($sisAgentSalesPage): ?>
        <?php if ($can_record): ?>
        <div class="form-section text-center">
            <div class="form-section-title"><i class="bi bi-person-plus-fill text-danger me-2"></i> Customer Service Timer</div>
            <p class="text-muted mb-3">Click the button when the customer is attended to. The timer starts when the form opens and stops when you submit.</p>
            <a href="<?php echo e(getHandleTimerContextUrl('customer')); ?>" class="btn btn-danger btn-lg px-5"><i class="bi bi-stopwatch-fill"></i> Start Attending Customer / Add Sale</a>
            <a href="support.php" class="btn btn-outline-primary btn-lg px-4 ms-2"><i class="bi bi-headset"></i> Complaint / Support</a>
        </div>

        <div class="modal-overlay" id="customerFormModal" onclick="closeCustomerFormModalOnBackdrop(event)">
            <div class="modal-container large">
                <div class="modal-header sis-u-e14d6806">
                    <h5><i class="bi bi-stopwatch-fill"></i> Add Customer / Sale</h5>
                    <button type="button" class="modal-close" onclick="closeCustomerFormModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="visitorForm" data-customer-service-form="1" onsubmit="return window.SISHandleTimer ? window.SISHandleTimer.stopFormBeforeSubmit(this) : true;">
                        <?php echo csrfField(); ?>
                        <?php renderHandleTimerFields('Customer Handle Time', false, 'customer'); ?>
                <div class="gsm-section">
                    <div class="row"><div class="col-md-12"><label class="form-label"><i class="bi bi-phone"></i> Customer GSM Number<span class="text-danger">*</span></label><input type="tel" class="form-control" name="customer_gsm" id="customer_gsm" required placeholder="Enter GSM number e.g., 6600309" pattern="[0-9]{7,15}"><small class="text-muted">Required - Enter without spaces</small></div></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label"><i class="bi bi-tag"></i> Purpose of Visit <span class="text-danger">*</span></label>
                        <select class="form-select searchable-purpose" name="purpose_id" id="purposeSelect" required>
                            <option value="">🔍 Search or select purpose...</option>
                            <?php $current_category = ''; mysqli_data_seek($purpose_result, 0); while($purpose = mysqli_fetch_assoc($purpose_result)): if($purpose['category_name'] != $current_category) { if($current_category != '') echo '</optgroup>'; echo '<optgroup label="' . htmlspecialchars($purpose['category_name']) . '">'; $current_category = $purpose['category_name']; } ?>
                            <option value="<?php echo e($purpose['id']); ?>"><?php echo htmlspecialchars($purpose['name']); ?></option>
                            <?php endwhile; if($current_category != '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label"><i class="bi bi-gear"></i> Service <span class="text-danger">*</span></label>
                        <select class="form-select searchable-service" name="service_id" id="serviceSelect" required>
                            <option value="">🔍 Search or select service...</option>
                            <?php mysqli_data_seek($services_result, 0); while($service = mysqli_fetch_assoc($services_result)): 
                                $is_quantity = isset($service['is_quantity_based']) ? $service['is_quantity_based'] : false;
                                $is_fixed = isset($service['is_fixed_price']) ? $service['is_fixed_price'] : 1;
                                $is_withdrawal = isStrictWithdrawal($service['name']);
                                $price_display = '';
                                if ($service['is_paid'] == 1) {
                                    if (!$is_fixed) { $min = $service['min_amount'] ?? 0; $max = $service['max_amount'] ?? 0; $price_display = ' - 💲 Flexible (' . $min . '-' . $max . ' D)'; }
                                    else { $price_display = ' - 💰 ' . number_format($service['price'], 2) . ' D'; }
                                } else { $price_display = ' - 🆓 FREE'; }
                                if ($is_withdrawal) $price_display .= ' 🚨 WITHDRAWAL';
                            ?>
                            <option value="<?php echo e($service['id']); ?>" data-paid="<?php echo e($service['is_paid']); ?>" data-quantity="<?php echo $is_quantity ? 1 : 0; ?>" data-price="<?php echo e($service['price']); ?>" data-is-fixed="<?php echo $is_fixed; ?>" data-min="<?php echo e($service['min_amount'] ?? 0); ?>" data-max="<?php echo e($service['max_amount'] ?? 0); ?>" data-withdrawal="<?php echo $is_withdrawal ? 1 : 0; ?>"><?php echo htmlspecialchars($service['name']) . $price_display; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-3 sis-u-224b51a7" id="quantityField"><label class="form-label"><i class="bi bi-hash"></i> Quantity</label><input type="number" class="form-control" name="quantity" id="quantity" value="1" min="1"></div>
                    <div class="col-md-4 sis-u-224b51a7" id="customAmountField"><label class="form-label"><i class="bi bi-cash"></i> Amount (D)</label><input type="number" class="form-control" name="custom_amount" id="customAmount" step="0.01" placeholder="Enter amount"></div>
                    <div class="col-md-3"><label class="form-label">&nbsp;</label><button type="submit" name="add_visitor" class="btn btn-primary w-100"><i class="bi bi-save"></i> Add Customer</button></div>
                </div>
                <div class="row mt-3"><div class="col-12 sis-u-224b51a7" id="totalPreview"><div class="summary-box show sis-u-7957bbd8"><strong><i class="bi bi-calculator"></i> Total: </strong><span class="total-preview" id="totalAmount">D 0.00</span><small class="text-muted ms-2" id="priceRangeNote"></small></div></div></div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($shift_selected): ?>
<?php if (!isset($sisCanUseTransactionActions)) { $sisCanUseTransactionActions = !isset($can_record) || $can_record; } ?>
        <div class="card sis-today-transactions-card sis-today-transactions" data-sis-today-transactions>
            <div class="card-header sis-tt-header">
                <div>
                    <div class="sis-tt-title"><i class="bi bi-list-check"></i> Today's Transactions</div>
                    <div class="sis-tt-subtitle">Clean view of all customers and sales recorded today. Cancelled services are automatically removed.</div>
                </div>
                <div class="sis-tt-header-actions">
                    <span class="sis-tt-count" data-tt-visible-count><?php echo mysqli_num_rows($sales_result); ?></span>
                    <span class="sis-tt-count-label">of <?php echo mysqli_num_rows($sales_result); ?> records</span>
                </div>
            </div>

            <div class="sis-tt-toolbar">
                <div class="sis-tt-search">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" data-tt-search placeholder="Search GSM, purpose, service, status, amount...">
                </div>
                <select class="form-select" data-tt-status-filter aria-label="Filter by payment status">
                    <option value="all">All payment status</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="unpaid">Unpaid</option>
                </select>
                <select class="form-select" data-tt-type-filter aria-label="Filter by transaction type">
                    <option value="all">All types</option>
                    <option value="sale">Sales</option>
                    <option value="withdrawal">Withdrawals</option>
                </select>
            </div>

            <div class="table-responsive sis-tt-table-wrap">
                <table class="table table-hover sis-tt-table mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Customer</th>
                            <th>Service Details</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Handle Time</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($sales_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($sales_result)):
                                $amount = (float)($row['display_amount'] ?? 0);
                                $payment_status = (string)($row['payment_status'] ?? 'Pending');
                                $service_name = (string)($row['service_name'] ?? '');
                                $purpose_name = (string)($row['purpose_name'] ?? '');
                                $customer_gsm = (string)($row['customer_gsm'] ?? '');
                                $visitor_notes = (string)($row['visitor_notes'] ?? '');
                                $is_withdrawal = isStrictWithdrawal($service_name);
                                $status_key = strtolower($payment_status ?: 'pending');
                                $transaction_type = $is_withdrawal ? 'withdrawal' : 'sale';
                                $row_search = strtolower(trim($customer_gsm . ' ' . $purpose_name . ' ' . $service_name . ' ' . $payment_status . ' ' . number_format($amount, 2)));
                                $qty = (int)($row['quantity'] ?? 1);
                                $recordPayload = [
                                    'visitorId' => (int)($row['visitor_id'] ?? 0),
                                    'gsm' => $customer_gsm,
                                    'purposeId' => (int)($row['purpose_id'] ?? 0),
                                    'serviceId' => (int)($row['service_id'] ?? 0),
                                    'quantity' => max(1, $qty),
                                    'visitorStatus' => (string)($row['visitor_status'] ?? 'Completed'),
                                    'notes' => $visitor_notes,
                                ];
                            ?>
                            <tr class="sis-tt-row" data-tt-row data-search="<?php echo htmlspecialchars($row_search, ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo htmlspecialchars($status_key, ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo $transaction_type; ?>">
                                <td class="sis-tt-time-cell">
                                    <strong><?php echo date('h:i A', strtotime($row['payment_date'])); ?></strong>
                                    <small><?php echo date('d M Y', strtotime($row['payment_date'])); ?></small>
                                </td>
                                <td>
                                    <div class="sis-tt-customer">
                                        <span class="sis-tt-avatar"><i class="bi bi-phone"></i></span>
                                        <div>
                                            <?php if($customer_gsm !== ''): ?>
                                                <strong class="sis-tt-gsm"><?php echo htmlspecialchars($customer_gsm); ?></strong>
                                                <small>GSM number locked for edits</small>
                                            <?php else: ?>
                                                <strong class="text-muted">No GSM</strong>
                                                <small>Customer number not saved</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sis-tt-service">
                                        <div class="sis-tt-service-title"><?php echo htmlspecialchars($service_name ?: 'No service'); ?></div>
                                        <div class="sis-tt-purpose"><?php echo htmlspecialchars($purpose_name ?: 'No purpose'); ?></div>
                                        <?php if($is_withdrawal): ?>
                                            <span class="sis-tt-type withdrawal"><i class="bi bi-arrow-down-circle"></i> Withdrawal</span>
                                        <?php else: ?>
                                            <span class="sis-tt-type sale"><i class="bi bi-cart-check"></i> Sale</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center"><span class="sis-tt-qty">x<?php echo max(1, $qty); ?></span></td>
                                <td class="text-end">
                                    <div class="sis-tt-amount <?php echo $is_withdrawal ? 'negative' : 'positive'; ?>">
                                        <?php echo $is_withdrawal ? '- D ' : '+ D '; ?><?php echo number_format($amount, 2); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if($status_key === 'paid'): ?>
                                        <span class="sis-tt-status paid"><i class="bi bi-check-circle"></i> Paid</span>
                                    <?php elseif($status_key === 'pending'): ?>
                                        <span class="sis-tt-status pending"><i class="bi bi-clock-history"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="sis-tt-status unpaid"><i class="bi bi-x-circle"></i> Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="sis-tt-handle"><?php echo renderHandleTimeCell($row['row_handle_seconds'] ?? null, $row['row_handle_started_at'] ?? null, $row['row_handle_completed_at'] ?? null); ?></td>
                                <td class="text-end">
                                    <?php if ($sisCanUseTransactionActions): ?>
                                    <div class="sis-tt-actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Edit record" onclick='openEditSaleModal(<?php echo (int)$row["id"]; ?>, <?php echo json_encode($amount); ?>, <?php echo json_encode($payment_status); ?>, <?php echo json_encode($row["payment_date"]); ?>, <?php echo $is_withdrawal ? "true" : "false"; ?>, <?php echo htmlspecialchars(json_encode($recordPayload), ENT_QUOTES, "UTF-8"); ?>)'>
                                            <i class="bi bi-pencil-square"></i><span>Edit</span>
                                        </button>
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="dropdown" aria-expanded="false" title="More actions">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end sis-u-019128fe">
                                                <?php if($payment_status !== 'Paid' && !$is_withdrawal): ?>
                                                <li>
                                                    <form method="POST" class="m-0"><?php echo csrfField(); ?>
                                                        <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                                                        <button type="submit" name="mark_as_paid" class="dropdown-item text-success"><i class="bi bi-check-circle"></i> Mark Paid</button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                                <li>
                                                    <form method="POST" class="m-0"><?php echo csrfField(); ?>
                                                        <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                                                        <input type="hidden" name="payment_status" value="Pending">
                                                        <button type="submit" name="update_payment_status" class="dropdown-item text-warning"><i class="bi bi-clock"></i> Mark Pending</button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger" 
                                                            onclick="openCancelModal(<?php echo (int)$row['id']; ?>, '<?php echo addslashes(htmlspecialchars($service_name)); ?>', '<?php echo addslashes(htmlspecialchars($customer_gsm)); ?>', '<?php echo addslashes(number_format($amount, 2)); ?>')">
                                                        <i class="bi bi-x-circle"></i> Cancel This Service
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <span class="sis-tt-locked"><i class="bi bi-lock"></i> Shift ended</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr data-tt-empty-row>
                                <td colspan="8" class="text-center py-5">
                                    <div class="sis-empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <h6>No transactions today</h6>
                                        <p>Use the Add Customer / Sale button above to start recording customer activity.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sis-tt-no-results" data-tt-no-results hidden>
                <i class="bi bi-search"></i>
                <strong>No matching transactions</strong>
                <span>Change your search or filters to see more records.</span>
            </div>
        </div>
        <?php endif; ?>

    
    <div class="modal-overlay" id="editSaleModal" onclick="closeModalOnBackdrop(event)">
        <div class="modal-container large">
            <div class="modal-header">
                <h5><i class="bi bi-pencil-square"></i> Edit Customer / Sale Record</h5>
                <button class="modal-close" onclick="closeEditSaleModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editSaleForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="edit_sale" value="1">
                    <input type="hidden" name="sale_id" id="edit_sale_id">
                    <input type="hidden" name="visitor_id" id="edit_visitor_id">
                    <input type="hidden" name="is_withdrawal" id="edit_is_withdrawal">

                    <div class="alert alert-info py-2">
                        <i class="bi bi-lock-fill"></i> Customer GSM number is locked for data integrity. You can edit the rest of the record.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-phone"></i> Customer GSM Number</label>
                            <input type="text" class="form-control bg-light" id="edit_customer_gsm" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-flag"></i> Record Status</label>
                            <select class="form-select" name="visitor_status" id="edit_visitor_status" required>
                                <option value="Waiting">Waiting</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-bullseye"></i> Purpose</label>
                            <select class="form-select" name="purpose_id" id="edit_purpose_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-gear"></i> Service</label>
                            <select class="form-select" name="service_id" id="edit_service_id" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-hash"></i> Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="edit_quantity" min="1" value="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-cash"></i> Amount (D)</label>
                            <input type="number" class="form-control" name="amount" id="edit_amount" step="0.01" min="0" required>
                            <small class="text-muted" id="amount_note"></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-calendar"></i> Sale Date</label>
                            <input type="date" class="form-control" name="sale_date" id="edit_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-credit-card"></i> Payment Status</label>
                            <select class="form-select" name="payment_status" id="edit_status" required>
                                <option value="Paid">Paid</option>
                                <option value="Pending">Pending</option>
                                <option value="Unpaid">Unpaid</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-card-text"></i> Notes</label>
                            <textarea class="form-control" name="visitor_notes" id="edit_visitor_notes" rows="3" placeholder="Record notes"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditSaleModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEditSale()"><i class="bi bi-save"></i> Save Changes</button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- CANCEL SERVICE MODAL -->
    <!-- Cancelled sales are removed from the transaction table -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="cancelServiceModal" onclick="closeCancelModalOnBackdrop(event)">
        <div class="modal-container">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #b02a37); color: white;">
                <h5><i class="bi bi-x-circle-fill"></i> Cancel Service</h5>
                <button type="button" class="modal-close" onclick="closeCancelModal()" style="color: white;">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="cancelServiceForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="cancel_single_service" value="1">
                    <input type="hidden" name="sale_id" id="cancel_sale_id">

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Warning:</strong> You are about to cancel this service. It will be <strong>removed from your transactions list</strong> immediately.
                    </div>

                    <div class="cancel-reason-preview" id="cancel_preview">
                        <div><strong>Service:</strong> <span id="cancel_service_name">—</span></div>
                        <div><strong>Customer GSM:</strong> <span id="cancel_customer_gsm">—</span></div>
                        <div><strong>Amount:</strong> D <span id="cancel_amount">0.00</span></div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">
                            <i class="bi bi-chat-left-text"></i> Cancellation Reason <span class="text-danger">*</span>
                        </label>
                        <select class="form-select mb-2" id="cancel_reason_preset" onchange="setCancelReason(this.value)">
                            <option value="">-- Select a reason --</option>
                            <option value="Customer changed mind">Customer changed mind</option>
                            <option value="Wrong service selected">Wrong service selected</option>
                            <option value="Duplicate entry">Duplicate entry</option>
                            <option value="Customer left before completion">Customer left before completion</option>
                            <option value="Payment failed">Payment failed</option>
                            <option value="Other (specify below)">Other (specify below)</option>
                        </select>
                        <textarea class="form-control" name="cancel_reason" id="cancel_reason"
                                  rows="3" placeholder="Explain why this service is being cancelled..." required></textarea>
                    </div>

                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Cancelling this service will:
                        <ul class="mb-0 mt-1">
                            <li><strong>Remove it from today's transactions list</strong></li>
                            <li>Exclude it from revenue calculations</li>
                            <li>Keep the record in the database for audit purposes</li>
                            <li>Log the cancellation with your reason for Admin/Supervisor review</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCancelModal()">Keep Service</button>
                <button type="button" class="btn btn-danger" onclick="submitCancelService()">
                    <i class="bi bi-x-circle"></i> Confirm Cancellation
                </button>
            </div>
        </div>
    </div>

    
    <div class="modal-overlay" id="multiServiceModal" onclick="closeMultiServiceModalOnBackdrop(event)">
        <div class="modal-container large"><div class="modal-header sis-u-fbc3a00c"><h5><i class="bi bi-plus-circle-fill"></i> Add Sale to Existing Customer</h5><button class="modal-close" onclick="closeMultiServiceModal()">&times;</button></div>
        <div class="modal-body"><form method="POST" id="multiServiceForm" data-customer-service-form="1" onsubmit="return window.SISHandleTimer ? window.SISHandleTimer.stopFormBeforeSubmit(this) : true;">
                    <?php echo csrfField(); ?><input type="hidden" name="add_multi_sale" value="1"><input type="hidden" name="visitor_id" id="ms_visitor_id">
        <?php renderHandleTimerFields('Same Customer Handle Time', false, 'same_customer'); ?>
        <div class="alert alert-info py-2 mb-3"><i class="bi bi-info-circle"></i> Adding sale to existing customer. No new visitor will be created.</div>
        <div class="mb-3"><label class="form-label">Sale Date</label><input type="date" class="form-control" name="sale_date_multi" id="ms_date" required></div>
        <div id="msRowsContainer"></div>
        <button type="button" class="btn-add-service" onclick="addServiceRow()"><i class="bi bi-plus-circle"></i> Add Another Service</button>
        <div class="ms-summary" id="msSummary"><div class="sis-u-bfe10c3c">📋 Summary</div><div id="msSummaryRows"></div><div class="ms-sum-total"><span>Total Amount</span><span id="msSummaryTotal">D 0.00</span></div></div></form></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeMultiServiceModal()">Cancel</button><button type="button" class="btn btn-primary" onclick="submitMultiService()">Save All Services</button></div></div>
    </div>
        <?php endif; ?>

    
    <?php if (!$shift_selected && !$today_assignment): ?>
    <div class="modal-overlay open" id="shiftModal" onclick="closeShiftModalOnBackdrop(event)">
        <div class="modal-container sis-u-3f4a3cc8"><div class="modal-header sis-u-e14d6806"><h5><i class="bi bi-calendar-check"></i> Select Your Shift</h5><button class="modal-close" onclick="closeShiftModal()">&times;</button></div>
        <div class="modal-body"><p class="text-muted mb-3">Please select your working shift before you start recording transactions.</p>
        <form method="POST" id="shiftForm"><input type="hidden" name="select_shift" value="1">
        <div class="shift-options"><div class="shift-option" onclick="selectShift('morning')" id="shift-morning"><i class="bi bi-sunrise-fill"></i><strong>Morning</strong></div>
        <div class="shift-option" onclick="selectShift('afternoon')" id="shift-afternoon"><i class="bi bi-sun-fill"></i><strong>Afternoon</strong></div>
        <div class="shift-option" onclick="selectShift('night')" id="shift-night"><i class="bi bi-moon-fill"></i><strong>Night</strong></div></div>
        <input type="hidden" name="shift" id="selectedShift" value=""><div class="mt-4"><button type="submit" class="btn btn-primary w-100" id="submitShiftBtn" disabled><i class="bi bi-check-circle"></i> Start Shift</button></div></form></div></div>
    </div>
    <?php endif; ?>

    
    <div class="modal-overlay" id="endShiftConfirmModal">
        <div class="modal-container sis-u-a833a409"><div class="modal-header sis-u-b72e2486"><h5><i class="bi bi-exclamation-triangle-fill"></i> End Shift</h5><button class="modal-close" onclick="closeEndShiftModal()">&times;</button></div>
        <div class="modal-body"><p>You are about to end your shift.</p><p><strong>Working hours:</strong> <span id="confirmDurationSpan"><?php echo $confirm_duration; ?></span></p><?php if($today_assignment): ?><div class="alert alert-warning py-2">If this is before your scheduled end time, a request will be sent to your Supervisor.</div><label class="form-label">Reason if ending early</label><textarea class="form-control" name="early_end_reason" form="endShiftForm" rows="2" placeholder="Write reason for Supervisor approval"></textarea><?php endif; ?><p class="mt-3">Are you sure you want to end your shift?</p></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeEndShiftModal()">Cancel</button><form class="sis-u-5f8f4af2" method="POST" id="endShiftForm"><input type="hidden" name="end_shift_confirm" value="1"><button type="submit" class="btn btn-danger">Yes, End Shift</button></form></div></div>
    </div>


    <?php if ($show_overtime_modal): ?>
    <div class="modal-overlay open" id="overtimeModal">
        <div class="modal-container">
            <div class="modal-header sis-u-e14d6806"><h5><i class="bi bi-clock-history"></i> Overtime Confirmation</h5></div>
            <div class="modal-body">
                <p>Your scheduled shift ended more than one hour ago.</p>
                <p><strong>Overtime now:</strong> <?php echo (int)floor($overtime_minutes_now / 60); ?> hrs <?php echo (int)($overtime_minutes_now % 60); ?> mins</p>
                <p>If you continue, your Supervisor will receive an overtime notification.</p>
                <form method="post" class="d-flex gap-2 flex-wrap"><button name="confirm_overtime" value="1" class="btn btn-primary"><i class="bi bi-check-circle"></i> Yes, continue overtime</button><button name="decline_overtime" value="1" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> No, logout in 15 minutes</button></form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($auto_logout_after_overtime_decline): ?>
    <div class="alert alert-warning position-fixed bottom-0 end-0 m-3 shadow" style="z-index:99999">You declined overtime. The system will logout in <span id="overtimeLogoutCounter">15:00</span>.</div>
    <script>let remain=<?php echo max(0, $overtime_logout_deadline ? strtotime($overtime_logout_deadline) - time() : 900); ?>;setInterval(function(){remain--;let m=Math.floor(Math.max(0,remain)/60),s=Math.max(0,remain)%60;let el=document.getElementById('overtimeLogoutCounter');if(el)el.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');if(remain<=0)location.href='../logout.php?force=1';},1000);</script>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var SERVICES = <?php echo $services_json; ?>;
        var PURPOSES = <?php echo $purposes_json; ?>;
        var serviceRowCount = 0;

        $(document).ready(function() {
            $('.searchable-purpose').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '🔍 Search or select purpose...', allowClear: true });
            $('.searchable-service').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '🔍 Search or select service...', allowClear: true });
            var visitorForm = document.getElementById('visitorForm');
            if (visitorForm && window.SISHandleTimer) window.SISHandleTimer.bindForm(visitorForm);
            if (visitorForm) visitorForm.addEventListener('submit', function(){ if (window.SISHandleTimer) window.SISHandleTimer.updateForForm(visitorForm); });
        });
        function openCustomerFormModal() {
            var modal = document.getElementById('customerFormModal');
            var form = document.getElementById('visitorForm');
            if (!modal || !form) return;
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            if (window.SISHandleTimer) {
                window.SISHandleTimer.bindForm(form);
                window.SISHandleTimer.resetForForm(form); window.SISHandleTimer.startForForm(form);
            }
            setTimeout(function(){ var gsm = document.getElementById('customer_gsm'); if (gsm) gsm.focus(); }, 200);
        }
        function closeCustomerFormModal() {
            var modal = document.getElementById('customerFormModal');
            if (modal) modal.classList.remove('open');
            document.body.style.overflow = '';
        }
        function closeCustomerFormModalOnBackdrop(e) {
            if (e.target === document.getElementById('customerFormModal')) closeCustomerFormModal();
        }
        var selectedShift = null;
        function selectShift(shift) {
            selectedShift = shift;
            document.getElementById('selectedShift').value = shift;
            document.querySelectorAll('.shift-option').forEach(opt => opt.classList.remove('selected'));
            document.getElementById('shift-' + shift).classList.add('selected');
            document.getElementById('submitShiftBtn').disabled = false;
        }
        function closeShiftModal() {
            if (!selectedShift) { alert('Please select a shift before continuing.'); return; }
            document.getElementById('shiftModal').classList.remove('open');
        }
        function closeShiftModalOnBackdrop(e) { if (e.target === document.getElementById('shiftModal') && selectedShift) closeShiftModal(); }
        function showEndShiftConfirm() {
            var durationSpan = document.getElementById('shiftDuration');
            var currentDuration = durationSpan ? durationSpan.textContent : '<?php echo $confirm_duration; ?>';
            document.getElementById('confirmDurationSpan').textContent = currentDuration;
            document.getElementById('endShiftConfirmModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeEndShiftModal() {
            document.getElementById('endShiftConfirmModal').classList.remove('open');
            document.body.style.overflow = '';
        }
        function sisEditEsc(value) {
            return String(value == null ? '' : value).replace(/[&<>'"]/g, function (ch) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch];
            });
        }

        function ensureEditDropdownOptions() {
            var purposeSelect = document.getElementById('edit_purpose_id');
            var serviceSelect = document.getElementById('edit_service_id');
            if (purposeSelect && purposeSelect.options.length === 0) {
                purposeSelect.innerHTML = '<option value="">Select purpose...</option>' + (PURPOSES || []).map(function (p) {
                    return '<option value="' + sisEditEsc(p.id) + '">' + sisEditEsc((p.category ? p.category + ' - ' : '') + p.name) + '</option>';
                }).join('');
            }
            if (serviceSelect && serviceSelect.options.length === 0) {
                serviceSelect.innerHTML = '<option value="">Select service...</option>' + (SERVICES || []).map(function (s) {
                    var tag = parseInt(s.is_withdrawal, 10) ? ' - WITHDRAWAL' : '';
                    return '<option value="' + sisEditEsc(s.id) + '">' + sisEditEsc(s.name + tag) + '</option>';
                }).join('');
            }
        }

        function openEditSaleModal(saleId, amount, paymentStatus, paymentDate, isWithdrawal, recordData) {
            recordData = recordData || {};
            ensureEditDropdownOptions();
            document.getElementById('edit_sale_id').value = saleId;
            document.getElementById('edit_visitor_id').value = recordData.visitorId || '';
            document.getElementById('edit_customer_gsm').value = recordData.gsm || 'Not provided';
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_status').value = paymentStatus || 'Paid';
            document.getElementById('edit_is_withdrawal').value = isWithdrawal ? '1' : '0';
            document.getElementById('edit_quantity').value = recordData.quantity || 1;
            document.getElementById('edit_visitor_status').value = recordData.visitorStatus || 'Completed';
            document.getElementById('edit_visitor_notes').value = recordData.notes || '';
            document.getElementById('edit_purpose_id').value = recordData.purposeId || '';
            document.getElementById('edit_service_id').value = recordData.serviceId || '';

            var amountNote = document.getElementById('amount_note');
            if (amountNote) {
                amountNote.innerHTML = isWithdrawal ? '⚠️ Withdrawal amounts are stored as negative automatically.' : '💰 Sale amounts are stored as positive automatically.';
            }

            if (paymentDate && paymentDate !== '0000-00-00') {
                var date = new Date(paymentDate);
                if (!isNaN(date.getTime())) document.getElementById('edit_date').value = date.toISOString().split('T')[0];
                else document.getElementById('edit_date').value = new Date().toISOString().split('T')[0];
            } else {
                document.getElementById('edit_date').value = new Date().toISOString().split('T')[0];
            }

            document.getElementById('editSaleModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeEditSaleModal() {
            var modal = document.getElementById('editSaleModal');
            if (modal) modal.classList.remove('open');
            document.body.style.overflow = '';
        }
        function closeModalOnBackdrop(e) { if (e.target === document.getElementById('editSaleModal')) closeEditSaleModal(); if (e.target === document.getElementById('multiServiceModal')) closeMultiServiceModal(); }
        function submitEditSale() { document.getElementById('editSaleForm').submit(); }

        // ============================================
        // CANCEL SERVICE MODAL
        // ============================================
        function openCancelModal(saleId, serviceName, customerGsm, amount) {
            document.getElementById('cancel_sale_id').value = saleId;
            document.getElementById('cancel_service_name').textContent = serviceName || '—';
            document.getElementById('cancel_customer_gsm').textContent = customerGsm || 'No GSM';
            document.getElementById('cancel_amount').textContent = amount || '0.00';
            document.getElementById('cancel_reason').value = '';
            document.getElementById('cancel_reason_preset').value = '';
            document.getElementById('cancelServiceModal').classList.add('open');
            document.body.style.overflow = 'hidden';
            setTimeout(function(){ document.getElementById('cancel_reason').focus(); }, 200);
        }
        function closeCancelModal() {
            document.getElementById('cancelServiceModal').classList.remove('open');
            document.body.style.overflow = '';
        }
        function closeCancelModalOnBackdrop(e) {
            if (e.target === document.getElementById('cancelServiceModal')) closeCancelModal();
        }
        function setCancelReason(presetValue) {
            if (presetValue && presetValue !== 'Other (specify below)') {
                document.getElementById('cancel_reason').value = presetValue;
            } else if (presetValue === 'Other (specify below)') {
                document.getElementById('cancel_reason').value = '';
                document.getElementById('cancel_reason').focus();
            }
        }
        function submitCancelService() {
            var reason = document.getElementById('cancel_reason').value.trim();
            var saleId = document.getElementById('cancel_sale_id').value;
            if (!reason) {
                alert('Please provide a cancellation reason.');
                document.getElementById('cancel_reason').focus();
                return;
            }
            if (!saleId) {
                alert('Sale ID missing. Please close and reopen the modal.');
                return;
            }
            document.getElementById('cancelServiceForm').submit();
        }

        function openMultiServiceModal(visitorId, customerName) {
            document.getElementById('ms_visitor_id').value = visitorId;
            document.getElementById('ms_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('msRowsContainer').innerHTML = '';
            serviceRowCount = 0;
            addServiceRow();
            updateSummary();
            document.getElementById('multiServiceModal').classList.add('open');
            if (window.SISHandleTimer) window.SISHandleTimer.resetForForm(document.getElementById('multiServiceForm'));
            document.body.style.overflow = 'hidden';
        }
        function closeMultiServiceModal() {
            var modal = document.getElementById('multiServiceModal');
            if (modal) modal.classList.remove('open');
            document.body.style.overflow = '';
        }
        function closeMultiServiceModalOnBackdrop(e) { if (e.target === document.getElementById('multiServiceModal')) closeMultiServiceModal(); }
        function addServiceRow() {
            serviceRowCount++; var n = serviceRowCount; var div = document.createElement('div'); div.className = 'ms-row'; div.id = 'msRow_' + n;
            var opts = '<option value="">-- Select a service --</option>';
            SERVICES.forEach(s => { opts += `<option value="${s.id}" data-is-withdrawal="${s.is_withdrawal}">${escapeHtml(s.name)}${s.is_withdrawal ? ' 🚨 WITHDRAWAL' : ''}</option>`; });
            div.innerHTML = `<span class="ms-row-num">Service ${n}</span>${n > 1 ? '<button type="button" class="ms-remove" onclick="removeServiceRow(' + n + ')">✕</button>' : ''}
                <div class="mb-2"><label class="form-label small">Select Service</label><select class="form-select form-select-sm service-select" name="ms_service_id[]" id="ms_service_${n}" required onchange="updateSummary()">${opts}</select></div>
                <div class="row g-2"><div class="col-5"><label class="form-label small">Amount (D)</label><input type="number" class="form-control form-control-sm" name="ms_amount[]" id="ms_amount_${n}" step="0.01" placeholder="0.00" oninput="updateSummary()"></div>
                <div class="col-4"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="ms_status[]"><option value="Paid">Paid</option><option value="Pending">Pending</option><option value="Unpaid">Unpaid</option></select></div>
                <div class="col-3"><label class="form-label small">Notes</label><input type="text" class="form-control form-control-sm" name="ms_notes[]" placeholder="Optional"></div></div>`;
            document.getElementById('msRowsContainer').appendChild(div);
        }
        function removeServiceRow(rowId) { var el = document.getElementById('msRow_' + rowId); if (el) el.remove(); updateSummary(); }
        function updateSummary() {
            var total = 0, rows = [];
            for (var i = 1; i <= serviceRowCount; i++) {
                var amt = parseFloat(document.getElementById(`ms_amount_${i}`)?.value) || 0;
                var sel = document.getElementById(`ms_service_${i}`);
                var name = sel ? sel.options[sel.selectedIndex]?.text : '';
                if (name && name !== '-- Select a service --' && amt > 0) { total += amt; rows.push(`<div class="d-flex justify-content-between"><span>${escapeHtml(name)}</span><span>D ${amt.toFixed(2)}</span></div>`); }
            }
            var summary = document.getElementById('msSummary'), sumRows = document.getElementById('msSummaryRows'), totalSpan = document.getElementById('msSummaryTotal');
            if (rows.length > 0) { summary.classList.add('show'); sumRows.innerHTML = rows.join(''); totalSpan.textContent = `D ${total.toFixed(2)}`; }
            else { summary.classList.remove('show'); }
        }
        function submitMultiService() {
            var selects = document.querySelectorAll('[name="ms_service_id[]"]');
            var has = false; selects.forEach(s => { if (s.value) has = true; });
            if (!has) { alert('Please select at least one service'); return; }
            if (window.SISHandleTimer) window.SISHandleTimer.updateForForm(document.getElementById('multiServiceForm'));
            return window.SISHandleTimer ? window.SISHandleTimer.submitForm(document.getElementById('multiServiceForm')) : (document.getElementById('multiServiceForm').submit(), false);
        }
        function escapeHtml(str) { return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }


        <?php if (!empty($open_customer_form_from_server)): ?>
        document.addEventListener('DOMContentLoaded', function(){
            setTimeout(function(){ if (typeof openCustomerFormModal === 'function') openCustomerFormModal(); }, 100);
        });
        <?php endif; ?>


        <?php if (!empty($open_same_customer_form_from_server) && !empty($open_same_customer_visitor_id)): ?>
        document.addEventListener('DOMContentLoaded', function(){
            setTimeout(function(){ if (typeof openMultiServiceModal === 'function') openMultiServiceModal(<?php echo (int)$open_same_customer_visitor_id; ?>, ''); }, 150);
        });
        <?php endif; ?>
        <?php if ($shift_selected && !$shift_ended && $shift_started_at): ?>
        var shiftStartTime = '<?php echo $shift_started_at; ?>';
        function updateLiveDuration() {
            var start = new Date(shiftStartTime.replace(' ', 'T'));
            var now = new Date();
            var diffMs = now - start;
            if (diffMs < 0) diffMs = 0;
            var hours = Math.floor(diffMs / 3600000);
            var mins = Math.floor((diffMs % 3600000) / 60000);
            var durSpan = document.getElementById('shiftDuration');
            if (durSpan) durSpan.textContent = hours + ' hrs ' + mins + ' mins';
        }
        updateLiveDuration();
        setInterval(updateLiveDuration, 60000);
        <?php endif; ?>
        const quantityField = document.getElementById('quantityField');
        const customAmountField = document.getElementById('customAmountField');
        const totalPreview = document.getElementById('totalPreview');
        const totalAmountSpan = document.getElementById('totalAmount');
        const priceRangeNote = document.getElementById('priceRangeNote');
        const quantityInput = document.getElementById('quantity');
        const customAmountInput = document.getElementById('customAmount');
        function calculateTotal() {
            const select = document.getElementById('serviceSelect');
            const option = select.options[select.selectedIndex];
            if (!option || !option.value) { totalPreview.style.display = 'none'; return; }
            const isPaid = option.dataset.paid === '1';
            const isQuantity = option.dataset.quantity === '1';
            const isFixed = option.dataset.isFixed === '1';
            const isWithdrawal = option.dataset.withdrawal === '1';
            const price = parseFloat(option.dataset.price) || 0;
            const minAmount = parseFloat(option.dataset.min) || 0;
            const maxAmount = parseFloat(option.dataset.max) || 0;
            const quantity = parseInt(quantityInput?.value) || 1;
            let total = 0, note = '';
            if (isPaid) {
                if (!isFixed) {
                    let customAmount = parseFloat(customAmountInput?.value) || 0;
                    if (!customAmountInput?.value) {
                        totalAmountSpan.textContent = `Enter amount (${minAmount} - ${maxAmount} D)`;
                        priceRangeNote.textContent = `Range: ${minAmount} - ${maxAmount} D`;
                        totalPreview.style.display = 'block';
                        return;
                    }
                    total = isQuantity ? customAmount * quantity : customAmount;
                    note = isQuantity ? `${customAmount} × ${quantity} = ${total} D` : `Custom amount: ${total} D`;
                } else {
                    total = isQuantity ? price * quantity : price;
                    note = isQuantity ? `${price} × ${quantity} = ${total} D` : `Fixed price: ${total} D`;
                    if (customAmountInput) customAmountInput.value = total;
                }
                if (isWithdrawal) { note = '🚨 WITHDRAWAL - ' + note; totalPreview.style.backgroundColor = '#fee2e2'; }
                else { totalPreview.style.backgroundColor = '#f0fdf4'; }
            } else {
                total = 0; note = 'Free service - no payment required';
                totalPreview.style.backgroundColor = '#f0fdf4';
            }
            if (total > 0 || (total === 0 && !isPaid)) {
                if (isWithdrawal) totalAmountSpan.textContent = `- D ${total.toFixed(2)} (WITHDRAWAL)`;
                else if (total > 0) totalAmountSpan.textContent = `+ D ${total.toFixed(2)}`;
                else totalAmountSpan.textContent = `D 0.00 (Free)`;
                priceRangeNote.textContent = note;
                totalPreview.style.display = 'block';
            } else { totalPreview.style.display = 'none'; }
        }
        $('#serviceSelect').on('change', function() {
            const option = this.options[this.selectedIndex];
            if (!option || !option.value) { quantityField.style.display = 'none'; customAmountField.style.display = 'none'; totalPreview.style.display = 'none'; return; }
            const isPaid = option.dataset.paid === '1';
            const isQuantity = option.dataset.quantity === '1';
            const isFixed = option.dataset.isFixed === '1';
            quantityField.style.display = isQuantity ? 'block' : 'none';
            if (!isQuantity && quantityInput) quantityInput.value = 1;
            if (isPaid && !isFixed) { customAmountField.style.display = 'block'; if(customAmountInput){ customAmountInput.placeholder = 'Enter amount'; customAmountInput.value = ''; customAmountInput.required = true; } }
            else { customAmountField.style.display = 'none'; if(customAmountInput){ customAmountInput.value = ''; customAmountInput.required = false; } }
            calculateTotal();
        });
        if (quantityInput) quantityInput.addEventListener('input', calculateTotal);
        if (customAmountInput) customAmountInput.addEventListener('input', calculateTotal);
        const gsmInput = document.getElementById('customer_gsm');
        if (gsmInput) gsmInput.addEventListener('input', function(e) { this.value = this.value.replace(/[^0-9]/g, ''); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeEditSaleModal(); closeMultiServiceModal(); closeEndShiftModal(); closeCancelModal(); } });
        setTimeout(() => { document.querySelectorAll('.alert').forEach(alert => { bootstrap.Alert.getOrCreateInstance(alert).close(); }); }, 5000);
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>