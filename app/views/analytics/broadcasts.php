<?php // FILE: /app/views/analytics/broadcasts.php ?>
<?php
$chartData = [
    'type' => 'bar',
    'labels' => array_column($rows, 'name'),
    'series' => [
        ['name' => 'Sent', 'color' => '#128c7e', 'values' => array_map('intval', array_column($rows, 'sent_count'))],
        ['name' => 'Failed', 'color' => '#dc2626', 'values' => array_map('intval', array_column($rows, 'failed_count'))]
    ]
];
?>
<div class="page-header">
    <h1>Broadcast Performance</h1>
    <div class="actions"><a class="btn btn-secondary" href="/broadcasts">All broadcasts</a></div>
</div>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if (!empty($rows)): ?>
<div class="card">
    <div class="chart" data-chart="<?php echo json_attr($chartData); ?>"></div>
</div>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Broadcast</th><th>Status</th><th>Completed</th><th class="text-right">Recipients</th><th class="text-right">Sent</th><th class="text-right">Failed</th><th class="text-right">Delivery rate</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><a href="/broadcasts/<?php echo (int) $r['id']; ?>"><?php echo e($r['name']); ?></a></td>
                <td><?php echo badge($r['status']); ?></td>
                <td class="nowrap"><?php echo e(format_date($r['completed_at'])); ?></td>
                <td class="text-right"><?php echo (int) $r['total_recipients']; ?></td>
                <td class="text-right"><?php echo (int) $r['sent_count']; ?></td>
                <td class="text-right"><?php echo (int) $r['failed_count']; ?></td>
                <td class="text-right"><?php echo e($r['delivery_rate']); ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state"><p>No completed broadcasts yet.</p></div>
<?php endif; ?>
