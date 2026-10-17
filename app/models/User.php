<?php
// FILE: /app/models/User.php

/**
 * User Model - users belong to a tenant (platform admins have tenant_id NULL).
 */
class User extends Model {

    protected $table = 'users';
    protected $searchable = ['name', 'email'];

    public function findByEmail($email) {
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE email = ? LIMIT 1", [strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    /** Create a user, hashing the password with password_hash(). */
    public function createUser($data) {
        $data['email'] = strtolower(trim($data['email']));
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return $this->create($data);
    }

    public function updateLastLogin($userId) {
        $this->query("UPDATE {$this->table} SET last_login_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $userId]);
    }

    /** Active users of a tenant that may handle conversations. */
    public function getAssignable($tenantId) {
        $stmt = $this->query(
            "SELECT id, name, role FROM {$this->table}
             WHERE tenant_id = ? AND status = 'active' AND role IN ('tenant_admin', 'agent')
             ORDER BY name",
            [$tenantId]
        );
        return $stmt->fetchAll();
    }

    public function getTenantAdmins($tenantId) {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE tenant_id = ? AND role = 'tenant_admin' AND status = 'active'",
            [$tenantId]
        );
        return $stmt->fetchAll();
    }

    public function countActiveAdmins($tenantId) {
        $stmt = $this->query(
            "SELECT COUNT(*) AS c FROM {$this->table} WHERE tenant_id = ? AND role = 'tenant_admin' AND status = 'active'",
            [$tenantId]
        );
        return (int) $stmt->fetch()['c'];
    }
}
