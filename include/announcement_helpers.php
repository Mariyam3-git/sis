<?php
// Announcement helper functions for COMIUM SIS.
// Admin/owner users can publish announcements. All roles see active announcements in the navbar and dashboards.

if (!function_exists('sisEnsureAnnouncementTables')) {
    function sisEnsureAnnouncementTables($conn) {
        if (!$conn) return false;
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS announcements (
            id INT NOT NULL AUTO_INCREMENT,
            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            priority ENUM('info','important','urgent') NOT NULL DEFAULT 'info',
            audience ENUM('all','admin','super_admin','supervisor','agent') NOT NULL DEFAULT 'all',
            outlet_id INT NULL,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            requires_ack TINYINT(1) NOT NULL DEFAULT 0,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_announcements_active (status, starts_at, ends_at, audience, outlet_id),
            KEY idx_announcements_pinned (is_pinned, status, created_at),
            KEY idx_announcements_created (created_at),
            KEY idx_announcements_creator (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS announcement_reads (
            announcement_id INT NOT NULL,
            user_id INT NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (announcement_id, user_id),
            KEY idx_announcement_reads_user (user_id, read_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (function_exists('tableExists') && tableExists($conn, 'announcements')) {
            $columns = [
                'title' => "ALTER TABLE announcements ADD COLUMN title VARCHAR(180) NOT NULL DEFAULT 'Announcement' AFTER id",
                'message' => "ALTER TABLE announcements ADD COLUMN message TEXT NULL AFTER title",
                'priority' => "ALTER TABLE announcements ADD COLUMN priority ENUM('info','important','urgent') NOT NULL DEFAULT 'info' AFTER message",
                'audience' => "ALTER TABLE announcements ADD COLUMN audience ENUM('all','admin','super_admin','supervisor','agent') NOT NULL DEFAULT 'all' AFTER priority",
                'outlet_id' => "ALTER TABLE announcements ADD COLUMN outlet_id INT NULL AFTER audience",
                'status' => "ALTER TABLE announcements ADD COLUMN status ENUM('draft','published','archived') NOT NULL DEFAULT 'published' AFTER outlet_id",
                // These two columns were added after the first announcement module.
                // Keep them in the repair list so old installations do not crash on is_pinned/requires_ack queries.
                'is_pinned' => "ALTER TABLE announcements ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
                'requires_ack' => "ALTER TABLE announcements ADD COLUMN requires_ack TINYINT(1) NOT NULL DEFAULT 0 AFTER is_pinned",
                'starts_at' => "ALTER TABLE announcements ADD COLUMN starts_at DATETIME NULL AFTER requires_ack",
                'ends_at' => "ALTER TABLE announcements ADD COLUMN ends_at DATETIME NULL AFTER starts_at",
                'created_by' => "ALTER TABLE announcements ADD COLUMN created_by INT NULL AFTER ends_at",
                'created_at' => "ALTER TABLE announcements ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by",
                'updated_at' => "ALTER TABLE announcements ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            ];
            foreach ($columns as $column => $sql) {
                if (function_exists('tableColumnExists') && !tableColumnExists($conn, 'announcements', $column)) @mysqli_query($conn, $sql);
            }
            if (function_exists('indexExists')) {
                if (!indexExists($conn, 'announcements', 'idx_announcements_active')) @mysqli_query($conn, "ALTER TABLE announcements ADD KEY idx_announcements_active (status, starts_at, ends_at, audience, outlet_id)");
                if (!indexExists($conn, 'announcements', 'idx_announcements_pinned')) @mysqli_query($conn, "ALTER TABLE announcements ADD KEY idx_announcements_pinned (is_pinned, status, created_at)");
                if (!indexExists($conn, 'announcements', 'idx_announcements_created')) @mysqli_query($conn, "ALTER TABLE announcements ADD KEY idx_announcements_created (created_at)");
                if (!indexExists($conn, 'announcements', 'idx_announcements_creator')) @mysqli_query($conn, "ALTER TABLE announcements ADD KEY idx_announcements_creator (created_by)");
            }
        }

        if (function_exists('tableExists') && tableExists($conn, 'announcement_reads')) {
            if (function_exists('tableColumnExists')) {
                if (!tableColumnExists($conn, 'announcement_reads', 'announcement_id')) @mysqli_query($conn, "ALTER TABLE announcement_reads ADD COLUMN announcement_id INT NOT NULL FIRST");
                if (!tableColumnExists($conn, 'announcement_reads', 'user_id')) @mysqli_query($conn, "ALTER TABLE announcement_reads ADD COLUMN user_id INT NOT NULL AFTER announcement_id");
                if (!tableColumnExists($conn, 'announcement_reads', 'read_at')) @mysqli_query($conn, "ALTER TABLE announcement_reads ADD COLUMN read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER user_id");
            }
            if (function_exists('indexExists')) {
                if (!indexExists($conn, 'announcement_reads', 'idx_announcement_reads_user')) @mysqli_query($conn, "ALTER TABLE announcement_reads ADD KEY idx_announcement_reads_user (user_id, read_at)");
            }
        }

        return (!function_exists('tableExists') || (tableExists($conn, 'announcements') && tableExists($conn, 'announcement_reads')));
    }
}

if (!function_exists('sisAnnouncementAudienceSql')) {
    function sisAnnouncementAudienceSql(&$params, &$types, $includeReadJoin = false) {
        $role = strtolower((string)($_SESSION['role'] ?? ''));
        $outletId = function_exists('normalizeNullableInt') ? normalizeNullableInt($_SESSION['outlet_id'] ?? null) : (($_SESSION['outlet_id'] ?? null) === '' ? null : (int)($_SESSION['outlet_id'] ?? 0));
        $sql = "a.status = 'published' AND (a.starts_at IS NULL OR a.starts_at <= NOW()) AND (a.ends_at IS NULL OR a.ends_at >= NOW()) AND (a.audience = 'all'";

        if ($role !== '') {
            if ($role === 'super_admin') {
                // Owner should see owner, admin and all announcements.
                $sql .= " OR a.audience IN ('super_admin','admin')";
            } else {
                $sql .= " OR a.audience = ?";
                $params[] = $role;
                $types .= 's';
            }
        }
        $sql .= ")";

        if ($outletId !== null && $outletId > 0) {
            $sql .= " AND (a.outlet_id IS NULL OR a.outlet_id = ?)";
            $params[] = $outletId;
            $types .= 'i';
        } else {
            // Users without outlet, usually Admin or owner accounts, should see global announcements.
            $sql .= " AND a.outlet_id IS NULL";
        }
        return $sql;
    }
}

if (!function_exists('sisGetActiveAnnouncementsForCurrentUser')) {
    function sisGetActiveAnnouncementsForCurrentUser($conn, $limit = 5) {
        if (!$conn || empty($_SESSION['user_id']) || !sisEnsureAnnouncementTables($conn)) return [];
        $limit = max(1, min(30, (int)$limit));
        $userId = (int)$_SESSION['user_id'];
        $params = [];
        $types = '';
        $where = sisAnnouncementAudienceSql($params, $types);
        $sql = "SELECT a.*, CASE WHEN ar.user_id IS NULL THEN 0 ELSE 1 END AS is_read
                FROM announcements a
                LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
                WHERE $where
                ORDER BY a.is_pinned DESC, FIELD(a.priority,'urgent','important','info'), a.created_at DESC, a.id DESC
                LIMIT ?";
        array_unshift($params, $userId);
        $types = 'i' . $types . 'i';
        $params[] = $limit;
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) $rows[] = $row;
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('sisAnnouncementUnreadCountForCurrentUser')) {
    function sisAnnouncementUnreadCountForCurrentUser($conn) {
        if (!$conn || empty($_SESSION['user_id']) || !sisEnsureAnnouncementTables($conn)) return 0;
        $userId = (int)$_SESSION['user_id'];
        $params = [];
        $types = '';
        $where = sisAnnouncementAudienceSql($params, $types);
        $sql = "SELECT COUNT(*) AS c
                FROM announcements a
                LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
                WHERE $where AND ar.user_id IS NULL";
        array_unshift($params, $userId);
        $types = 'i' . $types;
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return 0;
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('sisMarkAnnouncementRead')) {
    function sisMarkAnnouncementRead($conn, $announcementId, $userId = null) {
        if (!$conn || !sisEnsureAnnouncementTables($conn)) return false;
        $announcementId = (int)$announcementId;
        $userId = (int)($userId ?: ($_SESSION['user_id'] ?? 0));
        if ($announcementId <= 0 || $userId <= 0) return false;
        $stmt = mysqli_prepare($conn, "INSERT INTO announcement_reads (announcement_id, user_id, read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE read_at = NOW()");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'ii', $announcementId, $userId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('sisMarkAllAnnouncementsRead')) {
    function sisMarkAllAnnouncementsRead($conn) {
        if (!$conn || empty($_SESSION['user_id']) || !sisEnsureAnnouncementTables($conn)) return false;
        $rows = sisGetActiveAnnouncementsForCurrentUser($conn, 30);
        $ok = true;
        foreach ($rows as $row) {
            $ok = sisMarkAnnouncementRead($conn, (int)$row['id']) && $ok;
        }
        return $ok;
    }
}


if (!function_exists('sisShortText')) {
    function sisShortText($value, $limit = 100) {
        $value = trim((string)($value ?? ''));
        $limit = max(10, (int)$limit);
        if (function_exists('mb_strimwidth')) return mb_strimwidth($value, 0, $limit, '...');
        return strlen($value) > $limit ? substr($value, 0, $limit - 3) . '...' : $value;
    }
}

if (!function_exists('sisAnnouncementPriorityClass')) {
    function sisAnnouncementPriorityClass($priority) {
        $priority = strtolower((string)$priority);
        if ($priority === 'urgent') return 'danger';
        if ($priority === 'important') return 'warning';
        return 'primary';
    }
}

if (!function_exists('sisAnnouncementPriorityIcon')) {
    function sisAnnouncementPriorityIcon($priority) {
        $priority = strtolower((string)$priority);
        if ($priority === 'urgent') return 'bi-exclamation-octagon-fill';
        if ($priority === 'important') return 'bi-exclamation-triangle-fill';
        return 'bi-megaphone-fill';
    }
}

if (!function_exists('sisAnnouncementDateLabel')) {
    function sisAnnouncementDateLabel($value) {
        $value = trim((string)($value ?? ''));
        if ($value === '' || $value === '0000-00-00 00:00:00') return '';
        $ts = strtotime($value);
        return $ts ? date('d M Y H:i', $ts) : $value;
    }
}

if (!function_exists('sisRenderDashboardAnnouncements')) {
    function sisRenderDashboardAnnouncements($conn, $contextTitle = 'Announcements') {
        $items = sisGetActiveAnnouncementsForCurrentUser($conn, 6);
        $user = $_SESSION['full_name'] ?? 'User';
        $total = count($items);
        ?>
        <section class="sis-dashboard-announcements mb-4">
            <div class="sis-announcement-hero sis-announcement-showcase" data-sis-announcement-hero-slider>
                <div class="sis-announcement-orb orb-one"></div>
                <div class="sis-announcement-orb orb-two"></div>
                <div class="sis-announcement-hero-head">
                    <div>
                        <span class="sis-kicker"><i class="bi bi-megaphone-fill"></i> <?php echo e($contextTitle); ?></span>
                        <h2>Today&apos;s announcement for <?php echo e($user); ?></h2>
                    </div>
                    <div class="sis-announcement-hero-actions">
                        <?php if ($total > 1): ?>
                            <span class="sis-announcement-count-pill"><i class="bi bi-layers"></i> <?php echo (int)$total; ?> updates</span>
                        <?php endif; ?>
                        <a href="<?php echo e(appPath('announcements.php')); ?>" class="btn btn-light btn-sm border"><i class="bi bi-list-check"></i> View Board</a>
                    </div>
                </div>
                <?php if (!empty($items)): ?>
                    <div class="sis-announcement-slider-window">
                        <?php foreach ($items as $idx => $a): $class = sisAnnouncementPriorityClass($a['priority'] ?? 'info'); ?>
                            <article class="sis-announcement-slide <?php echo $idx === 0 ? 'active' : ''; ?> <?php echo empty($a['is_read']) ? 'unread' : ''; ?> priority-<?php echo e($class); ?>" data-announcement-slide="<?php echo (int)$idx; ?>" aria-hidden="<?php echo $idx === 0 ? 'false' : 'true'; ?>">
                                <div class="sis-announcement-slide-accent"></div>
                                <div class="sis-announcement-icon xl"><i class="bi <?php echo e(sisAnnouncementPriorityIcon($a['priority'] ?? 'info')); ?>"></i></div>
                                <div class="sis-announcement-body">
                                    <div class="d-flex justify-content-between gap-2 flex-wrap align-items-start">
                                        <div>
                                            <h3><?php echo e($a['title']); ?></h3>
                                            <div class="sis-announcement-meta-line">
                                                <span><i class="bi bi-clock"></i> <?php echo e(sisAnnouncementDateLabel($a['created_at'] ?? '')); ?></span>
                                                <?php if (!empty($a['is_pinned'])): ?><span><i class="bi bi-pin-angle"></i> Pinned</span><?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="d-inline-flex gap-1 flex-wrap">
                                            <span class="badge text-bg-<?php echo e($class); ?> sis-soft-badge"><?php echo e(ucfirst($a['priority'] ?? 'info')); ?></span>
                                            <?php if (empty($a['is_read'])): ?><span class="badge text-bg-success sis-soft-badge">New</span><?php else: ?><span class="badge text-bg-light text-secondary border sis-soft-badge">Read</span><?php endif; ?>
                                        </span>
                                    </div>
                                    <p><?php echo nl2br(e($a['message'])); ?></p>
                                    <div class="sis-announcement-slide-footer">
                                        <div class="sis-announcement-progress-text"><span data-announcement-current><?php echo (int)($idx + 1); ?></span> / <?php echo (int)$total; ?></div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <?php if (empty($a['is_read'])): ?>
                                                <button type="button" class="btn btn-sm btn-primary sis-announcement-inline-read" data-inline-announcement-read="<?php echo (int)$a['id']; ?>"><i class="bi bi-check2"></i> Mark Read</button>
                                            <?php endif; ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(appPath('announcements.php')); ?>#announcement-<?php echo (int)$a['id']; ?>"><i class="bi bi-eye"></i> Details</a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($total > 1): ?>
                        <div class="sis-announcement-slider-controls" aria-label="Announcement controls">
                            <button type="button" class="btn btn-sm btn-light border" data-announcement-prev><i class="bi bi-chevron-left"></i></button>
                            <div class="sis-announcement-dots">
                                <?php foreach ($items as $idx => $a): ?>
                                    <button type="button" class="<?php echo $idx === 0 ? 'active' : ''; ?>" data-announcement-dot="<?php echo (int)$idx; ?>" aria-label="Show announcement <?php echo (int)($idx + 1); ?>"></button>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border" data-announcement-next><i class="bi bi-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sis-announcement-empty premium-empty">
                        <i class="bi bi-check2-circle"></i>
                        <div><strong>No active announcement right now.</strong><small>You are up to date.</small></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
