<?php
// FILE: /app/models/ConversationVariable.php

/**
 * ConversationVariable Model - key/value context per conversation
 * (e.g. name, email, order_id). Keys starting with "_" are internal
 * flow-engine state (awaiting input / resume position).
 *
 * Not tenant-scoped directly: callers must first load the conversation
 * with its tenant check.
 */
class ConversationVariable extends Model {

    const MAX_VALUE_LENGTH = 2000;

    protected $table = 'conversation_variables';
    protected $tenantScoped = false;

    public function setVariable($conversationId, $key, $value) {
        $key = substr(preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key), 0, 100);
        if ($key === '') {
            return false;
        }
        $value = mb_substr((string) $value, 0, self::MAX_VALUE_LENGTH, 'UTF-8');
        $now = date('Y-m-d H:i:s');
        $this->query(
            "INSERT INTO {$this->table} (conversation_id, variable_key, variable_value, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE variable_value = VALUES(variable_value), updated_at = VALUES(updated_at)",
            [$conversationId, $key, $value, $now, $now]
        );
        return true;
    }

    public function getVariable($conversationId, $key, $default = null) {
        $stmt = $this->query(
            "SELECT variable_value FROM {$this->table} WHERE conversation_id = ? AND variable_key = ? LIMIT 1",
            [$conversationId, $key]
        );
        $row = $stmt->fetch();
        return $row ? $row['variable_value'] : $default;
    }

    public function deleteVariables($conversationId, $keys) {
        foreach ($keys as $key) {
            $this->query(
                "DELETE FROM {$this->table} WHERE conversation_id = ? AND variable_key = ?",
                [$conversationId, $key]
            );
        }
    }

    /** All variables as key => value (internal "_" keys optional). */
    public function getAllVariables($conversationId, $includeInternal = true) {
        $rows = $this->query(
            "SELECT variable_key, variable_value FROM {$this->table} WHERE conversation_id = ? ORDER BY variable_key",
            [$conversationId]
        )->fetchAll();

        $variables = [];
        foreach ($rows as $row) {
            if (!$includeInternal && strpos($row['variable_key'], '_') === 0) {
                continue;
            }
            $variables[$row['variable_key']] = $row['variable_value'];
        }
        return $variables;
    }
}
