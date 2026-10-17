<?php
// FILE: /app/models/UsageTracking.php

/**
 * UsageTracking Model - monthly snapshot of resource usage per tenant.
 */
class UsageTracking extends Model {

    protected $table = 'usage_tracking';

    public function getCurrentMonthUsage($tenantId) {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE tenant_id = ? AND period_start = ? LIMIT 1",
            [$tenantId, date('Y-m-01')]
        );
        return $stmt->fetch() ?: null;
    }

    public function getHistory($tenantId, $months = 6) {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE tenant_id = ? ORDER BY period_start DESC " . $this->limitClause($months),
            [$tenantId]
        )->fetchAll();
    }

    /**
     * Recompute live usage (one aggregate query + upload folder size) and
     * upsert this month's row.
     */
    public function recalculate($tenantId) {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM channels WHERE tenant_id = ?) AS total_channels,
                    (SELECT COUNT(*) FROM bots WHERE tenant_id = ?) AS total_bots,
                    (SELECT COUNT(*) FROM contacts WHERE tenant_id = ?) AS total_contacts,
                    (SELECT COUNT(*) FROM messages WHERE tenant_id = ? AND created_at >= ?) AS total_messages";
        $row = $this->query($sql, [$tenantId, $tenantId, $tenantId, $tenantId, date('Y-m-01 00:00:00')])->fetch();

        $metrics = [
            'total_channels' => (int) $row['total_channels'],
            'total_bots' => (int) $row['total_bots'],
            'total_contacts' => (int) $row['total_contacts'],
            'total_messages' => (int) $row['total_messages'],
            'total_storage_mb' => round(FileUpload::tenantUsageBytes($tenantId) / 1048576, 2)
        ];

        $now = date('Y-m-d H:i:s');
        $this->query(
            "INSERT INTO {$this->table}
                (tenant_id, period_start, period_end, total_channels, total_bots, total_contacts, total_messages, total_storage_mb, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE total_channels = VALUES(total_channels), total_bots = VALUES(total_bots),
                total_contacts = VALUES(total_contacts), total_messages = VALUES(total_messages),
                total_storage_mb = VALUES(total_storage_mb), updated_at = VALUES(updated_at)",
            [$tenantId, date('Y-m-01'), date('Y-m-t'), $metrics['total_channels'], $metrics['total_bots'],
             $metrics['total_contacts'], $metrics['total_messages'], $metrics['total_storage_mb'], $now, $now]
        );

        return $metrics;
    }
}
