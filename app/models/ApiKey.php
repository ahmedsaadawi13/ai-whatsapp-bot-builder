<?php
// FILE: /app/models/ApiKey.php

/**
 * ApiKey Model - per-tenant keys for the public API.
 *
 * Only a SHA-256 hash is stored. API keys are long random strings, so a
 * fast hash is appropriate and allows a direct indexed lookup.
 */
class ApiKey extends Model {

    protected $table = 'api_keys';

    /**
     * @return array ['key' => raw key (show once), 'id' => int]
     */
    public function generate($tenantId, $name) {
        $rawKey = 'awb_' . bin2hex(random_bytes(24));
        $id = $this->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'key_hash' => hash('sha256', $rawKey),
            'key_prefix' => substr($rawKey, 0, 12),
            'status' => 'active'
        ]);
        return ['key' => $rawKey, 'id' => $id];
    }

    /**
     * Validate a raw key. Returns the key row (with tenant info) or null.
     */
    public function validateKey($rawKey) {
        if (!is_string($rawKey) || strlen($rawKey) < 20 || strlen($rawKey) > 200) {
            return null;
        }
        $sql = "SELECT ak.*, t.name AS tenant_name
                FROM {$this->table} ak
                JOIN tenants t ON ak.tenant_id = t.id
                WHERE ak.key_hash = ? AND ak.status = 'active' AND t.status = 'active'
                  AND (ak.expires_at IS NULL OR ak.expires_at > ?)
                LIMIT 1";
        $apiKey = $this->query($sql, [hash('sha256', $rawKey), date('Y-m-d H:i:s')])->fetch();
        if (!$apiKey) {
            return null;
        }
        $this->query("UPDATE {$this->table} SET last_used_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $apiKey['id']]);
        return $apiKey;
    }

    public function revoke($id, $tenantId) {
        $stmt = $this->query(
            "UPDATE {$this->table} SET status = 'revoked', updated_at = ? WHERE id = ? AND tenant_id = ?",
            [date('Y-m-d H:i:s'), $id, $tenantId]
        );
        return $stmt->rowCount() > 0;
    }
}
