<?php
// FILE: /app/models/Subscription.php

/**
 * Subscription Model
 *
 * Handles tenant subscription data.
 */
class Subscription extends Model {

    protected $table = 'tenant_subscriptions';

    /**
     * Get active subscription for tenant
     *
     * @param int $tenantId
     * @return array|null
     */
    public function getActiveSubscription($tenantId) {
        $sql = "SELECT ts.*, p.name as plan_name, p.max_channels, p.max_bots,
                       p.max_contacts, p.max_messages_per_month, p.max_storage_mb
                FROM {$this->table} ts
                JOIN plans p ON ts.plan_id = p.id
                WHERE ts.tenant_id = ? AND ts.status = 'active'
                ORDER BY ts.id DESC
                LIMIT 1";
        $stmt = $this->query($sql, [$tenantId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get subscription with plan details
     *
     * @param int $subscriptionId
     * @param int $tenantId
     * @return array|null
     */
    public function getWithPlan($subscriptionId, $tenantId) {
        $sql = "SELECT ts.*, p.*
                FROM {$this->table} ts
                JOIN plans p ON ts.plan_id = p.id
                WHERE ts.id = ? AND ts.tenant_id = ?
                LIMIT 1";
        $stmt = $this->query($sql, [$subscriptionId, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Cancel subscription
     *
     * @param int $subscriptionId
     * @param int $tenantId
     * @return bool
     */
    public function cancel($subscriptionId, $tenantId) {
        return $this->update($subscriptionId, ['status' => 'cancelled'], $tenantId);
    }
}
