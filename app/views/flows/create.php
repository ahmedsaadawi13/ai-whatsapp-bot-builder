<?php // FILE: /app/views/flows/create.php ?>
<div class="page-header">
    <div>
        <p class="muted small"><a href="/bots">Bots</a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>"><?php echo e($bot['name']); ?></a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>/flows">Flows</a> &rsaquo; New</p>
        <h1>Create Flow</h1>
    </div>
</div>
<div class="card">
    <form method="POST" action="/bots/<?php echo (int) $bot['id']; ?>/flows/store">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create flow &amp; add steps</button>
            <a href="/bots/<?php echo (int) $bot['id']; ?>/flows" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
