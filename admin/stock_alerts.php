<?php
require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

$message = '';
$message_type = '';

// Delete category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if category has purposes
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM purpose_of_visit WHERE category_id = $id");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['count'] > 0) {
        $message = 'Cannot delete - ' . $row['count'] . ' purposes in this category';
        $message_type = 'danger';
    } else {
        $query = "UPDATE purpose_categories SET is_active = 0 WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            logAction($conn, 'DELETE_PURPOSE_CATEGORY', $id);
            $message = 'Category deleted!';
            $message_type = 'success';
        }
    }
}

// Add/Edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, (string)($_POST['name'] ?? ''));
    $description = mysqli_real_escape_string($conn, (string)($_POST['description'] ?? ''));
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $query = "UPDATE purpose_categories SET 
                  name = '$name', 
                  description = '$description' 
                  WHERE id = $id";
        $action = 'UPDATE_PURPOSE_CATEGORY';
    } else {
        $query = "INSERT INTO purpose_categories (name, description) 
                  VALUES ('$name', '$description')";
        $action = 'ADD_PURPOSE_CATEGORY';
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

// Get all categories
$categories_query = "SELECT * FROM purpose_categories WHERE is_active = 1 ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Get category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM purpose_categories WHERE id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_category = mysqli_fetch_assoc($edit_result);
}

// Get stats
$total_categories = mysqli_num_rows($categories_result);
$total_purposes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM purpose_of_visit WHERE is_active = 1"))['count'] ?? 0;
$used_categories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT category_id) as count FROM purpose_of_visit WHERE category_id IS NOT NULL"))['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <span class="date-badge"><?php echo date('l, d M Y'); ?></span>
        </div>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Categories</h6>
                <h2><?php echo $total_categories; ?></h2>
                <small>Purpose groups</small>
            </div>
            <div class="stat-card">
                <h6>Total Purposes</h6>
                <h2><?php echo $total_purposes; ?></h2>
                <small>Visit reasons</small>
            </div>
            <div class="stat-card">
                <h6>Categories Used</h6>
                <h2><?php echo $used_categories; ?></h2>
                <small>With purposes</small>
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
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-save"></i> <?php echo $edit_category ? 'Update' : 'Save'; ?>
                            </button>
                            <?php if($edit_category): ?>
                            <a href="purpose_categories.php" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-list"></i> All Categories</span>
                    <span class="badge bg-secondary"><?php echo $total_categories; ?> items</span>
                </div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Purposes</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($categories_result) > 0): ?>
                                <?php while($category = mysqli_fetch_assoc($categories_result)): 
                                    $count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM purpose_of_visit WHERE category_id = {$category['id']} AND is_active = 1");
                                    $count = mysqli_fetch_assoc($count_query)['count'] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($category['description'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $count; ?> purposes</span>
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
                                        <span class="action-btn text-muted" title="Cannot delete - has purposes">
                                            <i class="bi bi-trash"></i>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
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
            <a href="purposes.php" class="btn btn-outline-primary">
                <i class="bi bi-tags"></i> Manage Purposes
            </a>
        </div>
    </div>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>