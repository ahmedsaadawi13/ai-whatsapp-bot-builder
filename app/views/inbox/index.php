<?php // FILE: /app/views/inbox/index.php ?>
<?php
$assignedRaw = isset($_GET['assigned_to']) && is_string($_GET['assigned_to']) ? $_GET['assigned_to'] : '';
$tabs = ['open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed', '' => 'All'];
?>
<div class="page-header">
    <h1>Inbox</h1>
    <div class="actions">
        <a class="btn btn-secondary" href="<?php echo e(query_url(['assigned_to' => 'me', 'page' => null])); ?>">My conversations</a>
        <a class="btn btn-secondary" href="<?php echo e(query_url(['assigned_to' => 'none', 'page' => null])); ?>">Unassigned</a>
    </div>
</div>

<div class="filters">
    <?php foreach ($tabs as $value => $label): ?>
        <?php $count = $value === '' ? array_sum($counts) : (isset($counts[$value]) ? $counts[$value] : 0); ?>
        <a href="<?php echo e('/inbox?' . http_build_query(['status' => $value] + array_filter(['assigned_to' => $assignedRaw, 'channel_id' => $filters['channel_id'], 'search' => $filters['search']]))); ?>"
           class="btn btn-small <?php echo (string) $filters['status'] === (string) $value ? '' : 'btn-secondary'; ?>">
            <?php echo e($label); ?> (<?php echo (int) $count; ?>)
        </a>
    <?php endforeach; ?>
</div>

<form method="GET" action="/inbox" class="filters">
    <input type="hidden" name="status" value="<?php echo e($filters['status']); ?>">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" placeholder="Name or phone" value="<?php echo e($filters['search']); ?>">
    </div>
    <div class="form-group">
        <label for="assigned_to">Assigned to</label>
        <select id="assigned_to" name="assigned_to">
            <option value="">Anyone</option>
            <option value="me"<?php echo selected('me', $assignedRaw); ?>>Me</option>
            <option value="none"<?php echo selected('none', $assignedRaw); ?>>Unassigned (bot)</option>
            <?php foreach ($agents as $agent): ?>
                <option value="<?php echo (int) $agent['id']; ?>"<?php echo selected($agent['id'], $assignedRaw); ?>><?php echo e($agent['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="channel_id">Channel</label>
        <select id="channel_id" name="channel_id">
            <option value="">All channels</option>
            <?php foreach ($channels as $ch): ?>
                <option value="<?php echo (int) $ch['id']; ?>"<?php echo selected($ch['id'], $filters['channel_id']); ?>><?php echo e($ch['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/inbox" class="btn btn-secondary">Reset</a>
</form>

<?php if (!empty($conversations)): ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Contact</th><th>Last message</th><th>Channel / Bot</th><th>Assigned</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($conversations as $cv): ?>
            <tr>
                <td>
                    <a href="/conversations/<?php echo (int) $cv['id']; ?>"><strong><?php echo e($cv['contact_name'] ?: $cv['phone_number']); ?></strong></a>
                    <?php if ((int) $cv['unread_count'] > 0): ?><span class="badge badge-pending"><?php echo (int) $cv['unread_count']; ?> new</span><?php endif; ?>
                    <div class="muted small"><?php echo e($cv['phone_number']); ?></div>
                </td>
                <td><?php echo e(mb_strimwidth((string) $cv['last_message'], 0, 90, '...', 'UTF-8')); ?></td>
                <td><?php echo e($cv['channel_name']); ?><div class="muted small"><?php echo e($cv['bot_name'] ?: 'No bot'); ?></div></td>
                <td><?php echo $cv['assigned_to_name'] ? e($cv['assigned_to_name']) : '<span class="muted">Bot</span>'; ?></td>
                <td><?php echo badge($cv['status']); ?></td>
                <td class="nowrap"><?php echo e(format_date($cv['last_message_at'])); ?></td>
                <td><a class="btn btn-small" href="/conversations/<?php echo (int) $cv['id']; ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php partial('pagination', ['pagination' => $pagination]); ?>
<?php else: ?>
<div class="empty-state"><p>No conversations here. New WhatsApp messages will appear automatically.</p></div>
<?php endif; ?>
