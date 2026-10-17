<?php // FILE: /app/views/flow_steps/edit.php ?>
<div class="page-header">
    <div>
        <p class="muted small"><a href="/bots/<?php echo (int) $bot['id']; ?>"><?php echo e($bot['name']); ?></a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>/flows">Flows</a> &rsaquo; <a href="/flows/<?php echo (int) $flow['id']; ?>/steps"><?php echo e($flow['name']); ?></a> &rsaquo; Edit step</p>
        <h1>Edit Step</h1>
    </div>
</div>
<div class="card">
    <form method="POST" action="/steps/<?php echo (int) $step['id']; ?>/update" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save step</button>
            <a href="/flows/<?php echo (int) $flow['id']; ?>/steps" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
