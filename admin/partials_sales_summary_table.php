<?php
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === basename(__FILE__)) {
    require_once '../config.php';
    requireRole('admin');
    redirectTo(appPath('admin/sales.php'));
}
// Reusable summary table for admin/sales.php. Expects $r and $cols.
?>
<div class="table-responsive sis-table-shell sis-report-table-shell">
<table class="table table-striped table-hover align-middle sis-searchable-table sis-report-data-table" data-sis-searchable-table>
<caption class="visually-hidden">Sales report summary table</caption>
<thead><tr><?php foreach($cols as $c): ?><th><?php echo e($c); ?></th><?php endforeach; ?></tr></thead>
<tbody>
<?php if($r && mysqli_num_rows($r) > 0): while($row = mysqli_fetch_assoc($r)): ?>
<tr>
<?php if(count($cols) === 7 && in_array('Region', $cols, true)): ?>
    <td><strong><?php echo e($row['label']); ?></strong></td><td><?php echo e($row['region'] ?? '-'); ?></td><td><?php echo number_format((int)$row['transactions']); ?></td><td class="text-success fw-semibold"><?php echo salesMoney($row['collected']); ?></td><td class="text-danger fw-semibold"><?php echo salesMoney($row['withdrawals']); ?></td><td class="text-warning fw-semibold"><?php echo salesMoney($row['pending']); ?></td><td><?php echo salesMoney($row['unpaid']); ?></td>
<?php elseif(in_array('Average Handle', $cols, true)): ?>
    <td><strong><?php echo e($row['label']); ?></strong></td><td><?php echo number_format((int)$row['transactions']); ?></td><td class="text-success fw-semibold"><?php echo salesMoney($row['collected']); ?></td><td class="text-danger fw-semibold"><?php echo salesMoney($row['withdrawals']); ?></td><td class="text-warning fw-semibold"><?php echo salesMoney($row['pending']); ?></td><td><?php echo salesMoney($row['unpaid']); ?></td><td><?php echo renderHandleTimeCell($row['avg_handle'] ?? 0); ?></td>
<?php else: ?>
    <td><?php echo salesShiftBadge($row['label']); ?></td><td><?php echo number_format((int)$row['transactions']); ?></td><td class="text-success fw-semibold"><?php echo salesMoney($row['collected']); ?></td><td class="text-danger fw-semibold"><?php echo salesMoney($row['withdrawals']); ?></td><td class="text-warning fw-semibold"><?php echo salesMoney($row['pending']); ?></td><td><?php echo salesMoney($row['unpaid']); ?></td>
<?php endif; ?>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="<?php echo count($cols); ?>" class="text-center text-muted py-4">No summary data found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
