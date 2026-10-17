<?php
// FILE: /app/views/layouts/layout.php
// Master layout. $content holds the rendered view.
$auth = new Auth();
$currentUser = $auth->user();
$flash = (new Session())->getAllFlash();
$role = $currentUser ? $currentUser['role'] : null;
$tenant = ($currentUser && $currentUser['tenant_id']) ? (new Tenant())->find($currentUser['tenant_id']) : null;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$builder = in_array($role, ['tenant_admin', 'developer'], true);
$nav = [];
if ($role === 'platform_admin') {
    $nav = [
        'Platform' => [['/dashboard', 'Dashboard'], ['/tenants', 'Tenants'], ['/notifications', 'Email log']],
    ];
} elseif ($role) {
    $nav['Overview'] = [['/dashboard', 'Dashboard'], ['/inbox', 'Inbox'], ['/contacts', 'Contacts'], ['/analytics', 'Analytics']];
    if ($builder) {
        $nav['Build'] = [['/channels', 'Channels'], ['/bots', 'Bots'], ['/knowledge-base', 'Knowledge Base'], ['/templates', 'Templates'], ['/broadcasts', 'Broadcasts']];
        $nav['Developers'] = [['/webhooks', 'Webhooks'], ['/settings/api-keys', 'API Keys']];
    }
    if ($role === 'tenant_admin') {
        $nav['Account'] = [['/users', 'Team'], ['/subscription', 'Subscription'], ['/billing', 'Billing'], ['/notifications', 'Email log'], ['/settings', 'Settings']];
    }
}
$isActive = function ($href) use ($path) {
    return $href === '/dashboard' ? $path === $href : strpos($path, $href) === 0;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? e($title) . ' - ' : ''; ?>AI WhatsApp Bot Builder</title>
    <?php echo CSRF::meta(); ?>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?php echo $currentUser ? 'app-body' : 'guest-body'; ?>">
<?php if ($currentUser): ?>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="/dashboard">
            <span class="brand-mark">AI</span>
            <span>WhatsApp Bot Builder</span>
        </a>
        <?php if ($tenant): ?>
        <div class="tenant-card">
            <?php if ($tenant['logo']): ?>
                <img src="/files?path=<?php echo e(rawurlencode($tenant['logo'])); ?>" alt="" class="tenant-logo">
            <?php endif; ?>
            <div>
                <strong><?php echo e($tenant['name']); ?></strong>
                <div class="muted small"><?php echo e(str_replace('_', ' ', $role)); ?></div>
            </div>
        </div>
        <?php endif; ?>
        <nav>
            <?php foreach ($nav as $group => $links): ?>
                <div class="nav-group"><?php echo e($group); ?></div>
                <?php foreach ($links as $link): ?>
                    <a href="<?php echo e($link[0]); ?>" class="nav-link<?php echo $isActive($link[0]) ? ' active' : ''; ?>"><?php echo e($link[1]); ?></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <button type="button" class="btn btn-small btn-secondary menu-toggle" data-toggle="sidebar">Menu</button>
            <div class="topbar-title"><?php echo isset($title) ? e($title) : ''; ?></div>
            <div class="topbar-user">
                <span><?php echo e($currentUser['name']); ?></span>
                <form method="POST" action="/logout" class="inline-form">
                    <?php echo CSRF::field(); ?>
                    <button type="submit" class="btn btn-small btn-secondary">Logout</button>
                </form>
            </div>
        </header>

        <main class="content">
            <?php foreach ($flash as $type => $message): ?>
                <?php if (!in_array($type, ['success', 'error', 'info'], true) || !is_string($message)) continue; ?>
                <div class="alert alert-<?php echo e($type); ?>" role="status"><?php echo e($message); ?></div>
            <?php endforeach; ?>

            <?php echo $content; ?>
        </main>
    </div>
</div>
<?php else: ?>
<main class="content guest-content">
    <?php foreach ($flash as $type => $message): ?>
        <?php if (!in_array($type, ['success', 'error', 'info'], true) || !is_string($message)) continue; ?>
        <div class="alert alert-<?php echo e($type); ?>"><?php echo e($message); ?></div>
    <?php endforeach; ?>
    <?php echo $content; ?>
</main>
<?php endif; ?>
<script src="/assets/js/app.js"></script>
</body>
</html>
