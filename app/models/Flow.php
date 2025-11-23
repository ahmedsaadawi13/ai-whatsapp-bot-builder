<?php
// FILE: /app/models/Flow.php

/**
 * Flow Model
 *
 * Handles bot flow configuration.
 */
class Flow extends Model {

    protected $table = 'flows';

    /**
     * Get flows by bot
     *
     * @param int $botId
     * @param int $tenantId
     * @return array
     */
    public function getByBot($botId, $tenantId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE bot_id = ? AND tenant_id = ?
                ORDER BY priority DESC, id ASC";
        $stmt = $this->query($sql, [$botId, $tenantId]);
        return $stmt->fetchAll();
    }

    /**
     * Find matching flow for message
     *
     * @param int $botId
     * @param string $message
     * @param int $tenantId
     * @return array|null
     */
    public function findMatchingFlow($botId, $message, $tenantId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE bot_id = ? AND tenant_id = ? AND status = 'active'
                ORDER BY priority DESC";
        $stmt = $this->query($sql, [$botId, $tenantId]);
        $flows = $stmt->fetchAll();

        $messageLower = strtolower(trim($message));

        foreach ($flows as $flow) {
            if ($flow['trigger_type'] === 'keyword') {
                $keywords = explode(',', strtolower($flow['trigger_value']));
                foreach ($keywords as $keyword) {
                    if ($messageLower === trim($keyword)) {
                        return $flow;
                    }
                }
            } elseif ($flow['trigger_type'] === 'message_contains') {
                if (strpos($messageLower, strtolower($flow['trigger_value'])) !== false) {
                    return $flow;
                }
            } elseif ($flow['trigger_type'] === 'default') {
                // Default flow is returned if no other matches
                $defaultFlow = $flow;
            }
        }

        return isset($defaultFlow) ? $defaultFlow : null;
    }
}
