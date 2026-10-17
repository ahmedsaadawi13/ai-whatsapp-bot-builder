<?php // FILE: /app/views/broadcasts/create.php ?>
<div class="page-header"><h1>New Broadcast</h1><div class="actions"><a class="btn btn-secondary" href="/broadcasts">Back</a></div></div>

<form method="POST" action="/broadcasts/store" class="card">
    <?php echo csrf_field(); ?>
    <div class="form-group">
        <label for="name">Campaign name</label>
        <input type="text" id="name" name="name" required value="<?php echo e(old('name')); ?>">
        <?php echo field_error('name'); ?>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="channel_id">Send from channel</label>
            <select id="channel_id" name="channel_id" required>
                <?php foreach ($channels as $ch): ?>
                    <option value="<?php echo (int) $ch['id']; ?>"<?php echo selected($ch['id'], old('channel_id')); ?>><?php echo e($ch['name'] . ' (' . $ch['phone_number'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
            <?php echo field_error('channel_id'); ?>
        </div>
        <div class="form-group">
            <label for="bot_id">Bot handling replies (optional)</label>
            <select id="bot_id" name="bot_id">
                <option value="">None</option>
                <?php foreach ($bots as $bot): ?>
                    <option value="<?php echo (int) $bot['id']; ?>"<?php echo selected($bot['id'], old('bot_id')); ?>><?php echo e($bot['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php echo field_error('bot_id'); ?>
        </div>
    </div>

    <h2 class="mt">Message</h2>
    <div class="form-group">
        <label for="template_id">Template</label>
        <select id="template_id" name="template_id">
            <option value="">-- Write a custom message below --</option>
            <?php foreach ($templates as $t): ?>
                <option value="<?php echo (int) $t['id']; ?>"<?php echo selected($t['id'], old('template_id')); ?>><?php echo e($t['name']); ?> &mdash; <?php echo e(mb_strimwidth($t['body'], 0, 60, '...', 'UTF-8')); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('template_id'); ?>
    </div>
    <div class="form-group">
        <label for="message_content">Custom message (used when no template is selected)</label>
        <textarea id="message_content" name="message_content" placeholder="Hi {{name}}, ..."><?php echo e(old('message_content')); ?></textarea>
        <?php echo field_error('message_content'); ?>
    </div>
    <div class="form-group">
        <label for="template_variables">Variable mapping</label>
        <textarea id="template_variables" name="template_variables" placeholder="month=October&#10;industry={{custom.industry}}"><?php echo e(old('template_variables')); ?></textarea>
        <div class="help">One <code>placeholder=value</code> per line. Values can be static text or contact fields: <code>{{name}}</code>, <code>{{first_name}}</code>, <code>{{phone}}</code>, <code>{{email}}</code>, <code>{{custom.field}}</code>. Unmapped placeholders use the contact field with the same name.</div>
        <?php echo field_error('template_variables'); ?>
    </div>

    <h2 class="mt">Audience</h2>
    <?php $target = old('target_type', 'all'); ?>
    <div class="form-group">
        <label class="checkbox"><input type="radio" name="target_type" value="all"<?php echo checked($target === 'all'); ?>> All active contacts</label>
        <label class="checkbox"><input type="radio" name="target_type" value="tag"<?php echo checked($target === 'tag'); ?>> Contacts with a tag</label>
        <label class="checkbox"><input type="radio" name="target_type" value="custom"<?php echo checked($target === 'custom'); ?>> Custom filter (field equals value)</label>
        <div class="help">Unsubscribed and blocked contacts are always excluded.</div>
    </div>
    <div class="form-group" data-show-when="target_type=tag">
        <label for="tag_id">Tag</label>
        <select id="tag_id" name="tag_id">
            <?php foreach ($tags as $tag): ?>
                <option value="<?php echo (int) $tag['id']; ?>"<?php echo selected($tag['id'], old('tag_id')); ?>><?php echo e($tag['name']); ?> (<?php echo (int) $tag['contact_count']; ?> contacts)</option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('tag_id'); ?>
    </div>
    <div class="form-row" data-show-when="target_type=custom">
        <div class="form-group">
            <label for="custom_field">Field</label>
            <input type="text" id="custom_field" name="custom_field" placeholder="industry" value="<?php echo e(old('custom_field')); ?>">
            <div class="help">A custom field key, or name / email / phone_number.</div>
            <?php echo field_error('custom_field'); ?>
        </div>
        <div class="form-group">
            <label for="custom_value">Equals</label>
            <input type="text" id="custom_value" name="custom_value" placeholder="Retail" value="<?php echo e(old('custom_value')); ?>">
        </div>
    </div>

    <h2 class="mt">Schedule</h2>
    <?php $mode = old('schedule_mode', 'now'); ?>
    <div class="form-group">
        <label class="checkbox"><input type="radio" name="schedule_mode" value="now"<?php echo checked($mode === 'now'); ?>> Send now</label>
        <label class="checkbox"><input type="radio" name="schedule_mode" value="later"<?php echo checked($mode === 'later'); ?>> Schedule for later</label>
        <label class="checkbox"><input type="radio" name="schedule_mode" value="draft"<?php echo checked($mode === 'draft'); ?>> Save as draft</label>
        <?php echo field_error('schedule_mode'); ?>
    </div>
    <div class="form-group" data-show-when="schedule_mode=later">
        <label for="scheduled_at">Send at (<?php echo e($timezone); ?>)</label>
        <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?php echo e(old('scheduled_at')); ?>">
        <div class="help">Scheduled broadcasts are sent by the cron job <code>php cron/run.php broadcasts</code>.</div>
        <?php echo field_error('scheduled_at'); ?>
    </div>

    <div class="form-actions"><button type="submit" class="btn">Create broadcast</button></div>
</form>
