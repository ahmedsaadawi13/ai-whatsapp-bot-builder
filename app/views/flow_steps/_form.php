<?php // FILE: /app/views/flow_steps/_form.php
// Shared step form. Expects $step, $config, $actionLabels, $operators, $otherFlows.
$cfg = function ($key, $default = '') use ($config) {
    if (!isset($config[$key])) {
        return old($key, $default);
    }
    $value = $config[$key];
    if (is_array($value)) {
        $value = implode(', ', $value);
    }
    return old($key, (string) $value);
};
$actionType = old('action_type', $step['action_type']);
?>
<div class="form-row">
    <div class="form-group">
        <label for="action_type">Action</label>
        <select id="action_type" name="action_type">
            <?php foreach ($actionLabels as $key => $label): ?>
                <option value="<?php echo e($key); ?>"<?php echo selected($actionType, $key); ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php echo field_error('action_type'); ?>
    </div>
    <div class="form-group">
        <label for="step_order">Order</label>
        <input type="number" id="step_order" name="step_order" min="1" value="<?php echo e(old('step_order', $step['step_order'])); ?>">
        <div class="help">Steps run from the lowest number up.</div>
    </div>
</div>

<div data-show-when="action_type=send_text">
    <div class="form-group">
        <label for="message">Message</label>
        <textarea id="message" name="message" maxlength="4000"><?php echo e($cfg('message')); ?></textarea>
        <?php echo field_error('message'); ?>
    </div>
</div>

<div data-show-when="action_type=send_media">
    <div class="form-group">
        <label for="media_url">Media URL</label>
        <input type="text" id="media_url" name="media_url" value="<?php echo e($cfg('media_url')); ?>" placeholder="https://example.com/brochure.pdf">
        <?php echo field_error('media_url'); ?>
    </div>
    <div class="form-group">
        <label for="media_file">...or upload a file</label>
        <input type="file" id="media_file" name="media_file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt">
        <div class="help">jpg, png, pdf, doc, docx or txt, max 10 MB. Uploading replaces the URL.</div>
        <?php echo field_error('media_file'); ?>
    </div>
    <div class="form-group">
        <label for="caption">Caption</label>
        <input type="text" id="caption" name="caption" maxlength="4000" value="<?php echo e($cfg('caption')); ?>">
    </div>
</div>

<div data-show-when="action_type=ask_question">
    <div class="form-group">
        <label for="question">Question</label>
        <textarea id="question" name="question" maxlength="4000"><?php echo e($cfg('question')); ?></textarea>
        <?php echo field_error('question'); ?>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="options">Quick reply options</label>
            <input type="text" id="options" name="options" value="<?php echo e($cfg('options')); ?>" placeholder="Support, Sales, Business hours">
            <div class="help">Comma-separated buttons shown under the question (optional).</div>
        </div>
        <div class="form-group">
            <label for="variable">Save answer to variable</label>
            <input type="text" id="variable" name="variable" value="<?php echo e($cfg('variable')); ?>" placeholder="email">
            <div class="help">Optional. When set, the flow waits for the answer and stores it as {{variable}}.</div>
            <?php echo field_error('variable'); ?>
        </div>
    </div>
</div>

<div data-show-when="action_type=call_ai">
    <div class="form-group">
        <label for="prompt">Question for the AI</label>
        <input type="text" id="prompt" name="prompt" maxlength="4000" value="<?php echo e($cfg('prompt')); ?>" placeholder="Leave empty to use the contact's message">
        <div class="help">The AI answers from the knowledge base using the bot's tone and length settings.</div>
    </div>
</div>

<div data-show-when="action_type=call_webhook">
    <div class="form-group">
        <label for="url">Webhook URL</label>
        <input type="url" id="url" name="url" value="<?php echo e($cfg('url')); ?>" placeholder="https://api.example.com/orders/lookup">
        <div class="help">Receives a JSON POST with the message, contact and variables. Respond with {"reply": "...", "variables": {...}} to reply and/or save variables.</div>
        <?php echo field_error('url'); ?>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="secret">Signing secret (optional)</label>
            <input type="text" id="secret" name="secret" value="<?php echo e($cfg('secret')); ?>">
            <div class="help">Adds an X-Signature: sha256=... header.</div>
        </div>
        <div class="form-group">
            <label for="error_message">Message if the call fails</label>
            <input type="text" id="error_message" name="error_message" value="<?php echo e($cfg('error_message')); ?>">
        </div>
    </div>
</div>

<div data-show-when="action_type=set_variable">
    <div class="form-row">
        <div class="form-group">
            <label for="key">Variable name</label>
            <input type="text" id="key" name="key" value="<?php echo e($cfg('key')); ?>" placeholder="lead_status">
            <?php echo field_error('key'); ?>
        </div>
        <div class="form-group">
            <label for="value">Value</label>
            <input type="text" id="value" name="value" value="<?php echo e($cfg('value')); ?>" placeholder="qualified">
        </div>
    </div>
</div>

<div data-show-when="action_type=branch_condition">
    <div class="form-row">
        <div class="form-group">
            <label for="cond_variable">Variable</label>
            <input type="text" id="cond_variable" name="cond_variable" value="<?php echo e(isset($config['variable']) && $step['action_type'] === 'branch_condition' ? old('cond_variable', $config['variable']) : old('cond_variable')); ?>" placeholder="budget">
            <?php echo field_error('cond_variable'); ?>
        </div>
        <div class="form-group">
            <label for="operator">Operator</label>
            <select id="operator" name="operator">
                <?php $op = isset($config['operator']) ? old('operator', $config['operator']) : old('operator', '=='); ?>
                <?php foreach ($operators as $operator): ?>
                    <option value="<?php echo e($operator); ?>"<?php echo selected($op, $operator); ?>><?php echo e($operator); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="cond_value">Value</label>
            <input type="text" id="cond_value" name="cond_value" value="<?php echo e(isset($config['value']) && $step['action_type'] === 'branch_condition' ? old('cond_value', $config['value']) : old('cond_value')); ?>" placeholder="1000">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="then_message">If true, send</label>
            <textarea id="then_message" name="then_message"><?php echo e($cfg('then_message')); ?></textarea>
        </div>
        <div class="form-group">
            <label for="else_message">Otherwise, send</label>
            <textarea id="else_message" name="else_message"><?php echo e($cfg('else_message')); ?></textarea>
        </div>
    </div>
    <div class="form-row">
        <?php foreach (['then_flow_id' => 'If true, continue with flow', 'else_flow_id' => 'Otherwise, continue with flow'] as $field => $label): ?>
        <div class="form-group">
            <label for="<?php echo e($field); ?>"><?php echo e($label); ?></label>
            <select id="<?php echo e($field); ?>" name="<?php echo e($field); ?>">
                <option value="">- stay in this flow -</option>
                <?php foreach ($otherFlows as $other): ?>
                    <option value="<?php echo (int) $other['id']; ?>"<?php echo selected($cfg($field), $other['id']); ?>><?php echo e($other['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php echo field_error($field); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="help">Numeric operators (&gt;, &gt;=, &lt;, &lt;=) ignore currency symbols and commas, so "2,500 USD" &gt;= 1000 is true.</p>
</div>

<div class="alert alert-info small">
    Texts can use placeholders: <code>{{contact_name}}</code>, <code>{{contact_phone}}</code>, <code>{{contact_email}}</code>,
    <code>{{custom.&lt;field&gt;}}</code> for contact custom fields, and any variable saved earlier in the conversation (e.g. <code>{{email}}</code>).
</div>
