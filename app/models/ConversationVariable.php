<?php
// FILE: /app/models/ConversationVariable.php

/**
 * ConversationVariable Model
 *
 * Handles conversation context variables.
 */
class ConversationVariable extends Model {

    protected $table = 'conversation_variables';

    /**
     * Set variable value
     *
     * @param int $conversationId
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setVariable($conversationId, $key, $value) {
        $sql = "SELECT * FROM {$this->table} WHERE conversation_id = ? AND variable_key = ? LIMIT 1";
        $stmt = $this->query($sql, [$conversationId, $key]);
        $existing = $stmt->fetch();

        if ($existing) {
            $sql = "UPDATE {$this->table} SET variable_value = ?, updated_at = NOW()
                    WHERE conversation_id = ? AND variable_key = ?";
            $this->query($sql, [$value, $conversationId, $key]);
        } else {
            $this->create([
                'conversation_id' => $conversationId,
                'variable_key' => $key,
                'variable_value' => $value
            ]);
        }

        return true;
    }

    /**
     * Get variable value
     *
     * @param int $conversationId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getVariable($conversationId, $key, $default = null) {
        $sql = "SELECT variable_value FROM {$this->table}
                WHERE conversation_id = ? AND variable_key = ? LIMIT 1";
        $stmt = $this->query($sql, [$conversationId, $key]);
        $result = $stmt->fetch();

        return $result ? $result['variable_value'] : $default;
    }

    /**
     * Get all variables for conversation
     *
     * @param int $conversationId
     * @return array Key-value pairs
     */
    public function getAllVariables($conversationId) {
        $sql = "SELECT variable_key, variable_value FROM {$this->table}
                WHERE conversation_id = ?";
        $stmt = $this->query($sql, [$conversationId]);
        $rows = $stmt->fetchAll();

        $variables = [];
        foreach ($rows as $row) {
            $variables[$row['variable_key']] = $row['variable_value'];
        }

        return $variables;
    }

    /**
     * Check if table has tenant_id column
     *
     * @return bool
     */
    protected function hasTenantId() {
        return false; // ConversationVariable doesn't have tenant_id directly
    }
}
