<?php // FILE: /app/views/analytics/_nav.php ?>
<?php
$reportPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$reports = [
    '/analytics' => 'Overview',
    '/analytics/messages' => 'Messages',
    '/analytics/conversations' => 'Conversations',
    '/analytics/channels' => 'Channels',
    '/analytics/broadcasts' => 'Broadcasts'
];
?>
<div class="filters">
    <?php foreach ($reports as $href => $label): ?>
        <a href="<?php echo e($href); ?>" class="btn btn-small<?php echo $reportPath === $href ? '' : ' btn-secondary'; ?>"><?php echo e($label); ?></a>
    <?php endforeach; ?>
</div>
