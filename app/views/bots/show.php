<?php // FILE: /app/views/bots/show.php ?>
<div class="page-header">
    <div>
        <p class="muted small"><a href="/bots">Bots</a> &rsaquo; <?php echo e($bot['name']); ?></p>
        <h1><?php echo e($bot['name']); ?> <?php echo badge($bot['status']); ?></h1>
    </div>
    <div class="actions">
        <a href="/bots/<?php echo (int) $bot['id']; ?>/flows" class="btn btn-secondary">Manage flows</a>
        <a href="/bots/<?php echo (int) $bot['id']; ?>/edit" class="btn btn-secondary">Edit</a>
        <?php echo post_button('/bots/' . (int) $bot['id'] . '/toggle-status', $bot['status'] === 'active' ? 'Pause bot' : 'Activate bot', 'btn', null); ?>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><h3>Active flows</h3><p class="stat-number"><?php echo (int) $bot['flow_count']; ?></p></div>
    <div class="stat-card"><h3>Knowledge entries</h3><p class="stat-number"><?php echo (int) $bot['knowledge_base_count']; ?></p></div>
    <div class="stat-card"><h3>Conversations</h3><p class="stat-number"><?php echo (int) $bot['conversation_count']; ?></p></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Details</h2>
        <dl class="detail-list">
            <dt>Channel</dt><dd><?php echo e($bot['channel_name']); ?> (<?php echo e($bot['phone_number']); ?>)</dd>
            <dt>Description</dt><dd><?php echo $bot['description'] ? e($bot['description']) : '<span class="muted">-</span>'; ?></dd>
            <dt>Language</dt><dd><?php echo e($bot['default_language']); ?></dd>
            <dt>AI fallback</dt><dd><?php echo $bot['ai_enabled'] ? 'Enabled' : 'Disabled'; ?></dd>
            <dt>AI tone</dt><dd><?php echo e(ucfirst($bot['ai_tone'])); ?></dd>
            <dt>Max answer length</dt><dd><?php echo (int) $bot['ai_max_length']; ?> characters</dd>
            <dt>Fallback message</dt><dd><?php echo $bot['fallback_message'] ? e($bot['fallback_message']) : '<span class="muted">Default</span>'; ?></dd>
            <dt>Created</dt><dd><?php echo e(format_date($bot['created_at'])); ?></dd>
        </dl>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Test this bot</h2>
            <span class="muted small">Messages are sent as a sandbox contact</span>
        </div>
        <div id="simulator-chat" class="chat">
            <?php if (!empty($simulation)): ?>
                <div class="bubble bubble-inbound"><?php echo e($simulation['message']); ?></div>
                <?php foreach ($simulation['replies'] as $reply): ?>
                    <div class="bubble bubble-outbound"><?php echo e($reply['content']); ?>
                        <?php if (!empty($reply['media_url'])): ?><div class="bubble-meta">Attachment: <?php echo e($reply['media_url']); ?></div><?php endif; ?>
                        <?php if (!empty($reply['quick_replies'])): ?>
                            <div class="quick-replies"><?php foreach ($reply['quick_replies'] as $q): ?><button type="button" class="quick-reply" data-reply="<?php echo e($q); ?>"><?php echo e($q); ?></button><?php endforeach; ?></div>
                        <?php endif; ?>
                        <div class="bubble-meta"><?php echo e($reply['triggered_by']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted small">Type a message such as "hi" to see how the bot replies.</p>
            <?php endif; ?>
        </div>
        <form id="simulator" method="POST" action="/bots/<?php echo (int) $bot['id']; ?>/simulate" data-url="/bots/<?php echo (int) $bot['id']; ?>/simulate" class="mt">
            <?php echo csrf_field(); ?>
            <div class="composer">
                <input type="text" name="message" maxlength="1000" placeholder="Type a test message..." required autocomplete="off">
                <button type="submit" class="btn btn-primary">Send</button>
            </div>
            <label class="checkbox small mt"><input type="checkbox" name="reset" value="1"> Start a fresh conversation</label>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Flows</h2>
        <a href="/bots/<?php echo (int) $bot['id']; ?>/flows/create" class="btn btn-small">Add flow</a>
    </div>
    <?php if (empty($flows)): ?>
        <p class="muted">No flows yet. Without flows the bot answers with AI or the fallback message.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Trigger</th><th>Value</th><th>Priority</th><th>Steps</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($flows as $flow): ?>
            <tr>
                <td><strong><?php echo e($flow['name']); ?></strong></td>
                <td><?php echo e(str_replace('_', ' ', $flow['trigger_type'])); ?></td>
                <td><?php echo $flow['trigger_value'] !== null ? '<code>' . e($flow['trigger_value']) . '</code>' : '<span class="muted">-</span>'; ?></td>
                <td><?php echo (int) $flow['priority']; ?></td>
                <td><?php echo (int) $flow['step_count']; ?></td>
                <td><?php echo badge($flow['status']); ?></td>
                <td class="actions">
                    <a href="/flows/<?php echo (int) $flow['id']; ?>/steps" class="btn btn-small">Steps</a>
                    <a href="/flows/<?php echo (int) $flow['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
