<?php
// FILE: /tests/AuthTest.php

/**
 * Authentication Tests
 *
 * Basic functional tests for authentication system.
 */

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/User.php';

class AuthTest {

    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    /**
     * Test user creation
     */
    public function testUserCreation() {
        echo "Testing user creation...\n";

        $userData = [
            'tenant_id' => 1,
            'name' => 'Test User',
            'email' => 'test_' . time() . '@example.com',
            'password' => 'password123',
            'role' => 'agent',
            'status' => 'active'
        ];

        $userId = $this->userModel->createUser($userData);

        if ($userId > 0) {
            echo "✓ User created successfully (ID: $userId)\n";
            return true;
        } else {
            echo "✗ User creation failed\n";
            return false;
        }
    }

    /**
     * Test finding user by email
     */
    public function testFindUserByEmail() {
        echo "Testing find user by email...\n";

        $user = $this->userModel->findByEmail('john@techcorp.com');

        if ($user && $user['email'] === 'john@techcorp.com') {
            echo "✓ User found by email\n";
            return true;
        } else {
            echo "✗ User not found by email\n";
            return false;
        }
    }

    /**
     * Test password verification
     */
    public function testPasswordVerification() {
        echo "Testing password verification...\n";

        $user = $this->userModel->findByEmail('john@techcorp.com');

        if ($user && password_verify('password', $user['password'])) {
            echo "✓ Password verification successful\n";
            return true;
        } else {
            echo "✗ Password verification failed\n";
            return false;
        }
    }

    /**
     * Run all tests
     */
    public function runAll() {
        echo "\n=== Running Authentication Tests ===\n\n";

        $results = [
            $this->testFindUserByEmail(),
            $this->testPasswordVerification(),
            $this->testUserCreation()
        ];

        $passed = count(array_filter($results));
        $total = count($results);

        echo "\n=== Test Results: $passed/$total passed ===\n\n";

        return $passed === $total;
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new AuthTest();
    $success = $test->runAll();
    exit($success ? 0 : 1);
}
