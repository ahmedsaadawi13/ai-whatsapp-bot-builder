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
     * Check if table has tenant_id column
     *
     * @return bool
     */
    protected function hasTenantId() {
        return false; // WebhookLog doesn't have tenant_id
    }
}
