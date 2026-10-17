<?php // FILE: /app/views/channels/create.php ?>
<div class="page-header">
    <h1>Add Channel</h1>
    <div class="actions"><a href="/channels" class="btn btn-secondary">Back to channels</a></div>
</div>
<div class="card">
    <form method="POST" action="/channels/store">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-group">
            <label for="provider_config">Provider credentials (JSON)</label>
            <textarea id="provider_config" name="provider_config" placeholder='{"phone_number_id": "...", "access_token": "..."}'><?php echo e(old('provider_config', '')); ?></textarea>
            <div class="help">Stored encrypted. Leave empty for the sandbox provider.</div>
            <?php echo field_error('provider_config'); ?>
        </div>
        <p class="muted small">A webhook verify token is generated automatically after saving.</p>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save channel</button>
            <a href="/channels" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
