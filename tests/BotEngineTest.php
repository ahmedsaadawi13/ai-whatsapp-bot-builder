<?php
// FILE: /tests/BotEngineTest.php

/**
 * Conversation flow: incoming message -> flow / AI -> replies -> message log.
 */
class BotEngineTest extends TestCase {

    private $phone = '+15550101010';

    private function send($botId, $text, $tenantId = 1, $phone = null) {
        $bot = (new Bot())->find($botId, $tenantId);
        $channel = (new Channel())->find($bot['channel_id'], $tenantId);
        $result = (new MessageProcessor($tenantId))->handleInbound($channel, $bot, $phone ?: $this->phone, $text, ['source' => 'test']);
        $this->assertTrue($result['ok'], 'processing failed: ' . (isset($result['error']) ? $result['error'] : ''));
        return $result;
    }

    private function texts($result) {
        return array_column($result['replies'], 'content');
    }

    public function testKeywordTriggersWelcomeMenuWithQuickReplies() {
        $r = $this->send(1, 'Hello');
        $this->assertSame('interactive', $r['replies'][0]['type']);
        $this->assertSame(['Support', 'Sales', 'Business hours'], $r['replies'][0]['quick_replies']);
        $this->assertContains('Welcome to TechCorp', $r['replies'][0]['content']);
    }

    public function testQuestionsPauseAndResumeTheFlowAndSaveVariables() {
        $phone = '+15550102020';
        $r = $this->send(1, '1', 1, $phone);
        $this->assertContains('What is your email address?', $this->texts($r));

        $r = $this->send(1, 'jane@example.com', 1, $phone);
        $this->assertSame(['Please describe the problem in one message.'], $this->texts($r));

        $r = $this->send(1, 'App crashes', 1, $phone);
        $this->assertContains('Ticket created for jane@example.com', $r['replies'][0]['content']);

        $vars = (new ConversationVariable())->getAllVariables($r['conversation']['id'], false);
        $this->assertSame('jane@example.com', $vars['email']);
        $this->assertSame('App crashes', $vars['issue']);
        $this->assertSame('open', $vars['ticket_status'], 'set_variable step ran');
        $this->assertFalse(isset((new ConversationVariable())->getAllVariables($r['conversation']['id'])['_awaiting_input']));
    }

    public function testDuplicateStepOrderIsNotSkippedAfterAnAnswer() {
        // Give the step after the email question the same order as the question.
        $this->exec('UPDATE flow_steps SET step_order = 2 WHERE id = 4');
        $phone = '+15550102121';
        $this->send(1, '1', 1, $phone);
        $r = $this->send(1, 'dup@example.com', 1, $phone);
        $this->assertSame(['Please describe the problem in one message.'], $this->texts($r));
        $this->exec('UPDATE flow_steps SET step_order = 3 WHERE id = 4');
    }

    public function testSeededPausedConversationResumes() {
        // Seed: conversation 4 (Fatima) is waiting for her email in the lead flow.
        $bot = (new Bot())->find(2, 1);
        $r = (new MessageProcessor(1))->handleInbound((new Channel())->find(2, 1), $bot, '+15557001004', 'fatima@khanretail.com');
        $this->assertSame('media', $r['replies'][0]['type']);
        $this->assertContains('Fatima', $r['replies'][0]['content']);
    }

    public function testBranchConditionComparesNumbers() {
        $phone = '+15550103030';
        $this->send(2, 'budget', 1, $phone);
        $r = $this->send(2, '2,500', 1, $phone);
        $this->assertContains('Enterprise plan', $r['replies'][0]['content']);

        $phone = '+15550103031';
        $this->send(2, 'budget', 1, $phone);
        $r = $this->send(2, '300', 1, $phone);
        $this->assertContains('Starter or Professional', $r['replies'][0]['content']);
    }

    public function testMessageContainsAndMediaStepWithVariables() {
        $phone = '+15550104040';
        $r = $this->send(2, 'Can I get PRICING info?', 1, $phone);
        $this->assertContains('What is your name?', $this->texts($r));
        $this->send(2, 'Omar', 1, $phone);
        $r = $this->send(2, 'omar@example.com', 1, $phone);
        $this->assertSame('media', $r['replies'][0]['type']);
        $this->assertSame('https://techcorp.example.com/pricing.pdf', $r['replies'][0]['media_url']);
        $this->assertContains('Omar', $r['replies'][0]['content']);
    }

    public function testButtonTrigger() {
        $r = $this->send(2, 'book_demo', 1, '+15550105050');
        $this->assertContains('techcorp.example.com/demo', $r['replies'][0]['content']);
    }

    public function testAiFallbackAnswersFromKnowledgeBase() {
        $r = $this->send(1, 'What is your refund policy?', 1, '+15550106060');
        $this->assertSame('ai', $r['replies'][0]['triggered_by']);
        $this->assertContains('full refund within 14 days', $r['replies'][0]['content']);
    }

