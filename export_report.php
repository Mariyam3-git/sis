<?php
require_once 'config.php';
require_once __DIR__ . '/include/reporting_helpers.php';
requireRole('admin');

$type = $_GET['type'] ?? 'sales';
if (in_array($type, ['logs', 'audit_logs', 'super_audit_logs'], true)) {
    requireSuperAdmin();
}
$filename = 'sis_export_' . preg_replace('/[^a-z0-9_\-]/i', '_', $type) . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'sales') {
    [$date_from, $date_to] = sisDateRange($_GET['from'] ?? null, $_GET['to'] ?? null, date('Y-m-01'), date('Y-m-d'));
    $agent = isset($_GET['agent']) && $_GET['agent'] !== '' ? (int)$_GET['agent'] : 0;
    $outlet = isset($_GET['outlet']) && $_GET['outlet'] !== '' ? (int)$_GET['outlet'] : 0;
    $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, (string)($_GET['status'] ?? '')) : '';
    $shift = isset($_GET['shift']) ? mysqli_real_escape_string($conn, (string)($_GET['shift'] ?? '')) : '';

    $where = [sisDateWhere($conn, 's.payment_date', $date_from, $date_to)];
    if ($agent > 0) $where[] = "s.agent_id = $agent";
    if ($outlet > 0) $where[] = "s.outlet_id = $outlet";
    if ($status !== '') $where[] = "s.payment_status = '$status'";
    if ($shift !== '') $where[] = "LOWER(COALESCE(a.shift_type, u.shift, '')) = " . sisSqlValue($conn, strtolower($shift));
    $where_sql = implode(' AND ', $where);
    $salesHandleExpr = tableColumnExists($conn, 'sales', 'handle_seconds') ? 's.handle_seconds' : 'NULL';
    $visitorHandleExpr = tableColumnExists($conn, 'visitors', 'handling_seconds') ? 'v.handling_seconds' : 'NULL';
    $handleExpr = ($salesHandleExpr !== 'NULL' || $visitorHandleExpr !== 'NULL') ? "COALESCE($salesHandleExpr, $visitorHandleExpr)" : 'NULL';
    $handleStartExpr = tableColumnExists($conn, 'sales', 'handle_started_at') ? 's.handle_started_at' : 'NULL';
    $handleEndExpr = tableColumnExists($conn, 'sales', 'handle_completed_at') ? 's.handle_completed_at' : 'NULL';
    $shiftJoin = sisLatestAgentShiftJoin('s', 'a', $conn);

    fputcsv($output, ['SIS Sales / Transactions Report']);
    fputcsv($output, ['Period', $date_from . ' to ' . $date_to]);
    fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Sale ID', 'Date', 'Invoice', 'Customer GSM', 'Customer', 'Purpose', 'Service', 'Outlet', 'Region', 'Staff', 'Shift', 'Amount', 'Payment Status', 'Handle Started At', 'Handle Completed At', 'Handle Time', 'Handle Seconds']);

    $sql = "SELECT s.id, s.payment_date, s.invoice_number, s.amount, s.payment_status,
                   $handleStartExpr AS handle_started_at, $handleEndExpr AS handle_completed_at,
                   $handleExpr AS handle_seconds,
                   v.contact, v.name AS customer, p.name AS purpose, sv.name AS service,
                   o.name AS outlet, o.region, u.full_name AS staff, COALESCE(a.shift_type, u.shift) AS shift
            FROM sales s
            LEFT JOIN visitors v ON s.visitor_id = v.id
            LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
            LEFT JOIN services sv ON v.service_id = sv.id
            LEFT JOIN outlets o ON s.outlet_id = o.id
            LEFT JOIN users u ON s.agent_id = u.id
            $shiftJoin
            WHERE $where_sql
            ORDER BY s.payment_date DESC, s.id DESC";
    $rows = mysqli_query($conn, $sql);
    if ($rows) {
        while ($row = mysqli_fetch_assoc($rows)) {
            fputcsv($output, [
                $row['id'],
                $row['payment_date'],
                $row['invoice_number'],
                $row['contact'],
                $row['customer'],
                $row['purpose'],
                $row['service'],
                $row['outlet'],
                $row['region'],
                $row['staff'],
                $row['shift'],
                number_format((float)$row['amount'], 2, '.', ''),
                $row['payment_status'],
                $row['handle_started_at'] ?? '',
                $row['handle_completed_at'] ?? '',
                $row['handle_seconds'] !== null ? formatSecondsDuration($row['handle_seconds']) : '',
                $row['handle_seconds'] !== null ? (int)$row['handle_seconds'] : '',
            ]);
        }
    }
} elseif ($type === 'logs') {
    $where = [];
    $search = $_GET['search'] ?? '';
    $action = $_GET['action_type'] ?? '';
    $user = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : 0;
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    if ($search !== '') {
        $q = mysqli_real_escape_string($conn, $search);
        $where[] = "(a.action_type LIKE '%$q%' OR u.full_name LIKE '%$q%' OR a.ip_address LIKE '%$q%')";
    }
    if ($action !== '') $where[] = "a.action_type = '" . mysqli_real_escape_string($conn, $action) . "'";
    if ($user > 0) $where[] = "a.user_id = $user";
    if ($date_from !== '') $where[] = "DATE(a.timestamp) >= '" . mysqli_real_escape_string($conn, $date_from) . "'";
    if ($date_to !== '') $where[] = "DATE(a.timestamp) <= '" . mysqli_real_escape_string($conn, $date_to) . "'";
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    fputcsv($output, ['SIS Audit Log Export']);
    fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Date/Time', 'User', 'Role', 'Action', 'Record ID', 'IP Address']);
    $rows = mysqli_query($conn, "SELECT a.*, u.full_name, u.role FROM audit_log a LEFT JOIN users u ON a.user_id = u.id $where_sql ORDER BY a.timestamp DESC LIMIT 5000");
    if ($rows) {
        while ($row = mysqli_fetch_assoc($rows)) {
            fputcsv($output, [$row['timestamp'], $row['full_name'], $row['role'], $row['action_type'], $row['record_id'], $row['ip_address']]);
        }
    }
} else {
    fputcsv($output, ['Unsupported export type', $type]);
}

fclose($output);
exit();
