<?php // FILE: /app/views/conversations/show.php ?>
<?php $cid = (int) $conversation['id']; ?>
<div class="page-header">
    <h1><?php echo e($conversation['contact_name'] ?: $conversation['phone_number']); ?> <?php echo badge($conversation['status']); ?></h1>
    <div class="actions">
        <a class="btn btn-secondary" href="/inbox">Back to inbox</a>
        <?php if ($conversation['status'] !== 'closed'): ?>
            <?php echo post_button('/conversations/' . $cid . '/close', 'Close conversation', 'btn btn-secondary', null); ?>
        <?php else: ?>
            <?php echo post_button('/conversations/' . $cid . '/reopen', 'Reopen', 'btn btn-secondary', null); ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2" style="grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);">
    <div>
        <div class="chat" data-autoscroll>
            <?php if (empty($messages)): ?>
                <p class="muted">No messages yet.</p>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <?php $meta = json_array($m['metadata']); ?>
                <div class="bubble bubble-<?php echo $m['direction'] === 'inbound' ? 'inbound' : 'outbound'; ?>">
                    <?php echo e($m['content']); ?>
                    <?php if (!empty($m['media_url'])): ?>
                        <div class="bubble-meta">Attachment: <a href="<?php echo e(safe_url($m['media_url'])); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($m['media_url']); ?></a></div>
                    <?php endif; ?>
                    <?php if (!empty($meta['quick_replies']) && is_array($meta['quick_replies'])): ?>
                        <div class="quick-replies">
                            <?php foreach ($meta['quick_replies'] as $q): ?><span class="quick-reply"><?php echo e(is_scalar($q) ? $q : ''); ?></span><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="bubble-meta">
                        <?php echo e(format_date($m['created_at'])); ?>
                        <?php if ($m['triggered_by']): ?> &middot; <?php echo badge($m['triggered_by']); ?><?php endif; ?>
                        <?php if ($m['sent_by_name']): ?> &middot; <?php echo e($m['sent_by_name']); ?><?php endif; ?>
                        <?php if ($m['flow_name']): ?> &middot; flow: <?php echo e($m['flow_name']); ?><?php endif; ?>
                        <?php if ($m['type'] !== 'text'): ?> &middot; <?php echo e($m['type']); ?><?php endif; ?>
                        <?php if ($m['direction'] === 'outbound'): ?> &middot; <?php echo e($m['status']); ?><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="/conversations/<?php echo $cid; ?>/send-message" id="composer" class="card mt">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="message">Reply as agent</label>
                <textarea id="message" name="message" placeholder="Type your reply..."><?php echo e(old('message')); ?></textarea>
                <?php echo field_error('message'); ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="template_id">Or send a template</label>
                    <select id="template_id" name="template_id">
                        <option value="">-- Free text --</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?php echo (int) $t['id']; ?>"><?php echo e($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help">Placeholders are filled from the contact and conversation variables.</div>
                    <?php echo field_error('template_id'); ?>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Send</button>
                <button type="button" class="btn btn-secondary" data-rewrite-url="/conversations/<?php echo $cid; ?>/rewrite" data-target="message">Improve with AI</button>
            </div>
        </form>
    </div>

    <div>
        <div class="card">
            <h2>Contact</h2>
            <dl class="detail-list">
                <dt>Name</dt><dd><a href="/contacts/<?php echo (int) $conversation['contact_id']; ?>"><?php echo e($conversation['contact_name'] ?: '(no name)'); ?></a></dd>
                <dt>Phone</dt><dd><?php echo e($conversation['phone_number']); ?></dd>
                <dt>Email</dt><dd><?php echo e($conversation['email'] ?: '-'); ?></dd>
                <dt>Channel</dt><dd><?php echo e($conversation['channel_name']); ?><div class="muted small"><?php echo e($conversation['channel_phone']); ?></div></dd>
                <dt>Bot</dt><dd><?php echo e($conversation['bot_name'] ?: '-'); ?></dd>
                <dt>Started</dt><dd><?php echo e(format_date($conversation['created_at'])); ?></dd>
            </dl>
        </div>

        <div class="card">
            <h2>Status</h2>
            <form method="POST" action="/conversations/<?php echo $cid; ?>/status">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <select name="status">
                        <?php foreach (Conversation::STATUSES as $s): ?>
                            <option value="<?php echo e($s); ?>"<?php echo selected($s, $conversation['status']); ?>><?php echo e(ucfirst($s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">Update status</button>
            </form>
        </div>

        <div class="card">
            <h2>Assignment</h2>
            <p class="muted small">While a conversation is assigned to a team member the bot stays silent (human takeover). Replying from here assigns it to you automatically.</p>
            <form method="POST" action="/conversations/<?php echo $cid; ?>/assign">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <select name="assigned_to">
                        <option value="0">Unassigned - bot replies</option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo (int) $agent['id']; ?>"<?php echo selected($agent['id'], $conversation['assigned_to']); ?>><?php echo e($agent['name']); ?> (<?php echo e(str_replace('_', ' ', $agent['role'])); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php echo field_error('assigned_to'); ?>
                </div>
                <button type="submit" class="btn btn-secondary">Save assignment</button>
            </form>
        </div>

        <div class="card">
            <h2>Variables</h2>
            <?php if (empty($variables)): ?>
                <p class="muted">No variables collected yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <tbody>
                    <?php foreach ($variables as $k => $v): ?>
                        <tr><td><code><?php echo e($k); ?></code></td><td><?php echo e($v); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
