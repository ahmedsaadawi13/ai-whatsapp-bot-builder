<?php
// FILE: /app/models/Subscription.php

/**
 * Subscription Model - which plan a tenant is on.
 */
class Subscription extends Model {

    protected $table = 'tenant_subscriptions';

    /** Active subscription joined with plan limits, or null. */
    public function getActiveSubscription($tenantId) {
        $sql = "SELECT ts.*, p.name AS plan_name, p.price AS plan_price, p.billing_period,
                       p.max_channels, p.max_bots, p.max_contacts, p.max_messages_per_month, p.max_storage_mb
                FROM {$this->table} ts
                JOIN plans p ON ts.plan_id = p.id
                WHERE ts.tenant_id = ? AND ts.status = 'active'
                ORDER BY ts.id DESC
                LIMIT 1";
        return $this->query($sql, [$tenantId])->fetch() ?: null;
    }

    public function getHistory($tenantId) {
        $sql = "SELECT ts.*, p.name AS plan_name, p.price AS plan_price
                FROM {$this->table} ts JOIN plans p ON p.id = ts.plan_id
                WHERE ts.tenant_id = ? ORDER BY ts.id DESC LIMIT 20";
        return $this->query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Cancel the current subscription and start a new one on $plan.
     * Also issues a pending invoice for the new period.
     *
     * @return array ['subscription_id' => int, 'invoice_id' => int]
     */
    public function switchPlan($tenantId, $plan) {
        $this->beginTransaction();
        try {
            $this->query(
                "UPDATE {$this->table} SET status = 'cancelled', updated_at = ? WHERE tenant_id = ? AND status = 'active'",
                [date('Y-m-d H:i:s'), $tenantId]
            );

            $months = $plan['billing_period'] === 'yearly' ? 12 : 1;
            $start = date('Y-m-d H:i:s');
            $end = date('Y-m-d H:i:s', strtotime("+{$months} month"));

            $subscriptionId = $this->create([
                'tenant_id' => $tenantId,
                'plan_id' => $plan['id'],
                'status' => 'active',
                'started_at' => $start,
                'expires_at' => $end
            ]);

            $invoiceId = 0;
            if ((float) $plan['price'] > 0) {
                $invoiceId = (new Invoice())->createForPlan($tenantId, $subscriptionId, $plan, $start, $end);
            }

            $this->commit();
            return ['subscription_id' => $subscriptionId, 'invoice_id' => $invoiceId];
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Push the expiry date forward (never backwards) after a paid invoice.
     */
    public function extendTo($subscriptionId, $tenantId, $until) {
        $this->query(
            "UPDATE {$this->table} SET expires_at = GREATEST(COALESCE(expires_at, ?), ?), updated_at = ?
             WHERE id = ? AND tenant_id = ? AND status = 'active'",
            [$until, $until, date('Y-m-d H:i:s'), $subscriptionId, $tenantId]
        );
    }

    /**
     * Issue the next period's invoice for active paid subscriptions that expire
     * within $daysAhead days (cron: php cron/run.php billing). Idempotent.
     *
     * @return int invoices created
     */
    public function issueRenewalInvoices($daysAhead = 3) {
        $sql = "SELECT ts.*, p.name, p.price, p.billing_period
                FROM {$this->table} ts JOIN plans p ON p.id = ts.plan_id
                WHERE ts.status = 'active' AND p.price > 0 AND ts.expires_at IS NOT NULL AND ts.expires_at <= ?";
        $due = $this->query($sql, [date('Y-m-d H:i:s', strtotime("+{$daysAhead} days"))])->fetchAll();

        $invoiceModel = new Invoice();
        $created = 0;
        foreach ($due as $sub) {
            $start = $sub['expires_at'];
            $exists = $this->query(
                "SELECT 1 FROM invoices WHERE subscription_id = ? AND period_start = ? AND status != 'cancelled' LIMIT 1",
                [$sub['id'], date('Y-m-d', strtotime($start))]
            )->fetch();
            if ($exists) {
                continue;
            }
            $months = $sub['billing_period'] === 'yearly' ? 12 : 1;
            $end = date('Y-m-d H:i:s', strtotime($start . " +{$months} month"));
            $plan = ['id' => $sub['plan_id'], 'name' => $sub['name'], 'price' => $sub['price'], 'billing_period' => $sub['billing_period']];
            $invoiceModel->createForPlan($sub['tenant_id'], $sub['id'], $plan, $start, $end);
            $created++;
        }
        return $created;
    }
}
