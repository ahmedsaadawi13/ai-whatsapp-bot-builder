<?php
// FILE: /app/services/QuotaService.php

/**
 * Quota Service - enforces subscription plan limits.
 *
 * Resources: channels, bots, contacts, messages (per calendar month), storage (MB).
 * check() is called before creating anything that counts against a limit.
 * Tenant admins get a (simulated) email at 80% and at 100%.
 */
class QuotaService {

    const WARNING_THRESHOLD = 0.8;

    /** Days a tenant keeps working after expiry while the renewal invoice is unpaid. */
    const GRACE_DAYS = 7;

    const RESOURCES = [
        'channels' => ['limit' => 'max_channels', 'label' => 'WhatsApp channels'],
        'bots' => ['limit' => 'max_bots', 'label' => 'bots'],
        'contacts' => ['limit' => 'max_contacts', 'label' => 'contacts'],
        'messages' => ['limit' => 'max_messages_per_month', 'label' => 'messages this month'],
        'storage' => ['limit' => 'max_storage_mb', 'label' => 'MB of storage'],
    ];

    public static function label($resource) {
        return isset(self::RESOURCES[$resource]) ? self::RESOURCES[$resource]['label'] : $resource;
    }

    /** Current usage of one resource. */
    public static function used($tenantId, $resource) {
        $db = Database::getInstance()->getConnection();
        switch ($resource) {
            case 'channels':
            case 'bots':
                $stmt = $db->prepare("SELECT COUNT(*) AS c FROM {$resource} WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                return (int) $stmt->fetch()['c'];
            case 'contacts':
                $stmt = $db->prepare("SELECT COUNT(*) AS c FROM contacts WHERE tenant_id = ? AND phone_number != ?");
                $stmt->execute([$tenantId, MessageProcessor::SIMULATOR_PHONE]);
                return (int) $stmt->fetch()['c'];
            case 'messages':
                return (new Message())->countThisMonth($tenantId);
            case 'storage':
                return round(FileUpload::tenantUsageBytes($tenantId) / 1048576, 2);
        }
        throw new InvalidArgumentException('Unknown quota resource');
    }

    /**
     * Can the tenant add $amount more of $resource?
     *
     * @return array ['allowed' => bool, 'used' => num, 'limit' => int, 'message' => string]
     */
    public static function check($tenantId, $resource, $amount = 1) {
        $subscription = (new Subscription())->getActiveSubscription($tenantId);
        if (!$subscription) {
            return ['allowed' => false, 'used' => 0, 'limit' => 0,
                    'message' => 'No active subscription. Please choose a plan to continue.'];
        }
        if ($subscription['expires_at'] && strtotime($subscription['expires_at']) + self::GRACE_DAYS * 86400 < time()) {
            return ['allowed' => false, 'used' => 0, 'limit' => 0,
                    'message' => 'Your subscription has expired. Pay the pending invoice under Billing to renew it.'];
        }

        $limit = (int) $subscription[self::RESOURCES[$resource]['limit']];
        $used = self::used($tenantId, $resource);
        $after = $used + $amount;
        $label = self::label($resource);

        if ($after > $limit) {
            Notifier::quotaWarning($tenantId, $resource, $used, $limit, true);
            return [
                'allowed' => false,
                'used' => $used,
                'limit' => $limit,
                'message' => "Plan limit reached: your {$subscription['plan_name']} plan allows {$limit} {$label} (currently using {$used}). Upgrade your plan to add more."
            ];
        }

        if ($limit > 0 && $after >= $limit * self::WARNING_THRESHOLD) {
            Notifier::quotaWarning($tenantId, $resource, $after, $limit, false);
        }

        return ['allowed' => true, 'used' => $used, 'limit' => $limit, 'message' => ''];
    }

    /**
     * Usage vs limits for every resource (subscription and dashboard pages).
     */
    public static function summary($tenantId) {
        $subscription = (new Subscription())->getActiveSubscription($tenantId);
        $rows = [];
        foreach (self::RESOURCES as $key => $meta) {
            $limit = $subscription ? (int) $subscription[$meta['limit']] : 0;
            $used = self::used($tenantId, $key);
            $rows[$key] = [
                'label' => ucfirst($meta['label']),
                'used' => $used,
                'limit' => $limit,
                'percent' => percent($used, $limit),
                'state' => $used >= $limit ? 'exceeded' : ($used >= $limit * self::WARNING_THRESHOLD ? 'warning' : 'ok')
            ];
        }
        return $rows;
    }
}
