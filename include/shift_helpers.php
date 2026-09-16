<?php
// Shared helpers for SIS Advanced Shift Management.

if (!function_exists('sisShiftTypes')) {
    function sisShiftTypes() {
        return [
            'morning' => ['label' => 'Morning', 'start' => '08:00', 'end' => '16:00'],
            'afternoon' => ['label' => 'Afternoon', 'start' => '12:00', 'end' => '20:00'],
            'night' => ['label' => 'Night', 'start' => '20:00', 'end' => '04:00'],
        ];
    }
}

if (!function_exists('sisNormalizeShiftType')) {
    function sisNormalizeShiftType($shift_type) {
        $shift_type = strtolower(trim((string)$shift_type));
        return array_key_exists($shift_type, sisShiftTypes()) ? $shift_type : 'morning';
    }
}

if (!function_exists('sisShiftLabel')) {
    function sisShiftLabel($shift_type) {
        $types = sisShiftTypes();
        $key = sisNormalizeShiftType($shift_type);
        return $types[$key]['label'];
    }
}

if (!function_exists('sisValidDate')) {
    function sisValidDate($date) {
        $dt = DateTime::createFromFormat('Y-m-d', (string)$date);
        return $dt && $dt->format('Y-m-d') === $date;
    }
}

if (!function_exists('sisTimeOk')) {
    function sisTimeOk($time) {
        $time = trim((string)$time);
        foreach (['H:i', 'H:i:s'] as $format) {
            $dt = DateTime::createFromFormat('!' . $format, $time);
            $errors = DateTime::getLastErrors();
            if ($dt && ($errors === false || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0))
                && $dt->format($format) === $time) return true;
        }
        return false;
    }
}

if (!function_exists('sisDateListForShiftAllocation')) {
    function sisDateListForShiftAllocation($single_date, $date_from, $date_to, $is_bulk, array $weekdays = []) {
        $dates = [];
        if ($is_bulk && sisValidDate($date_from) && sisValidDate($date_to)) {
            $allowedDays = array_map('intval', $weekdays);
            $start = new DateTime($date_from);
            $end = new DateTime($date_to);
            if ($start > $end) return [];
            $end->modify('+1 day');
            foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $date) {
                $dow = (int)$date->format('N');
                if ($allowedDays && !in_array($dow, $allowedDays, true)) continue;
                $dates[] = $date->format('Y-m-d');
            }
        } elseif (sisValidDate($single_date)) {
            $dates[] = $single_date;
        }
        return array_slice($dates, 0, 93);
    }
}

