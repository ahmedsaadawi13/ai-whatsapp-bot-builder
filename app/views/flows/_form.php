<?php // FILE: /app/views/flows/_form.php
// Shared flow form. Expects $triggerLabels and optional $flow.
$f = isset($flow) ? $flow : ['name' => '', 'description' => '', 'trigger_type' => 'keyword', 'trigger_value' => '', 'priority' => 0, 'status' => 'active'];
?>
<div class="form-row">
    <div class="form-group">
        <label for="name">Flow name</label>
        <input type="text" id="name" name="name" required maxlength="255" value="<?php echo e(old('name', $f['name'])); ?>">
        <?php echo field_error('name'); ?>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['active', 'inactive'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected(old('status', $f['status']), $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('status'); ?>
    </div>
</div>
<div class="form-group">
    <label for="description">Description</label>
    <textarea id="description" name="description" maxlength="2000"><?php echo e(old('description', $f['description'])); ?></textarea>
    <?php echo field_error('description'); ?>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="trigger_type">Trigger type</label>
        <select id="trigger_type" name="trigger_type">
            <?php foreach ($triggerLabels as $key => $label): ?>
                <option value="<?php echo e($key); ?>"<?php echo selected(old('trigger_type', $f['trigger_type']), $key); ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('trigger_type'); ?>
    </div>
    <div class="form-group">
        <label for="priority">Priority</label>
        <input type="number" id="priority" name="priority" min="-100" max="100" value="<?php echo e(old('priority', $f['priority'])); ?>">
        <div class="help">Higher priority flows are checked first.</div>
        <?php echo field_error('priority'); ?>
    </div>
</div>
<div class="form-group" data-show-when="trigger_type=keyword|message_contains|menu|button">
    <label for="trigger_value">Trigger values</label>
    <input type="text" id="trigger_value" name="trigger_value" maxlength="255" value="<?php echo e(old('trigger_value', $f['trigger_value'])); ?>" placeholder="hi, hello, start">
    <div class="help">Comma-separated, case-insensitive. Keyword/menu/button: the whole message must equal a value (e.g. menu option "1" or button payload "book_demo"). Contains: the message must contain a value.</div>
    <?php echo field_error('trigger_value'); ?>
</div>
