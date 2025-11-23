<?php
// FILE: /app/models/Broadcast.php

/**
 * Broadcast Model
 *
 * Handles broadcast campaign data.
 */
class Broadcast extends Model {

    protected $table = 'broadcasts';

    /**
     * Get broadcasts with details
     *
     * @param int $tenantId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getWithDetails($tenantId, $limit = 100, $offset = 0) {
        $sql = "SELECT b.*, c.name as channel_name, t.name as template_name,
                       bot.name as bot_name
                FROM {$this->table} b
                JOIN channels c ON b.channel_id = c.id
                LEFT JOIN templates t ON b.template_id = t.id
                LEFT JOIN bots bot ON b.bot_id = bot.id
                WHERE b.tenant_id = ?
                ORDER BY b.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$tenantId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get broadcast with statistics
     *
     * @param int $id
     * @param int $tenantId
     * @return array|null
     */
    public function getWithStats($id, $tenantId) {
        $broadcast = $this->find($id, $tenantId);

        if (!$broadcast) {
            return null;
        }

        // Get detailed statistics
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM broadcast_messages
                WHERE broadcast_id = ?";
        $stmt = $this->query($sql, [$id]);
        $stats = $stmt->fetch();

        $broadcast['stats'] = $stats;

        return $broadcast;
    }
}
