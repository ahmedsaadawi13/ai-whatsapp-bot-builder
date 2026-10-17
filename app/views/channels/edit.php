<?php // FILE: /app/views/channels/edit.php ?>
<div class="page-header">
    <h1>Edit Channel</h1>
    <div class="actions"><a href="/channels" class="btn btn-secondary">Back to channels</a></div>
</div>

<div class="card">
    <h3>Webhook connection</h3>
    <dl class="detail-list">
        <dt>Webhook URL</dt>
        <dd><code><?php echo e($webhookUrl); ?></code>
            <button type="button" class="btn btn-small btn-secondary" data-copy="<?php echo e($webhookUrl); ?>">Copy</button></dd>
        <dt>Verify token</dt>
        <dd><code><?php echo e($channel['webhook_verify_token']); ?></code>
            <button type="button" class="btn btn-small btn-secondary" data-copy="<?php echo e($channel['webhook_verify_token']); ?>">Copy</button></dd>
    </dl>
    <p class="help">Send it as the <code>X-Webhook-Token</code> header, or as <code>hub.verify_token</code> for GET verification.</p>
</div>

<div class="card">
    <form method="POST" action="/channels/<?php echo (int) $channel['id']; ?>/update">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-group">
            <label for="provider_config">Provider credentials (JSON)</label>
            <?php if (!empty($configKeys)): ?>
                <p class="help">Stored (encrypted):
                <?php foreach ($configKeys as $key): ?>
                    <code><?php echo e($key); ?>: ********</code>
                <?php endforeach; ?></p>
            <?php endif; ?>
            <textarea id="provider_config" name="provider_config" placeholder="Leave empty to keep the stored credentials"><?php echo e(old('provider_config', '')); ?></textarea>
            <div class="help">Leave blank to keep the current credentials. Entering JSON replaces them.</div>
            <?php echo field_error('provider_config'); ?>
        </div>
        <div class="form-group">
            <label class="checkbox"><input type="checkbox" name="regenerate_token" value="1"> Generate a new verify token</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="/channels" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
