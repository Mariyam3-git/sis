<?php
// agent/cancelled_sales.php
// Read-only audit page showing all sales cancelled by this agent.
// Uses the [CANCELLED by ...] marker in visitors.notes.

require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('agent');

if (!hasRole('agent')) {
    redirectTo(appPath('dashboard.php'));
}

$agent_id = (int)$_SESSION['user_id'];
$agent_name = $_SESSION['full_name'] ?? 'Agent';

// Date range filter (default: last 30 days)
[$date_from, $date_to] = sisDateRange(
    $_GET['date_from'] ?? null,
    $_GET['date_to'] ?? null,
    date('Y-m-d', strtotime('-30 days')),
    date('Y-m-d')
);

// Search filter
$search = trim((string)($_GET['q'] ?? ''));

// Load settings for currency
$settings = [];
$settings_query = mysqli_query($conn, "SELECT * FROM settings");
while ($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currency_symbol = $settings['currency_symbol'] ?? 'D';

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="my_cancelled_sales_' . $date_from . '_to_' . $date_to . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Date Cancelled', 'Original Sale Date', 'Customer GSM', 'Service', 'Purpose', 'Quantity', 'Amount', 'Reason']);

    $export_sql = "SELECT sl.id, sl.payment_date, sl.amount, v.contact AS gsm, v.notes,
                          v.quantity, s.name AS service_name, p.name AS purpose_name
                   FROM sales sl
                   LEFT JOIN visitors v ON sl.visitor_id = v.id
                   LEFT JOIN services s ON v.service_id = s.id
                   LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
                   WHERE sl.agent_id = ?
                     AND (v.notes LIKE '%[CANCELLED by%')
                     AND DATE(sl.payment_date) BETWEEN ? AND ?
                   ORDER BY sl.payment_date DESC";
    $stmt = mysqli_prepare($conn, $export_sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iss', $agent_id, $date_from, $date_to);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            preg_match('/\[CANCELLED by [^\]]*?(?:\| Reason:\s*([^\]]+))?\]/i', (string)$row['notes'], $m);
            $reason = isset($m[1]) ? trim($m[1]) : '';
            fputcsv($output, [
                $row['payment_date'],
                $row['payment_date'],
                $row['gsm'] ?: 'N/A',
                $row['service_name'] ?: 'N/A',
                $row['purpose_name'] ?: 'N/A',
                max(1, (int)$row['quantity']),
                number_format(abs((float)$row['amount']), 2),
                $reason,
            ]);
        }
        mysqli_stmt_close($stmt);
    }
    fclose($output);
    exit;
}

// Build the query
$where = "sl.agent_id = ? AND v.notes LIKE '%[CANCELLED by%' AND DATE(sl.payment_date) BETWEEN ? AND ?";
$params = [$agent_id, $date_from, $date_to];
$types = 'iss';

if ($search !== '') {
    $where .= " AND (v.contact LIKE ? OR s.name LIKE ? OR p.name LIKE ? OR v.notes LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';
}

$sql = "SELECT sl.id AS sale_id, sl.visitor_id, sl.amount, sl.payment_status, sl.payment_date,
               v.contact AS gsm, v.notes AS visitor_notes, v.quantity, v.status AS visitor_status,
               v.visit_time, s.name AS service_name, p.name AS purpose_name
        FROM sales sl
        LEFT JOIN visitors v ON sl.visitor_id = v.id
        LEFT JOIN services s ON v.service_id = s.id
        LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
        WHERE $where
        ORDER BY sl.payment_date DESC
        LIMIT 500";
$stmt = mysqli_prepare($conn, $sql);
$rows = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    mysqli_stmt_close($stmt);
}

// Summary stats
$total_cancelled = count($rows);
$total_amount = 0;
foreach ($rows as $r) {
    $total_amount += abs((float)$r['amount']);
}

