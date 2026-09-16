<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';

if (!isLoggedIn() || (function_exists('sisSessionExpired') && sisSessionExpired()) || ($_SESSION['role'] ?? '') !== 'agent') {
    if (isLoggedIn() && function_exists('sisSessionExpired') && sisSessionExpired() && function_exists('sisDestroyCurrentSession')) {
        sisDestroyCurrentSession('SESSION_TIMEOUT', 'Automatic logout after inactivity during agent API call');
    }
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$agent_id = (int)($_SESSION['user_id'] ?? 0);
$outlet_id = (int)($_SESSION['outlet_id'] ?? 0);
$action = $_GET['action'] ?? '';

function api_json($payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_date_condition_for_period($period, $column = 's.payment_date') {
    switch ($period) {
        case 'today': return "DATE($column) = CURDATE()";
        case 'week': return "YEARWEEK($column, 1) = YEARWEEK(CURDATE(), 1)";
        case 'year': return "YEAR($column) = YEAR(CURDATE())";
        case 'month':
        default: return "MONTH($column) = MONTH(CURDATE()) AND YEAR($column) = YEAR(CURDATE())";
    }
}

function getDailyTrend($conn, $agent_id) {
    $days = isset($_GET['days']) ? max(1, min(60, (int)$_GET['days'])) : 7;
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $sql = "SELECT DATE(payment_date) as date,
                   COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as total_sales,
                   COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as withdrawals,
                   COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) as transaction_count,
                   COALESCE(AVG(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount END), 0) as avg_sale
            FROM sales
            WHERE agent_id = ? AND DATE(payment_date) BETWEEN ? AND ?
            GROUP BY DATE(payment_date)
            ORDER BY date ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iss', $agent_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'date' => $row['date'],
            'total_sales' => (float)$row['total_sales'],
            'withdrawals' => (float)$row['withdrawals'],
            'net_cash' => (float)$row['total_sales'] - (float)$row['withdrawals'],
            'transactions' => (int)$row['transaction_count'],
            'avg_sale' => (float)$row['avg_sale'],
        ];
    }
    api_json(['success' => true, 'period' => $days . '_days', 'data' => $data]);
}

function getWeeklySummary($conn, $agent_id) {
    [$week_start, $week_end] = sisDateRange($_GET['week_start'] ?? null, date('Y-m-d', strtotime('sunday this week', strtotime($_GET['week_start'] ?? date('Y-m-d')))), date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week')));

    $sql = "SELECT DAYOFWEEK(payment_date) as day_num,
                   DATE_FORMAT(payment_date, '%W') as day_name,
                   DATE(payment_date) as sale_day,
                   COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as total_sales,
                   COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as withdrawals,
                   COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) as transactions
            FROM sales
            WHERE agent_id = ? AND DATE(payment_date) BETWEEN ? AND ?
            GROUP BY DATE(payment_date), DAYOFWEEK(payment_date)
            ORDER BY sale_day ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iss', $agent_id, $week_start, $week_end);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $daily = [];
    $total_sales = 0.0;
    $total_withdrawals = 0.0;
    $total_transactions = 0;
    $best = ['day' => 'N/A', 'sales' => 0.0];
    while ($row = mysqli_fetch_assoc($result)) {
        $sales = (float)$row['total_sales'];
        $withdrawals = (float)$row['withdrawals'];
        $daily[] = [
            'day' => $row['day_name'],
            'date' => $row['sale_day'],
            'sales' => $sales,
            'withdrawals' => $withdrawals,
            'net_cash' => $sales - $withdrawals,
            'transactions' => (int)$row['transactions'],
        ];
        $total_sales += $sales;
        $total_withdrawals += $withdrawals;
        $total_transactions += (int)$row['transactions'];
        if ($sales > $best['sales']) $best = ['day' => $row['day_name'], 'sales' => $sales];
    }

    api_json([
        'success' => true,
        'week_start' => $week_start,
        'week_end' => $week_end,
        'total_sales' => $total_sales,
        'total_withdrawals' => $total_withdrawals,
        'net_cash' => $total_sales - $total_withdrawals,
        'total_transactions' => $total_transactions,
        'daily_breakdown' => $daily,
        'best_day' => $best['day'],
        'best_day_sales' => $best['sales'],
    ]);
}

