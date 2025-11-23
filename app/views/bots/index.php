<!-- FILE: /app/views/bots/index.php -->
<div class="page-header">
    <h1>Bots</h1>
    <a href="/bots/create" class="btn btn-primary">Create Bot</a>
</div>

<?php if (!empty($bots)): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Channel</th>
            <th>Status</th>
            <th>AI Enabled</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($bots as $bot): ?>
        <tr>
            <td>
                <strong><?php echo View::e($bot['name']); ?></strong><br>
                <small><?php echo View::e($bot['description']); ?></small>
            </td>
            <td><?php echo View::e($bot['channel_name']); ?> (<?php echo View::e($bot['phone_number']); ?>)</td>
            <td><span class="badge badge-<?php echo View::e($bot['status']); ?>"><?php echo View::e($bot['status']); ?></span></td>
            <td><?php echo $bot['ai_enabled'] ? 'Yes' : 'No'; ?></td>
            <td><?php echo View::e(date('M d, Y', strtotime($bot['created_at']))); ?></td>
            <td>
                <a href="/bots/<?php echo $bot['id']; ?>" class="btn btn-small">View</a>
                <a href="/bots/<?php echo $bot['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
                <a href="/bots/<?php echo $bot['id']; ?>/flows" class="btn btn-small btn-info">Flows</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($pagination['total_pages'] > 1): ?>
<div class="pagination">
    <?php if ($pagination['has_prev']): ?>
        <a href="?page=<?php echo $pagination['page'] - 1; ?>" class="btn btn-small">Previous</a>
    <?php endif; ?>

    <span>Page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?></span>

    <?php if ($pagination['has_next']): ?>
        <a href="?page=<?php echo $pagination['page'] + 1; ?>" class="btn btn-small">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state">
    <p>No bots found. Create your first bot to get started!</p>
    <a href="/bots/create" class="btn btn-primary">Create Bot</a>
</div>
<?php endif; ?>
