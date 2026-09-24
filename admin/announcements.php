<?php
require_once '../config.php';
require_once dirname(__DIR__) . '/include/announcement_helpers.php';
requireRole('admin');
sisEnsureAnnouncementTables($conn);

function sisAnnouncementNormalizeDate($value) {
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    $value = str_replace('T', ' ', $value);
    if (strlen($value) === 16) $value .= ':00';
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function sisAnnouncementDateInput($value) {
    $value = trim((string)($value ?? ''));
    if ($value === '' || $value === '0000-00-00 00:00:00') return '';
    $ts = strtotime($value);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

function sisAnnouncementManageUrl($extra = []) {
    $params = [];
    foreach ($extra as $k => $v) {
        if ($v !== null && $v !== '') $params[$k] = $v;
    }
    return 'announcements.php' . ($params ? ('?' . http_build_query($params)) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = 'announcements.php';
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if (!verifyCsrf()) {
        sisSetFlash('Security check failed. Please refresh and try again.', 'danger');
        redirectTo($redirect);
    }

    if (!sisConsumeFormRequestToken('announcements')) {
        sisSetFlash('Duplicate submission blocked. The announcement was not saved again.', 'warning');
        redirectTo($redirect);
    }

    if ($action === 'save') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['message'] ?? ''));
        $priority = strtolower(trim((string)($_POST['priority'] ?? 'info')));
        $audience = strtolower(trim((string)($_POST['audience'] ?? 'all')));
        $status = strtolower(trim((string)($_POST['status_override'] ?? $_POST['status'] ?? 'published')));
        $outletId = normalizeNullableInt($_POST['outlet_id'] ?? null);
        $startsAt = sisAnnouncementNormalizeDate($_POST['starts_at'] ?? null);
        $endsAt = sisAnnouncementNormalizeDate($_POST['ends_at'] ?? null);
        $isPinned = !empty($_POST['is_pinned']) ? 1 : 0;
        $requiresAck = !empty($_POST['requires_ack']) ? 1 : 0;
        $createdBy = (int)($_SESSION['user_id'] ?? 0);
        $redirect = sisAnnouncementManageUrl($id > 0 ? ['edit' => $id] : []);

        if (!in_array($priority, ['info','important','urgent'], true)) $priority = 'info';
        if (!in_array($audience, ['all','admin','super_admin','supervisor','agent'], true)) $audience = 'all';
        if ($audience === 'super_admin' && !isSuperAdmin()) $audience = 'admin';
        if (!in_array($status, ['draft','published','archived'], true)) $status = 'published';

        if ($title === '' || $body === '') {
            $_SESSION['announcement_old'] = $_POST;
            sisSetFlash('Title and announcement message are required.', 'danger');
            redirectTo($redirect);
        }
        if ($startsAt && $endsAt && strtotime($startsAt) > strtotime($endsAt)) {
            $_SESSION['announcement_old'] = $_POST;
            sisSetFlash('Start date cannot be after end date.', 'danger');
            redirectTo($redirect);
        }

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE announcements SET title=?, message=?, priority=?, audience=?, outlet_id=?, status=?, is_pinned=?, requires_ack=?, starts_at=?, ends_at=?, updated_at=NOW() WHERE id=?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssssisiissi', $title, $body, $priority, $audience, $outletId, $status, $isPinned, $requiresAck, $startsAt, $endsAt, $id);
                mysqli_stmt_execute($stmt);
                sisSetFlash('Announcement updated successfully.', 'success');
                auditAction($conn, 'UPDATE_ANNOUNCEMENT', $id, $title);
                redirectTo('announcements.php');
            }
            sisSetFlash(sisDbErrorMessage($conn), 'danger');
            redirectTo($redirect);
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, message, priority, audience, outlet_id, status, is_pinned, requires_ack, starts_at, ends_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssisiissi', $title, $body, $priority, $audience, $outletId, $status, $isPinned, $requiresAck, $startsAt, $endsAt, $createdBy);
            mysqli_stmt_execute($stmt);
            $newId = (int)mysqli_insert_id($conn);
            sisSetFlash('Announcement created successfully.', 'success');
            auditAction($conn, 'CREATE_ANNOUNCEMENT', $newId, $title);
            redirectTo('announcements.php');
        }
        sisSetFlash(sisDbErrorMessage($conn), 'danger');
        redirectTo($redirect);
    }

    if (in_array($action, ['publish','draft','archive'], true) && $id > 0) {
        $newStatus = $action === 'publish' ? 'published' : ($action === 'archive' ? 'archived' : 'draft');
        $stmt = mysqli_prepare($conn, "UPDATE announcements SET status=?, updated_at=NOW() WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $newStatus, $id);
            mysqli_stmt_execute($stmt);
            sisSetFlash('Announcement status changed to ' . ucfirst($newStatus) . '.', 'success');
            auditAction($conn, 'CHANGE_ANNOUNCEMENT_STATUS', $id, $newStatus);
        }
        redirectTo('announcements.php');
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM announcement_reads WHERE announcement_id=?");
        if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); }
        $stmt = mysqli_prepare($conn, "DELETE FROM announcements WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            sisSetFlash('Announcement deleted.', 'success');
            auditAction($conn, 'DELETE_ANNOUNCEMENT', $id, 'Deleted announcement');
        }
        redirectTo('announcements.php');
    }

    sisSetFlash('No valid announcement action was selected.', 'warning');
    redirectTo('announcements.php');
}

