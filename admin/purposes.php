<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Handle operations
$message = '';
$message_type = '';

// Archive purpose (soft delete)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $query = "UPDATE purpose_of_visit SET is_active = 0 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'DELETE_PURPOSE', $id);
        $message = 'Purpose archived!';
        $message_type = 'success';
    }
}

// Restore purpose
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $query = "UPDATE purpose_of_visit SET is_active = 1 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'RESTORE_PURPOSE', $id);
        $message = 'Purpose restored!';
        $message_type = 'success';
    }
}

// Permanent delete
if (isset($_GET['hard_delete'])) {
    $id = (int)$_GET['hard_delete'];
    $query = "DELETE FROM purpose_of_visit WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'PERMANENT_DELETE_PURPOSE', $id);
        $message = 'Purpose permanently deleted!';
        $message_type = 'success';
    }
}

// Add/Edit purpose
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purpose'])) {
    $name = mysqli_real_escape_string($conn, (string)($_POST['name'] ?? ''));
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 'NULL';
    $icon = mysqli_real_escape_string($conn, (string)($_POST['icon'] ?? ''));
    $color = mysqli_real_escape_string($conn, (string)($_POST['color'] ?? ''));
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $query = "UPDATE purpose_of_visit SET 
                  name = '$name', 
                  category_id = $category_id, 
                  icon = '$icon', 
                  color = '$color' 
                  WHERE id = $id";
        $action = 'UPDATE_PURPOSE';
    } else {
        $query = "INSERT INTO purpose_of_visit (name, category_id, icon, color, is_active) 
                  VALUES ('$name', $category_id, '$icon', '$color', 1)";
        $action = 'ADD_PURPOSE';
    }
    
    if (mysqli_query($conn, $query)) {
        $record_id = isset($id) ? $id : mysqli_insert_id($conn);
        logAction($conn, $action, $record_id);
        $message = 'Purpose saved successfully!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Get all categories for filter
$categories_query = "SELECT * FROM purpose_categories WHERE is_active = 1 ORDER BY sort_order, name";
$categories_result = mysqli_query($conn, $categories_query);

// Build WHERE clause for filtering
$where_conditions = [];
if ($show_inactive) {
    $where_conditions[] = "(p.is_active = 1 OR p.is_active = 0)";
} else {
    $where_conditions[] = "p.is_active = 1";
}
if ($filter_category > 0) {
    $where_conditions[] = "p.category_id = $filter_category";
}
if ($search) {
    $where_conditions[] = "(p.name LIKE '%$search%' OR c.name LIKE '%$search%')";
}
$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get purposes with filters
$purposes_query = "SELECT p.*, c.name as category_name, c.color as category_color 
                   FROM purpose_of_visit p 
                   LEFT JOIN purpose_categories c ON p.category_id = c.id 
                   $where_sql
                   ORDER BY p.is_active DESC, c.sort_order, p.name";
$purposes_result = mysqli_query($conn, $purposes_query);

// Group purposes by category
$purposes_by_category = [];
$inactive_purposes = [];

while ($purpose = mysqli_fetch_assoc($purposes_result)) {
    if ($purpose['is_active'] == 0) {
        $inactive_purposes[] = $purpose;
    } else {
        $category = $purpose['category_name'] ?? 'Uncategorized';
        if (!isset($purposes_by_category[$category])) {
            $purposes_by_category[$category] = [];
        }
        $purposes_by_category[$category][] = $purpose;
    }
}

// Sort categories alphabetically
ksort($purposes_by_category);

// Get purpose for editing
$edit_purpose = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM purpose_of_visit WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_purpose = mysqli_fetch_assoc($edit_result);
}

// Count totals
$total_active = 0;
foreach ($purposes_by_category as $category_purposes) {
    $total_active += count($category_purposes);
}

