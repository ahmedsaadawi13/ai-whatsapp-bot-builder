<?php
// FILE: /app/models/KnowledgeBase.php

/**
 * KnowledgeBase Model
 *
 * Handles knowledge base entries for AI responses.
 */
class KnowledgeBase extends Model {

    protected $table = 'knowledge_base';

    /**
     * Search knowledge base
     *
     * @param string $query
     * @param int $tenantId
     * @param int|null $botId
     * @param int $limit
     * @return array
     */
    public function search($query, $tenantId, $botId = null, $limit = 5) {
        $sql = "SELECT *, MATCH(question, answer) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM {$this->table}
                WHERE tenant_id = ? AND status = 'active'";
        $params = [$query, $tenantId];

        if ($botId !== null) {
            $sql .= " AND (bot_id = ? OR bot_id IS NULL)";
            $params[] = $botId;
        }

        $sql .= " HAVING relevance > 0 ORDER BY relevance DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get by bot
     *
     * @param int|null $botId
     * @param int $tenantId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByBot($botId, $tenantId, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = ? AND (bot_id = ? OR bot_id IS NULL)
                ORDER BY id DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$tenantId, $botId, $limit, $offset]);
        return $stmt->fetchAll();
    }
}
