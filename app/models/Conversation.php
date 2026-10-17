<?php
// FILE: /app/models/Conversation.php

/**
 * Conversation Model - a thread between a contact and a channel/bot.
 */
class Conversation extends Model {

    const STATUSES = ['open', 'pending', 'closed'];

    protected $table = 'conversations';

    /**
     * Reuse the latest open/pending conversation for contact+channel, or open a new one.
     *
     * @return array [array $conversation, bool $created]
     */
    public function findOrCreate($contactId, $channelId, $tenantId, $botId = null) {
        $stmt = $this->query(
            "SELECT * FROM {$this->table}
             WHERE contact_id = ? AND channel_id = ? AND tenant_id = ? AND status IN ('open', 'pending')
             ORDER BY id DESC LIMIT 1",
            [$contactId, $channelId, $tenantId]
        );
        $conversation = $stmt->fetch();
        if ($conversation) {
            return [$conversation, false];
        }

        $id = $this->create([
            'tenant_id' => $tenantId,
            'contact_id' => $contactId,
            'channel_id' => $channelId,
            'bot_id' => $botId,
            'status' => 'open',
            'last_message_at' => date('Y-m-d H:i:s')
        ]);
        return [$this->find($id, $tenantId), true];
    }

    /**
     * Inbox list with unread counts. Filters: status, assigned_to, channel_id, search.
     */
    public function getForInbox($tenantId, $filters = [], $limit = 20, $offset = 0) {
        list($where, $params) = $this->inboxWhere($tenantId, $filters);
        $sql = "SELECT conv.*, c.name AS contact_name, c.phone_number,
                       ch.name AS channel_name, b.name AS bot_name, u.name AS assigned_to_name,
                       COALESCE(unread.cnt, 0) AS unread_count,
                       (SELECT m.content FROM messages m WHERE m.conversation_id = conv.id ORDER BY m.id DESC LIMIT 1) AS last_message
                FROM {$this->table} conv
                JOIN contacts c ON conv.contact_id = c.id
                JOIN channels ch ON conv.channel_id = ch.id
                LEFT JOIN bots b ON conv.bot_id = b.id
                LEFT JOIN users u ON conv.assigned_to = u.id
                LEFT JOIN (
                    SELECT conversation_id, COUNT(*) AS cnt FROM messages
                    WHERE tenant_id = ? AND direction = 'inbound' AND status != 'read'
                    GROUP BY conversation_id
                ) unread ON unread.conversation_id = conv.id
                WHERE {$where}
                ORDER BY conv.last_message_at DESC, conv.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, array_merge([$tenantId], $params))->fetchAll();
    }

    public function countForInbox($tenantId, $filters = []) {
        list($where, $params) = $this->inboxWhere($tenantId, $filters);
        $sql = "SELECT COUNT(*) AS total FROM {$this->table} conv JOIN contacts c ON conv.contact_id = c.id WHERE {$where}";
        return (int) $this->query($sql, $params)->fetch()['total'];
    }

    private function inboxWhere($tenantId, $filters) {
        $where = ['conv.tenant_id = ?'];
        $params = [$tenantId];
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'conv.status = ?';
            $params[] = $filters['status'];
        }
        if (isset($filters['assigned_to']) && $filters['assigned_to'] !== '') {
            if ($filters['assigned_to'] === 'none') {
                $where[] = 'conv.assigned_to IS NULL';
            } else {
                $where[] = 'conv.assigned_to = ?';
                $params[] = (int) $filters['assigned_to'];
            }
        }
        if (!empty($filters['channel_id'])) {
            $where[] = 'conv.channel_id = ?';
            $params[] = (int) $filters['channel_id'];
        }
        if (!empty($filters['contact_id'])) {
            $where[] = 'conv.contact_id = ?';
            $params[] = (int) $filters['contact_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(c.name LIKE ? OR c.phone_number LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        return [implode(' AND ', $where), $params];
    }

    public function getWithDetails($id, $tenantId) {
        $sql = "SELECT conv.*, c.name AS contact_name, c.phone_number, c.email, c.custom_fields,
                       ch.name AS channel_name, ch.phone_number AS channel_phone,
                       b.name AS bot_name, u.name AS assigned_to_name
                FROM {$this->table} conv
                JOIN contacts c ON conv.contact_id = c.id
                JOIN channels ch ON conv.channel_id = ch.id
                LEFT JOIN bots b ON conv.bot_id = b.id
                LEFT JOIN users u ON conv.assigned_to = u.id
                WHERE conv.id = ? AND conv.tenant_id = ?
                LIMIT 1";
        return $this->query($sql, [$id, $tenantId])->fetch() ?: null;
    }

    public function setStatus($id, $tenantId, $status) {
        return $this->update($id, [
            'status' => $status,
            'closed_at' => $status === 'closed' ? date('Y-m-d H:i:s') : null
        ], $tenantId);
    }

    public function touch($id, $tenantId) {
        $this->query(
            "UPDATE {$this->table} SET last_message_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id, $tenantId]
        );
    }

    public function countByStatus($tenantId) {
        $rows = $this->query(
            "SELECT status, COUNT(*) AS total FROM {$this->table} WHERE tenant_id = ? GROUP BY status",
            [$tenantId]
        )->fetchAll();
        $result = ['open' => 0, 'pending' => 0, 'closed' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }

    /** New conversations per tenant since a time (daily summary). */
    public function countCreatedSince($tenantId, $since) {
        $stmt = $this->query(
            "SELECT COUNT(*) AS c FROM {$this->table} WHERE tenant_id = ? AND created_at >= ?",
            [$tenantId, $since]
        );
        return (int) $stmt->fetch()['c'];
    }
}
