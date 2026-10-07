<?php
require_once '../config.php';
require_once '../support_helpers.php';
requireRole('supervisor');
supportEnsureTables($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);
$user_name = $_SESSION['full_name'] ?? 'Supervisor';
$message = '';
$message_type = 'success';

// Active tab
$view_tab = isset($_GET['view']) && $_GET['view'] === 'outlet' ? 'outlet' : 'mine';
$role_filter = isset($_GET['role']) ? trim((string)$_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Outlet name
$outlet_name = 'Unknown Outlet';
if ($outlet_id) {
    $stmt = mysqli_prepare($conn, "SELECT name FROM outlets WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $outlet_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) $outlet_name = $row['name'];
        mysqli_stmt_close($stmt);
    }
}

// Save ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_support_ticket'])) {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        [$ok, $result, $ticket_id] = supportSaveTicket($conn, [
            'submitted_by' => $user_id,
            'submitted_role' => 'supervisor',
            'outlet_id' => $outlet_id,
            'customer_name' => $_POST['customer_name'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'category' => $_POST['category'] ?? 'Complaint',
            'priority' => $_POST['priority'] ?? 'Normal',
            'main_reason' => $_POST['main_reason'] ?? '',
            'sub_reason_id' => $_POST['sub_reason_id'] ?? null,
            'subject' => $_POST['subject'] ?? '',
            'description' => $_POST['description'] ?? '',
        ], $_POST);
        if ($ok) {
            $message = 'Ticket saved: ' . e($result);
        } else {
            $message = $result;
            $message_type = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Support ticket submitted.', $message_type);
    redirectTo('support.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

// Fetch tickets
$tickets = [];
if ($view_tab === 'outlet') {
    $where = "st.outlet_id = ?";
    $params = [$outlet_id];
    $types = 'i';

    if ($role_filter === 'agent' || $role_filter === 'supervisor') {
        $where .= " AND st.submitted_role = ?";
        $params[] = $role_filter;
        $types .= 's';
    }
    if ($status_filter !== '') {
        $where .= " AND st.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    if ($search !== '') {
        $where .= " AND (st.ticket_no LIKE ? OR st.customer_name LIKE ? OR st.customer_phone LIKE ? OR st.subject LIKE ? OR st.main_reason LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types .= 'sssss';
    }
    $sql = "SELECT st.*, o.name AS outlet_name, u.full_name AS submitted_by_name, u.role AS submitted_by_role
            FROM support_tickets st
            LEFT JOIN outlets o ON st.outlet_id = o.id
            LEFT JOIN users u ON st.submitted_by = u.id
            WHERE $where
            ORDER BY st.created_at DESC LIMIT 100";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) $tickets[] = $row;
        mysqli_stmt_close($stmt);
    }
} else {
    $stmt = mysqli_prepare($conn, "SELECT st.*, o.name AS outlet_name FROM support_tickets st LEFT JOIN outlets o ON st.outlet_id=o.id WHERE st.submitted_by=? AND st.submitted_role='supervisor' ORDER BY st.created_at DESC LIMIT 20");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) $tickets[] = $row;
        mysqli_stmt_close($stmt);
    }
}
$valuesMap = supportGetTicketValuesMap($conn, array_column($tickets, 'id'));

// Counts
$count_mine = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM support_tickets WHERE submitted_by = ? AND submitted_role = 'supervisor'");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) $count_mine = (int)$row['c'];
    mysqli_stmt_close($stmt);
}
$count_outlet = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM support_tickets WHERE outlet_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $outlet_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) $count_outlet = (int)$row['c'];
    mysqli_stmt_close($stmt);
}

$available_statuses = ['Open', 'In Progress', 'Resolved', 'Closed', 'Pending'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complaint / Support - Supervisor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">

<style>
/* Hero */
.support-hero {
    background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
    color: white;
    padding: 22px 26px;
    border-radius: 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    box-shadow: 0 8px 24px rgba(220, 53, 69, 0.25);
}
.support-hero h2 { margin: 0; font-weight: 800; font-size: 22px; }
.support-hero p { margin: 4px 0 0; opacity: 0.9; font-size: 13px; }
.support-hero-icon { font-size: 2.5rem; opacity: 0.5; }

/* Compact form panel */
.form-panel {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
    overflow: hidden;
    position: sticky;
    top: 20px;
}
.form-panel-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
}
.form-panel-header h5 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-panel-body { padding: 18px 20px; }

