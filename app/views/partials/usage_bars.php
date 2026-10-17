<?php
// FILE: /app/views/partials/usage_bars.php
// Expects $quota from QuotaService::summary().
?>
<div class="usage-list">
    <?php foreach ($quota as $row): ?>
    <div class="usage-row">
        <div class="usage-label">
            <span><?php echo e($row['label']); ?></span>
            <span class="muted"><?php echo e($row['used']); ?> / <?php echo e($row['limit']); ?></span>
        </div>
        <div class="progress"><div class="progress-bar progress-<?php echo e($row['state']); ?>" style="width: <?php echo (int) $row['percent']; ?>%"></div></div>
    </div>
    <?php endforeach; ?>
</div>
