# SIS Redirection Fix Report

Date: 2026-06-17

## Problem Fixed

The previous upgraded package could redirect users incorrectly when the system was opened from different folders such as:

- `/SIS/login.php`
- `/SIS/admin/dashboard.php`
- `/SIS/agent/dashboard.php`
- `/SIS/supervisor/dashboard.php`

The issue came from mixed relative redirects such as `../dashboard.php`, `../login.php`, and folder-dependent helper paths.

## Fix Applied

### 1. Root-safe application path helper

A new `appBaseUrl()` helper was added in `config.php`. It detects the project base folder automatically, for example `/SIS`, then builds redirects from the root of the app.

Examples:

- Login redirect now goes to `/SIS/login.php`
- Admin dashboard redirect now goes to `/SIS/admin/dashboard.php`
- Agent dashboard redirect now goes to `/SIS/agent/dashboard.php`
- Supervisor dashboard redirect now goes to `/SIS/supervisor/dashboard.php`

This works even when the current page is inside `admin`, `agent`, or `supervisor`.

### 2. Role router preserved

The root `dashboard.php` still works as the central role router:

- Admin and Super Admin → `admin/dashboard.php`
- Supervisor → `supervisor/dashboard.php`
- Agent → `agent/dashboard.php`

### 3. Manual folder redirects fixed

Manual redirects such as:

```php
header('Location: ../dashboard.php');
```

were replaced with:

```php
redirectTo(appPath('dashboard.php'));
```

This prevents wrong-folder redirects and redirect loops.

### 4. Agent profile compatibility fixed

`agent/profile.php` used `isLoggedIn()`, but the upgraded config used `isAuthenticated()`. A compatibility alias was added:

```php
function isLoggedIn(): bool {
    return isAuthenticated();
}
```

### 5. Correct database config preserved

The config keeps your real AMpps database settings:

```php
DB_HOST = localhost
DB_USER = root
DB_PASS = [REDACTED]
DB_NAME = comium_gm
```

## Validation

PHP syntax lint was run across all PHP files and passed with no syntax errors.

## Files Updated

- `config.php`
- Admin page role redirects
- Supervisor page role redirects
- Agent page role redirects
- `agent/profile.php`
- `admin/reopen_shift.php`
