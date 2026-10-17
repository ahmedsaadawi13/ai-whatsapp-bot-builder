<?php // FILE: /app/views/webhooks/logs.php ?>
<?php $wid = (int) $webhook['id']; ?>
<div class="page-header">
    <h1>Delivery logs: <?php echo e($webhook['name']); ?></h1>
    <div class="actions">
        <a class="btn btn-secondary" href="/webhooks">Back</a>
        <a class="btn btn-secondary" href="/webhooks/<?php echo $wid; ?>/edit">Edit</a>
        <?php echo post_button('/webhooks/' . $wid . '/test', 'Send test event', 'btn btn-info', null); ?>
    </div>
</div>
<p class="muted mb"><code><?php echo e($webhook['url']); ?></code> &middot; up to <?php echo (int) $webhook['retry_attempts']; ?> attempts per event &middot; <?php echo badge($webhook['status']); ?></p>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="status">Result</label>
        <select id="status" name="status">
            <option value="">All</option>
            <option value="success"<?php echo selected('success', $statusFilter); ?>>Success</option>
            <option value="failed"<?php echo selected('failed', $statusFilter); ?>>Failed</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
</form>

<?php if (!empty($logs)): ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Time</th><th>Event</th><th>Result</th><th>HTTP</th><th>Attempt</th><th>Payload / Response</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="nowrap"><?php echo e(format_date($log['created_at'], 'M d, Y H:i:s')); ?></td>
                <td><code><?php echo e($log['event_type']); ?></code></td>
                <td><?php echo badge($log['status']); ?><?php if ($log['status'] === 'failed' && (int) $log['retried']): ?> <span class="muted small">retried</span><?php endif; ?></td>
                <td><?php echo $log['response_code'] !== null ? (int) $log['response_code'] : '-'; ?></td>
                <td><?php echo (int) $log['attempt']; ?></td>
                <td>
                    <details>
                        <summary>Show</summary>
                        <p class="small muted">Payload</p>
                        <pre><?php
                            $decoded = json_decode((string) $log['payload'], true);
                            echo e($decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $log['payload']);
                        ?></pre>
                        <p class="small muted">Response</p>
                        <pre><?php echo e($log['response_body'] !== null && $log['response_body'] !== '' ? $log['response_body'] : '(empty)'); ?></pre>
                    </details>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php partial('pagination', ['pagination' => $pagination]); ?>
<?php else: ?>
<div class="empty-state"><p>No deliveries yet. Use "Send test event" to try the endpoint.</p></div>
<?php endif; ?>
