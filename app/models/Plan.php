<?php
// FILE: /app/models/Plan.php

/**
 * Plan Model - subscription plans with resource limits (global, not tenant-scoped).
 */
class Plan extends Model {

    protected $table = 'plans';
    protected $tenantScoped = false;

    public function getActivePlans() {
        return $this->query("SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY price ASC")->fetchAll();
    }

    public function findActive($id) {
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE id = ? AND status = 'active' LIMIT 1", [(int) $id]);
        return $stmt->fetch() ?: null;
    }
}
