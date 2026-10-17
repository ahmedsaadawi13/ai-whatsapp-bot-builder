<?php
// FILE: /app/models/Flow.php

/**
 * Flow Model - a trigger plus an ordered list of steps.
 *
 * Trigger types:
 *  - keyword          message equals one of the comma-separated values
 *  - message_contains message contains one of the values
 *  - menu             message equals a menu option (e.g. "1" or "support")
 *  - button           message equals a button payload (simulated click)
 *  - default          used when nothing else matches
 */
class Flow extends Model {

    const TRIGGER_TYPES = ['keyword', 'message_contains', 'menu', 'button', 'default'];

    protected $table = 'flows';
    protected $searchable = ['name', 'trigger_value'];

    public function getByBot($botId, $tenantId) {
        $sql = "SELECT f.*, (SELECT COUNT(*) FROM flow_steps s WHERE s.flow_id = f.id) AS step_count
                FROM {$this->table} f
                WHERE f.bot_id = ? AND f.tenant_id = ?
                ORDER BY f.priority DESC, f.id ASC";
        return $this->query($sql, [$botId, $tenantId])->fetchAll();
    }

    /**
     * Find the best flow for an incoming message (highest priority first).
     */
    public function findMatchingFlow($botId, $message, $tenantId) {
        $flows = $this->query(
            "SELECT * FROM {$this->table}
             WHERE bot_id = ? AND tenant_id = ? AND status = 'active'
             ORDER BY priority DESC, id ASC",
            [$botId, $tenantId]
        )->fetchAll();

        $text = self::normalize($message);
        $defaultFlow = null;

        foreach ($flows as $flow) {
            if ($flow['trigger_type'] === 'default') {
                if ($defaultFlow === null) {
                    $defaultFlow = $flow;
                }
                continue;
            }
            foreach (self::triggerValues($flow['trigger_value']) as $value) {
                if ($flow['trigger_type'] === 'message_contains') {
                    if (strpos($text, $value) !== false) {
                        return $flow;
                    }
                } elseif ($text === $value) {
                    return $flow;
                }
            }
        }

        return $defaultFlow;
    }

    public static function triggerValues($triggerValue) {
        $values = [];
        foreach (explode(',', (string) $triggerValue) as $value) {
            $value = self::normalize($value);
            if ($value !== '') {
                $values[] = $value;
            }
        }
        return $values;
    }

    public static function normalize($text) {
        $text = function_exists('mb_strtolower') ? mb_strtolower((string) $text, 'UTF-8') : strtolower((string) $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /** Most used flows (top triggers) for analytics. */
    public function topFlows($tenantId, $start, $end, $limit = 5) {
        $sql = "SELECT f.id, f.name, f.trigger_type, f.trigger_value, COUNT(DISTINCT m.conversation_id, m.created_at) AS hits
                FROM messages m
                JOIN {$this->table} f ON f.id = m.flow_id
                WHERE m.tenant_id = ? AND m.direction = 'outbound' AND m.created_at BETWEEN ? AND ?
                GROUP BY f.id, f.name, f.trigger_type, f.trigger_value
                ORDER BY hits DESC " . $this->limitClause($limit);
        return $this->query($sql, [$tenantId, $start, $end])->fetchAll();
    }
}
