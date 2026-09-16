<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/shift_helpers.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

sisEnsureAdvancedShiftTables($conn);

$is_admin = true;
$supervisor_id = (int)($_SESSION['user_id'] ?? 0);
$outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);
$role_for_layout = 'admin';

$outlet_name = 'All Outlets';
if (!$is_admin && $outlet_id) {
    $stmt = mysqli_prepare($conn, 'SELECT name FROM outlets WHERE id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $outlet_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) $outlet_name = $row['name'];
        mysqli_stmt_close($stmt);
    }
}

$message = '';
$message_type = 'info';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shift_rules'])) {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $late_minutes = max(0, min(180, (int)($_POST['shift_late_grace_minutes'] ?? 15)));
        if (function_exists('sisSetSettingValue') && sisSetSettingValue('shift_late_grace_minutes', (string)$late_minutes)) {
            $message = 'Shift late rule saved. Agents are marked late after ' . $late_minutes . ' minute(s).';
            $message_type = 'success';
            auditAction($conn, 'UPDATE_SHIFT_LATE_RULE', null, $message);
        } else {
            $message = 'Could not save shift late rule. Please run Upgrade first.';
            $message_type = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_shift'])) {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $agent_id = (int)($_POST['agent_id'] ?? 0);
        $shift_type = sisNormalizeShiftType($_POST['shift_type'] ?? 'morning');
        $defaults = sisShiftTypes()[$shift_type];
        $shift_start = trim((string)($_POST['shift_start'] ?? $defaults['start']));
        $shift_end = trim((string)($_POST['shift_end'] ?? $defaults['end']));
        $single_date = trim((string)($_POST['single_date'] ?? ''));
        $date_from = trim((string)($_POST['date_from'] ?? ''));
        $date_to = trim((string)($_POST['date_to'] ?? ''));
        $is_bulk = isset($_POST['is_bulk']) && $_POST['is_bulk'] === '1';
        $schedule_scope = trim((string)($_POST['schedule_scope'] ?? ($is_bulk ? 'custom' : 'single')));
        if (!in_array($schedule_scope, ['single','weekly','monthly','custom'], true)) $schedule_scope = $is_bulk ? 'custom' : 'single';
        $weekdays = $_POST['weekdays'] ?? [];
        if (!is_array($weekdays)) $weekdays = [];
        $notes = trim((string)($_POST['notes'] ?? ''));
        $edit_shift_id = (int)($_POST['edit_shift_id'] ?? 0);

        $agent_ok = false;
        if ($agent_id > 0) {
            if ($is_admin) {
                $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND role = 'agent' AND is_active = 1 LIMIT 1");
                mysqli_stmt_bind_param($stmt, 'i', $agent_id);
            } else {
                $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? AND role = 'agent' AND is_active = 1 AND outlet_id = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, 'ii', $agent_id, $outlet_id);
            }
            if ($stmt) {
                mysqli_stmt_execute($stmt);
                $agent_ok = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
            }
        }

        $dates = sisDateListForShiftAllocation($single_date, $date_from, $date_to, $is_bulk, $weekdays);
        if (!sisTimeOk($shift_start) || !sisTimeOk($shift_end) || $shift_start === $shift_end) {
            $message = 'Enter valid shift start and end times. Start and end cannot be the same.';
            $message_type = 'danger';
        } elseif (!$agent_ok) {
            $message = 'Please select a valid active agent for your scope.';
            $message_type = 'danger';
        } elseif (!$dates) {
            $message = 'Please choose a valid date or date range.';
            $message_type = 'danger';
        } else {
            $success = 0;
            if ($edit_shift_id > 0 && count($dates) === 1) {
                // Safer edit path: update the selected shift row by ID.
                $scopeOk = false;
                if ($is_admin) {
                    $checkStmt = mysqli_prepare($conn, 'SELECT id FROM shift_assignments WHERE id = ? LIMIT 1');
                    if ($checkStmt) mysqli_stmt_bind_param($checkStmt, 'i', $edit_shift_id);
                } else {
                    $checkStmt = mysqli_prepare($conn, 'SELECT sa.id FROM shift_assignments sa JOIN users u ON sa.agent_id = u.id WHERE sa.id = ? AND u.outlet_id = ? LIMIT 1');
                    if ($checkStmt) mysqli_stmt_bind_param($checkStmt, 'ii', $edit_shift_id, $outlet_id);
                }
                if (!empty($checkStmt)) {
                    mysqli_stmt_execute($checkStmt);
                    $scopeOk = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));
                    mysqli_stmt_close($checkStmt);
                }
                if ($scopeOk) {
                    $editDate = $dates[0];
                    $stmt = mysqli_prepare($conn, "UPDATE shift_assignments SET agent_id = ?, shift_date = ?, shift_type = ?, shift_start = ?, shift_end = ?, notes = ?, schedule_scope = ?, created_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'issssssii', $agent_id, $editDate, $shift_type, $shift_start, $shift_end, $notes, $schedule_scope, $supervisor_id, $edit_shift_id);
                        if (mysqli_stmt_execute($stmt)) $success = 1;
                        else $message = 'Could not update shift. Another shift may already exist for that agent/date.';
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    $message = 'You cannot edit that shift assignment.';
                }
            } else {
                foreach ($dates as $date) {
                    if (sisUpsertShiftAssignment($conn, $agent_id, $date, $shift_type, $shift_start, $shift_end, $supervisor_id, $notes, $schedule_scope)) {
                        $success++;
                    }
                }
            }
            if ($success > 0) {
                $message = $success . ' shift(s) saved successfully.';
                $message_type = 'success';
                auditAction($conn, $edit_shift_id > 0 ? 'EDIT_SHIFT_ASSIGNMENT' : 'ALLOCATE_SHIFT', $agent_id, $message);
            } else {
                $message = $message ?: 'No shift was saved. Please check the details and try again.';
                $message_type = 'warning';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shift'])) {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $id = (int)($_POST['shift_id'] ?? 0);
        if ($id > 0) {
            if ($is_admin) {
                $stmt = mysqli_prepare($conn, 'DELETE FROM shift_assignments WHERE id = ?');
                if ($stmt) mysqli_stmt_bind_param($stmt, 'i', $id);
            } else {
                $stmt = mysqli_prepare($conn, 'DELETE sa FROM shift_assignments sa JOIN users u ON sa.agent_id = u.id WHERE sa.id = ? AND u.outlet_id = ?');
                if ($stmt) mysqli_stmt_bind_param($stmt, 'ii', $id, $outlet_id);
            }
            if (!empty($stmt) && mysqli_stmt_execute($stmt)) {
                $message = 'Shift deleted.';
                $message_type = 'success';
                auditAction($conn, 'DELETE_SHIFT_ASSIGNMENT', $id, 'Deleted shift assignment');
            } else {
                $message = 'Could not delete shift.';
                $message_type = 'danger';
            }
            if (!empty($stmt)) mysqli_stmt_close($stmt);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sisSetFlash($message ?: 'Shift allocation updated.', $message_type);
    redirectTo('shift_allocation.php');
}
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

$agents_sql = "SELECT id, full_name, shift FROM users WHERE role = 'agent' AND is_active = 1";
if (!$is_admin) $agents_sql .= " AND outlet_id = " . (int)$outlet_id;
$agents_sql .= " ORDER BY full_name";
$agents = mysqli_query($conn, $agents_sql);

$today = date('Y-m-d');
$scope_join = (!$is_admin && $outlet_id) ? " AND u.outlet_id = " . (int)$outlet_id : '';
$attendanceJoin = tableExists($conn, 'agent_shifts') ? sisAgentShiftAggregateJoin('ash') : "LEFT JOIN (SELECT NULL AS agent_id, NULL AS shift_date, NULL AS login_time, NULL AS logout_time, 0 AS shift_session_count, 0 AS total_work_minutes WHERE 1=0) ash ON 1=0";
$assignments_today = mysqli_query($conn, "
    SELECT sa.*, u.full_name AS agent_name, u.shift AS default_shift, o.name AS outlet_name,
           ash.login_time, ash.logout_time
    FROM shift_assignments sa
    JOIN users u ON sa.agent_id = u.id
    LEFT JOIN outlets o ON u.outlet_id = o.id
    $attendanceJoin
    WHERE sa.shift_date = '$today' $scope_join
    ORDER BY sa.shift_start ASC, u.full_name ASC
");
$future_assignments = mysqli_query($conn, "
    SELECT sa.*, u.full_name AS agent_name, u.shift AS default_shift, o.name AS outlet_name,
           ash.login_time, ash.logout_time
    FROM shift_assignments sa
    JOIN users u ON sa.agent_id = u.id
    LEFT JOIN outlets o ON u.outlet_id = o.id
    $attendanceJoin
    WHERE sa.shift_date >= '$today' $scope_join
    ORDER BY sa.shift_date ASC, sa.shift_start ASC, u.full_name ASC
    LIMIT 50
");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shift Allocation - SIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart($role_for_layout, 'Shift Allocation', 'Assign agent shifts and keep attendance reports clean'); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1"><i class="bi bi-calendar2-week"></i> Shift Allocation</h3>
            <p class="text-muted mb-0"><?php echo e($outlet_name); ?> • Assign, edit, weekly, monthly, or custom shift schedules.</p>
        </div>
        <a href="shift_report.php" class="btn btn-outline-primary"><i class="bi bi-clipboard-data"></i> Shift Report</a>
    </div>

    <?php if ($message): ?><div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    
    <div class="card mb-4">
        <div class="card-header"><strong><i class="bi bi-sliders"></i> Shift Rules</strong></div>
        <div class="card-body">
            <form method="post" class="row g-3 align-items-end">
                <?php echo csrfField(); ?>
                <input type="hidden" name="save_shift_rules" value="1">
                <div class="col-md-4">
                    <label class="form-label">Mark agent late after</label>
                    <div class="input-group">
                        <input type="number" min="0" max="180" class="form-control" name="shift_late_grace_minutes" value="<?php echo (int)(function_exists('sisShiftLateGraceMinutes') ? sisShiftLateGraceMinutes() : 15); ?>">
                        <span class="input-group-text">minutes</span>
                    </div>
                    <small class="text-muted">Example: 15 means login after 15 minutes from shift start is late.</small>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save Rule</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><strong><i class="bi bi-plus-circle"></i> Allocate / Edit Shift</strong></div>
                <div class="card-body">
                    <form method="post" class="row g-3" id="shiftAllocationForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="allocate_shift" value="1">
                        <input type="hidden" name="edit_shift_id" id="edit_shift_id" value="">
                        <input type="hidden" name="is_bulk" id="is_bulk" value="0"><input type="hidden" name="schedule_scope" id="schedule_scope" value="single">
                        <div class="col-12">
                            <label class="form-label">Agent</label>
                            <select class="form-select" name="agent_id" required>
                                <option value="">Select agent</option>
                                <?php if ($agents): while ($agent = mysqli_fetch_assoc($agents)): ?>
                                    <option value="<?php echo (int)$agent['id']; ?>"><?php echo e($agent['full_name']); ?><?php echo !empty($agent['shift']) ? ' • ' . e($agent['shift']) : ''; ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Shift Type</label>
                            <select class="form-select" name="shift_type" id="shift_type" required>
                                <?php foreach (sisShiftTypes() as $key => $type): ?>
                                    <option value="<?php echo e($key); ?>" data-start="<?php echo e($type['start']); ?>" data-end="<?php echo e($type['end']); ?>"><?php echo e($type['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Start</label><input type="time" class="form-control" name="shift_start" id="shift_start" value="08:00"></div>
                        <div class="col-md-3"><label class="form-label">End</label><input type="time" class="form-control" name="shift_end" id="shift_end" value="16:00"></div>
                        <div class="col-12"><label class="form-label">Schedule Mode</label><div class="btn-group w-100" role="group"><button type="button" class="btn btn-outline-primary active" data-scope="single">Single Day</button><button type="button" class="btn btn-outline-primary" data-scope="weekly">Weekly</button><button type="button" class="btn btn-outline-primary" data-scope="monthly">Monthly</button><button type="button" class="btn btn-outline-primary" data-scope="custom">Custom Range</button></div><small class="text-muted">Saving the same agent/date again updates the existing shift.</small></div>
                        <div class="col-12 single-date-group"><label class="form-label">Date</label><input type="date" class="form-control" name="single_date" id="single_date" value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="col-md-6 date-range-group d-none"><label class="form-label">From</label><input type="date" class="form-control" name="date_from" value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="col-md-6 date-range-group d-none"><label class="form-label">To</label><input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo date('Y-m-d'); ?>"></div><div class="col-12 date-range-group d-none"><label class="form-label">Working Days</label><div class="d-flex flex-wrap gap-2"><?php foreach ([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $num=>$day): ?><label class="form-check-label border rounded px-2 py-1"><input class="form-check-input me-1" type="checkbox" name="weekdays[]" value="<?php echo $num; ?>" <?php echo $num <= 5 ? 'checked' : ''; ?>><?php echo $day; ?></label><?php endforeach; ?></div></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" placeholder="Optional shift note"></textarea></div>
                        <div class="col-12 d-grid gap-2"><button class="btn btn-primary" id="shiftSaveBtn"><i class="bi bi-save"></i> Save Shift</button><button type="button" class="btn btn-outline-secondary d-none" id="shiftCancelEditBtn"><i class="bi bi-x-circle"></i> Cancel Edit</button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header"><strong><i class="bi bi-calendar-day"></i> Today’s Assignments</strong></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Agent</th><th>Outlet</th><th>Shift</th><th>Time</th><th>Attendance</th><th>Action</th></tr></thead><tbody>
                    <?php if ($assignments_today && mysqli_num_rows($assignments_today) > 0): while ($row = mysqli_fetch_assoc($assignments_today)): $badge = sisShiftStatusBadge($row); ?>
                        <tr><td><strong><?php echo e($row['agent_name']); ?></strong></td><td><?php echo e($row['outlet_name'] ?? 'N/A'); ?></td><td><?php echo e(sisShiftLabel($row['shift_type'])); ?></td><td><?php echo e(substr($row['shift_start'],0,5)); ?> - <?php echo e(substr($row['shift_end'],0,5)); ?></td><td><span class="badge bg-<?php echo e($badge[1]); ?>"><?php echo e($badge[0]); ?></span></td><td><button type="button" class="btn btn-sm btn-outline-primary me-1" data-edit-shift data-shift-id="<?php echo (int)$row['id']; ?>" data-agent-id="<?php echo (int)$row['agent_id']; ?>" data-shift-date="<?php echo e($row['shift_date']); ?>" data-shift-type="<?php echo e($row['shift_type']); ?>" data-shift-start="<?php echo e(substr($row['shift_start'],0,5)); ?>" data-shift-end="<?php echo e(substr($row['shift_end'],0,5)); ?>" data-shift-notes="<?php echo e($row['notes'] ?? ''); ?>"><i class="bi bi-pencil-square"></i> Edit</button><form method="post" class="d-inline" onsubmit="return confirm('Delete this shift?')"><?php echo csrfField(); ?><input type="hidden" name="delete_shift" value="1"><input type="hidden" name="shift_id" value="<?php echo (int)$row['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
                    <?php endwhile; else: ?><tr><td colspan="6" class="text-center text-muted py-4">No shifts allocated for today.</td></tr><?php endif; ?>
                    </tbody></table></div></div>
            </div>

            <div class="card">
                <div class="card-header"><strong><i class="bi bi-calendar3"></i> Upcoming Assignments</strong></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Agent</th><th>Outlet</th><th>Shift</th><th>Time</th><th>Status</th><th></th></tr></thead><tbody>
                    <?php if ($future_assignments && mysqli_num_rows($future_assignments) > 0): while ($row = mysqli_fetch_assoc($future_assignments)): $badge = sisShiftStatusBadge($row); ?>
                        <tr><td><?php echo e(date('d/m/Y', strtotime($row['shift_date']))); ?></td><td><strong><?php echo e($row['agent_name']); ?></strong></td><td><?php echo e($row['outlet_name'] ?? 'N/A'); ?></td><td><?php echo e(sisShiftLabel($row['shift_type'])); ?></td><td><?php echo e(substr($row['shift_start'],0,5)); ?> - <?php echo e(substr($row['shift_end'],0,5)); ?></td><td><span class="badge bg-<?php echo e($badge[1]); ?>"><?php echo e($badge[0]); ?></span></td><td><button type="button" class="btn btn-sm btn-outline-primary me-1" data-edit-shift data-shift-id="<?php echo (int)$row['id']; ?>" data-agent-id="<?php echo (int)$row['agent_id']; ?>" data-shift-date="<?php echo e($row['shift_date']); ?>" data-shift-type="<?php echo e($row['shift_type']); ?>" data-shift-start="<?php echo e(substr($row['shift_start'],0,5)); ?>" data-shift-end="<?php echo e(substr($row['shift_end'],0,5)); ?>" data-shift-notes="<?php echo e($row['notes'] ?? ''); ?>"><i class="bi bi-pencil-square"></i> Edit</button><form method="post" class="d-inline" onsubmit="return confirm('Delete this shift?')"><?php echo csrfField(); ?><input type="hidden" name="delete_shift" value="1"><input type="hidden" name="shift_id" value="<?php echo (int)$row['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
                    <?php endwhile; else: ?><tr><td colspan="7" class="text-center text-muted py-4">No upcoming shifts found.</td></tr><?php endif; ?>
                    </tbody></table></div></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<script>
const shiftType = document.getElementById('shift_type');
const startInput = document.getElementById('shift_start');
const endInput = document.getElementById('shift_end');
shiftType?.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    startInput.value = opt.dataset.start || '08:00';
    endInput.value = opt.dataset.end || '16:00';
});
function setScheduleScope(scope){
    document.getElementById('schedule_scope').value = scope;
    document.getElementById('is_bulk').value = scope === 'single' ? '0' : '1';
    document.querySelectorAll('[data-scope]').forEach(btn => btn.classList.toggle('active', btn.dataset.scope === scope));
    document.querySelectorAll('.date-range-group').forEach(el => el.classList.toggle('d-none', scope === 'single'));
    document.querySelectorAll('.single-date-group').forEach(el => el.classList.toggle('d-none', scope !== 'single'));
    const from = document.querySelector('input[name="date_from"]');
    const to = document.querySelector('input[name="date_to"]');
    const today = new Date();
    const fmt = d => d.toISOString().slice(0,10);
    if (scope === 'weekly') { const end = new Date(today); end.setDate(today.getDate()+6); from.value = fmt(today); to.value = fmt(end); }
    if (scope === 'monthly') { const end = new Date(today.getFullYear(), today.getMonth()+1, 0); from.value = fmt(today); to.value = fmt(end); }
}
document.querySelectorAll('[data-scope]').forEach(btn => btn.addEventListener('click', () => setScheduleScope(btn.dataset.scope)));
function editShiftFromButton(btn){
    const agentId = btn.dataset.agentId || '';
    const shiftId = btn.dataset.shiftId || '';
    const date = btn.dataset.shiftDate || '';
    const type = btn.dataset.shiftType || 'morning';
    const start = btn.dataset.shiftStart || '08:00';
    const end = btn.dataset.shiftEnd || '16:00';
    const notes = btn.dataset.shiftNotes || '';
    const form = document.getElementById('shiftAllocationForm');
    if (!form) return;
    const idInput = document.getElementById('edit_shift_id'); if (idInput) idInput.value = shiftId;
    const agentSelect = form.querySelector('select[name="agent_id"]'); if (agentSelect) agentSelect.value = agentId;
    const typeSelect = document.getElementById('shift_type'); if (typeSelect) typeSelect.value = type;
    const startInput = document.getElementById('shift_start'); if (startInput) startInput.value = start;
    const endInput = document.getElementById('shift_end'); if (endInput) endInput.value = end;
    const single = form.querySelector('input[name="single_date"]'); if(single) single.value = date;
    const notesBox = form.querySelector('textarea[name="notes"]'); if(notesBox) notesBox.value = notes;
    setScheduleScope('single');
    const saveBtn = document.getElementById('shiftSaveBtn'); if(saveBtn) saveBtn.innerHTML = '<i class="bi bi-save"></i> Update Shift';
    const cancelBtn = document.getElementById('shiftCancelEditBtn'); if(cancelBtn) cancelBtn.classList.remove('d-none');
    form.scrollIntoView({behavior:'smooth', block:'start'});
}
document.querySelectorAll('[data-edit-shift]').forEach(btn => btn.addEventListener('click', () => editShiftFromButton(btn)));
const cancelEditBtn = document.getElementById('shiftCancelEditBtn');
if (cancelEditBtn) cancelEditBtn.addEventListener('click', function(){
    const form = document.getElementById('shiftAllocationForm');
    if (form) form.reset();
    const idInput = document.getElementById('edit_shift_id'); if (idInput) idInput.value = '';
    setScheduleScope('single');
    const saveBtn = document.getElementById('shiftSaveBtn'); if(saveBtn) saveBtn.innerHTML = '<i class="bi bi-save"></i> Save Shift';
    cancelEditBtn.classList.add('d-none');
});
</script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
