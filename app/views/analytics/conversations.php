<?php // FILE: /app/views/analytics/conversations.php ?>
<?php
$chartData = [
    'type' => 'bar',
    'labels' => ['Open', 'Pending', 'Closed'],
    'series' => [['name' => 'Conversations', 'color' => '#128c7e', 'values' => [$stats['open'], $stats['pending'], $stats['closed']]]]
];
?>
<div class="page-header"><h1>Conversations Report</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>

<div class="stats-grid">
    <div class="stat-card"><h3>Total</h3><div class="stat-number"><?php echo (int) $total; ?></div></div>
    <?php foreach (['open', 'pending', 'closed'] as $s): ?>
        <div class="stat-card">
            <h3><?php echo e(ucfirst($s)); ?></h3>
            <div class="stat-number"><?php echo (int) $stats[$s]; ?></div>
            <div class="muted small"><?php echo $total > 0 ? round($stats[$s] / $total * 100, 1) : 0; ?>% &middot; <a href="/inbox?status=<?php echo e($s); ?>">view</a></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Conversations by status</h2>
    <div class="chart" data-chart="<?php echo json_attr($chartData); ?>"></div>
</div>
