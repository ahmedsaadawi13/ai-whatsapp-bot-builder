<?php
// FILE: /app/models/Contact.php

/**
 * Contact Model - phone number is unique per tenant.
 */
class Contact extends Model {

    protected $table = 'contacts';
    protected $searchable = ['name', 'phone_number', 'email'];

    /**
     * Find a contact by phone or create it.
     *
     * @return array [array $contact, bool $created]
     */
    public function findOrCreate($phoneNumber, $tenantId, $additionalData = []) {
        $contact = $this->findByPhoneNumber($phoneNumber, $tenantId);
        $now = date('Y-m-d H:i:s');

        if ($contact) {
            $updates = ['last_contact_at' => $now];
            if (empty($contact['name']) && !empty($additionalData['name'])) {
                $updates['name'] = $additionalData['name'];
            }
            $this->update($contact['id'], $updates, $tenantId);
            return [array_merge($contact, $updates), false];
        }

        // Defaults first; caller data (e.g. name, status) may override them.
        $data = array_merge(['status' => 'active'], array_filter($additionalData, function ($v) {
            return $v !== null && $v !== '';
        }), [
            'tenant_id' => $tenantId,
            'phone_number' => $phoneNumber,
            'last_contact_at' => $now
        ]);

        try {
            $id = $this->create($data);
        } catch (PDOException $e) {
            // Unique (tenant, phone) race: another request created it first.
            if ($e->getCode() === '23000') {
                return [$this->findByPhoneNumber($phoneNumber, $tenantId), false];
            }
            throw $e;
        }

        return [$this->find($id, $tenantId), true];
    }

    public function findByPhoneNumber($phoneNumber, $tenantId) {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE phone_number = ? AND tenant_id = ? LIMIT 1",
            [$phoneNumber, $tenantId]
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * List contacts with tag names; supports status, tag and search filters.
     */
    public function listWithTags($tenantId, $filters, $search, $limit, $offset) {
        list($where, $params) = $this->buildListWhere($tenantId, $filters, $search);
        $sql = "SELECT c.*,
                       (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ')
                          FROM contact_tags ct JOIN tags t ON t.id = ct.tag_id
                         WHERE ct.contact_id = c.id) AS tags
                FROM {$this->table} c
                WHERE {$where}
                ORDER BY c.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    public function countWithTags($tenantId, $filters, $search) {
        list($where, $params) = $this->buildListWhere($tenantId, $filters, $search);
        return (int) $this->query("SELECT COUNT(*) AS total FROM {$this->table} c WHERE {$where}", $params)->fetch()['total'];
    }

    private function buildListWhere($tenantId, $filters, $search) {
        $tagId = isset($filters['tag_id']) ? (int) $filters['tag_id'] : 0;
        unset($filters['tag_id']);

        list($where, $params) = $this->buildWhere($filters, $tenantId, $search, 'c');
        if ($tagId > 0) {
            $where .= " AND EXISTS (SELECT 1 FROM contact_tags ct WHERE ct.contact_id = c.id AND ct.tag_id = ?)";
            $params[] = $tagId;
        }
        return [$where, $params];
    }

    /**
     * Contacts matching a broadcast segment.
     * $filter: {"type":"all"} | {"type":"tag","tag_id":N} | {"type":"custom","field":"x","value":"y"}
     * Unsubscribed and blocked contacts are always excluded.
     */
    public function getSegment($tenantId, $filter) {
        $sql = "SELECT c.* FROM {$this->table} c WHERE c.tenant_id = ? AND c.status = 'active'";
        $params = [$tenantId];
        $type = isset($filter['type']) ? $filter['type'] : 'all';

        if ($type === 'tag' && !empty($filter['tag_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM contact_tags ct WHERE ct.contact_id = c.id AND ct.tag_id = ?)";
            $params[] = (int) $filter['tag_id'];
        }

        $contacts = $this->query($sql . ' ORDER BY c.id', $params)->fetchAll();

        if ($type === 'custom' && !empty($filter['field'])) {
            $field = $filter['field'];
            $value = isset($filter['value']) ? Flow::normalize($filter['value']) : '';
            $contacts = array_values(array_filter($contacts, function ($c) use ($field, $value) {
                $custom = json_array($c['custom_fields']);
                if (in_array($field, ['name', 'email', 'phone_number'], true)) {
                    return Flow::normalize($c[$field]) === $value;
                }
                return isset($custom[$field]) && Flow::normalize($custom[$field]) === $value;
            }));
        }

        return $contacts;
    }

    /** Count of segment members including unsubscribed (for the "excluded" stat). */
    public function countSegmentAll($tenantId, $filter) {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} c WHERE c.tenant_id = ?";
        $params = [$tenantId];
        if (isset($filter['type']) && $filter['type'] === 'tag' && !empty($filter['tag_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM contact_tags ct WHERE ct.contact_id = c.id AND ct.tag_id = ?)";
            $params[] = (int) $filter['tag_id'];
        }
        return (int) $this->query($sql, $params)->fetch()['c'];
    }
}
