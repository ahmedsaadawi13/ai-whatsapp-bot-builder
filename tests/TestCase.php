<?php
// FILE: /tests/TestCase.php

/**
 * Minimal test framework (no PHPUnit needed).
 *
 * Every public method starting with "test" is a test. The database is
 * re-imported from database.sql before each test class, so tests can
 * rely on the demo seed data (tenant 1 = TechCorp, tenant 2 = Marketing Masters).
 */
abstract class TestCase {

    public $passed = 0;
    public $failed = [];
    private $assertions = 0;

    /** Override for per-class setup (DB is already fresh). */
    public function setUpClass() {}

    protected function assertTrue($condition, $message = 'Expected true') {
        $this->assertions++;
        if (!$condition) {
            throw new AssertionFailed($message);
        }
    }

    protected function assertFalse($condition, $message = 'Expected false') {
        $this->assertTrue(!$condition, $message);
    }

    protected function assertEquals($expected, $actual, $message = '') {
        $this->assertTrue($expected == $actual, ($message ? $message . ': ' : '') .
            'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    protected function assertSame($expected, $actual, $message = '') {
        $this->assertTrue($expected === $actual, ($message ? $message . ': ' : '') .
            'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    protected function assertNull($value, $message = 'Expected null') {
        $this->assertTrue($value === null, $message . ' (got ' . var_export($value, true) . ')');
    }

    protected function assertNotNull($value, $message = 'Expected a value, got null') {
        $this->assertTrue($value !== null, $message);
    }

    protected function assertContains($needle, $haystack, $message = '') {
        $ok = is_array($haystack) ? in_array($needle, $haystack) : strpos((string) $haystack, (string) $needle) !== false;
        $this->assertTrue($ok, ($message ? $message . ': ' : '') . var_export($needle, true) . ' not found in ' .
            (is_array($haystack) ? json_encode($haystack) : substr((string) $haystack, 0, 300)));
    }

    protected function assertThrows($exceptionClass, $callback, $message = '') {
        $this->assertions++;
        try {
            $callback();
        } catch (Exception $e) {
            if ($e instanceof $exceptionClass) {
                return;
            }
            throw new AssertionFailed(($message ? $message . ': ' : '') . 'expected ' . $exceptionClass . ', got ' . get_class($e));
        }
        throw new AssertionFailed(($message ? $message . ': ' : '') . 'expected ' . $exceptionClass . ' to be thrown');
    }

    protected function db() {
        return Database::getInstance()->getConnection();
    }

    protected function scalar($sql, $params = []) {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    protected function exec($sql, $params = []) {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Put a tenant on a tiny plan to test quota enforcement. */
    protected function setTinyPlan($tenantId, $limits) {
        $defaults = ['max_channels' => 10, 'max_bots' => 10, 'max_contacts' => 1000, 'max_messages_per_month' => 100000, 'max_storage_mb' => 1000];
        $limits = array_merge($defaults, $limits);
        $this->exec("INSERT INTO plans (name, price, billing_period, max_channels, max_bots, max_contacts, max_messages_per_month, max_storage_mb, status, created_at, updated_at)
                     VALUES ('Tiny', 0, 'monthly', ?, ?, ?, ?, ?, 'active', NOW(), NOW())",
            [$limits['max_channels'], $limits['max_bots'], $limits['max_contacts'], $limits['max_messages_per_month'], $limits['max_storage_mb']]);
        $planId = (int) $this->db()->lastInsertId();
        $this->exec("UPDATE tenant_subscriptions SET status = 'cancelled' WHERE tenant_id = ?", [$tenantId]);
        $this->exec("INSERT INTO tenant_subscriptions (tenant_id, plan_id, status, started_at, expires_at, created_at, updated_at)
                     VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), NOW(), NOW())", [$tenantId, $planId]);
        return $planId;
    }

    public function run($filter = null) {
        $methods = array_filter(get_class_methods($this), function ($m) use ($filter) {
            return strpos($m, 'test') === 0 && ($filter === null || stripos($m, $filter) !== false);
        });
        foreach ($methods as $method) {
            try {
                Auth::flushCache();
                WebhookDispatcher::$fakeTransport = null;
                $this->$method();
                $this->passed++;
                echo "  \033[32m✓\033[0m {$method}\n";
            } catch (Throwable $e) {
                $where = $e instanceof AssertionFailed ? '' : ' [' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
                $this->failed[] = get_class($this) . "::{$method} - " . $e->getMessage() . $where;
                echo "  \033[31m✗\033[0m {$method}: " . $e->getMessage() . $where . "\n";
            }
        }
    }
}

class AssertionFailed extends Exception {}
