<?php // FILE: /app/views/subscription/plans.php ?>
<?php $currentPlanId = $subscription ? (int) $subscription['plan_id'] : 0; ?>
<div class="page-header">
    <h1>Plans</h1>
    <div class="actions"><a href="/subscription" class="btn btn-secondary">Back to subscription</a></div>
</div>

<p class="muted mb">Switching plans cancels the current subscription and starts a new period. Paid plans create a pending invoice.
   Downgrades are blocked while your usage is above the new plan's limits.</p>

<div class="plan-grid">
    <?php foreach ($plans as $plan): $isCurrent = (int) $plan['id'] === $currentPlanId; ?>
    <div class="plan-card<?php echo $isCurrent ? ' current' : ''; ?>">
        <h2><?php echo e($plan['name']); ?> <?php if ($isCurrent): ?><?php echo badge('active'); ?><?php endif; ?></h2>
        <p class="muted"><?php echo e($plan['description']); ?></p>
        <div class="plan-price">$<?php echo e($plan['price']); ?><span class="muted small"> / <?php echo e($plan['billing_period']); ?></span></div>
        <ul>
            <li><?php echo (int) $plan['max_channels']; ?> WhatsApp channels</li>
            <li><?php echo (int) $plan['max_bots']; ?> bots</li>
            <li><?php echo e(number_format((int) $plan['max_contacts'])); ?> contacts</li>
            <li><?php echo e(number_format((int) $plan['max_messages_per_month'])); ?> messages / month</li>
            <li><?php echo e(number_format((int) $plan['max_storage_mb'])); ?> MB storage</li>
        </ul>
        <?php if ($plan['features']): ?><p class="small muted"><?php echo e($plan['features']); ?></p><?php endif; ?>
        <?php if ($isCurrent): ?>
            <button type="button" class="btn btn-secondary btn-block" disabled>Current plan</button>
        <?php else: ?>
            <form method="POST" action="/subscription/change-plan" data-confirm="Switch to the <?php echo e($plan['name']); ?> plan?">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="plan_id" value="<?php echo (int) $plan['id']; ?>">
                <button type="submit" class="btn btn-block">Switch to <?php echo e($plan['name']); ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
