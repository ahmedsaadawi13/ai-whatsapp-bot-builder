<?php
// FILE: /app/models/Message.php

/**
 * Message Model
 *
 * Handles message data.
 */
class Message extends Model {

    protected $table = 'messages';

    /**
     * Get messages by conversation
     *
     * @param int $conversationId
     * @param int $tenantId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByConversation($conversationId, $tenantId, $limit = 100, $offset = 0) {
        $sql = "SELECT m.*, u.name as sent_by_name
                FROM {$this->table} m
                LEFT JOIN users u ON m.sent_by_user_id = u.id
                WHERE m.conversation_id = ? AND m.tenant_id = ?
                ORDER BY m.id ASC
                LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$conversationId, $tenantId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Create message
     *
     * @param array $data
     * @return int Message ID
     */
    public function createMessage($data) {
        $messageId = $this->create($data);

        // Update conversation's last message timestamp
        if (isset($data['conversation_id'])) {
            require_once __DIR__ . '/Conversation.php';
            $conversationModel = new Conversation();
            $conversationModel->update(
                $data['conversation_id'],
                ['last_message_at' => date('Y-m-d H:i:s')],
                $data['tenant_id']
            );
        }

        return $messageId;
    }

    /**
     * Get message statistics
     *
     * @param int $tenantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getStatistics($tenantId, $startDate, $endDate) {
        $sql = "SELECT
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_count,
                    SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_count,
                    SUM(CASE WHEN triggered_by = 'ai' THEN 1 ELSE 0 END) as ai_count,
                    SUM(CASE WHEN triggered_by = 'flow' THEN 1 ELSE 0 END) as flow_count,
                    SUM(CASE WHEN triggered_by = 'agent' THEN 1 ELSE 0 END) as agent_count
                FROM {$this->table}
                WHERE tenant_id = ? AND created_at BETWEEN ? AND ?";
        $stmt = $this->query($sql, [$tenantId, $startDate, $endDate]);
        return $stmt->fetch();
    }
}
