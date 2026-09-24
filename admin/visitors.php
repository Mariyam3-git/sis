<?php
// admin/visitors.php - FIXED VERSION with correct column names

require_once '../config.php';
requireRole('admin');

if (!hasRole('admin')) {
    redirectTo(appPath('dashboard.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrf()) {
    http_response_code(403);
    exit('Security check failed. Please return to the previous page and try again.');
}

// Handle DELETE visitor and all associated records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_visitor'])) {
    $visitor_id = (int)$_POST['visitor_id'];
    
    // Get visitor details for logging
    $visitor_query = mysqli_query($conn, "SELECT name, contact FROM visitors WHERE id = $visitor_id");
    $visitor = mysqli_fetch_assoc($visitor_query);
    
    if ($visitor) {
        // Get all sales associated with this visitor for logging
        $sales_query = mysqli_query($conn, "SELECT COUNT(*) as count, SUM(amount) as total FROM sales WHERE visitor_id = $visitor_id");
        $sales_data = mysqli_fetch_assoc($sales_query);
        
        // Delete sales records first (due to foreign key constraints)
        $delete_sales = mysqli_query($conn, "DELETE FROM sales WHERE visitor_id = $visitor_id");
        
        // Then delete the visitor
        $delete_visitor = mysqli_query($conn, "DELETE FROM visitors WHERE id = $visitor_id");
        
        if ($delete_visitor) {
            logAction($conn, 'DELETE_VISITOR', $visitor_id);
            $message = "✓ Visitor '{$visitor['name']}' and their {$sales_data['count']} transaction(s) deleted successfully!";
            $message_type = 'success';
        } else {
            $message = sisDbErrorMessage($conn);
            $message_type = 'danger';
        }
    } else {
        $message = "Visitor not found!";
        $message_type = 'danger';
    }
}

// Handle DELETE single sale from visitor page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sale'])) {
    $sale_id = (int)$_POST['sale_id'];
    $visitor_id = (int)$_POST['visitor_id'];
    
    $sale_query = mysqli_query($conn, "SELECT invoice_number FROM sales WHERE id = $sale_id");
    $sale = mysqli_fetch_assoc($sale_query);
    
    if ($sale) {
        $delete = mysqli_query($conn, "DELETE FROM sales WHERE id = $sale_id");
        if ($delete) {
            logAction($conn, 'DELETE_SALE', $sale_id);
            $message = "✓ Transaction {$sale['invoice_number']} deleted successfully!";
            $message_type = 'success';
            
            // Check if visitor has any remaining sales
            $remaining = mysqli_query($conn, "SELECT COUNT(*) as count FROM sales WHERE visitor_id = $visitor_id");
            $remaining_data = mysqli_fetch_assoc($remaining);
            
            if ($remaining_data['count'] == 0) {
                $message .= " Visitor now has no transactions.";
            }
        } else {
            $message = sisDbErrorMessage($conn);
            $message_type = 'danger';
        }
    } else {
        $message = "Sale not found!";
        $message_type = 'danger';
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, (string)($_GET['search'] ?? '')) : '';
$date_from = sisCleanDate($_GET['date_from'] ?? '', '');
$date_to = sisCleanDate($_GET['date_to'] ?? '', '');
$outlet_filter = isset($_GET['outlet_id']) ? (int)$_GET['outlet_id'] : '';

// Build WHERE clause - FIXED: removed p.name and s.name references
$where_conditions = [];
if ($search) {
    $where_conditions[] = "(v.name LIKE '%$search%' OR v.contact LIKE '%$search%')";
}
if ($date_from) {
    $where_conditions[] = "DATE(v.visit_time) >= '$date_from'";
}
if ($date_to) {
    $where_conditions[] = "DATE(v.visit_time) <= '$date_to'";
}
if ($outlet_filter) {
    $where_conditions[] = "v.outlet_id = $outlet_filter";
}
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

// Get total visitors - FIXED: removed joins that might cause issues
$count_query = "SELECT COUNT(DISTINCT v.id) as total FROM visitors v $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Get visitors with their transaction summary - FIXED: simplified joins
$visitors_query = "SELECT v.*, 
                          o.name as outlet_name,
                          COUNT(sl.id) as transaction_count,
                          COALESCE(SUM(CASE WHEN sl.payment_status = 'Paid' AND sl.amount > 0 THEN sl.amount ELSE 0 END), 0) as total_paid,
                          COALESCE(SUM(CASE WHEN sl.payment_status = 'Paid' AND sl.amount < 0 THEN ABS(sl.amount) ELSE 0 END), 0) as total_withdrawn,
                          COALESCE(SUM(sl.amount), 0) as total_amount
                   FROM visitors v
                   LEFT JOIN outlets o ON v.outlet_id = o.id
                   LEFT JOIN sales sl ON v.id = sl.visitor_id
                   $where_clause
                   GROUP BY v.id
                   ORDER BY v.visit_time DESC
                   LIMIT $offset, $limit";
$visitors_result = mysqli_query($conn, $visitors_query);

// Get outlets for filter
$outlets_query = "SELECT id, name FROM outlets WHERE is_active = 1 ORDER BY name";
$outlets_result = mysqli_query($conn, $outlets_query);

// Get statistics - FIXED: simplified
$stats_query = "SELECT 
                    COUNT(DISTINCT v.id) as total_visitors,
                    COUNT(sl.id) as total_transactions,
                    COALESCE(SUM(CASE WHEN sl.payment_status = 'Paid' AND sl.amount > 0 THEN sl.amount ELSE 0 END), 0) as total_collected,
                    COALESCE(SUM(CASE WHEN sl.payment_status = 'Paid' AND sl.amount < 0 THEN ABS(sl.amount) ELSE 0 END), 0) as total_withdrawn
                FROM visitors v
                LEFT JOIN sales sl ON v.id = sl.visitor_id
                $where_clause";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Management - Comium GM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-page-components.css'); ?>">
<link rel="stylesheet" href="<?php echo appAsset('assets/css/sis-theme.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo appAsset('assets/images/favicon.svg'); ?>">
</head>
<body class="sis-app-body">
<?php require_once dirname(__DIR__) . '/include/side_header.php'; sisRenderLayoutStart('admin'); ?>


    
    <div class="main-content">
        <div class="page-title">
            <h3><i class="bi bi-person-lines-fill"></i> Visitor Management</h3>
            <span class="text-muted"><i class="bi bi-calendar"></i> <?php echo date('l, d M Y'); ?></span>
        </div>

        <?php if(isset($message) && $message): ?>
        <div class="alert alert-<?php echo e($message_type); ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        
        <div class="stats-grid">
            <div class="stat-card">
                <h6><i class="bi bi-people"></i> Total Visitors</h6>
                <h3><?php echo number_format($stats['total_visitors'] ?? 0); ?></h3>
            </div>
            <div class="stat-card">
                <h6><i class="bi bi-receipt"></i> Transactions</h6>
                <h3><?php echo number_format($stats['total_transactions'] ?? 0); ?></h3>
            </div>
            <div class="stat-card">
                <h6><i class="bi bi-cash-stack text-success"></i> Collected</h6>
                <h3>D <?php echo number_format($stats['total_collected'] ?? 0, 2); ?></h3>
            </div>
            <div class="stat-card">
                <h6><i class="bi bi-cash text-danger"></i> Withdrawn</h6>
                <h3>D <?php echo number_format($stats['total_withdrawn'] ?? 0, 2); ?></h3>
            </div>
        </div>

        
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group sis-u-36ae11e9">
                        <label><i class="bi bi-search"></i> Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by name or GSM number..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="bi bi-calendar"></i> From Date</label>
                        <input type="date" name="date_from" class="form-control sis-w-150" value="<?php echo e($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="bi bi-calendar"></i> To Date</label>
                        <input type="date" name="date_to" class="form-control sis-w-150" value="<?php echo e($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="bi bi-building"></i> Outlet</label>
                        <select name="outlet_id" class="form-select">
                            <option value="">All Outlets</option>
                            <?php 
                            mysqli_data_seek($outlets_result, 0);
                            while($outlet = mysqli_fetch_assoc($outlets_result)): 
                            ?>
                            <option value="<?php echo e($outlet['id']); ?>" <?php echo $outlet_filter == $outlet['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($outlet['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group sis-u-9102618d">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100 sis-u-7746d3b9">
                            <i class="bi bi-search"></i> Apply
                        </button>
                    </div>
                    <div class="filter-group sis-u-9102618d">
                        <label>&nbsp;</label>
                        <a href="visitors.php" class="btn btn-outline-secondary w-100 d-block text-center">
                            <i class="bi bi-arrow-repeat"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-list"></i> Visitor Records</span>
                <span class="badge bg-secondary"><?php echo number_format($total_records); ?> visitors</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Visit Date</th>
                                <th>Customer Info</th>
                                <th>Purpose ID</th>
                                <th>Service ID</th>
                                <th>Outlet</th>
                                <th>Transactions</th>
                                <th>Total Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($visitors_result) > 0): ?>
                                <?php while($visitor = mysqli_fetch_assoc($visitors_result)): ?>
                                <tr>
                                    <td class="sis-u-0ca50382">
                                        <?php echo date('d/m/Y H:i', strtotime($visitor['visit_time'])); ?>
                                        <small class="d-block text-muted"><?php echo date('l', strtotime($visitor['visit_time'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($visitor['name'] ?? 'Customer'); ?></strong>
                                        <?php if($visitor['contact']): ?>
                                        <div class="contact-badge mt-1">
                                            <i class="bi bi-phone"></i> <?php echo htmlspecialchars($visitor['contact']); ?>
                                        </div>
                                        <?php endif; ?>
                                     </td>
                                    <td><?php echo $visitor['purpose_id'] ?: '—'; ?></td>
                                    <td><?php echo $visitor['service_id'] ?: '—'; ?></td>
                                    <td><?php echo htmlspecialchars($visitor['outlet_name'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge-transactions">
                                            <i class="bi bi-receipt"></i> <?php echo e($visitor['transaction_count']); ?> transactions
                                        </span>
                                     </td>
                                    <td>
                                        <?php if($visitor['total_paid'] > 0): ?>
                                        <div class="text-success">+ D <?php echo number_format($visitor['total_paid'], 2); ?></div>
                                        <?php endif; ?>
                                        <?php if($visitor['total_withdrawn'] > 0): ?>
                                        <div class="text-danger">- D <?php echo number_format($visitor['total_withdrawn'], 2); ?></div>
                                        <?php endif; ?>
                                        <?php if($visitor['total_paid'] == 0 && $visitor['total_withdrawn'] == 0): ?>
                                        <span class="text-muted">D 0.00</span>
                                        <?php endif; ?>
                                     </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-info btn-sm" onclick="viewVisitorDetails(<?php echo e($visitor['id']); ?>)" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <form class="sis-u-3df0a395" method="POST" onsubmit="return confirm('⚠️ WARNING: This will delete the visitor and ALL their transactions! This action cannot be undone. Are you absolutely sure?')">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="visitor_id" value="<?php echo e($visitor['id']); ?>">
                                                <button type="submit" name="delete_visitor" class="btn btn-danger btn-sm" title="Delete Visitor & All Records">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                     </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="bi bi-person-x sis-u-e2718763"></i>
                                        <p class="text-muted mt-2">No visitors found</p>
                                        <small class="text-muted">Try adjusting your search filters</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                
                <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4 mb-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo e($date_from); ?>&date_to=<?php echo e($date_to); ?>&outlet_id=<?php echo $outlet_filter; ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        if($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&date_from=<?php echo e($date_from); ?>&date_to=<?php echo e($date_to); ?>&outlet_id=<?php echo $outlet_filter; ?>">1</a>
                        </li>
                        <?php if($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; endif; ?>
                        
                        <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo e($date_from); ?>&date_to=<?php echo e($date_to); ?>&outlet_id=<?php echo $outlet_filter; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if($end_page < $total_pages): ?>
                        <?php if($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo e($date_from); ?>&date_to=<?php echo e($date_to); ?>&outlet_id=<?php echo $outlet_filter; ?>"><?php echo $total_pages; ?></a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo e($date_from); ?>&date_to=<?php echo e($date_to); ?>&outlet_id=<?php echo $outlet_filter; ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                
                <div class="p-3 bg-light text-center small text-muted border-top">
                    <i class="bi bi-info-circle"></i> Showing <?php echo min($offset + 1, $total_records); ?> - <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> visitors
                </div>
            </div>
        </div>
    </div>

    
    <div class="modal fade" id="visitorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header sis-u-d9a36ba2">
                    <h5 class="modal-title"><i class="bi bi-person-badge"></i> Visitor Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="visitorModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-danger" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading visitor details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        
        function viewVisitorDetails(visitorId) {
            const modal = new bootstrap.Modal(document.getElementById('visitorModal'));
            const modalBody = document.getElementById('visitorModalBody');
            
            modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-danger" role="status"></div><p class="mt-2">Loading visitor details...</p></div>';
            modal.show();
            
            fetch(`get_visitor_details.php?id=${visitorId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let transactionsHtml = '';
                        if (data.transactions.length > 0) {
                            transactionsHtml = '<h6 class="mt-3"><i class="bi bi-receipt"></i> Transaction History</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Invoice</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
                            data.transactions.forEach(t => {
                                const amountClass = t.amount < 0 ? 'text-danger' : 'text-success';
                                transactionsHtml += `<tr>
                                    <td>${new Date(t.payment_date).toLocaleString()}</td>
                                    <td><code>${t.invoice_number || '—'}</code></td>
                                    <td class="${amountClass}">${t.amount < 0 ? '-' : '+'} D ${Math.abs(t.amount).toFixed(2)}</td>
                                    <td><span class="badge bg-${t.payment_status === 'Paid' ? 'success' : (t.payment_status === 'Pending' ? 'warning' : 'danger')}">${t.payment_status}</span></td>
                                </tr>`;
                            });
                            transactionsHtml += '</tbody></table></div>';
                        } else {
                            transactionsHtml = '<p class="text-muted mt-3">No transactions found for this visitor.</p>';
                        }
                        
                        modalBody.innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="text-muted small">Customer Name</label>
                                        <p class="fw-bold">${escapeHtml(data.visitor.name || 'Customer')}</p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted small">GSM Number</label>
                                        <p>${escapeHtml(data.visitor.contact || '—')}</p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted small">Purpose ID</label>
                                        <p>${data.visitor.purpose_id || '—'}</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="text-muted small">Service ID</label>
                                        <p>${data.visitor.service_id || '—'}</p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted small">Outlet</label>
                                        <p>${escapeHtml(data.visitor.outlet_name || '—')}</p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted small">Visit Time</label>
                                        <p>${new Date(data.visitor.visit_time).toLocaleString()}</p>
                                    </div>
                                </div>
                            </div>
                            ${transactionsHtml}
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle"></i> 
                                <strong>Warning:</strong> Deleting this visitor will also delete all associated transactions. This action cannot be undone.
                            </div>
                        `;
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading visitor details.</div>';
                });
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    </script>
    <script src="<?php echo appAsset('assets/js/sis-theme.js'); ?>"></script>
<?php sisRenderLayoutEnd(); ?>
</body>
</html>