    public function testAiFallbackWithoutKnowledgeSuggestsTopics() {
        $r = $this->send(1, 'qwertyuiop zxcvbnm', 1, '+15550107070');
        $this->assertSame('ai', $r['replies'][0]['triggered_by']);
        $this->assertContains('I can help with', $r['replies'][0]['content']);
    }

    public function testBotWithAiDisabledUsesDefaultFlow() {
        $r = $this->send(5, 'random question', 2, '+15550108080');
        $this->assertSame(['Sorry, I did not understand. Type START to begin.'], $this->texts($r));
    }

    public function testMaxAnswerLengthIsApplied() {
        $this->exec('UPDATE bots SET ai_max_length = 50, ai_tone = "professional" WHERE id = 1');
        $r = $this->send(1, 'How much does your service cost?', 1, '+15550109090');
        $this->assertTrue(mb_strlen($r['replies'][0]['content']) <= 50, 'answer longer than 50 chars');
    }

    public function testMessagesAreLoggedInBothDirections() {
        $before = (int) $this->scalar('SELECT COUNT(*) FROM messages WHERE tenant_id = 1');
        $r = $this->send(1, 'menu', 1, '+15550110000');
        $after = (int) $this->scalar('SELECT COUNT(*) FROM messages WHERE tenant_id = 1');
        $this->assertSame($before + 2, $after);
        $this->assertSame('inbound', $this->scalar('SELECT direction FROM messages WHERE id = ?', [$r['inbound_message_id']]));
        $this->assertSame('flow', $this->scalar('SELECT triggered_by FROM messages WHERE id = ?', [$r['replies'][0]['id']]));
    }

    public function testAssignedConversationSilencesTheBot() {
        $phone = '+15550111111';
        $r = $this->send(1, 'hello', 1, $phone);
        (new Conversation())->update($r['conversation']['id'], ['assigned_to' => 3], 1);
        $r = $this->send(1, 'hello again', 1, $phone);
        $this->assertTrue($r['handed_off']);
        $this->assertSame([], $r['replies']);
    }

    public function testPausedBotStoresMessageButDoesNotReply() {
        $this->exec("UPDATE bots SET status = 'paused' WHERE id = 1");
        $r = $this->send(1, 'hello', 1, '+15550112222');
        $this->assertSame([], $r['replies']);
        $this->assertNotNull($r['inbound_message_id']);
    }

    public function testBlockedContactIsRejected() {
        $this->exec("UPDATE contacts SET status = 'blocked' WHERE id = 1");
        $bot = (new Bot())->find(1, 1);
        $r = (new MessageProcessor(1))->handleInbound((new Channel())->find(1, 1), $bot, '+15557001001', 'hi');
        $this->assertFalse($r['ok']);
        $this->assertSame(403, $r['status']);
    }

    public function testSimulatorIsIsolatedFromWebhooksQuotaAndBroadcasts() {
        $this->exec("UPDATE webhooks SET status = 'active' WHERE id = 1");
        $calls = 0;
        WebhookDispatcher::$fakeTransport = function () use (&$calls) {
            $calls++;
            return ['ok' => true, 'code' => 200, 'body' => ''];
        };
        putenv('WEBHOOK_ALLOW_PRIVATE=true');
        $used = (new Message())->countThisMonth(1);
        $contacts = QuotaService::used(1, 'contacts');

        $this->exec("UPDATE bots SET status = 'draft' WHERE id = 3");
        $bot = (new Bot())->find(3, 1);
        $r = (new MessageProcessor(1))->handleInbound((new Channel())->find(1, 1), $bot, '+15550000000', 'What is your refund policy?', ['simulation' => true]);
        putenv('WEBHOOK_ALLOW_PRIVATE');

        $this->assertTrue($r['ok']);
        $this->assertSame(1, count($r['replies']), 'draft bot answers in the simulator');
        $this->assertSame(0, $calls, 'no outgoing webhooks for simulator traffic');
        $this->assertSame('unsubscribed', $r['contact']['status'], 'simulator contact never receives broadcasts');
        $this->assertSame($used, (new Message())->countThisMonth(1), 'not counted against the message quota');
        $this->assertSame($contacts, QuotaService::used(1, 'contacts'));
    }

    public function testEveryExtraReplyNeedsQuota() {
        $this->exec("UPDATE bots SET status = 'active' WHERE id = 1");
        $used = (new Message())->countThisMonth(1);
        // Flow 2 ("1") sends 2 replies; allow inbound + only the first reply.
        $this->setTinyPlan(1, ['max_messages_per_month' => $used + 2]);
        $r = $this->send(1, '1', 1, '+15550113333');
        $this->assertSame(1, count($r['replies']));
        $this->assertSame($used + 2, (new Message())->countThisMonth(1), 'never exceeds the plan limit');
    }
}
