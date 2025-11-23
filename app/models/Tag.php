<?php
// FILE: /app/models/Tag.php

/**
 * Tag Model
 *
 * Handles contact tags.
 */
class Tag extends Model {

    protected $table = 'tags';

    /**
     * Find or create tag by name
     *
     * @param string $name
     * @param int $tenantId
     * @param string $color
     * @return array
     */
    public function findOrCreate($name, $tenantId, $color = '#000000') {
        $sql = "SELECT * FROM {$this->table} WHERE name = ? AND tenant_id = ? LIMIT 1";
        $stmt = $this->query($sql, [$name, $tenantId]);
        $tag = $stmt->fetch();

        if ($tag) {
            return $tag;
        }

        $tagId = $this->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'color' => $color
        ]);

        return $this->find($tagId, $tenantId);
    }

    /**
     * Attach tag to contact
     *
     * @param int $contactId
     * @param int $tagId
     * @return bool
     */
    public function attachToContact($contactId, $tagId) {
        try {
            $sql = "INSERT INTO contact_tags (contact_id, tag_id, created_at) VALUES (?, ?, NOW())";
            $this->query($sql, [$contactId, $tagId]);
            return true;
        } catch (Exception $e) {
            // Tag already attached or error
            return false;
        }
    }

    /**
     * Detach tag from contact
     *
     * @param int $contactId
     * @param int $tagId
     * @return bool
     */
    public function detachFromContact($contactId, $tagId) {
        $sql = "DELETE FROM contact_tags WHERE contact_id = ? AND tag_id = ?";
        $stmt = $this->query($sql, [$contactId, $tagId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get tags for contact
     *
     * @param int $contactId
     * @return array
     */
    public function getByContact($contactId) {
        $sql = "SELECT t.* FROM {$this->table} t
                JOIN contact_tags ct ON t.id = ct.tag_id
                WHERE ct.contact_id = ?";
        $stmt = $this->query($sql, [$contactId]);
        return $stmt->fetchAll();
    }
}
