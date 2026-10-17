<?php // FILE: /app/views/contacts/show.php ?>
<div class="page-header">
    <h1><?php echo e($contact['name'] ?: $contact['phone_number']); ?></h1>
    <div class="actions">
        <a class="btn btn-secondary" href="/contacts">Back</a>
        <a class="btn btn-secondary" href="/contacts/<?php echo (int) $contact['id']; ?>/edit">Edit</a>
        <?php if ((new Auth())->hasRole('tenant_admin')): ?>
            <?php echo post_button('/contacts/' . (int) $contact['id'] . '/delete', 'Delete', 'btn btn-danger', 'Delete this contact and all of its conversations?'); ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Details</h2>
        <dl class="detail-list">
            <dt>Phone</dt><dd><?php echo e($contact['phone_number']); ?></dd>
            <dt>Email</dt><dd><?php echo e($contact['email'] ?: '-'); ?></dd>
            <dt>Status</dt><dd><?php echo badge($contact['status']); ?></dd>
            <dt>Tags</dt><dd><?php if (empty($contactTags)): ?>-<?php endif; ?><?php foreach ($contactTags as $t): ?><span class="tag"><?php echo e($t['name']); ?></span><?php endforeach; ?></dd>
            <dt>Last contact</dt><dd><?php echo e(format_date($contact['last_contact_at'])); ?></dd>
            <dt>Created</dt><dd><?php echo e(format_date($contact['created_at'])); ?></dd>
        </dl>
        <?php if (!empty($contact['notes'])): ?>
            <h3 class="mt">Notes</h3>
            <p><?php echo nl2br(e($contact['notes'])); ?></p>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Custom fields</h2>
        <?php if (empty($customFields)): ?>
            <p class="muted">No custom fields.</p>
        <?php else: ?>
            <dl class="detail-list">
                <?php foreach ($customFields as $k => $v): ?>
                    <dt><?php echo e($k); ?></dt><dd><?php echo e(is_scalar($v) ? $v : json_encode($v)); ?></dd>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Conversations</h2>
    <?php if (empty($conversations)): ?>
        <p class="muted">No conversations yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>#</th><th>Channel</th><th>Bot</th><th>Last message</th><th>Status</th><th>Updated</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($conversations as $cv): ?>
                <tr>
                    <td><?php echo (int) $cv['id']; ?></td>
                    <td><?php echo e($cv['channel_name']); ?></td>
                    <td><?php echo e($cv['bot_name'] ?: '-'); ?></td>
                    <td><?php echo e(mb_strimwidth((string) $cv['last_message'], 0, 80, '...', 'UTF-8')); ?></td>
                    <td><?php echo badge($cv['status']); ?></td>
                    <td class="nowrap"><?php echo e(format_date($cv['last_message_at'])); ?></td>
                    <td><a class="btn btn-small btn-secondary" href="/conversations/<?php echo (int) $cv['id']; ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
