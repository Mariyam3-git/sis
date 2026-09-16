<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/reporting_helpers.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Load settings for currency
$settings = [];
$settings_query = mysqli_query($conn, "SELECT * FROM settings");
while($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$currency_symbol = $settings['currency_symbol'] ?? 'D';
$has_handling_time = tableColumnExists($conn, 'visitors', 'handling_seconds');
$has_sales_handling_time = tableColumnExists($conn, 'sales', 'handle_seconds');
$has_sales_handle_started = tableColumnExists($conn, 'sales', 'handle_started_at');
$has_sales_handle_completed = tableColumnExists($conn, 'sales', 'handle_completed_at');
$salesHandlePart = $has_sales_handling_time ? 's.handle_seconds' : 'NULL';
$visitorHandlePart = $has_handling_time ? 'v.handling_seconds' : 'NULL';
$handleTimeExpr = ($has_sales_handling_time || $has_handling_time) ? "COALESCE($salesHandlePart, $visitorHandlePart)" : 'NULL';
$has_complaint_reasons = tableExists($conn, 'complaint_reasons') && tableColumnExists($conn, 'visitors', 'complaint_reason_id');

// Get filter parameters
[$date_from, $date_to] = sisDateRange($_GET['date_from'] ?? null, $_GET['date_to'] ?? null, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$filter_region = isset($_GET['region']) ? trim((string)$_GET['region']) : '';
$filter_outlet = isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : 0;
$filter_service = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
$filter_purpose = isset($_GET['purpose_id']) ? (int)$_GET['purpose_id'] : 0;
$filter_agent = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$unitExpr = sisVisitorUnitExpr($conn, 'v');
$filter_handle_min = isset($_GET['handle_min']) && $_GET['handle_min'] !== '' ? max(0, (int)$_GET['handle_min']) : '';
$filter_handle_max = isset($_GET['handle_max']) && $_GET['handle_max'] !== '' ? max(0, (int)$_GET['handle_max']) : '';

// Build WHERE clause for filters
$where_conditions = [sisDateWhere($conn, 's.payment_date', $date_from, $date_to)];
if ($filter_region) {
    $where_conditions[] = "o.region = " . sisSqlValue($conn, $filter_region);
}
if ($filter_outlet) {
    $where_conditions[] = "s.outlet_id = $filter_outlet";
}
if ($filter_service) {
    $where_conditions[] = "sv.id = $filter_service";
}
if ($filter_purpose) {
    $where_conditions[] = "p.id = $filter_purpose";
}
if ($filter_agent) {
    $where_conditions[] = "s.agent_id = $filter_agent";
}
if (($has_handling_time || $has_sales_handling_time) && $filter_handle_min !== '') {
    $where_conditions[] = "$handleTimeExpr >= " . ((int)$filter_handle_min * 60);
}
if (($has_handling_time || $has_sales_handling_time) && $filter_handle_max !== '') {
    $where_conditions[] = "$handleTimeExpr <= " . ((int)$filter_handle_max * 60);
}
$where_clause = implode(' AND ', $where_conditions);

// Visitor-based filters for charts/tables that are driven by the visitors table.
$visitor_where_conditions = [sisDateWhere($conn, 'v.visit_time', $date_from, $date_to)];
if ($filter_region) {
    $visitor_where_conditions[] = "o.region = " . sisSqlValue($conn, $filter_region);
}
if ($filter_outlet) {
    $visitor_where_conditions[] = "v.outlet_id = $filter_outlet";
}
if ($filter_service) {
    $visitor_where_conditions[] = "v.service_id = $filter_service";
}
if ($filter_purpose) {
    $visitor_where_conditions[] = "v.purpose_id = $filter_purpose";
}
if ($filter_agent) {
    $visitor_where_conditions[] = "v.agent_id = $filter_agent";
}
if ($has_handling_time && $filter_handle_min !== '') {
    $visitor_where_conditions[] = "v.handling_seconds >= " . ((int)$filter_handle_min * 60);
}
if ($has_handling_time && $filter_handle_max !== '') {
    $visitor_where_conditions[] = "v.handling_seconds <= " . ((int)$filter_handle_max * 60);
}
$visitor_where_clause = implode(' AND ', $visitor_where_conditions);

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$year_start = date('Y-01-01');
$last_30_start = date('Y-m-d', strtotime('-30 days'));
$has_active_filters = ($date_from !== $last_30_start || $date_to !== $today || $filter_region !== '' || $filter_outlet || $filter_service || $filter_purpose || $filter_agent || $filter_handle_min !== '' || $filter_handle_max !== '');

// Get filter dropdown data
$regions = mysqli_query($conn, "SELECT DISTINCT region FROM outlets WHERE region IS NOT NULL AND region != '' ORDER BY region");
$outlets_list = mysqli_query($conn, "SELECT id, name FROM outlets WHERE is_active = 1 ORDER BY name");
$services_list = mysqli_query($conn, "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name");
$purposes_list = mysqli_query($conn, "SELECT id, name FROM purpose_of_visit WHERE is_active = 1 ORDER BY name");
$agents_list = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'agent' AND is_active = 1 ORDER BY full_name");

// ============================================
// KPI QUERIES
// ============================================

// General KPIs
$general_kpis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        " . sisPaidPositiveSum('s') . " as total_revenue,
        " . sisPaidWithdrawalSum('s') . " as total_withdrawals,
        " . sisPaidNetSum('s') . " as net_cash_flow,
        COUNT(DISTINCT s.id) as total_transactions,
        COUNT(DISTINCT v.id) as total_customers,
        ROUND(" . sisPaidPositiveSum('s') . " / NULLIF(COUNT(DISTINCT v.id), 0), 2) as avg_revenue_per_customer,
        (SELECT o2.region FROM outlets o2 JOIN sales s2 ON o2.id = s2.outlet_id WHERE " . sisDateWhere($conn, 's2.payment_date', $date_from, $date_to) . " AND s2.payment_status = 'Paid' AND s2.amount > 0 GROUP BY o2.region ORDER BY SUM(s2.amount) DESC LIMIT 1) as top_region,
        (SELECT o3.name FROM outlets o3 JOIN sales s3 ON o3.id = s3.outlet_id WHERE " . sisDateWhere($conn, 's3.payment_date', $date_from, $date_to) . " AND s3.payment_status = 'Paid' AND s3.amount > 0 GROUP BY o3.id ORDER BY SUM(s3.amount) DESC LIMIT 1) as top_outlet
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    WHERE $where_clause
"));
$general_kpis['net_cash_flow'] = $general_kpis['net_cash_flow'] ?? (($general_kpis['total_revenue'] ?? 0) - ($general_kpis['total_withdrawals'] ?? 0));

// Telecom KPIs - Fixed to prevent double counting
$telecom_kpis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Bundle%' OR sv.name LIKE '%bundle%' THEN $unitExpr ELSE 0 END), 0) as bundles_sold_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%Bundle%' OR sv.name LIKE '%bundle%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as bundles_sold_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Airtime%' OR sv.name LIKE '%Top Up%' OR sv.name LIKE '%Recharge%' THEN $unitExpr ELSE 0 END), 0) as airtime_topups_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%Airtime%' OR sv.name LIKE '%Top Up%' OR sv.name LIKE '%Recharge%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as airtime_topups_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Data%' OR sv.name LIKE '%Internet%' THEN $unitExpr ELSE 0 END), 0) as data_topups_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%Data%' OR sv.name LIKE '%Internet%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as data_topups_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%EVC%' OR sv.name LIKE '%Mobile Money%' THEN $unitExpr ELSE 0 END), 0) as evc_transactions_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%EVC%' OR sv.name LIKE '%Mobile Money%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as evc_transactions_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%VIP%' OR sv.name LIKE '%Gold%' THEN $unitExpr ELSE 0 END), 0) as vip_numbers_sold_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%VIP%' OR sv.name LIKE '%Gold%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as vip_numbers_sold_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Bronze%' OR sv.name LIKE '%Standard%' THEN $unitExpr ELSE 0 END), 0) as bronze_numbers_sold_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%Bronze%' OR sv.name LIKE '%Standard%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as bronze_numbers_sold_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Premium Number%' OR sv.name LIKE '%Special%' THEN $unitExpr ELSE 0 END), 0) as premium_requests_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%Premium Number%' OR sv.name LIKE '%Special%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as premium_requests_rev,
        
        -- ============================================
        -- eSIM REQUESTS - EXACT TEXT MATCHING
        -- ============================================
        COALESCE(SUM(CASE 
            WHEN sv.name = 'eSIM Request' 
                OR sv.name = 'E-SIM Request'
                OR sv.name = 'New eSIM Request'
                OR sv.name = 'E-SIM Request - New'
                OR sv.name LIKE 'eSIM Request%'
                OR sv.name LIKE 'E-SIM Request%'
                AND sv.name NOT LIKE '%Replacement%'
                AND sv.name NOT LIKE '%Replace%'
            THEN $unitExpr 
            ELSE 0 
        END), 0) as esim_requests_qty,
        
        COALESCE(SUM(CASE 
            WHEN (sv.name = 'eSIM Request' 
                OR sv.name = 'E-SIM Request'
                OR sv.name = 'New eSIM Request'
                OR sv.name = 'E-SIM Request - New'
                OR sv.name LIKE 'eSIM Request%'
                OR sv.name LIKE 'E-SIM Request%')
                AND sv.name NOT LIKE '%Replacement%'
                AND sv.name NOT LIKE '%Replace%'
                AND s.amount >= 0 
            THEN s.amount 
            ELSE 0 
        END), 0) as esim_requests_rev,
        
        -- ============================================
        -- eSIM REPLACEMENT - EXACT TEXT MATCHING
        -- ============================================
        COALESCE(SUM(CASE 
            WHEN sv.name = 'eSIM Replacement' 
                OR sv.name = 'E-SIM Replacement'
                OR sv.name = 'eSIM Replace'
                OR sv.name = 'E-SIM Replace'
                OR sv.name = 'eSIM Lost Replacement'
                OR sv.name = 'E-SIM Lost Replacement'
                OR sv.name = 'eSIM Damaged Replacement'
                OR sv.name = 'E-SIM Damaged Replacement'
                OR sv.name LIKE 'eSIM Replacement%'
                OR sv.name LIKE 'E-SIM Replacement%'
                AND sv.name NOT LIKE '%Request%'
            THEN $unitExpr 
            ELSE 0 
        END), 0) as esim_replacements_qty,
        
        COALESCE(SUM(CASE 
            WHEN (sv.name = 'eSIM Replacement' 
                OR sv.name = 'E-SIM Replacement'
                OR sv.name = 'eSIM Replace'
                OR sv.name = 'E-SIM Replace'
                OR sv.name = 'eSIM Lost Replacement'
                OR sv.name = 'E-SIM Lost Replacement'
                OR sv.name = 'eSIM Damaged Replacement'
                OR sv.name = 'E-SIM Damaged Replacement'
                OR sv.name LIKE 'eSIM Replacement%'
                OR sv.name LIKE 'E-SIM Replacement%')
                AND sv.name NOT LIKE '%Request%'
                AND s.amount >= 0 
            THEN s.amount 
            ELSE 0 
        END), 0) as esim_replacements_rev,
        
        -- ============================================
        -- NEW SIM CARD - EXCLUDES renewal/replacement/swap and eSIM
        -- ============================================
        COALESCE(SUM(CASE 
            WHEN (sv.name LIKE '%New%SIM%' 
                OR sv.name LIKE '%SIM%New%'
                OR sv.name LIKE '%SIM Card%' 
                OR sv.name LIKE '%SIM Activation%'
                OR sv.name LIKE '%SIM Registration%'
                OR sv.name LIKE '%Physical SIM%'
                OR sv.name LIKE '%Standard SIM%'
                OR sv.name LIKE '%SIM Sale%'
                OR sv.name LIKE '%SIM Purchase%')
                AND sv.name NOT LIKE '%Renew%' 
                AND sv.name NOT LIKE '%Replace%' 
                AND sv.name NOT LIKE '%Swap%'
                AND sv.name NOT LIKE '%Renewal%'
                AND sv.name NOT LIKE '%Replacement%'
                AND sv.name NOT LIKE '%eSIM%' 
                AND sv.name NOT LIKE '%E-SIM%'
            THEN $unitExpr 
            ELSE 0 
        END), 0) as new_sim_qty,
        
        COALESCE(SUM(CASE 
            WHEN (sv.name LIKE '%New%SIM%' 
                OR sv.name LIKE '%SIM%New%'
                OR sv.name LIKE '%SIM Card%' 
                OR sv.name LIKE '%SIM Activation%'
                OR sv.name LIKE '%SIM Registration%'
                OR sv.name LIKE '%Physical SIM%'
                OR sv.name LIKE '%Standard SIM%'
                OR sv.name LIKE '%SIM Sale%'
                OR sv.name LIKE '%SIM Purchase%')
                AND sv.name NOT LIKE '%Renew%' 
                AND sv.name NOT LIKE '%Replace%' 
                AND sv.name NOT LIKE '%Swap%'
                AND sv.name NOT LIKE '%Renewal%'
                AND sv.name NOT LIKE '%Replacement%'
                AND sv.name NOT LIKE '%eSIM%' 
                AND sv.name NOT LIKE '%E-SIM%'
                AND s.amount >= 0 
            THEN s.amount 
            ELSE 0 
        END), 0) as new_sim_rev,
        
        -- ============================================
        -- SIM CARD RENEWAL / REPLACEMENT - EXCLUDES eSIM
        -- ============================================
        COALESCE(SUM(CASE 
            WHEN (sv.name LIKE '%SIM%Renew%' 
                OR sv.name LIKE '%Renew%SIM%'
                OR sv.name LIKE '%SIM%Replace%'
                OR sv.name LIKE '%Replace%SIM%'
                OR sv.name LIKE '%SIM%Swap%'
                OR sv.name LIKE '%Swap%SIM%'
                OR sv.name LIKE '%SIM Renewal%'
                OR sv.name LIKE '%SIM Replacement%'
                OR sv.name LIKE '%SIM Swap%'
                OR sv.name LIKE '%Renew SIM%'
                OR sv.name LIKE '%Replace SIM%'
                OR sv.name LIKE '%SIM Change%'
                OR sv.name LIKE '%SIM Upgrade%'
                OR sv.name LIKE '%SIM Downgrade%'
                OR sv.name LIKE '%SIM Card Renewal%'
                OR sv.name LIKE '%SIM Card Replacement%'
                OR sv.name LIKE '%SIM Card Swap%')
                AND sv.name NOT LIKE '%eSIM%' 
                AND sv.name NOT LIKE '%E-SIM%'
            THEN $unitExpr 
            ELSE 0 
        END), 0) as sim_renewal_qty,
        
        COALESCE(SUM(CASE 
            WHEN (sv.name LIKE '%SIM%Renew%' 
                OR sv.name LIKE '%Renew%SIM%'
                OR sv.name LIKE '%SIM%Replace%'
                OR sv.name LIKE '%Replace%SIM%'
                OR sv.name LIKE '%SIM%Swap%'
                OR sv.name LIKE '%Swap%SIM%'
                OR sv.name LIKE '%SIM Renewal%'
                OR sv.name LIKE '%SIM Replacement%'
                OR sv.name LIKE '%SIM Swap%'
                OR sv.name LIKE '%Renew SIM%'
                OR sv.name LIKE '%Replace SIM%'
                OR sv.name LIKE '%SIM Change%'
                OR sv.name LIKE '%SIM Upgrade%'
                OR sv.name LIKE '%SIM Downgrade%'
                OR sv.name LIKE '%SIM Card Renewal%'
                OR sv.name LIKE '%SIM Card Replacement%'
                OR sv.name LIKE '%SIM Card Swap%')
                AND sv.name NOT LIKE '%eSIM%' 
                AND sv.name NOT LIKE '%E-SIM%'
                AND s.amount >= 0 
            THEN s.amount 
            ELSE 0 
        END), 0) as sim_renewal_rev
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    WHERE $where_clause AND s.payment_status = 'Paid' AND s.amount >= 0
"));

