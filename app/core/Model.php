<?php
// FILE: /app/core/Model.php

/**
 * Base Model Class
 *
 * Common CRUD helpers for all models. Every query uses prepared statements.
 * When $tenantScoped is true and a tenant ID is passed, queries are
 * automatically restricted to that tenant's rows.
 */
class Model {

    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    /** Does the table have a tenant_id column? */
    protected $tenantScoped = true;

    /** Does the table have an updated_at column? */
    protected $hasUpdatedAt = true;

    /** Columns used by the free-text "search" filter on list pages. */
    protected $searchable = [];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find one record by ID (optionally restricted to a tenant).
     */
    public function find($id, $tenantId = null) {
        $this->guardTenantScope($tenantId);
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $params = [$id];

        if ($tenantId !== null && $this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        $stmt = $this->query($sql . ' LIMIT 1', $params);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find records matching exact-value filters plus an optional search term.
     */
    public function findAll($filters = [], $tenantId = null, $limit = 100, $offset = 0, $search = null) {
        $this->guardTenantScope($tenantId);
        list($where, $params) = $this->buildWhere($filters, $tenantId, $search);
        $sql = "SELECT * FROM {$this->table} WHERE {$where}
                ORDER BY {$this->primaryKey} DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Count records using the same filters as findAll().
     */
    public function count($filters = [], $tenantId = null, $search = null) {
        $this->guardTenantScope($tenantId);
        list($where, $params) = $this->buildWhere($filters, $tenantId, $search);
        $stmt = $this->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE {$where}", $params);
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Insert a record and return its ID.
     */
    public function create($data) {
        $now = date('Y-m-d H:i:s');
        if (!isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        if ($this->hasUpdatedAt && !isset($data['updated_at'])) {
            $data['updated_at'] = $now;
        }

        $columns = array_keys($data);
        $this->assertColumns($columns);

        $sql = sprintf(
            "INSERT INTO {$this->table} (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );

        $this->query($sql, array_values($data));
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a record. Returns true when the statement ran.
     */
    public function update($id, $data, $tenantId = null) {
        $this->guardTenantScope($tenantId);
        if ($this->hasUpdatedAt) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $this->assertColumns(array_keys($data));

        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = ?";
        $params[] = $id;

        if ($tenantId !== null && $this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        $this->query($sql, $params);
        return true;
    }

    /**
     * Delete a record. Returns true when a row was removed.
     */
    public function delete($id, $tenantId = null) {
        $this->guardTenantScope($tenantId);
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $params = [$id];

        if ($tenantId !== null && $this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }

        return $this->query($sql, $params)->rowCount() > 0;
    }

    /**
     * Fail closed: a tenant ID passed to a table without tenant_id would
     * otherwise be silently ignored, leaking data across tenants.
     */
    protected function guardTenantScope($tenantId) {
        if ($tenantId !== null && !$this->tenantScoped) {
            throw new LogicException(get_class($this) . ' is not tenant-scoped; use its tenant-aware query methods');
        }
    }

    /**
     * Run a prepared statement.
     */
    protected function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Build a WHERE clause from tenant, exact filters and search.
     *
     * @return array [string $where, array $params]
     */
    protected function buildWhere($filters, $tenantId, $search, $alias = '') {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $where = ['1=1'];
        $params = [];

        if ($tenantId !== null && $this->tenantScoped) {
            $where[] = "{$prefix}tenant_id = ?";
            $params[] = $tenantId;
        }

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $this->assertColumns([$key]);
            $where[] = "{$prefix}{$key} = ?";
            $params[] = $value;
        }

        if ($search !== null && $search !== '' && !empty($this->searchable)) {
            $likes = [];
            foreach ($this->searchable as $column) {
                $likes[] = "{$prefix}{$column} LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * LIMIT/OFFSET are cast to integers and inlined (safe, and portable
     * across PDO emulated/native prepares).
     */
    protected function limitClause($limit, $offset = 0) {
        return 'LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) $offset);
    }

    /**
     * Only allow plain identifiers as column names.
     */
    protected function isValidColumn($columnName) {
        return is_string($columnName) && preg_match('/^[a-zA-Z0-9_]+$/', $columnName) === 1;
    }

    protected function assertColumns($columns) {
        foreach ($columns as $column) {
            if (!$this->isValidColumn($column)) {
                throw new InvalidArgumentException('Invalid column name');
            }
        }
    }

    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    public function commit() {
        $this->db->commit();
    }

    public function rollback() {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
