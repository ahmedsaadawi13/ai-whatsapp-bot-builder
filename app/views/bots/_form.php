<?php // FILE: /app/views/bots/_form.php
// Shared bot form. Expects $channels, $tones, $languages and optional $bot.
$b = isset($bot) ? $bot : [
    'name' => '', 'description' => '', 'channel_id' => '', 'default_language' => 'en', 'ai_enabled' => 1,
    'ai_tone' => 'friendly', 'ai_max_length' => 500, 'fallback_message' => '', 'status' => 'draft'
];
?>
<div class="form-row">
    <div class="form-group">
        <label for="name">Bot name</label>
        <input type="text" id="name" name="name" required maxlength="255" value="<?php echo e(old('name', $b['name'])); ?>">
        <?php echo field_error('name'); ?>
    </div>
    <div class="form-group">
        <label for="channel_id">WhatsApp channel</label>
        <select id="channel_id" name="channel_id" required>
            <option value="">Choose a channel</option>
            <?php foreach ($channels as $ch): ?>
                <option value="<?php echo (int) $ch['id']; ?>"<?php echo selected(old('channel_id', $b['channel_id']), $ch['id']); ?>>
                    <?php echo e($ch['name'] . ' (' . $ch['phone_number'] . ')'); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($channels)): ?><div class="help">No channels yet. <a href="/channels/create">Add a channel</a> first.</div><?php endif; ?>
        <?php echo field_error('channel_id'); ?>
    </div>
</div>
<div class="form-group">
    <label for="description">Description</label>
    <textarea id="description" name="description" maxlength="2000"><?php echo e(old('description', $b['description'])); ?></textarea>
    <?php echo field_error('description'); ?>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="default_language">Default language</label>
        <select id="default_language" name="default_language">
            <?php foreach ($languages as $code => $label): ?>
                <option value="<?php echo e($code); ?>"<?php echo selected(old('default_language', $b['default_language']), $code); ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('default_language'); ?>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['draft', 'active', 'paused'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected(old('status', $b['status']), $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="help">Only active bots reply on the channel.</div>
        <?php echo field_error('status'); ?>
    </div>
</div>

<h3 class="mt">AI settings</h3>
<div class="form-group">
    <label class="checkbox"><input type="checkbox" name="ai_enabled" value="1"<?php echo checked(old('ai_enabled', $b['ai_enabled'])); ?>>
        Answer with AI (knowledge base) when no flow matches</label>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="ai_tone">Tone</label>
        <select id="ai_tone" name="ai_tone">
            <?php foreach ($tones as $tone): ?>
                <option value="<?php echo e($tone); ?>"<?php echo selected(old('ai_tone', $b['ai_tone']), $tone); ?>><?php echo e(ucfirst($tone)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('ai_tone'); ?>
    </div>
    <div class="form-group">
        <label for="ai_max_length">Max answer length (characters)</label>
        <input type="number" id="ai_max_length" name="ai_max_length" min="50" max="4000" value="<?php echo e(old('ai_max_length', $b['ai_max_length'])); ?>">
        <?php echo field_error('ai_max_length'); ?>
    </div>
</div>
<div class="form-group">
    <label for="fallback_message">Fallback message</label>
    <input type="text" id="fallback_message" name="fallback_message" maxlength="500" value="<?php echo e(old('fallback_message', $b['fallback_message'])); ?>" placeholder="Thank you for your message. A team member will respond shortly.">
    <div class="help">Sent when no flow matches and AI is disabled.</div>
    <?php echo field_error('fallback_message'); ?>
</div>
