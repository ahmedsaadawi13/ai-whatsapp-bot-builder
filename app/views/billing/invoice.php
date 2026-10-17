<?php // FILE: /app/views/billing/invoice.php ?>
<div class="page-header">
    <h1>Invoice <?php echo e($invoice['invoice_number']); ?> <?php echo badge($invoice['status']); ?></h1>
    <div class="actions">
        <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
        <a href="/billing" class="btn btn-secondary">Back to billing</a>
    </div>
</div>

<div class="card">
    <div class="grid grid-2">
        <div>
            <h3>From</h3>
            <p><strong>AI WhatsApp Bot Builder</strong><br><span class="muted">Subscription services</span></p>
        </div>
        <div>
            <h3>Bill to</h3>
            <p><strong><?php echo e($tenant['name']); ?></strong><br>
                <?php echo e($tenant['email']); ?><br>
                <?php if ($tenant['address']): ?><?php echo e($tenant['address']); ?><br><?php endif; ?>
                <?php if ($tenant['phone']): ?><?php echo e($tenant['phone']); ?><?php endif; ?></p>
        </div>
    </div>
    <dl class="detail-list mt">
        <dt>Invoice date</dt><dd><?php echo e(format_date($invoice['created_at'], 'M d, Y')); ?></dd>
        <dt>Due date</dt><dd><?php echo e(format_date($invoice['due_date'], 'M d, Y')); ?></dd>
        <dt>Service period</dt><dd><?php echo e(format_date($invoice['period_start'], 'M d, Y')); ?> - <?php echo e(format_date($invoice['period_end'], 'M d, Y')); ?></dd>
        <?php if ($invoice['paid_at']): ?><dt>Paid on</dt><dd><?php echo e(format_date($invoice['paid_at'])); ?></dd><?php endif; ?>
    </dl>
    <table class="data-table mt">
        <thead><tr><th>Description</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
            <tr><td><?php echo e($invoice['description'] ?: 'Subscription'); ?></td><td class="text-right">$<?php echo e(number_format((float) $invoice['amount'], 2)); ?></td></tr>
            <tr><td class="text-right muted">Tax</td><td class="text-right">$<?php echo e(number_format((float) $invoice['tax'], 2)); ?></td></tr>
            <tr><td class="text-right"><strong>Total</strong></td><td class="text-right"><strong>$<?php echo e(number_format((float) $invoice['total'], 2)); ?></strong></td></tr>
        </tbody>
    </table>
</div>

<?php if ($invoice['status'] === 'pending'): ?>
<form method="POST" action="/billing/pay-invoice" class="card">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="invoice_id" value="<?php echo (int) $invoice['id']; ?>">
    <h2>Pay this invoice</h2>
    <div class="alert alert-info">This is a dummy payment gateway. Test card <code>4242424242424242</code> succeeds; any card ending in <code>0002</code> is declined.</div>
    <div class="form-group">
        <span class="label">Payment method</span>
        <?php $method = old('payment_method', 'credit_card'); foreach ($methods as $key => $label): ?>
            <label class="checkbox"><input type="radio" name="payment_method" value="<?php echo e($key); ?>"<?php echo checked($method === $key); ?>> <?php echo e($label); ?></label>
        <?php endforeach; ?>
    </div>
    <div class="form-group" data-show-when="payment_method=credit_card">
        <label for="card_number">Card number</label>
        <input type="text" id="card_number" name="card_number" inputmode="numeric" autocomplete="off" value="4242424242424242" maxlength="23">
        <?php echo field_error('card_number'); ?>
    </div>
    <div class="form-actions"><button type="submit" class="btn">Pay $<?php echo e(number_format((float) $invoice['total'], 2)); ?></button></div>
</form>
<?php endif; ?>

<div class="card">
    <h2>Payment attempts</h2>
    <?php if (empty($payments)): ?>
        <p class="muted">No payments recorded for this invoice.</p>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Transaction</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td class="nowrap"><?php echo e(format_date($p['created_at'])); ?></td>
                <td>$<?php echo e(number_format((float) $p['amount'], 2)); ?></td>
                <td><?php echo e(str_replace('_', ' ', $p['payment_method'])); ?></td>
                <td><code><?php echo e($p['transaction_id']); ?></code></td>
                <td><?php echo badge($p['status']); ?></td>
                <td class="muted small"><?php echo e($p['notes']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
