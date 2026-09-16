<?php
require_once '../config.php';
requireLogin();

if (!hasRole('supervisor') && !hasRole('admin')) {
    header('Location: ../dashboard.php');
    exit();
}

$supervisor_id = $_SESSION['user_id'];
$outlet_id = $_SESSION['outlet_id'];
$is_admin = hasRole('admin');

// Get outlet details (for display)
if ($outlet_id) {
    $outlet_query = "SELECT name FROM outlets WHERE id = $outlet_id";
    $outlet_result = mysqli_query($conn, $outlet_query);
    $outlet = mysqli_fetch_assoc($outlet_result);
    $outlet_name = $outlet['name'] ?? 'Unknown Outlet';
} else {
    $outlet_name = 'All Outlets (Admin)';
}

$message = '';
$message_type = '';

// Get all active agents – filtered by outlet unless admin
$agents_query = "SELECT id, full_name, shift FROM users WHERE role = 'agent' AND is_active = 1";
if (!$is_admin && $outlet_id) {
    $agents_query .= " AND outlet_id = $outlet_id";
}
$agents_query .= " ORDER BY full_name";
$agents = mysqli_query($conn, $agents_query);

// Shift type definitions
$shift_types = [
    'morning'   => ['label' => 'Morning',   'start' => '08:00', 'end' => '16:00'],
    'afternoon' => ['label' => 'Afternoon', 'start' => '12:00', 'end' => '20:00'],
    'night'     => ['label' => 'Night',     'start' => '20:00', 'end' => '04:00'],
];

