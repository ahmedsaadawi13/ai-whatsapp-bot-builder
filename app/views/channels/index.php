<?php // FILE: /app/views/channels/index.php ?>
<div class="page-header">
    <h1>WhatsApp Channels</h1>
    <div class="actions"><a href="/channels/create" class="btn btn-primary">Add channel</a></div>
</div>

<div class="card">
    <h3>Incoming webhook URL</h3>
    <p class="muted small">Configure your provider to POST incoming messages here, with the channel's verify token in the <code>X-Webhook-Token</code> header.</p>
    <code><?php echo e($webhookUrl); ?></code>
    <button type="button" class="btn btn-small btn-secondary" data-copy="<?php echo e($webhookUrl); ?>">Copy</button>
</div>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Name or number">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['active', 'inactive'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected($filters['status'], $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="provider_type">Provider</label>
        <select id="provider_type" name="provider_type">
            <option value="">All</option>
            <?php foreach ($providers as $key => $label): ?>
                <option value="<?php echo e($key); ?>"<?php echo selected($filters['provider_type'], $key); ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/channels" class="btn btn-secondary">Reset</a>
</form>

<?php if (empty($channels)): ?>
<div class="empty-state">
    <p>No channels found. Connect a WhatsApp number to start building bots.</p>
    <a href="/channels/create" class="btn btn-primary">Add channel</a>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="data-table">
    <thead>
        <tr><th>Name</th><th>Number</th><th>Provider</th><th>Verify token</th><th>Bots</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($channels as $channel): ?>
        <tr>
            <td><strong><?php echo e($channel['name']); ?></strong></td>
            <td class="nowrap"><?php echo e($channel['phone_number']); ?></td>
            <td><?php echo e(isset($providers[$channel['provider_type']]) ? $providers[$channel['provider_type']] : $channel['provider_type']); ?></td>
            <td class="nowrap">
                <code><?php echo e(substr($channel['webhook_verify_token'], 0, 8)); ?>&hellip;</code>
                <button type="button" class="btn btn-small btn-secondary" data-copy="<?php echo e($channel['webhook_verify_token']); ?>">Copy</button>
            </td>
            <td><?php echo (int) $channel['bot_count']; ?></td>
            <td><?php echo badge($channel['status']); ?></td>
            <td class="actions">
                <a href="/channels/<?php echo (int) $channel['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
                <a href="/bots?channel_id=<?php echo (int) $channel['id']; ?>" class="btn btn-small btn-secondary">Bots</a>
                <?php echo post_button('/channels/' . (int) $channel['id'] . '/delete', 'Delete', 'btn btn-small btn-danger', 'Delete this channel and all its bots and conversations?'); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php partial('pagination', ['pagination' => $pagination]); ?>
