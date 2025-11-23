<?php
// FILE: /app/models/Webhook.php

/**
 * Webhook Model
 *
 * Handles webhook configuration and logging.
 */
class Webhook extends Model {

    protected $table = 'webhooks';

    /**
     * Get webhooks for event
     *
     * @param string $event
     * @param int $tenantId
     * @param int|null $botId
     * @return array
     */
    public function getForEvent($event, $tenantId, $botId = null) {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = ? AND status = 'active'
                AND (events LIKE ? OR events LIKE ?)";
        $params = [$tenantId, '%"' . $event . '"%', "%" . $event . "%"];

        if ($botId !== null) {
            $sql .= " AND (bot_id = ? OR bot_id IS NULL)";
            $params[] = $botId;
        }

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Log webhook call
     *
     * @param int $webhookId
     * @param string $eventType
     * @param array $payload
     * @param int $responseCode
     * @param string $responseBody
     * @param string $status
     * @param int $attempt
     * @return int Log ID
     */
    public function logCall($webhookId, $eventType, $payload, $responseCode, $responseBody, $status, $attempt = 1) {
        require_once __DIR__ . '/WebhookLog.php';
        $logModel = new WebhookLog();

        return $logModel->create([
            'webhook_id' => $webhookId,
            'event_type' => $eventType,
            'payload' => json_encode($payload),
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'status' => $status,
            'attempt' => $attempt
        ]);
    }
}
