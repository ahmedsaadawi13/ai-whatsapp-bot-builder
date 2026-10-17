<?php // FILE: /app/views/templates/_form.php
// Shared template form. Expects $categories, $languages and optional $template.
$t = isset($template) ? $template : ['name' => '', 'category' => 'notification', 'language' => 'en', 'header' => '', 'body' => '', 'footer' => '', 'status' => 'active'];
?>
<div class="form-row">
    <div class="form-group">
        <label for="name">Template name</label>
        <input type="text" id="name" name="name" required maxlength="255" value="<?php echo e(old('name', $t['name'])); ?>">
        <?php echo field_error('name'); ?>
    </div>
    <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category">
            <?php foreach ($categories as $c): ?>
                <option value="<?php echo e($c); ?>"<?php echo selected(old('category', $t['category']), $c); ?>><?php echo e(ucfirst($c)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('category'); ?>
    </div>
    <div class="form-group">
        <label for="language">Language</label>
        <select id="language" name="language">
            <?php foreach ($languages as $lang): ?>
                <option value="<?php echo e($lang); ?>"<?php echo selected(old('language', $t['language']), $lang); ?>><?php echo e($lang); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('language'); ?>
    </div>
</div>
<div class="form-group">
    <label for="header">Header (optional)</label>
    <input type="text" id="header" name="header" maxlength="255" value="<?php echo e(old('header', $t['header'])); ?>">
    <?php echo field_error('header'); ?>
</div>
<div class="form-group">
    <label for="body">Body</label>
    <textarea id="body" name="body" required maxlength="4096" rows="6"><?php echo e(old('body', $t['body'])); ?></textarea>
    <div class="help">Insert a placeholder:
        <?php foreach (['name', 'first_name', 'phone', 'email', 'order_id'] as $var): ?>
            <button type="button" class="btn btn-small btn-secondary" data-insert="{{<?php echo e($var); ?>}}" data-target="body">{{<?php echo e($var); ?>}}</button>
        <?php endforeach; ?>
    </div>
    <div class="help">Contact fields (name, first_name, phone, email and custom fields) are filled automatically; other placeholders get values from the broadcast's variable mapping.</div>
    <?php echo field_error('body'); ?>
</div>
<div class="form-group">
    <label for="footer">Footer (optional)</label>
    <input type="text" id="footer" name="footer" maxlength="255" value="<?php echo e(old('footer', $t['footer'])); ?>" placeholder="Reply STOP to unsubscribe">
    <?php echo field_error('footer'); ?>
</div>
<div class="form-group">
    <label for="status">Status</label>
    <select id="status" name="status">
        <?php foreach (['active', 'inactive'] as $s): ?>
            <option value="<?php echo e($s); ?>"<?php echo selected(old('status', $t['status']), $s); ?>><?php echo e(ucfirst($s)); ?></option>
        <?php endforeach; ?>
    </select>
    <?php echo field_error('status'); ?>
</div>
