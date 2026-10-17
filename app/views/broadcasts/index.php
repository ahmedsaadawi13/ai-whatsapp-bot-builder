<?php // FILE: /app/views/broadcasts/index.php ?>
<div class="page-header">
    <h1>Broadcasts</h1>
    <div class="actions">
        <a class="btn btn-secondary" href="/analytics/broadcasts">Performance report</a>
        <a class="btn" href="/broadcasts/create">New broadcast</a>
    </div>
</div>

<form method="GET" action="/broadcasts" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Campaign name">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected($s, $filters['status']); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/broadcasts" class="btn btn-secondary">Reset</a>
</form>

<?php if (!empty($broadcasts)): ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Channel</th><th>Template</th><th>Status</th><th>Scheduled (UTC)</th><th>Sent / Failed / Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($broadcasts as $b): ?>
            <tr>
                <td><a href="/broadcasts/<?php echo (int) $b['id']; ?>"><strong><?php echo e($b['name']); ?></strong></a>
                    <?php if ($b['bot_name']): ?><div class="muted small">Bot: <?php echo e($b['bot_name']); ?></div><?php endif; ?></td>
                <td><?php echo e($b['channel_name']); ?></td>
                <td><?php echo e($b['template_name'] ?: 'Custom message'); ?></td>
                <td><?php echo badge($b['status']); ?></td>
                <td class="nowrap"><?php echo e(format_date($b['scheduled_at'])); ?></td>
                <td><?php echo (int) $b['sent_count']; ?> / <?php echo (int) $b['failed_count']; ?> / <?php echo (int) $b['total_recipients']; ?></td>
                <td><a class="btn btn-small btn-secondary" href="/broadcasts/<?php echo (int) $b['id']; ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php partial('pagination', ['pagination' => $pagination]); ?>
<?php else: ?>
<div class="empty-state">
    <p>No broadcasts yet. Send a template to a tag or to all contacts.</p>
    <a class="btn" href="/broadcasts/create">New broadcast</a>
</div>
<?php endif; ?>
