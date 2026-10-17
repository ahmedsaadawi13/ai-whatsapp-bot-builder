<?php
// FILE: /tests/AuthTest.php

/**
 * Authentication: password hashing, login, throttling, inactive users, suspended tenants.
 */
class AuthTest extends TestCase {

    public function testPasswordsAreHashedWithPasswordHash() {
        $id = (new User())->createUser(['tenant_id' => 1, 'name' => 'Hash Test', 'email' => 'Hash@Example.com', 'password' => 'secret123', 'role' => 'agent', 'status' => 'active']);
        $user = (new User())->find($id);
        $this->assertSame('hash@example.com', $user['email'], 'email is normalised');
        $this->assertTrue($user['password'] !== 'secret123', 'password must not be stored in plain text');
        $this->assertTrue(password_verify('secret123', $user['password']), 'hash verifies');
    }

    public function testLoginWithCorrectCredentials() {
        $auth = new Auth();
        $this->assertTrue($auth->attempt('john@techcorp.com', 'password', '10.0.0.1'));
        Auth::flushCache();
        $this->assertSame(2, $auth->id());
        $this->assertSame(1, $auth->tenantId());
        $this->assertTrue($auth->hasRole('tenant_admin'));
        $auth->logout();
    }

    public function testLoginFailsWithWrongPasswordOrUnknownEmail() {
        $auth = new Auth();
        $this->assertFalse($auth->attempt('john@techcorp.com', 'wrong', '10.0.0.2'));
        $this->assertSame('Invalid email or password', $auth->getLastError());
        $this->assertFalse($auth->attempt('nobody@example.com', 'password', '10.0.0.2'));
    }

    public function testLoginIsThrottledAfterFiveFailures() {
        $auth = new Auth();
        for ($i = 0; $i < 5; $i++) {
            $auth->attempt('sarah@techcorp.com', 'bad', '10.0.0.3');
        }
        $this->assertFalse($auth->attempt('sarah@techcorp.com', 'password', '10.0.0.3'), 'correct password still blocked');
        $this->assertContains('Too many failed attempts', $auth->getLastError());
        $this->assertTrue($auth->attempt('sarah@techcorp.com', 'password', '10.0.0.4'), 'other IP is not blocked');
        $auth->logout();
    }

    public function testInactiveUserCannotLogin() {
        $this->exec("UPDATE users SET status = 'inactive' WHERE email = 'mike@techcorp.com'");
        $this->assertFalse((new Auth())->attempt('mike@techcorp.com', 'password', '10.0.0.5'));
    }

    public function testUsersOfSuspendedTenantCannotLogin() {
        $this->exec("UPDATE tenants SET status = 'suspended' WHERE id = 2");
        $auth = new Auth();
        $this->assertFalse($auth->attempt('emma@marketingmasters.com', 'password', '10.0.0.6'));
        $this->assertContains('suspended', $auth->getLastError());
    }

    public function testPlatformAdminHasNoTenant() {
        $auth = new Auth();
        $this->assertTrue($auth->attempt('admin@platform.com', 'password', '10.0.0.7'));
        Auth::flushCache();
        $this->assertNull($auth->tenantId());
        $this->assertTrue($auth->isPlatformAdmin());
        $auth->logout();
    }

    public function testCsrfTokensAreValidatedWithConstantTimeCompare() {
        $token = CSRF::generate();
        $this->assertSame(64, strlen($token));
        $this->assertTrue(CSRF::validate($token));
        $this->assertFalse(CSRF::validate('wrong'));
        $this->assertFalse(CSRF::validate(null));
    }

    public function testAccountIsThrottledAcrossManyIps() {
        $auth = new Auth();
        for ($i = 0; $i < 20; $i++) {
            $auth->attempt('olivia@marketingmasters.com', 'bad', '10.1.0.' . $i);
        }
        $this->assertFalse($auth->attempt('olivia@marketingmasters.com', 'password', '10.2.0.1'), 'rotating IPs must not bypass the lockout');
    }

    public function testSuspendingATenantEndsActiveSessions() {
        $this->exec("UPDATE tenants SET status = 'active' WHERE id = 2");
        $auth = new Auth();
        $this->assertTrue($auth->attempt('david@marketingmasters.com', 'password', '10.3.0.1'));
        Auth::flushCache();
        $this->assertNotNull($auth->user());
        $this->exec("UPDATE tenants SET status = 'suspended' WHERE id = 2");
        Auth::flushCache();
        $this->assertNull($auth->user(), 'suspended tenant user is logged out on the next request');
    }

    public function testForwardedForHeaderIsIgnoredWithoutTrustedProxy() {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        $this->assertSame('203.0.113.9', (new Request())->ip());
        putenv('TRUSTED_PROXIES=203.0.113.9');
        $this->assertSame('1.2.3.4', (new Request())->ip());
        putenv('TRUSTED_PROXIES');
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
}
