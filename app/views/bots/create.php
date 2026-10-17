<?php // FILE: /app/views/bots/create.php ?>
<div class="page-header">
    <h1>Create Bot</h1>
    <div class="actions"><a href="/bots" class="btn btn-secondary">Back to bots</a></div>
</div>
<div class="card">
    <form method="POST" action="/bots/store">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create bot</button>
            <a href="/bots" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
