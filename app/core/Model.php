<?php
// FILE: /app/core/Model.php

/**
 * Base Model Class
 *
 * Provides common database operations for all models.
 * All queries use prepared statements for security.
 */
class Model {

    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find record by ID
     *
     * @param int $id
     * @param int|null $tenantId
     * @return array|null
     */
    public function find($id, $tenantId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $params = [$id];

        if ($tenantId !== null && $this->hasTenantId()) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find all records with optional filters
     *
     * @param array $filters
     * @param int|null $tenantId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll($filters = [], $tenantId = null, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($tenantId !== null && $this->hasTenantId()) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        foreach ($filters as $key => $value) {
            if ($value !== null) {
                $sql .= " AND {$key} = ?";
                $params[] = $value;
            }
        }

        $sql .= " ORDER BY {$this->primaryKey} DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count records with filters
     *
     * @param array $filters
     * @param int|null $tenantId
     * @return int
     */
    public function count($filters = [], $tenantId = null) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($tenantId !== null && $this->hasTenantId()) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        foreach ($filters as $key => $value) {
            if ($value !== null) {
                $sql .= " AND {$key} = ?";
                $params[] = $value;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int) $result['total'];
    }

    /**
     * Create new record
     *
     * @param array $data
     * @return int Last insert ID
     */
    public function create($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            "INSERT INTO {$this->table} (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update record
     *
     * @param int $id
     * @param array $data
     * @param int|null $tenantId
     * @return bool
     */
    public function update($id, $data, $tenantId = null) {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $sets = [];
        $params = [];

        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = ?";
        $params[] = $id;

        if ($tenantId !== null && $this->hasTenantId()) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete record
     *
     * @param int $id
     * @param int|null $tenantId
     * @return bool
     */
    public function delete($id, $tenantId = null) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $params = [$id];

        if ($tenantId !== null && $this->hasTenantId()) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Execute raw query with parameters
     *
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    protected function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Check if table has tenant_id column
     *
     * @return bool
     */
    protected function hasTenantId() {
        // Tables without tenant_id: tenants, plans, platform admin users
        $nonTenantTables = ['tenants', 'plans'];
        return !in_array($this->table, $nonTenantTables);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->db->rollBack();
    }
}