// Today/month counts
$today_count = 0;
$today = date('Y-m-d');
foreach ($rows as $r) {
    if (date('Y-m-d', strtotime($r['payment_date'])) === $today) $today_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cancelled Sales - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
    <style>
        .audit-header {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(220,53,69,0.2);
        }
        .audit-header h2 { margin: 0; font-weight: 700; }
        .audit-header p { margin: 5px 0 0; opacity: 0.9; }
        .stat-card-cancel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #dc3545;
        }
        .stat-card-cancel .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #dc3545;
        }
        .stat-card-cancel .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .audit-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .audit-table thead {
            background: #dc3545;
            color: white;
        }
        .audit-table thead th {
            border: none;
            padding: 14px 12px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .audit-table tbody td {
            padding: 12px;
            vertical-align: middle;
        }
        .audit-table tbody tr:hover {
            background: #fff5f5;
        }
        .reason-badge {
            background: #fff3cd;
            color: #856404;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .strikethrough {
            text-decoration: line-through;
            color: #999;
        }
        .empty-audit {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        .empty-audit i {
            font-size: 4rem;
            color: #dee2e6;
        }
        .btn-export-csv {
            background: #198754;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-export-csv:hover { background: #146c43; color: white; }
    </style>
</head>
<body class="sis-app-body">
<?php
require_once dirname(__DIR__) . '/include/side_header.php';
sisRenderLayoutStart('agent', 'My Cancelled Sales', 'Audit trail of every service you have cancelled');
?>

<div class="container mb-4">

    <div class="audit-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h2><i class="bi bi-shield-check"></i> My Cancelled Sales</h2>
                <p>Every service you have cancelled — with reason, date, and full details.</p>
            </div>
            <div>
                <a href="?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&q=<?php echo urlencode($search); ?>&export=csv"
                   class="btn-export-csv text-decoration-none">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row">
        <div class="col-md-4">
            <div class="stat-card-cancel">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Cancelled</div>
                        <div class="stat-value"><?php echo number_format($total_cancelled); ?></div>
                        <small class="text-muted">In selected period</small>
                    </div>
                    <i class="bi bi-x-circle-fill" style="font-size: 2.5rem; color: #dc3545; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-cancel">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Amount</div>
                        <div class="stat-value"><?php echo $currency_symbol; ?> <?php echo number_format($total_amount, 2); ?></div>
                        <small class="text-muted">Value of cancelled services</small>
                    </div>
                    <i class="bi bi-cash-stack" style="font-size: 2.5rem; color: #dc3545; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-cancel">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Cancelled Today</div>
                        <div class="stat-value"><?php echo number_format($today_count); ?></div>
                        <small class="text-muted"><?php echo date('d M Y'); ?></small>
                    </div>
                    <i class="bi bi-calendar-x" style="font-size: 2.5rem; color: #dc3545; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-calendar-range"></i> From</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-calendar-check"></i> To</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><i class="bi bi-search"></i> Search</label>
                <input type="text" class="form-control" name="q" placeholder="GSM, service, purpose, reason..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-danger w-100"><i class="bi bi-funnel"></i> Filter</button>
                <a href="cancelled_sales.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
        <div class="mt-2 small text-muted">
            Quick ranges:
            <a href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>">Today</a> ·
            <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>">This Month</a> ·
            <a href="?date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>">Last 30 Days</a> ·
            <a href="?date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>">This Year</a>
        </div>
    </div>

    <!-- Audit Table -->
    <?php if (empty($rows)): ?>
        <div class="empty-audit">
            <i class="bi bi-shield-check"></i>
            <h4 class="mt-3">No cancelled sales found</h4>
            <p class="text-muted">You have not cancelled any services in the selected period.</p>
            <a href="dashboard.php" class="btn btn-danger mt-2"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
    <?php else: ?>
        <div class="audit-table">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cancelled On</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Amount</th>
                            <th>Reason</th>
                            <th>Original Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($rows as $row):
                            preg_match('/\[CANCELLED by ([^@]+) @ ([^|]+)(?:\| Reason:\s*([^\]]+))?\]/i', (string)$row['visitor_notes'], $m);
                            $cancel_agent = isset($m[1]) ? trim($m[1]) : 'N/A';
                            $cancel_time = isset($m[2]) ? trim($m[2]) : 'N/A';
                            $cancel_reason = isset($m[3]) ? trim($m[3]) : 'No reason given';
                        ?>
                        <tr>
                            <td><span class="text-muted">#<?php echo $i++; ?></span></td>
                            <td>
                                <strong><?php echo e($cancel_time); ?></strong>
                                <small class="d-block text-muted">By: <?php echo e($cancel_agent); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($row['gsm'])): ?>
                                    <strong><i class="bi bi-phone"></i> <?php echo e($row['gsm']); ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">No GSM</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo e($row['service_name'] ?: 'N/A'); ?></strong>
                                <small class="d-block text-muted"><?php echo e($row['purpose_name'] ?: 'N/A'); ?></small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary">x<?php echo max(1, (int)$row['quantity']); ?></span>
                            </td>
                            <td class="text-end">
                                <span class="strikethrough">D <?php echo number_format(abs((float)$row['amount']), 2); ?></span>
                            </td>
                            <td>
                                <span class="reason-badge">
                                    <i class="bi bi-chat-left-text"></i> <?php echo e($cancel_reason); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    Original: <?php echo e(date('d/m/Y H:i', strtotime($row['payment_date']))); ?><br>
                                    Status: <?php echo e($row['payment_status']); ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3 text-muted small">
            <i class="bi bi-info-circle"></i>
            Showing up to 500 cancellations. These records are kept for audit purposes and are visible to Admin and Supervisor for review.
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>