function getMonthlyPerformance($conn, $agent_id) {
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $year = (int)substr($month, 0, 4);
    $month_num = (int)substr($month, 5, 2);

    $sql = "SELECT WEEK(payment_date, 1) - WEEK(DATE_SUB(payment_date, INTERVAL DAYOFMONTH(payment_date)-1 DAY), 1) + 1 as week_of_month,
                   COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as total_sales,
                   COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as withdrawals,
                   COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) as transactions
            FROM sales
            WHERE agent_id = ? AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?
            GROUP BY WEEK(payment_date, 1)
            ORDER BY week_of_month";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $agent_id, $month_num, $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $weekly = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $weekly[] = [
            'week' => 'Week ' . (int)$row['week_of_month'],
            'sales' => (float)$row['total_sales'],
            'withdrawals' => (float)$row['withdrawals'],
            'transactions' => (int)$row['transactions'],
        ];
    }

    $totalsSql = "SELECT COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) as total_transactions,
                         COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as total_sales,
                         COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as withdrawals,
                         COALESCE(AVG(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount END), 0) as avg_sale,
                         COUNT(DISTINCT DATE(payment_date)) as active_days
                  FROM sales
                  WHERE agent_id = ? AND MONTH(payment_date) = ? AND YEAR(payment_date) = ?";
    $stmt = mysqli_prepare($conn, $totalsSql);
    mysqli_stmt_bind_param($stmt, 'iii', $agent_id, $month_num, $year);
    mysqli_stmt_execute($stmt);
    $totals = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];

    api_json([
        'success' => true,
        'month' => date('F Y', strtotime($month . '-01')),
        'weekly_data' => $weekly,
        'total_sales' => (float)($totals['total_sales'] ?? 0),
        'withdrawals' => (float)($totals['withdrawals'] ?? 0),
        'net_cash' => (float)($totals['total_sales'] ?? 0) - (float)($totals['withdrawals'] ?? 0),
        'total_transactions' => (int)($totals['total_transactions'] ?? 0),
        'avg_sale' => (float)($totals['avg_sale'] ?? 0),
        'active_days' => (int)($totals['active_days'] ?? 0),
    ]);
}

function getServiceBreakdown($conn, $agent_id) {
    $period = $_GET['period'] ?? 'month';
    $date_condition = api_date_condition_for_period($period, 's.payment_date');
    $unitExpr = sisVisitorUnitExpr($conn, 'v');

    $sql = "SELECT COALESCE(sv.id, 0) as id,
                   COALESCE(sv.name, 'Unknown Service') as service_name,
                   COUNT(sa.id) as transaction_count,
                   COALESCE(SUM($unitExpr), 0) as units,
                   COALESCE(SUM(CASE WHEN sa.amount > 0 AND sa.payment_status = 'Paid' THEN sa.amount ELSE 0 END), 0) as total_amount,
                   COALESCE(AVG(CASE WHEN sa.amount > 0 AND sa.payment_status = 'Paid' THEN sa.amount END), 0) as avg_amount
            FROM sales sa
            LEFT JOIN visitors v ON sa.visitor_id = v.id
            LEFT JOIN services sv ON v.service_id = sv.id
            WHERE sa.agent_id = ? AND $date_condition
            GROUP BY sv.id, sv.name
            HAVING transaction_count > 0
            ORDER BY total_amount DESC";
    // replace alias in condition produced for s.
    $sql = str_replace('s.payment_date', 'sa.payment_date', $sql);
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $agent_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $services = [];
    $total_sales = 0.0;
    while ($row = mysqli_fetch_assoc($result)) {
        $amount = (float)$row['total_amount'];
        $services[] = [
            'id' => (int)$row['id'],
            'name' => $row['service_name'],
            'transactions' => (int)$row['transaction_count'],
            'units' => (int)$row['units'],
            'amount' => $amount,
            'avg_amount' => (float)$row['avg_amount'],
        ];
        $total_sales += $amount;
    }
    foreach ($services as &$service) {
        $service['percentage'] = sisSafePercent($service['amount'], $total_sales);
    }
    unset($service);

    api_json(['success' => true, 'period' => $period, 'total_sales' => $total_sales, 'services' => $services]);
}

