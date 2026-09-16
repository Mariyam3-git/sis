<?php
require_once 'config.php';
requireLogin();
if (function_exists('sisPreventDuplicatePostRefresh')) sisPreventDuplicatePostRefresh();
sisEnsureUserAccountColumns($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower((string)($_SESSION['role'] ?? ''));
$forcePassword = !empty($_GET['force_password']) || !empty($_SESSION['force_password_change']) || sisUserNeedsPasswordChange($conn, $user_id);
$message = '';
$message_type = 'info';
$flash = sisGetFlash();
if (!empty($flash['message'])) { $message = $flash['message']; $message_type = $flash['type']; }

function sisProfileUploadPhoto($field, $user_id) {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null, ''];
    }

    $file = $_FILES[$field];
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'Photo is larger than the server upload limit. Please choose a smaller image.',
        UPLOAD_ERR_FORM_SIZE => 'Photo is larger than the form upload limit. Please choose a smaller image.',
        UPLOAD_ERR_PARTIAL => 'Photo upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server upload temp folder is missing. Please ask Admin to check PHP upload settings.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded photo. Please check folder permissions.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
    ];
    if ($err !== UPLOAD_ERR_OK) {
        return [false, null, $uploadErrors[$err] ?? 'Photo upload failed. Please choose another image.'];
    }

    if ((int)($file['size'] ?? 0) <= 0) return [false, null, 'Uploaded photo is empty. Please choose another image.'];
    if ((int)$file['size'] > 3 * 1024 * 1024) return [false, null, 'Photo must be 3MB or smaller.'];

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) return [false, null, 'Server could not read the temporary uploaded photo.'];
    $info = @getimagesize($tmp);
    if (!$info) return [false, null, 'Please upload a valid image file.'];
    $mime = strtolower((string)($info['mime'] ?? ''));
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if (!isset($extMap[$mime])) return [false, null, 'Allowed image formats: JPG, PNG, WEBP, or GIF.'];

    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'users';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return [false, null, 'Could not create upload folder: assets/uploads/users'];
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0777);
        clearstatcache(true, $dir);
    }
    if (!is_writable($dir)) {
        return [false, null, 'Upload folder is not writable: assets/uploads/users. Server Admin must make this folder writable by the PHP/web-server user (recommended: correct owner/group and permission 775).'];
    }

    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|php[0-9]|phtml|phar|cgi|pl|py|jsp|asp|aspx|sh)$\">\n    Require all denied\n</FilesMatch>\n");
    }

    $name = 'user_' . (int)$user_id . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extMap[$mime];
    $dest = $dir . DIRECTORY_SEPARATOR . $name;

    $moved = false;
    if (is_uploaded_file($tmp)) {
        $moved = move_uploaded_file($tmp, $dest);
    }
    // Fallback is useful on some local Windows/XAMPP setups and during safe tests.
    if (!$moved) {
        $moved = @rename($tmp, $dest) || @copy($tmp, $dest);
    }
    if (!$moved || !is_file($dest)) return [false, null, 'Could not save uploaded photo. Please check PHP upload_tmp_dir and assets/uploads/users permissions.'];
    @chmod($dest, 0664);
    return [true, 'assets/uploads/users/' . $name, ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $message = 'Security check failed. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_profile') {
            [$okUpload, $photoPath, $uploadError] = sisProfileUploadPhoto('profile_photo', $user_id);
            if (!$okUpload) {
                $message = $uploadError;
                $message_type = 'danger';
            } else {
                $canEditNames = in_array($role, ['admin','super_admin'], true);
                if ($canEditNames) {
                    $full_name = trim((string)($_POST['full_name'] ?? $_SESSION['full_name'] ?? ''));
                    $email = trim((string)($_POST['email'] ?? ''));
                    if ($photoPath) {
                        $stmt = mysqli_prepare($conn, 'UPDATE users SET full_name = ?, email = ?, profile_photo = ? WHERE id = ?');
                        mysqli_stmt_bind_param($stmt, 'sssi', $full_name, $email, $photoPath, $user_id);
                    } else {
                        $stmt = mysqli_prepare($conn, 'UPDATE users SET full_name = ?, email = ? WHERE id = ?');
                        mysqli_stmt_bind_param($stmt, 'ssi', $full_name, $email, $user_id);
                    }
                    if ($stmt && mysqli_stmt_execute($stmt)) {
                        $_SESSION['full_name'] = $full_name;
                        auditAction($conn, 'UPDATE_OWN_PROFILE', $user_id, 'Updated profile details/photo');
                        sisSetFlash('Profile updated successfully.', 'success');
                        redirectTo(appPath('profile.php'));
                    } else {
                        $message = 'Could not update profile.';
                        $message_type = 'danger';
                    }
                } else {
                    if ($photoPath) {
                        $stmt = mysqli_prepare($conn, 'UPDATE users SET profile_photo = ? WHERE id = ?');
                        mysqli_stmt_bind_param($stmt, 'si', $photoPath, $user_id);
                        if ($stmt && mysqli_stmt_execute($stmt)) {
                            auditAction($conn, 'UPDATE_OWN_PROFILE_PHOTO', $user_id, 'Updated own profile photo');
                            sisSetFlash('Profile photo updated successfully.', 'success');
                            redirectTo(appPath('profile.php'));
                        } else {
                            $message = 'Could not update profile photo.';
                            $message_type = 'danger';
                        }
                    } else {
                        $message = 'Choose a photo to upload. Agents and Supervisors can only update their account photo and password.';
                        $message_type = 'warning';
                    }
                }
            }
        }

        if ($action === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $stmt = mysqli_prepare($conn, 'SELECT password_hash FROM users WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            if (!$row || !password_verify($current, (string)$row['password_hash'])) {
                $message = 'Current password is incorrect.';
                $message_type = 'danger';
            } elseif (strlen($new) < 8) {
                $message = 'New password must be at least 8 characters.';
                $message_type = 'warning';
            } elseif ($new !== $confirm) {
                $message = 'New password and confirmation do not match.';
                $message_type = 'warning';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $now = date('Y-m-d H:i:s');
                $upd = mysqli_prepare($conn, 'UPDATE users SET password_hash = ?, password_changed_at = ?, must_change_password = 0 WHERE id = ?');
                mysqli_stmt_bind_param($upd, 'ssi', $hash, $now, $user_id);
                if ($upd && mysqli_stmt_execute($upd)) {
                    unset($_SESSION['force_password_change']);
                    auditAction($conn, 'CHANGE_OWN_PASSWORD', $user_id, 'User changed own password');
                    sisSetFlash('Password changed successfully.', 'success');
                    redirectTo(roleDashboardPath($role));
                } else {
                    $message = 'Could not change password.';
                    $message_type = 'danger';
                }
            }
        }
    }
}

