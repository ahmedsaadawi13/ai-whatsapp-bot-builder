<?php
// FILE: /app/models/Message.php

/**
 * Message Model - inbound/outbound messages of a conversation.
 * The messages table has created_at only (messages are immutable logs).
 */
class Message extends Model {

    protected $table = 'messages';
    protected $hasUpdatedAt = false;

    public function getByConversation($conversationId, $tenantId, $limit = 500, $offset = 0) {
        $sql = "SELECT m.*, u.name AS sent_by_name, f.name AS flow_name
                FROM {$this->table} m
                LEFT JOIN users u ON m.sent_by_user_id = u.id
                LEFT JOIN flows f ON m.flow_id = f.id
                WHERE m.conversation_id = ? AND m.tenant_id = ?
                ORDER BY m.created_at ASC, m.id ASC " . $this->limitClause($limit, $offset);
        return $this->query($sql, [$conversationId, $tenantId])->fetchAll();
    }

    /**
     * Store a message and bump the conversation's last_message_at.
     * The conversation must belong to the same tenant (enforced here).
     */
    public function createMessage($data) {
        $conversationModel = new Conversation();
        $conversation = $conversationModel->find((int) $data['conversation_id'], (int) $data['tenant_id']);
        if (!$conversation) {
            throw new InvalidArgumentException('Conversation does not belong to this tenant');
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }

        $id = $this->create($data);
        $conversationModel->touch($conversation['id'], $data['tenant_id']);
        return $id;
    }

    public function markConversationRead($conversationId, $tenantId) {
        $this->query(
            "UPDATE {$this->table} SET status = 'read'
             WHERE conversation_id = ? AND tenant_id = ? AND direction = 'inbound' AND status != 'read'",
            [$conversationId, $tenantId]
        );
    }

    public function getStatistics($tenantId, $startDate, $endDate) {
        $sql = "SELECT
                    COUNT(*) AS total_messages,
                    COALESCE(SUM(direction = 'inbound'), 0) AS inbound_count,
                    COALESCE(SUM(direction = 'outbound'), 0) AS outbound_count,
                    COALESCE(SUM(triggered_by = 'ai'), 0) AS ai_count,
                    COALESCE(SUM(triggered_by = 'flow'), 0) AS flow_count,
                    COALESCE(SUM(triggered_by = 'agent'), 0) AS agent_count,
                    COALESCE(SUM(triggered_by = 'broadcast'), 0) AS broadcast_count
                FROM {$this->table}
                WHERE tenant_id = ? AND created_at BETWEEN ? AND ?";
        $row = $this->query($sql, [$tenantId, $startDate, $endDate])->fetch();
        return array_map('intval', $row);
    }

    /** Messages counted against the monthly quota (all directions). */
    public function countThisMonth($tenantId) {
        $stmt = $this->query(
            "SELECT COUNT(*) AS c FROM {$this->table} m
             WHERE m.tenant_id = ? AND m.created_at >= ?
               AND m.conversation_id NOT IN (
                   SELECT cv.id FROM conversations cv JOIN contacts c ON c.id = cv.contact_id
                   WHERE cv.tenant_id = ? AND c.phone_number = ?
               )",
            [$tenantId, date('Y-m-01 00:00:00'), $tenantId, MessageProcessor::SIMULATOR_PHONE]
        );
        return (int) $stmt->fetch()['c'];
    }
}
