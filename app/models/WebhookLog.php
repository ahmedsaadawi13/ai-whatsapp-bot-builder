<?php
// FILE: /app/models/WebhookLog.php

/**
 * WebhookLog Model
 *
 * Handles webhook execution logs.
 */
class WebhookLog extends Model {

    protected $table = 'webhook_logs';

    /**
     * Get logs by webhook
     *
     * @param int $webhookId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByWebhook($webhookId, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM {$this->table}
                WHERE webhook_id = ?
                ORDER BY id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$webhookId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Override find to enforce tenant isolation via webhook
     *
     * @param int $id
     * @param int|null $tenantId
     * @return array|null
     */
    public function find($id, $tenantId = null) {
        if ($tenantId === null) {
            return parent::find($id, null);
        }

        $sql = "SELECT wl.* FROM {$this->table} wl
                JOIN webhooks w ON wl.webhook_id = w.id
                WHERE wl.id = ? AND w.tenant_id = ?
                LIMIT 1";
        $stmt = $this->query($sql, [$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Override delete to enforce tenant isolation via webhook
     *
     * @param int $id
     * @param int|null $tenantId
     * @return bool
     */
    public function delete($id, $tenantId = null) {
        if ($tenantId === null) {
            return parent::delete($id, null);
        }

        $sql = "DELETE wl FROM {$this->table} wl
                JOIN webhooks w ON wl.webhook_id = w.id
                WHERE wl.id = ? AND w.tenant_id = ?";
        $stmt = $this->query($sql, [$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if table has tenant_id column
     *
     * @return bool
     */
    protected function hasTenantId() {
        return false; // WebhookLog doesn't have tenant_id but enforces via JOIN
    }
}
