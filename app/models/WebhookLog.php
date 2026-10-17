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
     * Get logs by webhook (with optional tenant isolation)
     *
     * @param int $webhookId
     * @param int $limit
     * @param int $offset
     * @param int|null $tenantId Optional tenant ID for isolation
     * @return array
     */
    public function getByWebhook($webhookId, $limit = 100, $offset = 0, $tenantId = null) {
        if ($tenantId === null) {
            // No tenant filtering (backward compatibility)
            $sql = "SELECT * FROM {$this->table}
                    WHERE webhook_id = ?
                    ORDER BY id DESC
                    LIMIT ? OFFSET ?";
            $stmt = $this->query($sql, [$webhookId, (int)$limit, (int)$offset]);
        } else {
            // Enforce tenant isolation via JOIN
            $sql = "SELECT wl.* FROM {$this->table} wl
                    JOIN webhooks w ON wl.webhook_id = w.id
                    WHERE wl.webhook_id = ? AND w.tenant_id = ?
                    ORDER BY wl.id DESC
                    LIMIT ? OFFSET ?";
            $stmt = $this->query($sql, [$webhookId, $tenantId, (int)$limit, (int)$offset]);
        }
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
     * Override findAll to enforce tenant isolation via webhook
     *
     * @param array $filters
     * @param int|null $tenantId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll($filters = [], $tenantId = null, $limit = 100, $offset = 0) {
        if ($tenantId === null) {
            return parent::findAll($filters, null, $limit, $offset);
        }

        $sql = "SELECT wl.* FROM {$this->table} wl
                JOIN webhooks w ON wl.webhook_id = w.id
                WHERE w.tenant_id = ?";
        $params = [$tenantId];

        foreach ($filters as $key => $value) {
            if ($value !== null && $this->isValidColumn($key)) {
                $sql .= " AND wl.{$key} = ?";
                $params[] = $value;
            }
        }

        $sql .= " ORDER BY wl.id DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Override count to enforce tenant isolation via webhook
     *
     * @param array $filters
     * @param int|null $tenantId
     * @return int
     */
    public function count($filters = [], $tenantId = null) {
        if ($tenantId === null) {
            return parent::count($filters, null);
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table} wl
                JOIN webhooks w ON wl.webhook_id = w.id
                WHERE w.tenant_id = ?";
        $params = [$tenantId];

        foreach ($filters as $key => $value) {
            if ($value !== null && $this->isValidColumn($key)) {
                $sql .= " AND wl.{$key} = ?";
                $params[] = $value;
            }
        }

        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return (int) $result['total'];
    }

    /**
     * Override update to enforce tenant isolation via webhook
     *
     * @param int $id
     * @param array $data
     * @param int|null $tenantId
     * @return bool
     */
    public function update($id, $data, $tenantId = null) {
        if ($tenantId === null) {
            return parent::update($id, $data, null);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        $sets = [];
        $params = [];

        foreach ($data as $key => $value) {
            $sets[] = "wl.{$key} = ?";
            $params[] = $value;
        }

        // Verify webhook belongs to tenant before updating
        $sql = "UPDATE {$this->table} wl
                JOIN webhooks w ON wl.webhook_id = w.id
                SET " . implode(', ', $sets) . "
                WHERE wl.id = ? AND w.tenant_id = ?";
        $params[] = $id;
        $params[] = $tenantId;

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
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
