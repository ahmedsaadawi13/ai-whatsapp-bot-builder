<?php
// FILE: /app/models/Tenant.php

/**
 * Tenant Model
 *
 * Handles tenant (business/agency account) data.
 */
class Tenant extends Model {

    protected $table = 'tenants';

    /**
     * Find tenant by slug
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug) {
        $sql = "SELECT * FROM {$this->table} WHERE slug = ? LIMIT 1";
        $stmt = $this->query($sql, [$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get active tenants
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getActive($limit = 100, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get tenant with subscription
     *
     * @param int $id
     * @return array|null
     */
    public function getWithSubscription($id) {
        $sql = "SELECT t.*, ts.plan_id, ts.status as subscription_status,
                       p.name as plan_name, p.price as plan_price
                FROM {$this->table} t
                LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
                LEFT JOIN plans p ON ts.plan_id = p.id
                WHERE t.id = ?
                LIMIT 1";
        $stmt = $this->query($sql, [$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Generate unique slug from name
     *
     * @param string $name
     * @return string
     */
    public function generateSlug($name) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $originalSlug = $slug;
        $counter = 1;

        while ($this->findBySlug($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
