<?php
// FILE: /app/models/Conversation.php

/**
 * Conversation Model
 *
 * Handles conversation data.
 */
class Conversation extends Model {

    protected $table = 'conversations';

    /**
     * Find or create conversation
     *
     * @param int $contactId
     * @param int $channelId
     * @param int $tenantId
     * @param int|null $botId
     * @return array
     */
    public function findOrCreate($contactId, $channelId, $tenantId, $botId = null) {
        // Check for existing open conversation
        $sql = "SELECT * FROM {$this->table}
                WHERE contact_id = ? AND channel_id = ? AND tenant_id = ? AND status IN ('open', 'pending')
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->query($sql, [$contactId, $channelId, $tenantId]);
        $conversation = $stmt->fetch();

        if ($conversation) {
            // Update last message time
            $this->update($conversation['id'], ['last_message_at' => date('Y-m-d H:i:s')], $tenantId);
            return $conversation;
        }

        // Create new conversation
        $data = [
            'tenant_id' => $tenantId,
            'contact_id' => $contactId,
            'channel_id' => $channelId,
            'bot_id' => $botId,
            'status' => 'open',
            'last_message_at' => date('Y-m-d H:i:s')
        ];

        $conversationId = $this->create($data);
        return $this->find($conversationId, $tenantId);
    }

    /**
     * Get conversations for inbox
     *
     * @param int $tenantId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getForInbox($tenantId, $filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT conv.*, c.name as contact_name, c.phone_number,
                       ch.name as channel_name,
                       (SELECT COUNT(*) FROM messages WHERE conversation_id = conv.id AND direction = 'inbound' AND status != 'read') as unread_count
                FROM {$this->table} conv
                JOIN contacts c ON conv.contact_id = c.id
                JOIN channels ch ON conv.channel_id = ch.id
                WHERE conv.tenant_id = ?";
        $params = [$tenantId];

        if (isset($filters['status'])) {
            $sql .= " AND conv.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['assigned_to'])) {
            $sql .= " AND conv.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }

        $sql .= " ORDER BY conv.last_message_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get conversation with details
     *
     * @param int $id
     * @param int $tenantId
     * @return array|null
     */
    public function getWithDetails($id, $tenantId) {
        $sql = "SELECT conv.*, c.name as contact_name, c.phone_number, c.email,
                       ch.name as channel_name, ch.phone_number as channel_phone,
                       b.name as bot_name,
                       u.name as assigned_to_name
                FROM {$this->table} conv
                JOIN contacts c ON conv.contact_id = c.id
                JOIN channels ch ON conv.channel_id = ch.id
                LEFT JOIN bots b ON conv.bot_id = b.id
                LEFT JOIN users u ON conv.assigned_to = u.id
                WHERE conv.id = ? AND conv.tenant_id = ?
                LIMIT 1";
        $stmt = $this->query($sql, [$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }
}
