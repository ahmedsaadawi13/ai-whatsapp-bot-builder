<?php
// FILE: /app/models/BroadcastMessage.php

/**
 * BroadcastMessage Model - one recipient of a broadcast.
 *
 * The table has no tenant_id; every read joins broadcasts to enforce the
 * tenant. The generic find/update/delete refuse a tenant ID (fail closed).
 */
class BroadcastMessage extends Model {

    protected $table = 'broadcast_messages';
    protected $tenantScoped = false;
    protected $hasUpdatedAt = false;

    public function getByBroadcast($broadcastId, $tenantId, $status = null, $limit = 50, $offset = 0) {
        list($where, $params) = $this->recipientWhere($broadcastId, $tenantId, $status);
        $sql = "SELECT bm.*, c.name AS contact_name, c.phone_number
                FROM {$this->table} bm
                JOIN broadcasts b ON b.id = bm.broadcast_id
                JOIN contacts c ON c.id = bm.contact_id
                WHERE {$where}
                ORDER BY bm.id ASC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    public function countByBroadcast($broadcastId, $tenantId, $status = null) {
        list($where, $params) = $this->recipientWhere($broadcastId, $tenantId, $status);
        $sql = "SELECT COUNT(*) AS total FROM {$this->table} bm JOIN broadcasts b ON b.id = bm.broadcast_id WHERE {$where}";
        return (int) $this->query($sql, $params)->fetch()['total'];
    }

    private function recipientWhere($broadcastId, $tenantId, $status) {
        $where = 'bm.broadcast_id = ? AND b.tenant_id = ?';
        $params = [$broadcastId, $tenantId];
        if ($status !== null && in_array($status, ['pending', 'sent', 'failed'], true)) {
            $where .= ' AND bm.status = ?';
            $params[] = $status;
        }
        return [$where, $params];
    }

    /** Queue recipients (idempotent thanks to the unique broadcast+contact key). */
    public function queue($broadcastId, $contactIds) {
        $now = date('Y-m-d H:i:s');
        foreach ($contactIds as $contactId) {
            $this->query(
                "INSERT IGNORE INTO {$this->table} (broadcast_id, contact_id, status, created_at) VALUES (?, ?, 'pending', ?)",
                [$broadcastId, (int) $contactId, $now]
            );
        }
    }

    public function getPending($broadcastId, $limit = 500) {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE broadcast_id = ? AND status = 'pending' ORDER BY id " . $this->limitClause($limit),
            [$broadcastId]
        )->fetchAll();
    }

    public function markAsSent($id, $messageId) {
        $this->query(
            "UPDATE {$this->table} SET status = 'sent', sent_at = ?, message_id = ?, error_message = NULL WHERE id = ?",
            [date('Y-m-d H:i:s'), $messageId, $id]
        );
    }

    public function markAsFailed($id, $errorMessage) {
        $this->query(
            "UPDATE {$this->table} SET status = 'failed', error_message = ? WHERE id = ?",
            [mb_substr($errorMessage, 0, 500, 'UTF-8'), $id]
        );
    }
}
