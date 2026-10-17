<?php // FILE: /app/views/contacts/index.php ?>
<div class="page-header">
    <h1>Contacts</h1>
    <div class="actions"><a class="btn" href="/contacts/create">Add contact</a></div>
</div>

<form method="GET" action="/contacts" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" placeholder="Name, phone or email" value="<?php echo e($filters['search']); ?>">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?php echo e($s); ?>"<?php echo selected($s, $filters['status']); ?>><?php echo e(ucfirst($s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="tag_id">Tag</label>
        <select id="tag_id" name="tag_id">
            <option value="">All tags</option>
            <?php foreach ($tags as $tag): ?>
                <option value="<?php echo (int) $tag['id']; ?>"<?php echo selected($tag['id'], $filters['tag_id']); ?>><?php echo e($tag['name']); ?> (<?php echo (int) $tag['contact_count']; ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/contacts" class="btn btn-secondary">Reset</a>
</form>

<?php if (!empty($contacts)): ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Contact</th><th>Phone</th><th>Email</th><th>Tags</th><th>Status</th><th>Last contact</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($contacts as $c): ?>
            <tr>
                <td><a href="/contacts/<?php echo (int) $c['id']; ?>"><strong><?php echo e($c['name'] ?: '(no name)'); ?></strong></a></td>
                <td class="nowrap"><?php echo e($c['phone_number']); ?></td>
                <td><?php echo e($c['email'] ?: '-'); ?></td>
                <td>
                    <?php foreach (Tag::parseList($c['tags']) as $t): ?><span class="tag"><?php echo e($t); ?></span><?php endforeach; ?>
                </td>
                <td><?php echo badge($c['status']); ?></td>
                <td class="nowrap"><?php echo e(format_date($c['last_contact_at'])); ?></td>
                <td class="actions">
                    <a class="btn btn-small btn-secondary" href="/contacts/<?php echo (int) $c['id']; ?>">View</a>
                    <a class="btn btn-small btn-secondary" href="/contacts/<?php echo (int) $c['id']; ?>/edit">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php partial('pagination', ['pagination' => $pagination]); ?>
<?php else: ?>
<div class="empty-state">
    <p>No contacts match your filters.</p>
    <a class="btn" href="/contacts/create">Add contact</a>
</div>
<?php endif; ?>

<div class="card mt">
    <h2>Import contacts from CSV</h2>
    <p class="muted">Columns: <code>phone_number,name,email,tags</code> &mdash; tags separated by <code>|</code> (e.g. <code>VIP|Lead</code>). A header row is detected automatically; existing phone numbers are updated. Plan contact limits apply.</p>
    <form method="POST" action="/contacts/import" enctype="multipart/form-data" class="form-row">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <input type="file" name="csv_file" accept=".csv,text/csv" required>
        </div>
        <div class="form-group"><button type="submit" class="btn btn-secondary">Import CSV</button></div>
    </form>
</div>
