<?php // FILE: /app/views/auth/register.php ?>
<?php
$session = new Session();
$error = $session->getFlash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AI WhatsApp Bot Builder</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <h1>Create your workspace</h1>
        <p class="muted">Start building WhatsApp bots for your business or agency.</p>

        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

        <form method="POST" action="/register">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="tenant_name">Business / agency name</label>
                <input type="text" id="tenant_name" name="tenant_name" value="<?php echo e(old('tenant_name')); ?>" required maxlength="255">
                <?php echo field_error('tenant_name'); ?>
            </div>
            <div class="form-group">
                <label for="name">Your name</label>
                <input type="text" id="name" name="name" value="<?php echo e(old('name')); ?>" required maxlength="255">
                <?php echo field_error('name'); ?>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required maxlength="255">
                <?php echo field_error('email'); ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <?php echo field_error('password'); ?>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
                </div>
            </div>
            <div class="form-group">
                <label for="plan_id">Plan</label>
                <select id="plan_id" name="plan_id" required>
                    <?php foreach ($plans as $plan): ?>
                    <option value="<?php echo (int) $plan['id']; ?>"<?php echo selected(old('plan_id', 1), $plan['id']); ?>>
                        <?php echo e($plan['name']); ?> - $<?php echo e($plan['price']); ?>/<?php echo e($plan['billing_period']); ?>
                        (<?php echo (int) $plan['max_bots']; ?> bots, <?php echo (int) $plan['max_channels']; ?> channels)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php echo field_error('plan_id'); ?>
                <div class="help">Your first invoice is created automatically; payment is simulated.</div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>

        <p class="auth-link">Already have an account? <a href="/login">Login</a></p>
    </div>
</div>
</body>
</html>
