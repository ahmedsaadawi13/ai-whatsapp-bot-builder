<?php // FILE: /app/views/emails/quota.php ?>
<h2 style="margin-top:0;"><?php echo $exceeded ? 'Plan limit reached' : 'Plan limit almost reached'; ?></h2>
<p>Hi <?php echo e($user['name']); ?>,</p>
<p>Your workspace is using <strong><?php echo e($used); ?> of <?php echo e($limit); ?></strong> <?php echo e($label); ?>
   (<?php echo percent($used, $limit); ?>%).</p>
<?php if ($exceeded): ?>
<p>New <?php echo e($label); ?> are blocked until you upgrade your plan or the limit resets.</p>
<?php else: ?>
<p>Consider upgrading your plan to avoid interruptions.</p>
<?php endif; ?>
<p>You can change your plan under <em>Subscription</em> in your dashboard.</p>