// Common icons
$icons = [
    'bi-wifi', 'bi-hdd-stack', 'bi-tools', 'bi-gear',
    'bi-chat', 'bi-headset', 'bi-megaphone', 'bi-graph-up',
    'bi-cash', 'bi-briefcase', 'bi-building', 'bi-person-badge',
    'bi-calendar', 'bi-clock', 'bi-tag', 'bi-three-dots',
    'bi-phone', 'bi-envelope', 'bi-file-text', 'bi-question-circle'
];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Visit Purposes - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>


    
    <div class="main-content">
        
        <div class="page-header">
            <h3><i class="bi bi-tags sis-u-20e33279"></i>Visit Purposes</h3>
            <div class="header-actions">
                <a href="purpose_categories.php" class="btn-outline-primary">
                    <i class="bi bi-folder"></i> Manage Categories
                </a>
                <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
            </div>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Active Purposes</h6>
                <h3><?php echo $total_active; ?></h3>
                <small>Total active</small>
            </div>
            <div class="stat-card">
                <h6>Categories</h6>
                <h3><?php echo mysqli_num_rows($categories_result); ?></h3>
                <small>Purpose groups</small>
            </div>
            <div class="stat-card">
                <h6>Archived</h6>
                <h3><?php echo count($inactive_purposes); ?></h3>
                <small>Inactive purposes</small>
            </div>
            <div class="stat-card">
                <h6>Total All Time</h6>
                <h3><?php echo $total_active + count($inactive_purposes); ?></h3>
                <small>Including archived</small>
            </div>
        </div>

        
        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="filter-bar">
            <form class="sis-u-f0d95916" method="GET">
                <div class="search-box">
                    <input type="text" class="form-control" name="search" placeholder="🔍 Search by purpose name or category..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select class="form-select" name="category">
                        <option value="0">All Categories</option>
                        <?php 
                        mysqli_data_seek($categories_result, 0);
                        while($category = mysqli_fetch_assoc($categories_result)): 
                        ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $filter_category == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select class="form-select" name="status">
                        <option value="active" <?php echo !$show_inactive ? 'selected' : ''; ?>>Active Only</option>
                        <option value="all" <?php echo $show_inactive ? 'selected' : ''; ?>>All (incl. Archived)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="purposes.php" class="btn btn-outline-secondary">Reset</a>
            </form>
        </div>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?php echo $edit_purpose ? 'pencil-square' : 'plus-circle'; ?>"></i>
                    <?php echo $edit_purpose ? 'Edit Purpose' : 'Add New Purpose'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="save_purpose" value="1">
                        <?php if($edit_purpose): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_purpose['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Purpose Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($edit_purpose['name'] ?? ''); ?>" 
                                   placeholder="e.g., Technical Support" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id">
                                <option value="">-- Uncategorized --</option>
                                <?php 
                                mysqli_data_seek($categories_result, 0);
                                while($category = mysqli_fetch_assoc($categories_result)): 
                                ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($edit_purpose['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Icon</label>
                                <select class="form-select" name="icon">
                                    <option value="bi-tag">Default</option>
                                    <?php foreach($icons as $icon): ?>
                                    <option value="<?php echo $icon; ?>" 
                                        <?php echo ($edit_purpose['icon'] ?? '') == $icon ? 'selected' : ''; ?>>
                                        <?php echo str_replace('bi-', '', $icon); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-6 mb-3">
                                <label class="form-label">Color</label>
                                <input type="color" class="form-control color-input" name="color" 
                                       value="<?php echo $edit_purpose['color'] ?? '#6c757d'; ?>">
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_purpose ? 'Update Purpose' : 'Add Purpose'; ?>
                            </button>
                            <?php if($edit_purpose): ?>
                            <a href="purposes.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list-ul"></i> Purposes by Category</span>
                    <span class="badge bg-secondary"><?php echo $total_active; ?> active</span>
                </div>
                <div class="card-body p-0">
                    
                    <div id="purposesContainer">
                        <?php if (count($purposes_by_category) > 0): ?>
                            <?php foreach ($purposes_by_category as $category => $purposes): ?>
                            <div class="category-group" data-category="<?php echo htmlspecialchars($category); ?>">
                                <div class="group-header" onclick="toggleGroup(this)">
                                    <span>
                                        <i class="bi bi-chevron-down"></i>
                                        <i class="bi bi-folder me-1 sis-u-002e4b71"></i>
                                        <?php echo htmlspecialchars($category); ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo count($purposes); ?> purposes</span>
                                </div>
                                <div class="purpose-list">
                                    <div class="table-scroll">
                                        <table class="compact-table">
                                            <thead>
                                                <tr>
                                                    <th>Purpose</th>
                                                    <th>Icon</th>
                                                    <th class="sis-u-e3d221c5">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($purposes as $purpose): ?>
                                                <tr data-purpose-name="<?php echo strtolower(htmlspecialchars($purpose['name'])); ?>"
                                                    data-purpose-category="<?php echo strtolower(htmlspecialchars($category)); ?>">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span class="purpose-icon sis-dynamic-color" data-color="<?php echo e($purpose['color'] ?? '#666'); ?>">
                                                                <i class="bi <?php echo $purpose['icon'] ?? 'bi-tag'; ?>"></i>
                                                            </span>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($purpose['name']); ?></strong>
                                                                <div class="small text-muted"><?php echo $category != 'Uncategorized' ? htmlspecialchars($category) : 'No category'; ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge sis-dynamic-color" data-color="<?php echo e($purpose['color'] ?? '#666'); ?>">
                                                            <i class="bi <?php echo $purpose['icon'] ?? 'bi-tag'; ?>"></i> <?php echo str_replace('bi-', '', $purpose['icon'] ?? 'tag'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <a href="?edit=<?php echo $purpose['id']; ?>" class="action-btn" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="?delete=<?php echo $purpose['id']; ?>" class="action-btn text-warning" 
                                                               onclick="return confirm('Archive this purpose?')" title="Archive">
                                                                <i class="bi bi-archive"></i>
                                                            </a>
                                                        </div>
                                                     </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-tags"></i>
                                <p>No purposes found<?php echo $search ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?></p>
                                <a href="purposes.php" class="btn btn-primary btn-sm">View All Purposes</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    
                    <?php if ($show_inactive && count($inactive_purposes) > 0): ?>
                    <div class="inactive-section">
                        <div class="category-group">
                            <div class="group-header" onclick="toggleGroup(this)">
                                <span>
                                    <i class="bi bi-chevron-down"></i>
                                    <i class="bi bi-archive me-1"></i>
                                    Archived Purposes
                                </span>
                                <span class="badge bg-secondary"><?php echo count($inactive_purposes); ?> archived</span>
                            </div>
                            <div class="purpose-list">
                                <div class="table-scroll">
                                    <table class="compact-table">
                                        <thead>
                                            <tr>
                                                <th>Purpose Name</th>
                                                <th>Original Category</th>
                                                <th class="sis-u-e3d221c5">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inactive_purposes as $purpose): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="purpose-icon sis-dynamic-color" data-color="<?php echo e($purpose['color'] ?? '#666'); ?>">
                                                            <i class="bi <?php echo $purpose['icon'] ?? 'bi-tag'; ?>"></i>
                                                        </span>
                                                        <strong><?php echo htmlspecialchars($purpose['name']); ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if($purpose['category_name']): ?>
                                                    <span class="badge sis-dynamic-color" data-color="<?php echo e($purpose['category_color'] ?? '#666'); ?>">
                                                        <?php echo htmlspecialchars($purpose['category_name']); ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="?restore=<?php echo $purpose['id']; ?>" class="action-btn text-success" 
                                                           onclick="return confirm('Restore this purpose?')" title="Restore">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </a>
                                                        <a href="?hard_delete=<?php echo $purpose['id']; ?>" class="action-btn text-danger" 
                                                           onclick="return confirm('⚠️ PERMANENTLY DELETE this purpose? This cannot be undone! ⚠️')" title="Permanently Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar') || document.querySelector('[data-sis-sidebar]');
            if (sidebar) sidebar.classList.toggle('open');
        }
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar') || document.querySelector('[data-sis-sidebar]');
            const menuToggle = document.querySelector('.menu-toggle') || document.querySelector('[data-sis-sidebar-toggle]');
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                }
            }
        });
        function toggleGroup(headerElement) {
            const purposeList = headerElement.nextElementSibling;
            const icon = headerElement.querySelector('i:first-child');
            
            if (purposeList.classList.contains('collapsed')) {
                purposeList.classList.remove('collapsed');
                if (icon) icon.style.transform = 'rotate(0deg)';
                headerElement.classList.remove('collapsed');
            } else {
                purposeList.classList.add('collapsed');
                if (icon) icon.style.transform = 'rotate(-90deg)';
                headerElement.classList.add('collapsed');
            }
        }
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const categoryGroups = document.querySelectorAll('.category-group');
                
                categoryGroups.forEach(group => {
                    let hasVisiblePurposes = false;
                    const rows = group.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const purposeName = row.getAttribute('data-purpose-name') || '';
                        const purposeCategory = row.getAttribute('data-purpose-category') || '';
                        const rowText = (purposeName + ' ' + purposeCategory).toLowerCase();
                        
                        if (searchTerm === '' || rowText.includes(searchTerm)) {
                            row.style.display = '';
                            hasVisiblePurposes = true;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    if (hasVisiblePurposes || searchTerm === '') {
                        group.style.display = '';
                    } else {
                        group.style.display = 'none';
                    }
                });
            });
        }
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
        document.querySelectorAll('.group-header').forEach(header => {
            const purposeList = header.nextElementSibling;
            if (purposeList && !purposeList.classList.contains('collapsed')) {
                purposeList.classList.remove('collapsed');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>