// ISP KPIs
$isp_kpis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Router%' OR sv.name LIKE '%Modem%' OR sv.name LIKE '%CPE%' THEN $unitExpr ELSE 0 END), 0) as isp_devices_sold_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%Router%' OR sv.name LIKE '%Modem%' OR sv.name LIKE '%CPE%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as isp_devices_sold_rev,
        
        (SELECT sv2.name FROM services sv2 WHERE (sv2.name LIKE '%Router%' OR sv2.name LIKE '%Modem%') LIMIT 1) as most_sold_device,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Cambium%' THEN $unitExpr ELSE 0 END), 0) as cambium_routers_qty,
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Cambium%' AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as cambium_routers_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%CPE%' THEN $unitExpr ELSE 0 END), 0) as cpe_routers_qty,
        COALESCE(SUM(CASE WHEN sv.name LIKE '%CPE%' AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as cpe_routers_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Home Broadband%' OR sv.name LIKE '%Fixed Wireless%' THEN $unitExpr ELSE 0 END), 0) as home_broadband_users_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%Home Broadband%' OR sv.name LIKE '%Fixed Wireless%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as home_broadband_users_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%New Home Broadband%' THEN $unitExpr ELSE 0 END), 0) as new_broadband_users_qty,
        COALESCE(SUM(CASE WHEN sv.name LIKE '%New Home Broadband%' AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as new_broadband_users_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Installation%' AND sv.name NOT LIKE '%Request%' THEN $unitExpr ELSE 0 END), 0) as new_installations_qty,
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Installation%' AND sv.name NOT LIKE '%Request%' AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as new_installations_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Installation Request%' THEN $unitExpr ELSE 0 END), 0) as installation_requests_qty,
        
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%ISP%' OR sv.name LIKE '%Internet%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as isp_revenue,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%Renewal%' OR sv.name LIKE '%Subscription%' THEN $unitExpr ELSE 0 END), 0) as isp_renewals_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%Renewal%' OR sv.name LIKE '%Subscription%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as isp_renewals_rev
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    WHERE $where_clause AND s.payment_status = 'Paid' AND s.amount >= 0
"));

