<?php // FILE: /app/views/knowledge_base/index.php ?>
<div class="page-header">
    <h1>Knowledge Base</h1>
    <div class="actions"><a href="/knowledge-base/create" class="btn btn-primary">Add Q&amp;A</a></div>
</div>

<div class="card">
    <h3>Upload a document</h3>
    <p class="muted small">The text of the document is extracted and used by the AI to answer questions. Allowed: txt, pdf, doc, docx (max 10 MB).</p>
    <form method="POST" action="/knowledge-base/upload" enctype="multipart/form-data" class="form-row">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label for="document">Document</label>
            <input type="file" id="document" name="document" accept=".txt,.pdf,.doc,.docx" required>
            <?php echo field_error('document'); ?>
        </div>
        <div class="form-group">
            <label for="upload_topic">Topic</label>
            <input type="text" id="upload_topic" name="topic" maxlength="255" placeholder="e.g. Shipping policy">
        </div>
        <div class="form-group">
            <label for="upload_bot">Bot</label>
            <select id="upload_bot" name="bot_id">
                <option value="">All bots</option>
                <?php foreach ($bots as $b): ?>
                    <option value="<?php echo (int) $b['id']; ?>"><?php echo e($b['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php echo field_error('bot_id'); ?>
        </div>
        <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn">Upload &amp; index</button></div>
    </form>
</div>

<form method="GET" class="filters">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Topic, question, keyword">
    </div>
    <div class="form-group">
        <label for="bot_id">Bot</label>
        <select id="bot_id" name="bot_id">
            <option value="">All</option>
            <?php foreach ($bots as $b): ?>
                <option value="<?php echo (int) $b['id']; ?>"<?php echo selected($filters['bot_id'], $b['id']); ?>><?php echo e($b['name']); ?></option>
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
    <a href="/knowledge-base" class="btn btn-secondary">Reset</a>
</form>

<?php if (empty($entries)): ?>
<div class="empty-state">
    <p>No knowledge base entries found. Add questions and answers so the AI can reply to open questions.</p>
    <a href="/knowledge-base/create" class="btn btn-primary">Add Q&amp;A</a>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="data-table">
    <thead><tr><th>Topic</th><th>Question / answer</th><th>Keywords</th><th>Bot</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($entries as $entry): ?>
        <tr>
            <td><strong><?php echo e($entry['topic']); ?></strong>
                <?php if ($entry['category']): ?><div class="muted small"><?php echo e($entry['category']); ?></div><?php endif; ?>
                <?php if ($entry['file_path']): ?><div class="small"><a href="/files?path=<?php echo e(rawurlencode($entry['file_path'])); ?>">Source document</a></div><?php endif; ?></td>
            <td><div><?php echo e($entry['question']); ?></div>
                <div class="muted small"><?php echo e(mb_strimwidth($entry['answer'], 0, 160, '...', 'UTF-8')); ?></div></td>
            <td><?php foreach (Tag::parseList($entry['keywords']) as $kw): ?><span class="tag"><?php echo e($kw); ?></span><?php endforeach; ?></td>
            <td><?php echo $entry['bot_name'] ? e($entry['bot_name']) : '<span class="muted">All bots</span>'; ?></td>
            <td><?php echo badge($entry['status']); ?></td>
            <td class="actions">
                <a href="/knowledge-base/<?php echo (int) $entry['id']; ?>/edit" class="btn btn-small btn-secondary">Edit</a>
                <?php echo post_button('/knowledge-base/' . (int) $entry['id'] . '/delete', 'Delete', 'btn btn-small btn-danger', 'Delete this entry?'); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php partial('pagination', ['pagination' => $pagination]); ?>
