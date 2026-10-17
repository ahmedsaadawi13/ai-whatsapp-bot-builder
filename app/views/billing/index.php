<?php // FILE: /app/views/billing/index.php ?>
<div class="page-header">
    <h1>Billing</h1>
    <div class="actions"><a href="/subscription" class="btn btn-secondary">Subscription</a></div>
</div>

<div class="stats-grid">
    <div class="stat-card"><h3>Total paid</h3><div class="stat-number">$<?php echo e(number_format((float) $totals['paid'], 2)); ?></div></div>
    <div class="stat-card"><h3>Outstanding</h3><div class="stat-number">$<?php echo e(number_format((float) $totals['outstanding'], 2)); ?></div></div>
</div>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Invoice number or description">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['pending', 'paid', 'cancelled'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected($filters['status'], $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/billing" class="btn btn-secondary">Reset</a>
</form>

<?php if (empty($invoices)): ?>
    <div class="empty-state"><p>No invoices found.</p></div>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Invoice</th><th>Description</th><th>Period</th><th>Total</th><th>Status</th><th>Due</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): ?>
            <tr>
                <td><strong><?php echo e($i['invoice_number']); ?></strong></td>
                <td><?php echo e($i['description'] ?: '-'); ?></td>
                <td class="nowrap"><?php echo e(format_date($i['period_start'], 'M d')); ?> - <?php echo e(format_date($i['period_end'], 'M d, Y')); ?></td>
                <td>$<?php echo e(number_format((float) $i['total'], 2)); ?></td>
                <td><?php echo badge($i['status']); ?></td>
                <td class="nowrap"><?php echo e(format_date($i['due_date'], 'M d, Y')); ?></td>
                <td><a href="/billing/invoices/<?php echo (int) $i['id']; ?>" class="btn btn-small<?php echo $i['status'] === 'pending' ? '' : ' btn-secondary'; ?>"><?php echo $i['status'] === 'pending' ? 'Pay' : 'View'; ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php partial('pagination', ['pagination' => $pagination]); ?>

<div class="card mt">
    <h2>Payment history</h2>
    <?php if (empty($payments)): ?>
        <p class="muted">No payments yet.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Date</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Transaction</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td class="nowrap"><?php echo e(format_date($p['created_at'])); ?></td>
                <td><?php echo e($p['invoice_number'] ?: '-'); ?></td>
                <td>$<?php echo e(number_format((float) $p['amount'], 2)); ?></td>
                <td><?php echo e(str_replace('_', ' ', $p['payment_method'])); ?></td>
                <td><code><?php echo e($p['transaction_id']); ?></code></td>
                <td><?php echo badge($p['status']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
