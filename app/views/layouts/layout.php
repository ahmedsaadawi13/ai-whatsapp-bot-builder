<?php
// FILE: /app/views/layouts/layout.php
$user = isset($user) ? $user : (new Auth())->user();
$session = new Session();
$flash = $session->getAllFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? View::e($title) . ' - ' : ''; ?>AI WhatsApp Bot Builder</title>
    <?php echo CSRF::meta(); ?>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php if ($user): ?>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/dashboard">AI WhatsApp Bot Builder</a>
            </div>
            <ul class="nav-menu">
                <li><a href="/dashboard">Dashboard</a></li>
                <?php if ($user['role'] !== 'platform_admin'): ?>
                <li><a href="/bots">Bots</a></li>
                <li><a href="/channels">Channels</a></li>
                <li><a href="/inbox">Inbox</a></li>
                <li><a href="/contacts">Contacts</a></li>
                <li><a href="/knowledge-base">Knowledge Base</a></li>
                <li><a href="/templates">Templates</a></li>
                <li><a href="/broadcasts">Broadcasts</a></li>
                <li><a href="/analytics">Analytics</a></li>
                <?php if (in_array($user['role'], ['tenant_admin', 'developer'])): ?>
                <li><a href="/webhooks">Webhooks</a></li>
                <li><a href="/settings/api-keys">API</a></li>
                <?php endif; ?>
                <?php if ($user['role'] === 'tenant_admin'): ?>
                <li><a href="/users">Users</a></li>
                <li><a href="/subscription">Subscription</a></li>
                <li><a href="/billing">Billing</a></li>
                <?php endif; ?>
                <li><a href="/settings">Settings</a></li>
                <?php else: ?>
                <li><a href="/tenants">Tenants</a></li>
                <?php endif; ?>
                <li class="user-menu">
                    <span><?php echo View::e($user['name']); ?></span>
                    <a href="/logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>
    <?php endif; ?>

    <main class="main-content">
        <div class="container">
            <?php if (!empty($flash)): ?>
                <?php foreach ($flash as $type => $message): ?>
                    <?php if (is_array($message)) continue; ?>
                    <div class="alert alert-<?php echo View::e($type); ?>">
                        <?php echo View::e($message); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php echo $content; ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> AI WhatsApp Bot Builder. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/app.js"></script>
</body>
</html>
