<?php
require_once '../config.php';
requireLogin();

if (!hasRole('supervisor') && !hasRole('admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// ============================================
// Handle No-Show Reason Update (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_reason') {
    $assignment_id = (int)$_POST['assignment_id'];
    $reason = trim($_POST['reason']);
    if ($assignment_id > 0 && in_array($reason, ['Sick', 'Emergency', 'Disaster', 'Personal', 'Other', ''])) {
        $sql = "UPDATE shift_assignments SET no_show_reason = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $reason, $assignment_id);
        mysqli_stmt_execute($stmt);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    }
}

$is_admin = hasRole('admin');
$outlet_id = $_SESSION['outlet_id'] ?? 0;

// ----- Filters -----
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to   = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$agent_filter = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$outlet_filter = isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : 0;

// ----- For admin: fetch all active outlets -----
if ($is_admin) {
    $outlets_query = "SELECT id, name FROM outlets WHERE is_active = 1 ORDER BY name";
    $outlets = mysqli_query($conn, $outlets_query);
} else {
    $outlets = null;
}

// ----- Agents list (with optional outlet filter) -----
$agents_query = "SELECT id, full_name FROM users WHERE role = 'agent' AND is_active = 1";
if (!$is_admin) {
    $agents_query .= " AND outlet_id = $outlet_id";
} elseif ($outlet_filter > 0) {
    $agents_query .= " AND outlet_id = $outlet_filter";
}
$agents_query .= " ORDER BY full_name";
$agents = mysqli_query($conn, $agents_query);

// ----- Build WHERE clause for the report -----
$where = "sa.shift_date BETWEEN '$date_from' AND '$date_to'";
if ($agent_filter > 0) {
    $where .= " AND sa.agent_id = $agent_filter";
}
if (!$is_admin) {
    $where .= " AND u.outlet_id = $outlet_id";
} elseif ($outlet_filter > 0) {
    $where .= " AND u.outlet_id = $outlet_filter";
}

$report_query = "
    SELECT 
        sa.id as assignment_id,
        sa.shift_date,
        sa.shift_type,
        sa.shift_start,
        sa.shift_end,
        sa.no_show_reason,
        u.full_name as agent_name,
        al.login_time,
        al.logout_time,
        TIMEDIFF(al.logout_time, al.login_time) as actual_hours,
        TIMEDIFF(CONCAT(sa.shift_date, ' ', sa.shift_end), CONCAT(sa.shift_date, ' ', sa.shift_start)) as scheduled_hours,
        CASE 
            WHEN al.login_time IS NULL THEN 'No show'
            WHEN al.logout_time IS NULL THEN 'Clocked in'
            WHEN TIMEDIFF(al.logout_time, al.login_time) < TIMEDIFF(CONCAT(sa.shift_date, ' ', sa.shift_end), CONCAT(sa.shift_date, ' ', sa.shift_start)) THEN 'Short'
            ELSE 'Full'
        END as attendance_status
    FROM shift_assignments sa
    JOIN users u ON sa.agent_id = u.id
    LEFT JOIN attendance_logs al ON al.agent_id = sa.agent_id 
        AND DATE(al.login_time) = sa.shift_date
        AND (al.shift_assignment_id = sa.id OR al.shift_assignment_id IS NULL)
    WHERE $where
    ORDER BY sa.shift_date DESC, sa.shift_start ASC
";
$report_result = mysqli_query($conn, $report_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift & Attendance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 15px 0; }
        .container { max-width: 1400px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .badge-short { background: #ffc107; color: #000; }
        .badge-no-show { background: #dc3545; color: #fff; }
        .badge-full { background: #28a745; color: #fff; }
        .badge-clocked-in { background: #17a2b8; color: #fff; }
        .btn-primary { background: var(--comium-red, #E30613); border: none; }
        .btn-primary:hover { background: #b3040f; }
        :root { --comium-red: #E30613; --comium-dark: #333; }
        .reason-form { display: inline-flex; gap: 5px; align-items: center; }
        .reason-form select { width: 120px; padding: 2px 4px; font-size: 12px; }
        .reason-form .btn-sm { padding: 2px 8px; font-size: 12px; }
        .reason-text { font-size: 13px; font-weight: 500; }
        .edit-reason-btn { margin-left: 6px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">COM<span style="color: var(--comium-dark);">IUM</span> 
                <?php echo $is_admin ? 'ADMIN' : 'SUPERVISOR'; ?>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-graph-up"></i> Shift & Attendance Report</h3>
            <div>
                <a href="<?php echo $is_admin ? '../admin/dashboard.php' : 'dashboard.php'; ?>" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- ========== FILTERS ========== -->
        <div class="card p-3 mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">From Date</label>
                    <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">To Date</label>
                    <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo $date_to; ?>">
                </div>

                <?php if ($is_admin): ?>
                <div class="col-md-2">
                    <label class="form-label small">Outlet</label>
                    <select class="form-select form-select-sm" name="outlet_id">
                        <option value="0">All Outlets</option>
                        <?php if ($outlets && mysqli_num_rows($outlets) > 0): ?>
                            <?php while($o = mysqli_fetch_assoc($outlets)): ?>
                            <option value="<?php echo $o['id']; ?>" <?php echo $outlet_filter == $o['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="0" disabled>No active outlets found</option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-<?php echo $is_admin ? '2' : '3'; ?>">
                    <label class="form-label small">Agent</label>
                    <select class="form-select form-select-sm" name="agent_id">
                        <option value="0">All Agents</option>
                        <?php while($a = mysqli_fetch_assoc($agents)): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $agent_filter == $a['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['full_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-<?php echo $is_admin ? '3' : '3'; ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                    <a href="shift_report.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

        <!-- ========== REPORT TABLE ========== -->
        <div class="card">
            <div class="card-header"><i class="bi bi-table"></i> Shift Assignments & Attendance</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="reportTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Agent</th>
                                <th>Shift Type</th>
                                <th>Scheduled</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Actual Hours</th>
                                <th>Status</th>
                                <th>No Show Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($report_result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($report_result)): 
                                    $status_badge = '';
                                    switch($row['attendance_status']) {
                                        case 'Full':        $status_badge = 'badge-full'; break;
                                        case 'Short':       $status_badge = 'badge-short'; break;
                                        case 'Clocked in':  $status_badge = 'badge-clocked-in'; break;
                                        case 'No show': 
                                        default:            $status_badge = 'badge-no-show'; break;
                                    }
                                    $actual = $row['actual_hours'] ? $row['actual_hours'] : '--';
                                    $login = $row['login_time'] ? date('h:i A', strtotime($row['login_time'])) : '--';
                                    $logout = $row['logout_time'] ? date('h:i A', strtotime($row['logout_time'])) : '--';
                                    $shift_label = ucfirst($row['shift_type'] ?? '');
                                    $bg_color = $row['shift_type'] == 'morning' ? 'info' : ($row['shift_type'] == 'afternoon' ? 'warning' : 'dark');
                                    $is_no_show = ($row['attendance_status'] == 'No show');
                                    $current_reason = $row['no_show_reason'] ?? '';
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['shift_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['agent_name']); ?></td>
                                    <td><span class="badge bg-<?php echo $bg_color; ?>"><?php echo $shift_label; ?></span></td>
                                    <td><?php echo date('h:i A', strtotime($row['shift_start'])); ?> – <?php echo date('h:i A', strtotime($row['shift_end'])); ?></td>
                                    <td><?php echo $login; ?></td>
                                    <td><?php echo $logout; ?></td>
                                    <td><?php echo $actual; ?></td>
                                    <td><span class="badge <?php echo $status_badge; ?>"><?php echo $row['attendance_status']; ?></span></td>
                                    <td>
                                        <?php if ($is_no_show): ?>
                                            <?php if ($current_reason): ?>
                                                <span class="reason-text"><?php echo htmlspecialchars($current_reason); ?></span>
                                                <button class="btn btn-outline-secondary btn-sm edit-reason-btn" 
                                                        data-id="<?php echo $row['assignment_id']; ?>" 
                                                        data-reason="<?php echo htmlspecialchars($current_reason); ?>">
                                                    Edit
                                                </button>
                                            <?php else: ?>
                                                <form method="POST" class="reason-form">
                                                    <input type="hidden" name="action" value="update_reason">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $row['assignment_id']; ?>">
                                                    <select name="reason" class="form-select form-select-sm">
                                                        <option value="">Select reason</option>
                                                        <option value="Sick">Sick</option>
                                                        <option value="Emergency">Emergency</option>
                                                        <option value="Disaster">Disaster</option>
                                                        <option value="Personal">Personal</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center text-muted py-3">No shift records found for the selected filters.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery & DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#reportTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        // Inline edit for No Show reason – replaces the reason text + Edit button with a form
        $(document).on('click', '.edit-reason-btn', function() {
            var assignmentId = $(this).data('id');
            var currentReason = $(this).data('reason');
            var cell = $(this).closest('td');
            var formHtml = `
                <form method="POST" class="reason-form">
                    <input type="hidden" name="action" value="update_reason">
                    <input type="hidden" name="assignment_id" value="${assignmentId}">
                    <select name="reason" class="form-select form-select-sm">
                        <option value="">Select reason</option>
                        <option value="Sick" ${currentReason === 'Sick' ? 'selected' : ''}>Sick</option>
                        <option value="Emergency" ${currentReason === 'Emergency' ? 'selected' : ''}>Emergency</option>
                        <option value="Disaster" ${currentReason === 'Disaster' ? 'selected' : ''}>Disaster</option>
                        <option value="Personal" ${currentReason === 'Personal' ? 'selected' : ''}>Personal</option>
                        <option value="Other" ${currentReason === 'Other' ? 'selected' : ''}>Other</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    <button type="button" class="btn btn-secondary btn-sm cancel-edit">Cancel</button>
                </form>
            `;
            cell.html(formHtml);
        });

        $(document).on('click', '.cancel-edit', function() {
            location.reload(); // simple way to revert
        });
    </script>
</body>
</html>