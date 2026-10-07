<?php
// login.php - Corporate Telecom Login with safer authentication
require_once 'config.php';
require_once __DIR__ . '/include/shift_helpers.php';

$error = '';
if (function_exists('sisEnsureUserAccountColumns')) { @sisEnsureUserAccountColumns($conn); }
$loginAttemptLimit = max(3, min(20, (int)sisGetSettingValue('max_login_attempts', '5')));
$loginAttemptWindow = 900;
$loginNow = time();
if (!isset($_SESSION['sis_login_attempts']) || !is_array($_SESSION['sis_login_attempts'])
    || ($loginNow - (int)($_SESSION['sis_login_attempts']['started_at'] ?? 0)) > $loginAttemptWindow) {
    $_SESSION['sis_login_attempts'] = ['count' => 0, 'started_at' => $loginNow, 'blocked_until' => 0];
}

// Avoid stale-session redirect loops: if a timed-out session reaches login.php,
// clear it first and show the login form instead of redirecting back to dashboard.
if (isAuthenticated()) {
    if (function_exists('sisSessionExpired') && sisSessionExpired()) {
        sisDestroyCurrentSession('SESSION_TIMEOUT', 'Expired session cleared from login screen');
        redirectTo(appPath('login.php?timeout=1'));
    } else {
        redirectTo(roleDashboardPath());
    }
}

