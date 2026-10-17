<?php
// FILE: /tests/QuotaTest.php

/**
 * Subscription enforcement: bots, channels, contacts, messages, storage.
 */
class QuotaTest extends TestCase {

    public function testWithinLimitsIsAllowed() {
        $check = QuotaService::check(1, 'bots');
        $this->assertTrue($check['allowed']);
        $this->assertSame(10, $check['limit']);
    }

    public function testBotLimitBlocksCreation() {
        $this->setTinyPlan(1, ['max_bots' => 3]); // tenant 1 already has 3 bots
        $check = QuotaService::check(1, 'bots');
        $this->assertFalse($check['allowed']);
        $this->assertContains('Plan limit reached', $check['message']);
    }

    public function testChannelAndContactLimits() {
        $this->setTinyPlan(2, ['max_channels' => 2, 'max_contacts' => 3]);
        $this->assertFalse(QuotaService::check(2, 'channels')['allowed']);
        $this->assertFalse(QuotaService::check(2, 'contacts')['allowed']);
    }

    public function testMessageLimitBlocksInboundProcessing() {
        $used = (new Message())->countThisMonth(1);
        $this->setTinyPlan(1, ['max_messages_per_month' => $used + 1]);
        $bot = (new Bot())->find(1, 1);
        $result = (new MessageProcessor(1))->handleInbound((new Channel())->find(1, 1), $bot, '+15550201010', 'hello');
        $this->assertFalse($result['ok']);
        $this->assertSame(429, $result['status']);
        $this->assertSame($used, (new Message())->countThisMonth(1), 'nothing stored when over quota');
    }

    public function testNewContactBlockedWhenContactLimitReached() {
        $this->setTinyPlan(1, ['max_contacts' => 4]); // tenant 1 has 4 contacts
        $bot = (new Bot())->find(1, 1);
        $result = (new MessageProcessor(1))->handleInbound((new Channel())->find(1, 1), $bot, '+15550202020', 'hello');
        $this->assertFalse($result['ok']);
        $result = (new MessageProcessor(1))->handleInbound((new Channel())->find(1, 1), $bot, '+15557001001', 'hello');
        $this->assertTrue($result['ok'], 'existing contacts can still write');
    }

    public function testStorageLimitInMegabytes() {
        $this->setTinyPlan(1, ['max_storage_mb' => 1]);
        $this->assertFalse(QuotaService::check(1, 'storage', 2)['allowed']);
        $this->assertTrue(QuotaService::check(1, 'storage', 0.1)['allowed']);
    }

    public function testNoActiveSubscriptionBlocksEverything() {
        $this->exec("UPDATE tenant_subscriptions SET status = 'cancelled' WHERE tenant_id = 2");
        $check = QuotaService::check(2, 'contacts');
        $this->assertFalse($check['allowed']);
        $this->assertContains('No active subscription', $check['message']);
    }

    public function testExpiredSubscriptionHasGracePeriodThenBlocks() {
        $this->exec("UPDATE tenant_subscriptions SET expires_at = DATE_SUB(NOW(), INTERVAL 2 DAY) WHERE tenant_id = 1 AND status = 'active'");
        $this->assertTrue(QuotaService::check(1, 'bots')['allowed'], 'still inside the grace period');
        $this->exec("UPDATE tenant_subscriptions SET expires_at = DATE_SUB(NOW(), INTERVAL 8 DAY) WHERE tenant_id = 1 AND status = 'active'");
        $this->assertContains('expired', QuotaService::check(1, 'bots')['message']);
    }

    public function testRenewalInvoiceIsIssuedOnceAndPaymentExtendsSubscription() {
        $this->exec("UPDATE tenant_subscriptions SET status = 'cancelled' WHERE tenant_id = 2");
        $this->exec("UPDATE tenant_subscriptions SET status = 'active', expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE id = 2");
        $subscriptions = new Subscription();
        $this->assertSame(1, $subscriptions->issueRenewalInvoices(3));
        $this->assertSame(0, $subscriptions->issueRenewalInvoices(3), 'idempotent');

        $invoice = (new Invoice())->find((int) $this->scalar('SELECT MAX(id) FROM invoices WHERE tenant_id = 2'), 2);
        $this->assertSame('2', (string) $invoice['subscription_id']);
        $before = strtotime($subscriptions->getActiveSubscription(2)['expires_at']);
        (new Payment())->processPayment(2, $invoice, 'credit_card', '4242424242424242');
        $after = strtotime($subscriptions->getActiveSubscription(2)['expires_at']);
        $this->assertTrue($after - $before > 25 * 86400, 'paid renewal extends the subscription by about a month');
    }

    public function testWarningAndExceededEmailsAreSentOncePerMonth() {
        $this->setTinyPlan(1, ['max_bots' => 4]); // 3 used, adding 1 = 100% -> warning (>=80%)
        QuotaService::check(1, 'bots');
        QuotaService::check(1, 'bots');
        $this->assertSame('1', (string) $this->scalar("SELECT COUNT(*) FROM notifications WHERE tenant_id = 1 AND type = 'quota_warning' AND dedupe_key LIKE 'quota:1:bots:%'"));

        $this->setTinyPlan(1, ['max_bots' => 3]);
        QuotaService::check(1, 'bots');
        $this->assertSame('1', (string) $this->scalar("SELECT COUNT(*) FROM notifications WHERE tenant_id = 1 AND type = 'quota_exceeded' AND dedupe_key LIKE 'quota:1:bots:%'"));
    }

    public function testSummaryReportsState() {
        $this->setTinyPlan(1, ['max_bots' => 3]);
        $summary = QuotaService::summary(1);
        $this->assertSame('exceeded', $summary['bots']['state']);
        $this->assertSame(100, $summary['bots']['percent']);
    }
}
