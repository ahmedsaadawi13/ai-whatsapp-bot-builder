<?php
// FILE: /app/models/Tag.php

/**
 * Tag Model - contact tags (many-to-many through contact_tags).
 */
class Tag extends Model {

    protected $table = 'tags';

    public function getForTenant($tenantId) {
        return $this->query(
            "SELECT t.*, (SELECT COUNT(*) FROM contact_tags ct WHERE ct.tag_id = t.id) AS contact_count
             FROM {$this->table} t WHERE t.tenant_id = ? ORDER BY t.name",
            [$tenantId]
        )->fetchAll();
    }

    public function findOrCreate($name, $tenantId, $color = '#6b7280') {
        $name = trim($name);
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE name = ? AND tenant_id = ? LIMIT 1", [$name, $tenantId]);
        $tag = $stmt->fetch();
        if ($tag) {
            return $tag;
        }
        $id = $this->create(['tenant_id' => $tenantId, 'name' => $name, 'color' => $color]);
        return $this->find($id, $tenantId);
    }

    public function getByContact($contactId, $tenantId) {
        return $this->query(
            "SELECT t.* FROM {$this->table} t
             JOIN contact_tags ct ON t.id = ct.tag_id
             WHERE ct.contact_id = ? AND t.tenant_id = ?
             ORDER BY t.name",
            [$contactId, $tenantId]
        )->fetchAll();
    }

    /**
     * Replace a contact's tags with the given names (creating tags as needed).
     * The contact must already be verified to belong to $tenantId.
     */
    public function syncContactTags($contactId, $tenantId, $names) {
        $ids = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name !== '' && strlen($name) <= 100) {
                $ids[] = (int) $this->findOrCreate($name, $tenantId)['id'];
            }
        }

        $this->query(
            "DELETE ct FROM contact_tags ct JOIN tags t ON t.id = ct.tag_id
             WHERE ct.contact_id = ? AND t.tenant_id = ?",
            [$contactId, $tenantId]
        );
        foreach (array_unique($ids) as $tagId) {
            $this->query(
                "INSERT IGNORE INTO contact_tags (contact_id, tag_id, created_at) VALUES (?, ?, ?)",
                [$contactId, $tagId, date('Y-m-d H:i:s')]
            );
        }
    }

    /** Parse "a, b, c" into a clean list. */
    public static function parseList($text) {
        return array_values(array_filter(array_map('trim', explode(',', (string) $text)), 'strlen'));
    }
}
