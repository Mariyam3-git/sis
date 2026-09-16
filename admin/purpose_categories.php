<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

// Handle operations
$message = '';
$message_type = '';

// Archive category (soft delete)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if category has purposes
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM purpose_of_visit WHERE category_id = $id AND is_active = 1");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['count'] > 0) {
        $message = 'Cannot delete - ' . $row['count'] . ' active purposes use this category';
        $message_type = 'danger';
    } else {
        $query = "UPDATE purpose_categories SET is_active = 0 WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            logAction($conn, 'DELETE_PURPOSE_CATEGORY', $id);
            $message = 'Category archived!';
            $message_type = 'success';
        }
    }
}

// Restore category
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $query = "UPDATE purpose_categories SET is_active = 1 WHERE id = $id";
    if (mysqli_query($conn, $query)) {
        logAction($conn, 'RESTORE_PURPOSE_CATEGORY', $id);
        $message = 'Category restored!';
        $message_type = 'success';
    }
}

// Permanent delete
if (isset($_GET['hard_delete'])) {
    $id = (int)$_GET['hard_delete'];
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM purpose_of_visit WHERE category_id = $id");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['count'] > 0) {
        $message = 'Cannot permanently delete - ' . $row['count'] . ' purposes reference this category';
        $message_type = 'danger';
    } else {
        $query = "DELETE FROM purpose_categories WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            logAction($conn, 'PERMANENT_DELETE_PURPOSE_CATEGORY', $id);
            $message = 'Category permanently deleted!';
            $message_type = 'success';
        }
    }
}

// Add/Edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $name = mysqli_real_escape_string($conn, (string)($_POST['name'] ?? ''));
    $description = mysqli_real_escape_string($conn, (string)($_POST['description'] ?? ''));
    $icon = mysqli_real_escape_string($conn, (string)($_POST['icon'] ?? ''));
    $color = mysqli_real_escape_string($conn, (string)($_POST['color'] ?? ''));
    $sort_order = (int)$_POST['sort_order'];
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $query = "UPDATE purpose_categories SET 
                  name = '$name', 
                  description = '$description', 
                  icon = '$icon', 
                  color = '$color', 
                  sort_order = $sort_order 
                  WHERE id = $id";
        $action = 'UPDATE_PURPOSE_CATEGORY';
    } else {
        $query = "INSERT INTO purpose_categories (name, description, icon, color, sort_order, is_active) 
                  VALUES ('$name', '$description', '$icon', '$color', $sort_order, 1)";
        $action = 'ADD_PURPOSE_CATEGORY';
    }
    
    if (mysqli_query($conn, $query)) {
        $record_id = isset($id) ? $id : mysqli_insert_id($conn);
        logAction($conn, $action, $record_id);
        $message = 'Category saved successfully!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';

// Build WHERE clause for filtering
$where_conditions = [];
if ($show_inactive) {
    $where_conditions[] = "(is_active = 1 OR is_active = 0)";
} else {
    $where_conditions[] = "is_active = 1";
}
if ($search) {
    $where_conditions[] = "(name LIKE '%$search%' OR description LIKE '%$search%')";
}
$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get categories with filters
$categories_query = "SELECT * FROM purpose_categories $where_sql ORDER BY sort_order, name";
$categories_result = mysqli_query($conn, $categories_query);

// Group categories (active and inactive)
$active_categories = [];
$inactive_categories = [];

while ($category = mysqli_fetch_assoc($categories_result)) {
    // Get purpose count for each category
    $count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM purpose_of_visit WHERE category_id = {$category['id']} AND is_active = 1");
    $count = mysqli_fetch_assoc($count_query)['count'];
    $category['purpose_count'] = $count;
    
    if ($category['is_active'] == 0) {
        $inactive_categories[] = $category;
    } else {
        $active_categories[] = $category;
    }
}

// Get category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM purpose_categories WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_category = mysqli_fetch_assoc($edit_result);
}

// Count totals
$total_active = count($active_categories);
$total_purposes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM purpose_of_visit WHERE is_active = 1"))['count'];

