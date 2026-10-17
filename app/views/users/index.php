<?php // FILE: /app/views/users/index.php ?>
<?php $me = (new Auth())->id(); ?>
<div class="page-header">
    <h1>Team</h1>
    <div class="actions"><a href="/users/create" class="btn">Add team member</a></div>
</div>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Name or email">
    </div>
    <div class="form-group">
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="">All roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?php echo e($r); ?>"<?php echo selected($filters['role'], $r); ?>><?php echo e(ucfirst(str_replace('_', ' ', $r))); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <option value="active"<?php echo selected($filters['status'], 'active'); ?>>Active</option>
            <option value="inactive"<?php echo selected($filters['status'], 'inactive'); ?>>Inactive</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/users" class="btn btn-secondary">Reset</a>
</form>

<div class="alert alert-info">
    <strong>Roles:</strong> tenant admins manage everything; agents work the inbox and contacts;
    developers build bots and manage webhooks and API keys.
</div>

<?php if (empty($users)): ?>
    <div class="empty-state"><p>No team members match your filters.</p></div>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo e($u['name']); ?><?php if ((int) $u['id'] === $me): ?> <span class="muted small">(you)</span><?php endif; ?></td>
                <td><?php echo e($u['email']); ?></td>
                <td><?php echo e(ucfirst(str_replace('_', ' ', $u['role']))); ?></td>
                <td><?php echo badge($u['status']); ?></td>
                <td class="nowrap"><?php echo e(format_date($u['last_login_at'])); ?></td>
                <td class="actions">
                    <a href="/users/<?php echo (int) $u['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
                    <?php if ((int) $u['id'] !== $me): ?>
                        <?php echo post_button('/users/' . (int) $u['id'] . '/delete', 'Remove', 'btn btn-small btn-danger', 'Remove ' . $u['name'] . ' from the team?'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php partial('pagination', ['pagination' => $pagination]); ?>
