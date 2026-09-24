<?php
// support_helpers.php - Dynamic Complaint / Support helpers for SIS.
// Main Reason + Sub Reason now live in Complaint / Support only, not Add Customer / Sale.

if (!function_exists('supportSlug')) {
    function supportSlug($label) {
        $slug = strtolower(trim((string)$label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        if ($slug === '') $slug = 'field_' . time();
        return substr($slug, 0, 70);
    }
}

if (!function_exists('supportAllowedFieldTypes')) {
    function supportAllowedFieldTypes() {
        return ['text', 'textarea', 'select', 'number', 'date', 'phone', 'email'];
    }
}

if (!function_exists('supportEnsureTables')) {
    function supportEnsureTables($conn) {
        if (!$conn) return false;

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS complaint_reasons (
            id INT NOT NULL AUTO_INCREMENT,
            reason VARCHAR(150) NOT NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'General',
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_complaint_reason (reason),
            KEY idx_complaint_reasons_active (is_active, sort_order),
            KEY idx_complaint_reasons_category (category, is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS support_form_fields (
            id INT NOT NULL AUTO_INCREMENT,
            field_label VARCHAR(150) NOT NULL,
            field_key VARCHAR(80) NOT NULL,
            field_type ENUM('text','textarea','select','number','date','phone','email') NOT NULL DEFAULT 'text',
            field_options TEXT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_support_field_key (field_key),
            KEY idx_support_fields_active (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS support_tickets (
            id INT NOT NULL AUTO_INCREMENT,
            ticket_no VARCHAR(40) NOT NULL,
            submitted_by INT NULL,
            submitted_role VARCHAR(30) NULL,
            outlet_id INT NULL,
            customer_name VARCHAR(120) NULL,
            customer_phone VARCHAR(30) NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'Complaint',
            priority ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
            status ENUM('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
            main_reason VARCHAR(100) NULL,
            sub_reason_id INT NULL,
            subject VARCHAR(180) NULL,
            description TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_support_ticket_no (ticket_no),
            KEY idx_support_date_status (created_at, status),
            KEY idx_support_user (submitted_by, submitted_role),
            KEY idx_support_outlet (outlet_id),
            KEY idx_support_reason (main_reason, sub_reason_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (function_exists('tableColumnExists')) {
            if (!tableColumnExists($conn, 'support_tickets', 'main_reason')) {
                @mysqli_query($conn, "ALTER TABLE support_tickets ADD COLUMN main_reason VARCHAR(100) NULL AFTER status");
            }
            if (!tableColumnExists($conn, 'support_tickets', 'sub_reason_id')) {
                @mysqli_query($conn, "ALTER TABLE support_tickets ADD COLUMN sub_reason_id INT NULL AFTER main_reason");
            }
        }
        if (function_exists('indexExists') && !indexExists($conn, 'support_tickets', 'idx_support_reason')) {
            @mysqli_query($conn, "ALTER TABLE support_tickets ADD KEY idx_support_reason (main_reason, sub_reason_id)");
        }

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS support_ticket_values (
            id INT NOT NULL AUTO_INCREMENT,
            ticket_id INT NOT NULL,
            field_id INT NOT NULL,
            field_label VARCHAR(150) NOT NULL,
            field_value TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_support_values_ticket (ticket_id),
            KEY idx_support_values_field (field_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (function_exists('constraintExists')) {
            if (!constraintExists($conn, 'fk_support_values_ticket')) {
                @mysqli_query($conn, "ALTER TABLE support_ticket_values ADD CONSTRAINT fk_support_values_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE");
            }
            if (!constraintExists($conn, 'fk_support_values_field')) {
                @mysqli_query($conn, "ALTER TABLE support_ticket_values ADD CONSTRAINT fk_support_values_field FOREIGN KEY (field_id) REFERENCES support_form_fields(id) ON DELETE CASCADE");
            }
        }

        $defaultReasons = [
            ['Network', 'No Service', 10], ['Network', 'Poor Signal', 20], ['Network', 'Data Not Working', 30],
            ['Billing', 'Wrong Deduction', 40], ['Billing', 'Bundle Not Received', 50],
            ['SIM / Registration', 'SIM Swap Issue', 60], ['SIM / Registration', 'Registration Update', 70],
            ['ComCach', 'Cash-in Issue', 80], ['ComCach', 'Withdrawal Issue', 90],
            ['Customer Care', 'Follow-up Required', 100], ['Customer Care', 'Other', 110],
        ];
        $stmtReason = mysqli_prepare($conn, "INSERT IGNORE INTO complaint_reasons (category, reason, sort_order, is_active) VALUES (?, ?, ?, 1)");
        if ($stmtReason) {
            foreach ($defaultReasons as $r) {
                mysqli_stmt_bind_param($stmtReason, 'ssi', $r[0], $r[1], $r[2]);
                mysqli_stmt_execute($stmtReason);
            }
            mysqli_stmt_close($stmtReason);
        }

        $defaults = [
            ['MSISDN / Customer Number', 'msisdn_customer_number', 'phone', '', 0, 1, 20],
            ['Problem Details', 'problem_details', 'textarea', '', 1, 1, 30],
            ['Action Taken', 'action_taken', 'textarea', '', 0, 1, 40],
            ['Follow-up Required', 'follow_up_required', 'select', "No\nYes", 0, 1, 50],
        ];
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO support_form_fields (field_label, field_key, field_type, field_options, is_required, is_active, sort_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            foreach ($defaults as $d) {
                mysqli_stmt_bind_param($stmt, 'ssssiiii', $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $uid);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
        return true;
    }
}

if (!function_exists('supportGetFields')) {
    function supportGetFields($conn, $activeOnly = true) {
        supportEnsureTables($conn);
        $sql = "SELECT * FROM support_form_fields" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY sort_order, id";
        $res = mysqli_query($conn, $sql);
        $fields = [];
        if ($res) while ($row = mysqli_fetch_assoc($res)) $fields[] = $row;
        return $fields;
    }
}

if (!function_exists('supportNextTicketNo')) {
    function supportNextTicketNo($conn) {
        return 'SUP-' . date('Ymd-His') . '-' . random_int(100, 999);
    }
}

if (!function_exists('supportNormalizePriority')) {
    function supportNormalizePriority($priority) {
        $priority = trim((string)$priority);
        return in_array($priority, ['Low','Normal','High','Urgent'], true) ? $priority : 'Normal';
    }
}

if (!function_exists('supportNormalizeStatus')) {
    function supportNormalizeStatus($status) {
        $status = trim((string)$status);
        return in_array($status, ['Open','In Progress','Resolved','Closed'], true) ? $status : 'Open';
    }
}

if (!function_exists('supportGetReasonTree')) {
    function supportGetReasonTree($conn) {
        supportEnsureTables($conn);
        $tree = [];
        if (!tableExists($conn, 'complaint_reasons')) return $tree;
        $res = mysqli_query($conn, "SELECT id, category, reason, description FROM complaint_reasons WHERE is_active = 1 ORDER BY category, sort_order, reason");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $main = trim((string)($row['category'] ?: 'General'));
                if (!isset($tree[$main])) $tree[$main] = [];
                $tree[$main][] = $row;
            }
        }
        return $tree;
    }
}

if (!function_exists('supportGetReasonLabel')) {
    function supportGetReasonLabel($conn, $sub_reason_id) {
        $id = (int)$sub_reason_id;
        if ($id <= 0 || !tableExists($conn, 'complaint_reasons')) return '';
        $stmt = mysqli_prepare($conn, 'SELECT reason FROM complaint_reasons WHERE id = ? LIMIT 1');
        if (!$stmt) return '';
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ? (string)$row['reason'] : '';
    }
}

if (!function_exists('supportRenderReasonDropdowns')) {
    function supportRenderReasonDropdowns($conn) {
        $tree = supportGetReasonTree($conn);
        if (!$tree) return;
        $map = [];
        foreach ($tree as $main => $items) {
            $map[$main] = array_map(function($r) {
                return ['id' => (int)$r['id'], 'reason' => (string)$r['reason']];
            }, $items);
        }
        $json = json_encode($map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo '<div class="sis-col-6"><label class="form-label">Main Reason <span class="text-danger">*</span></label><select class="form-select" name="main_reason" id="supportMainReason" required><option value="">Select main reason...</option>';
        foreach (array_keys($tree) as $main) echo '<option value="' . e($main) . '">' . e($main) . '</option>';
        echo '</select></div>';
        echo '<div class="sis-col-6"><label class="form-label">Sub Reason <span class="text-danger">*</span></label><select class="form-select" name="sub_reason_id" id="supportSubReason" required><option value="">Select sub reason...</option></select></div>';
        echo '<script>window.SIS_SUPPORT_REASONS=' . $json . ';document.addEventListener("DOMContentLoaded",function(){var main=document.getElementById("supportMainReason"),sub=document.getElementById("supportSubReason");function refresh(){if(!main||!sub)return;var rows=(window.SIS_SUPPORT_REASONS||{})[main.value]||[];sub.innerHTML="<option value=\"\">Select sub reason...</option>";rows.forEach(function(r){var o=document.createElement("option");o.value=r.id;o.textContent=r.reason;sub.appendChild(o);});}if(main){main.addEventListener("change",refresh);refresh();}});</script>';
    }
}

if (!function_exists('supportRenderDynamicFields')) {
    function supportRenderDynamicFields($conn) {
        $fields = supportGetFields($conn, true);
        if (!$fields) return;
        foreach ($fields as $field) {
            $id = (int)$field['id'];
            $label = e($field['field_label']);
            $required = ((int)$field['is_required'] === 1) ? 'required' : '';
            $requiredMark = ((int)$field['is_required'] === 1) ? ' <span class="text-danger">*</span>' : '';
            $name = 'field_' . $id;
            $type = $field['field_type'];
            echo '<div class="sis-col-6">';
            echo '<label class="form-label">' . $label . $requiredMark . '</label>';
            if ($type === 'textarea') {
                echo '<textarea class="form-control" name="' . e($name) . '" rows="3" ' . $required . '></textarea>';
            } elseif ($type === 'select') {
                echo '<select class="form-select" name="' . e($name) . '" ' . $required . '><option value="">Select...</option>';
                $options = preg_split('/\r\n|\r|\n|,/', (string)$field['field_options']);
                foreach ($options as $opt) {
                    $opt = trim($opt);
                    if ($opt === '') continue;
                    echo '<option value="' . e($opt) . '">' . e($opt) . '</option>';
                }
                echo '</select>';
            } else {
                $inputType = 'text';
                if ($type === 'number') $inputType = 'number';
                if ($type === 'date') $inputType = 'date';
                if ($type === 'email') $inputType = 'email';
                if ($type === 'phone') $inputType = 'tel';
                echo '<input type="' . e($inputType) . '" class="form-control" name="' . e($name) . '" ' . $required . '>';
            }
            echo '</div>';
        }
    }
}

if (!function_exists('supportSaveTicket')) {
    function supportSaveTicket($conn, array $data, array $post) {
        supportEnsureTables($conn);
        $ticket_no = supportNextTicketNo($conn);
        $submitted_by = normalizeNullableInt($data['submitted_by'] ?? ($_SESSION['user_id'] ?? null));
        $submitted_role = (string)($data['submitted_role'] ?? ($_SESSION['role'] ?? ''));
        $outlet_id = normalizeNullableInt($data['outlet_id'] ?? ($_SESSION['outlet_id'] ?? null));
        $customer_name = trim((string)($data['customer_name'] ?? ''));
        $customer_phone = trim((string)($data['customer_phone'] ?? ''));
        $category = trim((string)($data['category'] ?? 'Complaint')) ?: 'Complaint';
        $priority = supportNormalizePriority($data['priority'] ?? 'Normal');
        $status = 'Open';
        $main_reason = trim((string)($data['main_reason'] ?? ($post['main_reason'] ?? '')));
        $sub_reason_id = normalizeNullableInt($data['sub_reason_id'] ?? ($post['sub_reason_id'] ?? null));
        $subject = trim((string)($data['subject'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));

        if ($subject === '' && $sub_reason_id) $subject = supportGetReasonLabel($conn, $sub_reason_id);
        if ($subject === '') $subject = $main_reason ?: 'Complaint / Support Request';

        $stmt = mysqli_prepare($conn, "INSERT INTO support_tickets (ticket_no, submitted_by, submitted_role, outlet_id, customer_name, customer_phone, category, priority, status, main_reason, sub_reason_id, subject, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return [false, sisDbErrorMessage($conn), null];
        mysqli_stmt_bind_param($stmt, 'sisissssssiss', $ticket_no, $submitted_by, $submitted_role, $outlet_id, $customer_name, $customer_phone, $category, $priority, $status, $main_reason, $sub_reason_id, $subject, $description);
        if (!mysqli_stmt_execute($stmt)) {
            $err = sisDbErrorMessage($conn, 'support ticket');
            mysqli_stmt_close($stmt);
            return [false, $err, null];
        }
        $ticket_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $fields = supportGetFields($conn, true);
        $valueStmt = mysqli_prepare($conn, "INSERT INTO support_ticket_values (ticket_id, field_id, field_label, field_value) VALUES (?, ?, ?, ?)");
        if ($valueStmt) {
            foreach ($fields as $field) {
                $field_id = (int)$field['id'];
                $name = 'field_' . $field_id;
                $value = trim((string)($post[$name] ?? ''));
                $label = (string)$field['field_label'];
                mysqli_stmt_bind_param($valueStmt, 'iiss', $ticket_id, $field_id, $label, $value);
                mysqli_stmt_execute($valueStmt);
            }
            mysqli_stmt_close($valueStmt);
        }

        if (function_exists('auditAction')) auditAction($conn, 'ADD_SUPPORT_TICKET', $ticket_id, $ticket_no);
        elseif (function_exists('logAction')) logAction($conn, 'ADD_SUPPORT_TICKET', $ticket_id);
        return [true, $ticket_no, $ticket_id];
    }
}

if (!function_exists('supportGetTicketValuesMap')) {
    function supportGetTicketValuesMap($conn, array $ticketIds) {
        $map = [];
        $ticketIds = array_values(array_filter(array_map('intval', $ticketIds)));
        if (!$ticketIds) return $map;
        $ids = implode(',', $ticketIds);
        $res = mysqli_query($conn, "SELECT ticket_id, field_label, field_value FROM support_ticket_values WHERE ticket_id IN ($ids) ORDER BY id");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $tid = (int)$row['ticket_id'];
                if (!isset($map[$tid])) $map[$tid] = [];
                $map[$tid][] = ['label' => $row['field_label'], 'value' => $row['field_value']];
            }
        }
        return $map;
    }
}
?>
