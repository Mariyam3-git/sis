<?php
require_once '../config.php';
requireRole('admin');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid visitor ID.']);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT v.id, v.name, v.contact, v.purpose_id, v.service_id, v.visit_time, o.name AS outlet_name FROM visitors v LEFT JOIN outlets o ON v.outlet_id = o.id WHERE v.id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$visitor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$visitor) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Visitor not found.']);
    exit;
}

$transactions = [];
$stmt = mysqli_prepare($conn, 'SELECT id, payment_date, invoice_number, amount, payment_status FROM sales WHERE visitor_id = ? ORDER BY payment_date DESC, id DESC LIMIT 200');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($result && ($row = mysqli_fetch_assoc($result))) {
    $row['id'] = (int)$row['id'];
    $row['amount'] = (float)$row['amount'];
    $transactions[] = $row;
}
echo json_encode(['success' => true, 'visitor' => $visitor, 'transactions' => $transactions], JSON_UNESCAPED_UNICODE);
