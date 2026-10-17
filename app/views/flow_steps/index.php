<?php // FILE: /app/views/flow_steps/index.php
// Readable one-line summary of a step's configuration.
$summary = function ($step) {
    $c = $step['config'];
    $get = function ($k) use ($c) { return isset($c[$k]) && is_scalar($c[$k]) ? (string) $c[$k] : ''; };
    switch ($step['action_type']) {
        case 'send_text':
            return 'Send: "' . e($get('message')) . '"';
        case 'send_media':
            return 'Send media <code>' . e($get('media_url')) . '</code>' . ($get('caption') !== '' ? ' with caption "' . e($get('caption')) . '"' : '');
        case 'ask_question':
            $html = 'Ask: "' . e($get('question')) . '"';
            if (!empty($c['options']) && is_array($c['options'])) {
                $html .= ' &middot; options: ';
                foreach ($c['options'] as $o) { $html .= '<span class="tag">' . e($o) . '</span>'; }
            }
            if ($get('variable') !== '') {
                $html .= ' &middot; saves answer to <code>{{' . e($get('variable')) . '}}</code> and waits';
            }
            return $html;
        case 'call_ai':
            return 'AI answer for ' . ($get('prompt') !== '' ? '"' . e($get('prompt')) . '"' : 'the user\'s message');
        case 'call_webhook':
            return 'POST conversation to <code>' . e($get('url')) . '</code>';
        case 'set_variable':
            return 'Set <code>{{' . e($get('key')) . '}}</code> = "' . e($get('value')) . '"';
        case 'branch_condition':
            $html = 'If <code>' . e($get('variable')) . '</code> ' . e($get('operator') ?: '==') . ' "' . e($get('value')) . '"';
            if ($get('then_message') !== '') { $html .= '<br>then: "' . e($get('then_message')) . '"'; }
            if ($get('then_flow_id') !== '') { $html .= '<br>then jump to flow #' . (int) $get('then_flow_id'); }
            if ($get('else_message') !== '') { $html .= '<br>else: "' . e($get('else_message')) . '"'; }
            if ($get('else_flow_id') !== '') { $html .= '<br>else jump to flow #' . (int) $get('else_flow_id'); }
            return $html;
    }
    return '';
};
?>
<div class="page-header">
    <div>
        <p class="muted small"><a href="/bots">Bots</a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>"><?php echo e($bot['name']); ?></a> &rsaquo; <a href="/bots/<?php echo (int) $bot['id']; ?>/flows">Flows</a> &rsaquo; <?php echo e($flow['name']); ?></p>
        <h1><?php echo e($flow['name']); ?> <?php echo badge($flow['status']); ?></h1>
        <p class="muted">Trigger: <?php echo e(str_replace('_', ' ', $flow['trigger_type'])); ?>
            <?php if ($flow['trigger_value'] !== null): ?> &middot; <code><?php echo e($flow['trigger_value']); ?></code><?php endif; ?>
            &middot; priority <?php echo (int) $flow['priority']; ?></p>
    </div>
    <div class="actions">
        <a href="/flows/<?php echo (int) $flow['id']; ?>/edit" class="btn btn-secondary">Edit flow</a>
        <a href="/bots/<?php echo (int) $bot['id']; ?>" class="btn btn-secondary">Test bot</a>
        <a href="/flows/<?php echo (int) $flow['id']; ?>/steps/create" class="btn btn-primary">Add step</a>
    </div>
</div>

<?php if (empty($steps)): ?>
<div class="empty-state">
    <p>This flow has no steps yet. Steps run in order when the flow is triggered.</p>
    <a href="/flows/<?php echo (int) $flow['id']; ?>/steps/create" class="btn btn-primary">Add the first step</a>
</div>
<?php else: ?>
<ol class="step-list">
    <?php foreach ($steps as $i => $step): ?>
    <li class="step-item">
        <div class="step-number"><?php echo $i + 1; ?></div>
        <div class="step-body">
            <strong><?php echo e(isset($actionLabels[$step['action_type']]) ? $actionLabels[$step['action_type']] : $step['action_type']); ?></strong>
            <div class="small"><?php echo $summary($step); ?></div>
        </div>
        <div class="actions">
            <?php if ($i > 0): ?><?php echo post_button('/steps/' . (int) $step['id'] . '/move-up', 'Up', 'btn btn-small btn-secondary', null); ?><?php endif; ?>
            <?php if ($i < count($steps) - 1): ?><?php echo post_button('/steps/' . (int) $step['id'] . '/move-down', 'Down', 'btn btn-small btn-secondary', null); ?><?php endif; ?>
            <a href="/steps/<?php echo (int) $step['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
            <?php echo post_button('/steps/' . (int) $step['id'] . '/delete', 'Delete', 'btn btn-small btn-danger', 'Delete this step?'); ?>
        </div>
    </li>
    <?php endforeach; ?>
</ol>
<p class="muted small">An "Ask a question" step that saves the answer pauses the flow; the next message from the contact is stored in the variable and the flow continues with the following step.</p>
<?php endif; ?>
