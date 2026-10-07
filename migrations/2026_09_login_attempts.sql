-- Failed-login tracking used by login.php for lockout per username and per IP.
-- login.php and admin/run_upgrade.php create this table automatically; this file is for manual installs.
CREATE TABLE IF NOT EXISTS sis_login_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    KEY idx_login_attempt_user (username, attempted_at),
    KEY idx_login_attempt_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
