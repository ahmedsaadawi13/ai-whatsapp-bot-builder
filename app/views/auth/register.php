<!-- FILE: /app/views/auth/register.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AI WhatsApp Bot Builder</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <h1>Register</h1>
            <p>Create your account and start building WhatsApp bots.</p>

            <?php
            $session = new Session();
            $error = $session->getFlash('error');
            if ($error):
            ?>
            <div class="alert alert-error"><?php echo View::e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="/register" class="auth-form">
                <?php echo CSRF::field(); ?>

                <div class="form-group">
                    <label for="tenant_name">Business/Agency Name</label>
                    <input type="text" id="tenant_name" name="tenant_name" required>
                </div>

                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required>
                </div>

                <div class="form-group">
                    <label for="plan_id">Select Plan</label>
                    <select id="plan_id" name="plan_id">
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>">
                            <?php echo View::e($plan['name']); ?> - $<?php echo View::e($plan['price']); ?>/<?php echo View::e($plan['billing_period']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </form>

            <p class="auth-link">
                Already have an account? <a href="/login">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>
