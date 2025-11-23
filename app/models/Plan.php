<?php
// FILE: /app/models/Plan.php

/**
 * Plan Model
 *
 * Handles subscription plan data.
 */
class Plan extends Model {

    protected $table = 'plans';

    /**
     * Get all active plans
     *
     * @return array
     */
    public function getActivePlans() {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY price ASC";
        $stmt = $this->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Check if table has tenant_id column
     *
     * @return bool
     */
    protected function hasTenantId() {
        return false; // Plans table doesn't have tenant_id
    }
}
