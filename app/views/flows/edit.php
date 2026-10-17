<?php // FILE: /app/views/flows/edit.php ?>
<div class="page-header">
    <div>
        <p class="muted small"><a href="/bots">Bots</a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>"><?php echo e($bot['name']); ?></a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>/flows">Flows</a> &rsaquo; <?php echo e($flow['name']); ?></p>
        <h1>Edit Flow</h1>
    </div>
    <div class="actions"><a href="/flows/<?php echo (int) $flow['id']; ?>/steps" class="btn btn-secondary">Edit steps</a></div>
</div>
<div class="card">
    <form method="POST" action="/flows/<?php echo (int) $flow['id']; ?>/update">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="/bots/<?php echo (int) $bot['id']; ?>/flows" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