// Handle form submission (Insert / Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_shift'])) {
    $agent_id = (int)$_POST['agent_id'];
    $shift_type = mysqli_real_escape_string($conn, $_POST['shift_type']);
    $shift_start = mysqli_real_escape_string($conn, $_POST['shift_start']);
    $shift_end = mysqli_real_escape_string($conn, $_POST['shift_end']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    $single_date = isset($_POST['single_date']) ? $_POST['single_date'] : '';
    $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : '';
    $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : '';
    $is_bulk = isset($_POST['is_bulk']) && $_POST['is_bulk'] == '1';

    $dates_to_process = [];
    if ($edit_id > 0) {
        $check = mysqli_query($conn, "SELECT shift_date FROM shift_assignments WHERE id = $edit_id");
        if ($row = mysqli_fetch_assoc($check)) {
            $dates_to_process[] = $row['shift_date'];
        } else {
            $message = "Shift not found.";
            $message_type = 'danger';
        }
    } elseif ($is_bulk && $date_from && $date_to) {
        $start = new DateTime($date_from);
        $end = new DateTime($date_to);
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        foreach ($period as $dt) {
            $dates_to_process[] = $dt->format('Y-m-d');
        }
    } elseif ($single_date) {
        $dates_to_process[] = $single_date;
    } else {
        $message = "Please select a date or date range.";
        $message_type = 'danger';
    }

    if (!isset($shift_types[$shift_type])) {
        $shift_type = 'morning';
    }

    $success_count = 0;
    foreach ($dates_to_process as $shift_date) {
        if ($edit_id > 0) {
            $update = "UPDATE shift_assignments SET 
                        agent_id = $agent_id,
                        shift_date = '$shift_date',
                        shift_type = '$shift_type', 
                        shift_start = '$shift_start', 
                        shift_end = '$shift_end', 
                        created_by = $supervisor_id 
                        WHERE id = $edit_id";
            if (mysqli_query($conn, $update)) {
                $success_count++;
            } else {
                $message = "Error updating shift: " . mysqli_error($conn);
                $message_type = 'danger';
            }
            break;
        } else {
            $check = mysqli_query($conn, "SELECT id FROM shift_assignments WHERE agent_id = $agent_id AND shift_date = '$shift_date'");
            if (mysqli_num_rows($check) > 0) {
                $row = mysqli_fetch_assoc($check);
                $update = "UPDATE shift_assignments SET 
                            shift_type = '$shift_type', 
                            shift_start = '$shift_start', 
                            shift_end = '$shift_end', 
                            created_by = $supervisor_id 
                            WHERE id = {$row['id']}";
                if (mysqli_query($conn, $update)) {
                    $success_count++;
                } else {
                    $message = "Error updating shift for $shift_date: " . mysqli_error($conn);
                    $message_type = 'danger';
                }
            } else {
                $insert = "INSERT INTO shift_assignments (agent_id, shift_date, shift_type, shift_start, shift_end, created_by) 
                           VALUES ($agent_id, '$shift_date', '$shift_type', '$shift_start', '$shift_end', $supervisor_id)";
                if (mysqli_query($conn, $insert)) {
                    $success_count++;
                } else {
                    $message = "Error inserting shift for $shift_date: " . mysqli_error($conn);
                    $message_type = 'danger';
                }
            }
        }
    }

    if ($success_count > 0) {
        $message = "$success_count shift(s) allocated/updated successfully!";
        $message_type = 'success';
    } elseif (empty($message)) {
        $message = "No changes made.";
        $message_type = 'info';
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $delete = "DELETE FROM shift_assignments WHERE id = $id AND created_by = $supervisor_id";
    if (mysqli_query($conn, $delete)) {
        $message = "Shift deleted!";
        $message_type = 'success';
    } else {
        $message = "Error deleting shift.";
        $message_type = 'danger';
    }
}

$today = date('Y-m-d');
$assignments_today = mysqli_query($conn, "
    SELECT sa.*, u.full_name as agent_name 
    FROM shift_assignments sa
    JOIN users u ON sa.agent_id = u.id
    WHERE sa.shift_date = '$today' 
    " . (!$is_admin ? "AND u.outlet_id = $outlet_id" : "") . "
    ORDER BY sa.shift_start
");

$future_assignments = mysqli_query($conn, "
    SELECT sa.*, u.full_name as agent_name 
    FROM shift_assignments sa
    JOIN users u ON sa.agent_id = u.id
    WHERE sa.shift_date >= '$today' 
    " . (!$is_admin ? "AND u.outlet_id = $outlet_id" : "") . "
    ORDER BY sa.shift_date ASC, sa.shift_start ASC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Allocation - <?php echo $outlet_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 15px 0; }
        .container { max-width: 1200px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 2px solid #f1f1f1; font-weight: 600; }
        .btn-primary { background: var(--comium-red, #E30613); border: none; }
        .btn-primary:hover { background: #b3040f; }
        .btn-outline-secondary:hover { background: #e9ecef; }
        :root { --comium-red: #E30613; --comium-dark: #333; }
        .edit-btn { cursor: pointer; color: #0d6efd; }
        .edit-btn:hover { color: #0a58ca; }
        .cancel-btn { display: none; }
        .date-range-group { display: none; }
        .single-date-group { display: block; }
        .outlet-badge { background: var(--comium-red); color: white; padding: 5px 12px; border-radius: 20px; font-size: 13px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">COM<span style="color: var(--comium-dark);">IUM</span> SUPERVISOR</a>
            <div class="d-flex align-items-center gap-3">
                <span class="outlet-badge"><i class="bi bi-building"></i> <?php echo $outlet_name; ?></span>
                <span class="text-muted small"><?php echo $_SESSION['full_name']; ?></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-clock-history"></i> Shift Allocation</h3>
            <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Allocate / Edit Shift Form -->
        <div class="card" id="shiftFormCard">
            <div class="card-header"><i class="bi bi-plus-circle"></i> <span id="formTitle">Assign New Shift</span></div>
            <div class="card-body">
                <form method="POST" class="row g-3" id="shiftForm">
                    <input type="hidden" name="edit_id" id="edit_id" value="0">
                    <input type="hidden" name="is_bulk" id="is_bulk" value="0">

                    <div class="col-md-3">
                        <label class="form-label">Agent</label>
                        <select class="form-select" name="agent_id" id="agent_id" required>
                            <option value="">Select Agent</option>
                            <?php while($a = mysqli_fetch_assoc($agents)): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Shift Type</label>
                        <select class="form-select" name="shift_type" id="shiftType" required>
                            <option value="">Select Shift</option>
                            <option value="morning">Morning (08:00 – 16:00)</option>
                            <option value="afternoon">Afternoon (12:00 – 20:00)</option>
                            <option value="night">Night (20:00 – 04:00)</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="shift_start" id="shiftStart" value="08:00" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="shift_end" id="shiftEnd" value="16:00" required>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="bulkSwitch" onchange="toggleBulkMode()">
                            <label class="form-check-label" for="bulkSwitch">Bulk (Date Range)</label>
                        </div>
                    </div>

                    <div class="col-md-3 single-date-group">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="single_date" id="singleDate" value="<?php echo $today; ?>">
                    </div>

                    <div class="col-md-3 date-range-group" style="display:none;">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" id="dateFrom" value="<?php echo $today; ?>">
                    </div>

                    <div class="col-md-3 date-range-group" style="display:none;">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" id="dateTo" value="<?php echo $today; ?>">
                    </div>

                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" name="allocate_shift" id="submitBtn" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle"></i> Save
                        </button>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-secondary w-100" id="cancelBtn" onclick="clearForm()" style="display:none;">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Today's Assignments -->
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-day"></i> Today's Shifts (<?php echo date('d M Y'); ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Shift Type</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($assignments_today) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($assignments_today)): 
                                    $attendance = mysqli_fetch_assoc(mysqli_query($conn, "
                                        SELECT login_time, logout_time FROM attendance_logs 
                                        WHERE agent_id = {$row['agent_id']} AND DATE(login_time) = '$today'
                                        ORDER BY login_time DESC LIMIT 1
                                    "));
                                    $status = 'Not started';
                                    if ($attendance && $attendance['login_time']) {
                                        $status = 'Clocked in';
                                        if ($attendance['logout_time']) {
                                            $status = 'Completed';
                                        }
                                    }
                                    $shift_label = ucfirst($row['shift_type'] ?? '');
                                    $bg_color = $row['shift_type'] == 'morning' ? 'info' : ($row['shift_type'] == 'afternoon' ? 'warning' : 'dark');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['agent_name']); ?></td>
                                    <td><span class="badge bg-<?php echo $bg_color; ?>"><?php echo $shift_label; ?></span></td>
                                    <td><?php echo date('h:i A', strtotime($row['shift_start'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($row['shift_end'])); ?></td>
                                    <td><span class="badge bg-<?php echo $status == 'Completed' ? 'success' : ($status == 'Clocked in' ? 'warning' : 'secondary'); ?>"><?php echo $status; ?></span></td>
                                    <td>
                                        <a href="#" class="edit-btn me-2" onclick="editShift(<?php echo $row['id']; ?>, '<?php echo addslashes($row['agent_name']); ?>', <?php echo $row['agent_id']; ?>, '<?php echo $row['shift_date']; ?>', '<?php echo $row['shift_type']; ?>', '<?php echo $row['shift_start']; ?>', '<?php echo $row['shift_end']; ?>')" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="text-danger" onclick="return confirm('Delete this shift?')" title="Delete"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No shifts allocated for today</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upcoming Shifts -->
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-week"></i> Upcoming Shifts (Next 7 Days)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Agent</th>
                                <th>Shift Type</th>
                                <th>Start</th>
                                <th>End</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($future_assignments) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($future_assignments)): 
                                    $shift_label = ucfirst($row['shift_type'] ?? '');
                                    $bg_color = $row['shift_type'] == 'morning' ? 'info' : ($row['shift_type'] == 'afternoon' ? 'warning' : 'dark');
                                ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['shift_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['agent_name']); ?></td>
                                    <td><span class="badge bg-<?php echo $bg_color; ?>"><?php echo $shift_label; ?></span></td>
                                    <td><?php echo date('h:i A', strtotime($row['shift_start'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($row['shift_end'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No upcoming shifts</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleBulkMode() {
            const isBulk = document.getElementById('bulkSwitch').checked;
            document.getElementById('is_bulk').value = isBulk ? '1' : '0';
            document.querySelectorAll('.single-date-group').forEach(el => el.style.display = isBulk ? 'none' : 'block');
            document.querySelectorAll('.date-range-group').forEach(el => el.style.display = isBulk ? 'block' : 'none');
        }

        document.getElementById('shiftType').addEventListener('change', function() {
            const shift = this.value;
            const times = {
                'morning': { start: '08:00', end: '16:00' },
                'afternoon': { start: '12:00', end: '20:00' },
                'night': { start: '20:00', end: '04:00' }
            };
            if (shift && times[shift]) {
                document.getElementById('shiftStart').value = times[shift].start;
                document.getElementById('shiftEnd').value = times[shift].end;
            }
        });

        function editShift(id, agentName, agentId, date, shiftType, startTime, endTime) {
            document.getElementById('edit_id').value = id;
            document.getElementById('agent_id').value = agentId;
            document.getElementById('singleDate').value = date;
            document.getElementById('dateFrom').value = date;
            document.getElementById('dateTo').value = date;
            document.getElementById('shiftType').value = shiftType;
            document.getElementById('shiftStart').value = startTime;
            document.getElementById('shiftEnd').value = endTime;

            document.getElementById('bulkSwitch').disabled = true;

            document.getElementById('formTitle').textContent = 'Edit Shift for ' + agentName;
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-pencil-square"></i> Update Shift';
            document.getElementById('submitBtn').name = 'allocate_shift';

            document.getElementById('cancelBtn').style.display = 'block';
            document.getElementById('shiftFormCard').scrollIntoView({ behavior: 'smooth' });
        }

        function clearForm() {
            document.getElementById('edit_id').value = 0;
            document.getElementById('shiftForm').reset();
            document.getElementById('singleDate').value = '<?php echo $today; ?>';
            document.getElementById('dateFrom').value = '<?php echo $today; ?>';
            document.getElementById('dateTo').value = '<?php echo $today; ?>';
            document.getElementById('shiftType').value = '';
            document.getElementById('shiftStart').value = '08:00';
            document.getElementById('shiftEnd').value = '16:00';
            document.getElementById('bulkSwitch').checked = false;
            document.getElementById('bulkSwitch').disabled = false;
            document.getElementById('is_bulk').value = '0';
            document.querySelectorAll('.single-date-group').forEach(el => el.style.display = 'block');
            document.querySelectorAll('.date-range-group').forEach(el => el.style.display = 'none');

            document.getElementById('formTitle').textContent = 'Assign New Shift';
            document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle"></i> Save';
            document.getElementById('submitBtn').name = 'allocate_shift';

            document.getElementById('cancelBtn').style.display = 'none';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>