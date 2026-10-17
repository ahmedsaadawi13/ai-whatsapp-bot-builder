<?php // FILE: /app/views/webhooks/_form.php ?>
<?php
$w = isset($webhook) ? $webhook : [];
$selectedEvents = isset($_POST['events']) ? (array) $_POST['events'] : (isset($w['events_list']) ? $w['events_list'] : []);
$oldEvents = old('events', null);
if (is_array($oldEvents)) {
    $selectedEvents = $oldEvents;
}
?>
<?php echo csrf_field(); ?>
<div class="form-row">
    <div class="form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required value="<?php echo e(old('name', isset($w['name']) ? $w['name'] : '')); ?>">
        <?php echo field_error('name'); ?>
    </div>
    <div class="form-group">
        <label for="url">Endpoint URL</label>
        <input type="url" id="url" name="url" required placeholder="https://example.com/hooks/whatsapp" value="<?php echo e(old('url', isset($w['url']) ? $w['url'] : '')); ?>">
        <div class="help">Must be a public http(s) address.</div>
        <?php echo field_error('url'); ?>
    </div>
</div>
<div class="form-group">
    <span class="label">Events</span>
    <?php foreach ($events as $event): ?>
        <label class="checkbox"><input type="checkbox" name="events[]" value="<?php echo e($event); ?>"<?php echo checked(in_array($event, $selectedEvents, true)); ?>> <code><?php echo e($event); ?></code></label>
    <?php endforeach; ?>
    <?php echo field_error('events'); ?>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="bot_id">Bot</label>
        <?php $botSel = old('bot_id', isset($w['bot_id']) ? $w['bot_id'] : ''); ?>
        <select id="bot_id" name="bot_id">
            <option value="">All bots (tenant-wide)</option>
            <?php foreach ($bots as $bot): ?>
                <option value="<?php echo (int) $bot['id']; ?>"<?php echo selected($bot['id'], $botSel); ?>><?php echo e($bot['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('bot_id'); ?>
    </div>
    <div class="form-group">
        <label for="retry_attempts">Max delivery attempts</label>
        <input type="number" id="retry_attempts" name="retry_attempts" min="1" max="10" value="<?php echo e(old('retry_attempts', isset($w['retry_attempts']) ? $w['retry_attempts'] : 3)); ?>">
        <div class="help">Failed deliveries are retried by <code>php cron/run.php webhooks</code>.</div>
        <?php echo field_error('retry_attempts'); ?>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <?php $st = old('status', isset($w['status']) ? $w['status'] : 'active'); ?>
        <select id="status" name="status">
            <option value="active"<?php echo selected('active', $st); ?>>Active</option>
            <option value="inactive"<?php echo selected('inactive', $st); ?>>Inactive</option>
        </select>
    </div>
</div>
