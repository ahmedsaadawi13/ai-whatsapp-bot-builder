<?php // FILE: /app/views/templates/create.php ?>
<div class="page-header">
    <h1>Create Template</h1>
    <div class="actions"><a href="/templates" class="btn btn-secondary">Back to templates</a></div>
</div>
<div class="card">
    <form method="POST" action="/templates/store">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save template</button>
            <a href="/templates" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
