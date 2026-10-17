<?php // FILE: /app/views/analytics/_range.php ?>
<form method="GET" action="<?php echo e(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)); ?>" class="filters">
    <div class="form-group">
        <label for="start_date">From (UTC)</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo e($startDate); ?>">
    </div>
    <div class="form-group">
        <label for="end_date">To (UTC)</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo e($endDate); ?>">
    </div>
    <button type="submit" class="btn btn-secondary">Apply</button>
    <?php if (!empty($csvExport)): ?>
        <a class="btn btn-secondary" href="<?php echo e(query_url(['export' => 'csv', 'page' => null])); ?>">Export CSV</a>
    <?php endif; ?>
</form>
