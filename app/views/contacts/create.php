<?php // FILE: /app/views/contacts/create.php ?>
<div class="page-header"><h1>Add Contact</h1><div class="actions"><a class="btn btn-secondary" href="/contacts">Back</a></div></div>
<div class="card">
    <form method="POST" action="/contacts/store">
        <?php require __DIR__ . '/_form.php'; ?>
        <div class="form-actions"><button type="submit" class="btn">Save contact</button></div>
    </form>
</div>
