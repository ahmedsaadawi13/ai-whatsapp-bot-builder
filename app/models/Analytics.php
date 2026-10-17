<?php
// FILE: /app/models/Analytics.php

/**
 * Analytics Model - read-only aggregate queries for dashboards and reports.
 * All queries are restricted to one tenant and a UTC date range.
 */
class Analytics extends Model {

    protected $table = 'messages';
    protected $hasUpdatedAt = false;

    /**
     * Inbound/outbound counts per day, with missing days filled with zeros.
     */
    public function messagesPerDay($tenantId, $startDate, $endDate) {
        $rows = $this->query(
            "SELECT DATE(created_at) AS day,
                    SUM(direction = 'inbound') AS inbound,
                    SUM(direction = 'outbound') AS outbound
             FROM messages
             WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
             GROUP BY DATE(created_at)",
            [$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
        )->fetchAll();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = ['inbound' => (int) $row['inbound'], 'outbound' => (int) $row['outbound']];
        }

        $series = [];
        $cursor = strtotime($startDate);
        $end = strtotime($endDate);
        $guard = 0;
        while ($cursor <= $end && $guard++ < 370) {
            $day = date('Y-m-d', $cursor);
            $series[] = [
                'day' => $day,
                'inbound' => isset($byDay[$day]) ? $byDay[$day]['inbound'] : 0,
                'outbound' => isset($byDay[$day]) ? $byDay[$day]['outbound'] : 0
            ];
            $cursor = strtotime('+1 day', $cursor);
        }
        return $series;
    }

    /**
     * Average seconds between an inbound message and the next reply,
     * grouped by who replied (ai / flow / agent).
     */
    public function averageResponseTimes($tenantId, $startDate, $endDate) {
        $sql = "SELECT r.triggered_by, AVG(r.seconds) AS avg_seconds, COUNT(*) AS replies
                FROM (
                    SELECT o.triggered_by,
                           TIMESTAMPDIFF(SECOND,
                               (SELECT MAX(i.created_at) FROM messages i
                                 WHERE i.conversation_id = o.conversation_id AND i.direction = 'inbound'
                                   AND i.created_at <= o.created_at),
                               o.created_at) AS seconds
                    FROM messages o
                    WHERE o.tenant_id = ? AND o.direction = 'outbound'
                      AND o.triggered_by IN ('ai', 'flow', 'agent')
                      AND o.created_at BETWEEN ? AND ?
                ) r
                WHERE r.seconds IS NOT NULL
                GROUP BY r.triggered_by";
        $rows = $this->query($sql, [$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'])->fetchAll();

        $result = ['ai' => null, 'flow' => null, 'agent' => null];
        foreach ($rows as $row) {
            $result[$row['triggered_by']] = ['avg_seconds' => round((float) $row['avg_seconds'], 1), 'replies' => (int) $row['replies']];
        }
        return $result;
    }

    /** Most frequent short inbound messages (keywords people actually type). */
    public function topKeywords($tenantId, $startDate, $endDate, $limit = 10) {
        $sql = "SELECT LOWER(TRIM(content)) AS keyword, COUNT(*) AS uses
                FROM messages
                WHERE tenant_id = ? AND direction = 'inbound' AND CHAR_LENGTH(content) <= 30
                  AND created_at BETWEEN ? AND ?
                GROUP BY LOWER(TRIM(content))
                ORDER BY uses DESC " . $this->limitClause($limit);
        return $this->query($sql, [$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'])->fetchAll();
    }

    /** Messages and conversations per channel. */
    public function usagePerChannel($tenantId, $startDate, $endDate) {
        $sql = "SELECT ch.id, ch.name, ch.phone_number, ch.status,
                       COUNT(m.id) AS messages,
                       COALESCE(SUM(m.direction = 'inbound'), 0) AS inbound,
                       COALESCE(SUM(m.direction = 'outbound'), 0) AS outbound,
                       COUNT(DISTINCT cv.id) AS conversations
                FROM channels ch
                LEFT JOIN conversations cv ON cv.channel_id = ch.id
                LEFT JOIN messages m ON m.conversation_id = cv.id AND m.created_at BETWEEN ? AND ?
                WHERE ch.tenant_id = ?
                GROUP BY ch.id, ch.name, ch.phone_number, ch.status
                ORDER BY messages DESC";
        return $this->query($sql, [$startDate . ' 00:00:00', $endDate . ' 23:59:59', $tenantId])->fetchAll();
    }

    /** Messages report table: per day per source. */
    public function messagesBySource($tenantId, $startDate, $endDate) {
        $sql = "SELECT DATE(created_at) AS day, direction, COALESCE(triggered_by, 'contact') AS source, COUNT(*) AS total
                FROM messages
                WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at), direction, COALESCE(triggered_by, 'contact')
                ORDER BY day DESC, direction, source";
        return $this->query($sql, [$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'])->fetchAll();
    }

    /** Platform-wide numbers for the platform admin dashboard. */
    public function platformTotals() {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM tenants) AS total_tenants,
                    (SELECT COUNT(*) FROM tenants WHERE status = 'active') AS active_tenants,
                    (SELECT COUNT(*) FROM users WHERE tenant_id IS NOT NULL) AS total_users,
                    (SELECT COUNT(*) FROM bots) AS total_bots,
                    (SELECT COUNT(*) FROM messages WHERE created_at >= ?) AS messages_this_month,
                    (SELECT COALESCE(SUM(total), 0) FROM invoices WHERE status = 'paid') AS revenue,
                    (SELECT COALESCE(SUM(total), 0) FROM invoices WHERE status = 'pending') AS outstanding";
        return $this->query($sql, [date('Y-m-01 00:00:00')])->fetch();
    }
}
