<?php // FILE: /app/views/knowledge_base/edit.php ?>
<div class="page-header">
    <h1>Edit Knowledge</h1>
    <div class="actions"><a href="/knowledge-base" class="btn btn-secondary">Back to knowledge base</a></div>
</div>
<?php if ($entry['file_path']): ?>
<div class="alert alert-info">This entry was created from an uploaded document
    (<a href="/files?path=<?php echo e(rawurlencode($entry['file_path'])); ?>">download</a>). You can edit the extracted text below.</div>
<?php endif; ?>
<div class="card">
    <form method="POST" action="/knowledge-base/<?php echo (int) $entry['id']; ?>/update">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="/knowledge-base" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
