<?php // FILE: /app/views/tenants/show.php ?>
<div class="page-header">
    <h1><?php echo e($tenant['name']); ?> <?php echo badge($tenant['status']); ?></h1>
    <div class="actions">
        <a href="/tenants/<?php echo (int) $tenant['id']; ?>/edit" class="btn btn-secondary">Edit</a>
        <?php echo post_button('/tenants/' . (int) $tenant['id'] . '/delete', 'Delete tenant', 'btn btn-danger',
            'Delete this tenant and ALL of its bots, contacts, conversations and invoices? This cannot be undone.'); ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Profile</h2>
        <dl class="detail-list">
            <dt>Slug</dt><dd><?php echo e($tenant['slug']); ?></dd>
            <dt>Email</dt><dd><?php echo e($tenant['email']); ?></dd>
            <dt>Phone</dt><dd><?php echo e($tenant['phone'] ?: '-'); ?></dd>
            <dt>Industry</dt><dd><?php echo e($tenant['industry'] ?: '-'); ?></dd>
            <dt>Timezone</dt><dd><?php echo e($tenant['timezone']); ?></dd>
            <dt>Created</dt><dd><?php echo e(format_date($tenant['created_at'])); ?></dd>
            <dt>Plan</dt><dd><?php echo $tenant['plan_name'] ? e($tenant['plan_name']) . ' ($' . e($tenant['plan_price']) . ')' : '<span class="muted">No active subscription</span>'; ?></dd>
            <dt>Renews / expires</dt><dd><?php echo e(format_date($tenant['expires_at'])); ?></dd>
        </dl>
    </div>
    <div class="card">
        <h2>Usage vs plan limits</h2>
        <?php partial('usage_bars', ['quota' => $quota]); ?>
    </div>
</div>

<div class="card">
    <h2>Users</h2>
    <?php if (empty($users)): ?>
        <p class="muted">No users.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo e($u['name']); ?></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td><?php echo e(str_replace('_', ' ', $u['role'])); ?></td>
                    <td><?php echo badge($u['status']); ?></td>
                    <td class="nowrap"><?php echo e(format_date($u['last_login_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Recent invoices</h2>
    <?php if (empty($invoices)): ?>
        <p class="muted">No invoices.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Number</th><th>Description</th><th>Total</th><th>Status</th><th>Due</th><th>Paid</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $i): ?>
                <tr>
                    <td><?php echo e($i['invoice_number']); ?></td>
                    <td><?php echo e($i['description'] ?: '-'); ?></td>
                    <td>$<?php echo e(number_format((float) $i['total'], 2)); ?></td>
                    <td><?php echo badge($i['status']); ?></td>
                    <td class="nowrap"><?php echo e(format_date($i['due_date'], 'M d, Y')); ?></td>
                    <td class="nowrap"><?php echo e(format_date($i['paid_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
