<?php // FILE: /app/views/tenants/index.php ?>
<div class="page-header">
    <h1>Tenants</h1>
    <div class="actions"><a href="/tenants/create" class="btn">New tenant</a></div>
</div>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Name, email or slug">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['active', 'suspended', 'cancelled'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected($filters['status'], $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/tenants" class="btn btn-secondary">Reset</a>
</form>

<?php if (empty($tenants)): ?>
    <div class="empty-state"><p>No tenants match your filters.</p><a href="/tenants/create" class="btn">Create a tenant</a></div>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Tenant</th><th>Industry</th><th>Plan</th><th>Users</th><th>Bots</th><th>Contacts</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tenants as $t): ?>
            <tr>
                <td><strong><?php echo e($t['name']); ?></strong><div class="muted small"><?php echo e($t['slug']); ?> &middot; <?php echo e($t['email']); ?></div></td>
                <td><?php echo e($t['industry'] ?: '-'); ?></td>
                <td><?php echo e($t['plan_name'] ?: '-'); ?></td>
                <td><?php echo (int) $t['user_count']; ?></td>
                <td><?php echo (int) $t['bot_count']; ?></td>
                <td><?php echo (int) $t['contact_count']; ?></td>
                <td><?php echo badge($t['status']); ?></td>
                <td class="nowrap"><?php echo e(format_date($t['created_at'], 'M d, Y')); ?></td>
                <td class="actions">
                    <a href="/tenants/<?php echo (int) $t['id']; ?>" class="btn btn-small">View</a>
                    <a href="/tenants/<?php echo (int) $t['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php partial('pagination', ['pagination' => $pagination]); ?>
