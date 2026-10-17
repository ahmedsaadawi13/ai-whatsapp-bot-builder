<?php // FILE: /app/views/emails/daily_summary.php ?>
<h2 style="margin-top:0;">Daily summary for <?php echo e($tenant['name']); ?></h2>
<p>Hi <?php echo e($user['name']); ?>, here is what happened in the last 24 hours:</p>
<table style="width:100%;border-collapse:collapse;">
  <tr><td style="padding:6px 0;">New conversations</td><td style="text-align:right;"><strong><?php echo (int) $newConversations; ?></strong></td></tr>
  <tr><td style="padding:6px 0;">Messages received</td><td style="text-align:right;"><strong><?php echo (int) $stats['inbound_count']; ?></strong></td></tr>
  <tr><td style="padding:6px 0;">Messages sent</td><td style="text-align:right;"><strong><?php echo (int) $stats['outbound_count']; ?></strong></td></tr>
  <tr><td style="padding:6px 0;">Answered by AI</td><td style="text-align:right;"><?php echo (int) $stats['ai_count']; ?></td></tr>
  <tr><td style="padding:6px 0;">Answered by flows</td><td style="text-align:right;"><?php echo (int) $stats['flow_count']; ?></td></tr>
  <tr><td style="padding:6px 0;">Answered by agents</td><td style="text-align:right;"><?php echo (int) $stats['agent_count']; ?></td></tr>
  <tr><td style="padding:6px 0;">Conversations still open</td><td style="text-align:right;"><?php echo (int) $open['open'] + (int) $open['pending']; ?></td></tr>
</table>
