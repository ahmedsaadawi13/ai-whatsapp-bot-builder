<?php
// FILE: /app/models/Tenant.php

/**
 * Tenant Model - a business / agency account.
 */
class Tenant extends Model {

    protected $table = 'tenants';
    protected $tenantScoped = false;
    protected $searchable = ['name', 'email', 'slug'];

    public function findBySlug($slug) {
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE slug = ? LIMIT 1", [$slug]);
        return $stmt->fetch() ?: null;
    }

    /** Tenant row plus its active plan. */
    public function getWithSubscription($id) {
        $sql = "SELECT t.*, ts.id AS subscription_id, ts.plan_id, ts.status AS subscription_status,
                       ts.expires_at, p.name AS plan_name, p.price AS plan_price
                FROM {$this->table} t
                LEFT JOIN tenant_subscriptions ts ON ts.id = (
                    SELECT MAX(id) FROM tenant_subscriptions WHERE tenant_id = t.id AND status = 'active'
                )
                LEFT JOIN plans p ON ts.plan_id = p.id
                WHERE t.id = ?
                LIMIT 1";
        return $this->query($sql, [$id])->fetch() ?: null;
    }

    /** Platform admin list: tenants with plan and resource counts. */
    public function listWithStats($filters, $search, $limit, $offset) {
        list($where, $params) = $this->buildWhere($filters, null, $search, 't');
        $sql = "SELECT t.*, p.name AS plan_name,
                       (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS user_count,
                       (SELECT COUNT(*) FROM bots b WHERE b.tenant_id = t.id) AS bot_count,
                       (SELECT COUNT(*) FROM contacts c WHERE c.tenant_id = t.id) AS contact_count
                FROM {$this->table} t
                LEFT JOIN tenant_subscriptions ts ON ts.id = (
                    SELECT MAX(id) FROM tenant_subscriptions WHERE tenant_id = t.id AND status = 'active'
                )
                LEFT JOIN plans p ON p.id = ts.plan_id
                WHERE {$where}
                ORDER BY t.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    public function generateSlug($name) {
        $base = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        if ($base === '') {
            $base = 'tenant';
        }
        $base = substr($base, 0, 80);
        $slug = $base;
        $counter = 2;
        while ($this->findBySlug($slug)) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }
}
