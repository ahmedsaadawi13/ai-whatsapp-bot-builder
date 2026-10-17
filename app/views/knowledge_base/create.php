<?php // FILE: /app/views/knowledge_base/create.php ?>
<div class="page-header">
    <h1>Add Knowledge</h1>
    <div class="actions"><a href="/knowledge-base" class="btn btn-secondary">Back to knowledge base</a></div>
</div>
<div class="card">
    <form method="POST" action="/knowledge-base/store">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save entry</button>
            <a href="/knowledge-base" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
