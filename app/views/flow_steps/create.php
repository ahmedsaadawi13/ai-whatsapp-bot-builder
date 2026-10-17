<?php // FILE: /app/views/flow_steps/create.php ?>
<div class="page-header">
    <div>
        <p class="muted small"><a href="/bots/<?php echo (int) $bot['id']; ?>"><?php echo e($bot['name']); ?></a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>/flows">Flows</a> &rsaquo; <a href="/flows/<?php echo (int) $flow['id']; ?>/steps"><?php echo e($flow['name']); ?></a> &rsaquo; New step</p>
        <h1>Add Step</h1>
    </div>
</div>
<div class="card">
    <form method="POST" action="/flows/<?php echo (int) $flow['id']; ?>/steps/store" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add step</button>
            <a href="/flows/<?php echo (int) $flow['id']; ?>/steps" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