// Common icons
$icons = [
    'bi-gear', 'bi-wifi', 'bi-tools', 'bi-headset',
    'bi-chat', 'bi-megaphone', 'bi-graph-up', 'bi-people',
    'bi-cash', 'bi-briefcase', 'bi-building', 'bi-person-badge',
    'bi-calendar', 'bi-clock', 'bi-tag', 'bi-three-dots',
    'bi-folder', 'bi-archive', 'bi-star', 'bi-heart'
];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Purpose Categories - Comium GM</title>
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
            <h3><i class="bi bi-folder sis-u-20e33279"></i>Purpose Categories</h3>
            <div class="header-actions">
                <a href="purposes.php" class="btn-outline-primary">
                    <i class="bi bi-tags"></i> Manage Purposes
                </a>
                <span class="date-badge"><i class="bi bi-calendar"></i> <?php echo date('d M Y'); ?></span>
            </div>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Active Categories</h6>
                <h3><?php echo $total_active; ?></h3>
                <small>Total active</small>
            </div>
            <div class="stat-card">
                <h6>Total Purposes</h6>
                <h3><?php echo $total_purposes; ?></h3>
                <small>Across all categories</small>
            </div>
            <div class="stat-card">
                <h6>Archived</h6>
                <h3><?php echo count($inactive_categories); ?></h3>
                <small>Inactive categories</small>
            </div>
            <div class="stat-card">
                <h6>Total All Time</h6>
                <h3><?php echo $total_active + count($inactive_categories); ?></h3>
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
                    <input type="text" class="form-control" name="search" placeholder="Search by name or description..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group sis-u-9102618d">
                    <select class="form-select" name="status">
                        <option value="active" <?php echo !$show_inactive ? 'selected' : ''; ?>>Active Only</option>
                        <option value="all" <?php echo $show_inactive ? 'selected' : ''; ?>>All (incl. Archived)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="purpose_categories.php" class="btn btn-outline-secondary">Reset</a>
            </form>
        </div>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?php echo $edit_category ? 'pencil-square' : 'plus-circle'; ?>"></i>
                    <?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="save_category" value="1">
                        <?php if($edit_category): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_category['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>" 
                                   placeholder="e.g., Technical, Sales, Customer Care" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" 
                                      placeholder="Brief description of this category"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Icon</label>
                                <select class="form-select" name="icon">
                                    <option value="bi-folder">Default</option>
                                    <?php foreach($icons as $icon): ?>
                                    <option value="<?php echo $icon; ?>" 
                                        <?php echo ($edit_category['icon'] ?? '') == $icon ? 'selected' : ''; ?>>
                                        <?php echo str_replace('bi-', '', $icon); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-6 mb-3">
                                <label class="form-label">Color</label>
                                <div class="color-input-group">
                                    <input type="color" class="form-control" name="color" 
                                           value="<?php echo $edit_category['color'] ?? '#6c757d'; ?>" 
                                          >
                                    <span class="color-preview sis-dynamic-color" data-color="<?php echo e($edit_category['color'] ?? '#6c757d'); ?>"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" 
                                   value="<?php echo $edit_category['sort_order'] ?? '0'; ?>">
                            <small class="text-muted">Lower numbers appear first in dropdowns</small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                            </button>
                            <?php if($edit_category): ?>
                            <a href="purpose_categories.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list"></i> Categories Directory</span>
                    <span class="badge bg-secondary">
                        <?php echo $total_active; ?> active category(s)
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Sort Order</th>
                                    <th>Category</th>
                                    <th>Purposes</th>
                                    <th class="sis-u-e3d221c5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($active_categories) > 0): ?>
                                    <?php foreach ($active_categories as $category): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $category['sort_order']; ?></span>
                                         </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="category-chip sis-dynamic-color" data-color="<?php echo e($category['color']); ?>">
                                                    <i class="bi <?php echo $category['icon'] ?? 'bi-folder'; ?>"></i>
                                                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                                                </span>
                                                <?php if($category['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($category['description'], 0, 40)); ?></small>
                                                <?php endif; ?>
                                            </div>
                                         </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $category['purpose_count']; ?> purposes</span>
                                         </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="?edit=<?php echo $category['id']; ?>" class="action-btn" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if($category['purpose_count'] == 0): ?>
                                                <a href="?delete=<?php echo $category['id']; ?>" class="action-btn text-warning" 
                                                   onclick="return confirm('Archive this category?')" title="Archive">
                                                    <i class="bi bi-archive"></i>
                                                </a>
                                                <?php else: ?>
                                                <span class="action-btn disabled text-muted" title="Cannot archive - has <?php echo $category['purpose_count']; ?> purposes">
                                                    <i class="bi bi-archive"></i>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="bi bi-folder sis-u-e2718763"></i>
                                            <p class="text-muted mt-2 mb-0">No categories found</p>
                                            <small class="text-muted">Add a new category to get started</small>
                                         </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    
                    <?php if ($show_inactive && count($inactive_categories) > 0): ?>
                    <div class="inactive-section mt-3">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Sort Order</th>
                                        <th>Category</th>
                                        <th>Purposes</th>
                                        <th class="sis-u-e3d221c5">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inactive_categories as $category): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $category['sort_order']; ?></span>
                                         </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="category-chip sis-dynamic-color" data-color="<?php echo e($category['color']); ?>">
                                                    <i class="bi <?php echo $category['icon'] ?? 'bi-folder'; ?>"></i>
                                                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                                                </span>
                                                <span class="badge badge-inactive ms-2">Archived</span>
                                                <?php if($category['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($category['description'], 0, 40)); ?></small>
                                                <?php endif; ?>
                                            </div>
                                         </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $category['purpose_count']; ?> purposes</span>
                                         </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="?restore=<?php echo $category['id']; ?>" class="action-btn text-success" 
                                                   onclick="return confirm('Restore this category?')" title="Restore">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </a>
                                                <?php if($category['purpose_count'] == 0): ?>
                                                <a href="?hard_delete=<?php echo $category['id']; ?>" class="action-btn text-danger" 
                                                   onclick="return confirm('⚠️ PERMANENTLY DELETE this category? This cannot be undone! ⚠️')" title="Permanently Delete">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                                <?php else: ?>
                                                <span class="action-btn disabled text-muted" title="Cannot delete - has purposes">
                                                    <i class="bi bi-trash"></i>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
        const colorInput = document.querySelector('input[name="color"]');
        if (colorInput) {
            colorInput.addEventListener('input', function() {
                const preview = document.querySelector('.color-preview');
                if (preview) {
                    preview.style.backgroundColor = this.value;
                }
            });
        }
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>