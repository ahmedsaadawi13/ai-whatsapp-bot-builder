<?php // FILE: /app/views/webhooks/index.php ?>
<div class="page-header">
    <h1>Webhooks</h1>
    <div class="actions"><a class="btn" href="/webhooks/create">Add webhook</a></div>
</div>
<p class="muted mb">Outgoing webhooks notify your systems (CRM, Slack, ...) about events: message.received, message.sent, conversation.opened, conversation.closed, contact.created.</p>

<form method="GET" action="/webhooks" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Name or URL">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <option value="active"<?php echo selected('active', $filters['status']); ?>>Active</option>
            <option value="inactive"<?php echo selected('inactive', $filters['status']); ?>>Inactive</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/webhooks" class="btn btn-secondary">Reset</a>
</form>

<?php if (!empty($webhooks)): ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>URL</th><th>Events</th><th>Bot</th><th>Deliveries</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($webhooks as $w): ?>
            <tr>
                <td><strong><?php echo e($w['name']); ?></strong></td>
                <td><code><?php echo e($w['url']); ?></code></td>
                <td><?php foreach (json_array($w['events']) as $ev): ?><span class="tag"><?php echo e($ev); ?></span><?php endforeach; ?></td>
                <td><?php echo e($w['bot_name'] ?: 'All bots'); ?></td>
                <td><?php echo (int) $w['delivery_count']; ?><?php if ((int) $w['failure_count'] > 0): ?> <span class="badge badge-failed"><?php echo (int) $w['failure_count']; ?> failed</span><?php endif; ?></td>
                <td><?php echo badge($w['status']); ?></td>
                <td class="actions">
                    <a class="btn btn-small btn-secondary" href="/webhooks/<?php echo (int) $w['id']; ?>/edit">Edit</a>
                    <a class="btn btn-small btn-secondary" href="/webhooks/<?php echo (int) $w['id']; ?>/logs">Logs</a>
                    <?php echo post_button('/webhooks/' . (int) $w['id'] . '/delete', 'Delete', 'btn btn-small btn-danger', 'Delete this webhook?'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php partial('pagination', ['pagination' => $pagination]); ?>
<?php else: ?>
<div class="empty-state">
    <p>No webhooks configured.</p>
    <a class="btn" href="/webhooks/create">Add webhook</a>
</div>
<?php endif; ?>
