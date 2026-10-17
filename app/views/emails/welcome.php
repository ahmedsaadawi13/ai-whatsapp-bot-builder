<?php // FILE: /app/views/emails/welcome.php ?>
<h2 style="margin-top:0;">Welcome, <?php echo e($user['name']); ?>!</h2>
<p>Your workspace <strong><?php echo e($tenant['name']); ?></strong> is ready.</p>
<p>Next steps:</p>
<ol>
  <li>Connect a WhatsApp channel (a sandbox number works for testing).</li>
  <li>Create a bot and add a welcome flow.</li>
  <li>Add knowledge base answers so the AI can reply to open questions.</li>
  <li>Generate an API key to send messages from your website or CRM.</li>
</ol>
<p>Happy building!</p>
