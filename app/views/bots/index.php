<?php // FILE: /app/views/bots/index.php ?>
<div class="page-header">
    <h1>Bots</h1>
    <div class="actions"><a href="/bots/create" class="btn btn-primary">Create bot</a></div>
</div>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Name or description">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['active', 'paused', 'draft'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected($filters['status'], $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="channel_id">Channel</label>
        <select id="channel_id" name="channel_id">
            <option value="">All</option>
            <?php foreach ($channels as $ch): ?>
                <option value="<?php echo (int) $ch['id']; ?>"<?php echo selected($filters['channel_id'], $ch['id']); ?>><?php echo e($ch['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/bots" class="btn btn-secondary">Reset</a>
</form>

<?php if (empty($bots)): ?>
<div class="empty-state">
    <p>No bots found. Create your first bot to start answering WhatsApp messages.</p>
    <a href="/bots/create" class="btn btn-primary">Create bot</a>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="data-table">
    <thead>
        <tr><th>Name</th><th>Channel</th><th>Flows</th><th>AI fallback</th><th>Status</th><th>Created</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($bots as $bot): ?>
        <tr>
            <td>
                <a href="/bots/<?php echo (int) $bot['id']; ?>"><strong><?php echo e($bot['name']); ?></strong></a>
                <?php if ($bot['description']): ?><div class="muted small"><?php echo e($bot['description']); ?></div><?php endif; ?>
            </td>
            <td><?php echo e($bot['channel_name']); ?><div class="muted small"><?php echo e($bot['phone_number']); ?></div></td>
            <td><?php echo (int) $bot['flow_count']; ?></td>
            <td><?php echo $bot['ai_enabled'] ? 'Yes (' . e($bot['ai_tone']) . ')' : 'No'; ?></td>
            <td><?php echo badge($bot['status']); ?></td>
            <td class="nowrap"><?php echo e(format_date($bot['created_at'], 'M d, Y')); ?></td>
            <td class="actions">
                <a href="/bots/<?php echo (int) $bot['id']; ?>" class="btn btn-small">Open</a>
                <a href="/bots/<?php echo (int) $bot['id']; ?>/flows" class="btn btn-small btn-secondary">Flows</a>
                <a href="/bots/<?php echo (int) $bot['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php partial('pagination', ['pagination' => $pagination]); ?>
