<?php
// FILE: /app/models/ApiKey.php

/**
 * ApiKey Model
 *
 * Handles API key management for public API access.
 */
class ApiKey extends Model {

    protected $table = 'api_keys';

    /**
     * Generate new API key
     *
     * @param int $tenantId
     * @param string $name
     * @return array ['key' => raw key, 'id' => key ID]
     */
    public function generate($tenantId, $name) {
        // Generate random API key
        $rawKey = bin2hex(random_bytes(32));
        $keyPrefix = substr($rawKey, 0, 8);
        $keyHash = password_hash($rawKey, PASSWORD_DEFAULT);

        $keyId = $this->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'key_hash' => $keyHash,
            'key_prefix' => $keyPrefix,
            'status' => 'active'
        ]);

        return [
            'key' => $rawKey,
            'id' => $keyId
        ];
    }

    /**
     * Validate API key and return tenant
     *
     * @param string $rawKey
     * @return array|null Tenant data if valid
     */
    public function validateKey($rawKey) {
        $keyPrefix = substr($rawKey, 0, 8);

        $sql = "SELECT ak.*, t.id as tenant_id, t.name as tenant_name, t.status as tenant_status
                FROM {$this->table} ak
                JOIN tenants t ON ak.tenant_id = t.id
                WHERE ak.key_prefix = ? AND ak.status = 'active' AND t.status = 'active'
                LIMIT 1";
        $stmt = $this->query($sql, [$keyPrefix]);
        $apiKey = $stmt->fetch();

        if (!$apiKey || !password_verify($rawKey, $apiKey['key_hash'])) {
            return null;
        }

        // Update last used timestamp
        $this->query("UPDATE {$this->table} SET last_used_at = NOW() WHERE id = ?", [$apiKey['id']]);

        return $apiKey;
    }

    /**
     * Revoke API key
     *
     * @param int $id
     * @param int $tenantId
     * @return bool
     */
    public function revoke($id, $tenantId) {
        return $this->update($id, ['status' => 'revoked'], $tenantId);
    }
}
