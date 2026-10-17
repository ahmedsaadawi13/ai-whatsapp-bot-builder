<?php
// FILE: /app/models/Webhook.php

/**
 * Webhook Model - outgoing webhook subscriptions (per tenant, optionally per bot).
 */
class Webhook extends Model {

    const EVENTS = ['message.received', 'message.sent', 'conversation.opened', 'conversation.closed', 'contact.created'];

    protected $table = 'webhooks';
    protected $searchable = ['name', 'url'];

    public function listWithStats($tenantId, $filters, $search, $limit, $offset) {
        list($where, $params) = $this->buildWhere($filters, $tenantId, $search, 'w');
        $sql = "SELECT w.*, b.name AS bot_name,
                       (SELECT COUNT(*) FROM webhook_logs l WHERE l.webhook_id = w.id) AS delivery_count,
                       (SELECT COUNT(*) FROM webhook_logs l WHERE l.webhook_id = w.id AND l.status = 'failed') AS failure_count
                FROM {$this->table} w
                LEFT JOIN bots b ON b.id = w.bot_id
                WHERE {$where}
                ORDER BY w.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    /** Active webhooks subscribed to an event (tenant-wide or for this bot). */
    public function getForEvent($event, $tenantId, $botId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE tenant_id = ? AND status = 'active' AND events LIKE ?";
        $params = [$tenantId, '%"' . $event . '"%'];
        if ($botId !== null) {
            $sql .= " AND (bot_id = ? OR bot_id IS NULL)";
            $params[] = $botId;
        } else {
            $sql .= " AND bot_id IS NULL";
        }
        return $this->query($sql, $params)->fetchAll();
    }
}
