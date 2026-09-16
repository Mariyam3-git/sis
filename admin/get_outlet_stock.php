<?php
require_once '../config.php';
requireRole('admin');

header('Content-Type: application/json');

if (!hasRole('admin')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$outlet_id = isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : 0;

if (!$product_id || !$outlet_id) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

// Get available stock for this product in the selected outlet
$query = "
    SELECT SUM(quantity) as total
    FROM outlet_stock
    WHERE product_id = $product_id AND outlet_id = $outlet_id
";

$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

echo json_encode([
    'success' => true,
    'quantity' => (int)($data['total'] ?? 0)
]);
?>