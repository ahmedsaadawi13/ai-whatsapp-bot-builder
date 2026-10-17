<?php // FILE: /app/views/knowledge_base/_form.php
// Shared KB form. Expects $bots and optional $entry.
$k = isset($entry) ? $entry : ['bot_id' => '', 'topic' => '', 'question' => '', 'answer' => '', 'keywords' => '', 'category' => '', 'status' => 'active'];
?>
<div class="form-row">
    <div class="form-group">
        <label for="topic">Topic</label>
        <input type="text" id="topic" name="topic" required maxlength="255" value="<?php echo e(old('topic', $k['topic'])); ?>">
        <?php echo field_error('topic'); ?>
    </div>
    <div class="form-group">
        <label for="category">Category</label>
        <input type="text" id="category" name="category" maxlength="100" value="<?php echo e(old('category', $k['category'])); ?>" placeholder="Billing, Support...">
        <?php echo field_error('category'); ?>
    </div>
</div>
<div class="form-group">
    <label for="question">Question</label>
    <textarea id="question" name="question" required maxlength="2000"><?php echo e(old('question', $k['question'])); ?></textarea>
    <?php echo field_error('question'); ?>
</div>
<div class="form-group">
    <label for="answer">Answer</label>
    <textarea id="answer" name="answer" required rows="8"><?php echo e(old('answer', $k['answer'])); ?></textarea>
    <?php echo field_error('answer'); ?>
</div>
<div class="form-group">
    <label for="keywords">Keywords</label>
    <input type="text" id="keywords" name="keywords" maxlength="1000" value="<?php echo e(old('keywords', $k['keywords'])); ?>" placeholder="refund, money back, cancel">
    <div class="help">Comma-separated words people might use. Keywords weigh more than words in the question.</div>
    <?php echo field_error('keywords'); ?>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="bot_id">Bot</label>
        <select id="bot_id" name="bot_id">
            <option value="">All bots</option>
            <?php foreach ($bots as $b): ?>
                <option value="<?php echo (int) $b['id']; ?>"<?php echo selected(old('bot_id', $k['bot_id']), $b['id']); ?>><?php echo e($b['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('bot_id'); ?>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['active', 'inactive'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected(old('status', $k['status']), $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('status'); ?>
    </div>
</div>
