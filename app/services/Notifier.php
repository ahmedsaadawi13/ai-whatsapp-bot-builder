<?php
// FILE: /app/services/Notifier.php

/**
 * Notifier - simulated email delivery.
 *
 * Emails are rendered from /app/views/emails/*.php, stored in the
 * notifications table (visible under "Notifications") and appended to
 * /storage/logs/mail.log. Replace send() with SMTP/API delivery later.
 */
class Notifier {

    public static function send($tenantId, $userId, $type, $recipient, $subject, $template, $data = [], $dedupeKey = null) {
        $notification = new Notification();
        if ($dedupeKey !== null && $notification->existsByDedupeKey($dedupeKey)) {
            return false;
        }

        $body = self::render($template, $data + ['subject' => $subject]);

        try {
            $notification->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => $type,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'dedupe_key' => $dedupeKey,
                'status' => 'sent'
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return false; // already sent (dedupe race)
            }
            throw $e;
        }

        $logDir = storage_path('logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir . '/mail.log',
            sprintf("[%s] To: %s | Subject: %s\n%s\n\n", gmdate('Y-m-d H:i:s'), $recipient, $subject, strip_tags($body)),
            FILE_APPEND | LOCK_EX
        );
        return true;
    }

    private static function render($template, $data) {
        $file = APP_ROOT . '/app/views/emails/' . basename($template) . '.php';
        extract($data);
        ob_start();
        require $file;
        $content = ob_get_clean();
        ob_start();
        require APP_ROOT . '/app/views/emails/layout.php';
        return ob_get_clean();
    }

    public static function welcome($tenant, $user) {
        return self::send($tenant['id'], $user['id'], 'welcome', $user['email'],
            'Welcome to AI WhatsApp Bot Builder', 'welcome', ['tenant' => $tenant, 'user' => $user]);
    }

    /**
     * Warn tenant admins when usage reaches 80% or 100% of a plan limit.
     * Deduplicated per tenant / resource / level / month.
     */
    public static function quotaWarning($tenantId, $resource, $used, $limit, $exceeded) {
        $level = $exceeded ? 'exceeded' : 'warning';
        $label = QuotaService::label($resource);
        $subject = $exceeded
            ? "Plan limit reached: {$label}"
            : "You have used " . percent($used, $limit) . "% of your {$label} limit";

        foreach ((new User())->getTenantAdmins($tenantId) as $admin) {
            self::send($tenantId, $admin['id'], 'quota_' . $level, $admin['email'], $subject, 'quota', [
                'user' => $admin, 'label' => $label, 'used' => $used, 'limit' => $limit, 'exceeded' => $exceeded
            ], "quota:{$tenantId}:{$resource}:{$level}:" . date('Y-m') . ':' . $admin['id']);
        }
    }

    /**
     * Daily summary of the last 24h for every tenant admin.
     */
    public static function dailySummary($tenant) {
        $since = date('Y-m-d H:i:s', time() - 86400);
        $stats = (new Message())->getStatistics($tenant['id'], $since, date('Y-m-d H:i:s'));
        $newConversations = (new Conversation())->countCreatedSince($tenant['id'], $since);
        $open = (new Conversation())->countByStatus($tenant['id']);

        $sent = 0;
        foreach ((new User())->getTenantAdmins($tenant['id']) as $admin) {
            $sent += (int) self::send($tenant['id'], $admin['id'], 'daily_summary', $admin['email'],
                'Your daily WhatsApp summary - ' . date('M d, Y'), 'daily_summary', [
                    'user' => $admin, 'tenant' => $tenant, 'stats' => $stats,
                    'newConversations' => $newConversations, 'open' => $open
                ], 'daily:' . $tenant['id'] . ':' . date('Y-m-d') . ':' . $admin['id']);
        }
        return $sent;
    }
}
