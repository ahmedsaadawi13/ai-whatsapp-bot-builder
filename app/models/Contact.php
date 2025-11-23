<?php
// FILE: /app/models/Contact.php

/**
 * Contact Model
 *
 * Handles contact data.
 */
class Contact extends Model {

    protected $table = 'contacts';

    /**
     * Find or create contact by phone number
     *
     * @param string $phoneNumber
     * @param int $tenantId
     * @param array $additionalData
     * @return array
     */
    public function findOrCreate($phoneNumber, $tenantId, $additionalData = []) {
        $contact = $this->findByPhoneNumber($phoneNumber, $tenantId);

        if ($contact) {
            // Update last contact time
            $this->update($contact['id'], ['last_contact_at' => date('Y-m-d H:i:s')], $tenantId);
            return $contact;
        }

        // Create new contact
        $data = array_merge([
            'tenant_id' => $tenantId,
            'phone_number' => $phoneNumber,
            'last_contact_at' => date('Y-m-d H:i:s')
        ], $additionalData);

        $contactId = $this->create($data);
        return $this->find($contactId, $tenantId);
    }

    /**
     * Find contact by phone number
     *
     * @param string $phoneNumber
     * @param int $tenantId
     * @return array|null
     */
    public function findByPhoneNumber($phoneNumber, $tenantId) {
        $sql = "SELECT * FROM {$this->table} WHERE phone_number = ? AND tenant_id = ? LIMIT 1";
        $stmt = $this->query($sql, [$phoneNumber, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get contacts with tags
     *
     * @param int $tenantId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getWithTags($tenantId, $filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT c.*, GROUP_CONCAT(t.name) as tags
                FROM {$this->table} c
                LEFT JOIN contact_tags ct ON c.id = ct.contact_id
                LEFT JOIN tags t ON ct.tag_id = t.id
                WHERE c.tenant_id = ?";
        $params = [$tenantId];

        if (isset($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['search'])) {
            $sql .= " AND (c.name LIKE ? OR c.phone_number LIKE ? OR c.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " GROUP BY c.id ORDER BY c.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
}
