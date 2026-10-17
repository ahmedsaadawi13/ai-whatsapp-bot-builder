<?php // FILE: /app/views/broadcasts/show.php ?>
<?php $bid = (int) $broadcast['id']; $s = $broadcast['stats']; ?>
<div class="page-header">
    <h1><?php echo e($broadcast['name']); ?> <?php echo badge($broadcast['status']); ?></h1>
    <div class="actions">
        <a class="btn btn-secondary" href="/broadcasts">Back</a>
        <?php if (in_array($broadcast['status'], ['draft', 'scheduled'], true)): ?>
            <?php echo post_button('/broadcasts/' . $bid . '/send', 'Send now', 'btn', 'Send this broadcast to ' . (int) $segmentSize . ' contacts now?'); ?>
            <?php echo post_button('/broadcasts/' . $bid . '/cancel', 'Cancel', 'btn btn-secondary', 'Cancel this broadcast?'); ?>
        <?php endif; ?>
        <?php if ($broadcast['status'] !== 'running'): ?>
            <?php echo post_button('/broadcasts/' . $bid . '/delete', 'Delete', 'btn btn-danger', 'Delete this broadcast and its delivery log?'); ?>
        <?php endif; ?>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><h3>Recipients</h3><div class="stat-number"><?php echo (int) $s['total']; ?></div></div>
    <div class="stat-card"><h3>Sent</h3><div class="stat-number"><?php echo (int) $s['sent']; ?></div></div>
    <div class="stat-card"><h3>Failed</h3><div class="stat-number"><?php echo (int) $s['failed']; ?></div></div>
    <div class="stat-card"><h3>Delivery rate</h3><div class="stat-number"><?php echo $s['total'] > 0 ? round($s['sent'] / $s['total'] * 100, 1) : 0; ?>%</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Details</h2>
        <dl class="detail-list">
            <dt>Channel</dt><dd><?php echo e($broadcast['channel_name']); ?></dd>
            <dt>Bot</dt><dd><?php echo e($broadcast['bot_name'] ?: '-'); ?></dd>
            <dt>Template</dt><dd><?php echo e($broadcast['template_name'] ?: 'Custom message'); ?></dd>
            <dt>Audience</dt><dd><?php echo e($segmentLabel); ?> (<?php echo (int) $segmentSize; ?> currently eligible)</dd>
            <dt>Scheduled</dt><dd><?php echo e(format_date($broadcast['scheduled_at'])); ?> UTC</dd>
            <dt>Started</dt><dd><?php echo e(format_date($broadcast['started_at'])); ?></dd>
            <dt>Completed</dt><dd><?php echo e(format_date($broadcast['completed_at'])); ?></dd>
        </dl>
        <p class="muted small mt">Times are stored in UTC; your account timezone is <?php echo e($timezone); ?>.</p>
    </div>
    <div class="card">
        <h2>Preview (first recipient)</h2>
        <?php if ($preview !== null && $preview !== ''): ?>
            <div class="chat"><div class="bubble bubble-outbound"><?php echo e($preview); ?></div></div>
        <?php else: ?>
            <p class="muted">No eligible contact to preview with.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Recipients</h2>
        <form method="GET" class="inline-form">
            <select name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach (['pending', 'sent', 'failed'] as $st): ?>
                    <option value="<?php echo e($st); ?>"<?php echo selected($st, $statusFilter); ?>><?php echo e(ucfirst($st)); ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-small btn-secondary">Filter</button></noscript>
        </form>
    </div>
    <?php if (empty($recipients)): ?>
        <p class="muted">No recipients<?php echo $broadcast['status'] === 'draft' || $broadcast['status'] === 'scheduled' ? ' yet - they are added when the broadcast is sent' : ''; ?>.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Contact</th><th>Phone</th><th>Status</th><th>Sent at</th><th>Error</th></tr></thead>
            <tbody>
            <?php foreach ($recipients as $r): ?>
                <tr>
                    <td><a href="/contacts/<?php echo (int) $r['contact_id']; ?>"><?php echo e($r['contact_name'] ?: '(no name)'); ?></a></td>
                    <td><?php echo e($r['phone_number']); ?></td>
                    <td><?php echo badge($r['status']); ?></td>
                    <td class="nowrap"><?php echo e(format_date($r['sent_at'])); ?></td>
                    <td><?php echo e($r['error_message'] ?: ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php partial('pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
</div>
