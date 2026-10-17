<?php
// FILE: /app/models/Broadcast.php

/**
 * Broadcast Model - a campaign sending a template/message to a contact segment.
 */
class Broadcast extends Model {

    protected $table = 'broadcasts';
    protected $searchable = ['name'];

    public function listWithDetails($tenantId, $filters, $search, $limit, $offset) {
        list($where, $params) = $this->buildWhere($filters, $tenantId, $search, 'b');
        $sql = "SELECT b.*, c.name AS channel_name, t.name AS template_name, bot.name AS bot_name
                FROM {$this->table} b
                JOIN channels c ON b.channel_id = c.id
                LEFT JOIN templates t ON b.template_id = t.id
                LEFT JOIN bots bot ON b.bot_id = bot.id
                WHERE {$where}
                ORDER BY b.id DESC " . $this->limitClause($limit, $offset);
        return $this->query($sql, $params)->fetchAll();
    }

    public function getWithStats($id, $tenantId) {
        $sql = "SELECT b.*, c.name AS channel_name, t.name AS template_name, bot.name AS bot_name
                FROM {$this->table} b
                JOIN channels c ON b.channel_id = c.id
                LEFT JOIN templates t ON b.template_id = t.id
                LEFT JOIN bots bot ON b.bot_id = bot.id
                WHERE b.id = ? AND b.tenant_id = ? LIMIT 1";
        $broadcast = $this->query($sql, [$id, $tenantId])->fetch();
        if (!$broadcast) {
            return null;
        }

        $stats = $this->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'sent'), 0) AS sent,
                    COALESCE(SUM(status = 'failed'), 0) AS failed,
                    COALESCE(SUM(status = 'pending'), 0) AS pending
             FROM broadcast_messages WHERE broadcast_id = ?",
            [$id]
        )->fetch();
        $broadcast['stats'] = array_map('intval', $stats);
        return $broadcast;
    }

    /** A "running" broadcast older than this is considered crashed and may be resumed. */
    const STALE_MINUTES = 15;

    /**
     * Scheduled broadcasts whose time has come, plus crashed runs to resume
     * (all tenants, used by cron).
     */
    public function getDue($limit = 20) {
        $sql = "SELECT * FROM {$this->table}
                WHERE (status = 'scheduled' AND scheduled_at <= ?)
                   OR (status = 'running' AND updated_at < ?)
                ORDER BY scheduled_at ASC " . $this->limitClause($limit);
        return $this->query($sql, [date('Y-m-d H:i:s'), $this->staleBefore()])->fetchAll();
    }

    /**
     * Atomically move draft/scheduled (or a stale running) broadcast to
     * running, so two workers never send the same campaign at once.
     * Already-sent recipients are never re-sent (see BroadcastMessage::queue).
     */
    public function claim($id, $tenantId) {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->query(
            "UPDATE {$this->table} SET status = 'running', started_at = COALESCE(started_at, ?), updated_at = ?
             WHERE id = ? AND tenant_id = ?
               AND (status IN ('draft', 'scheduled') OR (status = 'running' AND updated_at < ?))",
            [$now, $now, $id, $tenantId, $this->staleBefore()]
        );
        return $stmt->rowCount() === 1;
    }

    /** Heartbeat while sending so a long run is not mistaken for a crash. */
    public function heartbeat($id) {
        $this->query("UPDATE {$this->table} SET updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $id]);
    }

    public function isStale($broadcast) {
        return $broadcast['status'] === 'running' && $broadcast['updated_at'] < $this->staleBefore();
    }

    private function staleBefore() {
        return date('Y-m-d H:i:s', time() - self::STALE_MINUTES * 60);
    }

    public function performance($tenantId, $limit = 10) {
        $sql = "SELECT b.id, b.name, b.status, b.total_recipients, b.sent_count, b.failed_count, b.completed_at,
                       CASE WHEN b.total_recipients > 0 THEN ROUND(b.sent_count / b.total_recipients * 100, 1) ELSE 0 END AS delivery_rate
                FROM {$this->table} b
                WHERE b.tenant_id = ? AND b.status IN ('completed', 'running')
                ORDER BY b.id DESC " . $this->limitClause($limit);
        return $this->query($sql, [$tenantId])->fetchAll();
    }
}