if (!function_exists('sisEnsureAdvancedShiftTables')) {
    function sisEnsureAdvancedShiftTables($conn) {
        static $done = false;
        if (!$conn) return false;
        if ($done && tableExists($conn, 'shift_assignments') && tableExists($conn, 'shift_requests') && tableExists($conn, 'user_notifications')) return true;
        if (function_exists('ensureShiftAssignmentsTable')) ensureShiftAssignmentsTable($conn);

        if (tableExists($conn, 'shift_assignments')) {
            $cols = [
                'schedule_scope' => "ALTER TABLE shift_assignments ADD COLUMN schedule_scope ENUM('single','weekly','monthly','custom') NOT NULL DEFAULT 'single' AFTER notes",
                'early_end_allowed' => "ALTER TABLE shift_assignments ADD COLUMN early_end_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER schedule_scope",
                'overtime_allowed' => "ALTER TABLE shift_assignments ADD COLUMN overtime_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER early_end_allowed",
            ];
            foreach ($cols as $col => $sql) if (!tableColumnExists($conn, 'shift_assignments', $col)) @mysqli_query($conn, $sql);
        }

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS shift_requests (
            id INT NOT NULL AUTO_INCREMENT,
            assignment_id INT NULL,
            agent_id INT NOT NULL,
            request_type ENUM('early_end','overtime','login_access') NOT NULL DEFAULT 'early_end',
            reason TEXT NULL,
            status ENUM('pending','approved','rejected','notified') NOT NULL DEFAULT 'pending',
            supervisor_id INT NULL,
            supervisor_note TEXT NULL,
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_at DATETIME NULL,
            used_at DATETIME NULL,
            overtime_started_at DATETIME NULL,
            overtime_minutes INT NOT NULL DEFAULT 0,
            logout_deadline DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_shift_requests_agent (agent_id, requested_at),
            KEY idx_shift_requests_assignment (assignment_id, request_type, status),
            KEY idx_shift_requests_status (status, request_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        if (tableExists($conn, 'shift_requests')) {
            @mysqli_query($conn, "ALTER TABLE shift_requests MODIFY request_type ENUM('early_end','overtime','login_access') NOT NULL DEFAULT 'early_end'");
            if (!tableColumnExists($conn, 'shift_requests', 'used_at')) {
                @mysqli_query($conn, "ALTER TABLE shift_requests ADD COLUMN used_at DATETIME NULL AFTER decided_at");
            }
            if (!tableColumnExists($conn, 'shift_requests', 'logout_deadline')) {
                @mysqli_query($conn, "ALTER TABLE shift_requests ADD COLUMN logout_deadline DATETIME NULL AFTER overtime_minutes");
            }
            if (function_exists('indexExists') && !indexExists($conn, 'shift_requests', 'idx_shift_requests_used')) {
                @mysqli_query($conn, "ALTER TABLE shift_requests ADD KEY idx_shift_requests_used (request_type, status, used_at)");
            }
        }
        sisEnsureNotificationTables($conn);
        $done = tableExists($conn, 'shift_assignments') && tableExists($conn, 'shift_requests') && tableExists($conn, 'user_notifications');
        return $done;
    }
}

if (!function_exists('sisUpsertShiftAssignment')) {
    function sisUpsertShiftAssignment($conn, $agent_id, $shift_date, $shift_type, $shift_start, $shift_end, $created_by, $notes = '', $schedule_scope = 'single') {
        sisEnsureAdvancedShiftTables($conn);
        $agent_id = (int)$agent_id;
        $created_by = normalizeNullableInt($created_by);
        $shift_type = sisNormalizeShiftType($shift_type);
        $schedule_scope = in_array($schedule_scope, ['single','weekly','monthly','custom'], true) ? $schedule_scope : 'single';
        $notes = trim((string)$notes);
        if ($agent_id <= 0 || !sisValidDate($shift_date)) return false;
        if (!sisTimeOk($shift_start)) $shift_start = sisShiftTypes()[$shift_type]['start'];
        if (!sisTimeOk($shift_end)) $shift_end = sisShiftTypes()[$shift_type]['end'];

        $stmt = mysqli_prepare($conn, "INSERT INTO shift_assignments
            (agent_id, shift_date, shift_type, shift_start, shift_end, status, notes, schedule_scope, created_by)
            VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                shift_type = VALUES(shift_type),
                shift_start = VALUES(shift_start),
                shift_end = VALUES(shift_end),
                notes = VALUES(notes),
                schedule_scope = VALUES(schedule_scope),
                created_by = VALUES(created_by),
                updated_at = CURRENT_TIMESTAMP");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'issssssi', $agent_id, $shift_date, $shift_type, $shift_start, $shift_end, $notes, $schedule_scope, $created_by);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('sisGetAgentAssignmentForDate')) {
    function sisGetAgentAssignmentForDate($conn, $agent_id, $date = null) {
        sisEnsureAdvancedShiftTables($conn);
        $date = $date ?: date('Y-m-d');
        $agent_id = (int)$agent_id;
        $stmt = mysqli_prepare($conn, "SELECT sa.*, u.full_name AS agent_name, u.outlet_id, o.name AS outlet_name
            FROM shift_assignments sa
            JOIN users u ON sa.agent_id = u.id
            LEFT JOIN outlets o ON u.outlet_id = o.id
            WHERE sa.agent_id = ? AND sa.shift_date = ? AND sa.status <> 'cancelled'
            LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'is', $agent_id, $date);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('sisGetRelevantAgentAssignment')) {
    function sisGetRelevantAgentAssignment($conn, $agent_id, $now = null) {
        $now = $now ?: time();
        $yesterday = date('Y-m-d', $now - 86400);
        $previous = sisGetAgentAssignmentForDate($conn, $agent_id, $yesterday);
        if ($previous) {
            $openPrevious = function_exists('getAgentActiveShift') ? getAgentActiveShift($conn, (int)$agent_id, $yesterday) : null;
            if ($openPrevious) return $previous;
            [$startTs, $endTs] = sisShiftWindowTimestamps($previous);
            $startText = substr((string)($previous['shift_start'] ?? ''), 0, 8);
            $endText = substr((string)($previous['shift_end'] ?? ''), 0, 8);
            if ($endText <= $startText && $now >= $startTs && $now <= ($endTs + 3600)) return $previous;
        }
        return sisGetAgentAssignmentForDate($conn, $agent_id, date('Y-m-d', $now));
    }
}

if (!function_exists('sisAgentUsesManagedSchedule')) {
    function sisAgentUsesManagedSchedule($conn, $agent_id) {
        if (!$conn || !tableExists($conn, 'shift_assignments')) return false;
        $agent_id = (int)$agent_id;
        $stmt = mysqli_prepare($conn, "SELECT id FROM shift_assignments
            WHERE agent_id = ? AND status <> 'cancelled'
              AND shift_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 31 DAY) AND DATE_ADD(CURDATE(), INTERVAL 31 DAY)
            LIMIT 1");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'i', $agent_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $uses = (bool)($res && mysqli_fetch_assoc($res));
        mysqli_stmt_close($stmt);
        return $uses;
    }
}

if (!function_exists('sisShiftWindowTimestamps')) {
    function sisShiftWindowTimestamps(array $assignment) {
        $date = $assignment['shift_date'] ?? date('Y-m-d');
        $start = substr((string)($assignment['shift_start'] ?? '08:00:00'), 0, 8);
        $end = substr((string)($assignment['shift_end'] ?? '16:00:00'), 0, 8);
        if (strlen($start) === 5) $start .= ':00';
        if (strlen($end) === 5) $end .= ':00';
        $start_ts = strtotime($date . ' ' . $start);
        $end_ts = strtotime($date . ' ' . $end);
        if ($end_ts !== false && $start_ts !== false && $end_ts <= $start_ts) $end_ts += 86400;
        return [$start_ts ?: time(), $end_ts ?: time(), $start, $end];
    }
}

if (!function_exists('sisCanAgentLoginForShift')) {
    function sisCanAgentLoginForShift(array $assignment, $now = null) {
        $now = $now ?: time();
        [$start_ts, $end_ts] = sisShiftWindowTimestamps($assignment);
        $open_ts = $start_ts - 3600;
        $close_ts = $end_ts + 3600;
        if ($now < $open_ts) {
            return [false, 'Your shift is scheduled for ' . date('H:i', $start_ts) . '. You can login from ' . date('H:i', $open_ts) . ' only.'];
        }
        if ($now > $close_ts) {
            return [false, 'Your scheduled shift ended at ' . date('H:i', $end_ts) . '. Please contact your Supervisor if you need access.'];
        }
        return [true, 'Allowed'];
    }
}

if (!function_exists('sisUpdateAssignmentAttendance')) {
    function sisUpdateAssignmentAttendance($conn, array $assignment, $login_time = null) {
        $login_time = $login_time ?: date('Y-m-d H:i:s');
        [$start_ts] = sisShiftWindowTimestamps($assignment);
        $login_ts = strtotime($login_time);
        $grace = function_exists('sisShiftLateGraceMinutes') ? sisShiftLateGraceMinutes() : 30;
        $status = ($login_ts && $login_ts > ($start_ts + ($grace * 60))) ? 'late' : 'present';
        $id = (int)$assignment['id'];
        $stmt = mysqli_prepare($conn, "UPDATE shift_assignments SET status = ? WHERE id = ? AND status IN ('scheduled','present','late')");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $status, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

if (!function_exists('sisGetPendingShiftRequest')) {
    function sisGetPendingShiftRequest($conn, $assignment_id, $request_type = 'early_end') {
        sisEnsureAdvancedShiftTables($conn);
        $assignment_id = (int)$assignment_id;
        $stmt = mysqli_prepare($conn, "SELECT * FROM shift_requests WHERE assignment_id = ? AND request_type = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'is', $assignment_id, $request_type);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('sisCreateShiftRequest')) {
    function sisCreateShiftRequest($conn, $assignment_id, $agent_id, $request_type, $reason, $status = 'pending', $overtime_minutes = 0) {
        sisEnsureAdvancedShiftTables($conn);
        $assignment_id = normalizeNullableInt($assignment_id);
        $agent_id = (int)$agent_id;
        $request_type = in_array($request_type, ['early_end','overtime','login_access'], true) ? $request_type : 'early_end';
        $status = in_array($status, ['pending','approved','rejected','notified'], true) ? $status : 'pending';
        $reason = trim((string)$reason);
        $overtime_started_at = $request_type === 'overtime' ? date('Y-m-d H:i:s') : null;
        $stmt = mysqli_prepare($conn, "INSERT INTO shift_requests (assignment_id, agent_id, request_type, reason, status, overtime_started_at, overtime_minutes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'iissssi', $assignment_id, $agent_id, $request_type, $reason, $status, $overtime_started_at, $overtime_minutes);
        $ok = mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        if ($ok && function_exists('auditAction')) auditAction($conn, 'SHIFT_' . strtoupper($request_type) . '_REQUEST', $id, $reason);
        if ($ok && in_array($request_type, ['early_end','overtime'], true)) {
            $outlet_id = null;
            $agent_name = 'Agent';
            $agentStmt = mysqli_prepare($conn, "SELECT full_name, outlet_id FROM users WHERE id = ? LIMIT 1");
            if ($agentStmt) {
                mysqli_stmt_bind_param($agentStmt, 'i', $agent_id);
                mysqli_stmt_execute($agentStmt);
                $agentRes = mysqli_stmt_get_result($agentStmt);
                if ($agentRow = ($agentRes ? mysqli_fetch_assoc($agentRes) : null)) {
                    $agent_name = $agentRow['full_name'] ?: 'Agent';
                    $outlet_id = normalizeNullableInt($agentRow['outlet_id'] ?? null);
                }
                mysqli_stmt_close($agentStmt);
            }
            if ($request_type === 'early_end') {
                $msg = $agent_name . ' requested to end shift early. Reason: ' . $reason;
                sisCreateNotification($conn, null, 'supervisor', $outlet_id, 'Early shift end request', $msg, 'supervisor/shift_requests.php', 'shift_request', $id);
                sisCreateNotification($conn, null, 'admin', null, 'Early shift end request', $msg, 'admin/shift_requests.php', 'shift_request', $id);
            } elseif ($request_type === 'overtime') {
                $msg = $agent_name . ' is staying overtime. Overtime: ' . (int)$overtime_minutes . ' minute(s). ' . $reason;
                sisCreateNotification($conn, null, 'supervisor', $outlet_id, 'Agent overtime notification', $msg, 'supervisor/shift_requests.php', 'shift_request', $id);
                sisCreateNotification($conn, null, 'admin', null, 'Agent overtime notification', $msg, 'admin/shift_requests.php', 'shift_request', $id);
            }
        }
        return $ok ? $id : false;
    }
}

if (!function_exists('sisSupervisorPendingShiftCount')) {
    function sisSupervisorPendingShiftCount($conn, $outlet_id = null) {
        sisEnsureAdvancedShiftTables($conn);
        $scope = $outlet_id ? ' AND u.outlet_id = ' . (int)$outlet_id : '';
        $sql = "SELECT COUNT(*) AS c FROM shift_requests sr JOIN users u ON sr.agent_id = u.id WHERE sr.status = 'pending' $scope";
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('sisOvertimeMinutes')) {
    function sisOvertimeMinutes(array $assignment, $now = null) {
        $now = $now ?: time();
        [, $end_ts] = sisShiftWindowTimestamps($assignment);
        return max(0, (int)floor(($now - $end_ts) / 60));
    }
}

if (!function_exists('sisSetOvertimeLogoutDeadline')) {
    function sisSetOvertimeLogoutDeadline($conn, $request_id, $minutes = 15) {
        $request_id = (int)$request_id;
        $minutes = max(1, min(120, (int)$minutes));
        $deadline = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $stmt = mysqli_prepare($conn, "UPDATE shift_requests SET logout_deadline = ? WHERE id = ? AND request_type = 'overtime'");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'si', $deadline, $request_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok ? $deadline : false;
    }
}

if (!function_exists('sisGetOvertimeLogoutDeadline')) {
    function sisGetOvertimeLogoutDeadline($conn, $agent_id, $assignment_id) {
        if (!$conn || !tableExists($conn, 'shift_requests') || !tableColumnExists($conn, 'shift_requests', 'logout_deadline')) return null;
        $agent_id = (int)$agent_id;
        $assignment_id = (int)$assignment_id;
        $stmt = mysqli_prepare($conn, "SELECT logout_deadline FROM shift_requests
            WHERE agent_id = ? AND assignment_id = ? AND request_type = 'overtime' AND logout_deadline IS NOT NULL
            ORDER BY id DESC LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'ii', $agent_id, $assignment_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return !empty($row['logout_deadline']) ? (string)$row['logout_deadline'] : null;
    }
}

if (!function_exists('sisShiftLateGraceMinutes')) {
    function sisShiftLateGraceMinutes() {
        return 30; // Login this many minutes (or more) after shift start = Late
    }
}

if (!function_exists('sisShiftEarlyThresholdMinutes')) {
    function sisShiftEarlyThresholdMinutes() {
        return 15; // Login this many minutes (or more) before shift start = Early
    }
}

if (!function_exists('sisShiftStatusBadge')) {
    function sisShiftStatusBadge($row) {
        $login = $row['login_time'] ?? null;
        $logout = $row['logout_time'] ?? null;
        [$start_ts, $end_ts] = sisShiftWindowTimestamps($row);
        $now = time();
        if ($login) {
            $login_ts = strtotime($login);
            $lateGrace = sisShiftLateGraceMinutes();
            $earlyGrace = sisShiftEarlyThresholdMinutes();
            if (!$logout && $now > ($end_ts + 3600)) return ['Overtime', 'primary'];
            if ($start_ts && $login_ts && $login_ts <= ($start_ts - ($earlyGrace * 60))) return ['Early', 'info'];
            if ($start_ts && $login_ts && $login_ts > ($start_ts + ($lateGrace * 60))) return ['Late', 'warning'];
            return ['Present', 'success'];
        }
        if ($end_ts && $now > $end_ts) return ['Absent', 'danger'];
        if ($start_ts && $now > $start_ts) return ['Expected', 'info'];
        return ['Scheduled', 'secondary'];
    }
}

if (!function_exists('sisAgentShiftAggregateJoin')) {
    function sisAgentShiftAggregateJoin($alias = 'ash') {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
        if ($alias === '') $alias = 'ash';
        return "LEFT JOIN (
            SELECT agent_id, shift_date, MIN(login_time) AS login_time,
                   CASE WHEN SUM(CASE WHEN logout_time IS NULL THEN 1 ELSE 0 END) > 0 THEN NULL ELSE MAX(logout_time) END AS logout_time,
                   COUNT(*) AS shift_session_count,
                   SUM(CASE WHEN logout_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, login_time, logout_time) ELSE TIMESTAMPDIFF(MINUTE, login_time, NOW()) END) AS total_work_minutes
            FROM agent_shifts
            GROUP BY agent_id, shift_date
        ) " . $alias . " ON " . $alias . ".agent_id = sa.agent_id AND " . $alias . ".shift_date = sa.shift_date";
    }
}


if (!function_exists('sisAgentLastEndedShiftForDate')) {
    function sisAgentLastEndedShiftForDate($conn, $agent_id, $date = null) {
        if (!$conn || !tableExists($conn, 'agent_shifts')) return null;
        $date = $date ?: date('Y-m-d');
        $agent_id = (int)$agent_id;
        $stmt = mysqli_prepare($conn, "SELECT * FROM agent_shifts WHERE agent_id = ? AND shift_date = ? AND logout_time IS NOT NULL ORDER BY logout_time DESC, id DESC LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'is', $agent_id, $date);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('sisCloseAgentShiftAfterApproval')) {
    function sisCloseAgentShiftAfterApproval($conn, $agent_id, $assignment_date = null, $logout_time = null) {
        $logout_time = $logout_time ?: date('Y-m-d H:i:s');
        $assignment_date = $assignment_date ?: date('Y-m-d');
        $ok = endAgentShift($conn, (int)$agent_id, $assignment_date, $logout_time);
        return $ok;
    }
}



if (!function_exists('sisEnsureNotificationTables')) {
    function sisEnsureNotificationTables($conn) {
        if (!$conn) return false;
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS user_notifications (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NULL,
            role_scope VARCHAR(50) NULL,
            outlet_id INT NULL,
            title VARCHAR(180) NOT NULL,
            message TEXT NULL,
            url VARCHAR(255) NULL,
            related_type VARCHAR(60) NULL,
            related_id INT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_notifications_user (user_id, is_read, created_at),
            KEY idx_user_notifications_role (role_scope, outlet_id, is_read, created_at),
            KEY idx_user_notifications_related (related_type, related_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (tableExists($conn, 'user_notifications')) {
            $columns = [
                'user_id' => "ALTER TABLE user_notifications ADD COLUMN user_id INT NULL AFTER id",
                'role_scope' => "ALTER TABLE user_notifications ADD COLUMN role_scope VARCHAR(50) NULL AFTER user_id",
                'outlet_id' => "ALTER TABLE user_notifications ADD COLUMN outlet_id INT NULL AFTER role_scope",
                'title' => "ALTER TABLE user_notifications ADD COLUMN title VARCHAR(180) NOT NULL DEFAULT 'Notification' AFTER outlet_id",
                'message' => "ALTER TABLE user_notifications ADD COLUMN message TEXT NULL AFTER title",
                'url' => "ALTER TABLE user_notifications ADD COLUMN url VARCHAR(255) NULL AFTER message",
                'related_type' => "ALTER TABLE user_notifications ADD COLUMN related_type VARCHAR(60) NULL AFTER url",
                'related_id' => "ALTER TABLE user_notifications ADD COLUMN related_id INT NULL AFTER related_type",
                'is_read' => "ALTER TABLE user_notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER related_id",
                'created_at' => "ALTER TABLE user_notifications ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_read",
            ];
            foreach ($columns as $column => $sql) {
                if (!tableColumnExists($conn, 'user_notifications', $column)) @mysqli_query($conn, $sql);
            }
            if (function_exists('indexExists')) {
                if (!indexExists($conn, 'user_notifications', 'idx_user_notifications_user')) @mysqli_query($conn, "ALTER TABLE user_notifications ADD KEY idx_user_notifications_user (user_id, is_read, created_at)");
                if (!indexExists($conn, 'user_notifications', 'idx_user_notifications_role')) @mysqli_query($conn, "ALTER TABLE user_notifications ADD KEY idx_user_notifications_role (role_scope, outlet_id, is_read, created_at)");
                if (!indexExists($conn, 'user_notifications', 'idx_user_notifications_related')) @mysqli_query($conn, "ALTER TABLE user_notifications ADD KEY idx_user_notifications_related (related_type, related_id)");
            }
        }
        return tableExists($conn, 'user_notifications');
    }
}

if (!function_exists('sisCreateNotification')) {
    function sisCreateNotification($conn, $user_id, $role_scope, $outlet_id, $title, $message = '', $url = '', $related_type = null, $related_id = null) {
        if (!$conn || !sisEnsureNotificationTables($conn)) return false;
        $user_id = normalizeNullableInt($user_id);
        $outlet_id = normalizeNullableInt($outlet_id);
        $role_scope = $role_scope !== null ? strtolower(trim((string)$role_scope)) : null;
        $title = trim((string)$title);
        if ($title === '') $title = 'Notification';
        $message = trim((string)$message);
        $url = trim((string)$url);
        $related_type = $related_type !== null ? trim((string)$related_type) : null;
        $related_id = normalizeNullableInt($related_id);

        $stmt = mysqli_prepare($conn, "INSERT INTO user_notifications (user_id, role_scope, outlet_id, title, message, url, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'isissssi', $user_id, $role_scope, $outlet_id, $title, $message, $url, $related_type, $related_id);
        $ok = mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $ok ? $id : false;
    }
}

if (!function_exists('sisNotificationCountForCurrentUser')) {
    function sisNotificationCountForCurrentUser($conn) {
        if (!$conn || empty($_SESSION['user_id']) || !sisEnsureNotificationTables($conn)) return 0;
        $user_id = (int)$_SESSION['user_id'];
        $role = strtolower((string)($_SESSION['role'] ?? ''));
        if ($role === 'super_admin') $role = 'admin';
        $outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);
        $sql = "SELECT COUNT(*) AS c FROM user_notifications WHERE is_read = 0 AND (user_id = ?";
        $params = [$user_id];
        $types = 'i';
        if ($role !== '') {
            if ($outlet_id !== null) {
                $sql .= " OR (role_scope = ? AND (outlet_id IS NULL OR outlet_id = ?))";
                $params[] = $role; $params[] = $outlet_id; $types .= 'si';
            } else {
                $sql .= " OR (role_scope = ? AND outlet_id IS NULL)";
                $params[] = $role; $types .= 's';
            }
        }
        $sql .= ")";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return 0;
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('sisGetNotificationsForCurrentUser')) {
    function sisGetNotificationsForCurrentUser($conn, $limit = 8) {
        if (!$conn || empty($_SESSION['user_id']) || !sisEnsureNotificationTables($conn)) return [];
        $user_id = (int)$_SESSION['user_id'];
        $role = strtolower((string)($_SESSION['role'] ?? ''));
        if ($role === 'super_admin') $role = 'admin';
        $outlet_id = normalizeNullableInt($_SESSION['outlet_id'] ?? null);
        $limit = max(1, min(20, (int)$limit));
        $sql = "SELECT * FROM user_notifications WHERE (user_id = ?";
        $params = [$user_id];
        $types = 'i';
        if ($role !== '') {
            if ($outlet_id !== null) {
                $sql .= " OR (role_scope = ? AND (outlet_id IS NULL OR outlet_id = ?))";
                $params[] = $role; $params[] = $outlet_id; $types .= 'si';
            } else {
                $sql .= " OR (role_scope = ? AND outlet_id IS NULL)";
                $params[] = $role; $types .= 's';
            }
        }
        $sql .= ") ORDER BY is_read ASC, created_at DESC, id DESC LIMIT ?";
        $params[] = $limit; $types .= 'i';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) $rows[] = $row;
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('sisMarkNotificationRead')) {
    function sisMarkNotificationRead($conn, $notification_id) {
        if (!$conn || !sisEnsureNotificationTables($conn)) return false;
        $notification_id = (int)$notification_id;
        $stmt = mysqli_prepare($conn, "UPDATE user_notifications SET is_read = 1 WHERE id = ?");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'i', $notification_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('sisFindApprovedLoginAccess')) {
    function sisFindApprovedLoginAccess($conn, $agent_id, $assignment_id = null) {
        if (!$conn || !tableExists($conn, 'shift_requests')) return null;
        $agent_id = (int)$agent_id;
        $assignment_id = normalizeNullableInt($assignment_id);
        if ($assignment_id !== null) {
            $stmt = mysqli_prepare($conn, "SELECT * FROM shift_requests WHERE agent_id = ? AND request_type = 'login_access' AND status = 'approved' AND used_at IS NULL AND (assignment_id = ? OR assignment_id IS NULL) ORDER BY decided_at DESC, id DESC LIMIT 1");
            if (!$stmt) return null;
            mysqli_stmt_bind_param($stmt, 'ii', $agent_id, $assignment_id);
        } else {
            $stmt = mysqli_prepare($conn, "SELECT * FROM shift_requests WHERE agent_id = ? AND request_type = 'login_access' AND status = 'approved' AND used_at IS NULL AND requested_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY decided_at DESC, id DESC LIMIT 1");
            if (!$stmt) return null;
            mysqli_stmt_bind_param($stmt, 'i', $agent_id);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('sisMarkLoginAccessUsed')) {
    function sisMarkLoginAccessUsed($conn, $request_id) {
        if (!$conn || !tableExists($conn, 'shift_requests')) return false;
        $request_id = (int)$request_id;
        $stmt = mysqli_prepare($conn, "UPDATE shift_requests SET used_at = NOW() WHERE id = ? AND request_type = 'login_access'");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'i', $request_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('sisMaybeRequireSupervisorLoginApproval')) {
    function sisMaybeRequireSupervisorLoginApproval($conn, $agent_id, $outlet_id, $assignment = null, $reason = '') {
        sisEnsureAdvancedShiftTables($conn);
        $agent_id = (int)$agent_id;
        $outlet_id = normalizeNullableInt($outlet_id);
        $assignment_id = is_array($assignment) ? normalizeNullableInt($assignment['id'] ?? null) : null;
        $reason = trim((string)$reason);

        $needsApproval = false;
        if (is_array($assignment)) {
            [$allowed, $msg] = sisCanAgentLoginForShift($assignment);
            if (!$allowed) {
                $needsApproval = true;
                if ($reason === '') $reason = $msg;
            }
        }

        $assignmentDate = is_array($assignment) && sisValidDate($assignment['shift_date'] ?? '')
            ? $assignment['shift_date'] : date('Y-m-d');

        // If an agent has already ended the relevant shift, do not silently restart it.
        if (!$needsApproval && tableExists($conn, 'agent_shifts')) {
            $stmt = mysqli_prepare($conn, "SELECT id, logout_time FROM agent_shifts WHERE agent_id = ? AND shift_date = ? AND logout_time IS NOT NULL ORDER BY logout_time DESC, id DESC LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $agent_id, $assignmentDate);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                if ($row = ($res ? mysqli_fetch_assoc($res) : null)) {
                    // If there is no current active shift and the old shift has ended, approval is required.
                    $active = function_exists('getAgentActiveShift') ? getAgentActiveShift($conn, $agent_id, $assignmentDate) : null;
                    if (!$active) {
                        $needsApproval = true;
                        if ($reason === '') $reason = 'Agent already ended the current shift and is requesting to login again.';
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }

        if (!$needsApproval && !is_array($assignment) && sisAgentUsesManagedSchedule($conn, $agent_id)) {
            $needsApproval = true;
            if ($reason === '') $reason = 'No shift is allocated for the current work period.';
        }

        if (!$needsApproval) return [true, 'Allowed'];

        $approved = sisFindApprovedLoginAccess($conn, $agent_id, $assignment_id);
        if ($approved) {
            sisMarkLoginAccessUsed($conn, (int)$approved['id']);
            return [true, 'Supervisor approved login access'];
        }

        // Avoid creating duplicate pending requests every time the agent retries login.
        $pending = null;
        if (tableExists($conn, 'shift_requests')) {
            if ($assignment_id !== null) {
                $stmt = mysqli_prepare($conn, "SELECT id FROM shift_requests WHERE agent_id = ? AND request_type = 'login_access' AND status = 'pending' AND (assignment_id = ? OR assignment_id IS NULL) ORDER BY id DESC LIMIT 1");
                if ($stmt) mysqli_stmt_bind_param($stmt, 'ii', $agent_id, $assignment_id);
            } else {
                $stmt = mysqli_prepare($conn, "SELECT id FROM shift_requests WHERE agent_id = ? AND request_type = 'login_access' AND status = 'pending' AND requested_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR) ORDER BY id DESC LIMIT 1");
                if ($stmt) mysqli_stmt_bind_param($stmt, 'i', $agent_id);
            }
            if (!empty($stmt)) {
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $pending = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
            }
        }

        if (!$pending) {
            $reqId = sisCreateShiftRequest($conn, $assignment_id, $agent_id, 'login_access', $reason ?: 'Agent requested login access outside the normal shift rule.', 'pending', 0);
            $agent_name = 'Agent';
            $agentStmt = mysqli_prepare($conn, "SELECT full_name, username FROM users WHERE id = ? LIMIT 1");
            if ($agentStmt) {
                mysqli_stmt_bind_param($agentStmt, 'i', $agent_id);
                mysqli_stmt_execute($agentStmt);
                $agentRes = mysqli_stmt_get_result($agentStmt);
                if ($agentRow = ($agentRes ? mysqli_fetch_assoc($agentRes) : null)) $agent_name = $agentRow['full_name'] ?: $agentRow['username'];
                mysqli_stmt_close($agentStmt);
            }
            $msg = $agent_name . ' is requesting login access. Reason: ' . ($reason ?: 'Outside allowed shift access.');
            sisCreateNotification($conn, null, 'supervisor', $outlet_id, 'Agent login access request', $msg, 'supervisor/shift_requests.php', 'shift_request', $reqId ?: null);
            sisCreateNotification($conn, null, 'admin', null, 'Agent login access request', $msg, 'admin/shift_requests.php', 'shift_request', $reqId ?: null);
        }
        return [false, 'Your login request has been sent to your Supervisor/Admin. Please wait for approval before logging in again.'];
    }
}

?>