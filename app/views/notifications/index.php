<?php // FILE: /app/views/notifications/index.php ?>
<div class="page-header">
    <h1>Email notifications</h1>
</div>

<div class="alert alert-info">
    Emails are simulated: they are recorded here and in <code>storage/logs/mail.log</code> instead of being sent.
    Types: welcome, plan limit warnings (80% / 100%), and daily summaries (<code>php cron/run.php daily</code>).
</div>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Subject or recipient">
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/notifications" class="btn btn-secondary">Reset</a>
</form>

<?php if (empty($notifications)): ?>
    <div class="empty-state"><p>No emails yet.</p></div>
<?php else: ?>
    <?php foreach ($notifications as $n): ?>
    <details class="card">
        <summary style="cursor:pointer;">
            <strong><?php echo e($n['subject']); ?></strong>
            <?php echo badge(str_replace('_', ' ', $n['type'])); ?>
            <span class="muted small">to <?php echo e($n['recipient']); ?> &middot; <?php echo e(format_date($n['created_at'])); ?></span>
        </summary>
        <iframe sandbox title="Email preview" srcdoc="<?php echo e($n['body']); ?>" style="width:100%;height:420px;border:1px solid #e5e7eb;border-radius:8px;margin-top:12px;background:#fff;"></iframe>
    </details>
    <?php endforeach; ?>
<?php endif; ?>
<?php partial('pagination', ['pagination' => $pagination]); ?>
