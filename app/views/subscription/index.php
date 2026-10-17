<?php // FILE: /app/views/subscription/index.php ?>
<div class="page-header">
    <h1>Subscription</h1>
    <div class="actions">
        <a href="/subscription/plans" class="btn">Change plan</a>
        <a href="/billing" class="btn btn-secondary">Billing</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Current plan</h2>
        <?php if ($subscription): ?>
            <p class="plan-price"><?php echo e($subscription['plan_name']); ?>
                <span class="muted small">$<?php echo e($subscription['plan_price']); ?> / <?php echo e($subscription['billing_period']); ?></span></p>
            <dl class="detail-list">
                <dt>Status</dt><dd><?php echo badge($subscription['status']); ?></dd>
                <dt>Started</dt><dd><?php echo e(format_date($subscription['started_at'])); ?></dd>
                <dt>Renews / expires</dt><dd><?php echo e(format_date($subscription['expires_at'])); ?>
                    <?php if ($subscription['expires_at'] && strtotime($subscription['expires_at']) < time()): ?> <?php echo badge('expired'); ?><?php endif; ?></dd>
            </dl>
        <?php else: ?>
            <div class="alert alert-warning">You have no active subscription. Creating bots, contacts and sending messages is blocked until you <a href="/subscription/plans">choose a plan</a>.</div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Usage vs limits</h2>
        <?php partial('usage_bars', ['quota' => $quota]); ?>
        <p class="help">At 80% tenant admins receive a warning email; at 100% new items are blocked in the UI and the API returns HTTP 429.</p>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Monthly usage history</h2>
        <?php if (empty($usageHistory)): ?>
            <p class="muted">No history yet.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Month</th><th>Channels</th><th>Bots</th><th>Contacts</th><th>Messages</th><th>Storage (MB)</th></tr></thead>
            <tbody>
            <?php foreach ($usageHistory as $h): ?>
                <tr>
                    <td><?php echo e(format_date($h['period_start'], 'M Y')); ?></td>
                    <td><?php echo (int) $h['total_channels']; ?></td>
                    <td><?php echo (int) $h['total_bots']; ?></td>
                    <td><?php echo (int) $h['total_contacts']; ?></td>
                    <td><?php echo (int) $h['total_messages']; ?></td>
                    <td><?php echo e($h['total_storage_mb']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Plan history</h2>
        <?php if (empty($history)): ?>
            <p class="muted">No subscriptions yet.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Plan</th><th>Price</th><th>Status</th><th>Started</th><th>Expires</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?php echo e($h['plan_name']); ?></td>
                    <td>$<?php echo e($h['plan_price']); ?></td>
                    <td><?php echo badge($h['status']); ?></td>
                    <td class="nowrap"><?php echo e(format_date($h['started_at'], 'M d, Y')); ?></td>
                    <td class="nowrap"><?php echo e(format_date($h['expires_at'], 'M d, Y')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
