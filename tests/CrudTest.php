<?php
// FILE: /tests/CrudTest.php

/**
 * CRUD for tenants, bots, flows, steps, contacts, conversations, templates, billing.
 */
class CrudTest extends TestCase {

    public function testTenantCrudAndSlugUniqueness() {
        $model = new Tenant();
        $this->assertSame('techcorp-2', $model->generateSlug('TechCorp'));
        $id = $model->create(['name' => 'New Co', 'slug' => $model->generateSlug('New Co'), 'email' => 'a@newco.com', 'status' => 'active']);
        $model->update($id, ['industry' => 'Retail']);
        $this->assertSame('Retail', $model->find($id)['industry']);
        $this->assertTrue($model->delete($id));
        $this->assertNull($model->find($id));
    }

    public function testBotFlowAndStepLifecycle() {
        $botId = (new Bot())->create(['tenant_id' => 1, 'channel_id' => 2, 'name' => 'Test Bot', 'status' => 'draft']);
        $flowId = (new Flow())->create(['tenant_id' => 1, 'bot_id' => $botId, 'name' => 'Greet', 'trigger_type' => 'keyword', 'trigger_value' => 'yo', 'priority' => 1]);
        $steps = new FlowStep();
        $a = $steps->create(['tenant_id' => 1, 'flow_id' => $flowId, 'step_order' => 1, 'action_type' => 'send_text', 'action_config' => '{"message":"A"}']);
        $b = $steps->create(['tenant_id' => 1, 'flow_id' => $flowId, 'step_order' => 2, 'action_type' => 'send_text', 'action_config' => '{"message":"B"}']);
        $this->assertSame(3, $steps->nextOrder($flowId, 1));

        $steps->move($steps->find($b, 1), -1);
        $this->assertSame([$b, $a], array_map('intval', array_column($steps->getByFlow($flowId, 1), 'id')), 'move up reorders');

        $this->assertTrue((new Bot())->toggleStatus($botId, 1));
        $this->assertSame('active', (new Bot())->find($botId, 1)['status']);

        (new Bot())->delete($botId, 1);
        $this->assertSame('0', (string) $this->scalar('SELECT COUNT(*) FROM flow_steps WHERE flow_id = ?', [$flowId]), 'cascade delete');
    }

    public function testFlowMatchingRules() {
        $flow = new Flow();
        $this->assertSame('1', (string) $flow->findMatchingFlow(1, '  HELLO ', 1)['id'], 'keyword is case/space insensitive');
        $this->assertSame('5', (string) $flow->findMatchingFlow(2, 'what is the pricing?', 1)['id'], 'message_contains');
        $this->assertNull($flow->findMatchingFlow(1, 'hello there', 1), 'keyword requires an exact match');
        $this->assertSame('11', (string) $flow->findMatchingFlow(5, 'anything', 2)['id'], 'default flow');
    }

    public function testContactFindOrCreateAndTags() {
        $model = new Contact();
        list($c, $created) = $model->findOrCreate('+15550301010', 1, ['name' => 'Lina']);
        $this->assertTrue($created);
        list($again, $createdAgain) = $model->findOrCreate('+15550301010', 1);
        $this->assertFalse($createdAgain);
        $this->assertSame($c['id'], $again['id']);

        (new Tag())->syncContactTags($c['id'], 1, ['VIP Customer', 'Brand New']);
        $names = array_column((new Tag())->getByContact($c['id'], 1), 'name');
        $this->assertSame(['Brand New', 'VIP Customer'], $names);
        $this->assertSame(1, $model->countWithTags(1, ['tag_id' => 1], 'Lina'));
    }

    public function testConversationReuseAndStatus() {
        $model = new Conversation();
        list($conv, $created) = $model->findOrCreate(2, 2, 1, 2);
        $this->assertTrue($created, 'conversation 2 is closed, so a new one opens');
        list($same, $createdAgain) = $model->findOrCreate(2, 2, 1, 2);
        $this->assertFalse($createdAgain);
        $model->setStatus($conv['id'], 1, 'closed');
        $this->assertNotNull($model->find($conv['id'], 1)['closed_at']);
        $this->assertSame(1, $model->countForInbox(1, ['search' => 'Charlie']));
    }

    public function testTemplateRendering() {
        $this->assertSame('Hi Ann, order 7 ', Template::render('Hi {{name}}, order {{ order_id }} {{missing}}', ['name' => 'Ann', 'order_id' => 7]));
        $this->assertSame(['name', 'custom.city'], Template::extractVariables('{{name}} in {{custom.city}} {{name}}'));
        $full = Template::renderFull((new Template())->find(2, 1), ['name' => 'Bob', 'order_id' => 'A-9']);
        $this->assertContains("Order Confirmed\n\nHi Bob, your order A-9", $full);
    }

    public function testPlanChangeCreatesInvoiceAndPaymentMarksPaid() {
        $plan = (new Plan())->find(3);
        $result = (new Subscription())->switchPlan(1, $plan);
        $this->assertSame('3', (string) (new Subscription())->getActiveSubscription(1)['plan_id']);
        $invoice = (new Invoice())->find($result['invoice_id'], 1);
        $this->assertSame('328.90', $invoice['total']);
        $this->assertSame('1', (string) $this->scalar("SELECT COUNT(*) FROM tenant_subscriptions WHERE tenant_id = 1 AND status = 'active'"));

        $declined = (new Payment())->processPayment(1, $invoice, 'credit_card', '4000000000000002');
        $this->assertFalse($declined['success']);
        $this->assertSame('pending', (new Invoice())->find($invoice['id'], 1)['status']);

        $paid = (new Payment())->processPayment(1, $invoice, 'credit_card', '4242424242424242');
        $this->assertTrue($paid['success']);
        $this->assertSame('paid', (new Invoice())->find($invoice['id'], 1)['status']);
    }

    public function testCustomFieldParsing() {
        list($fields, $errors) = ContactController::textToFields("company=ACME\ncity = Cairo\nbad line");
        $this->assertSame(['company' => 'ACME', 'city' => 'Cairo'], $fields);
        $this->assertSame(1, count($errors));
    }

    public function testChannelCredentialsAreEncryptedAtRest() {
        $stored = Channel::encodeConfig(['access_token' => 'secret-token']);
        $this->assertContains('enc:v1:', $stored);
        $this->assertFalse(strpos($stored, 'secret-token') !== false);
        $this->assertSame(['access_token' => 'secret-token'], Channel::decodeConfig($stored));
        $this->assertSame(['a' => 1], Channel::decodeConfig('{"a":1}'), 'legacy plaintext still readable');
    }

    public function testUsageRecalculation() {
        $usage = (new UsageTracking())->recalculate(1);
        $this->assertSame(2, $usage['total_channels']);
        $this->assertSame(3, $usage['total_bots']);
        $this->assertSame((int) $this->scalar('SELECT COUNT(*) FROM contacts WHERE tenant_id = 1'), $usage['total_contacts']);
        $this->assertNotNull((new UsageTracking())->getCurrentMonthUsage(1));
    }
}