$stmt = mysqli_prepare($conn, 'SELECT id, username, full_name, email, role, outlet_id, shift, profile_photo, password_changed_at, must_change_password, created_at, last_login FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = $res ? mysqli_fetch_assoc($res) : [];
$photo = sisProfilePhotoPath($user['profile_photo'] ?? '');
$expiryDays = sisPasswordExpiryDays();
$changedAt = $user['password_changed_at'] ?: ($user['created_at'] ?? null);
$expiresAt = ($expiryDays > 0 && $changedAt) ? date('d/m/Y', strtotime($changedAt . ' +' . $expiryDays . ' days')) : 'Disabled';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Account</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
<link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once __DIR__ . '/include/side_header.php'; sisRenderLayoutStart($role, 'My Account', $forcePassword ? 'Please change your password to continue' : 'Manage your photo and password'); ?>
<div class="container-fluid py-3">
    <?php if($message): ?><div class="alert alert-<?php echo e($message_type); ?>"><i class="bi bi-info-circle"></i> <?php echo e($message); ?></div><?php endif; ?>
    <?php if($forcePassword): ?><div class="alert alert-warning"><i class="bi bi-shield-lock"></i> Your password has expired or was reset by an administrator. Change it before using the system.</div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card sis-profile-card h-100">
                <div class="card-body text-center">
                    <div class="sis-profile-photo-xl mx-auto mb-3">
                        <?php if($photo): ?><img src="<?php echo e($photo); ?>" alt="Profile photo"><?php else: ?><span><?php echo e(strtoupper(substr((string)($user['full_name'] ?? 'U'), 0, 1))); ?></span><?php endif; ?>
                    </div>
                    <h4 class="mb-1"><?php echo e($user['full_name'] ?? 'User'); ?></h4>
                    <div class="text-muted mb-2">@<?php echo e($user['username'] ?? ''); ?></div>
                    <span class="badge bg-primary-subtle text-primary border"><?php echo e(ucfirst(str_replace('_',' ', $user['role'] ?? ''))); ?></span>
                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Password expiry</span><strong><?php echo e($expiryDays > 0 ? $expiryDays . ' days' : 'Disabled'); ?></strong></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Next change</span><strong><?php echo e($expiresAt); ?></strong></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Last login</span><strong><?php echo e(!empty($user['last_login']) ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Never'); ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-person-square"></i> My Account Photo<?php if(in_array($role,['admin','super_admin'],true)): ?> & Details<?php endif; ?></h5></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="update_profile">
                        <?php if(in_array($role,['admin','super_admin'],true)): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?php echo e($user['full_name'] ?? ''); ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?php echo e($user['email'] ?? ''); ?>"></div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-light border"><i class="bi bi-lock"></i> Agents and Supervisors can update only their account photo and password. Personal details are managed by Admin.</div>
                        <?php endif; ?>
                        <label class="form-label">Profile Image</label>
                        <input type="file" class="form-control" name="profile_photo" accept="image/png,image/jpeg,image/webp,image/gif">
                        <div class="form-text">Allowed: JPG, PNG, WEBP, GIF. Maximum 2MB.</div>
                        <button class="btn btn-primary mt-3"><i class="bi bi-upload"></i> Save Profile</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-key"></i> Reset / Change Password</h5></div>
                <div class="card-body">
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                            <div class="col-md-12"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required autocomplete="current-password"></div>
                            <div class="col-md-6"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" required minlength="8" autocomplete="new-password"></div>
                            <div class="col-md-6"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
                        </div>
                        <button class="btn btn-success mt-3"><i class="bi bi-shield-check"></i> Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
