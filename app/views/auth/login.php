<!-- FILE: /app/views/auth/login.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AI WhatsApp Bot Builder</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <h1>Login</h1>
            <p>Welcome back! Please login to your account.</p>

            <?php
            $session = new Session();
            $error = $session->getFlash('error');
            if ($error):
            ?>
            <div class="alert alert-error"><?php echo View::e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="/login" class="auth-form">
                <?php echo CSRF::field(); ?>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <p class="auth-link">
                Don't have an account? <a href="/register">Register here</a>
            </p>

            <p class="demo-credentials">
                <strong>Demo Credentials:</strong><br>
                Email: john@techcorp.com<br>
                Password: password
            </p>
        </div>
    </div>
</body>
</html>
