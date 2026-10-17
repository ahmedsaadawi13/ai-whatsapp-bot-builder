<?php
// FILE: /app/controllers/AnalyticsController.php

/**
 * Analytics Controller - dashboard charts and reports (date range filter, CSV export).
 */
class AnalyticsController extends Controller {

    public function index() {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        list($start, $end) = $this->range(29);
        $analytics = new Analytics();

        $this->view('analytics/index', [
            'title' => 'Analytics',
            'startDate' => $start,
            'endDate' => $end,
            'stats' => (new Message())->getStatistics($tenantId, $start . ' 00:00:00', $end . ' 23:59:59'),
            'chart' => $analytics->messagesPerDay($tenantId, $start, $end),
            'responseTimes' => $analytics->averageResponseTimes($tenantId, $start, $end),
            'topFlows' => (new Flow())->topFlows($tenantId, $start . ' 00:00:00', $end . ' 23:59:59', 10),
            'topKeywords' => $analytics->topKeywords($tenantId, $start, $end, 10),
            'conversationStats' => (new Conversation())->countByStatus($tenantId)
        ]);
    }

    public function messages() {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        list($start, $end) = $this->range(29);
        $rows = (new Analytics())->messagesBySource($tenantId, $start, $end);

        if ($this->request->get('export') === 'csv') {
            $this->csv('messages-' . $start . '-to-' . $end . '.csv', ['date', 'direction', 'source', 'messages'], array_map(function ($r) {
                return [$r['day'], $r['direction'], $r['source'], $r['total']];
            }, $rows));
        }

        $this->view('analytics/messages', [
            'title' => 'Messages Report',
            'startDate' => $start,
            'endDate' => $end,
            'rows' => $rows,
            'stats' => (new Message())->getStatistics($tenantId, $start . ' 00:00:00', $end . ' 23:59:59')
        ]);
    }

    public function conversations() {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $stats = (new Conversation())->countByStatus($tenantId);
        $this->view('analytics/conversations', [
            'title' => 'Conversations Report',
            'stats' => $stats,
            'total' => array_sum($stats)
        ]);
    }

    public function channels() {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        list($start, $end) = $this->range(29);
        $rows = (new Analytics())->usagePerChannel($tenantId, $start, $end);

        if ($this->request->get('export') === 'csv') {
            $this->csv('channel-usage.csv', ['channel', 'phone', 'conversations', 'inbound', 'outbound', 'total'], array_map(function ($r) {
                return [$r['name'], $r['phone_number'], $r['conversations'], $r['inbound'], $r['outbound'], $r['messages']];
            }, $rows));
        }

        $this->view('analytics/channels', ['title' => 'Usage per Channel', 'startDate' => $start, 'endDate' => $end, 'rows' => $rows]);
    }

    public function broadcasts() {
        $tenantId = $this->requireTenant(self::TENANT_ROLES);
        $this->view('analytics/broadcasts', [
            'title' => 'Broadcast Performance',
            'rows' => (new Broadcast())->performance($tenantId, 50)
        ]);
    }

    /** Validated [start, end] (Y-m-d), defaulting to the last N days, max 366 days. */
    private function range($defaultDays) {
        $start = $this->request->get('start_date');
        $end = $this->request->get('end_date');
        $valid = function ($d) {
            return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        };
        $end = $valid($end) ? $end : date('Y-m-d');
        $start = $valid($start) ? $start : date('Y-m-d', strtotime($end . " -{$defaultDays} days"));
        if (strtotime($start) > strtotime($end)) {
            list($start, $end) = [$end, $start];
        }
        if ((strtotime($end) - strtotime($start)) > 366 * 86400) {
            $start = date('Y-m-d', strtotime($end . ' -366 days'));
        }
        return [$start, $end];
    }

    private function csv($filename, $header, $rows) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename) . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $header, ',', '"', '\\');
        foreach ($rows as $row) {
            // Prevent spreadsheet formula injection.
            fputcsv($out, array_map(function ($v) {
                return is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
            }, $row), ',', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
