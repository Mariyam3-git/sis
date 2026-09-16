<?php
require_once '../config.php';
requireSuperAdmin();
if (function_exists('sisPreventDuplicatePostRefresh')) sisPreventDuplicatePostRefresh();

$message = '';
$message_type = 'info';
$tables = [];
$res = mysqli_query($conn, "SELECT TABLE_NAME, TABLE_ROWS, ENGINE, CREATE_TIME, UPDATE_TIME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
if ($res) while ($row = mysqli_fetch_assoc($res)) $tables[] = $row;
$tableNames = array_map(fn($r) => $r['TABLE_NAME'], $tables);

function sisDbIdentifier($name) {
    $name = trim((string)$name);
    return preg_match('/^[A-Za-z0-9_]+$/', $name) ? $name : '';
}
function sisDbTextShort($value, $width = 140) {
    $value = (string)($value ?? '');
    if (function_exists('mb_strimwidth')) return mb_strimwidth($value, 0, $width, '...');
    return strlen($value) > $width ? substr($value, 0, max(0, $width - 3)) . '...' : $value;
}

function sisDbLogQuery($conn, $query, $type, $affected = null) {
    if (!tableExists($conn, 'sis_db_console_log')) return;
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = mysqli_prepare($conn, 'INSERT INTO sis_db_console_log (user_id, query_text, query_type, affected_rows, ip_address) VALUES (?, ?, ?, ?, ?)');
    if ($stmt) { mysqli_stmt_bind_param($stmt, 'issis', $uid, $query, $type, $affected, $ip); mysqli_stmt_execute($stmt); }
}

$selectedTable = sisDbIdentifier($_GET['table'] ?? '');
if ($selectedTable && !in_array($selectedTable, $tableNames, true)) $selectedTable = '';
$columns = [];
$rows = [];
$rowError = '';
$filterColumn = sisDbIdentifier($_GET['column'] ?? '');
$filterValue = trim((string)($_GET['value'] ?? ''));
$limit = max(10, min(500, (int)($_GET['limit'] ?? 50)));
if ($selectedTable) {
    $cres = mysqli_query($conn, "SHOW COLUMNS FROM `{$selectedTable}`");
    if ($cres) while ($c = mysqli_fetch_assoc($cres)) $columns[] = $c;
    $columnNames = array_map(fn($c) => $c['Field'], $columns);
    if ($filterColumn && !in_array($filterColumn, $columnNames, true)) $filterColumn = '';
    $sql = "SELECT * FROM `{$selectedTable}`";
    $params = [];
    $types = '';
    if ($filterColumn !== '' && $filterValue !== '') {
        $sql .= " WHERE `{$filterColumn}` LIKE ?";
        $params[] = '%' . $filterValue . '%';
        $types .= 's';
    }
    $sql .= " LIMIT {$limit}";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt && $params) mysqli_stmt_bind_param($stmt, $types, ...$params);
    if ($stmt && mysqli_stmt_execute($stmt)) {
        $rres = mysqli_stmt_get_result($stmt);
        while ($rres && ($r = mysqli_fetch_assoc($rres))) $rows[] = $r;
    } else {
        $rowError = mysqli_error($conn);
    }
}

