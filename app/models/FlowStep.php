<?php
// FILE: /app/models/FlowStep.php

/**
 * FlowStep Model - one action inside a flow, executed in step_order.
 */
class FlowStep extends Model {

    const ACTION_TYPES = ['send_text', 'send_media', 'ask_question', 'call_ai', 'call_webhook', 'set_variable', 'branch_condition'];

    protected $table = 'flow_steps';

    public function getByFlow($flowId, $tenantId) {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE flow_id = ? AND tenant_id = ? ORDER BY step_order ASC, id ASC",
            [$flowId, $tenantId]
        )->fetchAll();
    }

    public function nextOrder($flowId, $tenantId) {
        $stmt = $this->query(
            "SELECT COALESCE(MAX(step_order), 0) + 1 AS n FROM {$this->table} WHERE flow_id = ? AND tenant_id = ?",
            [$flowId, $tenantId]
        );
        return (int) $stmt->fetch()['n'];
    }

    /**
     * Swap a step with its neighbour ($direction = -1 up, +1 down).
     */
    public function move($step, $direction) {
        $steps = $this->getByFlow($step['flow_id'], $step['tenant_id']);

        // Renumber 1..n first so orders are unique and contiguous.
        $index = null;
        foreach ($steps as $i => $s) {
            $steps[$i]['step_order'] = $i + 1;
            if ((int) $s['id'] === (int) $step['id']) {
                $index = $i;
            }
        }
        $target = $index + $direction;
        if ($index === null || $target < 0 || $target >= count($steps)) {
            return false;
        }

        $tmp = $steps[$index]['step_order'];
        $steps[$index]['step_order'] = $steps[$target]['step_order'];
        $steps[$target]['step_order'] = $tmp;

        $this->beginTransaction();
        try {
            foreach ($steps as $s) {
                $this->query(
                    "UPDATE {$this->table} SET step_order = ? WHERE id = ? AND tenant_id = ?",
                    [$s['step_order'], $s['id'], $step['tenant_id']]
                );
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
        return true;
    }
}
