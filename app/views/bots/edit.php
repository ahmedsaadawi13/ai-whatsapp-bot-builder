<?php // FILE: /app/views/bots/edit.php ?>
<div class="page-header">
    <h1>Edit Bot</h1>
    <div class="actions"><a href="/bots/<?php echo (int) $bot['id']; ?>" class="btn btn-secondary">Back to bot</a></div>
</div>
<div class="card">
    <form method="POST" action="/bots/<?php echo (int) $bot['id']; ?>/update">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="/bots/<?php echo (int) $bot['id']; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<div class="card">
    <h3>Danger zone</h3>
    <p class="muted">Deleting a bot removes its flows and knowledge base entries.</p>
    <?php echo post_button('/bots/' . (int) $bot['id'] . '/delete', 'Delete bot', 'btn btn-danger', 'Delete this bot and all of its flows?'); ?>
</div>
