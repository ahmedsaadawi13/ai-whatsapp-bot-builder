<?php
// FILE: /app/models/Channel.php

/**
 * Channel Model
 *
 * Handles WhatsApp channel/phone number data.
 */
class Channel extends Model {

    protected $table = 'channels';

    /**
     * Get channels by tenant with bot count
     *
     * @param int $tenantId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByTenantWithBots($tenantId, $limit = 100, $offset = 0) {
        $sql = "SELECT c.*, COUNT(b.id) as bot_count
                FROM {$this->table} c
                LEFT JOIN bots b ON c.id = b.channel_id
                WHERE c.tenant_id = ?
                GROUP BY c.id
                ORDER BY c.id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$tenantId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Find channel by phone number
     *
     * @param string $phoneNumber
     * @param int $tenantId
     * @return array|null
     */
    public function findByPhoneNumber($phoneNumber, $tenantId) {
        $sql = "SELECT * FROM {$this->table} WHERE phone_number = ? AND tenant_id = ? LIMIT 1";
        $stmt = $this->query($sql, [$phoneNumber, $tenantId]);
        return $stmt->fetch() ?: null;
    }
}
