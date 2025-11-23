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
     * Check if table has tenant_id column
     *
     * @return bool
     */
    protected function hasTenantId() {
        return false; // BroadcastMessage doesn't have tenant_id directly
    }
}
