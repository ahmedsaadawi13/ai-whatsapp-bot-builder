<?php // FILE: /app/views/webhooks/create.php ?>
<div class="page-header"><h1>Add Webhook</h1><div class="actions"><a class="btn btn-secondary" href="/webhooks">Back</a></div></div>
<form method="POST" action="/webhooks/store" class="card">
    <?php require __DIR__ . '/_form.php'; ?>
    <p class="muted small">A signing secret is generated automatically and shown on the edit page.</p>
    <div class="form-actions"><button type="submit" class="btn">Create webhook</button></div>
</form>