// ComCach KPIs
$comcach_kpis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN sv.name LIKE '%ComCach Deposit%' OR sv.name LIKE '%Mobile Money Deposit%' THEN $unitExpr ELSE 0 END), 0) as comcach_deposits_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%ComCach Deposit%' OR sv.name LIKE '%Mobile Money Deposit%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as comcach_deposits_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%ComCach Bill%' OR sv.name LIKE '%Bill Payment%' THEN $unitExpr ELSE 0 END), 0) as comcach_bills_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%ComCach Bill%' OR sv.name LIKE '%Bill Payment%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as comcach_bills_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%ComCach%' OR sv.name LIKE '%Mobile Money%' THEN $unitExpr ELSE 0 END), 0) as comcach_transactions_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%ComCach%' OR sv.name LIKE '%Mobile Money%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as comcach_transactions_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%ComCach Airtime%' OR sv.name LIKE '%Mobile Money Airtime%' THEN $unitExpr ELSE 0 END), 0) as comcach_airtime_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%ComCach Airtime%' OR sv.name LIKE '%Mobile Money Airtime%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as comcach_airtime_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%ComCach Data%' OR sv.name LIKE '%Mobile Money Data%' THEN $unitExpr ELSE 0 END), 0) as comcach_data_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%ComCach Data%' OR sv.name LIKE '%Mobile Money Data%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as comcach_data_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%ComCach Withdrawal%' OR sv.name LIKE '%Mobile Money Withdrawal%' THEN $unitExpr ELSE 0 END), 0) as comcach_withdrawals_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%ComCach Withdrawal%' OR sv.name LIKE '%Mobile Money Withdrawal%') AND s.amount < 0 THEN ABS(s.amount) ELSE 0 END), 0) as comcach_withdrawals_rev,
        
        COALESCE(SUM(CASE WHEN sv.name LIKE '%ComCach Transfer%' OR sv.name LIKE '%Money Transfer%' THEN $unitExpr ELSE 0 END), 0) as comcach_transfers_qty,
        COALESCE(SUM(CASE WHEN (sv.name LIKE '%ComCach Transfer%' OR sv.name LIKE '%Money Transfer%') AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as comcach_transfers_rev
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    WHERE $where_clause AND s.payment_status = 'Paid'
"));

// Customer Service KPIs
$kpiHandleExpr = $handleTimeExpr;
$handlingKpiSelect = ($has_handling_time || $has_sales_handling_time)
    ? ", ROUND(AVG(CASE WHEN $kpiHandleExpr IS NOT NULL THEN $kpiHandleExpr END), 0) as avg_handling_seconds, MAX($kpiHandleExpr) as max_handling_seconds"
    : ", 0 as avg_handling_seconds, 0 as max_handling_seconds";
$complaintCsJoin = $has_complaint_reasons ? "LEFT JOIN complaint_reasons cr ON v.complaint_reason_id = cr.id" : "";
$complaintCase = "(p.name LIKE '%Complaint%'" . ($has_complaint_reasons ? " OR cr.id IS NOT NULL" : "") . ")";
$cs_kpis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN $complaintCase THEN 1 ELSE 0 END) as complaints_submitted,
        SUM(CASE WHEN $complaintCase AND v.status = 'Completed' THEN 1 ELSE 0 END) as complaints_resolved,
        SUM(CASE WHEN p.name LIKE '%Support%' THEN 1 ELSE 0 END) as support_requests,
        SUM(CASE WHEN p.name LIKE '%Feedback%' OR p.name LIKE '%Suggestion%' THEN 1 ELSE 0 END) as feedback_suggestions
        $handlingKpiSelect
    FROM visitors v
    LEFT JOIN sales s ON s.visitor_id = v.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    $complaintCsJoin
    WHERE $visitor_where_clause
"));
$cs_kpis['resolution_rate'] = $cs_kpis['complaints_submitted'] > 0 
    ? round(($cs_kpis['complaints_resolved'] / $cs_kpis['complaints_submitted']) * 100, 1) 
    : 0;

// ============================================
// CHART QUERIES
// ============================================

$revenue_by_region = mysqli_query($conn, "
    SELECT COALESCE(NULLIF(o.region, ''), 'Unknown') as region,
           COALESCE(SUM(CASE WHEN s.amount >= 0 AND s.payment_status = 'Paid' THEN s.amount ELSE 0 END), 0) as revenue
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    WHERE $where_clause AND s.payment_status = 'Paid' AND s.amount >= 0
    GROUP BY COALESCE(NULLIF(o.region, ''), 'Unknown')
    ORDER BY revenue DESC
");

$revenue_by_outlet = mysqli_query($conn, "
    SELECT COALESCE(o.name, 'Unknown') as name,
           COALESCE(SUM(CASE WHEN s.amount >= 0 AND s.payment_status = 'Paid' THEN s.amount ELSE 0 END), 0) as revenue
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    WHERE $where_clause AND s.payment_status = 'Paid' AND s.amount >= 0
    GROUP BY o.id, o.name
    ORDER BY revenue DESC
    LIMIT 10
");

$monthly_trend = mysqli_query($conn, "
    SELECT DATE_FORMAT(s.payment_date, '%Y-%m') as month,
           COUNT(*) as transactions,
           COALESCE(SUM(CASE WHEN s.amount >= 0 AND s.payment_status = 'Paid' THEN s.amount ELSE 0 END), 0) as revenue
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    WHERE $where_clause AND s.payment_status = 'Paid' AND s.amount >= 0
    GROUP BY DATE_FORMAT(s.payment_date, '%Y-%m')
    ORDER BY month ASC
");

$purpose_distribution = mysqli_query($conn, "
    SELECT p.name, COUNT(*) as count
    FROM visitors v
    LEFT JOIN sales s ON s.visitor_id = v.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    WHERE $visitor_where_clause
    GROUP BY p.id
    ORDER BY count DESC
    LIMIT 10
");

$service_distribution = mysqli_query($conn, "
    SELECT sv.name, COUNT(*) as count
    FROM visitors v
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    WHERE $visitor_where_clause
    GROUP BY sv.id
    ORDER BY count DESC
    LIMIT 10
");

$most_sold_devices = mysqli_query($conn, "
    SELECT sv.name, COALESCE(SUM($unitExpr), 0) as sold_count
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    WHERE $where_clause
    AND (sv.name LIKE '%Router%' OR sv.name LIKE '%Modem%' OR sv.name LIKE '%SIM%' OR sv.name LIKE '%Device%')
    AND s.payment_status = 'Paid' AND s.amount >= 0
    GROUP BY sv.id
    ORDER BY sold_count DESC
    LIMIT 10
");

$isp_installations = mysqli_query($conn, "
    SELECT o.region, COUNT(*) as installations
    FROM visitors v
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    WHERE $visitor_where_clause
    AND (sv.name LIKE '%Installation%' OR sv.name LIKE '%Broadband%')
    GROUP BY o.region
    ORDER BY installations DESC
");

$vip_by_region = mysqli_query($conn, "
    SELECT o.region, COUNT(*) as vip_count
    FROM visitors v
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    WHERE $visitor_where_clause
    AND (sv.name LIKE '%VIP%' OR sv.name LIKE '%Gold%')
    GROUP BY o.region
    ORDER BY vip_count DESC
");

$complaintTrendJoin = $has_complaint_reasons ? "LEFT JOIN complaint_reasons cr ON v.complaint_reason_id = cr.id" : "";
$complaintTrendCase = "(p.name LIKE '%Complaint%'" . ($has_complaint_reasons ? " OR cr.id IS NOT NULL" : "") . ")";
$complaint_trend = mysqli_query($conn, "
    SELECT DATE(v.visit_time) as date,
           SUM(CASE WHEN $complaintTrendCase THEN 1 ELSE 0 END) as complaints,
           SUM(CASE WHEN v.status = 'Completed' AND $complaintTrendCase THEN 1 ELSE 0 END) as resolved
    FROM visitors v
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    $complaintTrendJoin
    WHERE $visitor_where_clause
    GROUP BY DATE(v.visit_time)
    ORDER BY date ASC
");

// ============================================
// TABLE QUERIES
// ============================================

$top_showrooms = mysqli_query($conn, "
    SELECT o.name, o.region,
           COUNT(DISTINCT v.id) as visitors,
           COUNT(DISTINCT s.id) as sales_count,
           COALESCE(SUM(CASE WHEN s.amount >= 0 AND s.payment_status = 'Paid' THEN s.amount ELSE 0 END), 0) as revenue,
           COALESCE(SUM(CASE WHEN s.payment_status = 'Pending' AND s.amount >= 0 THEN s.amount ELSE 0 END), 0) as pending
    FROM outlets o
    LEFT JOIN visitors v ON o.id = v.outlet_id AND DATE(v.visit_time) BETWEEN '$date_from' AND '$date_to'
    LEFT JOIN sales s ON v.id = s.visitor_id AND " . sisDateWhere($conn, 's.payment_date', $date_from, $date_to) . "
    WHERE o.is_active = 1
    GROUP BY o.id
    ORDER BY revenue DESC
    LIMIT 10
");

$quantityTotalExpr = tableColumnExists($conn, 'visitors', 'quantity') ? "COALESCE(SUM(GREATEST(COALESCE(v.quantity, 1), 1)), 0)" : "COUNT(*)";
$most_requested = mysqli_query($conn, "
    SELECT sv.name, COUNT(*) as request_count,
           $quantityTotalExpr as total_units,
           COALESCE(SUM(CASE WHEN s.amount >= 0 AND s.payment_status = 'Paid' THEN s.amount ELSE 0 END), 0) as total_revenue
    FROM visitors v
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    LEFT JOIN sales s ON v.id = s.visitor_id AND " . sisDateWhere($conn, 's.payment_date', $date_from, $date_to) . "
    WHERE $visitor_where_clause
    GROUP BY sv.id
    ORDER BY request_count DESC
    LIMIT 10
");
$most_requested_rows = [];
$most_requested_totals = ['requests' => 0, 'units' => 0, 'revenue' => 0];
if ($most_requested) {
    while ($mr = mysqli_fetch_assoc($most_requested)) {
        $most_requested_rows[] = $mr;
        $most_requested_totals['requests'] += (int)$mr['request_count'];
        $most_requested_totals['units'] += (int)$mr['total_units'];
        $most_requested_totals['revenue'] += (float)$mr['total_revenue'];
    }
}

$complaintReasonSelect = $has_complaint_reasons ? "COALESCE(cr.reason, p.name)" : "p.name";
$complaintReasonJoin = $has_complaint_reasons ? "LEFT JOIN complaint_reasons cr ON v.complaint_reason_id = cr.id" : "";
$complaint_logs = mysqli_query($conn, "
    SELECT v.visit_time, v.name as customer_name, v.contact,
           $complaintReasonSelect as complaint_type, v.status, u.full_name as agent_name, o.name as outlet_name
    FROM visitors v
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    $complaintReasonJoin
    LEFT JOIN users u ON v.agent_id = u.id
    LEFT JOIN outlets o ON v.outlet_id = o.id
    WHERE $visitor_where_clause AND (p.name LIKE '%Complaint%'" . ($has_complaint_reasons ? " OR cr.id IS NOT NULL" : "") . ")
    ORDER BY v.visit_time DESC
    LIMIT 20
");

$isp_device_sales = mysqli_query($conn, "
    SELECT v.visit_time, v.name as customer_name, v.contact,
           sv.name as device_name, ABS(s.amount) as amount, s.payment_status,
           u.full_name as agent_name, o.name as outlet_name
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    WHERE $where_clause
    AND (sv.name LIKE '%Router%' OR sv.name LIKE '%Modem%' OR sv.name LIKE '%CPE%')
    ORDER BY s.payment_date DESC
    LIMIT 20
");

$vip_sales_table = mysqli_query($conn, "
    SELECT v.visit_time, v.name as customer_name, v.contact,
           sv.name as number_type, ABS(s.amount) as amount, s.payment_status,
           u.full_name as agent_name, o.name as outlet_name
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    WHERE $where_clause
    AND (sv.name LIKE '%VIP%' OR sv.name LIKE '%Gold%' OR sv.name LIKE '%Premium%')
    ORDER BY s.payment_date DESC
    LIMIT 20
");

$transactionsExtraSelect = ($has_handling_time || $has_sales_handling_time) ? ", $handleTimeExpr as handling_seconds" : ", NULL as handling_seconds";
$transactionsStartedSelect = $has_sales_handle_started ? ", s.handle_started_at as handle_started_at" : ", NULL as handle_started_at";
$transactionsCompletedSelect = $has_sales_handle_completed ? ", s.handle_completed_at as handle_completed_at" : ", NULL as handle_completed_at";
$transactionsComplaintSelect = $has_complaint_reasons ? ", cr.reason as complaint_reason" : ", NULL as complaint_reason";
$transactionsComplaintJoin = $has_complaint_reasons ? "LEFT JOIN complaint_reasons cr ON v.complaint_reason_id = cr.id" : "";
$transactions_table = mysqli_query($conn, "
    SELECT v.name as customer_name, o.region, o.name as showroom,
           sv.name as service_type, p.name as purpose_of_visit,
           s.amount, u.full_name as staff_name, s.payment_date
           $transactionsExtraSelect
           $transactionsStartedSelect
           $transactionsCompletedSelect
           $transactionsComplaintSelect
    FROM sales s
    LEFT JOIN visitors v ON s.visitor_id = v.id
    LEFT JOIN services sv ON v.service_id = sv.id
    LEFT JOIN purpose_of_visit p ON v.purpose_id = p.id
    $transactionsComplaintJoin
    LEFT JOIN users u ON s.agent_id = u.id
    LEFT JOIN outlets o ON s.outlet_id = o.id
    WHERE $where_clause
    ORDER BY s.payment_date DESC
    LIMIT 100
");

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Analytics Dashboard - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">

<style>
.kpi-filter-card {
    border: 1px solid var(--sis-border, #e2e8f0);
    border-radius: 18px;
    background: var(--sis-surface, #fff);
    box-shadow: var(--sis-shadow, 0 12px 32px rgba(15, 23, 42, 0.08));
    margin-bottom: 22px;
    overflow: hidden;
}
.kpi-filter-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--sis-border, #e2e8f0);
    background: var(--sis-surface-soft, #f8fafc);
}
.kpi-filter-title {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: var(--sis-heading, #0f172a);
}
.kpi-filter-subtitle {
    color: var(--sis-muted, #64748b);
    font-size: 13px;
    margin-top: 2px;
}
.kpi-filter-body { padding: 18px; }
.kpi-quick-ranges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.kpi-quick-ranges a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 11px;
    border-radius: 999px;
    border: 1px solid var(--sis-border, #e2e8f0);
    background: var(--sis-surface, #fff);
    color: var(--sis-text, #172033);
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}
.kpi-quick-ranges a:hover { border-color: var(--sis-primary, #e30613); color: var(--sis-primary, #e30613); }
.kpi-filter-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 14px;
    align-items: end;
}
.kpi-filter-field { grid-column: span 3; }
.kpi-filter-field.kpi-filter-field-small { grid-column: span 2; }
.kpi-filter-field label {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 800;
    color: var(--sis-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.kpi-filter-field .form-control,
.kpi-filter-field .form-select {
    min-height: 43px;
    border-radius: 12px;
}
.kpi-filter-actions {
    grid-column: span 12;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 4px;
}
.kpi-filter-actions-left,
.kpi-filter-actions-right {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.kpi-active-filter-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 11px;
    border-radius: 999px;
    background: var(--sis-primary-soft, rgba(227, 6, 19, 0.1));
    color: var(--sis-primary, #e30613);
    font-size: 12px;
    font-weight: 800;
}
@media (max-width: 1200px) {
    .kpi-filter-field { grid-column: span 4; }
    .kpi-filter-field.kpi-filter-field-small { grid-column: span 3; }
}
@media (max-width: 768px) {
    .kpi-filter-head { align-items: flex-start; flex-direction: column; }
    .kpi-filter-field,
    .kpi-filter-field.kpi-filter-field-small,
    .kpi-filter-actions { grid-column: span 12; }
    .kpi-filter-actions { align-items: stretch; }
    .kpi-filter-actions-left,
    .kpi-filter-actions-right { width: 100%; }
    .kpi-filter-actions .btn,
    .kpi-filter-actions .btn-reset,
    .kpi-filter-actions button { flex: 1 1 auto; text-align: center; justify-content: center; }
}
.kpi-card-sim-new { border-left: 4px solid #28a745; }
.kpi-card-sim-renewal { border-left: 4px solid #fd7e14; }
.kpi-icon-sim-new { background: rgba(40,167,69,0.1); color: #28a745; }
.kpi-icon-sim-renewal { background: rgba(253,126,20,0.1); color: #fd7e14; }
</style>

</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>
    <div class="loading-overlay" id="loadingOverlay"><div class="loading-spinner"></div></div>

    <div class="main-content" id="mainContent">
<div class="page-header"><h3><i class="bi bi-graph-up sis-u-20e33279"></i>Analytics Dashboard</h3><span class="date-badge"><i class="bi bi-calendar3"></i> <?php echo date('l, d M Y'); ?></span></div>

        
        <div class="kpi-filter-card">
            <div class="kpi-filter-head">
                <div>
                    <h5 class="kpi-filter-title"><i class="bi bi-funnel"></i> Advanced KPI Filters</h5>
                    <div class="kpi-filter-subtitle">Filter all KPI cards, charts, and report tables by date, showroom, service, purpose, staff, and handle time.</div>
                </div>
                <div class="kpi-quick-ranges">
                    <a href="reports.php?date_from=<?php echo $today; ?>&date_to=<?php echo $today; ?>"><i class="bi bi-calendar-day"></i> Today</a>
                    <a href="reports.php?date_from=<?php echo $month_start; ?>&date_to=<?php echo $today; ?>"><i class="bi bi-calendar-month"></i> This Month</a>
                    <a href="reports.php?date_from=<?php echo $last_30_start; ?>&date_to=<?php echo $today; ?>"><i class="bi bi-calendar-range"></i> Last 30 Days</a>
                    <a href="reports.php?date_from=<?php echo $year_start; ?>&date_to=<?php echo $today; ?>"><i class="bi bi-calendar4-range"></i> This Year</a>
                </div>
            </div>
            <form id="filterForm" method="GET" action="reports.php" class="kpi-filter-body">
                <div class="kpi-filter-grid">
                    <div class="kpi-filter-field kpi-filter-field-small">
                        <label for="date_from"><i class="bi bi-calendar-range"></i> From Date</label>
                        <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo e($date_from); ?>">
                    </div>
                    <div class="kpi-filter-field kpi-filter-field-small">
                        <label for="date_to"><i class="bi bi-calendar-check"></i> To Date</label>
                        <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo e($date_to); ?>">
                    </div>
                    <div class="kpi-filter-field">
                        <label for="filter_region"><i class="bi bi-geo-alt"></i> Region</label>
                        <select name="region" id="filter_region" class="form-select">
                            <option value="">All Regions</option>
                            <?php while($r = mysqli_fetch_assoc($regions)): ?>
                                <option value="<?php echo e($r['region']); ?>" <?php echo $filter_region == $r['region'] ? 'selected' : ''; ?>><?php echo e($r['region']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="kpi-filter-field">
                        <label for="filter_outlet"><i class="bi bi-building"></i> Showroom</label>
                        <select name="outlet_id" id="filter_outlet" class="form-select">
                            <option value="">All Showrooms</option>
                            <?php while($o = mysqli_fetch_assoc($outlets_list)): ?>
                                <option value="<?php echo (int)$o['id']; ?>" <?php echo $filter_outlet == $o['id'] ? 'selected' : ''; ?>><?php echo e($o['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="kpi-filter-field">
                        <label for="filter_service"><i class="bi bi-tag"></i> Service Type</label>
                        <select name="service_id" id="filter_service" class="form-select">
                            <option value="">All Services</option>
                            <?php while($sv = mysqli_fetch_assoc($services_list)): ?>
                                <option value="<?php echo (int)$sv['id']; ?>" <?php echo $filter_service == $sv['id'] ? 'selected' : ''; ?>><?php echo e($sv['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="kpi-filter-field">
                        <label for="filter_purpose"><i class="bi bi-tags"></i> Purpose</label>
                        <select name="purpose_id" id="filter_purpose" class="form-select">
                            <option value="">All Purposes</option>
                            <?php while($p = mysqli_fetch_assoc($purposes_list)): ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo $filter_purpose == $p['id'] ? 'selected' : ''; ?>><?php echo e($p['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="kpi-filter-field">
                        <label for="filter_agent"><i class="bi bi-person"></i> Staff</label>
                        <select name="agent_id" id="filter_agent" class="form-select">
                            <option value="">All Agents</option>
                            <?php while($a = mysqli_fetch_assoc($agents_list)): ?>
                                <option value="<?php echo (int)$a['id']; ?>" <?php echo $filter_agent == $a['id'] ? 'selected' : ''; ?>><?php echo e($a['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if($has_handling_time || $has_sales_handling_time): ?>
                        <div class="kpi-filter-field kpi-filter-field-small">
                            <label for="handle_min"><i class="bi bi-stopwatch"></i> Min Handle</label>
                            <input type="number" class="form-control" id="handle_min" name="handle_min" min="0" placeholder="Minutes" value="<?php echo e($filter_handle_min); ?>">
                        </div>
                        <div class="kpi-filter-field kpi-filter-field-small">
                            <label for="handle_max"><i class="bi bi-stopwatch-fill"></i> Max Handle</label>
                            <input type="number" class="form-control" id="handle_max" name="handle_max" min="0" placeholder="Minutes" value="<?php echo e($filter_handle_max); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="kpi-filter-actions">
                        <div class="kpi-filter-actions-left">
                            <?php if($has_active_filters): ?>
                                <span class="kpi-active-filter-badge"><i class="bi bi-check2-circle"></i> Filters active</span>
                            <?php else: ?>
                                <span class="text-muted small"><i class="bi bi-info-circle"></i> Showing the default last 30 days.</span>
                            <?php endif; ?>
                        </div>
                        <div class="kpi-filter-actions-right">
                            <a href="reports.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply Filters</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="action-buttons"><button class="btn-excel" onclick="exportToExcel()"><i class="bi bi-file-earmark-excel"></i> Export Excel</button><button class="btn-pdf" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button><button class="btn-print" onclick="window.print()"><i class="bi bi-printer"></i> Print Report</button></div>

        <div class="card mb-4 sis-support-report-cta">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-1"><i class="bi bi-headset text-danger"></i> Complaint / Support Reports</h5>
                    <p class="text-muted mb-0">Open the full complaint and support report with status filters, submitted fields, and CSV export.</p>
                </div>
                <a href="support_reports.php" class="btn btn-danger"><i class="bi bi-arrow-right-circle"></i> View Complaint Reports</a>
            </div>
        </div>

        
        <div class="kpi-section">
            <div class="kpi-section-header"><i class="bi bi-bar-chart"></i> General Overview</div>
            <div class="kpi-cards">
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-cash-stack"></i></div><div class="kpi-label">Total Revenue</div><div class="kpi-value"><?php echo $currency_symbol; ?> <?php echo number_format($general_kpis['total_revenue'] ?? 0, 2); ?></div><div class="kpi-trend"><i class="bi bi-arrow-up"></i> +12.5% vs last period</div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-cash"></i></div><div class="kpi-label">Total Withdrawals</div><div class="kpi-value sis-u-b755784c"><?php echo $currency_symbol; ?> <?php echo number_format($general_kpis['total_withdrawals'] ?? 0, 2); ?></div><div class="kpi-revenue withdrawal"><i class="bi bi-cash"></i> Money paid out</div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-arrow-left-right"></i></div><div class="kpi-label">Net Cash Flow</div><div class="kpi-value sis-u-caaaed57"><?php echo $currency_symbol; ?> <?php echo number_format($general_kpis['net_cash_flow'] ?? 0, 2); ?></div><div class="kpi-trend">Revenue − Withdrawals</div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-receipt"></i></div><div class="kpi-label">Total Transactions</div><div class="kpi-value"><?php echo number_format($general_kpis['total_transactions'] ?? 0); ?></div><div class="kpi-trend"><?php echo $general_kpis['total_customers'] ?? 0; ?> unique customers</div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-person"></i></div><div class="kpi-label">Avg Revenue/Customer</div><div class="kpi-value"><?php echo $currency_symbol; ?> <?php echo number_format($general_kpis['avg_revenue_per_customer'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-trophy"></i></div><div class="kpi-label">Top Region</div><div class="kpi-value"><?php echo $general_kpis['top_region'] ?? 'N/A'; ?></div><div class="kpi-trend">Top Showroom: <?php echo $general_kpis['top_outlet'] ?? 'N/A'; ?></div></div>
            </div>
        </div>

        <!-- Telecom KPIs with New SIM and SIM Renewal/Replacement -->
        <div class="kpi-section">
            <div class="kpi-section-header"><i class="bi bi-wifi"></i> Telecom KPIs</div>
            <div class="kpi-cards">
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-box-seam"></i></div><div class="kpi-label">Bundles Sold</div><div class="kpi-value"><?php echo number_format($telecom_kpis['bundles_sold_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['bundles_sold_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-phone"></i></div><div class="kpi-label">Airtime Top-Ups</div><div class="kpi-value"><?php echo number_format($telecom_kpis['airtime_topups_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['airtime_topups_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-wifi"></i></div><div class="kpi-label">Data Top-Ups</div><div class="kpi-value"><?php echo number_format($telecom_kpis['data_topups_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['data_topups_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-arrow-left-right"></i></div><div class="kpi-label">EVC Transactions</div><div class="kpi-value"><?php echo number_format($telecom_kpis['evc_transactions_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['evc_transactions_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-star"></i></div><div class="kpi-label">VIP Numbers Sold</div><div class="kpi-value"><?php echo number_format($telecom_kpis['vip_numbers_sold_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['vip_numbers_sold_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-shield"></i></div><div class="kpi-label">Bronze Numbers Sold</div><div class="kpi-value"><?php echo number_format($telecom_kpis['bronze_numbers_sold_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['bronze_numbers_sold_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-gem"></i></div><div class="kpi-label">Premium Requests</div><div class="kpi-value"><?php echo number_format($telecom_kpis['premium_requests_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['premium_requests_rev'] ?? 0, 2); ?></div></div>
                
                <!-- ============================================ -->
                <!-- eSIM REQUESTS - UPDATED WITH EXACT MATCHING -->
                <!-- ============================================ -->
                <div class="kpi-card" style="border-left: 4px solid #6f42c1;">
                    <div class="kpi-icon" style="background: rgba(111,66,193,0.1); color: #6f42c1;"><i class="bi bi-sim-fill"></i></div>
                    <div class="kpi-label">eSIM Requests</div>
                    <div class="kpi-value"><?php echo number_format($telecom_kpis['esim_requests_qty'] ?? 0); ?></div>
                    <div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['esim_requests_rev'] ?? 0, 2); ?></div>
                </div>
                
                <!-- ============================================ -->
                <!-- eSIM REPLACEMENTS - UPDATED WITH EXACT MATCHING -->
                <!-- ============================================ -->
                <div class="kpi-card" style="border-left: 4px solid #fd7e14;">
                    <div class="kpi-icon" style="background: rgba(253,126,20,0.1); color: #fd7e14;"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="kpi-label">eSIM Replacements</div>
                    <div class="kpi-value"><?php echo number_format($telecom_kpis['esim_replacements_qty'] ?? 0); ?></div>
                    <div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['esim_replacements_rev'] ?? 0, 2); ?></div>
                </div>
                
                <!-- ============================================ -->
                <!-- NEW SIM CARD -->
                <!-- ============================================ -->
                <div class="kpi-card kpi-card-sim-new">
                    <div class="kpi-icon kpi-icon-sim-new"><i class="bi bi-sim-fill"></i></div>
                    <div class="kpi-label">New SIM Card</div>
                    <div class="kpi-value"><?php echo number_format($telecom_kpis['new_sim_qty'] ?? 0); ?></div>
                    <div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['new_sim_rev'] ?? 0, 2); ?></div>
                </div>
                
                <!-- ============================================ -->
                <!-- SIM CARD RENEWAL / REPLACEMENT -->
                <!-- ============================================ -->
                <div class="kpi-card kpi-card-sim-renewal">
                    <div class="kpi-icon kpi-icon-sim-renewal"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="kpi-label">SIM Card Renewal/Replacement</div>
                    <div class="kpi-value"><?php echo number_format($telecom_kpis['sim_renewal_qty'] ?? 0); ?></div>
                    <div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($telecom_kpis['sim_renewal_rev'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>

        
        <div class="kpi-section">
            <div class="kpi-section-header"><i class="bi bi-hdd-network"></i> ISP & Broadband KPIs</div>
            <div class="kpi-cards">
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-router"></i></div><div class="kpi-label">ISP Devices Sold</div><div class="kpi-value"><?php echo number_format($isp_kpis['isp_devices_sold_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($isp_kpis['isp_devices_sold_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-trophy"></i></div><div class="kpi-label">Most Sold Device</div><div class="kpi-value sis-u-af18eac8"><?php echo $isp_kpis['most_sold_device'] ?? 'N/A'; ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-hdd-stack"></i></div><div class="kpi-label">Cambium Routers</div><div class="kpi-value"><?php echo number_format($isp_kpis['cambium_routers_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($isp_kpis['cambium_routers_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-wifi"></i></div><div class="kpi-label">CPE Routers</div><div class="kpi-value"><?php echo number_format($isp_kpis['cpe_routers_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($isp_kpis['cpe_routers_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-house-door"></i></div><div class="kpi-label">Home Broadband Users</div><div class="kpi-value"><?php echo number_format($isp_kpis['home_broadband_users_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($isp_kpis['home_broadband_users_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-person-plus"></i></div><div class="kpi-label">New Broadband Users</div><div class="kpi-value"><?php echo number_format($isp_kpis['new_broadband_users_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($isp_kpis['new_broadband_users_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-tools"></i></div><div class="kpi-label">New Installations</div><div class="kpi-value"><?php echo number_format($isp_kpis['new_installations_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($isp_kpis['new_installations_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-calendar-check"></i></div><div class="kpi-label">Installation Requests</div><div class="kpi-value"><?php echo number_format($isp_kpis['installation_requests_qty'] ?? 0); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-cash-stack"></i></div><div class="kpi-label">ISP Revenue</div><div class="kpi-value"><?php echo $currency_symbol; ?> <?php echo number_format($isp_kpis['isp_revenue'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-arrow-repeat"></i></div><div class="kpi-label">ISP Renewals</div><div class="kpi-value"><?php echo number_format($isp_kpis['isp_renewals_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($isp_kpis['isp_renewals_rev'] ?? 0, 2); ?></div></div>
            </div>
        </div>

        
        <div class="kpi-section">
            <div class="kpi-section-header"><i class="bi bi-cash-stack"></i> ComCach KPIs</div>
            <div class="kpi-cards">
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-bank"></i></div><div class="kpi-label">ComCach Deposits</div><div class="kpi-value"><?php echo number_format($comcach_kpis['comcach_deposits_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($comcach_kpis['comcach_deposits_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-receipt"></i></div><div class="kpi-label">ComCach Bills</div><div class="kpi-value"><?php echo number_format($comcach_kpis['comcach_bills_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($comcach_kpis['comcach_bills_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-arrow-left-right"></i></div><div class="kpi-label">ComCach Transactions</div><div class="kpi-value"><?php echo number_format($comcach_kpis['comcach_transactions_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($comcach_kpis['comcach_transactions_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-phone"></i></div><div class="kpi-label">ComCach Airtime</div><div class="kpi-value"><?php echo number_format($comcach_kpis['comcach_airtime_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($comcach_kpis['comcach_airtime_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-wifi"></i></div><div class="kpi-label">ComCach Data</div><div class="kpi-value"><?php echo number_format($comcach_kpis['comcach_data_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($comcach_kpis['comcach_data_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-cash"></i></div><div class="kpi-label">ComCach Withdrawals</div><div class="kpi-value"><?php echo number_format($comcach_kpis['comcach_withdrawals_qty'] ?? 0); ?></div><div class="kpi-revenue withdrawal"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($comcach_kpis['comcach_withdrawals_rev'] ?? 0, 2); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-send"></i></div><div class="kpi-label">ComCach Transfers</div><div class="kpi-value"><?php echo number_format($comcach_kpis['comcach_transfers_qty'] ?? 0); ?></div><div class="kpi-revenue"><i class="bi bi-cash"></i> <?php echo $currency_symbol; ?> <?php echo number_format($comcach_kpis['comcach_transfers_rev'] ?? 0, 2); ?></div></div>
            </div>
        </div>

        
        <div class="kpi-section">
            <div class="kpi-section-header"><i class="bi bi-headset"></i> Customer Service KPIs</div>
            <div class="kpi-cards">
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-chat-dots"></i></div><div class="kpi-label">Complaints Submitted</div><div class="kpi-value"><?php echo number_format($cs_kpis['complaints_submitted'] ?? 0); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-check-circle"></i></div><div class="kpi-label">Complaints Resolved</div><div class="kpi-value"><?php echo number_format($cs_kpis['complaints_resolved'] ?? 0); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-percent"></i></div><div class="kpi-label">Resolution Rate</div><div class="kpi-value"><?php echo $cs_kpis['resolution_rate']; ?>%</div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-headset"></i></div><div class="kpi-label">Support Requests</div><div class="kpi-value"><?php echo number_format($cs_kpis['support_requests'] ?? 0); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-chat-text"></i></div><div class="kpi-label">Feedback/Suggestions</div><div class="kpi-value"><?php echo number_format($cs_kpis['feedback_suggestions'] ?? 0); ?></div></div>
                <div class="kpi-card"><div class="kpi-icon"><i class="bi bi-stopwatch"></i></div><div class="kpi-label">Avg Handle Time</div><div class="kpi-value sis-u-1551e6d7"><?php echo ($has_handling_time || $has_sales_handling_time) ? formatSecondsDuration($cs_kpis['avg_handling_seconds'] ?? 0) : 'Not enabled'; ?></div><div class="kpi-trend">Calculated on submit</div></div>
            </div>
        </div>

        
        <div class="chart-grid">
            <div class="chart-card"><div class="chart-header"><i class="bi bi-pie-chart"></i> Revenue by Region</div><div class="chart-body"><canvas id="revenueByRegionChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><i class="bi bi-bar-chart-steps"></i> Revenue by Showroom</div><div class="chart-body"><canvas id="revenueByOutletChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><i class="bi bi-graph-up"></i> Monthly Sales Trend</div><div class="chart-body"><canvas id="monthlyTrendChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><i class="bi bi-pie-chart"></i> Purpose of Visit</div><div class="chart-body"><canvas id="purposeChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><i class="bi bi-donut"></i> Service Type Distribution</div><div class="chart-body"><canvas id="serviceChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><i class="bi bi-bar-chart"></i> Most Sold Devices</div><div class="chart-body"><canvas id="devicesChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><i class="bi bi-hdd-stack"></i> ISP Installations by Region</div><div class="chart-body"><canvas id="ispInstallationsChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><i class="bi bi-star"></i> VIP Numbers Sold by Region</div><div class="chart-body"><canvas id="vipByRegionChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><i class="bi bi-bar-chart"></i> Complaints vs Resolved (Last 14 Days)</div><div class="chart-body"><canvas id="complaintsChart"></canvas></div></div>
        </div>

        <div class="card mt-4"><div class="card-header"><i class="bi bi-list-check"></i> Transaction History</div><div class="card-body p-0"><table id="transactionsTable" class="table table-striped sis-u-8588e44d"><thead><tr><th>Date</th><th>Customer</th><th>Region</th><th>Showroom</th><th>Service</th><th>Purpose</th><th>Handle Time</th><th>Amount</th><th>Staff</th></tr></thead><tbody><?php while($row = mysqli_fetch_assoc($transactions_table)): ?><tr><td><?php echo date('d/m/Y H:i', strtotime($row['payment_date'])); ?></td><td><?php echo e($row['customer_name'] ?? '—'); ?></td><td><?php echo e($row['region'] ?? '—'); ?></td><td><?php echo e($row['showroom'] ?? '—'); ?></td><td><?php echo e($row['service_type'] ?? '—'); ?></td><td><?php echo e($row['purpose_of_visit'] ?? '—'); ?></td><td><?php echo ($has_handling_time || $has_sales_handling_time) ? renderHandleTimeCell($row['handling_seconds'] ?? null, $row['handle_started_at'] ?? null, $row['handle_completed_at'] ?? null) : '—'; ?></td><td><?php echo $currency_symbol; ?> <?php echo number_format(abs($row['amount']), 2); ?></td><td><?php echo e($row['staff_name'] ?? '—'); ?></td></tr><?php endwhile; ?></tbody></table></div></div>

        <div class="card mt-4"><div class="card-header"><i class="bi bi-hdd-stack"></i> ISP Device Sales</div><div class="card-body p-0"><table id="ispDevicesTable" class="table table-striped sis-u-8588e44d"><thead><tr><th>Date</th><th>Customer</th><th>Device</th><th>Amount</th><th>Status</th><th>Agent</th><th>Outlet</th></tr></thead><tbody><?php while($row = mysqli_fetch_assoc($isp_device_sales)): ?><tr><td><?php echo date('d/m/Y', strtotime($row['visit_time'])); ?></td><td><?php echo htmlspecialchars($row['customer_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['device_name'] ?? '—'); ?></td><td><?php echo $currency_symbol; ?> <?php echo number_format($row['amount'], 2); ?></td><td><span class="badge-<?php echo strtolower($row['payment_status']); ?>"><?php echo $row['payment_status']; ?></span></td><td><?php echo htmlspecialchars($row['agent_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['outlet_name'] ?? '—'); ?></td></tr><?php endwhile; ?></tbody></table></div></div>

        <div class="card mt-4"><div class="card-header"><i class="bi bi-star"></i> VIP Number Sales</div><div class="card-body p-0"><table id="vipNumbersTable" class="table table-striped sis-u-8588e44d"><thead><tr><th>Date</th><th>Customer</th><th>Number Type</th><th>Amount</th><th>Status</th><th>Agent</th><th>Outlet</th></tr></thead><tbody><?php while($row = mysqli_fetch_assoc($vip_sales_table)): ?><tr><td><?php echo date('d/m/Y', strtotime($row['visit_time'])); ?></td><td><?php echo htmlspecialchars($row['customer_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['number_type'] ?? '—'); ?></td><td><?php echo $currency_symbol; ?> <?php echo number_format($row['amount'], 2); ?></td><td><span class="badge-<?php echo strtolower($row['payment_status']); ?>"><?php echo $row['payment_status']; ?></span></td><td><?php echo htmlspecialchars($row['agent_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['outlet_name'] ?? '—'); ?></td></tr><?php endwhile; ?></tbody></table></div></div>

        <div class="card mt-4"><div class="card-header"><i class="bi bi-chat-dots"></i> Complaint Logs</div><div class="card-body p-0"><table id="complaintsTable" class="table table-striped sis-u-8588e44d"><thead><tr><th>Date</th><th>Customer</th><th>Contact</th><th>Reason</th><th>Status</th><th>Agent</th><th>Outlet</th></tr></thead><tbody><?php while($row = mysqli_fetch_assoc($complaint_logs)): ?><tr><td><?php echo date('d/m/Y H:i', strtotime($row['visit_time'])); ?></td><td><?php echo htmlspecialchars($row['customer_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['contact'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['complaint_type'] ?? '—'); ?></td><td><span class="badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td><td><?php echo htmlspecialchars($row['agent_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($row['outlet_name'] ?? '—'); ?></td></tr><?php endwhile; ?></tbody></table></div></div>

        
        <div class="card mt-4">
            <div class="card-header"><i class="bi bi-trophy"></i> Top Performing Showrooms</div>
            <div class="card-body p-0">
                <table id="topShowroomsTable" class="table table-striped sis-u-8588e44d">
                    <thead>
                        <tr>
                            <th>Showroom</th>
                            <th>Region</th>
                            <th>Visitors</th>
                            <th>Sales</th>
                            <th>Revenue</th>
                            <th>Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($top_showrooms) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($top_showrooms)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['region'] ?? '—'); ?></td>
                                <td><?php echo number_format($row['visitors']); ?></td>
                                <td><?php echo number_format($row['sales_count']); ?></td>
                                <td class="text-success"><?php echo $currency_symbol; ?> <?php echo number_format($row['revenue'], 2); ?></td>
                                <td class="text-warning"><?php echo $currency_symbol; ?> <?php echo number_format($row['pending'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center">No data found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-4"><div class="card-header"><i class="bi bi-bar-chart"></i> Most Requested Services</div><div class="card-body p-0"><table id="topServicesTable" class="table table-striped sis-u-8588e44d"><thead><tr><th>Service</th><th>Requests</th><th>Total</th><th>Total Revenue</th></tr></thead><tbody><?php foreach($most_requested_rows as $row): ?><tr><td><?php echo e($row['name'] ?? '—'); ?></td><td><?php echo number_format($row['request_count']); ?></td><td><?php echo number_format($row['total_units']); ?></td><td><?php echo $currency_symbol; ?> <?php echo number_format($row['total_revenue'], 2); ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th>Summary Total</th><th><?php echo number_format($most_requested_totals['requests']); ?></th><th><?php echo number_format($most_requested_totals['units']); ?></th><th><?php echo $currency_symbol; ?> <?php echo number_format($most_requested_totals['revenue'], 2); ?></th></tr></tfoot></table></div></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

    <script>
        function toggleSidebar() { const sidebar = document.getElementById('sidebar') || document.querySelector('[data-sis-sidebar]'); if (sidebar) sidebar.classList.toggle('open'); }
        document.addEventListener('click', function(event) { const sidebar = document.getElementById('sidebar') || document.querySelector('[data-sis-sidebar]'); const menuToggle = document.querySelector('.menu-toggle') || document.querySelector('[data-sis-sidebar-toggle]'); if (sidebar && menuToggle && window.innerWidth <= 768 && !sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('open')) { sidebar.classList.remove('open'); } });
        $(document).ready(function() {
            const reportMenu = [[10,25,50,75,100,150,-1],[10,25,50,75,100,150,'All']];
            const commonReportTable = { pageLength: 25, lengthMenu: reportMenu, scrollX: true, autoWidth: false, responsive: false, language: { search: "Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ entries" } };
            $('#transactionsTable').DataTable(Object.assign({}, commonReportTable, { order: [[0, 'desc']] }));
            $('#ispDevicesTable').DataTable(Object.assign({}, commonReportTable, { pageLength: 10 }));
            $('#vipNumbersTable').DataTable(Object.assign({}, commonReportTable, { pageLength: 10 }));
            $('#complaintsTable').DataTable(Object.assign({}, commonReportTable, { pageLength: 10 }));
            $('#topShowroomsTable').DataTable(Object.assign({}, commonReportTable, { pageLength: 10 }));
            $('#topServicesTable').DataTable(Object.assign({}, commonReportTable, { pageLength: 10 }));
        });
        const revenueByRegionData = <?php 
            $regions_array = []; $revenue_array = []; mysqli_data_seek($revenue_by_region, 0); while($r = mysqli_fetch_assoc($revenue_by_region)) { $regions_array[] = $r['region']; $revenue_array[] = $r['revenue']; } echo json_encode(['labels' => $regions_array, 'data' => $revenue_array]); 
        ?>;
        if(revenueByRegionData.labels.length > 0) { new Chart(document.getElementById('revenueByRegionChart'), { type: 'bar', data: { labels: revenueByRegionData.labels, datasets: [{ label: 'Revenue (<?php echo $currency_symbol; ?>)', data: revenueByRegionData.data, backgroundColor: 'rgba(227,6,19,0.7)', borderRadius: 8 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); }

        const revenueByOutletData = <?php 
            $outlet_names = []; $outlet_revenue = []; mysqli_data_seek($revenue_by_outlet, 0); while($o = mysqli_fetch_assoc($revenue_by_outlet)) { $outlet_names[] = $o['name']; $outlet_revenue[] = $o['revenue']; } echo json_encode(['labels' => $outlet_names, 'data' => $outlet_revenue]); 
        ?>;
        if(revenueByOutletData.labels.length > 0) { new Chart(document.getElementById('revenueByOutletChart'), { type: 'bar', data: { labels: revenueByOutletData.labels, datasets: [{ label: 'Revenue (<?php echo $currency_symbol; ?>)', data: revenueByOutletData.data, backgroundColor: 'rgba(40,167,69,0.7)', borderRadius: 8 }] }, options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { position: 'bottom' } } } }); }

        const monthlyData = <?php 
            $months = []; $transactions = []; $revenues = []; while($m = mysqli_fetch_assoc($monthly_trend)) { $months[] = $m['month']; $transactions[] = $m['transactions']; $revenues[] = $m['revenue']; } echo json_encode(['labels' => $months, 'transactions' => $transactions, 'revenues' => $revenues]); 
        ?>;
        if(monthlyData.labels.length > 0) { new Chart(document.getElementById('monthlyTrendChart'), { type: 'line', data: { labels: monthlyData.labels, datasets: [{ label: 'Transactions', data: monthlyData.transactions, borderColor: '#17a2b8', tension: 0.4, fill: false, yAxisID: 'y' }, { label: 'Revenue (<?php echo $currency_symbol; ?>)', data: monthlyData.revenues, borderColor: '#28a745', tension: 0.4, fill: false, yAxisID: 'y1' }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { title: { display: true, text: 'Transactions' } }, y1: { position: 'right', title: { display: true, text: 'Revenue' }, grid: { drawOnChartArea: false } } } } }); }

        const purposeData = <?php $purp_names = []; $purp_counts = []; while($p = mysqli_fetch_assoc($purpose_distribution)) { $purp_names[] = $p['name']; $purp_counts[] = $p['count']; } echo json_encode(['labels' => $purp_names, 'data' => $purp_counts]); ?>;
        if(purposeData.labels.length > 0) { new Chart(document.getElementById('purposeChart'), { type: 'doughnut', data: { labels: purposeData.labels, datasets: [{ data: purposeData.data, backgroundColor: ['#E30613', '#28a745', '#17a2b8', '#ffc107', '#6c757d', '#dc3545', '#fd7e14'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); }

        const serviceData = <?php $serv_names = []; $serv_counts = []; while($s = mysqli_fetch_assoc($service_distribution)) { $serv_names[] = $s['name']; $serv_counts[] = $s['count']; } echo json_encode(['labels' => $serv_names, 'data' => $serv_counts]); ?>;
        if(serviceData.labels.length > 0) { new Chart(document.getElementById('serviceChart'), { type: 'doughnut', data: { labels: serviceData.labels, datasets: [{ data: serviceData.data, backgroundColor: ['#E30613', '#28a745', '#17a2b8', '#ffc107', '#6c757d', '#dc3545'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); }

        const devicesData = <?php $dev_names = []; $dev_counts = []; while($d = mysqli_fetch_assoc($most_sold_devices)) { $dev_names[] = $d['name']; $dev_counts[] = $d['sold_count']; } echo json_encode(['labels' => $dev_names, 'data' => $dev_counts]); ?>;
        if(devicesData.labels.length > 0) { new Chart(document.getElementById('devicesChart'), { type: 'bar', data: { labels: devicesData.labels, datasets: [{ label: 'Units Sold', data: devicesData.data, backgroundColor: '#fd7e14', borderRadius: 8 }] }, options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { position: 'bottom' } } } }); }

        const ispInstallData = <?php $isp_regions = []; $isp_counts = []; while($i = mysqli_fetch_assoc($isp_installations)) { $isp_regions[] = $i['region']; $isp_counts[] = $i['installations']; } echo json_encode(['labels' => $isp_regions, 'data' => $isp_counts]); ?>;
        if(ispInstallData.labels.length > 0) { new Chart(document.getElementById('ispInstallationsChart'), { type: 'bar', data: { labels: ispInstallData.labels, datasets: [{ label: 'Installations', data: ispInstallData.data, backgroundColor: '#20c997', borderRadius: 8 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); }

        const vipRegionData = <?php $vip_regions = []; $vip_counts = []; while($v = mysqli_fetch_assoc($vip_by_region)) { $vip_regions[] = $v['region']; $vip_counts[] = $v['vip_count']; } echo json_encode(['labels' => $vip_regions, 'data' => $vip_counts]); ?>;
        if(vipRegionData.labels.length > 0) { new Chart(document.getElementById('vipByRegionChart'), { type: 'bar', data: { labels: vipRegionData.labels, datasets: [{ label: 'VIP Numbers Sold', data: vipRegionData.data, backgroundColor: '#6f42c1', borderRadius: 8 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); }

        const complaintData = <?php $dates = []; $complaints = []; $resolved = []; while($c = mysqli_fetch_assoc($complaint_trend)) { $dates[] = date('d/m', strtotime($c['date'])); $complaints[] = $c['complaints']; $resolved[] = $c['resolved']; } echo json_encode(['labels' => $dates, 'complaints' => $complaints, 'resolved' => $resolved]); ?>;
        if(complaintData.labels.length > 0) { new Chart(document.getElementById('complaintsChart'), { type: 'bar', data: { labels: complaintData.labels, datasets: [{ label: 'Complaints Submitted', data: complaintData.complaints, backgroundColor: '#dc3545', borderRadius: 8 }, { label: 'Complaints Resolved', data: complaintData.resolved, backgroundColor: '#28a745', borderRadius: 8 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } }); }

        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            ['transactionsTable','topServicesTable','topShowroomsTable','complaintsTable'].forEach((id) => {
                const table = document.getElementById(id);
                if (table) XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(table), id.replace('Table','').substring(0,31));
            });
            XLSX.writeFile(wb, `comium_report_${new Date().toISOString().split('T')[0]}.xlsx`);
        }
        function exportToPDF() { const { jsPDF } = window.jspdf; const doc = new jsPDF('landscape'); doc.autoTable({ html: '#transactionsTable', theme: 'striped', startY: 20 }); doc.save(`comium_report_${new Date().toISOString().split('T')[0]}.pdf`); }
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            if (dateFrom && dateTo && dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) {
                e.preventDefault();
                alert('From Date cannot be later than To Date.');
                return;
            }
            document.getElementById('loadingOverlay').style.display = 'flex';
        });
        window.addEventListener('load', function() { document.getElementById('loadingOverlay').style.display = 'none'; });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>