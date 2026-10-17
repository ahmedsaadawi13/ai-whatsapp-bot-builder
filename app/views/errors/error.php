<?php // FILE: /app/views/errors/error.php ?>
<div class="empty-state">
    <h1 class="error-code"><?php echo (int) $code; ?></h1>
    <p><?php echo e($message); ?></p>
    <a href="/dashboard" class="btn btn-primary">Back to dashboard</a>
</div>
