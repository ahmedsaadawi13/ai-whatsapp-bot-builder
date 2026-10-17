<?php // FILE: /app/views/templates/index.php ?>
<div class="page-header">
    <h1>Message Templates</h1>
    <div class="actions"><a href="/templates/create" class="btn btn-primary">Create template</a></div>
</div>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Name or text">
    </div>
    <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="">All</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?php echo e($c); ?>"<?php echo selected($filters['category'], $c); ?>><?php echo e(ucfirst($c)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['active', 'inactive'] as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected($filters['status'], $s); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/templates" class="btn btn-secondary">Reset</a>
</form>

<?php if (empty($templates)): ?>
<div class="empty-state">
    <p>No templates found. Templates are reusable messages for broadcasts and agent replies.</p>
    <a href="/templates/create" class="btn btn-primary">Create template</a>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>Name</th><th>Category</th><th>Language</th><th>Body</th><th>Variables</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($templates as $template): ?>
        <tr>
            <td><strong><?php echo e($template['name']); ?></strong></td>
            <td><?php echo e(ucfirst($template['category'])); ?></td>
            <td><?php echo e($template['language']); ?></td>
            <td class="small"><?php echo e(mb_strimwidth($template['body'], 0, 120, '...', 'UTF-8')); ?></td>
            <td><?php foreach (json_array($template['variables']) as $var): ?><span class="tag">{{<?php echo e($var); ?>}}</span><?php endforeach; ?></td>
            <td><?php echo badge($template['status']); ?></td>
            <td class="actions">
                <a href="/templates/<?php echo (int) $template['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
                <?php echo post_button('/templates/' . (int) $template['id'] . '/delete', 'Delete', 'btn btn-small btn-danger', 'Delete this template?'); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php partial('pagination', ['pagination' => $pagination]); ?>
