<?php // FILE: /app/views/channels/_form.php
// Shared channel form fields. Expects $providers and optional $channel.
$c = isset($channel) ? $channel : ['name' => '', 'phone_number' => '', 'provider_type' => 'sandbox', 'status' => 'active'];
?>
<div class="form-row">
    <div class="form-group">
        <label for="name">Display name</label>
        <input type="text" id="name" name="name" required maxlength="255" value="<?php echo e(old('name', $c['name'])); ?>">
        <?php echo field_error('name'); ?>
    </div>
    <div class="form-group">
        <label for="phone_number">WhatsApp number</label>
        <input type="tel" id="phone_number" name="phone_number" required placeholder="+15551234567" value="<?php echo e(old('phone_number', $c['phone_number'])); ?>">
        <div class="help">International format (E.164), e.g. +15551234567.</div>
        <?php echo field_error('phone_number'); ?>
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="provider_type">Provider</label>
        <select id="provider_type" name="provider_type">
            <?php foreach ($providers as $key => $label): ?>
                <option value="<?php echo e($key); ?>"<?php echo selected(old('provider_type', $c['provider_type']), $key); ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('provider_type'); ?>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['active', 'inactive'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected(old('status', $c['status']), $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('status'); ?>
    </div>
</div>
