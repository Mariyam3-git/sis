<?php
require_once '../config.php';
requireRole('admin');
if (function_exists('sisPreventDuplicatePostRefresh')) sisPreventDuplicatePostRefresh();
sisEnsureUserAccountColumns($conn);
if (function_exists('sisEnsureUserRoleColumn')) { sisEnsureUserRoleColumn($conn); }

$isSuper = isSuperAdmin();
$viewerRole = $_SESSION['role'] ?? 'admin';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$roleOptions = sisSafeUserRoleOptions($viewerRole);
$message = '';
$message_type = 'info';

function sisCanManageTargetUser(array $user = null, $isSuper = false, $currentUserId = 0) {
    if (!$user) return false;
    if (!$isSuper && ($user['role'] ?? '') === 'super_admin') return false;
    return true;
}

function sisRandomTemporaryPassword($length = 10) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function sisLoadTargetUserForAdmin($conn, $id) {
    $stmt = mysqli_prepare($conn, 'SELECT id, username, full_name, email, role, outlet_id, shift, is_active, profile_photo, password_changed_at, must_change_password, created_at, last_login, last_logout FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function sisUsernameTakenByAnother($conn, $username, $targetId) {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    if (!$stmt) return true;
    mysqli_stmt_bind_param($stmt, 'si', $username, $targetId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $taken = $res && mysqli_num_rows($res) > 0;
    mysqli_stmt_close($stmt);
    return $taken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $targetId = (int)($_POST['user_id'] ?? 0);
        $target = sisLoadTargetUserForAdmin($conn, $targetId);

        if (!$target || !sisCanManageTargetUser($target, $isSuper, $currentUserId)) {
            $message = 'You cannot manage that user.';
            $message_type = 'danger';
        } elseif ($action === 'change_role') {
            $newRole = function_exists('sisNormalizeRole') ? sisNormalizeRole($_POST['new_role'] ?? '') : strtolower((string)($_POST['new_role'] ?? ''));
            if (!isset($roleOptions[$newRole])) {
                $message = 'Invalid role selected.';
                $message_type = 'danger';
            } elseif ($targetId === $currentUserId) {
                $message = 'For safety, you cannot change your own role while logged in.';
                $message_type = 'warning';
            } else {
                $upd = mysqli_prepare($conn, 'UPDATE users SET role = ? WHERE id = ?');
                if ($upd) {
                    mysqli_stmt_bind_param($upd, 'si', $newRole, $targetId);
                    if (mysqli_stmt_execute($upd)) {
                        auditAction($conn, 'CHANGE_USER_ROLE', $targetId, 'Changed role from ' . $target['role'] . ' to ' . $newRole);
                        sisSetFlash('User role updated successfully.', 'success');
                        redirectTo('users.php');
                    }
                }
                $message = 'Could not update role. Please run Upgrade as the system owner if the role database column is outdated.';
                $message_type = 'danger';
            }
        } elseif ($action === 'reset_password') {
            $auto = !empty($_POST['auto_generate']);
            $newPassword = $auto ? sisRandomTemporaryPassword(10) : (string)($_POST['new_password'] ?? '');
            if (strlen($newPassword) < 8) {
                $message = 'Temporary password must be at least 8 characters.';
                $message_type = 'warning';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $now = date('Y-m-d H:i:s');
                $upd = mysqli_prepare($conn, 'UPDATE users SET password_hash = ?, password_changed_at = ?, must_change_password = 1 WHERE id = ?');
                if ($upd) {
                    mysqli_stmt_bind_param($upd, 'ssi', $hash, $now, $targetId);
                    if (mysqli_stmt_execute($upd)) {
                        auditAction($conn, 'RESET_USER_PASSWORD', $targetId, 'Password reset; user must change password at next login');
                        if ($auto) {
                            sisSetFlash('Password reset successfully. Temporary password for ' . ($target['full_name'] ?: $target['username']) . ': ' . $newPassword . ' — give it to the user securely. They must change it after login.', 'success');
                        } else {
                            sisSetFlash('Password reset successfully. The user must change it after login.', 'success');
                        }
                        redirectTo('users.php');
                    }
                }
                $message = 'Could not reset password. Please run Upgrade as the system owner if account columns are missing.';
                $message_type = 'danger';
            }
        } elseif ($action === 'toggle_active') {
            if ($targetId === $currentUserId) {
                $message = 'You cannot deactivate your own account.';
                $message_type = 'warning';
            } else {
                $active = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
                $upd = mysqli_prepare($conn, 'UPDATE users SET is_active = ? WHERE id = ?');
                if ($upd) {
                    mysqli_stmt_bind_param($upd, 'ii', $active, $targetId);
                    if (mysqli_stmt_execute($upd)) {
                        auditAction($conn, $active ? 'ACTIVATE_USER' : 'DEACTIVATE_USER', $targetId, 'Users & Roles page');
                        sisSetFlash($active ? 'User activated.' : 'User deactivated.', 'success');
                        redirectTo('users.php');
                    }
                }
                $message = 'Could not update account status.';
                $message_type = 'danger';
            }
        } elseif ($action === 'update_user') {
            $username = trim((string)($_POST['username'] ?? ''));
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $newRole = function_exists('sisNormalizeRole') ? sisNormalizeRole($_POST['role'] ?? $target['role']) : strtolower((string)($_POST['role'] ?? $target['role']));
            $outletRaw = $_POST['outlet_id'] ?? '';
            $outletId = ($outletRaw === '' || $outletRaw === null) ? null : (int)$outletRaw;
            $shift = (string)($_POST['shift'] ?? 'Morning');
            $isActive = array_key_exists('is_active', $_POST) ? 1 : (int)($target['is_active'] ?? 1);
            $newPassword = trim((string)($_POST['new_password'] ?? ''));

            if ($username === '' || $fullName === '') {
                $message = 'Username and full name are required.';
                $message_type = 'warning';
            } elseif (!isset($roleOptions[$newRole])) {
                $message = 'Invalid role selected.';
                $message_type = 'danger';
            } elseif (!in_array($shift, ['Morning','Afternoon','Night','Rotational'], true)) {
                $message = 'Invalid shift selected.';
                $message_type = 'danger';
            } elseif ($targetId === $currentUserId && $newRole !== $target['role']) {
                $message = 'For safety, you cannot change your own role while logged in.';
                $message_type = 'warning';
            } elseif ($targetId === $currentUserId && $isActive !== (int)$target['is_active']) {
                $message = 'For safety, you cannot deactivate your own account.';
                $message_type = 'warning';
            } elseif (sisUsernameTakenByAnother($conn, $username, $targetId)) {
                $message = 'Username is already used by another account.';
                $message_type = 'warning';
            } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
                $message = 'New password must be at least 8 characters.';
                $message_type = 'warning';
            } else {
                if ($newPassword !== '') {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $now = date('Y-m-d H:i:s');
                    $stmt = mysqli_prepare($conn, 'UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, outlet_id = ?, shift = ?, is_active = ?, password_hash = ?, password_changed_at = ?, must_change_password = 1 WHERE id = ?');
                    if ($stmt) mysqli_stmt_bind_param($stmt, 'ssssisissi', $username, $fullName, $email, $newRole, $outletId, $shift, $isActive, $hash, $now, $targetId);
                } else {
                    $stmt = mysqli_prepare($conn, 'UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, outlet_id = ?, shift = ?, is_active = ? WHERE id = ?');
                    if ($stmt) mysqli_stmt_bind_param($stmt, 'ssssisii', $username, $fullName, $email, $newRole, $outletId, $shift, $isActive, $targetId);
                }
                if (!empty($stmt) && mysqli_stmt_execute($stmt)) {
                    if ($targetId === $currentUserId) {
                        $_SESSION['username'] = $username;
                        $_SESSION['full_name'] = $fullName;
                        $_SESSION['outlet_id'] = $outletId;
                        $_SESSION['shift'] = $shift;
                    }
                    auditAction($conn, 'UPDATE_USER_ACCOUNT', $targetId, 'Updated from Users & Roles detail popup');
                    sisSetFlash('User account updated successfully.', 'success');
                    redirectTo('users.php');
                }
                $message = 'Could not update user account.';
                $message_type = 'danger';
            }
        }
    }
}

