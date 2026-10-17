<?php
// FILE: /app/models/WebhookLog.php

/**
 * WebhookLog Model - delivery attempts of outgoing webhooks.
 *
 * No tenant_id column: reads join webhooks to enforce the tenant.
 */
class WebhookLog extends Model {

    protected $table = 'webhook_logs';
    protected $tenantScoped = false;
    protected $hasUpdatedAt = false;

    public function getByWebhook($webhookId, $tenantId, $status = null, $limit = 50, $offset = 0) {
        list($where, $params) = $this->logWhere($webhookId, $tenantId, $status);
        $sql = "SELECT l.* FROM {$this->table} l
                JOIN webhooks w ON w.id = l.webhook_id
                WHERE {$where}
                ORDER BY l.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    public function countByWebhook($webhookId, $tenantId, $status = null) {
        list($where, $params) = $this->logWhere($webhookId, $tenantId, $status);
        $sql = "SELECT COUNT(*) AS total FROM {$this->table} l JOIN webhooks w ON w.id = l.webhook_id WHERE {$where}";
        return (int) $this->query($sql, $params)->fetch()['total'];
    }

    private function logWhere($webhookId, $tenantId, $status) {
        $where = 'l.webhook_id = ? AND w.tenant_id = ?';
        $params = [$webhookId, $tenantId];
        if ($status === 'success' || $status === 'failed') {
            $where .= ' AND l.status = ?';
            $params[] = $status;
        }
        return [$where, $params];
    }

    /** Failed deliveries that still have retries left (all tenants, used by cron). */
    public function getRetryable($limit = 50) {
        $sql = "SELECT l.*, w.url, w.secret, w.retry_attempts, w.tenant_id, w.status AS webhook_status
                FROM {$this->table} l
                JOIN webhooks w ON w.id = l.webhook_id
                WHERE l.status = 'failed' AND l.retried = 0 AND l.attempt < w.retry_attempts AND w.status = 'active'
                ORDER BY l.id ASC " . $this->limitClause($limit);
        return $this->query($sql)->fetchAll();
    }

    public function markRetried($id) {
        $this->query("UPDATE {$this->table} SET retried = 1 WHERE id = ?", [$id]);
    }
}
