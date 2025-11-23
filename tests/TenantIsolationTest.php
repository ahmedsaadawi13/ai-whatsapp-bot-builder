<?php
// FILE: /tests/TenantIsolationTest.php

/**
 * Tenant Isolation Tests
 *
 * Ensures proper tenant data isolation in multi-tenant system.
 */

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/Bot.php';
require_once __DIR__ . '/../app/models/Contact.php';

class TenantIsolationTest {

    private $botModel;
    private $contactModel;

    public function __construct() {
        $this->botModel = new Bot();
        $this->contactModel = new Contact();
    }

    /**
     * Test that tenant 1 cannot access tenant 2 bots
     */
    public function testBotIsolation() {
        echo "Testing bot tenant isolation...\n";

        // Get bots for tenant 1
        $tenant1Bots = $this->botModel->findAll([], 1);

        // Try to access tenant 2's bot with tenant 1 context
        if (!empty($tenant1Bots)) {
            $bot = $this->botModel->find($tenant1Bots[0]['id'], 2); // Wrong tenant
            if ($bot === null) {
                echo "✓ Tenant isolation working correctly for bots\n";
                return true;
            } else {
                echo "✗ Tenant isolation FAILED - cross-tenant access detected!\n";
                return false;
            }
        } else {
            echo "⚠ No bots found for tenant 1, skipping test\n";
            return true;
        }
    }

    /**
     * Test contact isolation
     */
    public function testContactIsolation() {
        echo "Testing contact tenant isolation...\n";

        $tenant1Contacts = $this->contactModel->findAll([], 1);

        if (!empty($tenant1Contacts)) {
            $contact = $this->contactModel->find($tenant1Contacts[0]['id'], 2); // Wrong tenant
            if ($contact === null) {
                echo "✓ Tenant isolation working correctly for contacts\n";
                return true;
            } else {
                echo "✗ Tenant isolation FAILED for contacts!\n";
                return false;
            }
        } else {
            echo "⚠ No contacts found for tenant 1, skipping test\n";
            return true;
        }
    }

    /**
     * Test that queries properly filter by tenant
     */
    public function testTenantFiltering() {
        echo "Testing tenant filtering in queries...\n";

        $tenant1Bots = $this->botModel->findAll([], 1);
        $tenant2Bots = $this->botModel->findAll([], 2);

        // Ensure no overlap
        $tenant1Ids = array_column($tenant1Bots, 'id');
        $tenant2Ids = array_column($tenant2Bots, 'id');
        $overlap = array_intersect($tenant1Ids, $tenant2Ids);

        if (empty($overlap)) {
            echo "✓ Tenant filtering working correctly - no data overlap\n";
            return true;
        } else {
            echo "✗ Tenant filtering FAILED - data overlap detected!\n";
            return false;
        }
    }

    /**
     * Run all tests
     */
    public function runAll() {
        echo "\n=== Running Tenant Isolation Tests ===\n\n";

        $results = [
            $this->testBotIsolation(),
            $this->testContactIsolation(),
            $this->testTenantFiltering()
        ];

        $passed = count(array_filter($results));
        $total = count($results);

        echo "\n=== Test Results: $passed/$total passed ===\n\n";

        return $passed === $total;
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new TenantIsolationTest();
    $success = $test->runAll();
    exit($success ? 0 : 1);
}
