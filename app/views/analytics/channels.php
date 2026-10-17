<?php // FILE: /app/views/analytics/channels.php ?>
<?php
$csvExport = true;
$chartData = [
    'type' => 'bar',
    'labels' => array_column($rows, 'name'),
    'series' => [
        ['name' => 'Received', 'color' => '#2563eb', 'values' => array_map('intval', array_column($rows, 'inbound'))],
        ['name' => 'Sent', 'color' => '#128c7e', 'values' => array_map('intval', array_column($rows, 'outbound'))]
    ]
];
?>
<div class="page-header"><h1>Usage per Channel</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_range.php'; ?>

<?php if (!empty($rows)): ?>
<div class="card">
    <div class="chart" data-chart="<?php echo json_attr($chartData); ?>"></div>
</div>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Channel</th><th>Number</th><th>Status</th><th class="text-right">Conversations</th><th class="text-right">Received</th><th class="text-right">Sent</th><th class="text-right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?php echo e($r['name']); ?></strong></td>
                <td><?php echo e($r['phone_number']); ?></td>
                <td><?php echo badge($r['status']); ?></td>
                <td class="text-right"><?php echo (int) $r['conversations']; ?></td>
                <td class="text-right"><?php echo (int) $r['inbound']; ?></td>
                <td class="text-right"><?php echo (int) $r['outbound']; ?></td>
                <td class="text-right"><strong><?php echo (int) $r['messages']; ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="muted small mt">Conversation counts are all-time; message counts are limited to the selected dates.</p>
<?php else: ?>
<div class="empty-state"><p>No channels yet.</p></div>
<?php endif; ?>