$queryResultColumns = [];
$queryResultRows = [];
$queryAffected = null;
$queryError = '';
$queryText = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_query') {
    if (!verifyCsrf()) {
        $queryError = 'Security check failed. Please refresh and try again.';
    } else {
        $queryText = trim((string)($_POST['query_text'] ?? ''));
        $allowWrite = !empty($_POST['allow_write']);
        $allowDangerous = !empty($_POST['allow_dangerous']);
        if ($queryText === '') {
            $queryError = 'Enter a query first.';
        } else {
            $first = strtoupper(strtok(ltrim($queryText), " \t\r\n"));
            $readOnly = in_array($first, ['SELECT','SHOW','DESCRIBE','DESC','EXPLAIN'], true);
            $dangerous = in_array($first, ['DROP','TRUNCATE'], true) || preg_match('/\bDROP\b|\bTRUNCATE\b/i', $queryText);
            if (!$readOnly && !$allowWrite) {
                $queryError = 'Write queries require the confirmation checkbox.';
            } elseif ($dangerous && !$allowDangerous) {
                $queryError = 'DROP/TRUNCATE queries require the dangerous-query confirmation checkbox.';
            } else {
                $ok = mysqli_query($conn, $queryText);
                if ($ok === false) {
                    $queryError = mysqli_error($conn);
                    sisDbLogQuery($conn, $queryText, $first ?: 'ERROR', null);
                    auditAction($conn, 'DB_CONSOLE_QUERY_ERROR', $_SESSION['user_id'] ?? null, $queryError);
                } elseif ($ok instanceof mysqli_result) {
                    $fields = mysqli_fetch_fields($ok);
                    foreach ($fields as $f) $queryResultColumns[] = $f->name;
                    while (($r = mysqli_fetch_assoc($ok)) && count($queryResultRows) < 500) $queryResultRows[] = $r;
                    sisDbLogQuery($conn, $queryText, $first ?: 'READ', count($queryResultRows));
                    auditAction($conn, 'DB_CONSOLE_READ_QUERY', $_SESSION['user_id'] ?? null, substr($queryText, 0, 250));
                } else {
                    $queryAffected = mysqli_affected_rows($conn);
                    sisDbLogQuery($conn, $queryText, $first ?: 'WRITE', $queryAffected);
                    auditAction($conn, 'DB_CONSOLE_WRITE_QUERY', $_SESSION['user_id'] ?? null, substr($queryText, 0, 250));
                    $message = 'Query executed successfully. Affected rows: ' . (int)$queryAffected;
                    $message_type = 'success';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database Console</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
<link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'Database Console', 'Owner tools for inspecting and repairing the database'); ?>
<div class="container-fluid py-3">
    <?php if($message): ?><div class="alert alert-<?php echo e($message_type); ?>"><i class="bi bi-info-circle"></i> <?php echo e($message); ?></div><?php endif; ?>
    <?php if($queryError): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo e($queryError); ?></div><?php endif; ?>
    <div class="alert alert-warning"><i class="bi bi-shield-lock"></i> System Owner only. Back up the database before running write queries.</div>
    <div class="row g-4">
        <div class="col-xl-3">
            <div class="card h-100"><div class="card-header bg-white"><strong><i class="bi bi-table"></i> Tables</strong></div><div class="list-group list-group-flush sis-db-table-list">
                <?php foreach($tables as $t): ?><a class="list-group-item list-group-item-action <?php echo $selectedTable===$t['TABLE_NAME']?'active':''; ?>" href="?table=<?php echo e($t['TABLE_NAME']); ?>"><div class="d-flex justify-content-between"><span><?php echo e($t['TABLE_NAME']); ?></span><small><?php echo e((string)($t['TABLE_ROWS'] ?? '')); ?></small></div><small class="opacity-75"><?php echo e($t['ENGINE'] ?? ''); ?></small></a><?php endforeach; ?>
            </div></div>
        </div>
        <div class="col-xl-9">
            <div class="card mb-4"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong><i class="bi bi-funnel"></i> Table Browser</strong><?php if($selectedTable): ?><span class="sis-pill-soft"><?php echo e($selectedTable); ?></span><?php endif; ?></div><div class="card-body">
                <?php if(!$selectedTable): ?><div class="text-muted py-4 text-center">Choose a table to view rows and columns.</div><?php else: ?>
                <form class="row g-2 align-items-end mb-3" method="get"><input type="hidden" name="table" value="<?php echo e($selectedTable); ?>"><div class="col-md-4"><label class="form-label">Column</label><select class="form-select" name="column"><option value="">No filter</option><?php foreach($columns as $c): ?><option value="<?php echo e($c['Field']); ?>" <?php echo $filterColumn===$c['Field']?'selected':''; ?>><?php echo e($c['Field']); ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Contains</label><input class="form-control" name="value" value="<?php echo e($filterValue); ?>"></div><div class="col-md-2"><label class="form-label">Limit</label><input class="form-control" type="number" name="limit" value="<?php echo (int)$limit; ?>" min="10" max="500"></div><div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i> View</button></div></form>
                <?php if($rowError): ?><div class="alert alert-danger"><?php echo e($rowError); ?></div><?php endif; ?>
                <div class="sis-query-result"><table class="table table-sm table-striped mb-0"><thead><tr><?php foreach($columns as $c): ?><th><?php echo e($c['Field']); ?><small class="d-block text-muted"><?php echo e($c['Type']); ?></small></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $r): ?><tr><?php foreach($columns as $c): $v=$r[$c['Field']] ?? ''; ?><td><?php echo e(sisDbTextShort($v, 120)); ?></td><?php endforeach; ?></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="<?php echo max(1,count($columns)); ?>" class="text-center py-4 text-muted">No rows found.</td></tr><?php endif; ?></tbody></table></div>
                <?php endif; ?>
            </div></div>
            <div class="card"><div class="card-header bg-white"><strong><i class="bi bi-terminal"></i> Run Query</strong></div><div class="card-body">
                <form method="post" data-confirm-message="Run this database query?"><?php echo csrfField(); ?><input type="hidden" name="action" value="run_query"><label class="form-label">SQL Query</label><textarea class="form-control sis-code-box" rows="7" name="query_text" placeholder="SELECT * FROM users LIMIT 20"><?php echo e($queryText); ?></textarea><div class="row g-2 mt-2"><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_write" value="1" id="allowWrite"><label class="form-check-label" for="allowWrite">I understand this is a write/repair query</label></div></div><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_dangerous" value="1" id="allowDanger"><label class="form-check-label text-danger" for="allowDanger">Allow DROP/TRUNCATE dangerous queries</label></div></div></div><button class="btn btn-dark mt-3"><i class="bi bi-play-fill"></i> Run Query</button></form>
                <?php if($queryResultColumns): ?><hr><h6>Query Results (max 500 rows)</h6><div class="sis-query-result"><table class="table table-sm table-striped mb-0"><thead><tr><?php foreach($queryResultColumns as $c): ?><th><?php echo e($c); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($queryResultRows as $r): ?><tr><?php foreach($queryResultColumns as $c): ?><td><?php echo e(sisDbTextShort($r[$c] ?? '', 160)); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
<?php sisRenderLayoutEnd(); ?>
</body></html>
