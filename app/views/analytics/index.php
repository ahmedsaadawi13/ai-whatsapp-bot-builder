<?php // FILE: /app/views/analytics/index.php ?>
<?php
$labels = array_map(function ($r) { return date('M d', strtotime($r['day'])); }, $chart);
$lineChart = [
    'type' => 'line',
    'labels' => $labels,
    'series' => [
        ['name' => 'Received', 'color' => '#2563eb', 'values' => array_column($chart, 'inbound')],
        ['name' => 'Sent', 'color' => '#128c7e', 'values' => array_column($chart, 'outbound')]
    ]
];
$sourceChart = [
    'type' => 'bar',
    'labels' => ['Flows', 'AI', 'Agents', 'Broadcasts'],
    'series' => [['name' => 'Replies', 'color' => '#128c7e', 'values' => [$stats['flow_count'], $stats['ai_count'], $stats['agent_count'], $stats['broadcast_count']]]]
];
$maxFlow = max(1, max(array_merge([0], array_column($topFlows, 'hits'))));
$maxKeyword = max(1, max(array_merge([0], array_column($topKeywords, 'uses'))));
$botTimes = array_filter([$responseTimes['ai'], $responseTimes['flow']]);
$botReplies = 0;
$botSeconds = 0;
foreach ($botTimes as $t) {
    $botReplies += $t['replies'];
    $botSeconds += $t['avg_seconds'] * $t['replies'];
}
$botAvg = $botReplies > 0 ? $botSeconds / $botReplies : null;
?>
<div class="page-header"><h1>Analytics</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_range.php'; ?>

<div class="stats-grid">
    <div class="stat-card"><h3>Total messages</h3><div class="stat-number"><?php echo (int) $stats['total_messages']; ?></div></div>
    <div class="stat-card"><h3>Received</h3><div class="stat-number"><?php echo (int) $stats['inbound_count']; ?></div></div>
    <div class="stat-card"><h3>Sent</h3><div class="stat-number"><?php echo (int) $stats['outbound_count']; ?></div></div>
    <div class="stat-card"><h3>Open conversations</h3><div class="stat-number"><?php echo (int) $conversationStats['open']; ?></div></div>
</div>

<div class="card">
    <h2>Messages per day</h2>
    <div class="chart" data-chart="<?php echo json_attr($lineChart); ?>"></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Average response time</h2>
        <table class="data-table">
            <thead><tr><th>Responder</th><th>Average</th><th>Replies</th></tr></thead>
            <tbody>
                <tr><td><strong>Bot (flows + AI)</strong></td><td><?php echo e(duration($botAvg)); ?></td><td><?php echo (int) $botReplies; ?></td></tr>
                <?php foreach (['flow' => 'Flows', 'ai' => 'AI answers', 'agent' => 'Human agents'] as $key => $label): ?>
                    <?php $rt = $responseTimes[$key]; ?>
                    <tr><td><?php echo e($label); ?></td><td><?php echo e(duration($rt ? $rt['avg_seconds'] : null)); ?></td><td><?php echo $rt ? (int) $rt['replies'] : 0; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted small mt">Time between a contact's message and the next reply.</p>
    </div>
    <div class="card">
        <h2>Who replied</h2>
        <div class="chart" data-chart="<?php echo json_attr($sourceChart); ?>"></div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Top flows (triggers)</h2>
        <?php if (empty($topFlows)): ?>
            <p class="muted">No flow activity in this period.</p>
        <?php endif; ?>
        <?php foreach ($topFlows as $f): ?>
            <div class="hbar">
                <span class="hbar-label" title="<?php echo e($f['trigger_value']); ?>"><?php echo e($f['name']); ?> <span class="muted small">(<?php echo e($f['trigger_type']); ?>)</span></span>
                <span class="hbar-track"><span class="hbar-fill" style="display:block;width:<?php echo (int) round($f['hits'] / $maxFlow * 100); ?>%"></span></span>
                <span><?php echo (int) $f['hits']; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Top keywords</h2>
        <?php if (empty($topKeywords)): ?>
            <p class="muted">No short messages received in this period.</p>
        <?php endif; ?>
        <?php foreach ($topKeywords as $k): ?>
            <div class="hbar">
                <span class="hbar-label">"<?php echo e($k['keyword']); ?>"</span>
                <span class="hbar-track"><span class="hbar-fill" style="display:block;width:<?php echo (int) round($k['uses'] / $maxKeyword * 100); ?>%"></span></span>
                <span><?php echo (int) $k['uses']; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
