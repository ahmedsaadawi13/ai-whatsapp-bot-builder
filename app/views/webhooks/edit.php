<?php // FILE: /app/views/webhooks/edit.php ?>
<?php $wid = (int) $webhook['id']; ?>
<div class="page-header">
    <h1>Edit Webhook</h1>
    <div class="actions">
        <a class="btn btn-secondary" href="/webhooks">Back</a>
        <a class="btn btn-secondary" href="/webhooks/<?php echo $wid; ?>/logs">Delivery logs</a>
        <?php echo post_button('/webhooks/' . $wid . '/test', 'Send test event', 'btn btn-info', null); ?>
    </div>
</div>

<form method="POST" action="/webhooks/<?php echo $wid; ?>/update" class="card">
    <?php require __DIR__ . '/_form.php'; ?>
    <label class="checkbox"><input type="checkbox" name="regenerate_secret" value="1"> Regenerate signing secret</label>
    <div class="form-actions"><button type="submit" class="btn">Save changes</button></div>
</form>

<div class="card">
    <h2>Signing secret</h2>
    <p><code><?php echo e($webhook['secret']); ?></code>
        <button type="button" class="btn btn-small btn-secondary" data-copy="<?php echo e($webhook['secret']); ?>">Copy</button></p>
    <p class="muted">Every delivery is a JSON POST with the header <code>X-Signature: sha256=&lt;hex&gt;</code>, where the hex value is <code>HMAC-SHA256(raw request body, secret)</code>. Verify it before trusting the payload:</p>
    <pre>$expected = 'sha256=' . hash_hmac('sha256', file_get_contents('php://input'), $secret);
if (!hash_equals($expected, $_SERVER['HTTP_X_SIGNATURE'] ?? '')) {
    http_response_code(401);
    exit;
}</pre>
    <p class="muted">Payload shape: <code>{"event": "message.received", "tenant_id": 1, "webhook_id": <?php echo $wid; ?>, "timestamp": "...", "data": {...}}</code>. Respond with any 2xx status to acknowledge.</p>
</div>

<div class="card">
    <h2>Danger zone</h2>
    <?php echo post_button('/webhooks/' . $wid . '/delete', 'Delete webhook', 'btn btn-danger', 'Delete this webhook and its logs?'); ?>
</div>
