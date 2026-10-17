<?php
// FILE: /tests/BroadcastWebhookTest.php

/**
 * Broadcast campaigns, outgoing webhooks (signature, logging, retries, SSRF), uploads.
 */
class BroadcastWebhookTest extends TestCase {

    public function testDraftBroadcastSendsWithPerContactVariables() {
        // Broadcast 4 (tenant 2): template "Hi {{name}}, ... code {{code}}", all active contacts.
        $stats = BroadcastRunner::run(4, 2);
        $this->assertSame(2, $stats['sent'], 'George is unsubscribed and excluded');
        $content = $this->scalar("SELECT content FROM messages WHERE tenant_id = 2 AND triggered_by = 'broadcast' ORDER BY id LIMIT 1");
        $this->assertContains('Hi Diana Prince, get 20% off an SEO audit this week with code SEO20.', $content);
        $this->assertSame('completed', (new Broadcast())->find(4, 2)['status']);
    }

    public function testBroadcastCannotRunTwiceOrCrossTenant() {
        $this->assertFalse(BroadcastRunner::run(4, 2), 'already completed');
        $this->assertFalse(BroadcastRunner::run(2, 2), 'broadcast 2 belongs to tenant 1');
    }

    public function testScheduledBroadcastRunsWhenDue() {
        $this->assertSame(0, BroadcastRunner::runDue(), 'broadcast 2 is scheduled in the future');
        $this->exec('UPDATE broadcasts SET scheduled_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = 2');
        $this->assertSame(1, BroadcastRunner::runDue());
        $this->assertSame('1', (string) (new Broadcast())->find(2, 1)['sent_count'], 'one contact tagged Support Issue');
    }

    public function testMappedVariablesReferenceCustomFields() {
        $vars = BroadcastRunner::variablesFor(
            ['name' => 'Diana Prince', 'phone_number' => '+1', 'email' => '', 'custom_fields' => '{"industry":"Retail"}'],
            ['industry' => '{{custom.industry}}', 'month' => 'October']
        );
        $this->assertSame('Retail', $vars['industry']);
        $this->assertSame('Diana', $vars['first_name']);
    }

    public function testWebhookDeliveryIsSignedAndLogged() {
        $this->exec("UPDATE webhooks SET status = 'active', url = 'https://hooks.example.com/x' WHERE id = 1");
        $captured = [];
        WebhookDispatcher::$fakeTransport = function ($url, $body, $headers) use (&$captured) {
            $captured[] = [$url, $body, $headers];
            return ['ok' => true, 'code' => 200, 'body' => 'ok'];
        };
        putenv('WEBHOOK_ALLOW_PRIVATE=true'); // no DNS lookups in tests
        WebhookDispatcher::dispatch(1, 'message.received', ['content' => 'hi'], 1);
        putenv('WEBHOOK_ALLOW_PRIVATE');

        $this->assertSame(1, count($captured));
        list($url, $body, $headers) = $captured[0];
        $this->assertContains('X-Signature: sha256=' . hash_hmac('sha256', $body, 'whsec_demo_techcorp_123'), $headers);
        $this->assertSame('message.received', json_decode($body, true)['event']);
        $this->assertSame('success', $this->scalar('SELECT status FROM webhook_logs WHERE webhook_id = 1 ORDER BY id DESC LIMIT 1'));
    }

    public function testEventsNotSubscribedAreNotSent() {
        $this->exec("UPDATE webhooks SET status = 'active' WHERE id = 1");
        $count = 0;
        WebhookDispatcher::$fakeTransport = function () use (&$count) {
            $count++;
            return ['ok' => true, 'code' => 200, 'body' => ''];
        };
        putenv('WEBHOOK_ALLOW_PRIVATE=true');
        WebhookDispatcher::dispatch(1, 'contact.created', [], 1);
        putenv('WEBHOOK_ALLOW_PRIVATE');
        $this->assertSame(0, $count);
    }

    public function testFailedDeliveriesAreRetriedUpToTheLimit() {
        $this->exec("UPDATE webhooks SET status = 'active', retry_attempts = 2 WHERE id = 1");
        WebhookDispatcher::$fakeTransport = function () {
            return ['ok' => false, 'code' => 500, 'body' => 'boom'];
        };
        putenv('WEBHOOK_ALLOW_PRIVATE=true');
        $before = (int) $this->scalar('SELECT COUNT(*) FROM webhook_logs WHERE webhook_id = 1');
        WebhookDispatcher::dispatch(1, 'conversation.opened', ['conversation_id' => 1], 1);
        $this->assertSame(1, WebhookDispatcher::retryFailed(), 'attempt 2');
        $this->assertSame(0, WebhookDispatcher::retryFailed(), 'limit of 2 attempts reached');
        putenv('WEBHOOK_ALLOW_PRIVATE');
        $this->assertSame($before + 2, (int) $this->scalar('SELECT COUNT(*) FROM webhook_logs WHERE webhook_id = 1'));
        $this->assertSame('2', (string) $this->scalar('SELECT MAX(attempt) FROM webhook_logs WHERE webhook_id = 1'));
    }

    public function testSsrfProtectionBlocksPrivateTargets() {
        $this->assertFalse(WebhookDispatcher::isSafeUrl('http://127.0.0.1/admin'));
        $this->assertFalse(WebhookDispatcher::isSafeUrl('http://10.0.0.5/'));
        $this->assertFalse(WebhookDispatcher::isSafeUrl('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse(WebhookDispatcher::isSafeUrl('file:///etc/passwd'));
        $this->assertFalse(WebhookDispatcher::isSafeUrl('http://[::1]/'));
        $this->assertTrue(WebhookDispatcher::isSafeUrl('https://93.184.216.34/hook'));
    }

    public function testUploadValidationAndTraversalProtection() {
        $this->assertNull(FileUpload::resolve('../../.env'));
        $this->assertNull(FileUpload::resolve('tenant_1/../../../database.sql'));

        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, '<?php echo "evil";');
        $uploader = new FileUpload();
        $result = $uploader->upload(['name' => 'photo.png', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK], 1, 'media');
        $this->assertFalse($result, 'PHP disguised as PNG must be rejected');
        $this->assertContains('does not match', implode(' ', $uploader->getErrors()));

        $result = $uploader->upload(['name' => 'shell.php', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK], 1, 'media');
        $this->assertFalse($result, '.php extension must be rejected');

        file_put_contents($tmp, "Opening hours: 9 to 5.\nWe deliver nationwide.");
        $path = $uploader->upload(['name' => 'faq.txt', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK], 1, 'knowledge');
        $this->assertTrue(is_string($path) && preg_match('#^tenant_1/knowledge/[a-f0-9]{32}\.txt$#', $path) === 1, 'random name in tenant folder');
        $this->assertContains('deliver nationwide', DocumentText::extract(FileUpload::resolve($path), 'txt'));
        $this->assertTrue($uploader->delete($path));
    }
}
