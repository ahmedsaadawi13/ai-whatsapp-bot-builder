<?php // FILE: /app/views/contacts/edit.php ?>
<div class="page-header"><h1>Edit Contact</h1><div class="actions"><a class="btn btn-secondary" href="/contacts/<?php echo (int) $contact['id']; ?>">Back</a></div></div>
<div class="card">
    <form method="POST" action="/contacts/<?php echo (int) $contact['id']; ?>/update">
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions"><button type="submit" class="btn">Save changes</button></div>
    </form>
</div>
