<?php // FILE: /app/views/dashboard/index.php ?>
<?php
$chartData = [
    'type' => 'line',
    'labels' => array_map(function ($r) { return date('M d', strtotime($r['day'])); }, $chart),
    'series' => [
        ['name' => 'Received', 'color' => '#128c7e', 'values' => array_map('intval', array_column($chart, 'inbound'))],
        ['name' => 'Sent', 'color' => '#2563eb', 'values' => array_map('intval', array_column($chart, 'outbound'))]
    ]
];
$responders = ['ai' => 'AI', 'flow' => 'Flows', 'agent' => 'Agents'];
$maxFlow = !empty($topFlows) ? max(array_map('intval', array_column($topFlows, 'hits'))) : 1;
$maxKeyword = !empty($topKeywords) ? max(array_map('intval', array_column($topKeywords, 'uses'))) : 1;
?>
<div class="page-header">
    <h1>Dashboard</h1>
    <div class="actions">
        <a href="/inbox" class="btn btn-secondary">Open inbox</a>
        <a href="/analytics" class="btn">Analytics</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><h3>Active bots</h3><div class="stat-number"><?php echo (int) $stats['active_bots']; ?></div><div class="muted small">of <?php echo (int) $stats['total_bots']; ?> bots</div></div>
    <div class="stat-card"><h3>Channels</h3><div class="stat-number"><?php echo (int) $stats['total_channels']; ?></div></div>
    <div class="stat-card"><h3>Contacts</h3><div class="stat-number"><?php echo (int) $stats['total_contacts']; ?></div></div>
    <div class="stat-card"><h3>Open conversations</h3><div class="stat-number"><?php echo (int) $stats['open_conversations']; ?></div><div class="muted small"><?php echo (int) $stats['pending_conversations']; ?> pending</div></div>
    <div class="stat-card"><h3>Messages this month</h3><div class="stat-number"><?php echo (int) $messageStats['total_messages']; ?></div><div class="muted small"><?php echo (int) $messageStats['inbound_count']; ?> in / <?php echo (int) $messageStats['outbound_count']; ?> out</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2>Messages - last 14 days</h2></div>
        <div class="chart" data-chart="<?php echo json_attr($chartData); ?>"></div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Average response time</h2></div>
        <table class="data-table">
            <thead><tr><th>Answered by</th><th>Avg. time</th><th>Replies</th></tr></thead>
            <tbody>
            <?php foreach ($responders as $key => $label): $rt = $responseTimes[$key]; ?>
                <tr>
                    <td><?php echo badge($key); ?> <?php echo e($label); ?></td>
                    <td><?php echo $rt ? e(duration($rt['avg_seconds'])) : '-'; ?></td>
                    <td><?php echo $rt ? (int) $rt['replies'] : 0; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="help">Time between a contact's message and the next reply, last 14 days.</p>
    </div>
</div>

<div class="grid grid-3">
    <div class="card">
        <h2>Top flows</h2>
        <?php if (empty($topFlows)): ?>
            <p class="muted">No flows triggered yet.</p>
        <?php else: foreach ($topFlows as $f): ?>
            <div class="hbar">
                <span class="hbar-label" title="<?php echo e($f['name']); ?>"><?php echo e($f['name']); ?></span>
                <span class="hbar-track"><span class="hbar-fill" style="display:block;width:<?php echo percent($f['hits'], $maxFlow); ?>%"></span></span>
                <span class="small"><?php echo (int) $f['hits']; ?></span>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card">
        <h2>Top keywords</h2>
        <?php if (empty($topKeywords)): ?>
            <p class="muted">No messages yet.</p>
        <?php else: foreach ($topKeywords as $k): ?>
            <div class="hbar">
                <span class="hbar-label" title="<?php echo e($k['keyword']); ?>"><?php echo e($k['keyword']); ?></span>
                <span class="hbar-track"><span class="hbar-fill" style="display:block;width:<?php echo percent($k['uses'], $maxKeyword); ?>%"></span></span>
                <span class="small"><?php echo (int) $k['uses']; ?></span>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>Plan usage</h2>
            <?php if ($subscription): ?><?php echo badge($subscription['plan_name']); ?><?php endif; ?>
        </div>
        <?php if (!$subscription): ?>
            <div class="alert alert-warning">No active subscription.</div>
        <?php endif; ?>
        <?php partial('usage_bars', ['quota' => $quota]); ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Recent conversations</h2><a href="/inbox" class="btn btn-small btn-secondary">View all</a></div>
    <?php if (empty($recentConversations)): ?>
        <p class="muted">No conversations yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Contact</th><th>Channel</th><th>Last message</th><th>Status</th><th>When</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recentConversations as $c): ?>
                <tr>
                    <td><?php echo e($c['contact_name'] ?: $c['phone_number']); ?>
                        <?php if ($c['unread_count'] > 0): ?><span class="badge badge-pending"><?php echo (int) $c['unread_count']; ?> new</span><?php endif; ?></td>
                    <td><?php echo e($c['channel_name']); ?></td>
                    <td class="muted"><?php echo e(mb_strimwidth((string) $c['last_message'], 0, 60, '...', 'UTF-8')); ?></td>
                    <td><?php echo badge($c['status']); ?></td>
                    <td class="nowrap"><?php echo e(format_date($c['last_message_at'])); ?></td>
                    <td><a href="/conversations/<?php echo (int) $c['id']; ?>" class="btn btn-small">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
