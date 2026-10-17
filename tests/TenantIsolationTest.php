<?php
// FILE: /tests/TenantIsolationTest.php

/**
 * Tenant isolation: tenant 1 must never read or modify tenant 2's data.
 */
class TenantIsolationTest extends TestCase {

    public function testFindIsScopedToTenant() {
        // Bot 4 and contact 5 belong to tenant 2.
        $this->assertNotNull((new Bot())->find(4, 2));
        $this->assertNull((new Bot())->find(4, 1), 'bot of tenant 2 visible to tenant 1');
        $this->assertNull((new Contact())->find(5, 1), 'contact of tenant 2 visible to tenant 1');
        $this->assertNull((new Conversation())->getWithDetails(5, 1));
        $this->assertNull((new Invoice())->find(2, 1));
    }

    public function testListsOnlyContainOwnRows() {
        foreach ((new Bot())->listWithChannel(1, [], null, 100, 0) as $bot) {
            $this->assertSame('1', (string) $bot['tenant_id']);
        }
        foreach ((new Conversation())->getForInbox(1, [], 100, 0) as $c) {
            $this->assertSame('1', (string) $c['tenant_id']);
        }
        foreach ((new Contact())->listWithTags(1, [], null, 100, 0) as $c) {
            $this->assertSame('1', (string) $c['tenant_id']);
        }
    }

    public function testUpdateAndDeleteCannotCrossTenants() {
        (new Bot())->update(4, ['name' => 'Hacked'], 1);
        $this->assertSame('FAQ Bot', $this->scalar('SELECT name FROM bots WHERE id = 4'));
        $this->assertFalse((new Contact())->delete(5, 1));
        $this->assertSame('1', (string) $this->scalar('SELECT COUNT(*) FROM contacts WHERE id = 5'));
        $this->assertFalse((new Bot())->toggleStatus(4, 1));
    }

    public function testMessageCannotBeWrittenIntoAnotherTenantsConversation() {
        $this->assertThrows('InvalidArgumentException', function () {
            (new Message())->createMessage(['tenant_id' => 1, 'conversation_id' => 5, 'direction' => 'outbound', 'content' => 'x']);
        });
    }

    public function testModelsWithoutTenantColumnFailClosed() {
        $this->assertThrows('LogicException', function () {
            (new BroadcastMessage())->find(1, 1);
        }, 'BroadcastMessage must refuse a tenant-scoped generic find');
        $this->assertThrows('LogicException', function () {
            (new WebhookLog())->findAll([], 1);
        });
        $this->assertSame([], (new WebhookLog())->getByWebhook(2, 1), 'tenant 1 sees no logs of tenant 2 webhook');
        $this->assertSame([], (new BroadcastMessage())->getByBroadcast(3, 1), 'tenant 1 sees no recipients of tenant 2 broadcast');
        $this->assertSame(3, count((new BroadcastMessage())->getByBroadcast(3, 2)));
    }

    public function testInvalidFilterColumnIsRejected() {
        $this->assertThrows('InvalidArgumentException', function () {
            (new Bot())->findAll(['1=1 OR tenant_id' => 2], 1);
        });
    }

    public function testApiKeyBelongsToItsTenant() {
        $key = (new ApiKey())->validateKey('awb_demo_marketing_key_00000000000000000000002');
        $this->assertSame('2', (string) $key['tenant_id']);
        $this->assertNull((new ApiKey())->validateKey('awb_demo_marketing_key_00000000000000000000003'));
    }

    public function testRevokedKeysAndOtherTenantsKeysCannotBeRevokedCrossTenant() {
        $id = (int) $this->scalar("SELECT id FROM api_keys WHERE tenant_id = 2");
        $this->assertFalse((new ApiKey())->revoke($id, 1));
        $this->assertTrue((new ApiKey())->revoke($id, 2));
        $this->assertNull((new ApiKey())->validateKey('awb_demo_marketing_key_00000000000000000000002'));
    }
}
