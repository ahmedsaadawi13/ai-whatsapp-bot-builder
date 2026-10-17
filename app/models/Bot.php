<?php
// FILE: /app/models/Bot.php

/**
 * Bot Model - bot configuration (channel, language, AI settings).
 */
class Bot extends Model {

    protected $table = 'bots';
    protected $searchable = ['name', 'description'];

    public function listWithChannel($tenantId, $filters, $search, $limit, $offset) {
        list($where, $params) = $this->buildWhere($filters, $tenantId, $search, 'b');
        $sql = "SELECT b.*, c.name AS channel_name, c.phone_number,
                       (SELECT COUNT(*) FROM flows f WHERE f.bot_id = b.id) AS flow_count
                FROM {$this->table} b
                JOIN channels c ON b.channel_id = c.id
                WHERE {$where}
                ORDER BY b.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    public function getForSelect($tenantId) {
        return $this->query(
            "SELECT id, name, channel_id, status FROM {$this->table} WHERE tenant_id = ? ORDER BY name",
            [$tenantId]
        )->fetchAll();
    }

    public function getWithDetails($id, $tenantId) {
        $sql = "SELECT b.*, c.name AS channel_name, c.phone_number,
                       (SELECT COUNT(*) FROM flows f WHERE f.bot_id = b.id AND f.status = 'active') AS flow_count,
                       (SELECT COUNT(*) FROM knowledge_base kb WHERE kb.tenant_id = b.tenant_id
                            AND kb.status = 'active' AND (kb.bot_id = b.id OR kb.bot_id IS NULL)) AS knowledge_base_count,
                       (SELECT COUNT(*) FROM conversations cv WHERE cv.bot_id = b.id) AS conversation_count
                FROM {$this->table} b
                JOIN channels c ON b.channel_id = c.id
                WHERE b.id = ? AND b.tenant_id = ?
                LIMIT 1";
        return $this->query($sql, [$id, $tenantId])->fetch() ?: null;
    }

    /** The active bot answering a channel (lowest ID wins if several). */
    public function findActiveForChannel($channelId) {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE channel_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1",
            [$channelId]
        );
        return $stmt->fetch() ?: null;
    }

    /** Atomic active <-> paused toggle (drafts become active). */
    public function toggleStatus($id, $tenantId) {
        $sql = "UPDATE {$this->table}
                SET status = CASE WHEN status = 'active' THEN 'paused' ELSE 'active' END, updated_at = ?
                WHERE id = ? AND tenant_id = ?";
        return $this->query($sql, [date('Y-m-d H:i:s'), $id, $tenantId])->rowCount() > 0;
    }
}
