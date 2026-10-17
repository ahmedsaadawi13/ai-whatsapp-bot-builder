<?php // FILE: /app/views/dashboard/platform.php ?>
<div class="page-header">
    <h1>Platform dashboard</h1>
    <div class="actions"><a href="/tenants/create" class="btn">New tenant</a></div>
</div>

<div class="stats-grid">
    <div class="stat-card"><h3>Tenants</h3><div class="stat-number"><?php echo (int) $totals['total_tenants']; ?></div><div class="muted small"><?php echo (int) $totals['active_tenants']; ?> active</div></div>
    <div class="stat-card"><h3>Tenant users</h3><div class="stat-number"><?php echo (int) $totals['total_users']; ?></div></div>
    <div class="stat-card"><h3>Bots</h3><div class="stat-number"><?php echo (int) $totals['total_bots']; ?></div></div>
    <div class="stat-card"><h3>Messages this month</h3><div class="stat-number"><?php echo (int) $totals['messages_this_month']; ?></div></div>
    <div class="stat-card"><h3>Revenue (paid)</h3><div class="stat-number">$<?php echo e(number_format((float) $totals['revenue'], 2)); ?></div><div class="muted small">$<?php echo e(number_format((float) $totals['outstanding'], 2)); ?> outstanding</div></div>
</div>

<div class="card">
    <div class="card-header"><h2>Recent tenants</h2><a href="/tenants" class="btn btn-small btn-secondary">All tenants</a></div>
    <?php if (empty($recentTenants)): ?>
        <p class="muted">No tenants yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Plan</th><th>Users</th><th>Bots</th><th>Contacts</th><th>Status</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recentTenants as $t): ?>
                <tr>
                    <td><strong><?php echo e($t['name']); ?></strong><div class="muted small"><?php echo e($t['email']); ?></div></td>
                    <td><?php echo e($t['plan_name'] ?: '-'); ?></td>
                    <td><?php echo (int) $t['user_count']; ?></td>
                    <td><?php echo (int) $t['bot_count']; ?></td>
                    <td><?php echo (int) $t['contact_count']; ?></td>
                    <td><?php echo badge($t['status']); ?></td>
                    <td class="nowrap"><?php echo e(format_date($t['created_at'], 'M d, Y')); ?></td>
                    <td><a href="/tenants/<?php echo (int) $t['id']; ?>" class="btn btn-small">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
