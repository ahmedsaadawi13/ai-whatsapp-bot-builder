<?php
// FILE: /app/models/User.php

/**
 * User Model
 *
 * Handles user data and authentication operations.
 */
class User extends Model {

    protected $table = 'users';

    /**
     * Find user by email
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? LIMIT 1";
        $stmt = $this->query($sql, [$email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create new user
     *
     * @param array $data
     * @return int User ID
     */
    public function createUser($data) {
        // Hash password before storing
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        return $this->create($data);
    }

    /**
     * Update user password
     *
     * @param int $userId
     * @param string $newPassword
     * @param int|null $tenantId
     * @return bool
     */
    public function updatePassword($userId, $newPassword, $tenantId = null) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->update($userId, ['password' => $hashedPassword], $tenantId);
    }

    /**
     * Get users by tenant
     *
     * @param int $tenantId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByTenant($tenantId, $filters = [], $limit = 100, $offset = 0) {
        return $this->findAll($filters, $tenantId, $limit, $offset);
    }

    /**
     * Update last login timestamp
     *
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin($userId) {
        $sql = "UPDATE {$this->table} SET last_login_at = NOW() WHERE id = ?";
        $stmt = $this->query($sql, [$userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if table has tenant_id column
     *
     * @return bool
     */
    protected function hasTenantId() {
        // Users table has tenant_id but platform admins have NULL
        return true;
    }
}
