<?php
// FILE: /app/models/FlowStep.php

/**
 * FlowStep Model
 *
 * Handles flow step actions.
 */
class FlowStep extends Model {

    protected $table = 'flow_steps';

    /**
     * Get steps by flow
     *
     * @param int $flowId
     * @param int $tenantId
     * @return array
     */
    public function getByFlow($flowId, $tenantId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE flow_id = ? AND tenant_id = ?
                ORDER BY step_order ASC";
        $stmt = $this->query($sql, [$flowId, $tenantId]);
        return $stmt->fetchAll();
    }
}