/* Compact form fields */
.field { margin-bottom: 14px; }
.field label {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 5px;
    display: block;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.field input,
.field select,
.field textarea {
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    font-size: 14px;
    padding: 9px 12px;
    width: 100%;
    transition: all 0.15s;
}
.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
    outline: none;
}
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 576px) { .field-row { grid-template-columns: 1fr; } }

/* Category chips */
.chip-group { display: flex; flex-wrap: wrap; gap: 8px; }
.chip {
    padding: 8px 14px;
    border-radius: 999px;
    border: 1.5px solid #e2e8f0;
    background: white;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.chip:hover { border-color: #dc3545; color: #dc3545; }
.chip.active {
    background: #dc3545;
    border-color: #dc3545;
    color: white;
    box-shadow: 0 4px 10px rgba(220, 53, 69, 0.25);
}
.chip input { display: none; }

/* Priority buttons */
.priority-group { display: flex; gap: 8px; flex-wrap: wrap; }
.priority-btn {
    flex: 1;
    min-width: 80px;
    padding: 9px 12px;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    background: white;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    color: #64748b;
}
.priority-btn:hover { transform: translateY(-1px); }
.priority-btn input { display: none; }
.priority-btn[data-priority="Low"].active { background: #dcfce7; border-color: #16a34a; color: #166534; }
.priority-btn[data-priority="Normal"].active { background: #dbeafe; border-color: #2563eb; color: #1e40af; }
.priority-btn[data-priority="High"].active { background: #fef3c7; border-color: #d97706; color: #92400e; }
.priority-btn[data-priority="Urgent"].active { background: #fee2e2; border-color: #dc2626; color: #991b1b; }

/* Advanced collapsible */
.advanced-toggle {
    font-size: 12px;
    font-weight: 700;
    color: #dc3545;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    user-select: none;
}
.advanced-toggle:hover { text-decoration: underline; }
.advanced-section { display: none; margin-top: 12px; }
.advanced-section.open { display: block; }

/* Submit */
.submit-btn {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 6px;
}
.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
}

/* Tickets panel */
.tickets-panel {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}
.tickets-tabs {
    display: flex;
    gap: 4px;
    padding: 8px 8px 0;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.tickets-tab {
    padding: 10px 16px;
    border: none;
    background: transparent;
    color: #64748b;
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
    border-radius: 10px 10px 0 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}
.tickets-tab:hover { background: rgba(220, 53, 69, 0.05); color: #dc3545; }
.tickets-tab.active {
    background: white;
    color: #dc3545;
    box-shadow: 0 -2px 0 #dc3545 inset;
}
.tickets-tab .badge {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
}
.tickets-tab:not(.active) .badge { background: #e2e8f0; color: #64748b; }

/* Filter bar */
.filter-bar {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    background: white;
}
.filter-bar input,
.filter-bar select {
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    padding: 7px 12px;
    font-size: 13px;
    min-height: 36px;
}
.filter-bar input:focus,
.filter-bar select:focus {
    border-color: #dc3545;
    outline: none;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
}

/* Ticket row */
.ticket-row {
    padding: 14px 18px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.15s;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 14px;
    align-items: center;
}
.ticket-row:hover { background: #fef2f2; }
.ticket-row:last-child { border-bottom: none; }
.ticket-row .ticket-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    font-size: 18px;
    flex-shrink: 0;
}
.ticket-row .ticket-main { min-width: 0; }
.ticket-row .ticket-no {
    font-weight: 800;
    color: #0f172a;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ticket-row .ticket-subject {
    color: #475569;
    font-size: 13px;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ticket-row .ticket-meta {
    display: flex;
    gap: 12px;
    font-size: 11px;
    color: #94a3b8;
    margin-top: 4px;
    flex-wrap: wrap;
}
.ticket-row .ticket-meta span { display: inline-flex; align-items: center; gap: 4px; }

.status-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.status-pill.open { background: #fef3c7; color: #92400e; }
.status-pill.in-progress { background: #dbeafe; color: #1e40af; }
.status-pill.resolved { background: #dcfce7; color: #166534; }
.status-pill.closed { background: #e5e7eb; color: #374151; }
.status-pill.pending { background: #ede9fe; color: #5b21b6; }

.role-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.role-chip.role-agent { background: #fee2e2; color: #991b1b; }
.role-chip.role-supervisor { background: #fce7f3; color: #9d174d; }

/* Empty */
.empty-tickets {
    padding: 60px 20px;
    text-align: center;
    color: #94a3b8;
}
.empty-tickets i { font-size: 3rem; display: block; margin-bottom: 12px; }

/* Detail modal */
.modal-detail .modal-header {
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
}
.modal-detail .detail-field {
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}
.modal-detail .detail-field:last-child { border-bottom: none; }
.modal-detail .detail-label {
    font-size: 11px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.modal-detail .detail-value {
    font-size: 14px;
    color: #0f172a;
    margin-top: 3px;
}

@media (max-width: 992px) {
    .form-panel { position: static; }
}
</style>
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('supervisor'); ?>

<div class="container-fluid mb-4">

    <!-- Hero -->
    <div class="support-hero">
        <div>
            <h2><i class="bi bi-headset"></i> Complaint / Support</h2>
            <p>Record and track customer complaints and support requests in <strong><?php echo e($outlet_name); ?></strong></p>
        </div>
        <div class="support-hero-icon"><i class="bi bi-ui-checks"></i></div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">

        <!-- LEFT: Compact form -->
        <div class="col-xl-5 col-lg-5">
            <div class="form-panel">
                <div class="form-panel-header">
                    <h5><i class="bi bi-plus-circle-fill text-danger"></i> New Ticket</h5>
                </div>
                <div class="form-panel-body">
                    <form method="post" id="supportForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="save_support_ticket" value="1">

                        <!-- Category chips -->
                        <div class="field">
                            <label>Category</label>
                            <div class="chip-group">
                                <?php $cats = ['Complaint', 'Support', 'Follow-up', 'Inquiry'];
                                foreach ($cats as $c):
                                    $icons = ['Complaint' => 'bi-exclamation-triangle', 'Support' => 'bi-tools', 'Follow-up' => 'bi-arrow-repeat', 'Inquiry' => 'bi-question-circle'];
                                ?>
                                    <label class="chip <?php echo $c === 'Complaint' ? 'active' : ''; ?>" data-chip-category>
                                        <input type="radio" name="category" value="<?php echo e($c); ?>" <?php echo $c === 'Complaint' ? 'checked' : ''; ?>>
                                        <i class="bi <?php echo $icons[$c]; ?>"></i> <?php echo e($c); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Priority buttons -->
                        <div class="field">
                            <label>Priority</label>
                            <div class="priority-group">
                                <?php $prios = ['Low', 'Normal', 'High', 'Urgent'];
                                foreach ($prios as $p): ?>
                                    <label class="priority-btn <?php echo $p === 'Normal' ? 'active' : ''; ?>" data-priority="<?php echo e($p); ?>" data-priority-btn>
                                        <input type="radio" name="priority" value="<?php echo e($p); ?>" <?php echo $p === 'Normal' ? 'checked' : ''; ?>>
                                        <?php echo e($p); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="field">
                            <label for="subject">Subject <span class="text-danger">*</span></label>
                            <input type="text" id="subject" name="subject" required placeholder="Short title of the issue" autofocus>
                        </div>

                        <!-- Reason dropdowns (from admin) -->
                        <?php supportRenderReasonDropdowns($conn); ?>

                        <!-- Description -->
                        <div class="field">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" placeholder="What did the customer say?"></textarea>
                        </div>

                        <!-- Advanced toggle -->
                        <div style="text-align: center; margin: 10px 0;">
                            <span class="advanced-toggle" onclick="toggleAdvanced()">
                                <i class="bi bi-chevron-down" id="advancedChevron"></i> Show customer & extra fields
                            </span>
                        </div>

                        <div class="advanced-section" id="advancedSection">
                            <div class="field-row">
                                <div class="field">
                                    <label for="customer_name">Customer Name</label>
                                    <input type="text" id="customer_name" name="customer_name" placeholder="Optional">
                                </div>
                                <div class="field">
                                    <label for="customer_phone">Customer Phone</label>
                                    <input type="tel" id="customer_phone" name="customer_phone" placeholder="e.g. 6600309">
                                </div>
                            </div>

                            <?php supportRenderDynamicFields($conn); ?>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="submit-btn">
                            <i class="bi bi-send-fill"></i> Save Ticket
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIGHT: Tickets list -->
        <div class="col-xl-7 col-lg-7">
            <div class="tickets-panel">
                <!-- Tabs -->
                <div class="tickets-tabs">
                    <a href="support.php?view=mine" class="tickets-tab <?php echo $view_tab === 'mine' ? 'active' : ''; ?>">
                        <i class="bi bi-person-badge"></i> My Tickets
                        <span class="badge"><?php echo (int)$count_mine; ?></span>
                    </a>
                    <a href="support.php?view=outlet" class="tickets-tab <?php echo $view_tab === 'outlet' ? 'active' : ''; ?>">
                        <i class="bi bi-building"></i> All Outlet Tickets
                        <span class="badge"><?php echo (int)$count_outlet; ?></span>
                    </a>
                </div>

                <!-- Filters (only outlet view) -->
                <?php if ($view_tab === 'outlet'): ?>
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="view" value="outlet">
                    <div style="flex: 0 0 130px;">
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="agent" <?php echo $role_filter === 'agent' ? 'selected' : ''; ?>>Agents</option>
                            <option value="supervisor" <?php echo $role_filter === 'supervisor' ? 'selected' : ''; ?>>Supervisors</option>
                        </select>
                    </div>
                    <div style="flex: 0 0 130px;">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach ($available_statuses as $st): ?>
                                <option value="<?php echo e($st); ?>" <?php echo $status_filter === $st ? 'selected' : ''; ?>><?php echo e($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1 1 160px;">
                        <input type="text" name="q" placeholder="Search ticket, customer, subject..." value="<?php echo e($search); ?>" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="support.php?view=outlet" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </form>
                <?php endif; ?>

                <!-- Tickets list -->
                <div>
                    <?php if(empty($tickets)): ?>
                        <div class="empty-tickets">
                            <i class="bi bi-inbox"></i>
                            <h5>No tickets found</h5>
                            <p class="small mb-0">
                                <?php echo $view_tab === 'outlet' ? 'No tickets in this outlet for the selected filters.' : 'You have not submitted any tickets yet.'; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach($tickets as $t):
                            $status_class = strtolower(str_replace(' ', '-', $t['status'] ?? 'open'));
                            $submitted_role = $t['submitted_role'] ?? ($t['submitted_by_role'] ?? 'supervisor');
                            $cat_icon = 'bi-exclamation-triangle';
                            $cat_color = '#dc3545';
                            if ($t['category'] === 'Support') { $cat_icon = 'bi-tools'; $cat_color = '#0d6efd'; }
                            elseif ($t['category'] === 'Follow-up') { $cat_icon = 'bi-arrow-repeat'; $cat_color = '#6f42c1'; }
                            elseif ($t['category'] === 'Inquiry') { $cat_icon = 'bi-question-circle'; $cat_color = '#198754'; }
                            $values = $valuesMap[(int)$t['id']] ?? [];
                            $payload = htmlspecialchars(json_encode([
                                'ticket_no' => $t['ticket_no'],
                                'date' => date('d/m/Y H:i', strtotime($t['created_at'])),
                                'category' => $t['category'],
                                'priority' => $t['priority'],
                                'status' => $t['status'],
                                'customer_name' => $t['customer_name'] ?: '',
                                'customer_phone' => $t['customer_phone'] ?: '',
                                'main_reason' => $t['main_reason'] ?: $t['category'],
                                'sub_reason' => supportGetReasonLabel($conn, $t['sub_reason_id']) ?: '',
                                'subject' => $t['subject'],
                                'description' => $t['description'] ?: '',
                                'submitted_by' => $t['submitted_by_name'] ?? 'You',
                                'submitted_role' => $submitted_role,
                                'extras' => array_map(function($v) { return ['label' => $v['label'], 'value' => $v['value']]; }, $values),
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="ticket-row" onclick='openTicketDetail(<?php echo $payload; ?>)'>
                                <div class="ticket-icon" style="background: <?php echo $cat_color; ?>15; color: <?php echo $cat_color; ?>;">
                                    <i class="bi <?php echo $cat_icon; ?>"></i>
                                </div>
                                <div class="ticket-main">
                                    <div class="ticket-no">
                                        <?php echo e($t['ticket_no']); ?>
                                        <?php if ($view_tab === 'outlet'): ?>
                                            <span class="role-chip role-<?php echo e($submitted_role); ?>">
                                                <i class="bi <?php echo $submitted_role === 'supervisor' ? 'bi-shield-check' : 'bi-person-badge'; ?>"></i>
                                                <?php echo e($t['submitted_by_name'] ?: ucfirst($submitted_role)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ticket-subject"><?php echo e($t['subject']); ?></div>
                                    <div class="ticket-meta">
                                        <span><i class="bi bi-clock"></i> <?php echo e(date('d/m/Y H:i', strtotime($t['created_at']))); ?></span>
                                        <?php if (!empty($t['customer_name']) || !empty($t['customer_phone'])): ?>
                                            <span><i class="bi bi-person"></i> <?php echo e($t['customer_name'] ?: $t['customer_phone']); ?></span>
                                        <?php endif; ?>
                                        <span><i class="bi bi-tag"></i> <?php echo e($t['main_reason'] ?: $t['category']); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-pill <?php echo e($status_class); ?>"><?php echo e($t['status']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ticket detail modal -->
<div class="modal fade modal-detail" id="ticketDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-ticket-detailed"></i> Ticket Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="ticketDetailBody">
                <!-- Filled by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="copyTicketNo()">
                    <i class="bi bi-clipboard"></i> Copy Ticket No
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<script>
// Category chip selection
document.querySelectorAll('[data-chip-category]').forEach(function (chip) {
    chip.addEventListener('click', function () {
        document.querySelectorAll('[data-chip-category]').forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        chip.querySelector('input').checked = true;
    });
});

// Priority button selection
document.querySelectorAll('[data-priority-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('[data-priority-btn]').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        btn.querySelector('input').checked = true;
    });
});

// Advanced toggle
function toggleAdvanced() {
    var section = document.getElementById('advancedSection');
    var chevron = document.getElementById('advancedChevron');
    section.classList.toggle('open');
    chevron.className = section.classList.contains('open') ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
}

// Detail modal
var currentTicketNo = '';
function openTicketDetail(data) {
    currentTicketNo = data.ticket_no;
    var html = '';

    function field(label, value, icon) {
        if (!value) return '';
        return '<div class="detail-field">' +
            '<div class="detail-label">' + (icon ? '<i class="bi ' + icon + '"></i> ' : '') + label + '</div>' +
            '<div class="detail-value">' + escapeHtml(value) + '</div>' +
        '</div>';
    }

    html += field('Ticket Number', data.ticket_no, 'bi-hash');
    html += field('Subject', data.subject, 'bi-bookmark');
    html += field('Category', data.category, 'bi-tag');
    html += field('Priority', data.priority, 'bi-flag');
    html += field('Status', data.status, 'bi-info-circle');
    html += field('Main Reason', data.main_reason, 'bi-chat-left-text');
    if (data.sub_reason) html += field('Sub Reason', data.sub_reason, 'bi-chat-left');
    html += field('Customer Name', data.customer_name, 'bi-person');
    html += field('Customer Phone', data.customer_phone, 'bi-phone');
    html += field('Description', data.description, 'bi-file-text');
    html += field('Submitted By', data.submitted_by + ' (' + data.submitted_role + ')', 'bi-person-badge');
    html += field('Date', data.date, 'bi-clock');

    if (data.extras && data.extras.length > 0) {
        html += '<div class="detail-field"><div class="detail-label"><i class="bi bi-ui-checks"></i> Additional Fields</div>';
        data.extras.forEach(function (ex) {
            if (ex.value) html += '<div class="detail-value"><strong>' + escapeHtml(ex.label) + ':</strong> ' + escapeHtml(ex.value) + '</div>';
        });
        html += '</div>';
    }

    document.getElementById('ticketDetailBody').innerHTML = html;
    var modal = new bootstrap.Modal(document.getElementById('ticketDetailModal'));
    modal.show();
}

function copyTicketNo() {
    navigator.clipboard.writeText(currentTicketNo).then(function () {
        alert('Ticket number copied: ' + currentTicketNo);
    });
}

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

// Enter key submits (except in textarea)
document.getElementById('supportForm').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') {
        e.preventDefault();
        var form = this;
        // Find the submit button
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.click();
    }
});
</script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>