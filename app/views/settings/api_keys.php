<?php // FILE: /app/views/settings/api_keys.php ?>
<?php
$exampleBot = !empty($bots) ? (int) $bots[0]['id'] : 1;
$exampleKey = $newKey ?: 'YOUR_API_KEY';
$exampleChannel = !empty($channels) ? $channels[0]['phone_number'] : '+15551234567';
$sendInbound = "curl -X POST " . $apiBase . "/send-message \\\n"
    . "  -H \"Content-Type: application/json\" \\\n"
    . "  -H \"X-API-KEY: " . $exampleKey . "\" \\\n"
    . "  -d '{\"bot_id\": " . $exampleBot . ", \"phone_number\": \"+15557001001\", \"message\": \"hi\", \"contact_name\": \"Alice\", \"metadata\": {\"source\": \"website\"}}'";
$sendOutbound = "curl -X POST " . $apiBase . "/send-message \\\n"
    . "  -H \"Content-Type: application/json\" \\\n"
    . "  -H \"X-API-KEY: " . $exampleKey . "\" \\\n"
    . "  -d '{\"bot_id\": " . $exampleBot . ", \"phone_number\": \"+15557001001\", \"message\": \"Your order has shipped!\", \"direction\": \"outbound\"}'";
$listBots = "curl " . $apiBase . "/bots -H \"X-API-KEY: " . $exampleKey . "\"";
$getConversation = "curl " . $apiBase . "/conversations/123 -H \"X-API-KEY: " . $exampleKey . "\"";
$webhook = "curl -X POST " . $apiBase . "/webhook \\\n"
    . "  -H \"Content-Type: application/json\" \\\n"
    . "  -H \"X-Webhook-Token: CHANNEL_VERIFY_TOKEN\" \\\n"
    . "  -d '{\"to_phone\": \"" . $exampleChannel . "\", \"from_phone\": \"+15557001001\", \"message\": \"hello\", \"message_id\": \"wamid.123\"}'";
$response = "{\n  \"success\": true,\n  \"conversation_id\": 42,\n  \"conversation_created\": false,\n  \"contact_id\": 7,\n  \"inbound_message_id\": 314,\n  \"bot_status\": \"active\",\n  \"handed_off_to_agent\": false,\n  \"messages\": [\n    {\"id\": 315, \"type\": \"interactive\", \"content\": \"Welcome! How can we help?\", \"media_url\": null,\n     \"quick_replies\": [\"Support\", \"Sales\"], \"triggered_by\": \"flow\"}\n  ]\n}";
?>
<div class="page-header">
    <h1>API keys</h1>
    <div class="actions"><a href="/webhooks" class="btn btn-secondary">Outgoing webhooks</a></div>
</div>

<?php if ($newKey): ?>
<div class="alert alert-warning">
    <strong>Your new API key</strong> - copy it now, it will not be shown again:
    <div class="mt"><code><?php echo e($newKey); ?></code>
        <button type="button" class="btn btn-small btn-secondary" data-copy="<?php echo e($newKey); ?>">Copy</button></div>
</div>
<?php endif; ?>

<div class="grid grid-2">
    <form method="POST" action="/settings/generate-api-key" class="card">
        <?php echo csrf_field(); ?>
        <h2>Create a key</h2>
        <div class="form-group">
            <label for="name">Key name</label>
            <input type="text" id="name" name="name" value="<?php echo e(old('name')); ?>" required maxlength="100" placeholder="e.g. Website contact form">
            <?php echo field_error('name'); ?>
        </div>
        <div class="form-actions"><button type="submit" class="btn">Generate API key</button></div>
        <p class="help">Keys are stored as SHA-256 hashes. Send them in the <code>X-API-KEY</code> header (or <code>Authorization: Bearer</code>).</p>
    </form>

    <div class="card">
        <h2>Your bots &amp; channels</h2>
        <?php if (empty($bots)): ?>
            <p class="muted">No bots yet. <a href="/bots/create">Create one</a> to use the API.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>bot_id</th><th>Bot</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($bots as $b): ?>
                    <tr><td><code><?php echo (int) $b['id']; ?></code></td><td><?php echo e($b['name']); ?></td><td><?php echo badge($b['status']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if (!empty($channels)): ?>
            <h3 class="mt">Channel numbers (to_phone)</h3>
            <ul style="margin-left:18px;">
                <?php foreach ($channels as $c): ?>
                    <li><code><?php echo e($c['phone_number']); ?></code> - <?php echo e($c['name']); ?> <?php echo badge($c['status']); ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="help">Verify tokens for the webhook receiver are shown on each <a href="/channels">channel</a>.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Keys</h2>
    <?php if (empty($apiKeys)): ?>
        <p class="muted">No API keys yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Prefix</th><th>Status</th><th>Last used</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($apiKeys as $k): ?>
                <tr>
                    <td><?php echo e($k['name']); ?></td>
                    <td><code><?php echo e($k['key_prefix']); ?>&hellip;</code></td>
                    <td><?php echo badge($k['status']); ?></td>
                    <td class="nowrap"><?php echo e(format_date($k['last_used_at'])); ?></td>
                    <td class="nowrap"><?php echo e(format_date($k['created_at'])); ?></td>
                    <td><?php if ($k['status'] === 'active'): ?>
                        <?php echo post_button('/settings/revoke-api-key', 'Revoke', 'btn btn-small btn-danger', 'Revoke this key? Integrations using it will stop working.', ['key_id' => $k['id']]); ?>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>API reference</h2>
    <p>Base URL: <code><?php echo e($apiBase); ?></code>. All requests and responses are JSON. Errors return
       <code>{"error": "...", "details": {...}}</code> with status 400, 401, 404, 409, 415, 422 or 429 (plan limit reached).</p>

    <h3 class="mt">Trigger a bot conversation (contact writes to the bot)</h3>
    <p class="help">Creates/updates the contact, opens a conversation, runs flows + AI and returns the bot's replies.</p>
    <pre><?php echo e($sendInbound); ?></pre>
    <p class="help">Example response:</p>
    <pre><?php echo e($response); ?></pre>

    <h3 class="mt">Send a message to a contact (business writes)</h3>
    <p class="help"><code>"direction": "outbound"</code> skips the bot and stores an outbound message (HTTP 201).</p>
    <pre><?php echo e($sendOutbound); ?></pre>

    <h3 class="mt">List bots</h3>
    <pre><?php echo e($listBots); ?></pre>

    <h3 class="mt">Get a conversation with its messages and variables</h3>
    <pre><?php echo e($getConversation); ?></pre>

    <h3 class="mt">Incoming WhatsApp webhook (simulated provider)</h3>
    <p class="help">Your provider posts here; the channel is identified by <code>to_phone</code> and authenticated with its verify token.
       Verification: <code>GET <?php echo e($apiBase); ?>/webhook?hub.mode=subscribe&amp;hub.verify_token=TOKEN&amp;hub.challenge=123</code> echoes the challenge.</p>
    <pre><?php echo e($webhook); ?></pre>
</div>
