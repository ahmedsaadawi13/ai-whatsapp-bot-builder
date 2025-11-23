<?php
// FILE: /app/models/Bot.php

/**
 * Bot Model
 *
 * Handles bot configuration data.
 */
class Bot extends Model {

    protected $table = 'bots';

    /**
     * Get bots by tenant with channel info
     *
     * @param int $tenantId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByTenantWithChannel($tenantId, $limit = 100, $offset = 0) {
        $sql = "SELECT b.*, c.name as channel_name, c.phone_number
                FROM {$this->table} b
                JOIN channels c ON b.channel_id = c.id
                WHERE b.tenant_id = ?
                ORDER BY b.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$tenantId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get bot with full details
     *
     * @param int $id
     * @param int $tenantId
     * @return array|null
     */
    public function getWithDetails($id, $tenantId) {
        $sql = "SELECT b.*, c.name as channel_name, c.phone_number,
                       COUNT(DISTINCT f.id) as flow_count,
                       COUNT(DISTINCT kb.id) as knowledge_base_count
                FROM {$this->table} b
                JOIN channels c ON b.channel_id = c.id
                LEFT JOIN flows f ON b.id = f.bot_id AND f.status = 'active'
                LEFT JOIN knowledge_base kb ON b.id = kb.bot_id AND kb.status = 'active'
                WHERE b.id = ? AND b.tenant_id = ?
                GROUP BY b.id
                LIMIT 1";
        $stmt = $this->query($sql, [$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Toggle bot status
     *
     * @param int $id
     * @param int $tenantId
     * @return bool
     */
    public function toggleStatus($id, $tenantId) {
        $bot = $this->find($id, $tenantId);
        if (!$bot) {
            return false;
        }

        $newStatus = ($bot['status'] === 'active') ? 'paused' : 'active';
        return $this->update($id, ['status' => $newStatus], $tenantId);
    }
}
