<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('supervisor');

$outletId = (int)($_SESSION['outlet_id'] ?? 0);
if ($outletId <= 0) {
    http_response_code(403);
    exit('No outlet is assigned to this supervisor.');
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to = $_GET['to'] ?? date('Y-m-d');
$fromDate = DateTime::createFromFormat('Y-m-d', $from);
$toDate = DateTime::createFromFormat('Y-m-d', $to);
if (!$fromDate || $fromDate->format('Y-m-d') !== $from || !$toDate || $toDate->format('Y-m-d') !== $to || $from > $to) {
    http_response_code(400);
    exit('Invalid report date range.');
}

$salesHandleExpr = tableColumnExists($conn, 'sales', 'handle_seconds') ? 's.handle_seconds' : 'NULL';
$visitorHandleExpr = tableColumnExists($conn, 'visitors', 'handling_seconds') ? 'v.handling_seconds' : 'NULL';
$handleExpr = ($salesHandleExpr !== 'NULL' || $visitorHandleExpr !== 'NULL') ? "COALESCE($salesHandleExpr, $visitorHandleExpr)" : 'NULL';
$shiftJoin = sisLatestAgentShiftJoin('s', 'a', $conn);
$stmt = mysqli_prepare($conn, "SELECT s.id, s.payment_date, s.invoice_number, v.contact, v.name AS customer,
    p.name AS purpose, sv.name AS service, u.full_name AS staff, COALESCE(a.shift_type, u.shift) AS shift, s.amount, s.payment_status,
    $handleExpr AS handle_seconds
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN users u ON s.agent_id = u.id
    $shiftJoin
    WHERE s.outlet_id = ? AND DATE(s.payment_date) BETWEEN ? AND ?
    ORDER BY s.payment_date DESC, s.id DESC");
if (!$stmt) {
    http_response_code(500);
    exit('Unable to prepare the report.');
}
mysqli_stmt_bind_param($stmt, 'iss', $outletId, $from, $to);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt);

$filename = 'outlet_report_' . $from . '_to_' . $to . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($output, ['Outlet Sales Report']);
fputcsv($output, ['Period', $from . ' to ' . $to]);
fputcsv($output, []);
fputcsv($output, ['Sale ID', 'Date', 'Invoice', 'Customer GSM', 'Customer', 'Purpose', 'Service', 'Staff', 'Shift', 'Amount', 'Payment Status', 'Handle Time']);
while ($rows && ($row = mysqli_fetch_assoc($rows))) {
    fputcsv($output, [
        $row['id'], $row['payment_date'], $row['invoice_number'], $row['contact'], $row['customer'],
        $row['purpose'], $row['service'], $row['staff'], $row['shift'],
        number_format((float)$row['amount'], 2, '.', ''), $row['payment_status'],
        $row['handle_seconds'] !== null ? formatSecondsDuration($row['handle_seconds']) : '',
    ]);
}
fclose($output);
exit;
