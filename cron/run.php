<?php
// FILE: /cron/run.php

/**
 * AI WhatsApp Bot Builder - scheduled jobs (CLI only).
 *
 * Usage:
 *   php cron/run.php broadcasts   # send scheduled broadcasts that are due
 *   php cron/run.php webhooks     # retry failed webhook deliveries
 *   php cron/run.php usage        # refresh usage_tracking for every tenant
 *   php cron/run.php billing      # issue renewal invoices for subscriptions expiring soon
 *   php cron/run.php daily        # daily summary emails to tenant admins
 *   php cron/run.php all          # broadcasts + webhooks + usage + billing
 *
 * Example crontab:
 *   * * * * *  php /var/www/ai-whatsapp-bot-builder/cron/run.php all
 *   0 7 * * *  php /var/www/ai-whatsapp-bot-builder/cron/run.php daily
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../app/bootstrap.php';

$task = isset($argv[1]) ? $argv[1] : 'all';

function log_line($text) {
    echo '[' . gmdate('Y-m-d H:i:s') . '] ' . $text . PHP_EOL;
}

function active_tenants() {
    return (new Tenant())->findAll(['status' => 'active'], null, 100000, 0);
}

try {
    if ($task === 'broadcasts' || $task === 'all') {
        log_line('Broadcasts processed: ' . BroadcastRunner::runDue());
    }
    if ($task === 'webhooks' || $task === 'all') {
        log_line('Webhook retries: ' . WebhookDispatcher::retryFailed());
    }
    if ($task === 'usage' || $task === 'all') {
        $usage = new UsageTracking();
        $count = 0;
        foreach (active_tenants() as $tenant) {
            $usage->recalculate($tenant['id']);
            $count++;
        }
        log_line("Usage recalculated for {$count} tenants");
    }
    if ($task === 'billing' || $task === 'all') {
        log_line('Renewal invoices issued: ' . (new Subscription())->issueRenewalInvoices());
    }
    if ($task === 'daily') {
        $sent = 0;
        foreach (active_tenants() as $tenant) {
            $sent += Notifier::dailySummary($tenant);
        }
        log_line("Daily summaries sent: {$sent}");
    }
    if (!in_array($task, ['broadcasts', 'webhooks', 'usage', 'billing', 'daily', 'all'], true)) {
        log_line("Unknown task: {$task}");
        exit(1);
    }
} catch (Throwable $e) {
    log_line('ERROR: ' . $e->getMessage());
    exit(1);
}