$flash = sisGetFlash();
$message = $flash['message'] ?? '';
$messageType = $flash['type'] ?? 'info';
$old = $_SESSION['announcement_old'] ?? [];
unset($_SESSION['announcement_old']);

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM announcements WHERE id=? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $editId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $edit = $res ? mysqli_fetch_assoc($res) : null;
    }
}


$audienceOptions = [
    'all' => 'All users',
    'admin' => 'Admins',
    'supervisor' => 'Supervisors',
    'agent' => 'Agents',
];
if (isSuperAdmin()) {
    $audienceOptions['super_admin'] = 'System owner only';
}
if (is_array($edit) && !isSuperAdmin() && (($edit['audience'] ?? '') === 'super_admin')) {
    sisSetFlash('Announcement not found or not available.', 'warning');
    redirectTo('announcements.php');
}

function sisAnnouncementField($key, $default = '') {
    global $old, $edit;
    if (is_array($old) && array_key_exists($key, $old)) return $old[$key];
    if (is_array($edit) && array_key_exists($key, $edit)) return $edit[$key];
    return $default;
}

$outlets = tableExists($conn, 'outlets') ? mysqli_query($conn, "SELECT id, name FROM outlets ORDER BY name") : false;

$statusFilter = strtolower((string)($_GET['status'] ?? ''));
$audienceFilter = strtolower((string)($_GET['audience'] ?? ''));
$priorityFilter = strtolower((string)($_GET['priority'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$where = [];
$params = [];
$types = '';
if (!isSuperAdmin()) { $where[] = "a.audience <> 'super_admin'"; }
if (in_array($statusFilter, ['draft','published','archived'], true)) { $where[] = 'a.status = ?'; $params[] = $statusFilter; $types .= 's'; }
if ($audienceFilter === 'super_admin' && !isSuperAdmin()) { $audienceFilter = ''; }
if (array_key_exists($audienceFilter, $audienceOptions)) { $where[] = 'a.audience = ?'; $params[] = $audienceFilter; $types .= 's'; }
if (in_array($priorityFilter, ['info','important','urgent'], true)) { $where[] = 'a.priority = ?'; $params[] = $priorityFilter; $types .= 's'; }
if ($search !== '') { $where[] = '(a.title LIKE ? OR a.message LIKE ?)'; $like = '%' . $search . '%'; $params[] = $like; $params[] = $like; $types .= 'ss'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$listSql = "SELECT a.*, u.full_name AS creator, o.name AS outlet_name,
    (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) AS read_count
    FROM announcements a
    LEFT JOIN users u ON u.id = a.created_by
    LEFT JOIN outlets o ON o.id = a.outlet_id
    $whereSql
    ORDER BY FIELD(a.status,'published','draft','archived'), a.is_pinned DESC, FIELD(a.priority,'urgent','important','info'), a.created_at DESC, a.id DESC";
$stmt = mysqli_prepare($conn, $listSql);
$announcements = false;
$listError = '';
if ($stmt) {
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (mysqli_stmt_execute($stmt)) {
        $announcements = mysqli_stmt_get_result($stmt);
    } else {
        $listError = mysqli_stmt_error($stmt);
    }
} else {
    $listError = sisDbErrorMessage($conn, 'announcement list');
}

$totalDefaults = ['total' => 0, 'published' => 0, 'draft' => 0, 'archived' => 0, 'pinned' => 0];
$totalStatsWhere = isSuperAdmin() ? '' : " WHERE audience <> 'super_admin'";
$totalResult = mysqli_query($conn, "SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status='published' THEN 1 ELSE 0 END),0) AS published, COALESCE(SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END),0) AS draft, COALESCE(SUM(CASE WHEN status='archived' THEN 1 ELSE 0 END),0) AS archived, COALESCE(SUM(CASE WHEN is_pinned=1 THEN 1 ELSE 0 END),0) AS pinned FROM announcements" . $totalStatsWhere);
$totalRow = $totalResult ? mysqli_fetch_assoc($totalResult) : null;
$total = array_merge($totalDefaults, is_array($totalRow) ? $totalRow : []);
if (!$totalResult && empty($message)) {
    $message = sisDbErrorMessage($conn);
    $messageType = 'warning';
}
if ($listError !== '' && empty($message)) {
    $message = 'Announcement list could not load. Please run Management & System > Run Upgrade. Details: ' . $listError;
    $messageType = 'warning';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcement Management - Showroom Information System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
    <link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'Announcements', 'Create, publish, and track management notices'); ?>
<div class="container-fluid px-0">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo e($messageType); ?> alert-dismissible fade show"><i class="bi bi-info-circle"></i> <?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="sis-announcement-admin-hero mb-4">
        <div>
            <span class="sis-kicker"><i class="bi bi-megaphone-fill"></i> Management Broadcast</span>
            <h2>Announcements Centre</h2>
            <p>Publish clear updates to every dashboard. The form now uses redirect-after-save and one-time request tokens, so refresh cannot create duplicate announcements.</p>
        </div>
        <a href="<?php echo e(appPath('announcements.php')); ?>" class="btn btn-outline-primary"><i class="bi bi-eye"></i> User Board</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="stat-card"><h6>Total</h6><h2><?php echo (int)($total['total'] ?? 0); ?></h2><small>All announcements</small></div></div>
        <div class="col-md-3"><div class="stat-card"><h6>Published</h6><h2><?php echo (int)($total['published'] ?? 0); ?></h2><small>Visible if active</small></div></div>
        <div class="col-md-3"><div class="stat-card"><h6>Pinned</h6><h2><?php echo (int)($total['pinned'] ?? 0); ?></h2><small>Shown first</small></div></div>
        <div class="col-md-3"><div class="stat-card"><h6>Draft / Archived</h6><h2><?php echo (int)($total['draft'] ?? 0); ?> / <?php echo (int)($total['archived'] ?? 0); ?></h2><small>Hidden or old notices</small></div></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card sis-card-sticky sis-announcement-form-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <strong><i class="bi bi-pencil-square"></i> <?php echo $edit ? 'Edit Announcement' : 'New Announcement'; ?></strong>
                    <?php if ($edit): ?><a href="announcements.php" class="btn btn-sm btn-outline-secondary">New</a><?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3" data-prevent-duplicate-submit>
                        <?php echo csrfField(); ?>
                        <?php echo sisFormRequestTokenField('announcements'); ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?php echo (int)sisAnnouncementField('id', 0); ?>">
                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" maxlength="180" required value="<?php echo e(sisAnnouncementField('title')); ?>" placeholder="Example: New ComCach process">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="7" required placeholder="Write the announcement users should see..."><?php echo e(sisAnnouncementField('message')); ?></textarea>
                            <small class="text-muted">Use short, clear instructions. Users will see this on the dashboard and announcement board.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <?php foreach (['info'=>'Info','important'=>'Important','urgent'=>'Urgent'] as $k=>$v): ?>
                                    <option value="<?php echo e($k); ?>" <?php echo ((string)sisAnnouncementField('priority', 'info') === $k) ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Audience</label>
                            <select name="audience" class="form-select">
                                <?php foreach ($audienceOptions as $k=>$v): ?>
                                    <option value="<?php echo e($k); ?>" <?php echo ((string)sisAnnouncementField('audience', 'all') === $k) ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Outlet</label>
                            <select name="outlet_id" class="form-select">
                                <option value="">All outlets</option>
                                <?php if ($outlets): mysqli_data_seek($outlets, 0); while ($o = mysqli_fetch_assoc($outlets)): ?>
                                    <option value="<?php echo (int)$o['id']; ?>" <?php echo ((string)sisAnnouncementField('outlet_id', '') === (string)$o['id']) ? 'selected' : ''; ?>><?php echo e($o['name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['published'=>'Published','draft'=>'Draft','archived'=>'Archived'] as $k=>$v): ?>
                                    <option value="<?php echo e($k); ?>" <?php echo ((string)sisAnnouncementField('status', 'published') === $k) ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date/Time</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="<?php echo e(sisAnnouncementDateInput(sisAnnouncementField('starts_at'))); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date/Time</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="<?php echo e(sisAnnouncementDateInput(sisAnnouncementField('ends_at'))); ?>">
                        </div>
                        <div class="col-12">
                            <div class="sis-check-grid">
                                <label class="sis-switch-card">
                                    <input type="checkbox" name="is_pinned" value="1" <?php echo !empty(sisAnnouncementField('is_pinned', 0)) ? 'checked' : ''; ?>>
                                    <span><strong>Pin announcement</strong><small>Show it first on dashboards and dropdowns.</small></span>
                                </label>
                                <label class="sis-switch-card">
                                    <input type="checkbox" name="requires_ack" value="1" <?php echo !empty(sisAnnouncementField('requires_ack', 0)) ? 'checked' : ''; ?>>
                                    <span><strong>Ask users to read</strong><small>Shows a clearer “Mark Read” action for users.</small></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12 d-grid gap-2">
                            <button class="btn btn-primary" name="status_override" value="published"><i class="bi bi-send-check"></i> <?php echo $edit ? 'Update & Publish' : 'Publish Announcement'; ?></button>
                            <button class="btn btn-outline-secondary" name="status_override" value="draft"><i class="bi bi-save"></i> Save as Draft</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card sis-announcement-list-card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <strong><i class="bi bi-list-ul"></i> Announcement List</strong>
                        <span class="badge text-bg-light border text-dark">Refresh-safe management</span>
                    </div>
                    <form method="get" class="sis-announcement-filters">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" name="q" class="form-control" value="<?php echo e($search); ?>" placeholder="Search title or message...">
                        </div>
                        <select name="status" class="form-select">
                            <option value="">All status</option>
                            <?php foreach (['published'=>'Published','draft'=>'Draft','archived'=>'Archived'] as $k=>$v): ?><option value="<?php echo e($k); ?>" <?php echo $statusFilter === $k ? 'selected' : ''; ?>><?php echo e($v); ?></option><?php endforeach; ?>
                        </select>
                        <select name="audience" class="form-select">
                            <option value="">All audience</option>
                            <?php foreach ($audienceOptions as $k=>$v): ?><option value="<?php echo e($k); ?>" <?php echo $audienceFilter === $k ? 'selected' : ''; ?>><?php echo e($v); ?></option><?php endforeach; ?>
                        </select>
                        <select name="priority" class="form-select">
                            <option value="">All priority</option>
                            <?php foreach (['info'=>'Info','important'=>'Important','urgent'=>'Urgent'] as $k=>$v): ?><option value="<?php echo e($k); ?>" <?php echo $priorityFilter === $k ? 'selected' : ''; ?>><?php echo e($v); ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="announcements.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="announcementTable">
                            <thead><tr><th>Announcement</th><th>Audience</th><th>Status</th><th>Schedule</th><th>Read</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php if ($announcements && mysqli_num_rows($announcements) > 0): while ($a = mysqli_fetch_assoc($announcements)): $class = sisAnnouncementPriorityClass($a['priority'] ?? 'info'); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($a['title']); ?></div>
                                        <small class="text-muted d-block"><?php echo e(sisShortText($a['message'] ?? '', 110)); ?></small>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <?php if (!empty($a['is_pinned'])): ?><span class="badge text-bg-dark"><i class="bi bi-pin-angle"></i> Pinned</span><?php endif; ?>
                                            <?php if (!empty($a['requires_ack'])): ?><span class="badge text-bg-light border text-dark"><i class="bi bi-check2-square"></i> Read action</span><?php endif; ?>
                                            <span class="badge text-bg-<?php echo e($class); ?>"><?php echo e(ucfirst($a['priority'])); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="badge text-bg-light border text-dark"><?php echo e(ucfirst(str_replace('_',' ', $a['audience']))); ?></span><small class="d-block text-muted"><?php echo e($a['outlet_name'] ?: 'All outlets'); ?></small></td>
                                    <td><span class="badge text-bg-<?php echo $a['status'] === 'published' ? 'success' : ($a['status'] === 'draft' ? 'secondary' : 'dark'); ?>"><?php echo e(ucfirst($a['status'])); ?></span></td>
                                    <td><small><?php echo $a['starts_at'] ? 'Start: '.e(sisAnnouncementDateLabel($a['starts_at'])).'<br>' : 'Start: Now<br>'; ?><?php echo $a['ends_at'] ? 'End: '.e(sisAnnouncementDateLabel($a['ends_at'])) : 'No end date'; ?></small></td>
                                    <td><span class="badge text-bg-light border text-dark"><?php echo (int)$a['read_count']; ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="announcements.php?edit=<?php echo (int)$a['id']; ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <form method="post" class="d-inline"><?php echo csrfField(); ?><?php echo sisFormRequestTokenField('announcements'); ?><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>"><input type="hidden" name="action" value="<?php echo $a['status'] === 'published' ? 'archive' : 'publish'; ?>"><button class="btn btn-outline-secondary" title="<?php echo $a['status'] === 'published' ? 'Archive' : 'Publish'; ?>"><i class="bi <?php echo $a['status'] === 'published' ? 'bi-archive' : 'bi-send-check'; ?>"></i></button></form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this announcement?')"><?php echo csrfField(); ?><?php echo sisFormRequestTokenField('announcements'); ?><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-megaphone d-block fs-2"></i>No announcements found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<script>
document.querySelectorAll('[data-prevent-duplicate-submit]').forEach(function(form){
  form.addEventListener('submit', function(){
    setTimeout(function(){
      form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function(btn){
        btn.disabled = true;
        if (!btn.dataset.originalText) btn.dataset.originalText = btn.innerHTML;
        if (btn.matches('.btn-primary')) btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
      });
    }, 0);
  });
});
</script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
