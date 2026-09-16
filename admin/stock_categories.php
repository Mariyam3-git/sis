<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$message = '';
$message_type = '';

// Check which columns exist
$columns = [];
$col_query = mysqli_query($conn, "SHOW COLUMNS FROM stock_categories");
while($col = mysqli_fetch_assoc($col_query)) {
    $columns[] = $col['Field'];
}

$has_sort_order = in_array('sort_order', $columns);
$has_icon = in_array('icon', $columns);
$has_color = in_array('color', $columns);

// Delete category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if category has products
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_products WHERE category_id = $id");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['count'] > 0) {
        $message = 'Cannot delete - ' . $row['count'] . ' products in this category';
        $message_type = 'danger';
    } else {
        $query = "UPDATE stock_categories SET is_active = 0 WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            logAction($conn, 'DELETE_STOCK_CATEGORY', $id);
            $message = 'Category deleted!';
            $message_type = 'success';
        }
    }
}

// Add/Edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, (string)($_POST['name'] ?? ''));
    $description = mysqli_real_escape_string($conn, (string)($_POST['description'] ?? ''));
    $icon = $has_icon ? mysqli_real_escape_string($conn, (string)($_POST['icon'] ?? '')) : 'bi-folder';
    $color = $has_color ? mysqli_real_escape_string($conn, (string)($_POST['color'] ?? '')) : '#6c757d';
    $sort_order = $has_sort_order ? (int)$_POST['sort_order'] : 0;
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        // Build dynamic query based on existing columns
        $fields = ["name = '$name'", "description = '$description'"];
        if ($has_icon) $fields[] = "icon = '$icon'";
        if ($has_color) $fields[] = "color = '$color'";
        if ($has_sort_order) $fields[] = "sort_order = $sort_order";
        
        $query = "UPDATE stock_categories SET " . implode(',', $fields) . " WHERE id = $id";
        $action = 'UPDATE_STOCK_CATEGORY';
    } else {
        // Build dynamic insert based on existing columns
        $fields = ['name', 'description'];
        $values = ["'$name'", "'$description'"];
        
        if ($has_icon) {
            $fields[] = 'icon';
            $values[] = "'$icon'";
        }
        if ($has_color) {
            $fields[] = 'color';
            $values[] = "'$color'";
        }
        if ($has_sort_order) {
            $fields[] = 'sort_order';
            $values[] = $sort_order;
        }
        
        $query = "INSERT INTO stock_categories (" . implode(',', $fields) . ") 
                  VALUES (" . implode(',', $values) . ")";
        $action = 'ADD_STOCK_CATEGORY';
    }
    
    if (mysqli_query($conn, $query)) {
        $record_id = isset($id) ? $id : mysqli_insert_id($conn);
        logAction($conn, $action, $record_id);
        $message = 'Category saved!';
        $message_type = 'success';
    } else {
        $message = 'Error: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Build order by clause based on existing columns
$order_by = $has_sort_order ? "ORDER BY sort_order, name" : "ORDER BY name";

// Get all categories
$categories_query = "SELECT * FROM stock_categories WHERE is_active = 1 $order_by";
$categories_result = mysqli_query($conn, $categories_query);

// Get category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM stock_categories WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_category = mysqli_fetch_assoc($edit_result);
}

// Common icons
$icons = ['bi-sim', 'bi-credit-card', 'bi-headphones', 'bi-wifi', 'bi-phone', 'bi-box', 'bi-hdd-stack', 'bi-cpu', 'bi-tools', 'bi-gear'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Categories - Comium GM</title>
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
            <h3><i class="bi bi-tags sis-u-20e33279"></i>Stock Categories</h3>
            <span class="date-badge"><?php echo date('l, d M Y'); ?></span>
        </div>

        
        <?php
        $total_categories = mysqli_num_rows($categories_result);
        $total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_products WHERE is_active = 1"))['count'] ?? 0;
        $used_categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT category_id) as count FROM stock_products WHERE category_id IS NOT NULL"))['count'] ?? 0;
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Categories</h6>
                <h2><?php echo $total_categories; ?></h2>
                <small>Active categories</small>
            </div>
            <div class="stat-card">
                <h6>Total Products</h6>
                <h2><?php echo $total_products; ?></h2>
                <small>In inventory</small>
            </div>
            <div class="stat-card">
                <h6>Categories Used</h6>
                <h2><?php echo $used_categories; ?></h2>
                <small>With products</small>
            </div>
        </div>

        <?php if($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        
        <div class="two-col">
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-<?php echo $edit_category ? 'pencil' : 'plus-circle'; ?>"></i>
                    <?php echo $edit_category ? 'Edit Category' : 'New Category'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php if($edit_category): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_category['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Category Name</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo $edit_category['name'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"><?php echo $edit_category['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <?php if($has_icon): ?>
                        <div class="mb-3">
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
                        <?php endif; ?>
                        
                        <?php if($has_color): ?>
                        <div class="mb-3">
                            <label class="form-label">Color</label>
                            <input type="color" class="form-control" name="color" 
                                   value="<?php echo $edit_category['color'] ?? '#6c757d'; ?>" 
                                  >
                        </div>
                        <?php endif; ?>
                        
                        <?php if($has_sort_order): ?>
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" 
                                   value="<?php echo $edit_category['sort_order'] ?? '0'; ?>">
                            <small class="text-muted">Lower numbers appear first</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_category ? 'Update' : 'Save'; ?>
                            </button>
                            <?php if($edit_category): ?>
                            <a href="stock_categories.php" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list"></i> All Categories</span>
                    <span class="badge bg-secondary"><?php echo $total_categories; ?> total</span>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <?php if($has_sort_order): ?><th>Order</th><?php endif; ?>
                                <th>Category</th>
                                <th>Products</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($categories_result) > 0): ?>
                                <?php while($category = mysqli_fetch_assoc($categories_result)): 
                                    $count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_products WHERE category_id = {$category['id']} AND is_active = 1");
                                    $count = mysqli_fetch_assoc($count_query)['count'] ?? 0;
                                ?>
                                <tr>
                                    <?php if($has_sort_order): ?>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $category['sort_order'] ?? 0; ?></span>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <div class="sis-u-b512c985">
                                            <span class="category-chip sis-dynamic-color" data-color="<?php echo e($category['color'] ?? '#6c757d'); ?>">
                                                <i class="bi <?php echo $category['icon'] ?? 'bi-folder'; ?>"></i>
                                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                                            </span>
                                        </div>
                                        <?php if(!empty($category['description'])): ?>
                                        <small class="sis-u-a74a8858"><?php echo $category['description']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $count; ?> products</span>
                                    </td>
                                    <td>
                                        <a href="?edit=<?php echo $category['id']; ?>" class="action-btn" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if($count == 0): ?>
                                        <a href="?delete=<?php echo $category['id']; ?>" class="action-btn text-danger" 
                                           onclick="return confirm('Delete this category?')" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="action-btn text-muted" title="Cannot delete - has products">
                                            <i class="bi bi-trash"></i>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $has_sort_order ? 4 : 3; ?>" class="text-center py-4 text-muted">
                                        <i class="bi bi-folder sis-u-1e52ae17"></i>
                                        <p class="mt-2">No categories found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div class="d-flex gap-2 mt-3">
            <a href="stock_products.php" class="btn btn-outline-primary">
                <i class="bi bi-box"></i> Manage Products
            </a>
            <a href="stock_receive.php" class="btn btn-outline-success">
                <i class="bi bi-box-arrow-in-down"></i> Receive Stock
            </a>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>