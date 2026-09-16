<?php
// include/reporting_helpers.php
// Centralised reporting and calculation helpers for SIS showroom reports.
// The goal is to keep revenue, withdrawals, quantities, targets and shifts consistent
// across admin, supervisor and agent reporting pages.

if (!function_exists('sisReportDate')) {
    function sisReportDate($value, $fallback = null) {
        $value = trim((string)($value ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        $fallback = $fallback ?: date('Y-m-d');
        return date('Y-m-d', strtotime($fallback));
    }
}

if (!function_exists('sisDateRange')) {
    function sisDateRange($from, $to, $fallbackFrom = null, $fallbackTo = null) {
        $from = sisReportDate($from, $fallbackFrom ?: date('Y-m-01'));
        $to = sisReportDate($to, $fallbackTo ?: date('Y-m-d'));
        if (strtotime($from) > strtotime($to)) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }
        return [$from, $to];
    }
}

if (!function_exists('sisSqlValue')) {
    function sisSqlValue($conn, $value) {
        return "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
    }
}

if (!function_exists('sisDateWhere')) {
    function sisDateWhere($conn, $column, $from, $to) {
        return "DATE($column) BETWEEN " . sisSqlValue($conn, $from) . " AND " . sisSqlValue($conn, $to);
    }
}

if (!function_exists('sisInclusiveDayCount')) {
    function sisInclusiveDayCount($from, $to) {
        $start = strtotime((string)$from);
        $end = strtotime((string)$to);
        if ($start === false || $end === false) return 1;
        return max(1, (int)floor(($end - $start) / 86400) + 1);
    }
}

if (!function_exists('sisLatestAgentShiftJoin')) {
    function sisLatestAgentShiftJoin($salesAlias = 's', $shiftAlias = 'a', $conn = null) {
        if ($conn && function_exists('tableExists') && !tableExists($conn, 'agent_shifts')) {
            return "LEFT JOIN (
                        SELECT NULL AS agent_id, NULL AS shift_date, NULL AS shift_type
                        WHERE 1 = 0
                    ) $shiftAlias ON 1 = 0";
        }
        return "LEFT JOIN (
                    SELECT ash.agent_id, ash.shift_date, ash.shift_type
                    FROM agent_shifts ash
                    INNER JOIN (
                        SELECT agent_id, shift_date, MAX(id) AS latest_id
                        FROM agent_shifts
                        GROUP BY agent_id, shift_date
                    ) latest_shift ON latest_shift.latest_id = ash.id
                ) $shiftAlias
                ON $salesAlias.agent_id = $shiftAlias.agent_id
               AND DATE($salesAlias.payment_date) = $shiftAlias.shift_date";
    }
}

if (!function_exists('sisShiftCaseSql')) {
    function sisShiftCaseSql($datetimeExpr) {
        return "CASE WHEN HOUR($datetimeExpr) >= 6 AND HOUR($datetimeExpr) < 14 THEN 'Morning' " .
               "WHEN HOUR($datetimeExpr) >= 14 AND HOUR($datetimeExpr) < 22 THEN 'Afternoon' " .
               "ELSE 'Night' END";
    }
}

if (!function_exists('sisVisitorUnitExpr')) {
    function sisVisitorUnitExpr($conn, $visitorAlias = 'v') {
        if (isset($conn) && tableColumnExists($conn, 'visitors', 'quantity')) {
            return "GREATEST(COALESCE($visitorAlias.quantity, 1), 1)";
        }
        return '1';
    }
}

if (!function_exists('sisPaidPositiveSum')) {
    function sisPaidPositiveSum($salesAlias = 's') {
        return "COALESCE(SUM(CASE WHEN $salesAlias.payment_status = 'Paid' AND $salesAlias.amount > 0 THEN $salesAlias.amount ELSE 0 END), 0)";
    }
}

if (!function_exists('sisPaidWithdrawalSum')) {
    function sisPaidWithdrawalSum($salesAlias = 's') {
        return "COALESCE(SUM(CASE WHEN $salesAlias.payment_status = 'Paid' AND $salesAlias.amount < 0 THEN ABS($salesAlias.amount) ELSE 0 END), 0)";
    }
}

if (!function_exists('sisPaidNetSum')) {
    function sisPaidNetSum($salesAlias = 's') {
        return "COALESCE(SUM(CASE WHEN $salesAlias.payment_status = 'Paid' THEN $salesAlias.amount ELSE 0 END), 0)";
    }
}

if (!function_exists('sisPendingPositiveSum')) {
    function sisPendingPositiveSum($salesAlias = 's') {
        return "COALESCE(SUM(CASE WHEN $salesAlias.payment_status = 'Pending' AND $salesAlias.amount > 0 THEN $salesAlias.amount ELSE 0 END), 0)";
    }
}

if (!function_exists('sisUnpaidPositiveSum')) {
    function sisUnpaidPositiveSum($salesAlias = 's') {
        return "COALESCE(SUM(CASE WHEN $salesAlias.payment_status = 'Unpaid' AND $salesAlias.amount > 0 THEN $salesAlias.amount ELSE 0 END), 0)";
    }
}

if (!function_exists('sisCountPaidPositive')) {
    function sisCountPaidPositive($salesAlias = 's') {
        return "COUNT(CASE WHEN $salesAlias.payment_status = 'Paid' AND $salesAlias.amount > 0 THEN 1 END)";
    }
}

if (!function_exists('sisServiceQtyCase')) {
    function sisServiceQtyCase($condition, $unitExpr = '1') {
        return "COALESCE(SUM(CASE WHEN ($condition) THEN $unitExpr ELSE 0 END), 0)";
    }
}

if (!function_exists('sisServiceRevenueCase')) {
    function sisServiceRevenueCase($condition, $salesAlias = 's') {
        return "COALESCE(SUM(CASE WHEN ($condition) AND $salesAlias.amount > 0 AND $salesAlias.payment_status = 'Paid' THEN $salesAlias.amount ELSE 0 END), 0)";
    }
}

if (!function_exists('sisSafePercent')) {
    function sisSafePercent($part, $total, $decimals = 1) {
        $total = (float)$total;
        if ($total <= 0) return 0;
        return round(((float)$part / $total) * 100, $decimals);
    }
}

if (!function_exists('sisMoney')) {
    function sisMoney($value) {
        return number_format((float)($value ?? 0), 2);
    }
}
?>