if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Your session ended after inactivity. Please login again.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Security check failed. Please refresh the page and try again.';
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $loginIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $blockedUntil = max(
        (int)($_SESSION['sis_login_attempts']['blocked_until'] ?? 0),
        $username !== '' ? $loginNow + sisLoginBlockedSeconds($conn, $username, $loginIp, $loginAttemptLimit, $loginAttemptWindow) : 0
    );
    if ($blockedUntil > $loginNow) {
        $error = 'Too many login attempts. Please wait ' . max(1, $blockedUntil - $loginNow) . ' seconds and try again.';
    } elseif ($error !== '') {
        // Keep the security error above.
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, username, full_name, role, outlet_id, shift, password_hash FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {
                $user = mysqli_fetch_assoc($result);
                if (password_verify($password, $user['password_hash'])) {
                    $uid = (int)$user['id'];
                    $now = date('Y-m-d H:i:s');
                    $session_shift = ($user['shift'] == 'Rotational') ? getShift() : $user['shift'];

                    // Advanced shift rule for agents. Normal access is allowed from 1 hour before
                    // scheduled start until 1 hour after scheduled end. Outside that rule, or after an
                    // agent already ended the current shift, the system creates a Supervisor login-access request.
                    $assignment = null;
                    $loginAccessApproved = false;
                    if ($user['role'] === 'agent') {
                        sisEnsureAdvancedShiftTables($conn);
                        $assignment = sisGetRelevantAgentAssignment($conn, $uid);
                        if ($assignment) {
                            [$allowed, $shiftMessage] = sisCanAgentLoginForShift($assignment);
                            [$supervisorAllowed, $supervisorMessage] = sisMaybeRequireSupervisorLoginApproval(
                                $conn,
                                $uid,
                                $user['outlet_id'],
                                $assignment,
                                $allowed ? '' : $shiftMessage
                            );
                            if (!$supervisorAllowed) {
                                $error = $supervisorMessage;
                            } else {
                                $loginAccessApproved = ($supervisorMessage !== 'Allowed');
                                $session_shift = sisShiftLabel($assignment['shift_type']);
                            }
                        } else {
                            [$supervisorAllowed, $supervisorMessage] = sisMaybeRequireSupervisorLoginApproval($conn, $uid, $user['outlet_id'], null, '');
                            if (!$supervisorAllowed) {
                                $error = $supervisorMessage;
                            } else {
                                $loginAccessApproved = ($supervisorMessage !== 'Allowed');
                            }
                        }
                    }

                    if ($error === '') {
                        unset($_SESSION['sis_login_attempts']);
                        sisClearLoginFailures($conn, $username);
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $uid;
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['outlet_id'] = $user['outlet_id'];
                        $_SESSION['shift'] = $session_shift;
                        $_SESSION['last_activity'] = time();

                        $update = mysqli_prepare($conn, 'UPDATE users SET last_login = ? WHERE id = ?');
                        if ($update) {
                            mysqli_stmt_bind_param($update, 'si', $now, $uid);
                            mysqli_stmt_execute($update);
                        }

                        if ($user['role'] === 'agent' && !empty($assignment)) {
                            startAgentShift($conn, $uid, (int)$user['outlet_id'], $assignment['shift_type'], $assignment['shift_date'], $now);
                            sisUpdateAssignmentAttendance($conn, $assignment, $now);
                        } elseif ($user['role'] === 'agent') {
                            startAgentShift($conn, $uid, (int)$user['outlet_id'], $session_shift, date('Y-m-d'), $now);
                        }

                        if ($user['role'] === 'agent' && !empty($loginAccessApproved)) {
                            sisCreateNotification($conn, null, 'supervisor', $user['outlet_id'], 'Approved login access used', ($user['full_name'] ?: $user['username']) . ' logged in using Supervisor-approved access.', 'supervisor/shift_report.php', 'shift_login', $uid);
                        }

                        auditAction($conn, 'LOGIN', $uid);
                        if (function_exists('sisUserNeedsPasswordChange') && sisUserNeedsPasswordChange($conn, $uid)) {
                            $_SESSION['force_password_change'] = 1;
                            redirectTo(appPath('profile.php?force_password=1'));
                        }
                        redirectTo(roleDashboardPath($user['role']));
                    }
                }
            }
            if ($error === '') {
                $attempts = (int)($_SESSION['sis_login_attempts']['count'] ?? 0) + 1;
                $_SESSION['sis_login_attempts']['count'] = $attempts;
                sisRecordLoginFailure($conn, $username, $loginIp);
                $dbWait = sisLoginBlockedSeconds($conn, $username, $loginIp, $loginAttemptLimit, $loginAttemptWindow);
                if ($attempts >= $loginAttemptLimit || $dbWait > 0) {
                    $penalty = max($dbWait, $attempts >= $loginAttemptLimit ? min(300, 30 * max(1, $attempts - $loginAttemptLimit + 1)) : 0);
                    $_SESSION['sis_login_attempts']['blocked_until'] = time() + $penalty;
                    $error = "Too many login attempts. Please wait $penalty seconds and try again.";
                } else {
                    $error = 'Invalid username or password';
                }
                if (function_exists('auditAction')) {
                    @auditAction($conn, 'LOGIN_FAILED', null, 'Failed login for username: ' . substr($username, 0, 80));
                }
            }
        } else {
            $error = 'Login service temporarily unavailable';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Showroom Information System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body>
    <div class="split-container">
        
        <div class="brand-panel">
            <div class="brand-content">
                <div class="brand-logo brand-logo-image">
                    <img src="<?php echo appAsset('assets/images/logo.svg'); ?>" alt="Company logo">
                </div>
                
                
                <div class="brand-together">
                    <div class="brand-together-text">Better Together</div>
                </div>
                
                <div class="brand-title">
                    Showroom Information System
                </div>
                
                
                <ul class="brand-features">
                    <li>
                        <i class="bi bi-shield-check"></i>
                        Enterprise-grade security
                    </li>
                    <li>
                        <i class="bi bi-speedometer2"></i>
                        Real-time analytics
                    </li>
                    <li>
                        <i class="bi bi-building"></i>
                        Multi-outlet management
                    </li>
                    <li>
                        <i class="bi bi-people"></i>
                        Role-based access control
                    </li>
                </ul>
            </div>
        </div>

        
        <div class="login-panel">
            <div class="login-card">
                
                <div class="lock-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>

                
                <div class="welcome-text">
                    <h2>Welcome Back</h2>
                    <p>Enter your credentials to access the system</p>
                </div>

                
                <?php if($error): ?>
                <div class="alert-custom">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo e($error); ?>
                </div>
                <?php endif; ?>

                
                <form method="POST" action="" id="loginForm">
                    <?php echo csrfField(); ?>
                    <div class="input-group-custom">
                        <i class="bi bi-person input-icon"></i>
                        <input 
                            type="text" 
                            class="form-control-custom" 
                            id="username" 
                            name="username" 
                            placeholder="Username"
                            required 
                            autofocus
                        >
                    </div>

                    <div class="input-group-custom">
                        <i class="bi bi-lock input-icon"></i>
                        <input 
                            type="password" 
                            class="form-control-custom" 
                            id="password" 
                            name="password" 
                            placeholder="Password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span class="btn-text">Sign In</span>
                    </button>
                </form>

                
                <div class="security-note">
                    <i class="bi bi-shield-check"></i>
                    Authorized personnel only
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    
    <script>
        if (window.location.search.indexOf('timeout=1') !== -1 && window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
        });
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
</body>
</html>
