<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$script = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '');
$base = rtrim(dirname($script), '/');
if (substr($base, -4) === '/404') $base = dirname($base);
$role = strtolower((string)($_SESSION['role'] ?? ''));
$dashboard = $base . '/login.php';
if ($role === 'admin' || $role === 'super_admin') $dashboard = $base . '/admin/dashboard.php';
elseif ($role === 'supervisor') $dashboard = $base . '/supervisor/dashboard.php';
elseif ($role === 'agent') $dashboard = $base . '/agent/dashboard.php';
$ref = $_SERVER['HTTP_REFERER'] ?? '';
$back = ($ref && stripos($ref, $_SERVER['HTTP_HOST'] ?? '') !== false) ? $ref : '';
http_response_code(404);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page Not Found - Showroom Information System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo h($base); ?>/assets/css/sis-theme.css">
<link rel="icon" type="image/svg+xml" href="<?php echo h($base); ?>/assets/images/favicon.svg">
</head><body><main class="sis-404-wrap"><section class="sis-404-card">
<img src="<?php echo h($base); ?>/assets/images/logo.svg" alt="Company logo">
<div class="sis-404-code">404</div>
<h1 class="mb-2">Page not found</h1>
<p class="text-muted mb-4">The page you are looking for may have been moved, renamed, or you may not have permission to open it.</p>
<div class="d-flex gap-2 justify-content-center flex-wrap">
<?php if ($back): ?><a class="btn btn-outline-secondary" href="<?php echo h($back); ?>"><i class="bi bi-arrow-left"></i> Back to Previous Page</a><?php else: ?><button class="btn btn-outline-secondary" onclick="history.back()"><i class="bi bi-arrow-left"></i> Back to Previous Page</button><?php endif; ?>
<a class="btn btn-primary" href="<?php echo h($dashboard); ?>"><i class="bi bi-speedometer2"></i> Go to Dashboard</a>
</div>
</section></main></body></html>