function getTargetProgress($conn, $agent_id) {
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    $sql = "SELECT t.id, t.target_value, t.period_type, t.start_date, t.end_date,
                   COALESCE((
                       SELECT SUM(CASE WHEN s.amount > 0 AND s.payment_status = 'Paid' THEN s.amount ELSE 0 END)
                       FROM sales s
                       WHERE s.agent_id = t.assigned_to_id
                         AND DATE(s.payment_date) BETWEEN GREATEST(t.start_date, ?) AND LEAST(t.end_date, ?)
                   ), 0) as actual_sales
            FROM targets t
            WHERE t.target_type = 'agent'
              AND t.assigned_to_id = ?
              AND t.start_date <= ?
              AND t.end_date >= ?
            ORDER BY t.end_date DESC, t.target_value DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssiss', $monthStart, $monthEnd, $agent_id, $monthEnd, $monthStart);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $targets = [];
    $total_target = 0.0;
    $total_achieved = 0.0;
    while ($row = mysqli_fetch_assoc($result)) {
        $target = (float)$row['target_value'];
        $achieved = (float)$row['actual_sales'];
        $progress = $target > 0 ? min(round(($achieved / $target) * 100, 1), 100) : 0;
        $targets[] = [
            'service' => 'Overall Sales Target',
            'period_type' => $row['period_type'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'target' => $target,
            'achieved' => $achieved,
            'progress' => $progress,
            'remaining' => max($target - $achieved, 0),
            'status' => $progress >= 100 ? 'achieved' : ($progress >= 70 ? 'on_track' : 'behind'),
        ];
        $total_target += $target;
        $total_achieved += min($achieved, $target);
    }

    api_json([
        'success' => true,
        'month' => date('F Y', strtotime($monthStart)),
        'total_target' => $total_target,
        'total_achieved' => $total_achieved,
        'overall_progress' => sisSafePercent($total_achieved, $total_target),
        'targets' => $targets,
    ]);
}

function getConversionStats($conn, $agent_id) {
    $period = $_GET['period'] ?? 'month';
    $date_condition = api_date_condition_for_period($period, 'visit_time');

    $sql = "SELECT COUNT(*) as total_visitors,
                   SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN status = 'Waiting' THEN 1 ELSE 0 END) as waiting,
                   SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                   SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM visitors
            WHERE agent_id = ? AND $date_condition";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $agent_id);
    mysqli_stmt_execute($stmt);
    $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];

    $purposesSql = "SELECT COALESCE(p.name, 'Unknown') as purpose, COUNT(*) as count
                    FROM visitors v
                    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
                    WHERE v.agent_id = ? AND " . api_date_condition_for_period($period, 'v.visit_time') . "
                    GROUP BY p.id, p.name
                    ORDER BY count DESC";
    $stmt = mysqli_prepare($conn, $purposesSql);
    mysqli_stmt_bind_param($stmt, 'i', $agent_id);
    mysqli_stmt_execute($stmt);
    $purposes_result = mysqli_stmt_get_result($stmt);
    $purposes = [];
    while ($p = mysqli_fetch_assoc($purposes_result)) {
        $purposes[] = ['purpose' => $p['purpose'], 'count' => (int)$p['count']];
    }

    api_json([
        'success' => true,
        'period' => $period,
        'total_visitors' => (int)($stats['total_visitors'] ?? 0),
        'converted' => (int)($stats['completed'] ?? 0),
        'completed' => (int)($stats['completed'] ?? 0),
        'pending' => (int)($stats['waiting'] ?? 0),
        'in_progress' => (int)($stats['in_progress'] ?? 0),
        'cancelled' => (int)($stats['cancelled'] ?? 0),
        'conversion_rate' => sisSafePercent($stats['completed'] ?? 0, $stats['total_visitors'] ?? 0),
        'purposes' => $purposes,
    ]);
}

