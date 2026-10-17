<?php
// FILE: /app/models/Channel.php

/**
 * Channel Model - a WhatsApp number connected through a (simulated) provider.
 * provider_config is stored encrypted (see Crypto).
 */
class Channel extends Model {

    protected $table = 'channels';
    protected $searchable = ['name', 'phone_number'];

    public function listWithBotCount($tenantId, $filters, $search, $limit, $offset) {
        list($where, $params) = $this->buildWhere($filters, $tenantId, $search, 'c');
        $sql = "SELECT c.*, (SELECT COUNT(*) FROM bots b WHERE b.channel_id = c.id) AS bot_count
                FROM {$this->table} c
                WHERE {$where}
                ORDER BY c.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    public function getForSelect($tenantId) {
        return $this->query(
            "SELECT id, name, phone_number, status FROM {$this->table} WHERE tenant_id = ? ORDER BY name",
            [$tenantId]
        )->fetchAll();
    }

    /** Global lookup used by the webhook receiver (phone numbers are unique). */
    public function findActiveByPhone($phoneNumber) {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE phone_number = ? AND status = 'active' LIMIT 1",
            [$phoneNumber]
        );
        return $stmt->fetch() ?: null;
    }

    public function phoneExists($phoneNumber, $exceptId = 0) {
        $stmt = $this->query(
            "SELECT 1 FROM {$this->table} WHERE phone_number = ? AND id != ? LIMIT 1",
            [$phoneNumber, (int) $exceptId]
        );
        return (bool) $stmt->fetch();
    }

    public function findByVerifyToken($token) {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE webhook_verify_token = ? AND status = 'active' LIMIT 1",
            [$token]
        );
        return $stmt->fetch() ?: null;
    }

    /** Decrypted provider config as an array. */
    public static function decodeConfig($stored) {
        return json_array(Crypto::decrypt($stored));
    }

    public static function encodeConfig($config) {
        return Crypto::encrypt(json_encode($config ?: new stdClass()));
    }
}
