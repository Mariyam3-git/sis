<?php
require_once __DIR__ . '/config.php';
requireLogin();

// Only allow agents and supervisors
if (!hasRole('agent') && !hasRole('supervisor')) {
    http_response_code(403);
    exit('Unauthorized');
}

$purpose_id = isset($_GET['purpose_id']) ? (int)$_GET['purpose_id'] : 0;

// ============================================
// STEP 1: Find your purpose IDs and service IDs
// Run these queries in your database:
// SELECT id, name FROM purpose_of_visit WHERE is_active = 1 ORDER BY name;
// SELECT id, name FROM services WHERE is_active = 1 ORDER BY name;
// ============================================

// ============================================
// STEP 2: Update this mapping with your actual IDs
// Format: purpose_id => [service_id_1, service_id_2, ...]
// ============================================
$purpose_service_map = [
    // ============================================
    // EXAMPLE - REPLACE WITH YOUR ACTUAL IDs
    // ============================================
    // 1 => [1, 2, 3, 4, 5],   // New SIM
    // 2 => [6, 7, 8, 9],      // SIM Renewal
    // 3 => [10, 11, 12],      // eSIM
    // 4 => [13, 14, 15],      // Airtime Top-Up
    // 5 => [16, 17, 18],      // Data Top-Up
    // 6 => [19, 20, 21, 22],  // EVC
    // 7 => [23, 24, 25],      // Complaint
    // 8 => [26, 27, 28, 29],  // ISP
];

// ============================================
// ALTERNATIVE: Pattern-based mapping (auto-detect by name)
// Uncomment and use this instead if you prefer
// ============================================
/*
$purpose_service_map_by_pattern = [
    // Purpose ID => array of patterns to match in service names
    1 => ['New SIM', 'SIM Card', 'SIM Activation', 'SIM Registration', 'Physical SIM'],
    2 => ['Renew', 'Replace', 'Swap', 'Renewal', 'Replacement'],
    3 => ['eSIM', 'E-SIM'],
    4 => ['Airtime', 'Top Up', 'Recharge'],
    5 => ['Data', 'Internet'],
    6 => ['EVC', 'Mobile Money'],
    7 => ['Complaint', 'Support', 'Feedback'],
    8 => ['Router', 'Modem', 'CPE', 'Broadband', 'Installation'],
];
*/

// ============================================
// METHOD 1: Manual mapping (specific service IDs)
// ============================================
if (isset($purpose_service_map[$purpose_id]) && !empty($purpose_service_map[$purpose_id])) {
    $service_ids = implode(',', array_map('intval', $purpose_service_map[$purpose_id]));
    $query = "SELECT id, name FROM services WHERE id IN ($service_ids) AND is_active = 1 ORDER BY name";
    
// ============================================
// METHOD 2: Pattern-based mapping (uncomment if using)
// ============================================
// } elseif (isset($purpose_service_map_by_pattern[$purpose_id]) && !empty($purpose_service_map_by_pattern[$purpose_id])) {
//     $patterns = $purpose_service_map_by_pattern[$purpose_id];
//     $conditions = [];
//     foreach ($patterns as $pattern) {
//         $pattern = mysqli_real_escape_string($conn, $pattern);
//         $conditions[] = "name LIKE '%$pattern%'";
//     }
//     $where = implode(' OR ', $conditions);
//     $query = "SELECT id, name FROM services WHERE ($where) AND is_active = 1 ORDER BY name";
    
} else {
    // No mapping found - show all services (fallback)
    $query = "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name";
}

$result = mysqli_query($conn, $query);
$services = [];
while ($row = mysqli_fetch_assoc($result)) {
    $services[] = $row;
}

header('Content-Type: application/json');
echo json_encode($services);
?>