function getPerformanceScore($conn, $agent_id, $outlet_id) {
    $month = date('Y-m');
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    $targetSql = "SELECT COALESCE(SUM(target_value), 0) as total_target
                  FROM targets
                  WHERE target_type = 'agent' AND assigned_to_id = ?
                    AND start_date <= ? AND end_date >= ?";
    $stmt = mysqli_prepare($conn, $targetSql);
    mysqli_stmt_bind_param($stmt, 'iss', $agent_id, $monthEnd, $monthStart);
    mysqli_stmt_execute($stmt);
    $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['total_target' => 0];

    $salesSql = "SELECT COALESCE(SUM(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN amount ELSE 0 END), 0) as total_sales,
                        COUNT(CASE WHEN payment_status = 'Paid' AND amount > 0 THEN 1 END) as transactions,
                        COUNT(DISTINCT DATE(payment_date)) as active_days
                 FROM sales
                 WHERE agent_id = ? AND DATE(payment_date) BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $salesSql);
    mysqli_stmt_bind_param($stmt, 'iss', $agent_id, $monthStart, $monthEnd);
    mysqli_stmt_execute($stmt);
    $sales = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];

    $visitorSql = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
                   FROM visitors
                   WHERE agent_id = ? AND DATE(visit_time) BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $visitorSql);
    mysqli_stmt_bind_param($stmt, 'iss', $agent_id, $monthStart, $monthEnd);
    mysqli_stmt_execute($stmt);
    $visitors = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];

    $avgSql = "SELECT AVG(transactions) as avg_transactions FROM (
                   SELECT agent_id, COUNT(*) as transactions
                   FROM sales
                   WHERE outlet_id = ? AND payment_status = 'Paid' AND amount > 0 AND DATE(payment_date) BETWEEN ? AND ?
                   GROUP BY agent_id
               ) t";
    $stmt = mysqli_prepare($conn, $avgSql);
    mysqli_stmt_bind_param($stmt, 'iss', $outlet_id, $monthStart, $monthEnd);
    mysqli_stmt_execute($stmt);
    $avg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['avg_transactions' => 1];

    $target_score = (float)$target['total_target'] > 0 ? min(((float)$sales['total_sales'] / (float)$target['total_target']) * 40, 40) : 0;
    $completion_rate = sisSafePercent($visitors['completed'] ?? 0, $visitors['total'] ?? 0);
    $conversion_score = min(($completion_rate / 100) * 30, 30);
    $avg_transactions = max(1, (float)($avg['avg_transactions'] ?? 1));
    $volume_score = min(((float)($sales['transactions'] ?? 0) / $avg_transactions) * 15, 15);
    $working_days = (int)date('t');
    $activity_score = min(((float)($sales['active_days'] ?? 0) / $working_days) * 15, 15);
    $total_score = (int)round($target_score + $conversion_score + $volume_score + $activity_score);

    if ($total_score >= 90) $grade = 'A+';
    elseif ($total_score >= 80) $grade = 'A';
    elseif ($total_score >= 70) $grade = 'B';
    elseif ($total_score >= 60) $grade = 'C';
    elseif ($total_score >= 50) $grade = 'D';
    else $grade = 'F';

    api_json([
        'success' => true,
        'score' => $total_score,
        'grade' => $grade,
        'components' => [
            'target_achievement' => round($target_score, 1),
            'completion_rate' => round($conversion_score, 1),
            'transaction_volume' => round($volume_score, 1),
            'activity' => round($activity_score, 1),
        ],
        'metrics' => [
            'sales_vs_target' => sisSafePercent($sales['total_sales'] ?? 0, $target['total_target'] ?? 0) . '%',
            'conversion_rate' => $completion_rate . '%',
            'transactions' => (int)($sales['transactions'] ?? 0),
            'active_days' => (int)($sales['active_days'] ?? 0),
        ],
    ]);
}

switch ($action) {
    case 'daily_trend': getDailyTrend($conn, $agent_id); break;
    case 'weekly_summary': getWeeklySummary($conn, $agent_id); break;
    case 'monthly_performance': getMonthlyPerformance($conn, $agent_id); break;
    case 'service_breakdown': getServiceBreakdown($conn, $agent_id); break;
    case 'target_progress': getTargetProgress($conn, $agent_id); break;
    case 'conversion_stats': getConversionStats($conn, $agent_id); break;
    case 'performance_score': getPerformanceScore($conn, $agent_id, $outlet_id); break;
    default: api_json(['success' => false, 'error' => 'Invalid action']);
}
?>
