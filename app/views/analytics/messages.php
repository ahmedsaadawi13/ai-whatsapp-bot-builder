<?php // FILE: /app/views/analytics/messages.php ?>
<?php $csvExport = true; ?>
<div class="page-header"><h1>Messages Report</h1></div>
<?php require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_range.php'; ?>

<div class="stats-grid">
    <div class="stat-card"><h3>Total</h3><div class="stat-number"><?php echo (int) $stats['total_messages']; ?></div></div>
    <div class="stat-card"><h3>Received</h3><div class="stat-number"><?php echo (int) $stats['inbound_count']; ?></div></div>
    <div class="stat-card"><h3>Flow replies</h3><div class="stat-number"><?php echo (int) $stats['flow_count']; ?></div></div>
    <div class="stat-card"><h3>AI replies</h3><div class="stat-number"><?php echo (int) $stats['ai_count']; ?></div></div>
    <div class="stat-card"><h3>Agent replies</h3><div class="stat-number"><?php echo (int) $stats['agent_count']; ?></div></div>
    <div class="stat-card"><h3>Broadcast</h3><div class="stat-number"><?php echo (int) $stats['broadcast_count']; ?></div></div>
</div>

<?php if (!empty($rows)): ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Date (UTC)</th><th>Direction</th><th>Source</th><th class="text-right">Messages</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="nowrap"><?php echo e($r['day']); ?></td>
                <td><?php echo e(ucfirst($r['direction'])); ?></td>
                <td><?php echo badge($r['source']); ?></td>
                <td class="text-right"><?php echo (int) $r['total']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state"><p>No messages in this date range.</p></div>
<?php endif; ?>