$flash = sisGetFlash();
if (!$message && !empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }
$search = trim((string)($_GET['search'] ?? ''));
$roleFilter = function_exists('sisNormalizeRole') ? sisNormalizeRole($_GET['role'] ?? '') : strtolower((string)($_GET['role'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
$where = [];
$params = [];
$types = '';
if (!$isSuper) $where[] = "u.role <> 'super_admin'";
if ($search !== '') {
    $where[] = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR o.name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like); $types .= 'ssss';
}
if ($roleFilter !== '' && isset($roleOptions[$roleFilter])) { $where[] = 'u.role = ?'; $params[] = $roleFilter; $types .= 's'; }
if ($statusFilter !== '' && in_array($statusFilter, ['0','1'], true)) { $where[] = 'u.is_active = ?'; $params[] = (int)$statusFilter; $types .= 'i'; }
$sql = "SELECT u.*, o.name AS outlet_name, o.location AS outlet_location, o.region AS outlet_region FROM users u LEFT JOIN outlets o ON u.outlet_id = o.id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY FIELD(u.role,'super_admin','admin','supervisor','agent'), u.full_name";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt && $params) mysqli_stmt_bind_param($stmt, $types, ...$params);
if ($stmt) mysqli_stmt_execute($stmt);
$result = $stmt ? mysqli_stmt_get_result($stmt) : mysqli_query($conn, $sql);

$outlets = [];
$outletResult = mysqli_query($conn, "SELECT id, name, region FROM outlets WHERE is_active = 1 ORDER BY region, name");
while ($outletResult && ($o = mysqli_fetch_assoc($outletResult))) { $outlets[] = $o; }
$shifts = ['Morning','Afternoon','Night','Rotational'];
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Users & Roles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
<link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin', 'Users & Roles', $isSuper ? 'Manage every account and role' : 'Manage normal user accounts only'); ?>
<div class="container-fluid py-3">
    <?php if($message): ?><div class="alert alert-<?php echo e($message_type); ?>"><i class="bi bi-info-circle"></i> <?php echo e($message); ?></div><?php endif; ?>

    <div class="sis-module-hero">
        <div>
            <span class="sis-kicker"><i class="bi bi-person-gear"></i> Account control</span>
            <h2>Users & Roles</h2>
            <p>Search accounts, reset passwords, change roles, activate/deactivate users, and double-click any row to open the full user detail popup.</p>
        </div>
        <div class="sis-module-hero-icon"><i class="bi bi-people"></i></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-5"><label class="form-label">Search</label><input class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Name, username, email, outlet"></div>
                <div class="col-md-3"><label class="form-label">Role</label><select name="role" class="form-select"><option value="">All roles</option><?php foreach($roleOptions as $key=>$label): ?><option value="<?php echo e($key); ?>" <?php echo $roleFilter===$key?'selected':''; ?>><?php echo e($label); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="1" <?php echo $statusFilter==='1'?'selected':''; ?>>Active</option><option value="0" <?php echo $statusFilter==='0'?'selected':''; ?>>Inactive</option></select></div>
                <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i> Filter</button></div>
            </form>
        </div>
    </div>

    <div class="alert alert-info py-2"><i class="bi bi-mouse2"></i> Tip: double-click any user row to view full details and edit the account in one popup.</div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sis-users-table">
                    <thead><tr><th>User</th><th>Role</th><th>Outlet</th><th>Status</th><th>Password</th><th>Last Login</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if($result && mysqli_num_rows($result)>0): while($u=mysqli_fetch_assoc($result)): 
                        $photo=sisProfilePhotoPath($u['profile_photo'] ?? ''); $userId=(int)$u['id']; $isSelf=($userId===$currentUserId); 
                        $detail = [
                            'id'=>$userId,
                            'username'=>(string)($u['username'] ?? ''),
                            'full_name'=>(string)($u['full_name'] ?? ''),
                            'email'=>(string)($u['email'] ?? ''),
                            'role'=>(string)($u['role'] ?? ''),
                            'role_label'=>(string)($roleOptions[$u['role']] ?? sisDisplayRole($u['role'] ?? '')),
                            'outlet_id'=>$u['outlet_id'] !== null ? (int)$u['outlet_id'] : '',
                            'outlet_name'=>(string)($u['outlet_name'] ?? 'All / N/A'),
                            'outlet_location'=>(string)($u['outlet_location'] ?? ''),
                            'outlet_region'=>(string)($u['outlet_region'] ?? ''),
                            'shift'=>(string)($u['shift'] ?? 'Morning'),
                            'is_active'=>(int)($u['is_active'] ?? 0),
                            'must_change_password'=>(int)($u['must_change_password'] ?? 0),
                            'password_changed_at'=>(string)($u['password_changed_at'] ?? ''),
                            'created_at'=>(string)($u['created_at'] ?? ''),
                            'last_login'=>(string)($u['last_login'] ?? ''),
                            'last_logout'=>(string)($u['last_logout'] ?? ''),
                            'photo'=>$photo,
                            'is_self'=>$isSelf ? 1 : 0,
                            'can_manage'=>sisCanManageTargetUser($u, $isSuper, $currentUserId) ? 1 : 0,
                        ];
                        $detailJson = htmlspecialchars(json_encode($detail, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="sis-user-row" data-user='<?php echo $detailJson; ?>' tabindex="0" title="Double-click to view full details">
                            <td><div class="d-flex align-items-center gap-2"><span class="sis-user-avatar"><?php if($photo): ?><img src="<?php echo e($photo); ?>" alt=""><?php else: ?><?php echo e(strtoupper(substr((string)$u['full_name'],0,1))); ?><?php endif; ?></span><div><strong><?php echo e($u['full_name']); ?></strong><small class="d-block text-muted">@<?php echo e($u['username']); ?><?php echo !empty($u['email']) ? ' · '.e($u['email']) : ''; ?></small></div></div></td>
                            <td><span class="badge bg-primary-subtle text-primary border"><?php echo e($roleOptions[$u['role']] ?? ucfirst($u['role'])); ?></span></td>
                            <td><?php echo e($u['outlet_name'] ?? 'All / N/A'); ?><small class="d-block text-muted"><?php echo e($u['outlet_region'] ?? ''); ?></small></td>
                            <td><?php echo (int)$u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
                            <td><?php echo !empty($u['must_change_password']) ? '<span class="badge bg-warning text-dark">Must change</span>' : '<span class="text-muted">OK</span>'; ?></td>
                            <td><?php echo !empty($u['last_login']) ? e(date('d/m/Y H:i', strtotime($u['last_login']))) : '<span class="text-muted">Never</span>'; ?></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-open-user-detail><i class="bi bi-eye"></i> View/Edit</button>
                                    <form method="post" class="d-flex align-items-center gap-1">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                        <select class="form-select form-select-sm sis-role-select" name="new_role" <?php echo $isSelf?'disabled':''; ?>>
                                            <?php foreach($roleOptions as $key=>$label): ?>
                                                <option value="<?php echo e($key); ?>" <?php echo $u['role']===$key?'selected':''; ?>><?php echo e($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary" <?php echo $isSelf?'disabled title="You cannot change your own role"':''; ?>><i class="bi bi-save"></i></button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-open-password-reset data-user-id="<?php echo $userId; ?>" data-user-name="<?php echo e($u['full_name'] ?: $u['username']); ?>" title="Reset password"><i class="bi bi-key"></i></button>
                                    <?php if(!$isSelf): ?><form method="post" class="d-inline" data-confirm-message="<?php echo (int)$u['is_active'] ? 'Deactivate this user?' : 'Activate this user?'; ?>"><?php echo csrfField(); ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><input type="hidden" name="is_active" value="<?php echo (int)$u['is_active'] ? 0 : 1; ?>"><button class="btn btn-sm btn-outline-<?php echo (int)$u['is_active'] ? 'danger' : 'success'; ?>"><i class="bi bi-<?php echo (int)$u['is_active'] ? 'slash-circle' : 'check-circle'; ?>"></i></button></form><?php endif; ?>
                                </div>
                                
                            </td>
                        </tr>
                    <?php endwhile; else: ?><tr><td colspan="7" class="text-center py-5 text-muted">No users found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade sis-fixed-modal" id="resetPasswordModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="resetPasswordForm">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="reset_user_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-key"></i> Reset Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning py-2 mb-3"><i class="bi bi-shield-lock"></i> Resetting password for <strong id="reset_user_name">this user</strong>.</div>
          <label class="form-label" for="reset_new_password">Temporary Password</label>
          <div class="input-group">
            <input class="form-control" id="reset_new_password" name="new_password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
            <button class="btn btn-outline-secondary" type="button" data-generate-password><i class="bi bi-magic"></i> Generate</button>
          </div>
          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="auto_generate" value="1" id="reset_auto_generate">
            <label class="form-check-label" for="reset_auto_generate">Auto-generate on save and show it once after reset</label>
          </div>
          <div class="form-text">The user will be forced to change this temporary password at next login.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-warning"><i class="bi bi-key-fill"></i> Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="userDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" id="userDetailForm">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" id="detail_user_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person-vcard"></i> User Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-4">
            <div class="col-lg-4">
              <div class="card h-100">
                <div class="card-body text-center">
                  <div class="sis-profile-photo-xl mx-auto mb-3" id="detail_photo_box"><span>U</span></div>
                  <h4 id="detail_name">User</h4>
                  <p class="text-muted mb-2" id="detail_username">@username</p>
                  <div class="d-flex justify-content-center gap-2 flex-wrap"><span class="badge bg-primary" id="detail_role_badge">Role</span><span class="badge bg-success" id="detail_status_badge">Active</span></div>
                  <hr>
                  <div class="text-start small" id="detail_summary"></div>
                </div>
              </div>
            </div>
            <div class="col-lg-8">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" id="edit_full_name" required></div>
                <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" id="edit_username" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="edit_email"></div>
                <div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="role" id="edit_role"><?php foreach($roleOptions as $key=>$label): ?><option value="<?php echo e($key); ?>"><?php echo e($label); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Outlet</label><select class="form-select" name="outlet_id" id="edit_outlet"><option value="">All / Not assigned</option><?php foreach($outlets as $o): ?><option value="<?php echo (int)$o['id']; ?>"><?php echo e(($o['region'] ? $o['region'].' · ' : '') . $o['name']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Shift</label><select class="form-select" name="shift" id="edit_shift"><?php foreach($shifts as $shift): ?><option value="<?php echo e($shift); ?>"><?php echo e($shift); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Optional New Password</label><input type="password" class="form-control" name="new_password" id="edit_new_password" minlength="8" placeholder="Leave blank to keep current password"><div class="form-text">If entered, the user must change it after login.</div></div>
                <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" id="edit_is_active"><label class="form-check-label" for="edit_is_active">Account active</label></div></div>
              </div>
              <div class="alert alert-light border mt-3 mb-0"><i class="bi bi-info-circle"></i> Admin can edit account details here. Profile photo is changed by the user from My Account.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<script>
(function(){
  function fmtDate(v){ if(!v) return '—'; try { var d=new Date(String(v).replace(' ','T')); return isNaN(d) ? v : d.toLocaleString(); } catch(e){ return v || '—'; } }
  function initials(name){ return (name||'U').trim().substring(0,1).toUpperCase() || 'U'; }
  function openUser(row){
    if(!row) return;
    var data = {};
    try { data = JSON.parse(row.getAttribute('data-user') || '{}'); } catch(e) { return; }
    document.getElementById('detail_user_id').value = data.id || '';
    document.getElementById('edit_full_name').value = data.full_name || '';
    document.getElementById('edit_username').value = data.username || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_role').value = data.role || 'agent';
    document.getElementById('edit_outlet').value = (data.outlet_id === null ? '' : data.outlet_id) || '';
    document.getElementById('edit_shift').value = data.shift || 'Morning';
    document.getElementById('edit_is_active').checked = Number(data.is_active || 0) === 1;
    document.getElementById('edit_new_password').value = '';
    var roleSel = document.getElementById('edit_role');
    var activeSwitch = document.getElementById('edit_is_active');
    roleSel.disabled = Number(data.is_self || 0) === 1;
    activeSwitch.disabled = Number(data.is_self || 0) === 1;
    document.getElementById('detail_name').textContent = data.full_name || 'User';
    document.getElementById('detail_username').textContent = '@' + (data.username || 'username') + (data.email ? ' · ' + data.email : '');
    document.getElementById('detail_role_badge').textContent = data.role_label || data.role || 'User';
    var statusBadge = document.getElementById('detail_status_badge');
    statusBadge.textContent = Number(data.is_active || 0) === 1 ? 'Active' : 'Inactive';
    statusBadge.className = 'badge ' + (Number(data.is_active || 0) === 1 ? 'bg-success' : 'bg-secondary');
    var photoBox = document.getElementById('detail_photo_box');
    photoBox.innerHTML = data.photo ? '<img src="'+String(data.photo).replace(/"/g,'&quot;')+'" alt="Profile photo">' : '<span>'+initials(data.full_name)+'</span>';
    document.getElementById('detail_summary').innerHTML =
      '<div class="d-flex justify-content-between py-1"><span class="text-muted">Outlet</span><strong>'+escapeHtml(data.outlet_name || 'All / N/A')+'</strong></div>'+
      '<div class="d-flex justify-content-between py-1"><span class="text-muted">Region</span><strong>'+escapeHtml(data.outlet_region || '—')+'</strong></div>'+
      '<div class="d-flex justify-content-between py-1"><span class="text-muted">Shift</span><strong>'+escapeHtml(data.shift || '—')+'</strong></div>'+
      '<div class="d-flex justify-content-between py-1"><span class="text-muted">Password status</span><strong>'+(Number(data.must_change_password||0)===1?'Must change':'OK')+'</strong></div>'+
      '<div class="d-flex justify-content-between py-1"><span class="text-muted">Password changed</span><strong>'+escapeHtml(fmtDate(data.password_changed_at))+'</strong></div>'+
      '<div class="d-flex justify-content-between py-1"><span class="text-muted">Created</span><strong>'+escapeHtml(fmtDate(data.created_at))+'</strong></div>'+
      '<div class="d-flex justify-content-between py-1"><span class="text-muted">Last login</span><strong>'+escapeHtml(fmtDate(data.last_login))+'</strong></div>'+
      '<div class="d-flex justify-content-between py-1"><span class="text-muted">Last logout</span><strong>'+escapeHtml(fmtDate(data.last_logout))+'</strong></div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('userDetailModal')).show();
  }
  function escapeHtml(v){ return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]; }); }
  document.querySelectorAll('.sis-user-row').forEach(function(row){
    row.addEventListener('dblclick', function(){ openUser(row); });
    row.addEventListener('keydown', function(e){ if(e.key === 'Enter') openUser(row); });
    var btn = row.querySelector('[data-open-user-detail]');
    if(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); openUser(row); }); }
  });
  document.querySelectorAll('[data-confirm-message]').forEach(function(form){
    form.addEventListener('submit', function(e){ if(!confirm(form.getAttribute('data-confirm-message') || 'Continue?')) e.preventDefault(); });
  });
  document.querySelectorAll('[data-open-password-reset]').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      var id = btn.getAttribute('data-user-id') || '';
      var name = btn.getAttribute('data-user-name') || 'this user';
      document.getElementById('reset_user_id').value = id;
      document.getElementById('reset_user_name').textContent = name;
      document.getElementById('reset_new_password').value = '';
      document.getElementById('reset_new_password').type = 'password';
      document.getElementById('reset_auto_generate').checked = false;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('resetPasswordModal'), {backdrop: 'static', keyboard: true}).show();
    });
  });
  function genPass(){
    var chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%'; var out='';
    if(window.crypto && crypto.getRandomValues){ var arr=new Uint32Array(10); crypto.getRandomValues(arr); for(var i=0;i<arr.length;i++) out += chars[arr[i] % chars.length]; }
    else { for(var j=0;j<10;j++) out += chars[Math.floor(Math.random()*chars.length)]; }
    return out;
  }
  document.querySelectorAll('[data-generate-password]').forEach(function(btn){ btn.addEventListener('click', function(){ var input=btn.closest('.input-group').querySelector('input[name="new_password"]'); if(input){ input.type='text'; input.value=genPass(); input.focus(); } }); });
})();
</script>
<?php sisRenderLayoutEnd(); ?>
</body></html>
