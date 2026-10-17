<?php
// FILE: /app/models/BroadcastMessage.php

/**
 * BroadcastMessage Model
 *
 * Handles individual broadcast message records.
 */
class BroadcastMessage extends Model {

    protected $table = 'broadcast_messages';

    /**
     * Get messages by broadcast
     *
     * @param int $broadcastId
     * @param string|null $status
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByBroadcast($broadcastId, $status = null, $limit = 100, $offset = 0) {
        $sql = "SELECT bm.*, c.name as contact_name, c.phone_number
                FROM {$this->table} bm
                JOIN contacts c ON bm.contact_id = c.id
                WHERE bm.broadcast_id = ?";
        $params = [$broadcastId];

        if ($status !== null) {
            $sql .= " AND bm.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY bm.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Mark as sent
     *
     * @param int $id
     * @return bool
     */
    public function markAsSent($id) {
        $sql = "UPDATE {$this->table} SET status = 'sent', sent_at = NOW() WHERE id = ?";
        $stmt = $this->query($sql, [$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark as failed
     *
     * @param int $id
     * @param string $errorMessage
     * @return bool
     */
    public function markAsFailed($id, $errorMessage) {
        $sql = "UPDATE {$this->table} SET status = 'failed', error_message = ? WHERE id = ?";
        $stmt = $this->query($sql, [$errorMessage, $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Override find to enforce tenant isolation via broadcast
     *
     * @param int $id
     * @param int|null $tenantId
     * @return array|null
     */
    public function find($id, $tenantId = null) {
        if ($tenantId === null) {
            return parent::find($id, null);
        }

        $sql = "SELECT bm.* FROM {$this->table} bm
                JOIN broadcasts b ON bm.broadcast_id = b.id
                WHERE bm.id = ? AND b.tenant_id = ?
                LIMIT 1";
        $stmt = $this->query($sql, [$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Override findAll to enforce tenant isolation via broadcast
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

        $sql = "SELECT bm.* FROM {$this->table} bm
                JOIN broadcasts b ON bm.broadcast_id = b.id
                WHERE b.tenant_id = ?";
        $params = [$tenantId];

        foreach ($filters as $key => $value) {
            if ($value !== null && $this->isValidColumn($key)) {
                $sql .= " AND bm.{$key} = ?";
                $params[] = $value;
            }
        }

        $sql .= " ORDER BY bm.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Override count to enforce tenant isolation via broadcast
     *
     * @param array $filters
     * @param int|null $tenantId
     * @return int
     */
    public function count($filters = [], $tenantId = null) {
        if ($tenantId === null) {
            return parent::count($filters, null);
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table} bm
                JOIN broadcasts b ON bm.broadcast_id = b.id
                WHERE b.tenant_id = ?";
        $params = [$tenantId];

        foreach ($filters as $key => $value) {
            if ($value !== null && $this->isValidColumn($key)) {
                $sql .= " AND bm.{$key} = ?";
                $params[] = $value;
            }
        }

        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return (int) $result['total'];
    }

    /**
     * Override delete to enforce tenant isolation via broadcast
     *
     * @param int $id
     * @param int|null $tenantId
     * @return bool
     */
    public function delete($id, $tenantId = null) {
        if ($tenantId === null) {
            return parent::delete($id, null);
        }

        // Verify broadcast belongs to tenant before deleting message
        $sql = "DELETE bm FROM {$this->table} bm
                JOIN broadcasts b ON bm.broadcast_id = b.id
                WHERE bm.id = ? AND b.tenant_id = ?";
        $stmt = $this->query($sql, [$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if table has tenant_id column
     *
     * @return bool
     */
    protected function hasTenantId() {
        return false; // BroadcastMessage doesn't have tenant_id directly but enforces via JOIN
    }
}
