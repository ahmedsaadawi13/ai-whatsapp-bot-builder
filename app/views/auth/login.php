<?php // FILE: /app/views/auth/login.php ?>
<?php
$session = new Session();
$error = $session->getFlash('error');
$success = $session->getFlash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AI WhatsApp Bot Builder</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <h1>Login</h1>
        <p class="muted">Welcome back to AI WhatsApp Bot Builder.</p>

        <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>

        <form method="POST" action="/login">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <p class="auth-link">No account yet? <a href="/register">Create your workspace</a></p>

        <div class="demo-credentials">
            <strong>Demo accounts</strong> (password: <code>password</code>)
            <ul style="margin: 6px 0 0 18px;">
                <li>admin@platform.com - platform admin</li>
                <li>john@techcorp.com - tenant admin (TechCorp)</li>
                <li>sarah@techcorp.com - agent (TechCorp)</li>
                <li>mike@techcorp.com - developer (TechCorp)</li>
                <li>emma@marketingmasters.com - tenant admin (Marketing Masters)</li>
                <li>david@marketingmasters.com - agent (Marketing Masters)</li>
                <li>olivia@marketingmasters.com - developer (Marketing Masters)</li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>
