<?php // FILE: /app/views/contacts/_form.php ?>
<?php $c = isset($contact) ? $contact : []; ?>
<?php echo csrf_field(); ?>
<div class="form-row">
    <div class="form-group">
        <label for="phone_number">Phone number (E.164)</label>
        <input type="tel" id="phone_number" name="phone_number" required placeholder="+15551234567"
               value="<?php echo e(old('phone_number', isset($c['phone_number']) ? $c['phone_number'] : '')); ?>">
        <?php echo field_error('phone_number'); ?>
    </div>
    <div class="form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?php echo e(old('name', isset($c['name']) ? $c['name'] : '')); ?>">
        <?php echo field_error('name'); ?>
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?php echo e(old('email', isset($c['email']) ? $c['email'] : '')); ?>">
        <?php echo field_error('email'); ?>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <?php $st = old('status', isset($c['status']) ? $c['status'] : 'active'); ?>
        <select id="status" name="status">
            <?php foreach ($statuses as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected($s, $st); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('status'); ?>
    </div>
</div>
<div class="form-group">
    <label for="tags">Tags</label>
    <input type="text" id="tags" name="tags" placeholder="VIP Customer, Lead" value="<?php echo e(old('tags', isset($tagList) ? $tagList : '')); ?>">
    <div class="help">Comma separated. New tags are created automatically.</div>
</div>
<div class="form-group">
    <label for="custom_fields">Custom fields</label>
    <textarea id="custom_fields" name="custom_fields" placeholder="company=ABC Corp&#10;order_id=A-1001"><?php echo e(old('custom_fields', isset($customFieldsText) ? $customFieldsText : '')); ?></textarea>
    <div class="help">One <code>key=value</code> per line. Usable in templates as <code>{{custom.key}}</code>.</div>
    <?php echo field_error('custom_fields'); ?>
</div>
<div class="form-group">
    <label for="notes">Notes</label>
    <textarea id="notes" name="notes"><?php echo e(old('notes', isset($c['notes']) ? $c['notes'] : '')); ?></textarea>
    <?php echo field_error('notes'); ?>
</div>
