<?php
// FILE: /app/models/UsageTracking.php

/**
 * UsageTracking Model
 *
 * Tracks tenant resource usage for quota enforcement.
 */
class UsageTracking extends Model {

    protected $table = 'usage_tracking';

    /**
     * Get current month usage
     *
     * @param int $tenantId
     * @return array|null
     */
    public function getCurrentMonthUsage($tenantId) {
        $periodStart = date('Y-m-01');
        $periodEnd = date('Y-m-t');

        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = ? AND period_start = ? AND period_end = ?
                LIMIT 1";
        $stmt = $this->query($sql, [$tenantId, $periodStart, $periodEnd]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Update current month usage
     *
     * @param int $tenantId
     * @param array $metrics
     * @return bool
     */
    public function updateCurrentMonth($tenantId, $metrics) {
        $periodStart = date('Y-m-01');
        $periodEnd = date('Y-m-t');

        $existing = $this->getCurrentMonthUsage($tenantId);

        if ($existing) {
            return $this->update($existing['id'], $metrics, $tenantId);
        } else {
            $data = array_merge([
                'tenant_id' => $tenantId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd
            ], $metrics);

            $this->create($data);
            return true;
        }
    }

    /**
     * Recalculate current usage
     *
     * @param int $tenantId
     * @return bool
     */
    public function recalculate($tenantId) {
        $periodStart = date('Y-m-01 00:00:00');
        $periodEnd = date('Y-m-t 23:59:59');

        // Count channels
        $sql = "SELECT COUNT(*) as count FROM channels WHERE tenant_id = ?";
        $stmt = $this->query($sql, [$tenantId]);
        $totalChannels = $stmt->fetch()['count'];

        // Count bots
        $sql = "SELECT COUNT(*) as count FROM bots WHERE tenant_id = ?";
        $stmt = $this->query($sql, [$tenantId]);
        $totalBots = $stmt->fetch()['count'];

        // Count contacts
        $sql = "SELECT COUNT(*) as count FROM contacts WHERE tenant_id = ?";
        $stmt = $this->query($sql, [$tenantId]);
        $totalContacts = $stmt->fetch()['count'];

        // Count messages this month
        $sql = "SELECT COUNT(*) as count FROM messages
                WHERE tenant_id = ? AND created_at BETWEEN ? AND ?";
        $stmt = $this->query($sql, [$tenantId, $periodStart, $periodEnd]);
        $totalMessages = $stmt->fetch()['count'];

        // Calculate storage (simplified - assume 0 for now)
        $totalStorageMb = 0;

        return $this->updateCurrentMonth($tenantId, [
            'total_channels' => $totalChannels,
            'total_bots' => $totalBots,
            'total_contacts' => $totalContacts,
            'total_messages' => $totalMessages,
            'total_storage_mb' => $totalStorageMb
        ]);
    }
}
