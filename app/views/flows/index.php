<?php // FILE: /app/views/flows/index.php ?>
<div class="page-header">
    <div>
        <p class="muted small"><a href="/bots">Bots</a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>"><?php echo e($bot['name']); ?></a> &rsaquo; Flows</p>
        <h1>Flows</h1>
    </div>
    <div class="actions">
        <a href="/bots/<?php echo (int) $bot['id']; ?>" class="btn btn-secondary">Back to bot</a>
        <a href="/bots/<?php echo (int) $bot['id']; ?>/flows/create" class="btn btn-primary">Add flow</a>
    </div>
</div>

<div class="alert alert-info">
    Incoming messages are matched against active flows by priority (highest first). The first matching trigger wins;
    a "default" flow catches everything else. If nothing matches, the bot answers with AI (when enabled) or its fallback message.
</div>

<?php if (empty($flows)): ?>
<div class="empty-state">
    <p>This bot has no flows yet.</p>
    <a href="/bots/<?php echo (int) $bot['id']; ?>/flows/create" class="btn btn-primary">Add the first flow</a>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>Priority</th><th>Name</th><th>Trigger</th><th>Trigger values</th><th>Steps</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($flows as $flow): ?>
        <tr>
            <td><?php echo (int) $flow['priority']; ?></td>
            <td><strong><?php echo e($flow['name']); ?></strong>
                <?php if ($flow['description']): ?><div class="muted small"><?php echo e($flow['description']); ?></div><?php endif; ?></td>
            <td><?php echo e(isset($triggerLabels[$flow['trigger_type']]) ? $triggerLabels[$flow['trigger_type']] : $flow['trigger_type']); ?></td>
            <td><?php if ($flow['trigger_type'] === 'default'): ?><span class="muted">any message</span><?php else: ?>
                <?php foreach (Flow::triggerValues($flow['trigger_value']) as $value): ?><span class="tag"><?php echo e($value); ?></span><?php endforeach; ?>
            <?php endif; ?></td>
            <td><?php echo (int) $flow['step_count']; ?></td>
            <td><?php echo badge($flow['status']); ?></td>
            <td class="actions">
                <a href="/flows/<?php echo (int) $flow['id']; ?>/steps" class="btn btn-small">Steps</a>
                <a href="/flows/<?php echo (int) $flow['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
                <?php echo post_button('/flows/' . (int) $flow['id'] . '/delete', 'Delete', 'btn btn-small btn-danger', 'Delete this flow and its steps?'); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
